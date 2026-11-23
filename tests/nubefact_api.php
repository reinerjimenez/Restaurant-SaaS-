<?php
/** Contrato NubeFacT con transporte de ensayo. No accede a red ni BD. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/nubefact_api.php';

$total_pruebas = 0;
function nubefact_prueba(bool $ok, string $nombre): void {
    global $total_pruebas;
    if (!$ok) throw new RuntimeException('Falló: ' . $nombre);
    $total_pruebas++;
}

function nubefact_prueba_rechazo(callable $accion, string $nombre): void {
    try {
        $accion();
    } catch (DomainException | RuntimeException | JsonException $e) {
        nubefact_prueba(true, $nombre);
        return;
    }
    nubefact_prueba(false, $nombre);
}

$ruta = 'https://demo.nubefact.com/api/v1/ejemplo-ficticio';
$factura = [
    'tipo'=>'factura','serie'=>'F001','correlativo'=>7,'fecha'=>'2026-11-23','moneda'=>'PEN','tasa_igv'=>'18.00',
    'emisor'=>['ruc'=>'20600695771'],
    'cliente'=>['tipo_documento'=>'ruc','numero_documento'=>'20131312955','nombre'=>'Cliente de muestra','direccion'=>'Av. Ejemplo'],
    'items'=>[['descripcion'=>'Venta de ensayo','cantidad'=>2,'precio_unitario'=>'59.00','subtotal'=>'118.00','afectacion_igv'=>'gravada']],
    'descuento'=>'5.90','cargo_extra'=>'11.80','total'=>'123.90',
    'resumen_fiscal'=>['base_gravada'=>'105.00','igv'=>'18.90','exonerada'=>'0.00','inafecta'=>'0.00'],
];
$respuesta = ['tipo_de_comprobante'=>1,'serie'=>'F001','numero'=>7,'aceptada_por_sunat'=>true,
    'sunat_responsecode'=>'0','cadena_para_codigo_qr'=>'20600695771 | 01 | F001 | 00000007 | 18.90 | 123.90',
    'enlace_del_pdf'=>'https://www.nubefact.com/cpe/archivo-ficticio.pdf',
    'enlace_del_xml'=>'https://www.nubefact.com/cpe/archivo-ficticio.xml',
    'enlace_del_cdr'=>'https://www.nubefact.com/cpe/archivo-ficticio.cdr'];
$recibida = nubefact_emitir($ruta, 'token-ficticio', $factura,
    static function (string $url, string $token, array $p) use ($ruta, $respuesta): string {
        nubefact_prueba($url === $ruta && $token === 'token-ficticio', 'Transporte con credencial aislada');
        nubefact_prueba($p['operacion'] === 'generar_comprobante' && $p['tipo_de_comprobante'] === 1, 'Contrato factura');
        nubefact_prueba($p['cliente_tipo_de_documento'] === 6 && $p['fecha_de_emision'] === '23-11-2026', 'Catálogos y fecha');
        nubefact_prueba($p['total'] === '123.90' && $p['total_gravada'] === '105.00'
            && $p['descuento_global'] === '5.00' && $p['total_igv'] === '18.90', 'Cuadre con ajuste');
        nubefact_prueba(count($p['items']) === 2 && $p['items'][1]['tipo_de_igv'] === 1
            && $p['items'][1]['total'] === '11.80', 'Cargo que conserva su IGV');
        return json_encode($respuesta, JSON_THROW_ON_ERROR);
    });
nubefact_prueba($recibida['estado'] === 'aceptado' && $recibida['cdr'] === $respuesta['enlace_del_cdr'], 'CDR aceptada');

$boleta = array_replace($factura, ['tipo'=>'boleta','serie'=>'B001',
    'cliente'=>['tipo_documento'=>'sin_documento','nombre'=>'Consumidor final','direccion'=>'','numero_documento'=>''],
    'descuento'=>'0.00','cargo_extra'=>'0.00','total'=>'30.00',
    'items'=>[
        ['descripcion'=>'Item exonerado','cantidad'=>1,'precio_unitario'=>'20.00','subtotal'=>'20.00','afectacion_igv'=>'exonerada'],
        ['descripcion'=>'Item inafecto','cantidad'=>1,'precio_unitario'=>'10.00','subtotal'=>'10.00','afectacion_igv'=>'inafecta'],
    ],
    'resumen_fiscal'=>['base_gravada'=>'0.00','igv'=>'0.00','exonerada'=>'20.00','inafecta'=>'10.00'],
]);
$p = nubefact_payload($boleta);
nubefact_prueba($p['tipo_de_comprobante'] === 2 && $p['cliente_tipo_de_documento'] === '-' && $p['cliente_direccion'] === '', 'Boleta sin documento');
nubefact_prueba(array_column($p['items'], 'tipo_de_igv') === [8,9] && $p['total_inafecta'] === '10.00', 'Afectaciones exonerada e inafecta');
nubefact_prueba_rechazo(static fn()=>nubefact_validar_emision(array_replace($boleta, ['total'=>'30.01'])), 'Centavos discordantes');
nubefact_prueba_rechazo(static fn()=>nubefact_validar_emision(array_replace($boleta, ['fecha'=>'2026-02-30'])), 'Fecha inválida');
nubefact_prueba_rechazo(static function () use ($boleta): void {
    $boleta['items'][] = ['descripcion'=>'Entrega gratuita','cantidad'=>1,'precio_unitario'=>'0.00','subtotal'=>'0.00','afectacion_igv'=>'gravada'];
    nubefact_validar_emision($boleta);
}, 'Una operación gratuita requiere su catálogo propio');
nubefact_prueba_rechazo(static function () use ($factura): void {
    $factura['cliente']['direccion'] = str_repeat('X', 101);
    nubefact_validar_emision($factura);
}, 'Dirección superior al tamaño oficial');

$pendiente = nubefact_consultar($ruta, 'token-ficticio', $factura, static function ($url, $token, $p) use ($respuesta): array {
    nubefact_prueba($p === ['operacion'=>'consultar_comprobante','tipo_de_comprobante'=>1,'serie'=>'F001','numero'=>7], 'Consulta por la misma identidad');
    return array_replace($respuesta, ['aceptada_por_sunat'=>false, 'sunat_responsecode'=>null]);
});
nubefact_prueba($pendiente['estado'] === 'pendiente_ticket', 'Una respuesta con PDF puede seguir pendiente');
nubefact_prueba(nubefact_respuesta(array_replace($respuesta, ['aceptada_por_sunat'=>false,
    'sunat_responsecode'=>'2335']), $factura)['estado'] === 'rechazado', 'Rechazo fiscal explícito');
nubefact_prueba_rechazo(static fn()=>nubefact_respuesta(array_replace($respuesta, ['numero'=>8]), $factura), 'Correlativo diferente');
nubefact_prueba_rechazo(static fn()=>nubefact_respuesta(array_replace($respuesta, ['cadena_para_codigo_qr'=>'']), $factura), 'Sin RUC en confirmación');
nubefact_prueba_rechazo(static fn()=>nubefact_respuesta(array_replace($respuesta, ['cadena_para_codigo_qr'=>
    '20131312955 | 01 | F001 | 7']), $factura), 'RUC de otro emisor');
nubefact_prueba_rechazo(static fn()=>nubefact_consultar($ruta, 'token-ficticio', $factura,
    static fn()=>['errors'=>'Duplicado','codigo'=>23]), 'Duplicado no se considera aceptado');
nubefact_prueba(nubefact_respuesta(array_replace($respuesta, ['enlace_del_xml'=>'https://example.invalid/cpe/archivo.xml']), $factura)['xml'] === null,
    'Archivo ajeno a la lista autorizada');
foreach (['http://demo.nubefact.com/api/v1/ejemplo-ficticio','https://127.0.0.1/api/v1/ejemplo-ficticio',
    'https://demo.nubefact.com.example.invalid/api/v1/ejemplo-ficticio','https://demo.nubefact.com/api/v1/..%2fposible',
    'https://demo.nubefact.com:8443/api/v1/ejemplo-ficticio','https://demo.nubefact.com/api/v1/ejemplo-ficticio?a=1',
    'https://api.nubefact.com/api/v1/ejemplo-ficticio'] as $invalida) {
    nubefact_prueba_rechazo(static fn()=>nubefact_ruta($invalida, 'pruebas'), 'Ruta rechazada');
}
echo "OK NubeFacT: $total_pruebas comprobaciones con transporte de ensayo.\n";
