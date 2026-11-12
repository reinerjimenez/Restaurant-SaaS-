<?php
/**
 * Envía ítems a cocina de forma idempotente.
 */
require_once '../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
$pedido_id = filter_var($input['pedido_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$operacion_id = $input['operacion_id'] ?? null;
$items = $input['items'] ?? null;
if (!$pedido_id || !is_string($operacion_id) || !preg_match('/\A[a-f0-9]{32}\z/', $operacion_id)
    || !is_array($items) || !array_is_list($items) || !$items || count($items) > 100) {
    jsonResponse(false, null, 'Datos de comanda inválidos. Actualiza la página si es necesario.');
}
$restaurante_id = (int)$_SESSION['restaurante_id'];
$usuario_id = (int)$_SESSION['usuario_id'];
$db = getDB();

try {
    $huella = hash('sha256', json_encode([$pedido_id, $items], JSON_THROW_ON_ERROR));
    $db->beginTransaction();
    bloquear_restaurante($db, $restaurante_id);
    $operacion = $db->prepare('SELECT huella, resultado_json FROM envios_comanda
        WHERE restaurante_id = ? AND usuario_id = ? AND operacion_id = ? FOR UPDATE');
    $operacion->execute([$restaurante_id, $usuario_id, $operacion_id]);
    $guardado = $operacion->fetch();
    $operacion_consultada = true;
    if ($guardado) {
        if (!hash_equals($guardado['huella'], $huella)) {
            throw new DomainException('Esta clave ya pertenece a otro envío de comanda.');
        }
        $resultado = json_decode($guardado['resultado_json'], true, 512, JSON_THROW_ON_ERROR);
        $db->commit();
        jsonResponse(true, $resultado, 'Ítems añadidos correctamente.');
    }

    bloquear_pedido($db, $restaurante_id, $pedido_id, 'activo');
    $producto = $db->prepare('SELECT precio, nombre FROM productos WHERE id = ? AND restaurante_id = ? AND activo = 1');
    $insertar = $db->prepare("INSERT INTO pedido_items
        (pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal, notas, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')");
    $opcion = $db->prepare('SELECT ov.id FROM opciones_valor ov JOIN opciones_grupo og ON og.id = ov.grupo_id
        WHERE ov.id = ? AND ov.grupo_id = ? AND og.producto_id = ?');
    $insertar_opcion = $db->prepare('INSERT INTO pedido_item_opciones (item_id, grupo_id, valor_id) VALUES (?, ?, ?)');

    $total_agregado = 0;
    foreach ($items as $item) {
        $producto_id = filter_var($item['producto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cantidad = filter_var($item['cantidad'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        $selecciones = $item['selecciones'] ?? [];
        $notas = $item['notas'] ?? '';
        if (!is_array($item) || !$producto_id || !$cantidad || !is_array($selecciones) || !array_is_list($selecciones)
            || !is_string($notas) || strlen($notas) > 65535) {
            throw new DomainException('Producto, cantidad u opciones inválidos.');
        }
        $producto->execute([$producto_id, $restaurante_id]);
        $fila = $producto->fetch();
        if (!$fila) {
            throw new DomainException('Un producto ya no está disponible. Actualiza la comanda.');
        }

        $precio = importe_centimos($fila['precio']);
        $subtotal = $precio * $cantidad;
        if ($subtotal > 99999999) { // DECIMAL(8,2) de pedido_items
            throw new DomainException('El subtotal de un producto supera el importe permitido.');
        }
        $insertar->execute([$pedido_id, $producto_id, $fila['nombre'], $cantidad, decimal_centimos($precio),
            decimal_centimos($subtotal), trim($notas) ?: null]);
        $item_id = $db->lastInsertId();

        foreach ($selecciones as $seleccion) {
            $grupo_id = filter_var($seleccion['grupo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $valor_id = filter_var($seleccion['valor_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_array($seleccion) || !$grupo_id || !$valor_id) {
                throw new DomainException('Opción inválida.');
            }
            $opcion->execute([$valor_id, $grupo_id, $producto_id]);
            if (!$opcion->fetchColumn()) {
                throw new DomainException('Una opción ya no está disponible para este producto.');
            }
            $insertar_opcion->execute([$item_id, $grupo_id, $valor_id]);
        }

        $total_agregado += $subtotal;
    }

    $db->prepare('UPDATE pedidos SET total = ? WHERE id = ? AND restaurante_id = ?')
        ->execute([decimal_centimos(subtotal_pedido($db, $pedido_id)), $pedido_id, $restaurante_id]);

    $resultado = ['total_agregado' => decimal_centimos($total_agregado)];
    $db->prepare('INSERT INTO envios_comanda (restaurante_id, usuario_id, operacion_id, huella, resultado_json)
        VALUES (?, ?, ?, ?, ?)')
        ->execute([$restaurante_id, $usuario_id, $operacion_id, $huella, json_encode($resultado, JSON_THROW_ON_ERROR)]);
    registrarAuditoria($db, 'Envió pedido a cocina', count($items) . " ítems · Pedido #$pedido_id", true);

    $db->commit();
    jsonResponse(true, $resultado, 'Ítems añadidos correctamente.');
} catch (Throwable $e) {
    // Solo un rechazo funcional confirma que el envío no se guardó.
    if ($e instanceof DomainException) {
        if ($db->inTransaction()) $db->rollBack();
        jsonResponse(false, ['envio_rechazado' => !empty($operacion_consultada) && !$guardado], $e->getMessage());
    }
    error_financiero($db, $e, 'Error al agregar ítems');
}
