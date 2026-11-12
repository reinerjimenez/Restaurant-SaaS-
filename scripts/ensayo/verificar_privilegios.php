<?php
// Revisión de grants efectivos para cuentas sintéticas de la VM.
declare(strict_types=1);
require __DIR__ . '/verificar_entorno.php';
require __DIR__ . '/perfil_privilegios.php';
try {
    $m = verificar_manifiesto_ensayo('C:/ensayo_privado/manifiesto_vm.json');
    $clave = getenv('OLA1_OPERADOR_PASS');
    if (!is_string($clave) || $clave === '') {
        throw new RuntimeException('Falta credencial temporal.');
    }
    $db = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'ola1_operador', $clave,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    unset($clave);
    $id = $db->query('SELECT @@hostname AS host, @@port AS port, @@datadir AS datadir, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
    if ($id['host'] !== $m['hostname'] || (int)$id['port'] !== 3306 ||
        $id['version'] !== $m['mariadb_version'] ||
        strcasecmp((string)realpath($id['datadir']), (string)realpath($m['datadir'])) !== 0) {
        throw new RuntimeException('Servidor incorrecto.');
    }
    $perfiles = [
        'ola1_app' => ['restaurant_ola1_test', ['SELECT', 'INSERT', 'UPDATE', 'DELETE']],
        'ola1_migrador' => ['restaurant_ola1_test', ['SELECT', 'CREATE', 'ALTER', 'INDEX', 'INSERT']],
        'ola1_restaurador' => ['restaurant_ola1_restore', ['SELECT', 'CREATE', 'ALTER', 'INDEX', 'INSERT']],
        'ola1_origen_ro' => ['restaurant_ola1_test', ['SELECT']],
        'ola1_restore_ro' => ['restaurant_ola1_restore', ['SELECT']],
    ];
    foreach ($perfiles as $cuenta => [$base, $permitidos]) {
        $grants = $db->query("SHOW GRANTS FOR '$cuenta'@'127.0.0.1'")->fetchAll(PDO::FETCH_COLUMN);
        if (!privilegios_validos($grants, $base, $permitidos)) {
            throw new RuntimeException('Perfil de permisos incorrecto.');
        }
    }
    echo "Cinco perfiles de grants coinciden con la matriz de ensayo.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Grants no verificados.\n");
    exit(1);
}
