<?php

const LOGIN_VENTANA = 15 * 60;
const LOGIN_MAX_FALLOS_IP = 30;
const LOGIN_FALLOS_SIN_PAUSA = 4;
// Hash señuelo de un valor aleatorio descartado: mantiene trabajo similar para usuarios inexistentes.
const LOGIN_HASH_SENUELO = '$2y$10$E6rnZmuy2w7tt6rVNU8Vn.8vURn26cjlO059grpFVfJvkYd4qPL6O';

function normalizar_identificador_login(string $identificador): string {
    return mb_strtolower(trim($identificador), 'UTF-8');
}

function clave_cuenta_login(?int $usuario_id, string $identificador): string {
    // Las equivalencias de collation de una cuenta real confluyen en su id.
    return hash('sha256', $usuario_id !== null
        ? 'usuario:' . $usuario_id
        : 'identificador:' . normalizar_identificador_login($identificador));
}

function clave_ip_login(string $ip): string {
    $binario = inet_pton($ip);
    if ($binario !== false && strlen($binario) === 16 && substr($binario, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        $binario = substr($binario, 12);
    }
    return hash('sha256', 'ip:' . ($binario === false ? 'desconocida' : bin2hex($binario)));
}

function estado_login_vigente(array $fila, int $ahora): array {
    $inicio = (int)$fila['inicio'];
    if ($inicio > $ahora || $ahora - $inicio >= LOGIN_VENTANA) {
        return ['inicio' => $ahora, 'fallos' => 0, 'proximo_en' => 0];
    }
    return [
        'inicio' => $inicio,
        'fallos' => (int)$fila['fallos'],
        'proximo_en' => (int)$fila['proximo_en'],
    ];
}

function pausa_login_cuenta(int $fallos): int {
    if ($fallos <= LOGIN_FALLOS_SIN_PAUSA) return 0;
    return min(60, 2 ** min(6, $fallos - LOGIN_FALLOS_SIN_PAUSA));
}

function espera_login(array $estado, int $ahora, bool $por_ip): int {
    if ($por_ip && $estado['fallos'] >= LOGIN_MAX_FALLOS_IP) {
        return max(0, $estado['inicio'] + LOGIN_VENTANA - $ahora);
    }
    return max(0, min($estado['proximo_en'], $estado['inicio'] + LOGIN_VENTANA) - $ahora);
}

function bloquear_limite_login(PDO $db, string $clave): array {
    $db->prepare("INSERT INTO login_limites (clave, inicio, fallos, proximo_en)
                  VALUES (?, ?, 0, 0) ON DUPLICATE KEY UPDATE clave = clave")->execute([$clave, time()]);
    $st = $db->prepare("SELECT inicio, fallos, proximo_en FROM login_limites WHERE clave = ? FOR UPDATE");
    $st->execute([$clave]);
    return $st->fetch();
}

function guardar_limite_login(PDO $db, string $clave, array $estado): void {
    $db->prepare("UPDATE login_limites SET inicio = ?, fallos = ?, proximo_en = ? WHERE clave = ?")
        ->execute([$estado['inicio'], $estado['fallos'], $estado['proximo_en'], $clave]);
}

function verificar_contrasena_login(string $contrasena, string $hash): bool {
    if (password_verify($contrasena, $hash)) return true;
    // Compatibilidad con credenciales del sistema anterior, que recortaba el formulario.
    $recortada = trim($contrasena);
    return $recortada !== $contrasena && password_verify($recortada, $hash);
}

/**
 * El candado de IP precede al de cuenta; los intentos rechazados no extienden
 * el bloqueo de cuenta. La espera se comunica al cliente sin dormir un worker.
 */
function autenticar_login(PDO $db, string $identificador, string $contrasena, string $ip): array {
    $clave_ip = clave_ip_login($ip);
    $db->beginTransaction();
    try {
        $estado_ip = bloquear_limite_login($db, $clave_ip);
        $ahora = time();
        $estado_ip = estado_login_vigente($estado_ip, $ahora);
        $espera = espera_login($estado_ip, $ahora, true);
        $usuario = null;
        if ($espera === 0) {
            $st = $db->prepare("SELECT u.id, u.restaurante_id, u.nombre, u.email, u.password, u.rol,
                                       u.activo, u.session_version, r.activo AS restaurante_activo
                                FROM usuarios u LEFT JOIN restaurantes r ON r.id = u.restaurante_id
                                WHERE u.email = ? LIMIT 1");
            $st->execute([$identificador]);
            $fila = $st->fetch();
            $clave_cuenta = clave_cuenta_login($fila ? (int)$fila['id'] : null, $identificador);
            $estado_cuenta = bloquear_limite_login($db, $clave_cuenta);
            $ahora = time();
            $estado_cuenta = estado_login_vigente($estado_cuenta, $ahora);
            $estado_ip = estado_login_vigente($estado_ip, $ahora);
            $espera = espera_login($estado_cuenta, $ahora, false);

            if ($espera === 0) {
                $verificada = verificar_contrasena_login($contrasena, $fila['password'] ?? LOGIN_HASH_SENUELO);
                if ($fila && $verificada && $fila['activo']
                    && ($fila['rol'] === 'superadmin' || $fila['restaurante_activo'])) {
                    $estado_cuenta = ['inicio' => $ahora, 'fallos' => 0, 'proximo_en' => 0];
                    $db->prepare("INSERT INTO logs_acceso (usuario_id, accion, ip) VALUES (?, 'login', ?)")
                        ->execute([$fila['id'], $ip]);
                    unset($fila['password']);
                    $usuario = $fila;
                } else {
                    $estado_cuenta['fallos']++;
                    $espera = pausa_login_cuenta($estado_cuenta['fallos']);
                    $estado_cuenta['proximo_en'] = $ahora + $espera;
                }
                guardar_limite_login($db, $clave_cuenta, $estado_cuenta);
            }

            // Un éxito no reinicia la cuota de IP compartida entre cuentas.
            if (!$usuario) $estado_ip['fallos']++;
            guardar_limite_login($db, $clave_ip, $estado_ip);
            $espera = max($espera, espera_login($estado_ip, $ahora, true));
        }

        $db->commit();
        // La limpieza es acotada y queda fuera de los candados del intento.
        try {
            if (random_int(1, 128) === 1) {
                $db->prepare("DELETE FROM login_limites WHERE inicio < ? LIMIT 500")
                    ->execute([$ahora - 86400]);
            }
        } catch (Throwable $e) {
            error_log('No se pudieron limpiar contadores vencidos de acceso.');
        }
        return ['usuario' => $usuario, 'espera' => $espera];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
