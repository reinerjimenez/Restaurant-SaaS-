<?php
require_once '../../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../../includes/emision_fiscal.php';
$db = getDB();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id || !emision_esquema_disponible($db)) { http_response_code(404); exit('Documento no encontrado.'); }
$st = $db->prepare('SELECT id,ambiente,tipo,serie,correlativo,estado,snapshot_json,respuesta_mensaje FROM emision_documentos WHERE id=? AND restaurante_id=?');
$st->execute([$id, (int)$_SESSION['restaurante_id']]);
$d = $st->fetch();
if (!$d) { http_response_code(404); exit('Documento no encontrado.'); }
try { $s = json_decode($d['snapshot_json'], true, 512, JSON_THROW_ON_ERROR); }
catch (Throwable $e) { error_log('Documento fiscal con snapshot ilegible.'); http_response_code(500); exit('No se pudo mostrar el documento.'); }
$e = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$aceptado = $d['ambiente'] === 'produccion' && in_array($d['estado'], ['aceptado','aceptado_observado'], true);
$pageTitle = 'Estado de emisión';
$activeMenu = 'comprobantes';
require_once '../../includes/header.php';
?>
<div class="page-content">
    <div class="card">
        <h1><?= $aceptado ? 'Detalle de emisión electrónica' : 'Preparación de emisión electrónica' ?></h1>
        <p role="status"><strong><?= $e($d['ambiente']) ?> · <?= $e($d['estado']) ?></strong></p>
        <?php if (!$aceptado): ?><p>Esta vista no acredita aceptación SUNAT. Los documentos de pruebas no tienen validez tributaria.</p><?php endif; ?>
        <p>Descarga la representación PDF con QR para entregar o conservar el documento.</p>
        <h2><?= $e($s['emisor']['razon_social']) ?></h2>
        <p>RUC <?= $e($s['emisor']['ruc']) ?> · <?= $e($s['emisor']['direccion']) ?></p>
        <p><?= $e($d['tipo']) ?> · <?= $e($d['serie'] . '-' . $d['correlativo']) ?> · Fecha <?= $e($s['fecha']) ?></p>
        <p>Cliente: <?= $e($s['cliente']['nombre']) ?> · <?= $e($s['cliente']['numero_documento']) ?></p>
        <div style="overflow-x:auto"><table class="table"><thead><tr><th>Descripción</th><th>Cantidad</th><th>Precio</th><th>Importe</th></tr></thead><tbody>
        <?php foreach ($s['items'] as $item): ?><tr><td><?= $e($item['descripcion']) ?></td><td><?= $e($item['cantidad']) ?></td><td>S/ <?= $e($item['precio_unitario']) ?></td><td>S/ <?= $e($item['subtotal']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p><strong>Total: S/ <?= $e($s['total']) ?></strong> · Tasa registrada: <?= $e($s['tasa_igv']) ?> %</p>
        <p><?= $e($d['respuesta_mensaje']) ?></p>
        <form method="post" action="<?= $e(BASE_URL) ?>/api/descargar_emision.php">
            <input type="hidden" name="csrf_token" value="<?= $e(getCsrfToken()) ?>"><?= campos_contexto_restaurante() ?>
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="btn btn-ghost" style="min-height:44px" name="archivo" value="xml">Descargar XML</button>
            <button class="btn btn-ghost" style="min-height:44px" name="archivo" value="cdr">Descargar CDR</button>
            <button class="btn btn-ghost" style="min-height:44px" name="archivo" value="pdf">Descargar PDF con QR</button>
        </form>
        <button type="button" class="btn btn-ghost" style="min-height:44px" onclick="window.print()">Imprimir detalle de estado</button>
        <a class="btn btn-ghost" style="min-height:44px" href="dashboard.php">Volver</a>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
