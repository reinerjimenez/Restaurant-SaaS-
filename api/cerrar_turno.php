<?php
/**
 * Cierra el turno presentado y, opcionalmente, abre el siguiente.
 */
require_once '../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);
require_once '../config/finanzas.php';
require_once '../includes/reportes_financieros.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido.');

$input = json_decode($GLOBALS['_RAW_INPUT'] ?? file_get_contents('php://input'), true);
$turno_id = filter_var($input['turno_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$reabrir = filter_var($input['reabrir'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if (!$turno_id || $reabrir === null) {
    jsonResponse(false, null, 'Turno inválido. Actualiza la vista de caja.');
}

$restaurante_id = (int)$_SESSION['restaurante_id'];
$usuario_id = (int)$_SESSION['usuario_id'];
$db = getDB();

try {
    $db->beginTransaction();
    bloquear_restaurante($db, $restaurante_id);
    $turno = exigir_turno_abierto($db, $restaurante_id);
    if ((int)$turno['id'] !== $turno_id) {
        throw new DomainException('El turno ya cambió. Actualiza la vista de caja antes de cerrar.');
    }
    $totales = resumen_turno($db, $restaurante_id, $turno_id);
    $metodos = ['efectivo', 'yape', 'transferencia', 'tarjeta', 'otros', 'general'];
    $valores = array_map(static fn(string $metodo): string => $totales["total_$metodo"], $metodos);
    foreach ($valores as $valor) {
        if (strlen(explode('.', ltrim($valor, '-'), 2)[0]) > 8) {
            throw new DomainException('El turno supera el límite de saldo y requiere conciliación.');
        }
    }
    $cierre = $totales + ['mesas_por_pedido' => mesas_del_turno($db, $restaurante_id, $turno_id)];
    $cierre_json = json_encode($cierre, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $db->prepare('UPDATE turnos SET fin = NOW(), total_efectivo = ?, total_yape = ?, total_transferencia = ?,
        total_tarjeta = ?, total_otros = ?, total_general = ?, cierre_json = ?
        WHERE id = ? AND restaurante_id = ? AND fin IS NULL')
        ->execute([...$valores, $cierre_json, $turno_id, $restaurante_id]);
    registrarAuditoria($db, 'Cerró turno',
        "Turno #$turno_id · Ventas netas S/ {$totales['total_general']} · Gastos S/ {$totales['total_gastos']} · Reversos S/ {$totales['total_reversos']}", true);

    $mensaje = 'Turno cerrado correctamente.';
    if ($reabrir) {
        $db->prepare('INSERT INTO turnos (restaurante_id, usuario_id, inicio) VALUES (?, ?, NOW())')
            ->execute([$restaurante_id, $usuario_id]);
        $nuevo_id = $db->lastInsertId();
        registrarAuditoria($db, 'Abrió turno', "Turno #$nuevo_id · Después del cierre #$turno_id · Saldo inicial S/ 0.00", true);
        $mensaje = 'Turno cerrado y nueva caja abierta inmediatamente.';
    }

    $db->commit();
    jsonResponse(true, array_combine($metodos, $valores) + ['num_pedidos' => $totales['num_pedidos']], $mensaje);
} catch (Throwable $e) {
    error_financiero($db, $e, 'Error al cerrar caja');
}
