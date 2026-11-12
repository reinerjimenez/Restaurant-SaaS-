<?php
/**
 * Registra un cobro sin comprobante.
 */
require_once '../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
$pedido_id = filter_var($input['pedido_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$notas = $input['notas'] ?? '';
if (!$pedido_id || !is_string($notas) || strlen($notas) > 65535) {
    jsonResponse(false, null, 'Pedido o notas inválidos.');
}

$restaurante_id = (int)$_SESSION['restaurante_id'];
$usuario_id = (int)$_SESSION['usuario_id'];
$db = getDB();

try {
    $pagos = normalizar_pagos($input['pagos'] ?? null);
    $db->beginTransaction();
    bloquear_restaurante($db, $restaurante_id);
    $turno = exigir_turno_abierto($db, $restaurante_id);
    $pedido = bloquear_pedido($db, $restaurante_id, $pedido_id, 'activo');
    [$total, $descuento, $cargo_extra] = calcular_cobro($db, $pedido, $input, $pagos);

    persistir_cobro($db, $restaurante_id, (int)$turno['id'], $usuario_id, $pedido, $pagos, $total, $descuento, $cargo_extra, trim($notas));
    $monto = decimal_centimos($total);
    registrarAuditoria($db, 'Cobró pedido', "Pedido #$pedido_id · Turno #{$turno['id']} · S/ 0.00 → S/ $monto", true);
    $db->commit();
    jsonResponse(true, ['total_cobrado' => $monto], '¡Cobro registrado exitosamente!');
} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al registrar el cobro');
}
