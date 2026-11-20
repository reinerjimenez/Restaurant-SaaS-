<?php
/**
 * Motor sin BD para preparación SEE del contribuyente (UBL 2.1).
 * Referencias: https://cpe.sunat.gob.pe/guias-y-manuales y manual del programador.
 * Alcance: operaciones gravadas, contado, PEN y cantidades enteras.
 * Requiere homologación SUNAT antes de habilitar emisión real.
 */
function sunat_centimos(string $monto): int {
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,7})\.[0-9]{2}\z/D', $monto)) {
        throw new DomainException('Importe fiscal inválido: utiliza dos decimales.');
    }
    return (int)str_replace('.', '', $monto);
}

function sunat_decimal(int $centimos): string {
    return intdiv($centimos, 100) . '.' . str_pad((string)($centimos % 100), 2, '0', STR_PAD_LEFT);
}

function sunat_cargar_xml(string $xml): DOMDocument {
    if (strlen($xml) > 8 * 1024 * 1024 || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
        throw new DomainException('XML fiscal no permitido.');
    }
    $anterior = libxml_use_internal_errors(true);
    try {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        if (!$doc->loadXML($xml, LIBXML_NONET) || $doc->doctype) {
            throw new DomainException('XML fiscal inválido.');
        }
        return $doc;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
    }
}

function sunat_texto($valor, int $maximo): string {
    if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || trim($valor) === '' || mb_strlen($valor) > $maximo
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $valor)) {
        throw new DomainException('Texto fiscal incompleto o inválido.');
    }
    return trim($valor);
}

function sunat_ruc_valido(string $ruc): bool {
    if (!preg_match('/\A(?:10|15|16|17|20)[0-9]{9}\z/D', $ruc)) return false;
    $suma = 0;
    foreach ([5,4,3,2,7,6,5,4,3,2] as $i => $peso) $suma += (int)$ruc[$i] * $peso;
    $digito = 11 - ($suma % 11);
    if ($digito === 10) $digito = 0;
    if ($digito === 11) $digito = 1;
    return $digito === (int)$ruc[10];
}

