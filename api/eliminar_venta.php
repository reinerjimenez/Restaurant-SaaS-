<?php
/**
 * Cancela una venta y registra su reverso sin borrar el histórico.
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
    $motivo = 'Venta cancelada desde historial';
    $reverso = revertir_pagos($db, $pedido, (int)$turno['id'], $usuario_id, $motivo);

    $db->prepare('UPDATE comprobantes SET anulado = 1, motivo_anulacion = ?
        WHERE pedido_id = ? AND restaurante_id = ? AND COALESCE(anulado, 0) = 0')
        ->execute([$motivo, $pedido_id, $restaurante_id]);
    $db->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = ? AND restaurante_id = ?")
        ->execute([$pedido_id, $restaurante_id]);

    $pagos = implode(', ', $reverso['pagos']);
    registrarAuditoria($db, 'Canceló venta y registró reverso',
        "Pedido #$pedido_id · Turno #{$turno['id']} · Pagos $pagos · S/ {$reverso['monto']} → S/ 0.00 · $motivo", true);
    $db->commit();
    jsonResponse(true, null, 'Venta cancelada y reverso registrado correctamente.');
} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al cancelar venta');
}
