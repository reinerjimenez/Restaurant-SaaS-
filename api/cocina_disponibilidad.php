<?php
require_once '../config/db.php';
requireRole(['cocina']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido.');
$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
$productoId = filter_var($input['producto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$agotado = filter_var($input['sin_stock'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if (!$productoId || $agotado === null) jsonResponse(false, null, 'Datos de disponibilidad inválidos.');

$restauranteId = (int)$_SESSION['restaurante_id'];
$db = getDB();
try {
    $db->beginTransaction();
    $lock = $db->prepare('SELECT id FROM restaurantes WHERE id = ? AND activo = 1 FOR UPDATE');
    $lock->execute([$restauranteId]);
    if (!$lock->fetchColumn()) throw new DomainException('Restaurante no disponible.');
    $producto = $db->prepare('SELECT nombre, sin_stock FROM productos WHERE id = ? AND restaurante_id = ? AND activo = 1 FOR UPDATE');
    $producto->execute([$productoId, $restauranteId]);
    $fila = $producto->fetch();
    if (!$fila) throw new DomainException('Plato no encontrado o inactivo.');
    $db->prepare('UPDATE productos SET sin_stock = ? WHERE id = ? AND restaurante_id = ?')
        ->execute([(int)$agotado, $productoId, $restauranteId]);
    registrarAuditoria($db, $agotado ? 'Marcó plato agotado' : 'Restableció plato disponible', $fila['nombre'], true);
    $db->commit();
    jsonResponse(true, ['sin_stock' => $agotado], $agotado ? 'Plato marcado como agotado.' : 'Plato disponible nuevamente.');
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    if ($e instanceof DomainException) jsonResponse(false, null, $e->getMessage());
    error_log('No se pudo actualizar la disponibilidad del plato.');
    jsonResponse(false, null, 'No se pudo actualizar el plato. Intenta nuevamente.');
}
