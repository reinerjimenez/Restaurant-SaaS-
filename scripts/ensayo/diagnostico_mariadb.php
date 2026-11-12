<?php
// Diagnóstico PDO de solo lectura contra el loopback de la VM de ensayo.
declare(strict_types=1);

require __DIR__ . '/verificar_entorno.php';
ini_set('zend.exception_ignore_args', '1');

try {
    [$clave, $host, $datos] = verificar_configuracion_ensayo($argv);
    $manifiesto = verificar_manifiesto_ensayo('C:/ensayo_privado/manifiesto_vm.json');
    if (strcasecmp($host, $manifiesto['hostname']) !== 0 ||
        strcasecmp($datos, (string)realpath($manifiesto['datadir'])) !== 0) {
        throw new RuntimeException('Argumentos distintos del manifiesto.');
    }
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=restaurant_ola1_test;charset=utf8mb4',
        'ola1_app',
        $clave,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    unset($clave);
    $identidad = $pdo->query("SELECT VERSION() AS version, @@hostname AS host, @@port AS puerto,
        @@datadir AS datadir, DATABASE() AS esquema, CURRENT_USER() AS cuenta,
        @@character_set_database AS charset, @@collation_database AS collation")
        ->fetch(PDO::FETCH_ASSOC);
    $datos_servidor = realpath($identidad['datadir']);
    if (!str_contains($identidad['version'], 'MariaDB') ||
        strcasecmp($identidad['host'], $host) !== 0 || (int)$identidad['puerto'] !== 3306 ||
        $datos_servidor === false || strcasecmp($datos_servidor, $datos) !== 0 ||
        $identidad['esquema'] !== 'restaurant_ola1_test' ||
        !in_array($identidad['cuenta'], ['ola1_app@localhost', 'ola1_app@127.0.0.1'], true)) {
        throw new RuntimeException('La identidad efectiva del servidor no coincide con el ensayo.');
    }

    // SHOW GRANTS puede devolver un hash. Solo se examina internamente.
    $privilegios = [];
    foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $permiso) {
        if (str_contains(strtoupper($permiso), 'WITH GRANT OPTION') ||
            !preg_match('/^GRANT (.+?) ON (\*\.\*|`restaurant\\\\_ola1\\\\_test`\.\*) TO /', $permiso, $partes)) {
            throw new RuntimeException('La cuenta de aplicación tiene privilegios fuera del perfil de ensayo.');
        }
        if ($partes[2] === '*.*' && $partes[1] === 'USAGE') {
            continue;
        }
        if ($partes[2] === '*.*') {
            throw new RuntimeException('La cuenta de aplicación tiene privilegios globales.');
        }
        array_push($privilegios, ...explode(', ', $partes[1]));
    }
    sort($privilegios);
    if ($privilegios !== ['DELETE', 'INSERT', 'SELECT', 'UPDATE']) {
        throw new RuntimeException('Los permisos del esquema de ensayo no coinciden con los esperados.');
    }

    $variables = $pdo->query("SHOW VARIABLES WHERE Variable_name IN (
        'bind_address', 'datadir', 'port', 'default_storage_engine', 'sql_mode',
        'character_set_server', 'collation_server', 'autocommit', 'tx_isolation',
        'transaction_isolation', 'foreign_key_checks', 'check_constraint_checks',
        'innodb_lock_wait_timeout', 'innodb_flush_log_at_trx_commit',
        'max_prepared_stmt_count', 'time_zone', 'log_bin')")
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    $innodb = array_values(array_filter($pdo->query('SHOW ENGINES')->fetchAll(PDO::FETCH_ASSOC),
        static fn (array $motor): bool => strcasecmp($motor['Engine'], 'InnoDB') === 0));
    $json = $pdo->prepare('SELECT JSON_VALID(?)');
    $json->execute(['{}']);
    $json_valido = (int)$json->fetchColumn() === 1;

    echo json_encode([
        'identidad' => $identidad,
        'privilegios_ola1_app' => $privilegios,
        'variables' => $variables,
        'innodb' => $innodb,
        'json_valido' => $json_valido,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (($variables['bind_address'] ?? '') !== '127.0.0.1' ||
        $identidad['charset'] !== 'utf8mb4' || $identidad['collation'] !== 'utf8mb4_general_ci' ||
        ($variables['foreign_key_checks'] ?? '') !== 'ON' ||
        ($variables['check_constraint_checks'] ?? '') !== 'ON' ||
        !in_array($innodb[0]['Support'] ?? '', ['YES', 'DEFAULT'], true) ||
        ($innodb[0]['Transactions'] ?? '') !== 'YES' || !$json_valido) {
        fwrite(STDERR, "No se cumplen todas las precondiciones del motor. Revisar también los listeners del sistema.\n");
        exit(1);
    }
} catch (PDOException $error) {
    // Evitar mensajes del servidor que puedan contener usuarios, rutas o configuración.
    fwrite(STDERR, 'Falló el diagnóstico PDO. SQLSTATE: ' . $error->getCode() . PHP_EOL);
    exit(1);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
