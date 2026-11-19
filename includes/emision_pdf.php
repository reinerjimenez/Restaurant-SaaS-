<?php
/** Representación PDF privada de un documento fiscal preparado por MESORA. */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

function emision_pdf_html(mixed $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construye los campos públicos del QR de la representación impresa.
 * No incluye claves, XML ni datos que no aparezcan ya en el comprobante.
 */
function emision_pdf_qr(array $documento, array $snapshot): string {
    $total = sunat_centimos((string)$snapshot['total']);
    $tasa = sunat_centimos((string)$snapshot['tasa_igv']);
    $igv = intdiv($total * $tasa + intdiv(10000 + $tasa, 2), 10000 + $tasa);
    $tipo = $snapshot['tipo'] === 'factura' ? '01' : '03';
    $cliente_tipo = $snapshot['cliente']['tipo_documento'] === 'ruc' ? '6' : '1';
    return implode('|', [
        $snapshot['emisor']['ruc'], $tipo, $documento['serie'], $documento['correlativo'],
        sunat_decimal($igv), sunat_decimal($total), $snapshot['fecha'], $cliente_tipo,
        $snapshot['cliente']['numero_documento'], '',
    ]);
}

function emision_pdf_qr_svg(string $contenido): string {
    $opciones = new QROptions([
        'outputBase64' => false,
        'eccLevel' => 'M',
        'scale' => 4,
        'svgAddXmlHeader' => false,
    ]);
    $svg = (new QRCode($opciones))->render($contenido);
    if (!is_string($svg) || $svg === '') throw new RuntimeException('No se pudo generar el código QR.');
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function emision_pdf_estado(array $documento): array {
    $aceptado = $documento['ambiente'] === 'produccion'
        && in_array($documento['estado'], ['aceptado', 'aceptado_observado'], true);
    if ($aceptado) return ['ACEPTADO POR SUNAT', 'ok'];
    if ($documento['ambiente'] === 'pruebas') return ['PRUEBA - SIN VALIDEZ TRIBUTARIA', 'warning'];
    return ['PENDIENTE DE ACEPTACIÓN SUNAT', 'warning'];
}

/** Genera en memoria el PDF; su acceso se controla en api/descargar_emision.php. */
function emision_generar_pdf(array $documento, array $snapshot): string {
    foreach (['emisor', 'cliente', 'items', 'total', 'fecha', 'tipo'] as $campo) {
        if (!array_key_exists($campo, $snapshot)) throw new DomainException('Datos fiscales incompletos para el PDF.');
    }
    $estado = emision_pdf_estado($documento);
    $qr = emision_pdf_qr_svg(emision_pdf_qr($documento, $snapshot));
    $total = sunat_centimos((string)$snapshot['total']);
    $tasa = sunat_centimos((string)$snapshot['tasa_igv']);
    $igv = intdiv($total * $tasa + intdiv(10000 + $tasa, 2), 10000 + $tasa);
    $base = $total - $igv;
    $filas = '';
    foreach ($snapshot['items'] as $item) {
        $filas .= '<tr><td>' . emision_pdf_html($item['descripcion'] ?? '') . '</td><td class="num">'
            . emision_pdf_html($item['cantidad'] ?? '') . '</td><td class="num">S/ '
            . emision_pdf_html($item['precio_unitario'] ?? '') . '</td><td class="num">S/ '
            . emision_pdf_html($item['subtotal'] ?? '') . '</td></tr>';
    }
    if ($filas === '') throw new DomainException('El documento no tiene líneas para representar.');
    $tipo = $snapshot['tipo'] === 'factura' ? 'FACTURA ELECTRÓNICA' : 'BOLETA ELECTRÓNICA';
    $mensaje = trim((string)($documento['respuesta_mensaje'] ?? ''));
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . '@page{margin:22px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#1f2937}'
        . 'h1{font-size:17px;margin:0 0 6px}h2{font-size:12px;margin:0 0 5px}.head{width:100%;border-collapse:collapse}'
        . '.head td{vertical-align:top}.box{border:1px solid #334155;padding:8px;text-align:center}.badge{padding:5px;font-weight:bold;text-align:center;border:1px solid #a16207;background:#fef3c7}.ok{border-color:#166534;background:#dcfce7}.warning{border-color:#a16207;background:#fef3c7}'
        . 'table.items{width:100%;border-collapse:collapse;margin-top:12px}table.items th{background:#e2e8f0;text-align:left}table.items td,table.items th{border:1px solid #94a3b8;padding:5px}.num{text-align:right}.summary{width:45%;margin-left:auto;margin-top:10px;border-collapse:collapse}.summary td{padding:3px}.footer{font-size:8px;color:#475569;margin-top:14px}.qr{width:105px;height:105px}</style></head><body>'
        . '<table class="head"><tr><td><h1>' . emision_pdf_html($snapshot['emisor']['razon_social']) . '</h1><div>RUC: '
        . emision_pdf_html($snapshot['emisor']['ruc']) . '</div><div>' . emision_pdf_html($snapshot['emisor']['direccion'])
        . '</div></td><td width="38%"><div class="box"><strong>' . $tipo . '</strong><br>RUC '
        . emision_pdf_html($snapshot['emisor']['ruc']) . '<br><strong>' . emision_pdf_html($documento['serie'] . '-' . $documento['correlativo'])
        . '</strong></div></td></tr></table><p class="badge ' . $estado[1] . '">' . $estado[0] . '</p>'
        . '<table class="head"><tr><td><strong>Cliente:</strong> ' . emision_pdf_html($snapshot['cliente']['nombre'])
        . '<br><strong>Documento:</strong> ' . emision_pdf_html($snapshot['cliente']['numero_documento'])
        . '</td><td><strong>Fecha de emisión:</strong> ' . emision_pdf_html($snapshot['fecha'])
        . '<br><strong>Moneda:</strong> ' . emision_pdf_html($snapshot['moneda'] ?? 'PEN') . '</td></tr></table>'
        . '<table class="items"><thead><tr><th>Descripción</th><th class="num">Cant.</th><th class="num">P. unit.</th><th class="num">Importe</th></tr></thead><tbody>'
        . $filas . '</tbody></table><table class="summary"><tr><td>Operación gravada</td><td class="num">S/ ' . sunat_decimal($base)
        . '</td></tr><tr><td>IGV (' . emision_pdf_html($snapshot['tasa_igv']) . '%)</td><td class="num">S/ ' . sunat_decimal($igv)
        . '</td></tr><tr><td><strong>Total</strong></td><td class="num"><strong>S/ ' . sunat_decimal($total)
        . '</strong></td></tr></table><table class="head" style="margin-top:14px"><tr><td><img class="qr" src="' . $qr
        . '" alt="Código QR"><br><small>Consulta mediante QR</small></td><td class="footer">'
        . ($mensaje !== '' ? emision_pdf_html($mensaje) . '<br>' : '')
        . 'Representación impresa generada por MESORA. La validez tributaria depende exclusivamente de la CDR SUNAT y del estado indicado arriba.</td></tr></table></body></html>';
    $opciones = new Options();
    $opciones->set('isRemoteEnabled', false);
    $opciones->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($opciones);
    $dompdf->setPaper('A4');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    return $dompdf->output();
}
