<?php
/**
 * Abre un turno de caja para el restaurante.
 */
require_once '../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido.');

$restaurante_id = (int)$_SESSION['restaurante_id'];
$usuario_id = (int)$_SESSION['usuario_id'];
$db = getDB();

try {
    $db->beginTransaction();
    bloquear_restaurante($db, $restaurante_id);
    if (turno_abierto($db, $restaurante_id)) {
        throw new DomainException('Ya existe una caja abierta en este momento.');
    }
    $db->prepare('INSERT INTO turnos (restaurante_id, usuario_id, inicio) VALUES (?, ?, NOW())')
        ->execute([$restaurante_id, $usuario_id]);
    $turno_id = $db->lastInsertId();
    registrarAuditoria($db, 'Abrió turno', "Turno #$turno_id · Saldo inicial S/ 0.00", true);
    $db->commit();
    jsonResponse(true, null, 'Caja abierta correctamente.');
} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al abrir caja');
}