/** Construye un Invoice UBL 2.1; todos los importes de entrada incluyen IGV. */
function sunat_generar_invoice(array $datos): string {
    $tipo = ['factura' => '01', 'boleta' => '03'][$datos['tipo'] ?? ''] ?? null;
    $serie = $datos['serie'] ?? '';
    $numero = filter_var($datos['correlativo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99999999]]);
    $fecha = $datos['fecha'] ?? '';
    $dia = is_string($fecha) ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) : false;
    if (!$tipo || !is_string($serie) || !preg_match($tipo === '01' ? '/\AF[A-Z0-9]{3}\z/D' : '/\AB[A-Z0-9]{3}\z/D', $serie)
        || !$numero || !$dia || $dia->format('Y-m-d') !== $fecha || ($datos['moneda'] ?? '') !== 'PEN') {
        throw new DomainException('Tipo, serie, fecha o moneda fiscal inválida.');
    }
    $emisor = $datos['emisor'] ?? [];
    $cliente = $datos['cliente'] ?? [];
    $ruc = $emisor['ruc'] ?? '';
    if (!is_string($ruc) || !sunat_ruc_valido($ruc) || !preg_match('/\A[0-9]{6}\z/D', $emisor['ubigeo'] ?? '')) {
        throw new DomainException('RUC o ubigeo del emisor inválido.');
    }
    $identificacion = $cliente['numero_documento'] ?? '';
    $tipo_cliente = ['dni' => '1', 'ruc' => '6', 'sin_documento' => '0'][$cliente['tipo_documento'] ?? ''] ?? null;
    if ($tipo_cliente === null || !is_string($identificacion)
        || ($tipo_cliente !== '0' && !preg_match($tipo_cliente === '1' ? '/\A[0-9]{8}\z/D' : '/\A[0-9]{11}\z/D', $identificacion))
        || ($tipo_cliente === '0' && ($tipo !== '03' || $identificacion !== '' || sunat_centimos((string)($datos['total'] ?? '')) > 70000))
        || ($tipo_cliente === '6' && !sunat_ruc_valido($identificacion)) || ($tipo === '01' && $tipo_cliente !== '6')) {
        throw new DomainException('Documento del cliente inválido para el tipo fiscal.');
    }
    $tasa = sunat_centimos((string)($datos['tasa_igv'] ?? ''));
    if ($tasa <= 0 || $tasa > 10000) throw new DomainException('Este motor requiere una tasa gravada mayor a cero.');
    $items = $datos['items'] ?? null;
    if (!is_array($items) || !array_is_list($items) || !$items || count($items) > 500) throw new DomainException('Detalle fiscal inválido.');
    $lineas = []; $base = 0; $igv = 0; $total = 0;
    $totales_afectacion = ['gravada'=>['base'=>0,'igv'=>0], 'exonerada'=>['base'=>0,'igv'=>0], 'inafecta'=>['base'=>0,'igv'=>0]];
    foreach ($items as $item) {
        $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        $precio = sunat_centimos((string)($item['precio_unitario'] ?? ''));
        $importe = sunat_centimos((string)($item['subtotal'] ?? ''));
        if (!$cantidad || !$precio || $precio * $cantidad !== $importe) throw new DomainException('El detalle fiscal no concilia: verifica cantidades y ajustes.');
        $afectacion = $item['afectacion_igv'] ?? 'gravada';
        if (!isset($totales_afectacion[$afectacion])) throw new DomainException('Afectación IGV inválida.');
        $neto = $afectacion === 'gravada'
            ? intdiv($importe * 10000 + intdiv(10000 + $tasa, 2), 10000 + $tasa) : $importe;
        $lineas[] = ['descripcion' => sunat_texto($item['descripcion'] ?? null, 500), 'cantidad' => $cantidad,
            'precio' => $precio, 'base' => $neto, 'igv' => $importe - $neto, 'afectacion'=>$afectacion];
        $totales_afectacion[$afectacion]['base'] += $neto;
        $totales_afectacion[$afectacion]['igv'] += $importe - $neto;
        $base += $neto; $igv += $importe - $neto; $total += $importe;
    }
    $descuento = sunat_centimos((string)($datos['descuento'] ?? '0.00'));
    $cargo = sunat_centimos((string)($datos['cargo_extra'] ?? '0.00'));
    if (($descuento || $cargo) && ($totales_afectacion['exonerada']['base'] || $totales_afectacion['inafecta']['base'])) {
        throw new DomainException('No se admiten ajustes globales con afectaciones IGV mixtas.');
    }
    if ($descuento >= $total || $total + $cargo - $descuento !== sunat_centimos((string)($datos['total'] ?? ''))) {
        throw new DomainException('El total fiscal no concilia con las líneas y ajustes.');
    }
    $descuento_base = intdiv($descuento * 10000 + intdiv(10000 + $tasa, 2), 10000 + $tasa);
    $cargo_base = intdiv($cargo * 10000 + intdiv(10000 + $tasa, 2), 10000 + $tasa);
    $base_ajustada = $base - $descuento_base + $cargo_base;
    $total_ajustado = $total - $descuento + $cargo;
    $igv_ajustado = $total_ajustado - $base_ajustada;
    $ns = ['cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2'];
    $doc = new DOMDocument('1.0', 'UTF-8');
    $raiz = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
    $doc->appendChild($raiz);
    foreach ($ns as $prefijo => $uri) $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefijo, $uri);
    $agregar = static function (DOMNode $padre, string $nombre, ?string $valor = null, array $atributos = []) use ($doc, $ns): DOMElement {
        $elemento = $doc->createElementNS($ns[explode(':', $nombre)[0]], $nombre);
        if ($valor !== null) $elemento->appendChild($doc->createTextNode($valor));
        foreach ($atributos as $clave => $v) $elemento->setAttribute($clave, $v);
        $padre->appendChild($elemento); return $elemento;
    };
    $a = $agregar;
    $a($a($a($raiz, 'ext:UBLExtensions'), 'ext:UBLExtension'), 'ext:ExtensionContent');
    $a($raiz, 'cbc:UBLVersionID', '2.1'); $a($raiz, 'cbc:CustomizationID', '2.0');
    $a($raiz, 'cbc:ID', $serie . '-' . $numero); $a($raiz, 'cbc:IssueDate', $fecha);
    $a($raiz, 'cbc:InvoiceTypeCode', $tipo, ['listID' => '0101', 'listAgencyName' => 'PE:SUNAT',
        'listName' => 'SUNAT:Identificador de Tipo de Documento', 'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01']);
    $a($raiz, 'cbc:DocumentCurrencyCode', 'PEN', ['listID' => 'ISO 4217 Alpha', 'listName' => 'Currency', 'listAgencyName' => 'United Nations Economic Commission for Europe']);
    $a($raiz, 'cbc:LineCountNumeric', (string)count($lineas));
    $firma = $a($raiz, 'cac:Signature'); $a($firma, 'cbc:ID', 'MESORA-SIGN');
    $firmante = $a($firma, 'cac:SignatoryParty'); $a($a($firmante, 'cac:PartyIdentification'), 'cbc:ID', $ruc);
    $a($a($firmante, 'cac:PartyName'), 'cbc:Name', sunat_texto($emisor['razon_social'] ?? null, 200));
    $a($a($a($firma, 'cac:DigitalSignatureAttachment'), 'cac:ExternalReference'), 'cbc:URI', '#MESORA-SIGN');
    foreach ([['cac:AccountingSupplierParty', $ruc, '6', $emisor['razon_social'] ?? '', true],
        ['cac:AccountingCustomerParty', $identificacion, $tipo_cliente, $cliente['nombre'] ?? '', false]] as [$n, $id, $tipodoc, $nombre, $es_emisor]) {
        $party = $a($a($raiz, $n), 'cac:Party');
        if ($tipodoc !== '0') $a($a($party, 'cac:PartyIdentification'), 'cbc:ID', $id, ['schemeID' => $tipodoc,
            'schemeName' => 'Documento de Identidad', 'schemeAgencyName' => 'PE:SUNAT',
            'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06']);
        $legal = $a($party, 'cac:PartyLegalEntity'); $a($legal, 'cbc:RegistrationName', sunat_texto($nombre, 200));
        if ($es_emisor) {
            $direccion = $a($legal, 'cac:RegistrationAddress'); $a($direccion, 'cbc:ID', $emisor['ubigeo']);
            $a($direccion, 'cbc:AddressTypeCode', '0000');
            $a($a($direccion, 'cac:AddressLine'), 'cbc:Line', sunat_texto($emisor['direccion'] ?? null, 300));
            $a($a($direccion, 'cac:Country'), 'cbc:IdentificationCode', 'PE');
        }
    }
    $forma = $a($raiz, 'cac:PaymentTerms'); $a($forma, 'cbc:ID', 'FormaPago'); $a($forma, 'cbc:PaymentMeansID', 'Contado');
    $dinero = static fn(DOMNode $p, string $n, int $v) => $a($p, $n, sunat_decimal($v), ['currencyID' => 'PEN']);
    $impuesto = static function (DOMNode $padre, int $neto, int $tributo, bool $linea, string $afectacion = 'gravada') use ($a, $dinero, $tasa): void {
        $tax = $a($padre, 'cac:TaxTotal'); $dinero($tax, 'cbc:TaxAmount', $tributo);
        $sub = $a($tax, 'cac:TaxSubtotal'); $dinero($sub, 'cbc:TaxableAmount', $neto); $dinero($sub, 'cbc:TaxAmount', $tributo);
        $cat = $a($sub, 'cac:TaxCategory');
        $categoria = ['gravada'=>['S','10','1000','IGV','VAT'], 'exonerada'=>['E','20','9997','EXO','VAT'], 'inafecta'=>['O','30','9998','INA','FRE']][$afectacion];
        $a($cat, 'cbc:ID', $categoria[0], ['schemeID' => 'UN/ECE 5305', 'schemeName' => 'Tax Category Identifier', 'schemeAgencyName' => 'United Nations Economic Commission for Europe']);
        if ($linea) { if ($afectacion === 'gravada') $a($cat, 'cbc:Percent', sunat_decimal($tasa)); $a($cat, 'cbc:TaxExemptionReasonCode', $categoria[1]); }
        $esquema = $a($cat, 'cac:TaxScheme'); $a($esquema, 'cbc:ID', $categoria[2], ['schemeID' => 'UN/ECE 5153', 'schemeAgencyID' => '6']); $a($esquema, 'cbc:Name', $categoria[3]); $a($esquema, 'cbc:TaxTypeCode', $categoria[4]);
    };
    foreach ([[$descuento_base, false, '02'], [$cargo_base, true, '50']] as [$importe_ajuste, $es_cargo, $codigo]) {
        if ($importe_ajuste <= 0) continue;
        $ajuste = $a($raiz, 'cac:AllowanceCharge');
        $a($ajuste, 'cbc:ChargeIndicator', $es_cargo ? 'true' : 'false');
        $a($ajuste, 'cbc:AllowanceChargeReasonCode', $codigo);
        $dinero($ajuste, 'cbc:Amount', $importe_ajuste);
        $dinero($ajuste, 'cbc:BaseAmount', $base);
    }
    foreach ($totales_afectacion as $afectacion => $montos) {
        if ($montos['base'] > 0) $impuesto($raiz,
            $afectacion === 'gravada' ? $montos['base'] - $descuento_base + $cargo_base : $montos['base'],
            $afectacion === 'gravada' ? $montos['igv'] - ($descuento - $descuento_base) + ($cargo - $cargo_base) : 0,
            false, $afectacion);
    }
    $monetario = $a($raiz, 'cac:LegalMonetaryTotal'); $dinero($monetario, 'cbc:LineExtensionAmount', $base);
    if ($descuento_base) $dinero($monetario, 'cbc:AllowanceTotalAmount', $descuento_base);
    if ($cargo_base) $dinero($monetario, 'cbc:ChargeTotalAmount', $cargo_base);
    $dinero($monetario, 'cbc:TaxInclusiveAmount', $total_ajustado); $dinero($monetario, 'cbc:PayableAmount', $total_ajustado);
    foreach ($lineas as $i => $linea) {
        $n = $a($raiz, 'cac:InvoiceLine'); $a($n, 'cbc:ID', (string)($i + 1));
        $a($n, 'cbc:InvoicedQuantity', (string)$linea['cantidad'], ['unitCode' => 'NIU']);
        $dinero($n, 'cbc:LineExtensionAmount', $linea['base']);
        $alterno = $a($a($n, 'cac:PricingReference'), 'cac:AlternativeConditionPrice');
        $dinero($alterno, 'cbc:PriceAmount', $linea['precio']); $a($alterno, 'cbc:PriceTypeCode', '01');
        $impuesto($n, $linea['base'], $linea['igv'], true, $linea['afectacion']);
        $a($a($n, 'cac:Item'), 'cbc:Description', $linea['descripcion']);
        $a($a($n, 'cac:Price'), 'cbc:PriceAmount', number_format($linea['precio'] * 100 / ($linea['afectacion'] === 'gravada' ? 10000 + $tasa : 10000), 6, '.', ''), ['currencyID' => 'PEN']);
    }
    return $doc->saveXML();
}

