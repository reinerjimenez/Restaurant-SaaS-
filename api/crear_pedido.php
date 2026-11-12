<?php
/**
 * api/crear_pedido.php — Crea un nuevo pedido y ocupa la mesa
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido');

$input    = json_decode($GLOBALS['_RAW_INPUT'] ?? ($GLOBALS['_RAW_INPUT'] = file_get_contents('php://input')), true);
$mesaId   = (int)($input['mesa_id'] ?? 0);
$tipo     = $input['tipo'] ?? '';
$usuarioId     = $_SESSION['usuario_id'];
$restauranteId = $_SESSION['restaurante_id'];

if (!in_array($tipo, ['aqui','llevar'], true)) jsonResponse(false, null, 'Tipo inválido');

$db = getDB();

try {
    $db->beginTransaction();
    bloquear_restaurante($db, $restauranteId);
    exigir_turno_abierto($db, $restauranteId);

    // Verificar que la mesa esté libre
    $mesa_numero = null;
    if ($mesaId) {
        $stMesa = $db->prepare("SELECT estado, numero FROM mesas WHERE id = ? AND restaurante_id = ? AND activo = 1 FOR UPDATE");
        $stMesa->execute([$mesaId, $restauranteId]);
        $mesa = $stMesa->fetch();
        if (!$mesa) {
            $db->rollBack();
            jsonResponse(false, null, 'Mesa no encontrada');
        }
        $stActivo = $db->prepare("SELECT id FROM pedidos WHERE mesa_id = ? AND restaurante_id = ? AND estado = 'activo' LIMIT 1");
        $stActivo->execute([$mesaId, $restauranteId]);
        if ($stActivo->fetch()) {
            $db->rollBack();
            jsonResponse(false, null, 'La mesa ya tiene un pedido activo');
        }
        if ($mesa['estado'] !== 'libre') {
            $db->rollBack();
            jsonResponse(false, null, 'La mesa no está libre');
        }
        $mesa_numero = $mesa['numero'];
    }

    // Crear pedido
    $stPed = $db->prepare("
        INSERT INTO pedidos (restaurante_id, mesa_id, usuario_id, tipo, estado, total)
        VALUES (?, ?, ?, ?, 'activo', 0.00)
    ");
    $stPed->execute([$restauranteId, $mesaId ?: null, $usuarioId, $tipo]);
    $pedidoId = $db->lastInsertId();

    // Marcar mesa como ocupada
    if ($mesaId) {
        $stActualizar = $db->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ? AND restaurante_id = ?");
        $stActualizar->execute([$mesaId, $restauranteId]);
    }

    $mesaLabel = $mesa_numero ? "Mesa $mesa_numero" : 'Para llevar';
    $tipoLabel = $tipo === 'aqui' ? 'En local' : 'Para llevar';
    registrarAuditoria($db, 'Abrió comanda', "Pedido #$pedidoId · $mesaLabel · $tipoLabel", true);
    $db->commit();

    jsonResponse(true, ['pedido_id' => $pedidoId], 'Pedido creado correctamente');

} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al crear el pedido');
}
