<?php
/** Gestión privada de conexiones NubeFacT por restaurante y ambiente. */
declare(strict_types=1);

require_once __DIR__ . '/sunat_motor.php';
require_once __DIR__ . '/nubefact_api.php';
require_once __DIR__ . '/../config/finanzas.php';

function nubefact_esquema_disponible(PDO $db): bool {
    $st = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
        AND table_name IN ('emision_nubefact_config','emision_nubefact_documentos')");
    return (int)$st->fetchColumn() === 2;
}

function nubefact_config(PDO $db, int $restaurante_id, string $ambiente, bool $bloquear = false): ?array {
    if (!in_array($ambiente, ['pruebas', 'produccion'], true) || !nubefact_esquema_disponible($db)) return null;
    $st = $db->prepare('SELECT * FROM emision_nubefact_config WHERE restaurante_id = ? AND ambiente = ?'
        . ($bloquear ? ' FOR UPDATE' : ''));
    $st->execute([$restaurante_id, $ambiente]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function nubefact_produccion_autorizada(int $restaurante_id): bool {
    return getenv('NUBEFACT_PRODUCCION_AUTORIZADA_' . $restaurante_id) === '1';
}

function nubefact_config_activa(array $config): bool {
    if (empty($config['habilitado'])) return false;
    return $config['ambiente'] === 'pruebas' || ($config['ambiente'] === 'produccion'
        && !empty($config['verificado_en']) && nubefact_produccion_autorizada((int)$config['restaurante_id']));
}

function nubefact_clave(): string {
    $valor = getenv('MESORA_NUBEFACT_KEY');
    $clave = is_string($valor) ? base64_decode($valor, true) : false;
    if (!is_string($clave) || strlen($clave) !== 32 || !extension_loaded('openssl')) {
        throw new DomainException('La custodia de credenciales de NubeFacT no está configurada en el servidor.');
    }
    return $clave;
}

function nubefact_datos_autenticados(array $config): string {
    return implode('|', ['mesora:nubefact:v1', (int)$config['restaurante_id'], $config['ambiente'], $config['ruc'], $config['ruta']]);
}

function nubefact_cifrar_token(string $token, array $config): string {
    nubefact_validar_token($token);
    $iv = random_bytes(12);
    $cifrado = openssl_encrypt($token, 'aes-256-gcm', nubefact_clave(), OPENSSL_RAW_DATA, $iv,
        $tag, nubefact_datos_autenticados($config), 16);
    if ($cifrado === false) throw new RuntimeException('No se pudo custodiar el Token.');
    return "\x01" . $iv . $tag . $cifrado;
}

function nubefact_token(array $config): string {
    $bytes = $config['token_cifrado'] ?? '';
    if (!is_string($bytes) || strlen($bytes) < 37 || $bytes[0] !== "\x01") {
        throw new DomainException('El Token de NubeFacT no está disponible.');
    }
    $token = openssl_decrypt(substr($bytes, 29), 'aes-256-gcm', nubefact_clave(), OPENSSL_RAW_DATA,
        substr($bytes, 1, 12), substr($bytes, 13, 16), nubefact_datos_autenticados($config));
    if ($token === false) throw new DomainException('No se pudo abrir el Token de NubeFacT.');
    nubefact_validar_token($token);
    return $token;
}

/** Evita reutilizar en NubeFacT la identidad de un comprobante de emisión directa. */
function nubefact_exigir_sin_series_directas(PDO $db, array $config): void {
    $series = [$config['serie_boleta'], $config['serie_factura']];
    $local = $db->prepare("SELECT 1 FROM emision_documentos d
        LEFT JOIN emision_nubefact_documentos n ON n.documento_id=d.id AND n.restaurante_id=d.restaurante_id
        WHERE d.restaurante_id=? AND d.ambiente=? AND d.serie IN (?,?) AND n.documento_id IS NULL LIMIT 1");
    $local->execute([$config['restaurante_id'], $config['ambiente'], ...$series]);
    if ($local->fetchColumn()) throw new DomainException('Una serie ya tiene documentos de emisión directa en este ambiente.');
    if ($config['ambiente'] !== 'produccion') return;

    $reservada = $db->prepare("SELECT 1 FROM emision_config c
        LEFT JOIN emision_nubefact_config n ON n.restaurante_id=c.restaurante_id AND n.ambiente='produccion'
        WHERE c.modo='produccion' AND n.restaurante_id IS NULL AND c.ruc=?
          AND (c.serie_boleta=? OR c.serie_factura=?) LIMIT 1");
    $reservada->execute([$config['ruc'], $config['serie_boleta'], $config['serie_factura']]);
    if ($reservada->fetchColumn()) throw new DomainException('Una serie está configurada para emisión directa en producción con este RUC.');
    $st = $db->prepare("SELECT 1 FROM emision_documentos d
        LEFT JOIN emision_nubefact_documentos n ON n.documento_id=d.id AND n.restaurante_id=d.restaurante_id
        WHERE d.ambiente='produccion' AND n.documento_id IS NULL AND d.serie IN (?,?)
          AND JSON_UNQUOTE(JSON_EXTRACT(d.snapshot_json, '$.emisor.ruc'))=? LIMIT 1");
    $st->execute([$config['serie_boleta'], $config['serie_factura'], $config['ruc']]);
    if ($st->fetchColumn()) throw new DomainException('Una serie ya tiene documentos de emisión directa para este RUC.');
}

/** La emisión directa no debe tomar una serie reservada por el proveedor. */
function nubefact_exigir_serie_directa_libre(PDO $db, string $ruc, string $serie): void {
    if (!nubefact_esquema_disponible($db)) return;
    $st = $db->prepare('SELECT 1 FROM emision_nubefact_config WHERE ruc_produccion=? AND (serie_boleta=? OR serie_factura=?) LIMIT 1');
    $st->execute([$ruc, $serie, $serie]);
    if ($st->fetchColumn()) throw new DomainException('Esta serie de producción está reservada para NubeFacT.');
}

function nubefact_config_guardar(PDO $db, int $restaurante_id, string $ambiente, array $entrada): void {
    if (!in_array($ambiente, ['pruebas', 'produccion'], true)) throw new DomainException('Ambiente inválido.');
    foreach (['ruc', 'ruta', 'token', 'serie_boleta', 'serie_factura'] as $campo) {
        if (!is_string($entrada[$campo] ?? null)) throw new DomainException('Configuración inválida.');
    }
    $nueva = ['restaurante_id' => $restaurante_id, 'ambiente' => $ambiente, 'ruc' => trim($entrada['ruc']),
        'ruta' => trim($entrada['ruta']),
        'serie_boleta' => strtoupper(trim($entrada['serie_boleta'])), 'serie_factura' => strtoupper(trim($entrada['serie_factura']))];
    $token = trim($entrada['token']);
    if (!sunat_ruc_valido($nueva['ruc']) || !preg_match('/\AB[A-Z0-9]{3}\z/', $nueva['serie_boleta'])
        || !preg_match('/\AF[A-Z0-9]{3}\z/', $nueva['serie_factura'])) {
        throw new DomainException('Indica un RUC válido y las series de cuatro caracteres autorizadas en NubeFacT.');
    }
    $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $fiscal = $db->prepare('SELECT ruc FROM emision_config WHERE restaurante_id = ?');
        $fiscal->execute([$restaurante_id]);
        if ($fiscal->fetchColumn() !== $nueva['ruc']) {
            throw new DomainException('Guarda primero el mismo RUC en los datos fiscales del restaurante.');
        }
        $previa = nubefact_config($db, $restaurante_id, $ambiente, true);
        $nueva['ruta'] = nubefact_ruta($nueva['ruta'] !== '' ? $nueva['ruta'] : ($previa['ruta'] ?? ''), $ambiente);
        $otra = nubefact_config($db, $restaurante_id, $ambiente === 'pruebas' ? 'produccion' : 'pruebas', true);
        if ($otra && ($nueva['serie_boleta'] === $otra['serie_boleta'] || $nueva['serie_factura'] === $otra['serie_factura'])) {
            throw new DomainException('Usa series distintas para pruebas y producción.');
        }
        nubefact_exigir_sin_series_directas($db, $nueva);
        $identidad_cambio = !$previa || (bool)array_diff_assoc($nueva, array_intersect_key($previa, $nueva));
        if ($previa && $identidad_cambio) {
            $st = $db->prepare('SELECT 1 FROM emision_nubefact_documentos n
                JOIN emision_documentos d ON d.id=n.documento_id AND d.restaurante_id=n.restaurante_id
                WHERE n.restaurante_id=? AND d.ambiente=? LIMIT 1');
            $st->execute([$restaurante_id, $ambiente]);
            if ($st->fetchColumn()) {
                throw new DomainException('El RUC, Ruta y series se conservan mientras existan documentos de este ambiente.');
            }
        }
        if ($token === '' && (!$previa || $nueva['ruta'] !== $previa['ruta'] || $nueva['ruc'] !== $previa['ruc'])) {
            throw new DomainException('Indica el Token correspondiente a la Ruta y al RUC.');
        }
        $cifrado = $token !== '' ? nubefact_cifrar_token($token, $nueva) : $previa['token_cifrado'];
        $habilitado = $ambiente === 'pruebas' ? (int)(($entrada['habilitado'] ?? '') === '1')
            : (int)($previa && !$identidad_cambio && $token === '' && $previa['habilitado']);
        $verificado = $ambiente === 'produccion' && $habilitado ? $previa['verificado_en'] : null;
        $valores = [$nueva['ruc'], $nueva['ruta'], hash('sha256', $nueva['ruta'], true), $cifrado,
            $nueva['serie_boleta'], $nueva['serie_factura'], $habilitado, $verificado, $restaurante_id, $ambiente];
        // UPDATE explícito para que un conflicto de unicidad jamás actualice otro restaurante.
        $sql = $previa
            ? 'UPDATE emision_nubefact_config SET ruc=?,ruta=?,ruta_hash=?,token_cifrado=?,serie_boleta=?,serie_factura=?,habilitado=?,verificado_en=?
                WHERE restaurante_id=? AND ambiente=?'
            : 'INSERT INTO emision_nubefact_config (ruc,ruta,ruta_hash,token_cifrado,serie_boleta,serie_factura,habilitado,verificado_en,restaurante_id,ambiente)
                VALUES (?,?,?,?,?,?,?,?,?,?)';
        $db->prepare($sql)->execute($valores);
        registrarAuditoria($db, 'Guardó conexión NubeFacT', 'Ambiente: ' . $ambiente, true);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
            throw new DomainException('La Ruta o una de las series de producción para este RUC ya está reservada.');
        }
        throw $e;
    }
}

function nubefact_config_habilitar(PDO $db, int $restaurante_id, bool $habilitar, string $confirmacion, bool $es_superadmin, bool $series_confirmadas): void {
    $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $c = nubefact_config($db, $restaurante_id, 'produccion', true);
        if (!$c) throw new DomainException('Guarda primero las credenciales de producción.');
        if ($habilitar) {
            if (!$es_superadmin || !nubefact_produccion_autorizada($restaurante_id) || $confirmacion !== $c['ruc'] || !$series_confirmadas) {
                throw new DomainException('La activación requiere revisión del superadministrador, autorización del servidor y confirmación del RUC y las series.');
            }
            $fiscal = $db->prepare('SELECT ruc FROM emision_config WHERE restaurante_id = ?');
            $fiscal->execute([$restaurante_id]);
            if ($fiscal->fetchColumn() !== $c['ruc']) throw new DomainException('El RUC guardado ya no coincide con la ficha fiscal.');
            nubefact_exigir_sin_series_directas($db, $c);
            nubefact_token($c);
        }
        $sql = $habilitar
            ? "UPDATE emision_nubefact_config SET habilitado=1, verificado_en=NOW() WHERE restaurante_id=? AND ambiente='produccion'"
            : "UPDATE emision_nubefact_config SET habilitado=0, verificado_en=NULL WHERE restaurante_id=? AND ambiente='produccion'";
        $db->prepare($sql)->execute([$restaurante_id]);
        registrarAuditoria($db, $habilitar ? 'Aprobó producción NubeFacT' : 'Pausó producción NubeFacT',
            'RUC: ' . $c['ruc'], true);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