/** Firma enveloped RSA-SHA256; su aceptación debe verificarse en homologación vigente. */
function sunat_firmar_xml(string $xml, string $pkcs12, string $clave): string {
    $doc = sunat_cargar_xml($xml);
    $xp = new DOMXPath($doc); $xp->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
    $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
    if ($xp->query('//ds:Signature')->length) throw new DomainException('El XML ya tiene una firma.');
    $contenedor = $xp->query('/*/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent')->item(0);
    if (!$contenedor) throw new DomainException('Falta el contenedor UBL de firma.');
    if (!openssl_pkcs12_read($pkcs12, $material, $clave) || !openssl_x509_check_private_key($material['cert'], $material['pkey'])) {
        throw new DomainException('Certificado o contraseña inválidos.');
    }
    $certificado = openssl_x509_parse($material['cert']);
    if (!$certificado || $certificado['validFrom_time_t'] > time() || $certificado['validTo_time_t'] <= time()) throw new DomainException('El certificado no está vigente.');
    $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $ruc_firma = $xp->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID)');
    if ($ruc_firma === '') $ruc_firma = $xp->evaluate('string(/*/cac:AccountingSupplierParty/cbc:CustomerAssignedAccountID)');
    // Manual SUNAT, sección 3.1: el RUC se consigna en OU del Subject Name.
    $ou = $certificado['subject']['OU'] ?? [];
    $serial = implode(' ', is_array($ou) ? $ou : [$ou]);
    if (!sunat_ruc_valido($ruc_firma) || !preg_match('/(?<![0-9])' . preg_quote($ruc_firma, '/') . '(?![0-9])/', $serial)) {
        throw new DomainException('El certificado no identifica el RUC del emisor en OU.');
    }
    $llave = openssl_pkey_get_private($material['pkey']); $detalle = openssl_pkey_get_details($llave);
    if ($detalle['type'] !== OPENSSL_KEYTYPE_RSA || $detalle['bits'] < 2048) throw new DomainException('Se requiere un certificado RSA de al menos 2048 bits.');
    $digest = base64_encode(hash('sha256', $doc->C14N(false, false), true));
    $ns = 'http://www.w3.org/2000/09/xmldsig#';
    $a = static function (DOMNode $p, string $nombre, ?string $valor = null, array $atributos = []) use ($doc, $ns): DOMElement {
        $n = $doc->createElementNS($ns, 'ds:' . $nombre); if ($valor !== null) $n->appendChild($doc->createTextNode($valor));
        foreach ($atributos as $k => $v) $n->setAttribute($k, $v); $p->appendChild($n); return $n;
    };
    $sig = $a($contenedor, 'Signature', null, ['Id' => 'MESORA-SIGN']);
    $info = $a($sig, 'SignedInfo');
    $a($info, 'CanonicalizationMethod', null, ['Algorithm' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315']);
    $a($info, 'SignatureMethod', null, ['Algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256']);
    $referencia = $a($info, 'Reference', null, ['URI' => '']); $transforms = $a($referencia, 'Transforms');
    $a($transforms, 'Transform', null, ['Algorithm' => $ns . 'enveloped-signature']);
    $a($transforms, 'Transform', null, ['Algorithm' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315']);
    $a($referencia, 'DigestMethod', null, ['Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256']); $a($referencia, 'DigestValue', $digest);
    if (!openssl_sign($info->C14N(false, false), $firma, $llave, OPENSSL_ALGO_SHA256)) throw new RuntimeException('No se pudo firmar el XML.');
    $a($sig, 'SignatureValue', base64_encode($firma));
    $a($a($a($sig, 'KeyInfo'), 'X509Data'), 'X509Certificate', preg_replace('/-----[^-]+-----|\s/', '', $material['cert']));
    return $doc->saveXML();
}

/** ZIP mediante PharData nativo; no requiere habilitar la extensión ZIP. */
function sunat_crear_zip(string $nombre_xml, string $xml): string {
    if (!class_exists('PharData')) throw new RuntimeException('Falta PharData para el envío SUNAT.');
    if (!preg_match('/\A[0-9]{11}-(?:(?:01|03|07)-[BF][A-Z0-9]{3}-[1-9][0-9]{0,7}|RC-[0-9]{8}-[1-9][0-9]{0,4})\.xml\z/D', $nombre_xml)) throw new DomainException('Nombre fiscal inválido.');
    $doc = sunat_cargar_xml($xml); $xp = new DOMXPath($doc);
    $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $ruc = $xp->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID)');
    if ($ruc === '') $ruc = $xp->evaluate('string(/*/cac:AccountingSupplierParty/cbc:CustomerAssignedAccountID)');
    $id = $xp->evaluate('string(/*/cbc:ID)');
    $tipo = $doc->documentElement->localName === 'CreditNote' ? '07'
        : $xp->evaluate('string(/*/cbc:InvoiceTypeCode)');
    $esperado = $doc->documentElement->localName === 'SummaryDocuments' ? "$ruc-$id.xml" : "$ruc-$tipo-$id.xml";
    if ($nombre_xml !== $esperado) throw new DomainException('El XML no corresponde a la identidad del archivo fiscal.');
    $temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mesora_' . bin2hex(random_bytes(16)) . '.zip';
    try {
        $zip = new PharData($temporal, 0, null, Phar::ZIP);
        $zip->addFromString($nombre_xml, $xml); unset($zip);
        $contenido = file_get_contents($temporal); if ($contenido === false) throw new RuntimeException('No se pudo leer el ZIP fiscal.'); return $contenido;
    } finally { if (is_file($temporal)) unlink($temporal); }
}

/** Extrae únicamente la CDR esperada; nunca extrae archivos del ZIP al directorio web. */
function sunat_leer_cdr(string $bytes, string $identidad_esperada): array {
    if (!class_exists('PharData')) throw new RuntimeException('Falta PharData para leer la CDR.');
    if (strlen($bytes) > 8 * 1024 * 1024 || !preg_match('/\A([0-9]{11})-((?:01|03|07)-[BF][A-Z0-9]{3}-[1-9][0-9]{0,7}|RC-[0-9]{8}-[1-9][0-9]{0,4})\z/D', $identidad_esperada, $identidad)) throw new DomainException('Identidad CDR inválida.');
    $temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mesora_cdr_' . bin2hex(random_bytes(16)) . '.zip';
    try {
        if (file_put_contents($temporal, $bytes) === false) throw new RuntimeException('No se pudo abrir la CDR.');
        try { $zip = new PharData($temporal); } catch (Throwable $e) { throw new DomainException('ZIP CDR inválido.'); }
        try {
            if (count($zip) !== 1) throw new DomainException('La CDR debe contener un solo archivo.');
            $xml = null;
            foreach ($zip as $archivo) {
                if ($archivo->isDir() || $archivo->getSize() > 8 * 1024 * 1024
                    || $archivo->getFilename() !== 'R-' . $identidad_esperada . '.xml') throw new DomainException('Contenido ZIP CDR inválido.');
                $xml = $archivo->getContent();
            }
            if (!is_string($xml)) throw new DomainException('CDR vacía.');
        } finally { unset($zip); }
        $referencia = str_starts_with($identidad[2], 'RC-') ? $identidad[2] : substr($identidad[2], 3);
        return sunat_analizar_cdr_xml($xml, $referencia, $identidad[1]);
    } finally { if (is_file($temporal)) unlink($temporal); }
}

function sunat_analizar_cdr_xml(string $xml, string $numero_esperado, string $ruc_esperado): array {
    $doc = sunat_cargar_xml($xml); $xp = new DOMXPath($doc);
    $xp->registerNamespace('ar', 'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2');
    $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $respuestas = $xp->query('/ar:ApplicationResponse/cac:DocumentResponse');
    if ($respuestas->length !== 1) throw new DomainException('CDR sin una respuesta única.');
    $n = $respuestas->item(0);
    $referencia = $xp->evaluate('string(cac:DocumentReference/cbc:ID)', $n);
    $codigo = $xp->evaluate('string(cac:Response/cbc:ResponseCode)', $n);
    $mensaje = $xp->evaluate('string(cac:Response/cbc:Description)', $n);
    $ruc_receptor = $xp->evaluate('string(/ar:ApplicationResponse/cac:ReceiverParty/cac:PartyIdentification/cbc:ID)');
    if ($referencia !== $numero_esperado || $ruc_receptor !== $ruc_esperado || !sunat_ruc_valido($ruc_esperado)
        || !preg_match('/\A[0-9]{1,4}\z/D', $codigo)) throw new DomainException('La CDR no corresponde al comprobante y RUC esperados.');
    $notas = []; foreach ($xp->query('/ar:ApplicationResponse/cbc:Note') as $nota) $notas[] = $nota->textContent;
    return ['codigo' => $codigo, 'mensaje' => $mensaje, 'notas' => $notas, 'xml' => $xml,
        'estado' => $codigo === '0' ? ($notas ? 'aceptado_observado' : 'aceptado') : 'rechazado'];
}

/** Manual SUNAT, sendBill(fileName, contentFile). No SOAP extension requerida. */
function sunat_enviar_bill(string $zip, string $nombre_zip, string $usuario, string $clave, bool $produccion = false): string {
    if (!extension_loaded('curl')) throw new RuntimeException('Falta cURL para el envío SUNAT.');
    if (!preg_match('/\A[0-9]{11}-(?:01|03)-[BF][A-Z0-9]{3}-[1-9][0-9]{0,7}\.zip\z/D', $nombre_zip)
        || !str_starts_with($zip, "PK\x03\x04") || strlen($zip) > 8 * 1024 * 1024 || $usuario === '' || $clave === '') throw new DomainException('Datos de envío fiscal inválidos.');
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sobre = '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.sunat.gob.pe" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"><soap:Header><wsse:Security><wsse:UsernameToken><wsse:Username>' . $e($usuario) . '</wsse:Username><wsse:Password>' . $e($clave) . '</wsse:Password></wsse:UsernameToken></wsse:Security></soap:Header><soap:Body><ser:sendBill><fileName>' . $e($nombre_zip) . '</fileName><contentFile>' . base64_encode($zip) . '</contentFile></ser:sendBill></soap:Body></soap:Envelope>';
    $url = $produccion ? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService' : 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
    $curl = curl_init($url); $respuesta = ''; $exceso = false;
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $sobre, CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: urn:sendBill'], CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $parte) use (&$respuesta, &$exceso): int {
            if (strlen($respuesta) + strlen($parte) > 12 * 1024 * 1024) { $exceso = true; return 0; } $respuesta .= $parte; return strlen($parte);
        }]);
    try {
        $ok = curl_exec($curl); $estado = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($ok === false || $exceso) throw new RuntimeException('Envío sin confirmación: consultar el estado antes de reintentar.');
        $doc = sunat_cargar_xml($respuesta); $xp = new DOMXPath($doc); $xp->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        if ($xp->query('/soap:Envelope/soap:Body/soap:Fault')->length) throw new RuntimeException('SUNAT devolvió una falla SOAP; concilia el comprobante antes de reintentar.');
        if ($estado !== 200) throw new RuntimeException('SUNAT no confirmó el envío.');
        $nodos = $xp->query('/soap:Envelope/soap:Body/*[local-name()="sendBillResponse"]/*[local-name()="applicationResponse" or local-name()="document"]');
        if ($nodos->length !== 1 || ($cdr = base64_decode($nodos->item(0)->textContent, true)) === false) throw new RuntimeException('Respuesta SUNAT sin CDR válida.');
        return $cdr;
    } finally { curl_close($curl); }
}

