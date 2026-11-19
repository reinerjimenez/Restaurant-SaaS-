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
$ruta = tempnam(sys_get_temp_dir(), 'mesora_pdf_');
if ($ruta === false) throw new RuntimeException('No se pudo crear archivo temporal.');
file_put_contents($ruta . '.pdf', $pdf);
echo 'OK PDF/QR: ' . strlen($pdf) . " bytes; archivo temporal " . $ruta . ".pdf\n";
