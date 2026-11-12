<?php
/**
 * roles/admin/auditoria.php — Registro de acciones relevantes del restaurante
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../../config/db.php';
requireRole(['admin', 'superadmin']);

$restauranteId = $_SESSION['restaurante_id'];
$db = getDB();

$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin    = $_GET['fecha_fin'] ?? date('Y-m-d');
$usuarioId   = max(0, (int)($_GET['usuario_id'] ?? 0));
$accion      = trim($_GET['accion'] ?? '');
$pagina      = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina   = 50;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) $fechaInicio = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) $fechaFin = date('Y-m-d');
if (strlen($accion) > 100) $accion = '';

$condiciones = [
    'restaurante_id = ?',
    'DATE(created_at) BETWEEN ? AND ?',
];
$parametros = [$restauranteId, $fechaInicio, $fechaFin];

if ($usuarioId > 0) {
    $condiciones[] = 'usuario_id = ?';
    $parametros[] = $usuarioId;
}

if ($accion !== '') {
    $condiciones[] = 'accion = ?';
    $parametros[] = $accion;
}

$whereSql = implode(' AND ', $condiciones);

$stTotal = $db->prepare("SELECT COUNT(*) FROM auditoria WHERE $whereSql");
$stTotal->execute($parametros);
$totalRegistros = (int)$stTotal->fetchColumn();
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$stRegistros = $db->prepare("
    SELECT id, usuario_id, usuario_nombre, accion, detalle, ip, created_at
    FROM auditoria
    WHERE $whereSql
    ORDER BY created_at DESC, id DESC
    LIMIT ? OFFSET ?
");

$indice = 1;
foreach ($parametros as $parametro) {
    $stRegistros->bindValue($indice++, $parametro);
}
$stRegistros->bindValue($indice++, $porPagina, PDO::PARAM_INT);
$stRegistros->bindValue($indice, $offset, PDO::PARAM_INT);
$stRegistros->execute();
$registros = $stRegistros->fetchAll();

$stUsuarios = $db->prepare("
    SELECT usuario_id, MAX(usuario_nombre) AS usuario_nombre
    FROM auditoria
    WHERE restaurante_id = ?
    GROUP BY usuario_id
    ORDER BY usuario_nombre ASC
");
$stUsuarios->execute([$restauranteId]);
$usuarios = $stUsuarios->fetchAll();

$stAcciones = $db->prepare("
    SELECT DISTINCT accion
    FROM auditoria
    WHERE restaurante_id = ?
    ORDER BY accion ASC
");
$stAcciones->execute([$restauranteId]);
$acciones = $stAcciones->fetchAll(PDO::FETCH_COLUMN);

function urlAuditoria(array $cambios = []): string {
    $parametros = array_merge($_GET, $cambios);
    foreach ($parametros as $clave => $valor) {
        if ($valor === '' || $valor === null || $valor === 0 || $valor === '0') unset($parametros[$clave]);
    }
    return 'auditoria.php' . ($parametros ? '?' . http_build_query($parametros) : '');
}

$pageTitle = 'Auditoría';
$activeMenu = 'auditoria';
require_once '../../includes/header.php';
?>

<div class="page-content">
    <div class="d-flex align-center justify-between mb-24">
        <div>
            <h1><i class="fa-solid fa-shield-halved" style="font-size:1rem;margin-right:6px;color:var(--primary);"></i> Auditoría</h1>
            <p>Registro de acciones relevantes realizadas por los usuarios</p>
        </div>
        <div class="badge badge-azul" style="padding:8px 12px;">
            <?= number_format($totalRegistros) ?> registro<?= $totalRegistros === 1 ? '' : 's' ?>
        </div>
    </div>

    <div class="card mb-24">
        <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="fecha_inicio">Desde</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio) ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="fecha_fin">Hasta</label>
                <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="usuario_id">Usuario</label>
                <select id="usuario_id" name="usuario_id" class="form-control">
                    <option value="0">Todos los usuarios</option>
                    <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?= (int)$usuario['usuario_id'] ?>" <?= $usuarioId === (int)$usuario['usuario_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($usuario['usuario_nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="accion">Acción</label>
                <select id="accion" name="accion" class="form-control">
                    <option value="">Todas las acciones</option>
                    <?php foreach ($acciones as $accionDisponible): ?>
                    <option value="<?= htmlspecialchars($accionDisponible) ?>" <?= $accion === $accionDisponible ? 'selected' : '' ?>>
                        <?= htmlspecialchars($accionDisponible) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primario" style="min-height:44px;flex:1;">
                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                </button>
                <a href="auditoria.php" class="btn btn-ghost" style="min-height:44px;display:inline-flex;align-items:center;justify-content:center;" title="Limpiar filtros">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <?php if ($registros): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="white-space:nowrap;">Fecha / hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td style="white-space:nowrap;color:var(--text-secondary);font-size:.82rem;">
                            <?= date('d/m/Y H:i:s', strtotime($registro['created_at'])) ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="user-avatar" style="width:32px;height:32px;background:var(--primary);">
                                    <?= strtoupper(substr($registro['usuario_nombre'], 0, 1)) ?>
                                </div>
                                <strong><?= htmlspecialchars($registro['usuario_nombre']) ?></strong>
                            </div>
                        </td>
                        <td><span class="badge badge-azul"><?= htmlspecialchars($registro['accion']) ?></span></td>
                        <td style="color:var(--text-secondary);max-width:360px;"><?= htmlspecialchars($registro['detalle'] ?: '—') ?></td>
                        <td style="white-space:nowrap;color:var(--text-muted);font-family:monospace;"><?= htmlspecialchars($registro['ip'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div style="display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-top:20px;">
            <?php if ($pagina > 1): ?>
            <a href="<?= htmlspecialchars(urlAuditoria(['pagina' => $pagina - 1])) ?>" class="btn btn-ghost btn-sm" style="min-height:44px;min-width:44px;">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($numeroPagina = max(1, $pagina - 2); $numeroPagina <= min($totalPaginas, $pagina + 2); $numeroPagina++): ?>
            <a href="<?= htmlspecialchars(urlAuditoria(['pagina' => $numeroPagina])) ?>"
               class="btn <?= $numeroPagina === $pagina ? 'btn-primario' : 'btn-ghost' ?> btn-sm"
               style="min-height:44px;min-width:44px;justify-content:center;">
                <?= $numeroPagina ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagina < $totalPaginas): ?>
            <a href="<?= htmlspecialchars(urlAuditoria(['pagina' => $pagina + 1])) ?>" class="btn btn-ghost btn-sm" style="min-height:44px;min-width:44px;">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-shield-halved"></i></div>
            <h3>Sin registros de auditoría</h3>
            <p>No hay acciones que coincidan con los filtros seleccionados.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