/**
 * Recuperación de CDR de factura o nota de crédito de factura según manual SUNAT, secciones 2.7.1 y 2.8.
 * No se inventa consulta para boleta: el manual documenta 01/07/08, serie F.
 * Retorna CDR solo cuando el servicio entrega contenido; no deduce aceptación
 * de un código de transporte ni reenvía documentos ante una respuesta vacía.
 */
function sunat_consultar_cdr(string $identidad, string $usuario, string $clave, bool $produccion = true): array {
    if (!preg_match('/\A([0-9]{11})-(01|07)-(F[A-Z0-9]{3})-([1-9][0-9]{0,7})\z/D', $identidad, $partes)
        || !sunat_ruc_valido($partes[1]) || $usuario === '' || $clave === '') {
        throw new DomainException('La recuperación de CDR solo admite facturas o notas de factura con identidad completa.');
    }
    if (!$produccion) throw new DomainException('SUNAT documenta esta consulta de CDR para producción.');
    if (!extension_loaded('curl')) throw new RuntimeException('Falta cURL para consultar la CDR.');
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $cuerpo = '<ser:getStatusCdr><rucComprobante>' . $partes[1] . '</rucComprobante><tipoComprobante>' . $partes[2] . '</tipoComprobante><serieComprobante>'
        . $partes[3] . '</serieComprobante><numeroComprobante>' . $partes[4] . '</numeroComprobante></ser:getStatusCdr>';
    $sobre = '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.sunat.gob.pe" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"><soap:Header><wsse:Security><wsse:UsernameToken><wsse:Username>'
        . $e($usuario) . '</wsse:Username><wsse:Password>' . $e($clave) . '</wsse:Password></wsse:UsernameToken></wsse:Security></soap:Header><soap:Body>' . $cuerpo . '</soap:Body></soap:Envelope>';
    $curl = curl_init('https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService');
    $respuesta = '';
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $sobre,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: urn:getStatusCdr'],
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $parte) use (&$respuesta): int {
            if (strlen($respuesta) + strlen($parte) > 12 * 1024 * 1024) return 0;
            $respuesta .= $parte; return strlen($parte);
        }]);
    try {
        if (curl_exec($curl) === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new RuntimeException('SUNAT no confirmó la consulta de CDR. No reenvíes el comprobante.');
        }
        return sunat_analizar_consulta_cdr($respuesta);
    } finally { curl_close($curl); }
}

