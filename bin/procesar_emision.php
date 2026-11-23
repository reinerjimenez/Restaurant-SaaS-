<?php
/** Procesador de ensayo local. Nunca envía a SUNAT. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (count($argv) !== 4 || $argv[1] !== '--simular' || !ctype_digit($argv[2]) || !ctype_digit($argv[3])) {
    fwrite(STDERR, "Uso: php bin/procesar_emision.php --simular RESTAURANTE_ID DOCUMENTO_ID\n");
    exit(1);
}
// Es obligatorio seleccionar la base en el entorno; nunca se usa el valor de .env por omisión.
$base_ensayo = getenv('DB_NAME');
if (!is_string($base_ensayo) || !preg_match('/\Amesora_[a-z0-9_]*test[a-z0-9_]*\z/', $base_ensayo)) {
    fwrite(STDERR, "Define DB_NAME con una base desechable mesora_*test*. No usar mesora ni producción.\n");
    exit(1);
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/emision_fiscal.php';
require_once __DIR__ . '/../includes/sunat_motor.php';
$db = getDB();
if ($db->query('SELECT DATABASE()')->fetchColumn() !== $base_ensayo) exit(1);
$restaurante_id = (int)$argv[2];
$id = (int)$argv[3];
try {
    if (!emision_esquema_disponible($db)) throw new DomainException('Falta ejecutar Database/011_emision_electronica.sql en la base de ensayo.');
    $db->beginTransaction();
    bloquear_restaurante($db, $restaurante_id);
    $st = $db->prepare('SELECT d.* FROM emision_documentos d JOIN emision_config c ON c.restaurante_id=d.restaurante_id
        WHERE d.id=? AND d.restaurante_id=? AND d.ambiente=? AND c.modo=? FOR UPDATE');
    $st->execute([$id, $restaurante_id, 'pruebas', 'pruebas']);
    $d = $st->fetch();
    if (!$d) throw new DomainException('Documento de ensayo no disponible para este restaurante.');
    $snapshot = json_decode($d['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
    if (($snapshot['proveedor'] ?? 'directo') !== 'directo') {
        throw new DomainException('Este documento de ensayo pertenece a un proveedor externo.');
    }
    if ($d['estado'] === 'prueba_aceptada') {
        $db->commit();
        echo "Documento ya procesado como ensayo; no se repitió.\n";
        exit;
    }
    $credenciales = emision_credenciales($restaurante_id);
    $p12 = file_get_contents($credenciales['p12_path']);
    if ($p12 === false) throw new DomainException('No se pudo leer el certificado de ensayo.');
    $ensayo = emision_simular_documento($snapshot, $p12, $credenciales['p12_password']);
    $db->prepare("UPDATE emision_documentos SET estado='prueba_aceptada', intentos=intentos+1,
        xml_firmado=?, cdr_xml=?, respuesta_codigo='SIMULADO', respuesta_mensaje=? WHERE id=? AND restaurante_id=? AND ambiente='pruebas'")
        ->execute([$ensayo['xml_firmado'], $ensayo['cdr_xml'], 'Ensayo local: firma, ZIP y CDR simulada conciliados. No se envió a SUNAT.', $id, $restaurante_id]);
    $db->commit();
    echo "Ensayo local completado #$id: XML firmado, ZIP " . $ensayo['zip_bytes'] . " bytes y CDR simulada; sin envío SUNAT.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $e instanceof DomainException ? $e->getMessage() . "\n" : "No se pudo procesar el ensayo. Revisa el entorno y los datos.\n");
    exit(1);
}
