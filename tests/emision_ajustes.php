<?php
/** Ensayo local de nota de crédito 01 y baja 03; no llama a SUNAT ni a la BD. */
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../includes/emision_ajustes.php';
$origen = ['tipo'=>'factura','serie'=>'F001','correlativo'=>1,'fecha'=>'2026-11-20','moneda'=>'PEN','tasa_igv'=>'18.00',
    'emisor'=>['ruc'=>'20100066603','razon_social'=>'Restaurante ficticio','direccion'=>'Lima','ubigeo'=>'150101'],
    'cliente'=>['tipo_documento'=>'ruc','numero_documento'=>'20100066603','nombre'=>'Cliente ficticio','direccion'=>'Lima'],
    'items'=>[['descripcion'=>'Producto de prueba','cantidad'=>1,'precio_unitario'=>'118.00','subtotal'=>'118.00']],
    'total'=>'118.00'];
$xml = sunat_generar_nota_credito($origen,'FC01',1,'2026-11-20','Anulación total de operación');
$doc = sunat_cargar_xml($xml); $xp = new DOMXPath($doc);
$xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
$xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
if ($doc->documentElement->localName !== 'CreditNote'
    || $xp->evaluate('string(/*[local-name()="CreditNote"]/cac:DiscrepancyResponse/cbc:ResponseCode)') !== '01'
    || $xp->evaluate('string(/*[local-name()="CreditNote"]/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)') !== 'F001-1'
    || $xp->evaluate('string(/*[local-name()="CreditNote"]/cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode)') !== '01'
    || $xp->evaluate('string(/*[local-name()="CreditNote"]/cac:CreditNoteLine/cbc:CreditedQuantity)') !== '1'
    || $xp->query('/*[local-name()="CreditNote"]/cac:InvoiceLine')->length !== 0) throw new RuntimeException('Nota fiscal mal formada.');
$zip = sunat_crear_zip('20100066603-07-FC01-1.xml',$xml);
if (!str_starts_with($zip,"PK\x03\x04")) throw new RuntimeException('ZIP de nota inválido.');
$openssl = getenv('OPENSSL_CONF') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/apache/conf/openssl.cnf' : '/etc/ssl/openssl.cnf');
$opts = ['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'config'=>$openssl];
$llave = openssl_pkey_new($opts);
$csr = openssl_csr_new(['commonName'=>'ENSAYO','organizationalUnitName'=>'20100066603','countryName'=>'PE'],$llave,$opts);
$cert = openssl_csr_sign($csr,null,$llave,1,$opts);
if (!$cert || !openssl_pkcs12_export($cert,$p12,$llave,'ensayo')) throw new RuntimeException('Certificado de ensayo inválido.');
$firmado = sunat_firmar_xml($xml,$p12,'ensayo');
if (!str_contains($firmado,'<ds:Signature')) throw new RuntimeException('Nota sin firma.');
sunat_crear_zip('20100066603-07-FC01-1.xml',$firmado);
$cdr = static function (string $codigo, bool $observacion = false): string {
    return '<ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"'
        . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
        . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
        . ($observacion ? '<cbc:Note>Advertencia simulada</cbc:Note>' : '')
        . '<cac:ReceiverParty><cac:PartyIdentification><cbc:ID>20100066603</cbc:ID></cac:PartyIdentification></cac:ReceiverParty>'
        . '<cac:DocumentResponse><cac:Response><cbc:ResponseCode>' . $codigo . '</cbc:ResponseCode>'
        . '<cbc:Description>Respuesta ficticia</cbc:Description></cac:Response>'
        . '<cac:DocumentReference><cbc:ID>FC01-1</cbc:ID></cac:DocumentReference></cac:DocumentResponse></ApplicationResponse>';
};
if (sunat_analizar_cdr_xml($cdr('0'),'FC01-1','20100066603')['estado'] !== 'aceptado'
    || sunat_analizar_cdr_xml($cdr('0',true),'FC01-1','20100066603')['estado'] !== 'aceptado_observado'
    || sunat_analizar_cdr_xml($cdr('2222'),'FC01-1','20100066603')['estado'] !== 'rechazado') {
    throw new RuntimeException('Estados CDR de nota inválidos.');
}
$boleta = $origen; $boleta['tipo']='boleta'; $boleta['serie']='B001';
$boleta['cliente']=['tipo_documento'=>'dni','numero_documento'=>'12345678','nombre'=>'Cliente ficticio'];
$resumen = sunat_generar_resumen($boleta,'RC-20261120-1','2026-11-20',3);
if (!str_contains($resumen,'<cbc:ConditionCode>3</cbc:ConditionCode>')) throw new RuntimeException('Baja sin condición 3.');
echo "OK ajustes: nota 07, ZIP, CDR ficticias aceptada/observada/rechazada y baja RC condición 3; sin BD ni red.\n";