function sunat_analizar_consulta_cdr(string $respuesta): array {
    $doc = sunat_cargar_xml($respuesta); $xp = new DOMXPath($doc);
    $xp->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
    if ($xp->query('/soap:Envelope/soap:Body/soap:Fault')->length) throw new RuntimeException('SUNAT devolvió una falla al consultar la CDR.');
    $n = $xp->query('/soap:Envelope/soap:Body/*[local-name()="getStatusCdrResponse" and namespace-uri()="http://service.sunat.gob.pe"]/*[local-name()="statusCdr"]');
    if ($n->length !== 1) throw new DomainException('Respuesta de consulta CDR inválida.');
    $nodo = $n->item(0);
    $codigo = $xp->evaluate('string(*[local-name()="statusCode"])', $nodo);
    $mensaje = $xp->evaluate('string(*[local-name()="statusMessage"])', $nodo);
    $contenido = $xp->evaluate('string(*[local-name()="content"])', $nodo);
    if (!preg_match('/\A[0-9]{1,4}\z/D', $codigo)) throw new DomainException('Código de consulta CDR inválido.');
    $cdr = $contenido === '' ? null : base64_decode($contenido, true);
    if ($cdr === false || ($cdr !== null && !str_starts_with($cdr, "PK\x03\x04"))) throw new DomainException('Contenido de consulta CDR inválido.');
    return ['codigo' => $codigo, 'mensaje' => $mensaje, 'cdr' => $cdr];
}

