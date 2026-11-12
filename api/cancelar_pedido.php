<?php
/**
 * api/cancelar_pedido.php — Cancela un pedido activo y libera la mesa
 * Solo para admin/superadmin
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input         = json_decode($GLOBALS['_RAW_INPUT'] ?? ($GLOBALS['_RAW_INPUT'] = file_get_contents('php://input')), true);
$pedidoId      = (int)($input['id'] ?? 0);
$restauranteId = $_SESSION['restaurante_id'];

if (!$pedidoId) jsonResponse(false, null, 'ID requerido');

$db = getDB();

try {
    $db->beginTransaction();
    bloquear_restaurante($db, $restauranteId);
    $pedido = bloquear_pedido($db, $restauranteId, $pedidoId, 'activo');

    $mesaNumero = null;
    if ($pedido['mesa_id']) {
        $stMesaNumero = $db->prepare("SELECT numero FROM mesas WHERE id = ? AND restaurante_id = ?");
        $stMesaNumero->execute([$pedido['mesa_id'], $restauranteId]);
        $mesaNumero = $stMesaNumero->fetchColumn();
    }

    // Seguridad: atención y encargado solo pueden cancelar si el pedido no tiene ítems.
    if (in_array($_SESSION['rol'], ['atencion', 'encargado'], true)) {
        $stCheckItems = $db->prepare("SELECT COUNT(id) FROM pedido_items WHERE pedido_id = ?");
        $stCheckItems->execute([$pedidoId]);
        if ($stCheckItems->fetchColumn() > 0) {
            $db->rollBack();
            jsonResponse(false, null, 'No puedes cancelar esta comanda porque ya tiene platos agregados. Pide ayuda a un Administrador.');
        }
    }

    // Cancelar pedido
    $stCan = $db->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = ? AND restaurante_id = ?");
    $stCan->execute([$pedidoId, $restauranteId]);

    // Liberar mesa
    liberar_mesa($db, $restauranteId, $pedido['mesa_id']);
    $ubicacion = $mesaNumero ? 'Mesa ' . $mesaNumero : 'Para llevar';
    registrarAuditoria($db, 'Canceló comanda', "Pedido #$pedidoId · $ubicacion", true);
    $db->commit();
    jsonResponse(true, null, 'Pedido cancelado y mesa liberada');

} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al cancelar pedido');
}
