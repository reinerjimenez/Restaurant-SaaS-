<?php
/**
 * roles/admin/reportes.php — Reportes de ventas
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);

$restauranteId = $_SESSION['restaurante_id'];
$db = getDB();

require_once __DIR__ . '/../../includes/reportes_financieros.php';

$fechaInicio = fecha_reporte($_GET['fecha_inicio'] ?? null, date('Y-m-01'));
$fechaFin = fecha_reporte($_GET['fecha_fin'] ?? null, date('Y-m-d'));
$fin_exclusivo = limite_exclusivo_reporte($fechaFin);

$periodo = ingresos_periodo($db, $restauranteId, $fechaInicio, $fin_exclusivo);
$ventasDia = array_reverse($periodo['dias']);
$ventasMetodos = $periodo['metodos'];
$ventasProds = productos_periodo($db, $restauranteId, $fechaInicio, $fin_exclusivo, 20);
$totalGeneral = $periodo['total_centimos'] / 100;
$totalGastos = gastos_contables_periodo($db, $restauranteId, $fechaInicio, $fin_exclusivo);
$utilidadNeta = ($periodo['total_centimos'] - centimos_reporte($totalGastos)) / 100;

$pageTitle  = 'Reportes';
$activeMenu = 'reportes';
require_once '../../includes/header.php';
?>

<div class="page-content">

            <div class="d-flex align-center justify-between mb-16">
                <div>
                    <h1><i class="fa-solid fa-chart-line" style="font-size:1rem;margin-right:6px;color:var(--primary);"></i> Reportes de Ventas</h1>
                    <p>Del <?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?></p>
                </div>
            </div>

            <!-- Filtro de fechas -->
            <div class="card mb-24">
                <form method="GET" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                        <label class="form-label">Desde</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= $fechaInicio ?>">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= $fechaFin ?>">
                    </div>
                    <button type="submit" class="btn btn-primario">
                        <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                    </button>
                    <a href="reportes.php" class="btn btn-ghost">
                        <i class="fa-solid fa-rotate-left"></i> Este mes
                    </a>
                </form>
            </div>

            <!-- Total del período -->
            <div class="stats-grid mb-24">
                <div class="stat-card verde">
                    <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="stat-valor">S/ <?= number_format($totalGeneral, 2) ?></div>
                    <div class="stat-label">Cobros vigentes</div>
                </div>
                <div class="stat-card rojo">
                    <div class="stat-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                    <div class="stat-valor">S/ <?= number_format($totalGastos, 2) ?></div>
                    <div class="stat-label">Gastos por fecha contable</div>
                </div>
                <div class="stat-card azul" style="background: var(--primary-light); border-color: var(--primary);">
                    <div class="stat-icon" style="color: var(--primary);"><i class="fa-solid fa-piggy-bank"></i></div>
                    <div class="stat-valor" style="color: var(--primary);">S/ <?= number_format($utilidadNeta, 2) ?></div>
                    <div class="stat-label" style="color: var(--primary);">Utilidad Neta</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
                    <div class="stat-valor"><?= array_sum(array_column($ventasDia, 'pedidos')) ?></div>
                    <div class="stat-label">Pedidos totales</div>
                </div>
            </div>

            <div class="stats-grid mb-24">
                <?php
                $metodoMap2 = array_column($ventasMetodos, 'total', 'metodo');
                $metodos2 = [
                    ['efectivo','fa-money-bill-wave','Efectivo'],
                    ['yape','fa-mobile-screen','Yape'],
                    ['transferencia','fa-building-columns','Transferencia'],
                    ['tarjeta','fa-credit-card','Tarjeta'],
                    ['otro','fa-rotate','Otro'],
                ];
                foreach ($metodos2 as [$key,$icon,$label]): ?>
                <div class="stat-card naranja">
                    <div class="stat-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                    <div class="stat-valor">S/ <?= number_format($metodoMap2[$key] ?? 0, 2) ?></div>
                    <div class="stat-label"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                <!-- Ventas por día -->
                <div class="card">
                    <div class="card-title"><i class="fa-solid fa-calendar-days"></i> Ventas por día</div>
                    <?php if ($ventasDia): ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Fecha</th><th>Pedidos</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($ventasDia as $v): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($v['fecha'])) ?></td>
                                    <td><?= $v['pedidos'] ?></td>
                                    <td><strong>S/ <?= number_format($v['total'],2) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state" style="padding:30px 0;">
                        <div class="icon"><i class="fa-solid fa-calendar-days"></i></div>
                        <p>Sin datos en el período</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Top productos -->
                <div class="card">
                    <div class="card-title"><i class="fa-solid fa-trophy"></i> Top productos vendidos</div>
                    <?php if ($ventasProds): ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>#</th><th>Producto</th><th>Cant.</th><th>Subtotal sin ajustes</th></tr></thead>
                            <tbody>
                                <?php foreach ($ventasProds as $i => $p): ?>
                                <tr>
                                    <td><?= $i+1 ?></td>
                                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                                    <td><?= $p['vendidos'] ?></td>
                                    <td>S/ <?= number_format($p['ingreso'],2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state" style="padding:30px 0;">
                        <div class="icon"><i class="fa-solid fa-utensils"></i></div>
                        <p>Sin ventas en el período</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

<?php require_once '../../includes/footer.php'; ?>
