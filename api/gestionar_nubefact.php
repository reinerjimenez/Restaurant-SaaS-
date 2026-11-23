<?php
require_once '../config/db.php';
requireRole(['atencion','encargado','admin','superadmin']);
require_once '../includes/nubefact_procesador.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); jsonResponse(false,null,'Método no permitido.'); }
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$accion = $_POST['accion'] ?? '';
if (!$id || !in_array($accion,['enviar','consultar','conciliar'],true)) {
    http_response_code(400); jsonResponse(false,null,'Solicitud inválida.');
}
$restaurante_id = (int)$_SESSION['restaurante_id'];
if ($accion === 'conciliar' && !in_array($_SESSION['rol'],['admin','superadmin'],true)) {
    http_response_code(403); jsonResponse(false,null,'Acceso denegado.');
}
try {
    $db = getDB();
    if (!nubefact_esquema_disponible($db)) { http_response_code(404); jsonResponse(false,null,'Documento no disponible.'); }
    if ($accion === 'conciliar') {
        $referencia = $_POST['referencia'] ?? '';
        if (!is_string($referencia)) throw new DomainException('Referencia inválida.');
        nubefact_registrar_referencia($db,$restaurante_id,$id,trim($referencia));
    } elseif (nubefact_procesar($db,$restaurante_id,$id,$accion === 'consultar') === null) {
        http_response_code(404); jsonResponse(false,null,'Documento no disponible.');
    }
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        jsonResponse(true,['emision_id'=>$id],'Operación registrada. Revisa el estado actualizado del comprobante.');
    }
    header('Location: ' . BASE_URL . '/roles/atencion/emision_view.php?id=' . $id, true, 303);
    exit;
} catch (DomainException $e) {
    http_response_code(409); jsonResponse(false,null,$e->getMessage());
} catch (Throwable $e) {
    error_log('No se completó la operación NubeFacT.');
    http_response_code(503); jsonResponse(false,null,'No se completó la operación. Revisa el estado antes de reintentar.');
}
