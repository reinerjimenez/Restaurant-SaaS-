<?php
/** Solicitud fiscal separada del cobro y del comprobante original. */
require_once '../config/db.php';
requireRole(['admin','superadmin']);
require_once '../includes/emision_ajustes.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); jsonResponse(false, null, 'Método no permitido.'); }
$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
if (!is_array($input)) jsonResponse(false, null, 'Solicitud inválida.');
$id = filter_var($input['documento_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$tipo = $input['tipo'] ?? null;
$motivo = $input['motivo'] ?? null;
if (!$id || !is_string($tipo) || !is_string($motivo)) jsonResponse(false, null, 'Completa el documento y motivo.');
$db = getDB();
try {
    $st = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='emision_ajustes'");
    if ((int)$st->fetchColumn() !== 1) throw new DomainException('Falta ejecutar manualmente Database/015_ajustes_emision_electronica.sql.');
    $ajuste_id = emision_solicitar_ajuste($db,(int)$_SESSION['restaurante_id'],(int)$_SESSION['usuario_id'],(int)$id,$tipo,$motivo);
    jsonResponse(true,['ajuste_id'=>$ajuste_id], 'Ajuste preparado. No se revirtió el cobro; espera la CDR antes de informar una anulación fiscal.');
} catch (DomainException $e) {
    jsonResponse(false,null,$e->getMessage());
} catch (Throwable $e) {
    error_log('Falló la solicitud de ajuste fiscal.');
    http_response_code(500);
    jsonResponse(false,null,'No se pudo preparar el ajuste fiscal.');
}
