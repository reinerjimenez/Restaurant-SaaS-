<?php
// Comprobación local y de solo lectura. Ejecutar fuera del directorio público.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function verificar_configuracion_ensayo(array $argumentos): array
{
    if (count($argumentos) !== 4) {
        throw new RuntimeException('Uso: php <script> <raíz-copia-web> <nombre-VM> <datadir-VM>');
    }
    [, $raiz, $host, $datos] = $argumentos;
    $raiz = realpath($raiz);
    $datos = realpath($datos);
    if (PHP_OS_FAMILY !== 'Windows' || strcasecmp($host, (string)gethostname()) !== 0 ||
        $raiz === false || basename($raiz) !== 'system-restaurant-ola1' ||
        !is_file($raiz . '/config/db.php') || $datos === false || !is_dir($datos)) {
        throw new RuntimeException('Equipo, ruta de la copia o directorio de datos de ensayo inválido.');
    }

    $archivo = $raiz . '/.env';
    $lineas = is_file($archivo) && !is_link($archivo)
        ? @file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
    if ($lineas === false) {
        throw new RuntimeException('Falta el .env privado en la copia de ensayo.');
    }

    $esperado = [
        'DB_HOST' => '127.0.0.1',
        'DB_NAME' => 'restaurant_ola1_test',
        'DB_USER' => 'ola1_app',
        'DB_CHARSET' => 'utf8mb4',
        'BASE_URL' => '/system-restaurant-ola1',
        'APIS_NET_TOKEN' => '',
    ];
    $config = [];
    foreach ($lineas as $linea) {
        if (trim($linea) === '' || str_starts_with(ltrim($linea), '#')) {
            continue;
        }
        if (!preg_match('/^([A-Z_]+)=([^\r\n]*)$/D', $linea, $partes)) {
            throw new RuntimeException('Formato del .env inválido.');
        }
        [, $clave, $valor] = $partes;
        if (($clave !== 'DB_PASS' && !array_key_exists($clave, $esperado)) ||
            array_key_exists($clave, $config) || getenv($clave) !== false ||
            array_key_exists($clave, $_SERVER) || array_key_exists($clave, $_ENV)) {
            throw new RuntimeException('Variable inesperada, duplicada o heredada en la configuración de ensayo.');
        }
        $config[$clave] = $valor;
    }
    foreach ($esperado as $clave => $valor) {
        if (($config[$clave] ?? null) !== $valor) {
            throw new RuntimeException('El .env no coincide con la configuración de ensayo.');
        }
    }
    // Formato de contraseña elegido para reproducir la lectura del .env sin comillas ni espacios.
    if (!preg_match('/^[A-Za-z0-9_-]{24,}$/D', $config['DB_PASS'] ?? '')) {
        throw new RuntimeException('Se requiere una contraseña de ensayo de 24 caracteres o más en formato base64url.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('pdo_mysql no está disponible en PHP CLI.');
    }
    return [$config['DB_PASS'], $host, $datos];
}

function verificar_manifiesto_ensayo(string $ruta): array
{
    if (!is_file($ruta) || is_link($ruta)) {
        throw new RuntimeException('Falta el manifiesto privado de la VM.');
    }
    $manifiesto = json_decode((string)file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
    foreach (['vm_uuid', 'hostname', 'os_caption', 'mariadb_pid', 'mariadb_exe', 'datadir', 'mariadb_version', 'mysql_ini', 'mysql_ini_sha256', 'apache_ini', 'apache_ini_sha256'] as $clave) {
        if (empty($manifiesto[$clave])) {
            throw new RuntimeException('Manifiesto incompleto.');
        }
    }
    if (!preg_match('/^[0-9a-f-]{36}$/i', $manifiesto['vm_uuid']) ||
        !is_int($manifiesto['mariadb_pid']) || $manifiesto['mariadb_pid'] <= 0 ||
        !is_dir($manifiesto['datadir']) ||
        !is_file($manifiesto['mysql_ini']) || !is_file($manifiesto['apache_ini']) ||
        !hash_equals(strtolower($manifiesto['mysql_ini_sha256']), hash_file('sha256', $manifiesto['mysql_ini'])) ||
        !hash_equals(strtolower($manifiesto['apache_ini_sha256']), hash_file('sha256', $manifiesto['apache_ini']))) {
        throw new RuntimeException('Identidad de VM inválida.');
    }
    $comando = ['powershell.exe', '-NoProfile', '-NonInteractive', '-File', __DIR__ . '/verificar_vm.ps1', '-Manifiesto', $ruta];
    $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias);
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo comprobar la VM.');
    }
    $salida = stream_get_contents($tuberias[1]);
    fclose($tuberias[1]);
    stream_get_contents($tuberias[2]);
    fclose($tuberias[2]);
    if (proc_close($proceso) !== 0) {
        throw new RuntimeException('Identidad o red de VM no verificadas.');
    }
    $observado = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
    if ($observado['vm_uuid'] !== $manifiesto['vm_uuid'] ||
        $observado['hostname'] !== $manifiesto['hostname'] ||
        $observado['mariadb_pid'] !== $manifiesto['mariadb_pid']) {
        throw new RuntimeException('La VM observada difiere del manifiesto.');
    }
    return $manifiesto;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        verificar_configuracion_ensayo($argv);
        echo "Configuración local válida. Aún falta verificar red, Apache y servidor MariaDB.\n";
    } catch (RuntimeException $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
