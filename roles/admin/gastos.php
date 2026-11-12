<?php
/**
 * roles/admin/gastos.php — Gestión completa de gastos (Admin)
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);

require_once __DIR__ . '/../../config/finanzas.php';
require_once __DIR__ . '/../../includes/reportes_financieros.php';
require_once __DIR__ . '/../../includes/gastos_financieros.php';

$restauranteId = (int)$_SESSION['restaurante_id'];
$usuarioId = (int)$_SESSION['usuario_id'];
$db = getDB();
$hoy = date('Y-m-d');
$mensaje = '';
$tipo_mensaje = 'exito';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $mensaje = guardar_gasto($db, $restauranteId, $usuarioId, $_SESSION['rol'], $_POST);
    } catch (DomainException $error) {
        $mensaje = $error->getMessage();
        $tipo_mensaje = 'error';
    } catch (Throwable $error) {
        error_log('No se guardó el gasto (' . get_class($error) . ':' . $error->getCode() . ').');
        $mensaje = 'No se pudo guardar el gasto. Intenta nuevamente.';
        $tipo_mensaje = 'error';
    }
}

$fechaInicio = fecha_reporte($_GET['fecha_inicio'] ?? null, date('Y-m-01'));
$fechaFin = fecha_reporte($_GET['fecha_fin'] ?? null, $hoy);
$stGastos = $db->prepare("
    SELECT g.*, u.nombre AS usuario_nombre,
           (g.activo = 1 AND t.id IS NOT NULL AND t.fin IS NULL) AS editable
    FROM gastos g
    LEFT JOIN usuarios u ON u.id = g.usuario_id
    LEFT JOIN turnos t ON t.id = g.turno_id AND t.restaurante_id = g.restaurante_id
    WHERE g.restaurante_id = ? AND g.fecha >= ? AND g.fecha < ?
    ORDER BY g.fecha DESC, g.created_at DESC
");
$stGastos->execute([$restauranteId, $fechaInicio, limite_exclusivo_reporte($fechaFin)]);
$gastos = $stGastos->fetchAll();
$activos = array_filter($gastos, static fn(array $gasto): bool => (bool)$gasto['activo']);
$totalPeriodo = array_sum(array_map('centimos_reporte', array_column($activos, 'monto'))) / 100;

$pageTitle  = 'Gestión de Gastos';
$activeMenu = 'gastos';
require_once '../../includes/header.php';
?>

<div class="page-content">
    <?php if ($mensaje !== ''): ?>
        <div class="toast <?= $tipo_mensaje ?>" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="d-flex align-center justify-between mb-16">
        <div>
            <h1><i class="fa-solid fa-money-bill-wave" style="font-size:1rem;margin-right:6px;color:var(--primary);"></i> Gestión de Gastos</h1>
            <p>Gastos por fecha contable; el turno corresponde al momento de registro.</p>
        </div>
        <button class="btn btn-primario" onclick="abrirModalGasto()">
            <i class="fa-solid fa-plus"></i> Registrar Gasto
        </button>
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
            <a href="gastos.php" class="btn btn-ghost">
                <i class="fa-solid fa-rotate-left"></i> Este mes
            </a>
        </form>
    </div>

    <div class="stats-grid mb-24">
        <div class="stat-card rojo">
            <div class="stat-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
            <div class="stat-valor">S/ <?= number_format($totalPeriodo, 2) ?></div>
            <div class="stat-label">Total de gastos activos</div>
        </div>
        <div class="stat-card naranja">
            <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
            <div class="stat-valor"><?= count($activos) ?></div>
            <div class="stat-label">Gastos activos</div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-list"></i> Detalle de Gastos</div>
        <?php if ($gastos): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha contable</th>
                        <th>Turno de registro</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th>Registrado por</th>
                        <th class="text-right">Monto</th>
                        <?php if ($_SESSION['rol'] !== 'encargado'): ?>
                        <th class="text-right">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gastos as $g): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($g['fecha'])) ?></td>
                        <td><?= $g['turno_id'] ? '#' . (int)$g['turno_id'] : 'Sin asignar' ?></td>
                        <td><span class="badge badge-gris"><?= htmlspecialchars($g['categoria']) ?></span></td>
                        <td><?= htmlspecialchars($g['descripcion']) ?></td>
                        <td><?= htmlspecialchars($g['usuario_nombre'] ?? 'Usuario no disponible') ?></td>
                        <td class="text-right fw-700" style="color: var(--danger);">S/ <?= number_format($g['monto'], 2) ?><?= $g['activo'] ? '' : ' · Anulado' ?></td>
                        <?php if ($_SESSION['rol'] !== 'encargado'): ?>
                        <td class="text-right">
                            <?php if ($g['editable']): ?>
                            <button type="button" class="btn btn-ghost btn-sm" style="min-height:44px;min-width:44px;" title="Editar gasto"
                                    data-gasto="<?= htmlspecialchars(json_encode($g, JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="editarGasto(JSON.parse(this.dataset.gasto))">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger);min-height:44px;min-width:44px;" title="Anular gasto"
                                    onclick="eliminarGasto(<?= (int)$g['id'] ?>)">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:30px 0;">
            <div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div>
            <p>No se han registrado gastos en este período.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Gasto -->
<div class="modal-overlay" id="modal-gasto">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-titulo"><i class="fa-solid fa-plus"></i> Registrar Gasto</div>
            <button class="modal-close" style="min-width:44px;min-height:44px;" onclick="Modal.cerrar('modal-gasto')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <?= campos_contexto_restaurante() ?>
            <input type="hidden" name="accion" id="accion" value="registrar">
            <input type="hidden" name="id" id="gasto_id" value="">
            <div class="modal-body">
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Fecha contable</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoría</label>
                        <select name="categoria" id="categoria" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="Insumos">Insumos de Cocina</option>
                            <option value="Limpieza">Artículos de Limpieza</option>
                            <option value="Mantenimiento">Mantenimiento/Reparaciones</option>
                            <option value="Servicios">Servicios (Luz, Agua, etc.)</option>
                            <option value="Movilidad">Movilidad / Pasajes</option>
                            <option value="Planilla">Planilla / Adelantos</option>
                            <option value="Otros">Otros</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción del gasto</label>
                    <input type="text" name="descripcion" id="descripcion" class="form-control" placeholder="Ej: Compra de gas" required maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">Monto (S/)</label>
                    <input type="number" name="monto" id="monto" class="form-control" step="0.01" min="0.01" max="99999999.99" placeholder="0.00" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.cerrar('modal-gasto')">Cancelar</button>
                <button type="submit" class="btn btn-primario" id="btn-submit">Registrar Gasto</button>
            </div>
        </form>
    </div>
</div>

<!-- Formulario para anular sin borrar el registro -->
<form method="POST" id="form-eliminar" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <?= campos_contexto_restaurante() ?>
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id" id="eliminar_id" value="">
    <input type="hidden" name="motivo" id="eliminar_motivo" value="">
</form>

<script>
function abrirModalGasto() {
    document.getElementById('accion').value = 'registrar';
    document.getElementById('gasto_id').value = '';
    document.getElementById('fecha').value = '<?= date('Y-m-d') ?>';
    document.getElementById('categoria').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('monto').value = '';
    document.getElementById('modal-titulo').innerHTML = '<i class="fa-solid fa-plus"></i> Registrar Gasto';
    document.getElementById('btn-submit').textContent = 'Registrar Gasto';
    Modal.abrir('modal-gasto');
}

function editarGasto(g) {
    document.getElementById('accion').value = 'editar';
    document.getElementById('gasto_id').value = g.id;
    document.getElementById('fecha').value = g.fecha;
    document.getElementById('categoria').value = g.categoria;
    document.getElementById('descripcion').value = g.descripcion;
    document.getElementById('monto').value = parseFloat(g.monto).toFixed(2);
    document.getElementById('modal-titulo').innerHTML = '<i class="fa-solid fa-pen"></i> Editar Gasto';
    document.getElementById('btn-submit').textContent = 'Guardar Cambios';
    Modal.abrir('modal-gasto');
}

function eliminarGasto(id) {
    confirmar('¿Anular este gasto del turno abierto?', () => {
        const motivo = window.prompt('Motivo de anulación (sin datos personales):');
        if (motivo === null) return;
        if (!motivo.trim() || motivo.trim().length > 200) {
            Toast.error('Indica un motivo de hasta 200 caracteres.');
            return;
        }
        document.getElementById('eliminar_motivo').value = motivo.trim();
        document.getElementById('eliminar_id').value = id;
        document.getElementById('form-eliminar').submit();
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
