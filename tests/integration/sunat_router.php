<?php
/** Router exclusivo para la prueba HTTP contra mesora_sunat_test. */
declare(strict_types=1);

if (getenv('DB_NAME') !== 'mesora_sunat_test') {
    http_response_code(500);
    exit('Router de prueba bloqueado: DB_NAME inválido.');
}
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($uri, '/MESORA/')) $uri = substr($uri, 7);
if ($uri === '/MESORA') $uri = '/';
if ($uri === '/') $uri = '/index.php';
if (preg_match('~\A/(?:vendor|tmp)(?:/|\z)~i', $uri)) {
    http_response_code(404);
    exit('Ruta de prueba no encontrada.');
}
$raiz = realpath(__DIR__ . '/../..');
$archivo = realpath($raiz . DIRECTORY_SEPARATOR . ltrim($uri, '/'));
if ($archivo && str_starts_with(strtolower($archivo), strtolower($raiz)) && is_file($archivo)) {
    if (pathinfo($archivo, PATHINFO_EXTENSION) !== 'php') return false;
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim($uri, '/');
    $_SERVER['SCRIPT_FILENAME'] = $archivo;
    chdir(dirname($archivo));
    require $archivo;
    return true;
}
http_response_code(404);
exit('Ruta de prueba no encontrada.');
