<?php
// Ejecutor manual local. Ningún CREATE se envía antes de validar VM, servidor y autorización.
declare(strict_types=1);
require __DIR__ . '/verificar_entorno.php';
if ($argc !== 2 || $argv[1] !== '--crear-bases') {
    fwrite(STDERR, "Uso: php aprovisionar_bases.php --crear-bases\n");
    exit(1);
}
try {
    $m = verificar_manifiesto_ensayo('C:/ensayo_privado/manifiesto_vm.json');
    $permiso = json_decode((string)file_get_contents('C:/ensayo_privado/autorizar_bases.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($permiso['vm_uuid'] ?? '') !== $m['vm_uuid'] || ($permiso['aprobado'] ?? false) !== true ||
        ($permiso['sql_sha256'] ?? '') !== hash_file('sha256', __DIR__ . '/crear_bases_ensayo.sql') ||
        ($permiso['manifiesto_sha256'] ?? '') !== hash_file('sha256', 'C:/ensayo_privado/manifiesto_vm.json')) {
        throw new RuntimeException('Autorización de bases inválida.');
    }
    $clave = getenv('OLA1_OPERADOR_PASS');
    if (!is_string($clave) || $clave === '') {
        throw new RuntimeException('Falta la contraseña temporal del operador.');
    }
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'ola1_operador', $clave,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    unset($clave);
    $id = $pdo->query('SELECT VERSION() AS version, @@hostname AS host, @@port AS port, @@datadir AS datadir, CURRENT_USER() AS cuenta')->fetch(PDO::FETCH_ASSOC);
    if ($id['host'] !== $m['hostname'] || (int)$id['port'] !== 3306 ||
        strcasecmp((string)realpath($id['datadir']), (string)realpath($m['datadir'])) !== 0 ||
        $id['version'] !== $m['mariadb_version'] || $id['cuenta'] !== 'ola1_operador@127.0.0.1') {
        throw new RuntimeException('Servidor de aprovisionamiento incorrecto.');
    }
    $existentes = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('restaurant_ola1_test','restaurant_ola1_restore')")->fetchAll(PDO::FETCH_COLUMN);
    if ($existentes !== []) {
        throw new RuntimeException('Una base de ensayo ya existe.');
    }
    $sql = (string)file_get_contents(__DIR__ . '/crear_bases_ensayo.sql');
    $sin_comentarios = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);
    $sentencias = array_values(array_filter(array_map('trim', explode(';', $sin_comentarios))));
    $esperadas = [
        'CREATE DATABASE restaurant_ola1_test CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
        'CREATE DATABASE restaurant_ola1_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    ];
    if (array_map(static fn (string $s): string => preg_replace('/\s+/', ' ', $s), $sentencias) !== $esperadas) {
        throw new RuntimeException('El SQL de bases no coincide con las dos sentencias autorizadas.');
    }
    // CREATE DATABASE confirma cada sentencia. La segunda falla deja la primera creada.
    foreach ($sentencias as $sentencia) {
        $pdo->exec($sentencia);
    }
    echo "Bases de ensayo creadas. Verificar ambas en information_schema.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Aprovisionamiento detenido. Revisar manifiesto, permisos y estado de ambas bases.\n");
    exit(1);
}
