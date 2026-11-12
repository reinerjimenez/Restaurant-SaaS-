<?php
/**
 * Procesa el acceso con los límites de cuenta e IP.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/limite_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$rechazar_login = static function (int $espera = 0): never {
    $_SESSION['login_error'] = 'No se pudo iniciar sesión. Revisa tus credenciales o inténtalo en unos minutos.';
    if ($espera > 0) header('Retry-After: ' . $espera);
    header('Location: ' . BASE_URL . '/index.php', true, 303);
    exit;
};

$email = $_POST['email'] ?? '';
$contrasena = $_POST['password'] ?? '';
if (!is_string($email) || !is_string($contrasena) || !mb_check_encoding($email, 'UTF-8')
    || strlen($email) > 400 || strlen($contrasena) > 1024) {
    $rechazar_login();
}
$email = normalizar_identificador_login($email);
if ($email === '' || $contrasena === '' || mb_strlen($email, 'UTF-8') > 100) $rechazar_login();

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = 'unknown';

try {
    $db = getDB();
    $resultado = autenticar_login($db, $email, $contrasena, $ip);
    $usuario = $resultado['usuario'];
    if (!$usuario) $rechazar_login($resultado['espera']);

    if (!session_regenerate_id(true)) {
        destruir_sesion();
        iniciar_sesion();
        $rechazar_login();
    }
    $ahora = time();
    $_SESSION = [
        'usuario_id' => (int)$usuario['id'],
        'restaurante_id' => $usuario['rol'] === 'superadmin' ? null : (int)$usuario['restaurante_id'],
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email'],
        'rol' => $usuario['rol'],
        'session_version' => (int)$usuario['session_version'],
        '_last_revalidated' => $ahora,
        '_inicio_sesion' => $ahora,
        '_actividad_sesion' => $ahora,
    ];
    getCsrfToken();
    registrarAuditoria($db, 'Inició sesión');

    $destinos = [
        'superadmin' => '/roles/superadmin/dashboard.php',
        'admin' => '/roles/admin/dashboard.php',
        'encargado' => '/roles/admin/dashboard.php',
        'atencion' => '/roles/atencion/dashboard.php',
        'cocina' => '/roles/cocina/dashboard.php',
    ];
    header('Location: ' . BASE_URL . ($destinos[$usuario['rol']] ?? '/index.php'), true, 303);
    exit;
} catch (Throwable $e) {
    error_log('No se pudo completar la autenticación.');
    $rechazar_login();
}
