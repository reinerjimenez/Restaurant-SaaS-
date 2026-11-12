<?php
/**
 * api/eliminar_item.php — Elimina un ítem de un pedido activo
 * Solo para admin / superadmin
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input         = json_decode($GLOBALS['_RAW_INPUT'] ?? ($GLOBALS['_RAW_INPUT'] = file_get_contents('php://input')), true);
$itemId        = (int)($input['item_id']    ?? 0);
$restauranteId = $_SESSION['restaurante_id'];

if (!$itemId) jsonResponse(false, null, 'item_id requerido');

$db = getDB();

try {
    $db->beginTransaction();
    bloquear_restaurante($db, $restauranteId);

    // Ubicar el pedido; el bloqueo de restaurante serializa las operaciones
    // de comanda antes de bloquear el pedido y luego modificar el ítem.
    $stItem = $db->prepare("
        SELECT pi.id, pi.subtotal, pi.pedido_id, pi.nombre_producto
        FROM pedido_items pi
        JOIN pedidos p ON p.id = pi.pedido_id
        WHERE pi.id = ?
          AND p.restaurante_id = ?
    ");
    $stItem->execute([$itemId, $restauranteId]);
    $item = $stItem->fetch();

    if (!$item) {
        $db->rollBack();
        jsonResponse(false, null, 'Ítem no encontrado o pedido ya no activo');
    }
    $pedido = bloquear_pedido($db, $restauranteId, (int)$item['pedido_id'], 'activo');
    $subtotal_anterior = decimal_centimos(subtotal_pedido($db, (int)$pedido['id']));

    // Eliminar opciones relacionadas primero (FK)
    $db->prepare("DELETE FROM pedido_item_opciones WHERE item_id = ?")->execute([$itemId]);

    // Eliminar el ítem
    $db->prepare("DELETE FROM pedido_items WHERE id = ?")->execute([$itemId]);

    // Recalcular total del pedido
    $nuevoTotal = decimal_centimos(subtotal_pedido($db, (int)$pedido['id']));

    $db->prepare("UPDATE pedidos SET total = ? WHERE id = ? AND restaurante_id = ?")
        ->execute([$nuevoTotal, $pedido['id'], $restauranteId]);

    registrarAuditoria($db, 'Eliminó ítem del pedido',
        "Ítem #$itemId · Pedido #{$pedido['id']} · S/ $subtotal_anterior → S/ $nuevoTotal", true);
    $db->commit();
    jsonResponse(true, ['nuevo_total' => $nuevoTotal], 'Ítem eliminado correctamente');

} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al eliminar ítem');
}
