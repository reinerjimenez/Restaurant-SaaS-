<?php
// ============================================
// Conexión a la Base de Datos — PDO
// Sistema SaaS Restaurante | R.DEV
// ============================================

/**
 * Función nativa para cargar variables de entorno desde un archivo .env
 * Compatible con hostings compartidos (no requiere Composer).
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Quitar comillas si las tiene
            $value = trim($value, '"\'');
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Cargar variables de entorno
loadEnv(__DIR__ . '/../.env');

// Detectar automáticamente la URL base (configurable vía .env, o auto-detectar localhost vs producción)
if (!empty($_ENV['BASE_URL'])) {
    define('BASE_URL', rtrim($_ENV['BASE_URL'], '/'));
} else {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        define('BASE_URL', '/system-restaurant');
    } else {
        define('BASE_URL', '');
    }
}

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'restaurante_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ── Facturación — API de consulta DNI/RUC ─────────────────────
// Obtén tu token en: https://api.apis.net.pe
define('APIS_NET_TOKEN', $_ENV['APIS_NET_TOKEN'] ?? '');

date_default_timezone_set('America/Lima');
require_once __DIR__ . '/session.php';

/**
 * Retorna una instancia singleton de PDO.
 */
function getDB(bool $tolerar_fallo = false): ?PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'",
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            error_log('Falló la conexión a la base de datos.');
            if ($tolerar_fallo) return null;
            http_response_code(503);
            jsonResponse(false, null, 'Error de conexión a la base de datos.');
        }
    }

    return $pdo;
}

/**
 * Respuesta estándar JSON para endpoints AJAX.
 */
function jsonResponse(bool $success, $data = null, string $message = ''): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ]);
    exit;
}

/**
 * Los eventos críticos se escriben en la misma transacción que el movimiento.
 */
function registrarAuditoria(PDO $db, string $accion, string $detalle = '', bool $obligatoria = false): void {
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['restaurante_id'])) {
        if ($obligatoria) throw new LogicException('Falta el actor o restaurante para la auditoría.');
        return;
    }
    if ($obligatoria && !$db->inTransaction()) {
        throw new LogicException('La auditoría obligatoria requiere una transacción.');
    }

    try {
        $stmt = $db->prepare(
            "INSERT INTO auditoria (restaurante_id, usuario_id, usuario_nombre, accion, detalle, ip)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $_SESSION['restaurante_id'],
            $_SESSION['usuario_id'],
            $_SESSION['nombre'] ?? 'Desconocido',
            $accion,
            $detalle !== '' ? $detalle : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        if ($obligatoria) throw $e;
        error_log('No se pudo registrar un evento de auditoría.');
    }
}

function es_peticion_api(): bool {
    return preg_match('~/api/[^/]+\.php$~D', $_SERVER['SCRIPT_NAME'] ?? '') === 1;
}

