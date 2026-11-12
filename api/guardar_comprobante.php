<?php
/**
 * Guarda el comprobante y registra el cobro en una sola transacción.
 */
require_once '../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
$pedido_id = filter_var($input['pedido_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tipo = $input['tipo'] ?? '';
$series = [
    'factura' => ['correlativo_factura', 'serie_factura', 'F001'],
    'boleta' => ['correlativo_boleta', 'serie_boleta', 'B001'],
    'simple' => ['correlativo_simple', 'serie_simple', 'T001'],
];
if (!$pedido_id || !is_string($tipo) || !isset($series[$tipo])) {
    jsonResponse(false, null, 'Pedido o tipo de comprobante inválido.');
}
$campos = ['tipo_documento' => 3, 'numero_documento' => 11, 'nombre_cliente' => 200,
    'direccion' => 300, 'distrito' => 100, 'provincia' => 100, 'departamento' => 100, 'notas' => 65535];
$datos = [];
foreach ($campos as $campo => $maximo) {
    $valor = $input[$campo] ?? '';
    if (!is_string($valor) || mb_strlen($valor) > $maximo || ($campo === 'notas' && strlen($valor) > $maximo)) {
        jsonResponse(false, null, 'Datos del comprobante inválidos.');
    }
    $datos[$campo] = trim($valor);
}
$tipo_documento = strtolower($datos['tipo_documento']);
$numero_documento = $datos['numero_documento'];
if ($tipo !== 'simple' && (!in_array($tipo_documento, ['dni', 'ruc'], true)
    || !ctype_digit($numero_documento) || strlen($numero_documento) !== ($tipo_documento === 'dni' ? 8 : 11)
    || $datos['nombre_cliente'] === '')) {
    jsonResponse(false, null, 'Indica un DNI o RUC válido y el nombre del cliente.');
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

    $stmt = $db->prepare('SELECT * FROM facturacion_config WHERE restaurante_id = ? FOR UPDATE');
    $stmt->execute([$restaurante_id]);
    $configuracion = $stmt->fetch();
    if (!$configuracion) {
        $db->prepare('INSERT INTO facturacion_config (restaurante_id) VALUES (?)')->execute([$restaurante_id]);
        $stmt->execute([$restaurante_id]);
        $configuracion = $stmt->fetch();
    }

    // Los nombres de columnas provienen únicamente de la lista fija de series.
    [$campo_correlativo, $campo_serie, $serie_defecto] = $series[$tipo];
    $correlativo = (int)$configuracion[$campo_correlativo] + 1;
    $serie = $configuracion[$campo_serie] ?: $serie_defecto;
    $numero = $serie . '-' . str_pad((string)$correlativo, 5, '0', STR_PAD_LEFT);
    $db->prepare("UPDATE facturacion_config SET $campo_correlativo = ? WHERE restaurante_id = ?")
        ->execute([$correlativo, $restaurante_id]);

    // Redondeo a céntimos; el IGV es la diferencia respecto del total.
    $base = $tipo === 'simple' ? $total : intdiv($total * 100 + 59, 118);
    $monto = decimal_centimos($total);
    $fecha_cobro = persistir_cobro($db, $restaurante_id, (int)$turno['id'], $usuario_id, $pedido, $pagos, $total,
        $descuento, $cargo_extra, $datos['notas']);
    $stmt = $db->prepare('INSERT INTO comprobantes
        (restaurante_id, pedido_id, usuario_id, tipo, serie, correlativo, numero_comprobante,
         tipo_documento, numero_documento, nombre_cliente, direccion_cliente, distrito, provincia, departamento,
         subtotal, igv, total, descuento, cargo_extra, items_json, pagos_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $restaurante_id, $pedido_id, $usuario_id, $tipo, $serie, $correlativo, $numero,
        $tipo === 'simple' ? null : $tipo_documento, $tipo === 'simple' ? null : $numero_documento,
        $datos['nombre_cliente'] ?: 'Cliente', $datos['direccion'] ?: null,
        $datos['distrito'] ?: null, $datos['provincia'] ?: null, $datos['departamento'] ?: null,
        decimal_centimos($base), decimal_centimos($total - $base), $monto,
        decimal_centimos($descuento), decimal_centimos($cargo_extra),
        json_encode(items_comprobante($db, $pedido_id), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        json_encode($pagos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $fecha_cobro,
    ]);
    $comprobante_id = $db->lastInsertId();

    registrarAuditoria($db, 'Emitió comprobante y cobró pedido',
        "Comprobante #$comprobante_id ($numero) · Pedido #$pedido_id · Turno #{$turno['id']} · S/ 0.00 → S/ $monto", true);
    $db->commit();
    jsonResponse(true, [
        'comprobante_id' => $comprobante_id, 'numero_comprobante' => $numero, 'tipo' => $tipo, 'total_cobrado' => $monto,
    ], '¡Comprobante emitido y cobro registrado!');
} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al guardar el comprobante');
}
