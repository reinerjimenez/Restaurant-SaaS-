<?php
// Importación CLI de respaldos sintéticos ya validados.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/verificar_entorno.php';
require __DIR__ . '/perfil_privilegios.php';

function separar_sentencias_respaldo(string $sql): array
{
    $resultado = [];
    $inicio = 0;
    $comilla = null;
    $escape = false;
    $longitud = strlen($sql);
    for ($i = 0; $i < $longitud; $i++) {
        $caracter = $sql[$i];
        if ($comilla !== null) {
            if ($escape) {
                $escape = false;
            } elseif ($caracter === '\\' && $comilla !== '`') {
                $escape = true;
            } elseif ($caracter === $comilla) {
                if ($i + 1 < $longitud && $sql[$i + 1] === $comilla) {
                    $i++;
                } else {
                    $comilla = null;
                }
            }
        } elseif ($caracter === "'" || $caracter === '"' || $caracter === '`') {
            $comilla = $caracter;
        } elseif ($caracter === ';') {
            $parte = trim(substr($sql, $inicio, $i - $inicio));
            if ($parte !== '') {
                $resultado[] = $parte;
            }
            $inicio = $i + 1;
        }
    }
    if ($comilla !== null || trim(substr($sql, $inicio)) !== '') {
        throw new RuntimeException('El SQL está incompleto.');
    }
    return $resultado;
}

