<?php
/**
 * roles/atencion/gastos.php — Registro de gastos del día (Atención)
 * MESORA | Sistema SaaS para restaurantes
 */
require_once '../../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);

require_once __DIR__ . '/../../config/finanzas.php';
require_once __DIR__ . '/../../includes/reportes_financieros.php';
require_once __DIR__ . '/../../includes/gastos_financieros.php';

$restauranteId = (int)$_SESSION['restaurante_id'];
$usuarioId = (int)$_SESSION['usuario_id'];
$db = getDB();
$hoy = date('Y-m-d');
$filtroMetodo = $_GET['metodo_pago'] ?? '';
if (!is_string($filtroMetodo) || ($filtroMetodo !== '' && !in_array($filtroMetodo, METODOS_PAGO, true))) $filtroMetodo = '';
$etiquetasMetodo = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'tarjeta' => 'Tarjeta',
    'transferencia' => 'Transferencia', 'otro' => 'Otros'];
$mensaje = '';
$tipo_mensaje = 'exito';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $mensaje = guardar_gasto($db, $restauranteId, $usuarioId, $_SESSION['rol'], $_POST, $hoy);
    } catch (DomainException $error) {
        $mensaje = $error->getMessage();
        $tipo_mensaje = 'error';
    } catch (Throwable $error) {
        error_log('No se guardó el gasto (' . get_class($error) . ':' . $error->getCode() . ').');
        $mensaje = 'No se pudo guardar el gasto. Intenta nuevamente.';
        $tipo_mensaje = 'error';
    }
}

$condicionMetodo = $filtroMetodo === '' ? '' : ' AND g.metodo_pago = ?';
$stGastos = $db->prepare("
    SELECT g.*, u.nombre AS usuario_nombre
    FROM gastos g
    LEFT JOIN usuarios u ON u.id = g.usuario_id
    WHERE g.restaurante_id = ? AND g.fecha = ? AND g.activo = 1 $condicionMetodo
    ORDER BY g.created_at DESC
");
$parametros = [$restauranteId, $hoy];
if ($filtroMetodo !== '') $parametros[] = $filtroMetodo;
$stGastos->execute($parametros);
$gastos = $stGastos->fetchAll();
$totalDia = array_sum(array_map('centimos_reporte', array_column($gastos, 'monto'))) / 100;

$pageTitle  = 'Gastos del Día';
$activeMenu = 'gastos';
require_once '../../includes/header.php';
?>

<div class="page-content">
    <?php if ($mensaje !== ''): ?>
        <div class="toast <?= $tipo_mensaje ?>" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="d-flex align-center justify-between mb-24">
        <div>
            <h1><i class="fa-solid fa-money-bill-wave" style="font-size:1rem;margin-right:6px;color:var(--primary);"></i> Gastos del Día</h1>
            <p>Fecha contable de hoy (<?= date('d/m/Y') ?>). Los gastos nuevos se vinculan al turno abierto.</p>
        </div>
        <button class="btn btn-primario" onclick="Modal.abrir('modal-gasto')">
            <i class="fa-solid fa-plus"></i> Registrar Gasto
        </button>
    </div>

    <!-- Resumen -->
    <div class="stats-grid mb-24">
        <div class="stat-card rojo">
            <div class="stat-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
            <div class="stat-valor">S/ <?= number_format($totalDia, 2) ?></div>
            <div class="stat-label"><?= $filtroMetodo === '' ? 'Total Gastos Hoy' : 'Total Gastos Hoy Filtrados' ?></div>
        </div>
        <div class="stat-card naranja">
            <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
            <div class="stat-valor"><?= count($gastos) ?></div>
            <div class="stat-label"><?= $filtroMetodo === '' ? 'Gastos Registrados' : 'Gastos Filtrados' ?></div>
        </div>
    </div>

    <!-- Tabla de Gastos de Hoy -->
    <div class="card mb-24">
        <form method="GET" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;min-width:180px;flex:1;">
                <label class="form-label" for="filtro-metodo">Método de pago</label>
                <select name="metodo_pago" id="filtro-metodo" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($etiquetasMetodo as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $filtroMetodo === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primario">Filtrar</button>
        </form>
    </div>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-list"></i> Lista de gastos de hoy</div>
        <?php if ($gastos): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha y hora de registro</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th>Registrado por</th>
                        <th>Método de pago</th>
                        <th class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gastos as $g): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($g['created_at'])) ?></td>
                        <td><span class="badge badge-gris"><?= htmlspecialchars($g['categoria']) ?></span></td>
                        <td><?= htmlspecialchars($g['descripcion']) ?></td>
                        <td><?= htmlspecialchars($g['usuario_nombre'] ?? 'Usuario no disponible') ?></td>
                        <td><?= htmlspecialchars($etiquetasMetodo[$g['metodo_pago'] ?? ''] ?? 'Sin especificar', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-right fw-700" style="color: var(--danger);">S/ <?= number_format($g['monto'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:30px 0;">
            <div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div>
            <p><?= $filtroMetodo === '' ? 'No se han registrado gastos el día de hoy.' : 'No hay gastos con este método hoy.' ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Registrar Gasto -->
<div class="modal-overlay" id="modal-gasto">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-plus"></i> Registrar Gasto</div>
            <button class="modal-close" style="min-width:44px;min-height:44px;" onclick="Modal.cerrar('modal-gasto')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" id="form-gasto">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <?= campos_contexto_restaurante() ?>
            <input type="hidden" name="accion" value="registrar">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select name="categoria" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <option value="Insumos">Insumos de Cocina</option>
                        <option value="Limpieza">Artículos de Limpieza</option>
                        <option value="Mantenimiento">Mantenimiento/Reparaciones</option>
                        <option value="Servicios">Servicios (Luz, Agua, etc.)</option>
                        <option value="Movilidad">Movilidad / Pasajes</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción del gasto</label>
                    <input type="text" name="descripcion" class="form-control" placeholder="Ej: Compra de gas, hielo, etc." required maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">Monto (S/)</label>
                    <input type="number" name="monto" class="form-control" step="0.01" min="0.01" max="99999999.99" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="metodo_pago">Método de pago</label>
                    <select name="metodo_pago" id="metodo_pago" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($etiquetasMetodo as $valor => $etiqueta): ?>
                        <option value="<?= $valor ?>"><?= $etiqueta ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="Modal.cerrar('modal-gasto')">Cancelar</button>
                <button type="submit" class="btn btn-primario" id="btn-guardar-gasto">Registrar Gasto</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('form-gasto').addEventListener('submit', function (evento) {
    if (this.dataset.enviando === '1') {
        evento.preventDefault();
        return;
    }
    this.dataset.enviando = '1';
    const boton = document.getElementById('btn-guardar-gasto');
    boton.disabled = true;
    boton.textContent = 'Guardando...';
});
</script>

<?php require_once '../../includes/footer.php'; ?>
