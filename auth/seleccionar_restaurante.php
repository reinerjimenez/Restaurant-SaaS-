<?php
/**
 * Selección explícita de restaurante para el superadministrador.
 */
require_once __DIR__ . '/../config/db.php';
requireRole(['superadmin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Usa el selector de restaurantes para continuar.');
}

$restaurante_id = filter_var($_POST['restaurante_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($restaurante_id === false) {
    http_response_code(400);
    exit('Selecciona un restaurante válido.');
}

try {
    $st = getDB()->prepare("SELECT nombre FROM restaurantes WHERE id = ? AND activo = 1");
    $st->execute([$restaurante_id]);
    $nombre = $st->fetchColumn();
    if ($nombre === false) {
        http_response_code(409);
        exit('El restaurante seleccionado ya no está disponible.');
    }

    $_SESSION['restaurante_id'] = $restaurante_id;
    $_SESSION['_nombre_contexto_restaurante'] = $nombre;
    $_SESSION['_contexto_restaurante'] = bin2hex(random_bytes(32));
    header('Location: ' . BASE_URL . '/roles/admin/dashboard.php', true, 303);
    exit;
} catch (Throwable $e) {
    error_log('No se pudo cambiar el restaurante seleccionado.');
    http_response_code(503);
    exit('No se pudo seleccionar el restaurante. Intenta nuevamente.');
}
