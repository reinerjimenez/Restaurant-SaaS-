<?php
/**
 * Cierra la sesión por POST protegido y expira su cookie.
 */
require_once __DIR__ . '/../config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Usa el botón Salir para cerrar sesión.');
}

$usuario_id = $_SESSION['usuario_id'] ?? null;
destruir_sesion();

if ($usuario_id) {
    try {
        $db = getDB(true);
        if ($db !== null) {
            $st = $db->prepare("INSERT INTO logs_acceso (usuario_id, accion, ip) VALUES (?, 'logout', ?)");
            $st->execute([$usuario_id, $_SERVER['REMOTE_ADDR'] ?? null]);
        }
    } catch (Throwable $e) {
        error_log('No se pudo registrar el cierre de sesión.');
    }
}
header('Location: ' . BASE_URL . '/index.php', true, 303);
exit;
