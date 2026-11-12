<?php
// Importación exclusivamente local de un respaldo sintético validado.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/verificar_entorno.php';

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
            } elseif ($caracter === '\\') {
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
    $registro['utc'] = gmdate('c');
    $linea = json_encode($registro, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($ruta, $linea, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('No se pudo persistir la evidencia de operación.');
    }
}

if ($argc !== 3) {
    fwrite(STDERR, "Uso: php restaurar_respaldo.php <ola1.sql> <manifiesto.json>\n");
    exit(1);
}

$pdo = null;
$bloqueo = false;
$directorio = dirname($argv[1]);
$evidencia = $directorio . DIRECTORY_SEPARATOR . 'evidencia.jsonl';
$inicio = gmdate('c');
$archivo = realpath($argv[1]);
$sha256 = $archivo !== false ? hash_file('sha256', $archivo) : false;
try {
    $m = verificar_manifiesto_ensayo('C:/ensayo_privado/manifiesto_vm.json');
    $rutaManifiesto = realpath($argv[2]);
    if ($archivo === false || is_link($argv[1]) || $rutaManifiesto === false ||
        !is_file($evidencia) || !is_array($m) || empty($m['mariadb_server_id'])) {
        throw new RuntimeException('Faltan respaldo, manifiesto de VM o identidad de servidor autorizados.');
    }
    $datos = json_decode((string)file_get_contents($rutaManifiesto), true, 512, JSON_THROW_ON_ERROR);
    if (($datos['origen'] ?? '') !== 'restaurant_ola1_test' ||
        ($datos['destino'] ?? '') !== 'restaurant_ola1_restore' ||
        !is_int($datos['tamano'] ?? null) || !is_string($datos['sha256'] ?? null) ||
        !is_array($datos['tablas'] ?? null) || $datos['tablas'] === [] ||
        $datos['sha256'] !== $sha256 || filesize($archivo) !== $datos['tamano']) {
        throw new RuntimeException('Manifiesto, tamaño o SHA-256 no coinciden.');
    }

    $python = getenv('OLA1_PYTHON') ?: 'python';
    $comando = [$python, __DIR__ . '/validar_respaldo.py', $archivo, $rutaManifiesto];
    $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo iniciar la validación SQL local.');
    }
    $salida = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($proceso) !== 0 || !str_contains($salida, 'SQL del respaldo validado')) {
        throw new RuntimeException('El SQL no pertenece al subconjunto autorizado.');
    }

    $clave = getenv('OLA1_RESTAURADOR_PASS');
    if (!is_string($clave) || $clave === '') {
        throw new RuntimeException('Falta la credencial temporal del restaurador de ensayo.');
    }
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=restaurant_ola1_restore;charset=utf8mb4',
        'ola1_restaurador', $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false]);
    unset($clave);
    $identidad = $pdo->query('SELECT VERSION() version, @@hostname host, @@port puerto,
        @@datadir datadir, @@server_id server_id, DATABASE() esquema, CURRENT_USER() cuenta')
        ->fetch(PDO::FETCH_ASSOC);
    if (!str_contains($identidad['version'], 'MariaDB') ||
        $identidad['version'] !== $m['mariadb_version'] ||
        $identidad['host'] !== $m['hostname'] || (int)$identidad['puerto'] !== 3306 ||
        (int)$identidad['server_id'] !== (int)$m['mariadb_server_id'] ||
        strcasecmp((string)realpath($identidad['datadir']), (string)realpath($m['datadir'])) !== 0 ||
        $identidad['esquema'] !== 'restaurant_ola1_restore' ||
        !in_array($identidad['cuenta'], ['ola1_restaurador@localhost', 'ola1_restaurador@127.0.0.1'], true)) {
        throw new RuntimeException('La identidad efectiva de VM, servidor, cuenta o esquema no coincide.');
    }

    // El lock serializa restauraciones controladas. El perfil de ensayo debe impedir escrituras ajenas.
    if ((int)$pdo->query("SELECT GET_LOCK('ola1_respaldo_restauracion', 10)")->fetchColumn() !== 1) {
        throw new RuntimeException('No se pudo reservar el destino para restauración.');
    }
    $bloqueo = true;
    $objetos = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = 'restaurant_ola1_restore'")->fetchColumn();
    if ($objetos !== 0) {
        throw new RuntimeException('El esquema de destino no está vacío.');
    }

    $sql = (string)file_get_contents($archivo);
    $sentencias = separar_sentencias_respaldo($sql);
    if (count($sentencias) === 0) {
        throw new RuntimeException('El respaldo no contiene sentencias.');
    }
    foreach ($sentencias as $sentencia) {
        $pdo->exec($sentencia);
    }
    if (!hash_equals($datos['sha256'], hash_file('sha256', $archivo)) ||
        filesize($archivo) !== $datos['tamano']) {
        throw new RuntimeException('El archivo original cambió durante la importación.');
    }
    registrar_evidencia_respaldo($evidencia, ['evento' => 'restauracion_completada',
        'inicio_utc' => $inicio, 'fin_utc' => gmdate('c'), 'archivo' => basename($archivo),
        'tamano' => $datos['tamano'], 'sha256' => $datos['sha256'],
        'vm_uuid' => $m['vm_uuid'], 'server_id' => (int)$identidad['server_id'],
        'esquema' => $identidad['esquema'], 'sentencias' => count($sentencias)]);
    echo "Respaldo sintético importado; registrar conciliación antes de emitir dictamen.\n";
} catch (Throwable $error) {
    $motivo = $error instanceof PDOException
        ? 'Error PDO SQLSTATE ' . $error->getCode()
        : $error->getMessage();
    try {
        if (is_file($evidencia)) {
            registrar_evidencia_respaldo($evidencia, ['evento' => 'restauracion_fallida',
                'inicio_utc' => $inicio, 'fin_utc' => gmdate('c'), 'archivo' => basename($argv[1]),
                'sha256' => $sha256, 'motivo' => $motivo]);
        }
    } catch (Throwable $ignorado) {
        // El error primario se informa sin ocultarlo.
    }
    fwrite(STDERR, 'Restauración detenida: ' . $motivo . PHP_EOL);
    exit(1);
} finally {
    if ($bloqueo && $pdo instanceof PDO) {
        $pdo->query("SELECT RELEASE_LOCK('ola1_respaldo_restauracion')");
    }
}
