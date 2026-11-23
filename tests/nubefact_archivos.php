<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/nubefact_archivos.php';

$snapshot = ['serie' => 'F001', 'correlativo' => 19, 'emisor' => ['ruc' => '20100066603']];
$xml = <<<'XML'
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">
    <cbc:ID>F001-00000019</cbc:ID>
    <cac:AccountingSupplierParty><cac:Party><cac:PartyIdentification>
        <cbc:ID schemeID="6">20100066603</cbc:ID>
    </cac:PartyIdentification></cac:Party></cac:AccountingSupplierParty>
</Invoice>
XML;
nubefact_verificar_xml($xml, $snapshot);
$alterno = str_replace(['PartyIdentification', '<cbc:ID schemeID="6">20100066603</cbc:ID>'],
    ['PartyTaxScheme', '<cbc:CompanyID>20100066603</cbc:CompanyID>'], $xml);
nubefact_verificar_xml($alterno, $snapshot);
foreach ([
    str_replace('20100066603', '20600695771', $xml),
    str_replace('F001-00000019', 'F002-00000019', $xml),
    str_replace('F001-00000019', 'F001-00000091', $xml),
    str_replace(['<Invoice ', '</Invoice>'], ['<CreditNote ', '</CreditNote>'], $xml),
] as $archivo_ajeno) {
    try {
        nubefact_verificar_xml($archivo_ajeno, $snapshot);
    } catch (DomainException $e) {
        continue;
    }
    throw new RuntimeException('La comprobación admitió un XML ajeno.');
}
echo "OK NubeFacT: pertenencia de XML UBL por RUC, serie y número, sin red ni BD.\n";
