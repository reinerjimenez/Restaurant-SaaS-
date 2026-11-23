<?php
/** Conciliación de pertenencia del XML UBL descargado del proveedor. */
declare(strict_types=1);
require_once __DIR__ . '/sunat_motor.php';

function nubefact_verificar_xml(string $xml, array $snapshot): void {
    $doc = sunat_cargar_xml($xml);
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
    $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $ids = $xp->query('/inv:Invoice/cbc:ID');
    $ruc = $xp->evaluate('string(/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID[@schemeID="6"][1])');
    if ($ruc === '') {
        $ruc = $xp->evaluate('string(/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID[1])');
    }
    if ($ids->length !== 1 || !preg_match('/\A([FB][A-Z0-9]{3})-([0-9]{1,8})\z/', trim($ids->item(0)->textContent), $numero)
        || $numero[1] !== $snapshot['serie'] || (int)$numero[2] !== (int)$snapshot['correlativo']
        || trim($ruc) !== $snapshot['emisor']['ruc']) {
        throw new DomainException('El XML del proveedor no corresponde al comprobante.');
    }
}
