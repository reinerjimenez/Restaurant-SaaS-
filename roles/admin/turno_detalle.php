<?php
/**
 * roles/admin/turno_detalle.php
 * Vista detallada de un turno cerrado.
 */
require_once '../../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);

$restauranteId = $_SESSION['restaurante_id'];
$db = getDB();

$turnoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$turnoId) {
    header('Location: caja.php');
    exit;
}

// Obtener detalles del turno
$stTurno = $db->prepare("
    SELECT t.*, u.nombre AS abierto_por
    FROM turnos t
    JOIN usuarios u ON u.id = t.usuario_id
    WHERE t.id = ? AND t.restaurante_id = ? AND t.fin IS NOT NULL
");
$stTurno->execute([$turnoId, $restauranteId]);
$turno = $stTurno->fetch();

if (!$turno) {
    header('Location: caja.php');
    exit;
}

require_once __DIR__ . '/../../includes/reportes_financieros.php';

// Un cierre se muestra tal como fue guardado; los legados solo tienen totales por método.
$cierre = leer_cierre_turno($turno);
$gastosTotal = $cierre['total_gastos'] ?? null;
$numPedidos = $cierre['num_pedidos'] ?? null;
$desgloseUsuarios = $cierre['desglose_usuarios'] ?? [];

$pageTitle = 'Detalles del Turno';
$activeMenu = 'caja';
require_once '../../includes/header.php';
?>

<div class="page-content">
    <div style="margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <a href="caja.php" class="btn btn-ghost" style="padding:8px;border-radius:50%;min-width:44px;min-height:44px;"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1 style="font-size:1.55rem;font-weight:800;margin-bottom:4px;">
                <i class="fa-solid fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px;"></i>Detalles del Turno #<?= $turno['id'] ?>
            </h1>
            <p style="color:var(--text-secondary);font-size:.88rem;">
                <?= date('d/m/Y H:i', strtotime($turno['inicio'])) ?> &mdash; <?= date('d/m/Y H:i', strtotime($turno['fin'])) ?> (Abierto por <?= htmlspecialchars($turno['abierto_por']) ?>)
            </p>
        </div>
    </div>

    <?php if ($cierre === null): ?>
        <p class="card mb-16">Cierre anterior al registro por turno. Se conservan los importes guardados; no hay desglose verificable de pedidos, gastos o usuarios.</p>
    <?php else: ?>
        <p class="card mb-16">Resumen guardado al cerrar. Reversos de este turno: S/ <?= number_format($cierre['total_reversos'] ?? 0, 2) ?>.</p>
    <?php endif; ?>

    <!-- Panel de Resumen -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:20px;">
        <!-- Ingresos -->
        <div class="card" style="border-left:4px solid var(--success);padding:16px;">
            <div style="color:var(--success);margin-bottom:8px;"><i class="fa-solid fa-sack-dollar"></i></div>
            <div style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:4px;">S/ <?= number_format($turno['total_general'], 2) ?></div>
            <div style="font-size:.85rem;color:var(--text-secondary);">Cobros netos<?= $numPedidos === null ? '' : ' (' . (int)$numPedidos . ' pedidos)' ?></div>
        </div>
        <!-- Gastos -->
        <div class="card" style="border-left:4px solid var(--danger);padding:16px;">
            <div style="color:var(--danger);margin-bottom:8px;"><i class="fa-solid fa-money-bill-transfer"></i></div>
            <div style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:4px;"><?= $gastosTotal === null ? 'No disponible' : 'S/ ' . number_format($gastosTotal, 2) ?></div>
            <div style="font-size:.85rem;color:var(--text-secondary);">Gastos del Turno</div>
        </div>
        <!-- Utilidad -->
        <div class="card" style="border-left:4px solid var(--primary);padding:16px;background:var(--bg-secondary);">
            <div style="color:var(--primary);margin-bottom:8px;"><i class="fa-solid fa-piggy-bank"></i></div>
            <div style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:4px;"><?= $gastosTotal === null ? 'No disponible' : 'S/ ' . number_format((centimos_reporte($turno['total_general']) - centimos_reporte($gastosTotal)) / 100, 2) ?></div>
            <div style="font-size:.85rem;color:var(--text-secondary);">Saldo después de gastos</div>
        </div>
    </div>

    <div class="card mb-24" style="padding:16px;">
        <div style="font-size:.85rem;font-weight:700;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-credit-card"></i> Ingresos por método de pago (General)
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(120px, 1fr));gap:12px;">
            <?php
            $listaMetodos = [
                'total_efectivo'      => ['Efectivo', 'fa-money-bill-1'],
                'total_yape'          => ['Yape / Plin', 'fa-mobile-screen'],
                'total_transferencia' => ['Transferencia', 'fa-building-columns'],
                'total_tarjeta'       => ['Tarjeta', 'fa-credit-card'],
                'total_otros'         => ['Otro', 'fa-wallet']
            ];
            foreach ($listaMetodos as $k => $m):
                $val = floatval($turno[$k]);
            ?>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;text-align:center;">
                <i class="fa-solid <?= $m[1] ?>" style="color:var(--text-secondary);font-size:1.2rem;margin-bottom:6px;"></i>
                <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);">S/ <?= number_format($val, 2) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted);"><?= $m[0] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-users"></i> Desglose por Usuario</div>
        <?php if ($desgloseUsuarios): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Responsable del movimiento</th>
                        <th class="text-right">Efectivo</th>
                        <th class="text-right">Yape/Plin</th>
                        <th class="text-right">Transferencia</th>
                        <th class="text-right">Tarjeta</th>
                        <th class="text-right">Otros</th>
                        <th class="text-right" style="color:var(--success);">Ingresos</th>
                        <th class="text-right" style="color:var(--danger);">Gastos</th>
                        <th class="text-right" style="color:#0ea5e9;">Utilidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($desgloseUsuarios as $du): 
                        $gastoU = $du['total_gastos'];
                        $utilidadU = (centimos_reporte($du['total_general']) - centimos_reporte($gastoU)) / 100;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($du['usuario']) ?></strong></td>
                        <td class="text-right">S/ <?= number_format($du['total_efectivo'], 2) ?></td>
                        <td class="text-right">S/ <?= number_format($du['total_yape'], 2) ?></td>
                        <td class="text-right">S/ <?= number_format($du['total_transferencia'], 2) ?></td>
                        <td class="text-right">S/ <?= number_format($du['total_tarjeta'], 2) ?></td>
                        <td class="text-right">S/ <?= number_format($du['total_otros'], 2) ?></td>
                        <td class="text-right fw-700" style="color:var(--success);">S/ <?= number_format($du['total_general'], 2) ?></td>
                        <td class="text-right fw-700" style="color:var(--danger);">S/ <?= number_format($gastoU, 2) ?></td>
                        <td class="text-right fw-700" style="color:#0ea5e9;">S/ <?= number_format($utilidadU, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-users-slash"></i></div>
            <p><?= $cierre === null ? 'Desglose no disponible.' : 'No se registraron movimientos en este turno.' ?></p>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>
