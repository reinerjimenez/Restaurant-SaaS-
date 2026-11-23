<?php
/** Ejecutar desde tarea programada autorizada; nunca accesible por HTTP. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$lote = ($argv[1] ?? '') === '--pendientes';
if ((!$lote && count($argv) !== 4) || ($lote && count($argv) !== 3)
    || !in_array($argv[1] ?? '', ['--enviar','--consultar','--pendientes'], true)
    || !ctype_digit($argv[2] ?? '') || (int)$argv[2] < 1
    || (!$lote && (!ctype_digit($argv[3] ?? '') || (int)$argv[3] < 1))) {
    fwrite(STDERR, "Uso: php bin/enviar_emision.php --enviar|--consultar RESTAURANTE_ID DOCUMENTO_ID\nO: php bin/enviar_emision.php --pendientes RESTAURANTE_ID (máximo 20)\n"); exit(1);
}
$base = getenv('DB_NAME');
if (!$base) { fwrite(STDERR, "Define DB_NAME explícitamente en el proceso antes de ejecutar.\n"); exit(1); }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/emision_procesador.php';
try {
    $db = getDB();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $base) throw new DomainException('La base conectada difiere del entorno.');
    if ($lote) {
        if (!emision_produccion_autorizada((int)$argv[2])) throw new DomainException('Producción no autorizada para este restaurante.');
        $st=$db->prepare("SELECT id,estado FROM emision_documentos WHERE restaurante_id=? AND ambiente='produccion'
            AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json, '$.proveedor')), 'directo') = 'directo'
            AND estado IN ('pendiente','procesando','incierto','pendiente_ticket') ORDER BY id LIMIT 20");
        $st->execute([(int)$argv[2]]);
        foreach ($st->fetchAll() as $d) {
            try { $r=emision_procesar_real($db,(int)$argv[2],(int)$d['id'],$d['estado']!=='pendiente'); }
            catch (Throwable $e) { $r=['estado'=>'error','mensaje'=>'No se procesó este documento. Revisar configuración y evidencia.']; }
            echo json_encode(['id'=>(int)$d['id']]+$r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
        }
        exit;
    }
    $r = emision_procesar_real($db, (int)$argv[2], (int)$argv[3], $argv[1] === '--consultar');
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(in_array($r['estado'], ['aceptado','aceptado_observado'], true) ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, $e instanceof DomainException ? $e->getMessage() . "\n" : "No se pudo procesar el documento. Revisa el entorno y conserva la evidencia.\n");
    exit(1);
}
