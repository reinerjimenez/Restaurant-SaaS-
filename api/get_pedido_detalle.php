<?php
/**
 * api/get_pedido_detalle.php — Detalle completo de un pedido
 * Usado por historial.php para el modal de detalle
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../config/db.php';
requireRole(['admin', 'encargado', 'superadmin', 'atencion']);

$pedidoId      = (int)($_GET['id'] ?? 0);
$restauranteId = $_SESSION['restaurante_id'];

if (!$pedidoId) jsonResponse(false, null, 'ID requerido');

$db = getDB();

// Pedido (incluye descuento y cargo_extra)
$stPed = $db->prepare("
    SELECT pe.id, pe.tipo, pe.estado, pe.total,
           pe.descuento, pe.cargo_extra,
           pe.created_at,
           m.numero AS mesa_numero
    FROM pedidos pe
    LEFT JOIN mesas m ON m.id = pe.mesa_id AND m.restaurante_id = pe.restaurante_id
    WHERE pe.id = ? AND pe.restaurante_id = ?
");
$stPed->execute([$pedidoId, $restauranteId]);
$pedido = $stPed->fetch();
if (!$pedido) jsonResponse(false, null, 'Pedido no encontrado');

// Items — LEFT JOIN para que productos eliminados sigan apareciendo con nombre snapshot
$stItems = $db->prepare("
    SELECT pi.id, pi.cantidad, pi.precio_unitario, pi.subtotal, pi.notas, pi.estado,
           COALESCE(pi.nombre_producto, pr.nombre, '(Producto eliminado)') AS nombre,
           GROUP_CONCAT(ov.valor SEPARATOR ' · ') AS opciones
    FROM pedido_items pi
    LEFT JOIN productos pr ON pr.id = pi.producto_id
    LEFT JOIN pedido_item_opciones pio ON pio.item_id = pi.id
    LEFT JOIN opciones_valor ov ON ov.id = pio.valor_id
    WHERE pi.pedido_id = ?
    GROUP BY pi.id
    ORDER BY pi.created_at ASC
");
$stItems->execute([$pedidoId]);
$pedido['items'] = $stItems->fetchAll();

// Pagos efectivos y su cobrador, independientemente del creador del pedido.
$stPagos = $db->prepare("
    SELECT pa.metodo, pa.monto, pa.referencia, pa.created_at,
           pa.usuario_id AS cobrador_id, COALESCE(u.nombre, 'Usuario no disponible') AS cobrador
    FROM pagos pa
    JOIN pedidos pe ON pe.id = pa.pedido_id
    LEFT JOIN usuarios u ON u.id = pa.usuario_id
    WHERE pa.pedido_id = ? AND pe.restaurante_id = ? AND COALESCE(pa.anulado, 0) = 0
    ORDER BY pa.id
");
$stPagos->execute([$pedidoId, $restauranteId]);
$pedido['pagos'] = $stPagos->fetchAll();

jsonResponse(true, $pedido, 'OK');
