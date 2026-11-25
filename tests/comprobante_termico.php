<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../includes/comprobante_termico.php';

function comprobar_termico(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$comp = [
    'tipo' => 'factura', 'numero_comprobante' => 'F001-42', 'anulado' => 1,
    'created_at' => '2026-11-25 14:30:00', 'cajero_nombre' => "María\x1b\x1d\x00\x7f\nCaja",
    'rest_razon' => 'Restaurante Peña', 'rest_ruc' => '20000000000', 'rest_dir' => 'Av. Lima',
    'rest_tel' => null, 'tipo_documento' => 'ruc', 'numero_documento' => '20000000001',
    'nombre_cliente' => 'Compañía de prueba', 'direccion_cliente' => 'Av. Perú',
    'distrito' => 'Ate', 'provincia' => 'Lima', 'departamento' => 'Lima',
    'subtotal' => '90.00', 'igv' => '16.20', 'total' => '106.20',
    'cargo_extra' => '1.00', 'descuento' => '2.00', 'pie_mensaje' => null,
];
$producto = [
    'producto_nombre' => str_repeat('A', 85), 'opciones_texto' => 'Ají y limón',
    'cantidad' => 2, 'precio_unitario' => 25, 'subtotal' => 50,
];
$items = comprobante_agrupar_items([$producto, $producto]);
$pagos = [['metodo' => 'efectivo', 'monto' => '100.00'], ['metodo' => 'yape', 'monto' => '6.20']];
$ticket = comprobante_escpos($comp, $items, $pagos);
comprobar_termico(str_starts_with($ticket, "\x1b@\x1bM\x00\x1bt\x02\x1ba\x01"), 'Inicialización ESC/POS inválida.');
comprobar_termico(str_contains($ticket, "\x1bE\x01TOTAL:"), 'Falta el énfasis del total.');

$texto = preg_replace('/\x1b(?:@|[tMaE][\x00-\x02])/', '', $ticket);
comprobar_termico(!preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $texto), 'Quedaron caracteres de control de los datos.');
comprobar_termico(str_contains($texto, "Pe\xa4a"), 'La ñ debe ocupar un byte CP850.');
foreach (explode("\n", $texto) as $linea) {
    comprobar_termico(strlen($linea) <= 48, 'Una línea excede el perfil de 48 columnas.');
}

$legible = iconv('CP850', 'UTF-8', $texto);
foreach (['F001-42', 'SIN VALIDEZ TRIBUTARIA', 'ANULADO', 'María Caja', 'Compañía', 'Ate - Lima - Lima', 'Ají y limón'] as $dato) {
    comprobar_termico(str_contains($legible, $dato), 'Falta el dato ' . $dato);
}
comprobar_termico((bool) preg_match('/^ +4 +25\.00 +100\.00$/m', $legible), 'Los items agrupados no concilian.');
foreach (['Cargo extra:' => 'S/ 1.00', 'Descuento:' => '-S/ 2.00', 'TOTAL:' => 'S/ 106.20', 'Efectivo:' => 'S/ 100.00', 'Yape/Plin:' => 'S/ 6.20'] as $label => $importe) {
    $linea = $label . str_repeat(' ', 48 - strlen($label) - strlen($importe)) . $importe;
    comprobar_termico(str_contains($legible, $linea . "\n"), 'Importe omitido o desalineado para ' . $label);
}
$simple = comprobante_escpos(array_replace($comp, ['tipo' => 'simple', 'anulado' => 0]), [], []);
foreach (['IGV:', 'ANULADO', 'Yape/Plin:', 'RUC: 20000000001'] as $dato) {
    comprobar_termico(!str_contains($simple, $dato), 'Dato incorrecto para un ticket simple: ' . $dato);
}

echo "OK: ESC/POS, datos guardados, ancho, codificación CP850 y limpieza de controles.\n";
