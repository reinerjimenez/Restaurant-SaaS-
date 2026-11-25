<?php
/** Datos compartidos y salida ESC/POS para comprobantes internos. */

const COMPROBANTE_COLUMNAS_TERMICAS = 48;
const COMPROBANTE_METODOS_PAGO = [
    'efectivo' => 'Efectivo', 'yape' => 'Yape/Plin', 'transferencia' => 'Transferencia',
    'tarjeta' => 'Tarjeta', 'otro' => 'Otro',
];

function comprobante_agrupar_items(array $items): array
{
    $agrupados = [];
    foreach ($items as $item) {
        $nombre = $item['producto_nombre'] ?? '';
        $opciones = $item['opciones_texto'] ?? '';
        $clave = $nombre . '||' . $opciones;
        if (!isset($agrupados[$clave])) {
            $agrupados[$clave] = [
                'nombre' => $nombre, 'opciones' => $opciones, 'cantidad' => 0,
                'precio' => (float) ($item['precio_unitario'] ?? 0), 'subtotal' => 0.0,
            ];
        }
        $agrupados[$clave]['cantidad'] += (int) $item['cantidad'];
        $agrupados[$clave]['subtotal'] += (float) $item['subtotal'];
    }
    return $agrupados;
}

function comprobante_texto_termico(string $texto): string
{
    $limpio = trim(preg_replace('/[\p{C}\p{Z}\s]+/u', ' ', $texto) ?? '');
    $bytes = iconv('UTF-8', 'CP850//TRANSLIT', $limpio);
    return preg_replace('/[\x00-\x1F\x7F]/', ' ', $bytes === false ? '?' : $bytes);
}

function comprobante_lineas_termicas(string $texto): string
{
    return wordwrap(comprobante_texto_termico($texto), COMPROBANTE_COLUMNAS_TERMICAS, "\n", true) . "\n";
}

function comprobante_importe_termico(string $etiqueta, $monto, bool $descuento = false): string
{
    $importe = ($descuento ? '-S/ ' : 'S/ ') . number_format((float) $monto, 2);
    $texto = comprobante_texto_termico($etiqueta);
    $espacios = COMPROBANTE_COLUMNAS_TERMICAS - strlen($texto) - strlen($importe);
    if ($espacios < 1) {
        return comprobante_lineas_termicas($etiqueta) . str_pad($importe, COMPROBANTE_COLUMNAS_TERMICAS, ' ', STR_PAD_LEFT) . "\n";
    }
    return $texto . str_repeat(' ', $espacios) . $importe . "\n";
}

function comprobante_escpos(array $comp, array $items, array $pagos): string
{
    $negrita = "\x1bE\x01";
    $normal = "\x1bE\x00";
    $separador = str_repeat('-', COMPROBANTE_COLUMNAS_TERMICAS) . "\n";

    $ticket = "\x1b@\x1bM\x00\x1bt\x02\x1ba\x01"; // Fuente A, CP850, centrado.
    $ticket .= $negrita . comprobante_lineas_termicas($comp['rest_razon'] ?: 'Restaurante') . $normal;
    foreach (['rest_ruc' => 'RUC: ', 'rest_dir' => '', 'rest_tel' => 'Tel: '] as $campo => $prefijo) {
        if (!empty($comp[$campo])) {
            $ticket .= comprobante_lineas_termicas($prefijo . $comp[$campo]);
        }
    }

    $tipo = $comp['tipo'];
    $titulo = ['boleta' => 'Boleta de venta', 'factura' => 'Factura'][$tipo] ?? 'Comprobante simple';
    $ticket .= "\n" . $negrita . comprobante_lineas_termicas($titulo . ' ' . $comp['numero_comprobante']);
    $ticket .= comprobante_lineas_termicas('DOCUMENTO INTERNO — SIN VALIDEZ TRIBUTARIA');
    if (!empty($comp['anulado'])) {
        $ticket .= "ANULADO\n";
    }
    $ticket .= $normal . comprobante_lineas_termicas('No acredita emisión ni aceptación de SUNAT.');
    $ticket .= "\x1ba\x00" . $separador;
    $ticket .= 'Fecha: ' . date('d/m/Y H:i', strtotime($comp['created_at'])) . "\n";
    $ticket .= comprobante_lineas_termicas('Cajero: ' . $comp['cajero_nombre']);

    if ($tipo !== 'simple') {
        $ticket .= comprobante_lineas_termicas(strtoupper($comp['tipo_documento'] ?? '') . ': ' . ($comp['numero_documento'] ?? ''));
        $ticket .= comprobante_lineas_termicas(($tipo === 'factura' ? 'Razón social: ' : 'Cliente: ') . ($comp['nombre_cliente'] ?? ''));
    }
    if ($tipo === 'factura' && !empty($comp['direccion_cliente'])) {
        $ticket .= comprobante_lineas_termicas('Dir: ' . $comp['direccion_cliente']);
        $ubicacion = array_filter([$comp['distrito'] ?? '', $comp['provincia'] ?? '', $comp['departamento'] ?? '']);
        if ($ubicacion) {
            $ticket .= comprobante_lineas_termicas('Ubic: ' . implode(' - ', $ubicacion));
        }
    }

    $ticket .= $separador . $negrita . sprintf("%12s %15s %19s\n", 'Cant.', 'P. unit.', 'Subtotal') . $normal;
    foreach ($items as $item) {
        $ticket .= comprobante_lineas_termicas($item['nombre']);
        if ($item['opciones'] !== '') {
            $ticket .= comprobante_lineas_termicas('- ' . $item['opciones']);
        }
        $ticket .= sprintf("%12d %15s %19s\n", $item['cantidad'], number_format($item['precio'], 2), number_format($item['subtotal'], 2));
    }

    $ticket .= $separador;
    if (($comp['cargo_extra'] ?? 0) > 0) {
        $ticket .= comprobante_importe_termico('Cargo extra:', $comp['cargo_extra']);
    }
    if (($comp['descuento'] ?? 0) > 0) {
        $ticket .= comprobante_importe_termico('Descuento:', $comp['descuento'], true);
    }
    if ($tipo !== 'simple') {
        $ticket .= comprobante_importe_termico('Op. gravada:', $comp['subtotal']);
        $ticket .= comprobante_importe_termico('IGV:', $comp['igv']);
    }
    $ticket .= $negrita . comprobante_importe_termico('TOTAL:', $comp['total']) . $normal;
    foreach ($pagos as $pago) {
        $metodo = $pago['metodo'] ?? 'otro';
        $ticket .= comprobante_importe_termico((COMPROBANTE_METODOS_PAGO[$metodo] ?? $metodo) . ':', $pago['monto'] ?? 0);
    }

    return $ticket . "\n\x1ba\x01" . comprobante_lineas_termicas($comp['pie_mensaje'] ?: '¡Gracias por su visita!') . "\n\n\x1ba\x00";
}