/**
 * Una boleta por resumen: guía SUNAT Resumen de Boletas versión 2, enero 2018.
 * https://cpe.sunat.gob.pe/guias-y-manuales (SummaryDocuments UBL 2.0, Customization 1.1).
 * Condición 1 agrega y 3 comunica anulación; nunca altera el cobro.
 */
function sunat_generar_resumen(array $datos, string $id_resumen, string $fecha_envio, int $condicion = 1): string {
    if (($datos['tipo'] ?? '') !== 'boleta' || !in_array($condicion, [1, 3], true)
        || !preg_match('/\ARC-([0-9]{8})-[1-9][0-9]{0,4}\z/D', $id_resumen, $partes)) {
        throw new DomainException('Identificador o condición del resumen inválidos.');
    }
    // Reutiliza la validación de identidad, importes y redondeo de la boleta individual.
    $invoice = sunat_cargar_xml(sunat_generar_invoice($datos)); $xp = new DOMXPath($invoice);
    $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $envio = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_envio);
    if (!$envio || $envio->format('Y-m-d') !== $fecha_envio || $envio->format('Ymd') !== $partes[1]
        || $fecha_envio < $datos['fecha']) throw new DomainException('Fecha del resumen inválida.');
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElementNS('urn:sunat:names:specification:ubl:peru:schema:xsd:SummaryDocuments-1', 'SummaryDocuments'); $doc->appendChild($root);
    $ns = ['cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
        'sac' => 'urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1'];
    foreach ($ns as $p => $uri) $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $p, $uri);
    $a = static function (DOMNode $p, string $nombre, ?string $texto = null, array $attrs = []) use ($doc, $ns): DOMElement {
        $n = $doc->createElementNS($ns[explode(':', $nombre)[0]], $nombre);
        if ($texto !== null) $n->appendChild($doc->createTextNode($texto));
        foreach ($attrs as $k => $v) $n->setAttribute($k, $v); $p->appendChild($n); return $n;
    };
    $a($a($a($root, 'ext:UBLExtensions'), 'ext:UBLExtension'), 'ext:ExtensionContent');
    $a($root, 'cbc:UBLVersionID', '2.0'); $a($root, 'cbc:CustomizationID', '1.1'); $a($root, 'cbc:ID', $id_resumen);
    $a($root, 'cbc:ReferenceDate', $datos['fecha']); $a($root, 'cbc:IssueDate', $fecha_envio);
    $root->appendChild($doc->importNode($xp->query('/*/cac:Signature')->item(0), true));
    $supplier = $a($root, 'cac:AccountingSupplierParty');
    $a($supplier, 'cbc:CustomerAssignedAccountID', $datos['emisor']['ruc']); $a($supplier, 'cbc:AdditionalAccountID', '6');
    $a($a($a($supplier, 'cac:Party'), 'cac:PartyLegalEntity'), 'cbc:RegistrationName', $datos['emisor']['razon_social']);
    $linea = $a($root, 'sac:SummaryDocumentsLine'); $a($linea, 'cbc:LineID', '1'); $a($linea, 'cbc:DocumentTypeCode', '03');
    $a($linea, 'cbc:ID', $datos['serie'] . '-' . $datos['correlativo']);
    $customer = $a($linea, 'cac:AccountingCustomerParty');
    $a($customer, 'cbc:CustomerAssignedAccountID', $datos['cliente']['numero_documento']);
    $a($customer, 'cbc:AdditionalAccountID', $datos['cliente']['tipo_documento'] === 'ruc' ? '6' : '1');
    $a($a($linea, 'cac:Status'), 'cbc:ConditionCode', (string)$condicion);
    $a($linea, 'sac:TotalAmount', $datos['total'], ['currencyID' => 'PEN']);
    $base = $xp->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:LineExtensionAmount)');
    foreach (['01' => $base, '02' => '0.00', '03' => '0.00'] as $codigo => $importe) {
        $pago = $a($linea, 'sac:BillingPayment'); $a($pago, 'cbc:PaidAmount', $importe, ['currencyID' => 'PEN']); $a($pago, 'cbc:InstructionID', $codigo);
    }
    $tax = $a($linea, 'cac:TaxTotal'); $impuesto = $xp->evaluate('string(/*/cac:TaxTotal/cbc:TaxAmount)');
    $a($tax, 'cbc:TaxAmount', $impuesto, ['currencyID' => 'PEN']);
    $sub = $a($tax, 'cac:TaxSubtotal'); $a($sub, 'cbc:TaxAmount', $impuesto, ['currencyID' => 'PEN']);
    $scheme = $a($a($sub, 'cac:TaxCategory'), 'cac:TaxScheme'); $a($scheme, 'cbc:ID', '1000'); $a($scheme, 'cbc:Name', 'IGV'); $a($scheme, 'cbc:TaxTypeCode', 'VAT');
    return $doc->saveXML();
}

