<?php
// Valida las líneas de SHOW GRANTS sin imprimir hashes ni nombres de secretos.
declare(strict_types=1);

function privilegios_validos(array $grants, string $base, array $permitidos): bool
{
    $obtenidos = [];
    foreach ($grants as $grant) {
        if (!is_string($grant) || stripos($grant, 'WITH GRANT OPTION') !== false ||
            stripos($grant, 'GRANT PROXY') !== false ||
            !preg_match('/^GRANT (.+?) ON (.+?) TO /i', $grant, $partes)) {
            return false;
        }
        if ($partes[2] === '*.*' && strtoupper($partes[1]) === 'USAGE') {
            continue;
        }
        $objetivo = '`' . str_replace('_', '\\_', $base) . '`.*';
        if ($partes[2] !== $objetivo) {
            return false;
        }
        foreach (explode(',', $partes[1]) as $privilegio) {
            $obtenidos[] = strtoupper(trim($privilegio));
        }
    }
    sort($obtenidos);
    sort($permitidos);
    return $obtenidos === $permitidos;
}
