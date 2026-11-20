<?php
/** Trabajador CLI de ajustes. No ejecuta SQL estructural ni crea cobros. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$lote = ($argv[1] ?? '') === '--pendientes';
if ((!$lote && count($argv) !== 4) || ($lote && count($argv) !== 3)
    || !in_array($argv[1] ?? '', ['--enviar','--consultar','--pendientes'], true)
    || !ctype_digit($argv[2] ?? '') || (int)$argv[2] < 1
    || (!$lote && (!ctype_digit($argv[3] ?? '') || (int)$argv[3] < 1))) {
    fwrite(STDERR,"Uso: php bin/enviar_ajuste.php --enviar|--consultar RESTAURANTE_ID AJUSTE_ID\n"
        . "O: php bin/enviar_ajuste.php --pendientes RESTAURANTE_ID\n"); exit(1);
}
$base = getenv('DB_NAME');
if (!$base) { fwrite(STDERR,"Define DB_NAME explícitamente.\n"); exit(1); }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/emision_ajustes_procesador.php';
try {
    $db = getDB();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $base) throw new DomainException('Base conectada distinta de DB_NAME.');
    if ($lote) {
        if (!emision_produccion_autorizada((int)$argv[2])) throw new DomainException('Producción no autorizada.');
        $st = $db->prepare("SELECT id,tipo,estado FROM emision_ajustes WHERE restaurante_id=? AND ambiente='produccion'
            AND (estado='pendiente' OR (tipo='baja_boleta' AND estado IN ('procesando','incierto','pendiente_ticket')))
            ORDER BY id LIMIT 20");
        $st->execute([(int)$argv[2]]);
        foreach ($st->fetchAll() as $a) {
            $r = emision_procesar_ajuste_real($db,(int)$argv[2],(int)$a['id'],$a['estado'] !== 'pendiente');
            echo json_encode(['id'=>(int)$a['id']]+$r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
        }
        exit;
    }
    $r = emision_procesar_ajuste_real($db,(int)$argv[2],(int)$argv[3],$argv[1] === '--consultar');
    echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
    exit(in_array($r['estado'], ['aceptado','aceptado_observado'], true) ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR,$e instanceof DomainException ? $e->getMessage() . "\n" : "No se pudo procesar el ajuste. Conserva la evidencia.\n");
    exit(1);
}
