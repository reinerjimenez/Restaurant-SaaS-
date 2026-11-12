<?php
// Ejecuta solo consultas aprobadas después de verificar la VM y el esquema.
declare(strict_types=1);
require __DIR__ . '/verificar_entorno.php';
require __DIR__ . '/validar_consulta.php';

$archivos = [
    'origen' => ['restaurant_ola1_test', __DIR__ . '/../../tests/integration/conciliacion.sql'],
    'destino' => ['restaurant_ola1_restore', __DIR__ . '/../../tests/integration/conciliacion_restore.sql'],
    'esquema' => ['restaurant_ola1_test', __DIR__ . '/../../tests/integration/diagnostico_esquema.sql'],
];
if ($argc !== 2 || !isset($archivos[$argv[1]])) {
    fwrite(STDERR, "Uso: php ejecutar_lecturas.php origen|destino|esquema\n");
    exit(1);
}
try {
    $m = verificar_manifiesto_ensayo('C:/ensayo_privado/manifiesto_vm.json');
    [$base, $ruta] = $archivos[$argv[1]];
    $sql = (string)file_get_contents($ruta);
    $consulta = preg_replace('/^[ \t]*--[^\r\n]*\r?$/m', '', $sql);
    if (!consulta_ensayo_valida($consulta, $base)) {
        throw new RuntimeException('Archivo de consultas fuera del perfil.');
    }
    $clave = getenv('OLA1_LECTOR_PASS');
    if (!is_string($clave) || $clave === '') {
        throw new RuntimeException('Falta credencial temporal de lectura.');
    }
    $usuario = $base === 'restaurant_ola1_test' ? 'ola1_origen_ro' : 'ola1_restore_ro';
    $db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=' . $base . ';charset=utf8mb4',
        $usuario, $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    unset($clave);
    $id = $db->query('SELECT DATABASE() AS base, @@hostname AS host, @@port AS port, @@datadir AS datadir, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
    if ($id['base'] !== $base || $id['host'] !== $m['hostname'] || (int)$id['port'] !== 3306 ||
        $id['version'] !== $m['mariadb_version'] ||
        strcasecmp((string)realpath($id['datadir']), (string)realpath($m['datadir'])) !== 0) {
        throw new RuntimeException('Identidad o esquema de lectura incorrectos.');
    }
    foreach (explode(';', $consulta) as $sentencia) {
        if (trim($sentencia) === '') {
            continue;
        }
        $filas = $db->query($sentencia)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($filas, JSON_THROW_ON_ERROR) . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Lectura detenida. Revisar VM, esquema, consulta y permisos.\n");
    exit(1);
}
