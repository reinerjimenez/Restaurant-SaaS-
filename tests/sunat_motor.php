<?php
// Pruebas locales sin BD ni red. Material criptográfico efímero de ensayo.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/sunat_motor.php';
$cuenta = 0;
function comprobar_motor(bool $ok, string $caso): void { global $cuenta; if (!$ok) throw new RuntimeException($caso); $cuenta++; }
function rechazar_motor(callable $op): void {
    try { $op(); } catch (DomainException $e) { comprobar_motor(true, 'rechazo'); return; }
    throw new RuntimeException('Se aceptó un dato inválido.');
}
$datos = ['tipo' => 'factura', 'serie' => 'F001', 'correlativo' => 1, 'fecha' => '2026-11-19', 'moneda' => 'PEN', 'tasa_igv' => '18.00',
    'emisor' => ['ruc' => '20100066603', 'razon_social' => 'ENSAYO & Hijos', 'direccion' => 'Dirección <ensayo>', 'ubigeo' => '150101'],
    'cliente' => ['tipo_documento' => 'ruc', 'numero_documento' => '20100066603', 'nombre' => 'CLIENTE ENSAYO'],
    'items' => [['descripcion' => '<img src=x onerror=alert(1)>', 'cantidad' => 2, 'precio_unitario' => '59.00', 'subtotal' => '118.00']], 'total' => '118.00'];