/** Transporte SOAP fijo a SUNAT, sin redirects ni secretos en mensajes de error. */
function sunat_soap_resumen(string $metodo, array $campos, string $usuario, string $clave, bool $produccion): string {
    if (!in_array($metodo, ['sendSummary', 'getStatus'], true) || $usuario === '' || $clave === '' || !extension_loaded('curl')) throw new DomainException('Solicitud SOAP inválida.');
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $cuerpo = '<ser:' . $metodo . '>';
    foreach ($campos as $nombre => $valor) {
        if (!in_array($nombre, ['fileName', 'contentFile', 'ticket'], true)) throw new DomainException('Campo SOAP inválido.');
        $cuerpo .= '<' . $nombre . '>' . $e($valor) . '</' . $nombre . '>';
    }
    $cuerpo .= '</ser:' . $metodo . '>';
    $sobre = '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.sunat.gob.pe" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"><soap:Header><wsse:Security><wsse:UsernameToken><wsse:Username>'
        . $e($usuario) . '</wsse:Username><wsse:Password>' . $e($clave) . '</wsse:Password></wsse:UsernameToken></wsse:Security></soap:Header><soap:Body>' . $cuerpo . '</soap:Body></soap:Envelope>';
    $url = $produccion ? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService' : 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
    $curl = curl_init($url); $respuesta = '';
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $sobre, CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: urn:' . $metodo],
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $parte) use (&$respuesta): int {
            if (strlen($respuesta) + strlen($parte) > 12 * 1024 * 1024) return 0;
            $respuesta .= $parte; return strlen($parte);
        }]);
    try {
        if (curl_exec($curl) === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) throw new RuntimeException('SUNAT no confirmó el resumen. Concilia antes de reintentar.');
        return $respuesta;
    } finally { curl_close($curl); }
}