function es_peticion_ajax(): bool {
    return es_peticion_api() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function campos_contexto_restaurante(): string {
    if (($_SESSION['rol'] ?? '') !== 'superadmin' || empty($_SESSION['_contexto_restaurante'])) return '';
    $id = (int)($_SESSION['restaurante_id'] ?? 0);
    $token = htmlspecialchars($_SESSION['_contexto_restaurante'], ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="restaurante_esperado" value="' . $id . '">'
        . '<input type="hidden" name="contexto_restaurante" value="' . $token . '">';
}

/**
 * Conserva la revocación de la Ola 0 y comprueba el contexto antes de operar.
 */
function requireRole(array $roles_permitidos): void {
    $es_ajax = es_peticion_ajax();
    $terminar_sesion = static function (string $motivo) use ($es_ajax): never {
        destruir_sesion();
        if ($es_ajax) {
            http_response_code(401);
            jsonResponse(false, null, $motivo);
        }
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    };

    if (!isset($_SESSION['usuario_id']) || sesion_expirada($_SESSION, time())) {
        $terminar_sesion('Inicia sesión para continuar.');
    }
    $rol = $_SESSION['rol'] ?? '';
    if (!in_array($rol, $roles_permitidos, true)) {
        if ($es_ajax) {
            http_response_code(403);
            jsonResponse(false, null, 'No autorizado');
        }
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ahora = time();
    if ($metodo === 'POST' || $ahora - (int)($_SESSION['_last_revalidated'] ?? 0) >= 60) {
        try {
            $st = getDB()->prepare("SELECT u.activo AS usuario_activo, u.rol, u.session_version,
                                             u.restaurante_id, r.activo AS restaurante_activo
                                      FROM usuarios u LEFT JOIN restaurantes r ON r.id = u.restaurante_id
                                      WHERE u.id = ?");
            $st->execute([$_SESSION['usuario_id']]);
            $fila = $st->fetch();

            if (!$fila || !$fila['usuario_activo'] || $fila['rol'] !== $rol
                || (int)$fila['session_version'] !== (int)($_SESSION['session_version'] ?? 0)
                || ($rol !== 'superadmin' && (!$fila['restaurante_activo']
                    || (int)$fila['restaurante_id'] !== (int)($_SESSION['restaurante_id'] ?? 0)))) {
                $terminar_sesion('La sesión ya no es válida. Vuelve a iniciar sesión.');
            }
            $_SESSION['_last_revalidated'] = $ahora;
        } catch (Throwable $e) {
            error_log('No se pudo revalidar la sesión.');
            http_response_code(503);
            if ($es_ajax) jsonResponse(false, null, 'No se pudo verificar la sesión. Intenta nuevamente.');
            exit('No se pudo verificar la sesión. Intenta nuevamente.');
        }
    }

    if ($rol === 'superadmin' && $roles_permitidos !== ['superadmin']) {
        $rechazar_contexto = static function (string $motivo, bool $redirigir = false) use ($es_ajax): never {
            if ($es_ajax) {
                http_response_code(409);
                jsonResponse(false, null, $motivo);
            }
            if ($redirigir) {
                header('Location: ' . BASE_URL . '/roles/superadmin/dashboard.php');
                exit;
            }
            http_response_code(409);
            exit($motivo);
        };

        $restaurante_id = (int)($_SESSION['restaurante_id'] ?? 0);
        if (!$restaurante_id || empty($_SESSION['_contexto_restaurante'])) {
            $rechazar_contexto('Selecciona un restaurante para continuar.', $metodo === 'GET');
        }
        if ($metodo === 'POST' || $es_ajax) {
            $esperado = $_SERVER['HTTP_X_RESTAURANTE_ESPERADO'] ?? $_POST['restaurante_esperado'] ?? null;
            $token = $_SERVER['HTTP_X_CONTEXTO_RESTAURANTE'] ?? $_POST['contexto_restaurante'] ?? null;
            if (!contexto_restaurante_valido($_SESSION, $esperado, $token)) {
                $rechazar_contexto('El restaurante de esta pestaña cambió. Recarga la página antes de continuar.');
            }
        }

        try {
            $st = getDB()->prepare("SELECT nombre FROM restaurantes WHERE id = ? AND activo = 1");
            $st->execute([$restaurante_id]);
            $nombre = $st->fetchColumn();
            if ($nombre === false) {
                unset($_SESSION['restaurante_id'], $_SESSION['_contexto_restaurante'], $_SESSION['_nombre_contexto_restaurante']);
                $rechazar_contexto('El restaurante seleccionado ya no está disponible.', $metodo === 'GET');
            }
            $_SESSION['_nombre_contexto_restaurante'] = $nombre;
        } catch (Throwable $e) {
            error_log('No se pudo verificar el restaurante seleccionado.');
            http_response_code(503);
            if ($es_ajax) jsonResponse(false, null, 'No se pudo verificar el restaurante. Intenta nuevamente.');
            exit('No se pudo verificar el restaurante. Intenta nuevamente.');
        }
    }

    $_SESSION['_actividad_sesion'] = $ahora;
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token): bool {
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function verifyCsrfProtection(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $GLOBALS['_RAW_INPUT'] ??= file_get_contents('php://input');
    $json_payload = json_decode($GLOBALS['_RAW_INPUT'], true);
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ($json_payload['csrf_token'] ?? '');
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        if (es_peticion_ajax()) jsonResponse(false, null, 'La sesión del formulario expiró. Recarga la página.');
        exit('La sesión del formulario expiró. Recarga la página.');
    }
}

iniciar_sesion();
if (sesion_expirada($_SESSION, time())) {
    destruir_sesion();
    iniciar_sesion();
}
getCsrfToken();
verifyCsrfProtection();