$xml = sunat_generar_invoice($datos);
comprobar_motor(str_contains($xml, '&lt;img') && !str_contains($xml, '<img'), 'texto XML escapado');
comprobar_motor(str_contains($xml, '>100.00<'), 'base gravada correcta');
$b = $datos; $b['total'] = '117.99'; rechazar_motor(fn() => sunat_generar_invoice($b));
$b = $datos; $b['cliente']['tipo_documento'] = 'dni'; $b['cliente']['numero_documento'] = '12345678'; rechazar_motor(fn() => sunat_generar_invoice($b));
$b = $datos; $b['fecha'] = '2026-02-30'; rechazar_motor(fn() => sunat_generar_invoice($b));
$b = $datos; $b['emisor']['ruc'] = '20100066604'; rechazar_motor(fn() => sunat_generar_invoice($b));
$b = $datos; $b['tasa_igv'] = '10.50'; $b['total'] = $b['items'][0]['subtotal'] = '110.50'; $b['items'][0]['cantidad'] = 1; $b['items'][0]['precio_unitario'] = '110.50';
comprobar_motor(str_contains(sunat_generar_invoice($b), '>100.00<'), 'tasa 10.50 configurable');
$b = $datos; $b['tipo'] = 'boleta'; $b['serie'] = 'B001'; $b['cliente']['tipo_documento'] = 'dni'; $b['cliente']['numero_documento'] = '12345678';
comprobar_motor(str_contains(sunat_generar_invoice($b), '>03<'), 'boleta UBL');
rechazar_motor(fn() => sunat_cargar_xml('<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>'));
$config = getenv('OPENSSL_CONF') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/apache/conf/openssl.cnf' : '/etc/ssl/openssl.cnf');
$opciones = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $config];
$llave = openssl_pkey_new($opciones);
if (!$llave) throw new RuntimeException('No se pudo generar llave de ensayo.');
$csr = openssl_csr_new(['commonName' => 'ENSAYO', 'organizationalUnitName' => '20100066603', 'countryName' => 'PE'], $llave, $opciones);
$cert = openssl_csr_sign($csr, null, $llave, 1, $opciones);
if (!$cert || !openssl_pkcs12_export($cert, $p12, $llave, 'ensayo')) throw new RuntimeException('No se pudo crear certificado efímero.');
$firmado = sunat_firmar_xml($xml, $p12, 'ensayo');
$fd = sunat_cargar_xml($firmado); $xp = new DOMXPath($fd); $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$info = $xp->query('//ds:SignedInfo')->item(0); $valor = base64_decode($xp->evaluate('string(//ds:SignatureValue)'));
comprobar_motor(openssl_verify($info->C14N(false, false), $valor, $cert, OPENSSL_ALGO_SHA256) === 1, 'firma verificada independientemente');
$digest = $xp->evaluate('string(//ds:DigestValue)'); $firma = $xp->query('//ds:Signature')->item(0); $firma->parentNode->removeChild($firma);
comprobar_motor(hash_equals($digest, base64_encode(hash('sha256', $fd->C14N(false, false), true))), 'digest de todo el documento');
rechazar_motor(fn() => sunat_firmar_xml($firmado, $p12, 'ensayo'));
rechazar_motor(fn() => sunat_firmar_xml($xml, $p12, 'incorrecta'));
rechazar_motor(fn() => sunat_firmar_xml(str_replace('20100066603', '20600695771', $xml), $p12, 'ensayo'));
$zip = sunat_crear_zip('20100066603-01-F001-1.xml', $firmado);
comprobar_motor(str_starts_with($zip, "PK\x03\x04"), 'ZIP nativo sin ZipArchive');
rechazar_motor(fn() => sunat_crear_zip('20600695771-01-F001-1.xml', $firmado));
rechazar_motor(fn() => sunat_crear_zip('20100066603-01-F001-2.xml', $firmado));
$cdr = '<ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cac:ReceiverParty><cac:PartyIdentification><cbc:ID>20100066603</cbc:ID></cac:PartyIdentification></cac:ReceiverParty><cac:DocumentResponse><cac:Response><cbc:ResponseCode>0</cbc:ResponseCode><cbc:Description>Aceptado de ensayo</cbc:Description></cac:Response><cac:DocumentReference><cbc:ID>F001-1</cbc:ID></cac:DocumentReference></cac:DocumentResponse></ApplicationResponse>';
comprobar_motor(sunat_analizar_cdr_xml($cdr, 'F001-1', '20100066603')['estado'] === 'aceptado', 'CDR aceptada');
rechazar_motor(fn() => sunat_analizar_cdr_xml($cdr, 'F001-2', '20100066603'));
rechazar_motor(fn() => sunat_analizar_cdr_xml($cdr, 'F001-1', '20600695771'));
comprobar_motor(sunat_analizar_cdr_xml(str_replace('>0<', '>2335<', $cdr), 'F001-1', '20100066603')['estado'] === 'rechazado', 'CDR rechazada');
$temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mesora_test_' . bin2hex(random_bytes(8)) . '.zip';
try {
    $pa = new PharData($temporal, 0, null, Phar::ZIP); $pa['R-20100066603-01-F001-1.xml'] = $cdr; unset($pa);
    comprobar_motor(sunat_leer_cdr(file_get_contents($temporal), '20100066603-01-F001-1')['estado'] === 'aceptado', 'CDR ZIP PharData');
    rechazar_motor(fn() => sunat_leer_cdr(file_get_contents($temporal), '20600695771-01-F001-1'));
} finally { if (is_file($temporal)) unlink($temporal); }
$resumen = sunat_generar_resumen($b, 'RC-20261119-1', '2026-11-19');
comprobar_motor(str_contains($resumen, 'SummaryDocuments') && str_contains($resumen, '>1.1<'), 'RC versión 1.1');
comprobar_motor(str_contains($resumen, '<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>'), 'RC contiene boleta');
comprobar_motor(str_contains(sunat_generar_resumen($b, 'RC-20261119-2', '2026-11-19', 3), '<cbc:ConditionCode>3</cbc:ConditionCode>'), 'RC condición anulación');
rechazar_motor(fn() => sunat_generar_resumen($datos, 'RC-20261119-1', '2026-11-19'));
rechazar_motor(fn() => sunat_generar_resumen($b, 'RC-20261118-1', '2026-11-19'));
$resumen_firmado = sunat_firmar_xml($resumen, $p12, 'ensayo');
$rfd = sunat_cargar_xml($resumen_firmado); $rx = new DOMXPath($rfd); $rx->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
comprobar_motor(openssl_verify($rx->query('//ds:SignedInfo')->item(0)->C14N(false, false), base64_decode($rx->evaluate('string(//ds:SignatureValue)')), $cert, OPENSSL_ALGO_SHA256) === 1, 'firma RC verificable');
comprobar_motor(str_starts_with(sunat_crear_zip('20100066603-RC-20261119-1.xml', $resumen_firmado), "PK\x03\x04"), 'ZIP RC');
$temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mesora_rc_test_' . bin2hex(random_bytes(8)) . '.zip';
try {
    $pa = new PharData($temporal, 0, null, Phar::ZIP); $pa['R-20100066603-RC-20261119-1.xml'] = str_replace('F001-1', 'RC-20261119-1', $cdr); unset($pa);
    $cdr_rc = file_get_contents($temporal);
    comprobar_motor(sunat_leer_cdr($cdr_rc, '20100066603-RC-20261119-1')['estado'] === 'aceptado', 'CDR RC acepta identidad resumen');
} finally { if (is_file($temporal)) unlink($temporal); }
$sobre = static fn(string $body): string => '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.sunat.gob.pe"><s:Body>' . $body . '</s:Body></s:Envelope>';
comprobar_motor(sunat_analizar_ticket($sobre('<ser:sendSummaryResponse><ticket>123456789</ticket></ser:sendSummaryResponse>')) === '123456789', 'ticket SOAP');
rechazar_motor(fn() => sunat_analizar_ticket($sobre('<ser:sendSummaryResponse><ticket>abc</ticket></ser:sendSummaryResponse>')));
comprobar_motor(sunat_analizar_estado_ticket($sobre('<ser:getStatusResponse><status><statusCode>98</statusCode></status></ser:getStatusResponse>'))['cdr'] === null, 'ticket98 pendiente');
comprobar_motor(sunat_analizar_estado_ticket($sobre('<ser:getStatusResponse><status><statusCode>0</statusCode><content>' . base64_encode($cdr_rc) . '</content></status></ser:getStatusResponse>'))['cdr'] === $cdr_rc, 'ticket0 transporta CDR');
rechazar_motor(fn() => sunat_analizar_estado_ticket($sobre('<ser:getStatusResponse><status><statusCode>0</statusCode></status></ser:getStatusResponse>')));
rechazar_motor(fn() => sunat_analizar_estado_ticket($sobre('<ser:getStatusResponse><status><statusCode>0</statusCode><content>AAA</content></status></ser:getStatusResponse>')));
comprobar_motor(sunat_analizar_consulta_cdr($sobre('<ser:getStatusCdrResponse><statusCdr><statusCode>0001</statusCode><statusMessage>Sin CDR</statusMessage></statusCdr></ser:getStatusCdrResponse>'))['cdr'] === null, 'consulta sin contenido no acredita aceptación');
echo "Motor SUNAT: $cuenta comprobaciones sin BD ni red; pendiente homologación fiscal.\n";
