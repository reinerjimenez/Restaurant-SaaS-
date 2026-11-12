<?php
// Validación cerrada para los archivos SQL de lectura del paquete.
declare(strict_types=1);

function consulta_ensayo_valida(string $sql, string $base): bool
{
    if (!in_array($base, ['restaurant_ola1_test', 'restaurant_ola1_restore'], true) ||
        preg_match('/\b(?:INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE|GRANT|REVOKE|CALL|OUTFILE|LOAD_FILE|USE)\b/i', $sql) ||
        preg_match('/(?:--|#|\/\*|\*\/)/', $sql)) {
        return false;
    }
    foreach (explode(';', $sql) as $parte) {
        $parte = trim($parte);
        if ($parte !== '' && !preg_match('/^(?:SELECT|SHOW|WITH)\b/i', $parte)) {
            return false;
        }
    }
    preg_match_all('/\b(?:FROM|JOIN)\s+([a-z_][a-z_0-9]*\.)?([a-z_][a-z_0-9]*)/i', $sql, $tablas, PREG_SET_ORDER);
    foreach ($tablas as $tabla) {
        if (($tabla[1] ?? '') !== '' && !in_array(strtolower($tabla[1]),
            [$base . '.', 'information_schema.'], true)) {
            return false;
        }
        if (($tabla[1] ?? '') === '' && !in_array(strtolower($tabla[2]),
            ['information_schema', 'base', 'brutos', 'reversos', 'egresos'], true)) {
            return false;
        }
    }
    return !preg_match('/\b(?:restaurant_[a-z0-9_]+|mysql|performance_schema|sys)\s*\./i',
        str_replace([$base . '.', 'information_schema.'], '', $sql));
}
