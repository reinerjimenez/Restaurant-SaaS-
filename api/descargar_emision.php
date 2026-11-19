<?php
require_once '../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../includes/emision_fiscal.php';
require_once '../includes/emision_pdf.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); jsonResponse(false, null, 'Usa el formulario de descarga.'); }
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$archivo = $_POST['archivo'] ?? '';
if (!$id || !in_array($archivo, ['xml','cdr','pdf'], true)) jsonResponse(false, null, 'Archivo no válido.');
$db = getDB();
try {
    if (!emision_esquema_disponible($db)) throw new DomainException('Falta instalar el módulo de emisión.');
    $st = $db->prepare('SELECT ambiente,tipo,serie,correlativo,estado,snapshot_json,respuesta_mensaje,xml_firmado,cdr_xml
        FROM emision_documentos WHERE id=? AND restaurante_id=?');
    $st->execute([$id, (int)$_SESSION['restaurante_id']]);
    $d = $st->fetch();
    if ($archivo === 'pdf' && $d) {
        try {
            $snapshot = json_decode($d['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $contenido = emision_generar_pdf($d, $snapshot);
        } catch (Throwable $e) {
            error_log('Falló la representación PDF de un documento fiscal.');
            http_response_code(500);
            jsonResponse(false, null, 'No se pudo generar la representación PDF.');
        }
    } else {
        $contenido = $d ? $d[$archivo === 'xml' ? 'xml_firmado' : 'cdr_xml'] : null;
    }
    if (!is_string($contenido) || $contenido === '') { http_response_code(404); jsonResponse(false, null, 'Archivo no encontrado o aún no generado.'); }
    header('Content-Type: ' . ($archivo === 'pdf' ? 'application/pdf' : 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="' . $d['ambiente'] . '-' . $id . '-' . $archivo
        . ($archivo === 'pdf' ? '.pdf' : '.xml') . '"');
    echo $contenido;
} catch (Throwable $e) {
    if ($e instanceof DomainException) jsonResponse(false, null, $e->getMessage());
    error_log('Falló la descarga de un archivo fiscal.');
    http_response_code(500);
    jsonResponse(false, null, 'No se pudo descargar el archivo.');
}
