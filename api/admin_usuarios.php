<?php
/**
 * api/admin_usuarios.php — CRUD de usuarios del restaurante
 * Sistema SaaS Restaurante | R.DEV
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/credenciales.php';
requireRole(['admin', 'encargado', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input         = json_decode($GLOBALS['_RAW_INPUT'] ?? ($GLOBALS['_RAW_INPUT'] = file_get_contents('php://input')), true);
$accion        = $input['accion'] ?? '';
$restauranteId = $_SESSION['restaurante_id'];
$usuarioActual = $_SESSION['usuario_id'];
$rolActual     = $_SESSION['rol'];
$db            = getDB();

switch ($accion) {

    case 'crear':
        $nombre   = trim($input['nombre']   ?? '');
        $email    = trim($input['email']    ?? '');
        $rol      = trim($input['rol']      ?? '');
        $password = $input['password'] ?? '';
        $activo   = !empty($input['activo']) ? 1 : 0;

        if ($rolActual === 'superadmin') {
            $rolesPermitidos = ['admin', 'encargado', 'atencion', 'cocina'];
        } elseif ($rolActual === 'admin') {
            $rolesPermitidos = ['encargado', 'atencion', 'cocina'];
        } else {
            $rolesPermitidos = ['atencion', 'cocina'];
        }

        if (empty($nombre) || empty($email) || !in_array($rol, $rolesPermitidos)) {
            jsonResponse(false, null, 'Datos inválidos o rol no permitido');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, null, 'Correo electrónico inválido');
        if (mb_strlen($nombre) > 100) jsonResponse(false, null, 'El nombre es demasiado largo (máx 100 caracteres)');
        if ($error_password = error_contrasena($password)) jsonResponse(false, null, $error_password);

        // Verificar email único
        $stDup = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stDup->execute([$email]);
        if ($stDup->fetch()) jsonResponse(false, null, 'El correo ya está registrado');

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $st   = $db->prepare("INSERT INTO usuarios (restaurante_id, nombre, email, password, rol, activo) VALUES (?,?,?,?,?,?)");
        $st->execute([$restauranteId, $nombre, $email, $hash, $rol, $activo]);
        $nuevoUsuarioId = $db->lastInsertId();
        registrarAuditoria($db, 'Creó usuario', "$nombre · Rol: $rol");
        jsonResponse(true, ['id' => $nuevoUsuarioId], "Usuario \"$nombre\" creado correctamente");

    case 'editar':
        $id       = (int)($input['id']      ?? 0);
        $nombre   = trim($input['nombre']   ?? '');
        $email    = trim($input['email']    ?? '');
        $rol      = trim($input['rol']      ?? '');
        $password = $input['password'] ?? '';
        $activo   = !empty($input['activo']) ? 1 : 0;

        if (!$id || empty($nombre) || empty($email)) jsonResponse(false, null, 'Datos incompletos');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, null, 'Correo electrónico inválido');
        if (mb_strlen($nombre) > 100) jsonResponse(false, null, 'El nombre es demasiado largo (máx 100 caracteres)');

        // Leer el rol ACTUAL del usuario objetivo antes de cualquier validación
        $stChkRol = $db->prepare("SELECT rol FROM usuarios WHERE id = ? AND restaurante_id = ?");
        $stChkRol->execute([$id, $restauranteId]);
        $usuarioTarget = $stChkRol->fetch();
        if (!$usuarioTarget) jsonResponse(false, null, 'Usuario no encontrado');

        $rolTarget = $usuarioTarget['rol'];

        // Superadmin no se puede editar desde este endpoint
        if ($rolTarget === 'superadmin') jsonResponse(false, null, 'No se puede editar una cuenta superadmin desde este panel');

        // — H-019: validar jerarquía sobre el rol ACTUAL del objetivo —
        // El encargado solo puede gestionar atencion/cocina; no puede tocar admin ni encargado
        if ($rolActual === 'encargado' && !in_array($rolTarget, ['atencion', 'cocina'], true)) {
            jsonResponse(false, null, 'No tienes permiso para editar este usuario');
        }
        // El admin no puede editar a otros admin ni a superadmin (ya cubierto arriba)
        if ($rolActual === 'admin' && $rolTarget === 'admin' && $id !== $usuarioActual) {
            jsonResponse(false, null, 'No puedes editar a otro administrador');
        }

        // Roles permitidos para el nuevo rol (destino)
        if ($rolActual === 'superadmin') {
            $rolesPermitidos = ['admin', 'encargado', 'atencion', 'cocina'];
        } elseif ($rolActual === 'admin') {
            $rolesPermitidos = ['encargado', 'atencion', 'cocina'];
        } else {
            // encargado: solo puede asignar atencion/cocina
            $rolesPermitidos = ['atencion', 'cocina'];
        }

        if (!in_array($rol, $rolesPermitidos)) {
            jsonResponse(false, null, 'Rol no permitido');
        }

        // No puede desactivarse a sí mismo
        if ($id === $usuarioActual && !$activo) jsonResponse(false, null, 'No puedes desactivar tu propia cuenta');

        // Verificar email único excluyendo el propio
        $stDup = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stDup->execute([$email, $id]);
        if ($stDup->fetch()) jsonResponse(false, null, 'El correo ya está en uso por otro usuario');

        if ($password !== '') {
            if ($error_password = error_contrasena($password)) jsonResponse(false, null, $error_password);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            // Incrementar session_version para invalidar sesiones activas del usuario editado
            $st   = $db->prepare("UPDATE usuarios SET nombre=?, email=?, password=?, rol=?, activo=?, session_version = session_version + 1 WHERE id=? AND restaurante_id=?");
            $st->execute([$nombre, $email, $hash, $rol, $activo, $id, $restauranteId]);
        } else {
            // Siempre incrementar session_version al editar para invalidar sesiones activas del objetivo
            $st = $db->prepare("UPDATE usuarios SET nombre=?, email=?, rol=?, activo=?, session_version = session_version + 1 WHERE id=? AND restaurante_id=?");
            $st->execute([$nombre, $email, $rol, $activo, $id, $restauranteId]);
        }
        registrarAuditoria($db, 'Editó usuario', "$nombre · Rol: $rol");
        jsonResponse(true, null, "Usuario \"$nombre\" actualizado correctamente");

    case 'eliminar':
        if ($rolActual === 'encargado') {
            jsonResponse(false, null, 'No tienes permiso para eliminar usuarios.');
        }

        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(false, null, 'ID requerido');
        if ($id === $usuarioActual) jsonResponse(false, null, 'No puedes eliminarte a ti mismo');

        // — H-019: leer rol actual del objetivo antes de eliminar —
        $stUsuario = $db->prepare("SELECT nombre, rol FROM usuarios WHERE id = ? AND restaurante_id = ?");
        $stUsuario->execute([$id, $restauranteId]);
        $usuarioEliminar = $stUsuario->fetch();
        if (!$usuarioEliminar) jsonResponse(false, null, 'Usuario no encontrado');
        if ($usuarioEliminar['rol'] === 'superadmin') jsonResponse(false, null, 'No se puede eliminar una cuenta superadmin');
        // Admin solo puede eliminar encargado/atencion/cocina, no a otro admin
        if ($rolActual === 'admin' && $usuarioEliminar['rol'] === 'admin') {
            jsonResponse(false, null, 'No puedes eliminar a otro administrador');
        }

        $st = $db->prepare("DELETE FROM usuarios WHERE id=? AND restaurante_id=? AND rol != 'superadmin'");
        $st->execute([$id, $restauranteId]);
        registrarAuditoria($db, 'Eliminó usuario', $usuarioEliminar['nombre']);
        jsonResponse(true, null, 'Usuario eliminado correctamente');

    default:
        jsonResponse(false, null, 'Acción no reconocida');
}
