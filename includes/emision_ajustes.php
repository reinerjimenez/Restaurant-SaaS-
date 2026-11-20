<?php
/** Ajustes fiscales aislados del comprobante y del cobro original. */
declare(strict_types=1);
require_once __DIR__ . '/emision_fiscal.php';

/** Nota de crédito 01 (anulación total), sobre los mismos importes del original. */
function sunat_generar_nota_credito(array $origen, string $serie, int $correlativo, string $fecha, string $motivo): string {
    if (!preg_match('/\A[FB][A-Z0-9]{3}\z/D', $serie) || $correlativo < 1 || $correlativo > 99999999
        || mb_strlen($motivo) < 3 || mb_strlen($motivo) > 250) throw new DomainException('Datos de nota de crédito inválidos.');
    $tipo = $origen['tipo'] === 'factura' ? '01' : '03';
    if (($tipo === '01' ? 'F' : 'B') !== $serie[0]) throw new DomainException('La serie de la nota no corresponde al comprobante.');
    $nota = $origen;
    $nota['serie'] = $serie;
    $nota['correlativo'] = $correlativo;
    $nota['fecha'] = $fecha;
    $doc = sunat_cargar_xml(sunat_generar_invoice($nota));
    $raiz = $doc->documentElement;
    $nsNota = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
    $nsCac = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    $nsCbc = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    $nueva = $doc->createElementNS($nsNota, 'CreditNote');
    foreach (iterator_to_array($raiz->attributes) as $attr) {
        if ($attr->namespaceURI === 'http://www.w3.org/2000/xmlns/' && $attr->nodeName !== 'xmlns') {
            $nueva->setAttributeNS($attr->namespaceURI, $attr->nodeName, $attr->value);
        }
    }
    while ($raiz->firstChild) $nueva->appendChild($raiz->firstChild);
    $doc->replaceChild($nueva, $raiz);
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('cac', $nsCac);
    $xp->registerNamespace('cbc', $nsCbc);
    $tipoInvoice = $xp->query('/*[local-name()="CreditNote"]/cbc:InvoiceTypeCode')->item(0);
    if ($tipoInvoice) $tipoInvoice->parentNode->removeChild($tipoInvoice);
    $referencia = $origen['serie'] . '-' . $origen['correlativo'];
    $crea = static function (DOMNode $p, string $ns, string $nombre, string $valor = '') use ($doc): DOMElement {
        $e = $doc->createElementNS($ns, $nombre);
        if ($valor !== '') $e->appendChild($doc->createTextNode($valor));
        $p->appendChild($e);
        return $e;
    };
    $discrepancia = $doc->createElementNS($nsCac, 'cac:DiscrepancyResponse');
    $crea($discrepancia, $nsCbc, 'cbc:ReferenceID', $referencia);
    $crea($discrepancia, $nsCbc, 'cbc:ResponseCode', '01');
    $crea($discrepancia, $nsCbc, 'cbc:Description', $motivo);
    $billing = $doc->createElementNS($nsCac, 'cac:BillingReference');
    $ref = $crea($billing, $nsCac, 'cac:InvoiceDocumentReference');
    $crea($ref, $nsCbc, 'cbc:ID', $referencia);
    $crea($ref, $nsCbc, 'cbc:DocumentTypeCode', $tipo);
    $currency = $nueva->getElementsByTagNameNS($nsCbc, 'DocumentCurrencyCode')->item(0);
    if (!$currency) throw new DomainException('Moneda fiscal ausente.');
    $nueva->insertBefore($discrepancia, $currency->nextSibling);
    $nueva->insertBefore($billing, $discrepancia->nextSibling);
    foreach (iterator_to_array($xp->query('/*[local-name()="CreditNote"]/cac:InvoiceLine')) as $linea) {
        $nuevo = $doc->createElementNS($nsCac, 'cac:CreditNoteLine');
        while ($linea->firstChild) $nuevo->appendChild($linea->firstChild);
        $linea->parentNode->replaceChild($nuevo, $linea);
        $cantidad = $xp->query('cbc:InvoicedQuantity', $nuevo)->item(0);
        if ($cantidad) {
            $creditada = $doc->createElementNS($nsCbc, 'cbc:CreditedQuantity', $cantidad->textContent);
            foreach (iterator_to_array($cantidad->attributes) as $atributo) $creditada->setAttribute($atributo->name, $atributo->value);
            $nuevo->replaceChild($creditada, $cantidad);
        }
    }
    return $doc->saveXML();
}

