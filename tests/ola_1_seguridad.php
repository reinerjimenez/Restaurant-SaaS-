<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/credenciales.php';
require_once __DIR__ . '/../config/limite_login.php';

$pruebas = 0;
function verificar_seguridad(bool $condicion, string $caso): void {
    global $pruebas;
    if (!$condicion) throw new RuntimeException('Falló: ' . $caso);
    $pruebas++;
}

// Política de contraseña: el límite de bytes impide el truncamiento de bcrypt.
verificar_seguridad(error_contrasena('abcdefghijk') !== null, 'rechaza 11 caracteres');
verificar_seguridad(error_contrasena('abcdefghijkl') === null, 'acepta 12 caracteres');
verificar_seguridad(error_contrasena(str_repeat('á', 36)) === null, 'acepta 72 bytes Unicode');
verificar_seguridad(error_contrasena(str_repeat('á', 37)) !== null, 'rechaza 74 bytes Unicode');
verificar_seguridad(error_contrasena("abcdefghi\0xyz") !== null, 'rechaza bytes nulos');
verificar_seguridad(error_contrasena(['no válido']) !== null, 'rechaza tipos distintos a texto');
$hash_legacy = password_hash('acceso-antiguo', PASSWORD_BCRYPT);
$hash_nuevo = password_hash(' acceso con espacios ', PASSWORD_BCRYPT);
verificar_seguridad(verificar_contrasena_login(' acceso-antiguo ', $hash_legacy), 'login conserva acceso legado');
verificar_seguridad(verificar_contrasena_login(' acceso con espacios ', $hash_nuevo), 'login conserva espacios significativos');
verificar_seguridad(!verificar_contrasena_login('acceso con espacios', $hash_nuevo), 'no recorta al crear la nueva contraseña');

$ahora = 1_800_000_000;
$sesion = ['usuario_id' => 9, '_inicio_sesion' => $ahora - 3600, '_actividad_sesion' => $ahora - 50];
verificar_seguridad(!sesion_expirada($sesion, $ahora), 'sesión activa');
verificar_seguridad(sesion_expirada(array_replace($sesion, ['_actividad_sesion' => $ahora - SESION_INACTIVIDAD]), $ahora), 'expiración por inactividad');
verificar_seguridad(sesion_expirada(array_replace($sesion, ['_inicio_sesion' => $ahora - SESION_DURACION]), $ahora), 'expiración absoluta aunque haya actividad');
verificar_seguridad(sesion_expirada(['usuario_id' => 9], $ahora), 'sesión antigua requiere autenticación');
verificar_seguridad(!sesion_expirada([], $ahora), 'visitante puede recibir un formulario');
$cookie_https = parametros_cookie_sesion('/system-restaurant', ['HTTPS' => 'on']);
verificar_seguridad($cookie_https['secure'] && $cookie_https['httponly'] && $cookie_https['samesite'] === 'Lax'
    && $cookie_https['path'] === '/system-restaurant/', 'configuración de cookie HTTPS');
verificar_seguridad(!parametros_cookie_sesion('', ['HTTP_X_FORWARDED_PROTO' => 'https'])['secure'], 'solo configuración HTTPS confiable');

$a = ['restaurante_id' => 4, '_contexto_restaurante' => 'generacion_a'];
$b = ['restaurante_id' => 7, '_contexto_restaurante' => 'generacion_b'];
$a_nuevo = ['restaurante_id' => 4, '_contexto_restaurante' => 'generacion_c'];
verificar_seguridad(contexto_restaurante_valido($a, '4', 'generacion_a'), 'contexto correcto');
verificar_seguridad(!contexto_restaurante_valido($b, '4', 'generacion_a'), 'pestaña anterior rechazada al cambiar restaurante');
verificar_seguridad(!contexto_restaurante_valido($a_nuevo, 4, 'generacion_a'), 'pestaña anterior rechazada al volver al mismo restaurante');
verificar_seguridad(!contexto_restaurante_valido($a, 4, null) && !contexto_restaurante_valido($a, [4], 'generacion_a'), 'contexto obligatorio y tipado');

verificar_seguridad(clave_cuenta_login(null, ' Usuario@Ejemplo.test ') === clave_cuenta_login(null, 'usuario@ejemplo.test'), 'cuenta desconocida normalizada');
verificar_seguridad(clave_cuenta_login(9, 'alias-A') === clave_cuenta_login(9, 'alias-B'), 'cuenta real comparte contador por id');
verificar_seguridad(clave_ip_login('192.0.2.1') === clave_ip_login('::ffff:192.0.2.1'), 'IPv4 equivalente en IPv6');
verificar_seguridad(pausa_login_cuenta(4) === 0 && pausa_login_cuenta(5) === 2 && pausa_login_cuenta(6) === 4
    && pausa_login_cuenta(100) === 60, 'espera progresiva acotada');
$estado = ['inicio' => $ahora - 30, 'fallos' => LOGIN_MAX_FALLOS_IP, 'proximo_en' => $ahora + 60];
verificar_seguridad(espera_login($estado, $ahora, true) === LOGIN_VENTANA - 30, 'límite fijo de IP');
verificar_seguridad(espera_login($estado, $ahora, false) === 60, 'espera por cuenta');
$recuperado = estado_login_vigente($estado, $estado['inicio'] + LOGIN_VENTANA);
verificar_seguridad($recuperado['fallos'] === 0 && $recuperado['proximo_en'] === 0, 'la ventana caduca sin bloqueo permanente');

echo "Seguridad Ola 1: $pruebas comprobaciones sin base de datos.\n";
