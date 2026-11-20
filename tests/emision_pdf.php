<?php
/** Prueba local de representación PDF y QR, sin BD ni red. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/sunat_motor.php';
require_once __DIR__ . '/../includes/emision_pdf.php';

$documento = [
    'ambiente' => 'pruebas', 'tipo' => 'boleta', 'serie' => 'B001', 'correlativo' => 7,
    'estado' => 'prueba_aceptada', 'respuesta_mensaje' => 'Ensayo local sin envío SUNAT.',
];
$snapshot = [
    'emisor' => ['ruc'=>'20123456786','razon_social'=>'Restaurante de ensayo','direccion'=>'Av. Prueba 123'],
    'cliente' => ['tipo_documento'=>'dni','numero_documento'=>'12345678','nombre'=>'Cliente de ensayo'],
    'tipo'=>'boleta','serie'=>'B001','correlativo'=>7,'fecha'=>'2026-11-19','moneda'=>'PEN','tasa_igv'=>'10.50','total'=>'10.00',
    'items'=>[['descripcion'=>'Plato de ensayo','cantidad'=>1,'precio_unitario'=>'10.00','subtotal'=>'10.00']],
];
$qr = emision_pdf_qr($documento, $snapshot);
if ($qr !== '20123456786|03|B001|7|0.95|10.00|2026-11-19|1|12345678|') throw new RuntimeException('QR fiscal no concilia.');
$pdf = emision_generar_pdf($documento, $snapshot);
if (!str_starts_with($pdf, '%PDF-') || strlen($pdf) < 1000) throw new RuntimeException('PDF inválido.');
$directorio = __DIR__ . '/../tmp/pdfs';
if (!is_dir($directorio) && !mkdir($directorio, 0700, true)) throw new RuntimeException('No se pudo preparar la carpeta de ejemplos.');
$casos = [
    'boleta_prueba' => [$documento, $snapshot],
    'boleta_pendiente' => [array_replace($documento, ['ambiente'=>'produccion','estado'=>'pendiente']), $snapshot],
    'boleta_rechazada' => [array_replace($documento, ['ambiente'=>'produccion','estado'=>'rechazado', 'respuesta_mensaje'=>'Rechazo de ensayo.']), $snapshot],
    'factura_aceptada' => [array_replace($documento, ['ambiente'=>'produccion','tipo'=>'factura','serie'=>'F001','estado'=>'aceptado']),
        array_replace($snapshot, ['tipo'=>'factura','serie'=>'F001','emisor'=>array_replace($snapshot['emisor'],
            ['razon_social'=>str_repeat('Restaurante de ensayo con razón social extensa ', 4)]),
            'cliente'=>['tipo_documento'=>'ruc','numero_documento'=>'20500000008','nombre'=>str_repeat('Cliente corporativo de ensayo ', 5)]] )],
    'boleta_descuento' => [$documento, array_replace($snapshot, ['descuento'=>'1.00','cargo_extra'=>'0.00','total'=>'9.00'])],
    'factura_cargo' => [array_replace($documento, ['tipo'=>'factura','serie'=>'F001']),
        array_replace($snapshot, ['tipo'=>'factura','serie'=>'F001','descuento'=>'0.00','cargo_extra'=>'1.00','total'=>'11.00',
            'cliente'=>['tipo_documento'=>'ruc','numero_documento'=>'20500000008','nombre'=>'Cliente corporativo de ensayo']])],
];
$casos['factura_aceptada'][1]['items'][0]['descripcion'] = str_repeat('Producto de ensayo de descripción extensa con opciones ', 8);
foreach ($casos as $nombre => [$estado_documento, $datos]) {
    $bytes = emision_generar_pdf($estado_documento, $datos);
    if (!str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1000) throw new RuntimeException("PDF inválido: $nombre.");
    if (file_put_contents($directorio . '/' . $nombre . '.pdf', $bytes) !== strlen($bytes)) {
        throw new RuntimeException("No se pudo guardar el PDF: $nombre.");
    }
}
if (emision_pdf_estado($casos['boleta_rechazada'][0])[0] !== 'RECHAZADO POR SUNAT - SIN VALIDEZ TRIBUTARIA') {
    throw new RuntimeException('El PDF rechazado se anuncia incorrectamente.');
}
try {
    emision_generar_pdf($documento, array_replace($snapshot, ['items'=>[array_replace($snapshot['items'][0], ['afectacion_igv'=>'exonerada'])]]));
    throw new RuntimeException('No se rechazó un PDF sin desglose de afectaciones.');
} catch (DomainException $e) {
    if (!str_contains($e->getMessage(), 'desglose')) throw $e;
}
echo 'OK PDF/QR: seis ejemplos generados en ' . $directorio . "; falta inspección visual.\n";