function emision_solicitar_ajuste(PDO $db, int $restaurante_id, int $usuario_id, int $documento_id,
    string $tipo, string $motivo): int {
    if (!in_array($tipo, ['nota_credito','baja_boleta'], true) || trim($motivo) === ''
        || mb_strlen($motivo) > 250 || $documento_id < 1) throw new DomainException('Solicitud de ajuste inválida.');
    if (!emision_esquema_disponible($db)) throw new DomainException('Falta el esquema de emisión.');
    $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $st = $db->prepare('SELECT d.*,c.modo,c.ruc,c.serie_nota_factura,c.serie_nota_boleta,
            c.correlativo_nota_factura,c.correlativo_nota_boleta,c.correlativo_nota_factura_pruebas,
            c.correlativo_nota_boleta_pruebas FROM emision_documentos d
            JOIN emision_config c ON c.restaurante_id=d.restaurante_id
            WHERE d.id=? AND d.restaurante_id=? FOR UPDATE');
        $st->execute([$documento_id,$restaurante_id]);
        $d = $st->fetch();
        if (!$d || $d['modo'] !== $d['ambiente'] || !in_array($d['estado'],
            $d['ambiente'] === 'pruebas' ? ['prueba_aceptada'] : ['aceptado','aceptado_observado'], true)) {
            throw new DomainException('Solo se ajusta un documento confirmado del mismo restaurante.');
        }
        if ($tipo === 'baja_boleta' && $d['tipo'] !== 'boleta') throw new DomainException('La baja por resumen corresponde solo a boletas.');
        if ($tipo === 'nota_credito' && $d['tipo'] !== 'factura') {
            throw new DomainException('La nota de crédito de boleta requiere resumen 07; este flujo aún no está disponible.');
        }
        $st = $db->prepare('SELECT id FROM emision_ajustes WHERE documento_id=? AND restaurante_id=? LIMIT 1');
        $st->execute([$documento_id,$restaurante_id]);
        if ($st->fetchColumn()) throw new DomainException('Este documento ya tiene un ajuste iniciado.');
        $origen = json_decode($d['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        if ($d['ruc'] !== $origen['emisor']['ruc']) throw new DomainException('El RUC del original cambió.');
        $serie = null; $numero = null;
        if ($tipo === 'nota_credito') {
            $columna = $d['tipo'] === 'factura' ? 'correlativo_nota_factura' : 'correlativo_nota_boleta';
            if ($d['ambiente'] === 'pruebas') $columna .= '_pruebas';
            $serie = $d[$d['tipo'] === 'factura' ? 'serie_nota_factura' : 'serie_nota_boleta'];
            if (!preg_match($d['tipo'] === 'factura' ? '/\AF[A-Z0-9]{3}\z/D' : '/\AB[A-Z0-9]{3}\z/D', $serie)) {
                throw new DomainException('Configura una serie de nota válida.');
            }
            $numero = (int)$d[$columna] + 1;
            if ($numero > 99999999) throw new DomainException('La serie de notas agotó su correlativo.');
            $db->prepare("UPDATE emision_config SET $columna=? WHERE restaurante_id=?")->execute([$numero,$restaurante_id]);
        }
        $snapshot = ['origen'=>$origen, 'origen_numero'=>$d['serie'] . '-' . $d['correlativo'],
            'motivo_codigo'=>$tipo === 'nota_credito' ? '01' : '03', 'motivo_texto'=>trim($motivo),
            'fecha'=>date('Y-m-d')];
        $db->prepare('INSERT INTO emision_ajustes (restaurante_id,documento_id,usuario_id,ambiente,tipo,
            serie,correlativo,motivo_codigo,motivo_texto,snapshot_json) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$restaurante_id,$documento_id,$usuario_id,$d['ambiente'],$tipo,$serie,$numero,
                $snapshot['motivo_codigo'],$snapshot['motivo_texto'],json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $id = (int)$db->lastInsertId();
        registrarAuditoria($db, 'Solicitó ajuste fiscal', "Ajuste #$id del documento #$documento_id. Sin reverso de pago.", true);
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