function sunat_enviar_resumen(string $zip, string $nombre_zip, string $usuario, string $clave, bool $produccion = false): string {
    if (!preg_match('/\A[0-9]{11}-RC-[0-9]{8}-[1-9][0-9]{0,4}\.zip\z/D', $nombre_zip)
        || !str_starts_with($zip, "PK\x03\x04") || strlen($zip) > 8 * 1024 * 1024) throw new DomainException('Archivo de resumen inválido.');
    $respuesta = sunat_soap_resumen('sendSummary', ['fileName' => $nombre_zip, 'contentFile' => base64_encode($zip)], $usuario, $clave, $produccion);
    return sunat_analizar_ticket($respuesta);
}

function sunat_analizar_ticket(string $respuesta): string {
    $doc = sunat_cargar_xml($respuesta); $xp = new DOMXPath($doc); $xp->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
    $n = $xp->query('/soap:Envelope/soap:Body/*[local-name()="sendSummaryResponse" and namespace-uri()="http://service.sunat.gob.pe"]/*[local-name()="ticket"]');
    if ($n->length !== 1 || !preg_match('/\A[0-9]{1,64}\z/D', $n->item(0)->textContent)) throw new DomainException('SUNAT no devolvió un ticket válido. Concilia antes de reintentar.');
    return $n->item(0)->textContent;
}

function sunat_consultar_ticket(string $ticket, string $usuario, string $clave, bool $produccion = false): array {
    if (!preg_match('/\A[0-9]{1,64}\z/D', $ticket)) throw new DomainException('Ticket SUNAT inválido.');
    return sunat_analizar_estado_ticket(sunat_soap_resumen('getStatus', ['ticket' => $ticket], $usuario, $clave, $produccion));
}

function sunat_analizar_estado_ticket(string $respuesta): array {
    $doc = sunat_cargar_xml($respuesta); $xp = new DOMXPath($doc); $xp->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
    $n = $xp->query('/soap:Envelope/soap:Body/*[local-name()="getStatusResponse" and namespace-uri()="http://service.sunat.gob.pe"]/*[local-name()="status"]');
    if ($n->length !== 1) throw new DomainException('Respuesta de ticket inválida.');
    $codigo = $xp->evaluate('string(*[local-name()="statusCode"])', $n->item(0));
    $contenido = $xp->evaluate('string(*[local-name()="content"])', $n->item(0));
    if (!in_array($codigo, ['0', '98', '99'], true)) throw new DomainException('Estado del ticket desconocido.');
    $cdr = $contenido === '' ? null : base64_decode($contenido, true);
    if ($cdr === false || ($cdr !== null && !str_starts_with($cdr, "PK\x03\x04"))) throw new DomainException('CDR de ticket inválida.');
    if ($codigo === '0' && $cdr === null) throw new DomainException('Ticket procesado sin CDR: requiere conciliación.');
    return ['codigo' => $codigo, 'cdr' => $cdr];
}
