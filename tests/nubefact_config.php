<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/nubefact_config.php';

function exige_nubefact(bool $ok, string $mensaje): void {
    if (!$ok) throw new RuntimeException($mensaje);
}

function rechaza_nubefact(callable $prueba, string $contexto): void {
    try {
        $prueba();
    } catch (DomainException) {
        return;
    }
    throw new RuntimeException('Se aceptó un caso inválido: ' . $contexto);
}

putenv('MESORA_NUBEFACT_KEY');
rechaza_nubefact('nubefact_clave', 'clave ausente');
putenv('MESORA_NUBEFACT_KEY=' . base64_encode(random_bytes(32)));
$base = ['restaurante_id'=>7001, 'ambiente'=>'pruebas', 'ruc'=>'20123456786',
    'ruta'=>'https://demo.nubefact.com/api/v1/ruta-ensayo-a', 'habilitado'=>1];
$token = 'token-ficticio-para-prueba';
$cifrado = nubefact_cifrar_token($token, $base);
$datos = $base + ['token_cifrado'=>$cifrado];
exige_nubefact(!str_contains($cifrado, $token), 'El Token quedó legible en almacenamiento.');
exige_nubefact(nubefact_token($datos) === $token, 'No se recuperó el Token.');
exige_nubefact($cifrado !== nubefact_cifrar_token($token, $base), 'Se reutilizó el nonce.');
foreach (['restaurante_id'=>7002, 'ambiente'=>'produccion', 'ruc'=>'20512312315',
    'ruta'=>'https://demo.nubefact.com/api/v1/ruta-ensayo-b'] as $campo => $cambio) {
    rechaza_nubefact(static fn() => nubefact_token(array_replace($datos, [$campo=>$cambio])), $campo);
}
$alterado = $cifrado;
$alterado[30] = $alterado[30] ^ "\x01";
rechaza_nubefact(static fn() => nubefact_token(array_replace($datos, ['token_cifrado'=>$alterado])), 'cifrado alterado');
rechaza_nubefact(static fn() => nubefact_cifrar_token("fuga\r\nHTTP", $base), 'saltos de línea');
rechaza_nubefact(static fn() => nubefact_cifrar_token(str_repeat('x', 513), $base), 'Token demasiado largo');
exige_nubefact(nubefact_config_activa($base), 'No se activaron las pruebas.');
exige_nubefact(!nubefact_config_activa(array_replace($base, ['habilitado'=>0])), 'Se ignoró la pausa.');
$prod = array_replace($base, ['ambiente'=>'produccion']);
putenv('NUBEFACT_PRODUCCION_AUTORIZADA_7001');
exige_nubefact(!nubefact_config_activa($prod + ['verificado_en'=>'2026-01-01 00:00:00']), 'Producción sin permiso.');
putenv('NUBEFACT_PRODUCCION_AUTORIZADA_7001=1');
exige_nubefact(!nubefact_config_activa($prod), 'Producción sin revisar.');
exige_nubefact(nubefact_config_activa($prod + ['verificado_en'=>'2026-01-01 00:00:00']), 'Producción revisada no activa.');
echo "OK: custodia autenticada del Token, separación de ambientes y autorización de producción.\n";
