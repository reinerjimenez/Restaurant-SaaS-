<?php
/**
 * Anula el documento sin alterar el cobro ni el cierre de caja.
 */
require_once '../config/db.php';
requireRole(['admin', 'superadmin']);
require_once '../config/finanzas.php';
require_once '../includes/emision_fiscal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
$comprobante_id = filter_var($input['comprobante_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$motivo = $input['motivo'] ?? null;
if (!$comprobante_id || !is_string($motivo) || trim($motivo) === '' || mb_strlen($motivo) > 200) {
    jsonResponse(false, null, 'Indica el comprobante y un motivo de hasta 200 caracteres.');
}

$restaurante_id = (int)$_SESSION['restaurante_id'];
$db = getDB();

try {
    $db->beginTransaction();
    bloquear_restaurante($db, $restaurante_id);
    $stmt = $db->prepare('SELECT pedido_id, numero_comprobante, total, anulado
        FROM comprobantes WHERE id = ? AND restaurante_id = ? FOR UPDATE');
    $stmt->execute([$comprobante_id, $restaurante_id]);
    $comprobante = $stmt->fetch();
    if (!$comprobante || $comprobante['anulado']) {
        throw new DomainException('El comprobante no existe o ya está anulado.');
    }
    if (emision_esquema_disponible($db)) {
        $stFiscal = $db->prepare('SELECT id FROM emision_documentos WHERE restaurante_id=? AND pedido_id=? LIMIT 1');
        $stFiscal->execute([$restaurante_id,(int)$comprobante['pedido_id']]);
        if ($stFiscal->fetchColumn()) throw new DomainException('Esta venta tiene evidencia fiscal. Usa nota de crédito o baja fiscal y espera su CDR; no se puede anular solo localmente.');
    }
    $motivo = trim($motivo);
    $db->prepare('UPDATE comprobantes SET anulado = 1, motivo_anulacion = ? WHERE id = ? AND restaurante_id = ?')
        ->execute([$motivo, $comprobante_id, $restaurante_id]);
    registrarAuditoria($db, 'Anuló comprobante',
        "Comprobante #$comprobante_id ({$comprobante['numero_comprobante']}) · Pedido #{$comprobante['pedido_id']} · S/ {$comprobante['total']} · $motivo · Sin reverso de pago", true);
    $db->commit();
    jsonResponse(true, null, 'Documento interno anulado. No se modificó ningún documento SUNAT.');
} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al anular comprobante');
}