function registrar_evidencia_respaldo(string $ruta, array $registro): void
{
    if (is_link($ruta)) {
        throw new RuntimeException('El archivo de evidencia no puede ser un enlace.');
    }
    $registro['utc'] = gmdate('c');
    $linea = json_encode($registro, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $descriptor = fopen($ruta, 'ab');
    if ($descriptor === false) {
        throw new RuntimeException('No se pudo abrir el registro de evidencia.');
    }
    try {
        if (!flock($descriptor, LOCK_EX) || fwrite($descriptor, $linea) !== strlen($linea) || !fflush($descriptor)) {
            throw new RuntimeException('No se pudo persistir la evidencia de operación.');
        }
        if (function_exists('fsync')) {
            fsync($descriptor);
        }
    } finally {
        flock($descriptor, LOCK_UN);
        fclose($descriptor);
    }
}

function restauracion_ya_iniciada(string $ruta): bool
{
    if (is_link($ruta)) {
        throw new RuntimeException('El estado de restauración no puede ser un enlace.');
    }
    if (!is_file($ruta)) {
        return false;
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
        $evento = json_decode($linea, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($evento)) {
            throw new RuntimeException('El estado de restauración está corrupto; no se permite repetir.');
        }
        if (in_array($evento['evento'] ?? '', ['restauracion_iniciada', 'restauracion_fallida', 'restauracion_completada'], true)) {
            return true;
        }
    }
    return false;
}

function manifiesto_prueba_local(string $ruta): array
{
    if (!is_file($ruta) || is_link($ruta)) {
        throw new RuntimeException('Falta el manifiesto de la instancia temporal.');
    }
    $m = json_decode((string)file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
    $claves = ['modo', 'hostname', 'puerto', 'datadir', 'mariadb_version', 'mariadb_server_id', 'mariadb_pid', 'mariadb_exe'];
    if (!is_array($m) || array_diff($claves, array_keys($m)) !== [] || array_diff(array_keys($m), $claves) !== [] ||
        ($m['modo'] ?? '') !== 'prueba_local_desechable' ||
        $m['hostname'] !== gethostname() || !is_int($m['puerto']) || $m['puerto'] < 1024 || $m['puerto'] > 65535 ||
        !is_int($m['mariadb_server_id']) || !is_int($m['mariadb_pid']) || $m['mariadb_pid'] <= 0 ||
        !is_string($m['mariadb_version']) || !str_starts_with($m['mariadb_version'], '10.4.') ||
        !is_string($m['datadir']) || !is_dir($m['datadir']) ||
        !preg_match('/^mariadb_p03_[a-f0-9]{32}$/D', basename(dirname($m['datadir']))) ||
        realpath($m['mariadb_exe']) !== realpath('C:/xampp/mysql/bin/mysqld.exe')) {
        throw new RuntimeException('El manifiesto no describe una instancia MariaDB temporal válida.');
    }
    $proceso = ['powershell.exe', '-NoProfile', '-NonInteractive', '-File',
        __DIR__ . '/verificar_instancia_temporal.ps1', '-Manifiesto', $ruta];
    $handle = proc_open($proceso, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($handle)) {
        throw new RuntimeException('No se pudo comprobar el proceso local.');
    }
    $salida = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($handle) !== 0) {
        throw new RuntimeException('El listener no coincide con el proceso temporal declarado.');
    }
    $observado = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
    if ($observado['pid'] !== $m['mariadb_pid'] || $observado['puerto'] !== $m['puerto'] ||
        realpath($observado['ejecutable']) !== realpath($m['mariadb_exe'])) {
        throw new RuntimeException('La identidad del proceso temporal cambió.');
    }
    return $m;
}

function inventario_destino(PDO $pdo): array
{
    $filas = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = 'restaurant_ola1_restore' AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
    return $filas;
}

$modoLocal = $argc === 5 && $argv[3] === '--prueba-local';
if ((!$modoLocal && $argc !== 3) || ($modoLocal && $argc !== 5)) {
    fwrite(STDERR, "Uso normal: php restaurar_respaldo.php <ola1.sql> <corte.json>\n" .
        "Solo ensayo desechable: php restaurar_respaldo.php <ola1.sql> <corte.json> --prueba-local <instancia.json>\n");
    exit(1);
}

$pdo = null;
$bloqueo = false;
$inicioImportacion = false;
$directorio = dirname($argv[1]);
$evidencia = $directorio . DIRECTORY_SEPARATOR . 'evidencia.jsonl';
$inicio = gmdate('c');
$archivo = realpath($argv[1]);
$shaInicial = $archivo !== false ? hash_file('sha256', $archivo) : false;
try {
    $m = $modoLocal
        ? manifiesto_prueba_local($argv[4])
        : verificar_manifiesto_ensayo('C:/ensayo_privado/manifiesto_vm.json');
    $rutaManifiesto = realpath($argv[2]);
    if ($archivo === false || is_link($argv[1]) || $rutaManifiesto === false || is_link($argv[2]) ||
        !is_file($evidencia) || is_link($evidencia) || empty($m['mariadb_server_id'])) {
        throw new RuntimeException('Faltan respaldo, manifiesto o identidad autorizada del servidor.');
    }
    $jsonManifiesto = file_get_contents($rutaManifiesto);
    if ($jsonManifiesto === false) {
        throw new RuntimeException('No se pudo leer el manifiesto del respaldo.');
    }
    $hashManifiesto = hash('sha256', $jsonManifiesto);
    $datos = json_decode($jsonManifiesto, true, 512, JSON_THROW_ON_ERROR);
    $clavesManifiesto = ['origen', 'destino', 'tablas', 'tamano', 'sha256'];
    if (!is_array($datos) || array_diff($clavesManifiesto, array_keys($datos)) !== [] ||
        array_diff(array_keys($datos), $clavesManifiesto) !== [] ||
        ($datos['origen'] ?? '') !== 'restaurant_ola1_test' ||
        ($datos['destino'] ?? '') !== 'restaurant_ola1_restore' ||
        !is_int($datos['tamano'] ?? null) || $datos['tamano'] <= 0 ||
        !is_string($datos['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $datos['sha256']) ||
        !is_array($datos['tablas'] ?? null) || $datos['tablas'] === [] ||
        array_filter($datos['tablas'], static fn ($tabla): bool => !is_string($tabla) || !preg_match('/^[a-z_][a-z_0-9]*$/D', $tabla)) !== [] ||
        count(array_unique($datos['tablas'])) !== count($datos['tablas'])) {
        throw new RuntimeException('El manifiesto del respaldo no es válido o contiene tablas repetidas.');
    }
    if (filesize($archivo) !== $datos['tamano'] || !hash_equals($datos['sha256'], (string)$shaInicial)) {
        throw new RuntimeException('Tamaño o SHA-256 inicial no coincide.');
    }

    $python = getenv('OLA1_PYTHON') ?: 'python';
    $comando = [$python, __DIR__ . '/validar_respaldo.py', $archivo, $rutaManifiesto];
    $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo iniciar el analizador SQL local.');
    }
    $salida = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($proceso) !== 0 || !str_contains($salida, 'SQL del respaldo validado') ||
        !hash_equals($hashManifiesto, (string)hash_file('sha256', $rutaManifiesto))) {
        throw new RuntimeException('SQL o manifiesto alterados o fuera del formato autorizado.');
    }
    $sql = file_get_contents($archivo);
    if ($sql === false || strlen($sql) !== $datos['tamano'] || !hash_equals($datos['sha256'], hash('sha256', $sql))) {
        throw new RuntimeException('El respaldo cambió entre validación y lectura.');
    }
    $sentencias = separar_sentencias_respaldo($sql);
    if ($sentencias === []) {
        throw new RuntimeException('El respaldo no contiene sentencias completas.');
    }

    $clave = getenv('OLA1_RESTAURADOR_PASS');
    if (!is_string($clave) || $clave === '') {
        throw new RuntimeException('Falta la credencial temporal del restaurador de ensayo.');
    }
    $puerto = (int)($m['puerto'] ?? 3306);
    $pdo = new PDO("mysql:host=127.0.0.1;port=$puerto;dbname=restaurant_ola1_restore;charset=utf8mb4",
        'ola1_restaurador', $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
    unset($clave);
    $identidad = $pdo->query('SELECT VERSION() version, @@hostname host, @@port puerto,
        @@datadir datadir, @@server_id server_id, DATABASE() esquema, CURRENT_USER() cuenta')
        ->fetch(PDO::FETCH_ASSOC);
    if (!str_contains($identidad['version'], 'MariaDB') ||
        $identidad['version'] !== $m['mariadb_version'] ||
        $identidad['host'] !== $m['hostname'] || (int)$identidad['puerto'] !== $puerto ||
        (int)$identidad['server_id'] !== (int)$m['mariadb_server_id'] ||
        strcasecmp((string)realpath($identidad['datadir']), (string)realpath($m['datadir'])) !== 0 ||
        $identidad['esquema'] !== 'restaurant_ola1_restore' ||
        !in_array($identidad['cuenta'], ['ola1_restaurador@localhost', 'ola1_restaurador@127.0.0.1'], true)) {
        throw new RuntimeException('La identidad efectiva de servidor, cuenta o esquema no coincide.');
    }
    $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
    if (!privilegios_validos($grants, 'restaurant_ola1_restore', ['SELECT', 'INSERT', 'CREATE', 'ALTER', 'INDEX'])) {
        throw new RuntimeException('La cuenta restauradora excede o incumple el perfil autorizado.');
    }
    if ((int)$pdo->query("SELECT GET_LOCK('ola1_respaldo_restauracion', 10)")->fetchColumn() !== 1) {
        throw new RuntimeException('No se pudo reservar el destino para restauración.');
    }
    $bloqueo = true;
    $eventos = $directorio . DIRECTORY_SEPARATOR . 'restauracion_estado.jsonl';
    if (restauracion_ya_iniciada($eventos)) {
        throw new RuntimeException('Este respaldo ya tuvo un intento; no se permite repetir la restauración.');
    }
    $objetos = 0;
    foreach (['TABLES' => 'TABLE_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA',
        'TRIGGERS' => 'TRIGGER_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $catalogo => $columnaEsquema) {
        $consulta = $pdo->prepare("SELECT COUNT(*) FROM information_schema.$catalogo WHERE $columnaEsquema = ?");
        $consulta->execute(['restaurant_ola1_restore']);
        $objetos += (int)$consulta->fetchColumn();
    }
    if ($objetos !== 0) {
        throw new RuntimeException('El esquema de destino contiene objetos y no está vacío.');
    }

    registrar_evidencia_respaldo($eventos, ['evento' => 'restauracion_iniciada',
        'inicio_utc' => $inicio, 'archivo' => basename($archivo), 'tamano' => $datos['tamano'],
        'sha256' => $datos['sha256'], 'server_id' => (int)$identidad['server_id'],
        'esquema' => $identidad['esquema']]);
    registrar_evidencia_respaldo($evidencia, ['evento' => 'restauracion_iniciada',
        'inicio_utc' => $inicio, 'archivo' => basename($archivo), 'tamano' => $datos['tamano'],
        'sha256' => $datos['sha256'], 'server_id' => (int)$identidad['server_id'],
        'esquema' => $identidad['esquema']]);
    $inicioImportacion = true;
    foreach ($sentencias as $sentencia) {
        $pdo->exec($sentencia);
    }
    $actuales = inventario_destino($pdo);
    $tablasActuales = array_column($actuales, 'TABLE_NAME');
    $esperadas = $datos['tablas'];
    sort($tablasActuales);
    sort($esperadas);
    if ($tablasActuales !== $esperadas) {
        throw new RuntimeException('El inventario del destino no coincide con el manifiesto.');
    }
    foreach ($actuales as $tabla) {
        if (strcasecmp((string)$tabla['ENGINE'], 'InnoDB') !== 0) {
            throw new RuntimeException('El destino contiene una tabla que no usa InnoDB.');
        }
    }
    if (!hash_equals($datos['sha256'], (string)hash_file('sha256', $archivo)) ||
        filesize($archivo) !== $datos['tamano'] ||
        !hash_equals($hashManifiesto, (string)hash_file('sha256', $rutaManifiesto))) {
        throw new RuntimeException('El respaldo o manifiesto cambió durante la importación.');
    }
    registrar_evidencia_respaldo($eventos, ['evento' => 'restauracion_completada',
        'fin_utc' => gmdate('c'), 'sentencias' => count($sentencias), 'tablas' => $tablasActuales]);
    registrar_evidencia_respaldo($evidencia, ['evento' => 'restauracion_completada',
        'fin_utc' => gmdate('c'), 'archivo' => basename($archivo), 'tamano' => $datos['tamano'],
        'sha256' => $datos['sha256'], 'server_id' => (int)$identidad['server_id'],
        'esquema' => $identidad['esquema'], 'sentencias' => count($sentencias), 'tablas' => $tablasActuales]);
    echo "Respaldo importado y el inventario InnoDB coincide.\n";
} catch (Throwable $error) {
    $motivo = $error instanceof PDOException ? 'Error PDO SQLSTATE ' . $error->getCode() : $error->getMessage();
    if ($inicioImportacion) {
        try {
            $eventos = $directorio . DIRECTORY_SEPARATOR . 'restauracion_estado.jsonl';
            registrar_evidencia_respaldo($eventos, ['evento' => 'restauracion_fallida',
                'fin_utc' => gmdate('c'), 'motivo' => $motivo]);
        } catch (Throwable $ignorado) {
            // El evento de inicio ya bloquea un segundo intento incluso tras una caída del registro.
        }
        try {
            if (is_file($evidencia)) {
                registrar_evidencia_respaldo($evidencia, ['evento' => 'restauracion_fallida',
                    'fin_utc' => gmdate('c'), 'archivo' => basename($argv[1]), 'motivo' => $motivo]);
            }
        } catch (Throwable $ignorado) {
            // La evidencia principal de inicio evita ocultar que el esquema puede estar parcial.
        }
    } else {
        try {
            if (is_file($evidencia) && !is_link($evidencia)) {
                registrar_evidencia_respaldo($evidencia, ['evento' => 'restauracion_preflight_fallido',
                    'archivo' => basename($argv[1]), 'sha256' => $shaInicial,
                    'motivo' => $motivo]);
            }
        } catch (Throwable $ignorado) {
            // Un fallo al escribir evidencia no cambia el rechazo ni expone datos.
        }
    }
    fwrite(STDERR, 'Restauración detenida: ' . $motivo . PHP_EOL);
    exit(1);
} finally {
    if ($bloqueo && $pdo instanceof PDO) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('ola1_respaldo_restauracion')");
        } catch (Throwable $ignorado) {
            // El servidor libera automáticamente el advisory lock al cerrar la conexión.
        }
    }
}
