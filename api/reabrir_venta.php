<?php
/**
 * Reabre un pedido cobrado registrando el reverso en la caja actual.
 */
require_once '../config/db.php';
requireRole(['admin', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
$pedido_id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$pedido_id) jsonResponse(false, null, 'ID requerido');

$restaurante_id = (int)$_SESSION['restaurante_id'];
$usuario_id = (int)$_SESSION['usuario_id'];
$db = getDB();

try {
    $db->beginTransaction();
    bloquear_restaurante($db, $restaurante_id);
    $turno = exigir_turno_abierto($db, $restaurante_id);
    $pedido = bloquear_pedido($db, $restaurante_id, $pedido_id, 'cobrado');

    if ($pedido['mesa_id']) {
        $mesa = $db->prepare('SELECT estado FROM mesas WHERE id = ? AND restaurante_id = ? AND activo = 1 FOR UPDATE');
        $mesa->execute([$pedido['mesa_id'], $restaurante_id]);
        $activo = $db->prepare("SELECT id FROM pedidos WHERE mesa_id = ? AND restaurante_id = ? AND estado = 'activo' LIMIT 1");
        $activo->execute([$pedido['mesa_id'], $restaurante_id]);
        if ($mesa->fetchColumn() !== 'libre' || $activo->fetchColumn()) {
            throw new DomainException('La mesa no está disponible. Resuelve su estado antes de reabrir la venta.');
        }
    }

    $motivo = 'Pedido reabierto para modificación';
    $reverso = revertir_pagos($db, $pedido, (int)$turno['id'], $usuario_id, $motivo);
    $db->prepare('UPDATE comprobantes SET anulado = 1, motivo_anulacion = ?
        WHERE pedido_id = ? AND restaurante_id = ? AND COALESCE(anulado, 0) = 0')
        ->execute([$motivo, $pedido_id, $restaurante_id]);

    // El pedido activo guarda el subtotal; los ajustes siguen separados.
    $subtotal = decimal_centimos(subtotal_pedido($db, $pedido_id));
    $db->prepare("UPDATE pedidos SET estado = 'activo', total = ? WHERE id = ? AND restaurante_id = ?")
        ->execute([$subtotal, $pedido_id, $restaurante_id]);
    if ($pedido['mesa_id']) {
        $db->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ? AND restaurante_id = ?")
            ->execute([$pedido['mesa_id'], $restaurante_id]);
    }

    $pagos = implode(', ', $reverso['pagos']);
    registrarAuditoria($db, 'Reabrió venta y registró reverso',
        "Pedido #$pedido_id · Turno #{$turno['id']} · Pagos $pagos · S/ {$reverso['monto']} → S/ 0.00 · $motivo", true);
    $db->commit();
    jsonResponse(true, null, 'Pedido reabierto correctamente.');
} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al reabrir venta');
}
