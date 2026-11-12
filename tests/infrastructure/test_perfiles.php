<?php
// Pruebas simuladas, sin conexión MariaDB.
declare(strict_types=1);
require __DIR__ . '/../../scripts/ensayo/perfil_privilegios.php';
require __DIR__ . '/../../scripts/ensayo/validar_consulta.php';

$base = 'restaurant_ola1_test';
$perfil = ["GRANT USAGE ON *.* TO 'ola1_app'@'127.0.0.1'",
    "GRANT SELECT, INSERT, UPDATE, DELETE ON `restaurant\\_ola1\\_test`.* TO 'ola1_app'@'127.0.0.1'"];
if (!privilegios_validos($perfil, $base, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) exit(1);
foreach ([
    "GRANT ALL PRIVILEGES ON *.* TO 'ola1_app'@'127.0.0.1'",
    "GRANT SELECT, INSERT, UPDATE, DELETE ON `restaurant\\_ola1\\_test`.* TO 'ola1_app'@'127.0.0.1' WITH GRANT OPTION",
    "GRANT SELECT ON `otra_base`.* TO 'ola1_app'@'127.0.0.1'",
    "GRANT CREATE ON `restaurant\\_ola1\\_test`.* TO 'ola1_app'@'127.0.0.1'",
    "GRANT `admin` TO 'ola1_app'@'127.0.0.1'",
] as $grant) {
    if (privilegios_validos([$grant], $base, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) exit(2);
}
if (!consulta_ensayo_valida('SELECT * FROM restaurant_ola1_test.turnos', $base) ||
    consulta_ensayo_valida('SELECT * FROM produccion.turnos', $base) ||
    consulta_ensayo_valida('DROP TABLE turnos', $base)) exit(3);
foreach (['diagnostico_esquema.sql' => $base, 'conciliacion.sql' => $base,
    'conciliacion_restore.sql' => 'restaurant_ola1_restore'] as $archivo => $esquema) {
    $texto = file_get_contents(__DIR__ . '/../integration/' . $archivo);
    $texto = preg_replace('/^[ \t]*--[^\r\n]*\r?$/m', '', $texto);
    if (!consulta_ensayo_valida($texto, $esquema)) {
        fwrite(STDERR, "Consulta autorizada rechazada: $archivo, indicadores " .
            preg_match('/\b(?:INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE|GRANT|REVOKE|CALL|OUTFILE|LOAD_FILE|USE)\b/i', $texto) . ',' .
            preg_match('/(?:--|#|\/\*|\*\/)/', $texto) . "\n");
        exit(4);
    }
}
echo "Perfiles y consultas sintéticas rechazadas o aceptadas según lo esperado.\n";
