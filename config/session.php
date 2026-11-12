<?php

const SESION_INACTIVIDAD = 30 * 60;
const SESION_DURACION = 12 * 60 * 60;

function parametros_cookie_sesion(string $base_url, array $servidor): array {
    $ruta = rtrim((string)parse_url($base_url, PHP_URL_PATH), '/') . '/';
    $https = strtolower((string)($servidor['HTTPS'] ?? ''));
    return [
        'lifetime' => 0,
        'path' => $ruta,
        'domain' => '',
        'secure' => in_array($https, ['on', '1'], true) || (string)($servidor['SERVER_PORT'] ?? '') === '443',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function sesion_expirada(array $sesion, int $ahora): bool {
    if (!isset($sesion['usuario_id'])) return false;
    $inicio = (int)($sesion['_inicio_sesion'] ?? 0);
    $actividad = (int)($sesion['_actividad_sesion'] ?? 0);
    return $inicio <= 0 || $actividad < $inicio || $inicio > $ahora || $actividad > $ahora
        || $ahora - $inicio >= SESION_DURACION || $ahora - $actividad >= SESION_INACTIVIDAD;
}

function iniciar_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // La expiración de negocio se controla con marcas propias, no con el recolector de PHP.
    ini_set('session.gc_maxlifetime', (string)SESION_DURACION);
    session_set_cookie_params(parametros_cookie_sesion(BASE_URL, $_SERVER));
    if (!session_start()) {
        http_response_code(503);
        jsonResponse(false, null, 'No se pudo iniciar la sesión. Intenta nuevamente.');
    }
}

function destruir_sesion(): void {
    $_SESSION = [];
    if (session_status() !== PHP_SESSION_ACTIVE) return;

    if (ini_get('session.use_cookies')) {
        $opciones = session_get_cookie_params();
        unset($opciones['lifetime']);
        $opciones['expires'] = time() - 3600;
        setcookie(session_name(), '', $opciones);
        // El código anterior emitía la cookie con ruta raíz.
        if ($opciones['path'] !== '/') {
            $opciones['path'] = '/';
            setcookie(session_name(), '', $opciones);
        }
        unset($_COOKIE[session_name()]);
    }
    session_destroy();
    session_id('');
}

function contexto_restaurante_valido(array $sesion, mixed $restaurante_id, mixed $contexto): bool {
    $activo_id = (int)($sesion['restaurante_id'] ?? 0);
    $activo_token = $sesion['_contexto_restaurante'] ?? '';
    return $activo_id > 0 && (is_int($restaurante_id) || is_string($restaurante_id))
        && (string)$restaurante_id === (string)$activo_id
        && is_string($contexto) && is_string($activo_token) && $activo_token !== ''
        && hash_equals($activo_token, $contexto);
}
