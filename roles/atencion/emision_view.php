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
$puede_ajustar = in_array($_SESSION['rol'] ?? '', ['admin','superadmin'], true)
    && ($aceptado || ($d['ambiente'] === 'pruebas' && $d['estado'] === 'prueba_aceptada'));
$ajuste = null;
if ($puede_ajustar) {
    $stTabla = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='emision_ajustes'");
    if ((int)$stTabla->fetchColumn() === 1) {
        $stAjuste = $db->prepare('SELECT id,tipo,estado,respuesta_mensaje FROM emision_ajustes
            WHERE restaurante_id=? AND documento_id=? ORDER BY id DESC LIMIT 1');
        $stAjuste->execute([(int)$_SESSION['restaurante_id'],$id]);
        $ajuste = $stAjuste->fetch() ?: null;
    } else $puede_ajustar = false;
}
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
        <?php if ($puede_ajustar): ?>
        <section class="card" style="margin-top:18px" aria-label="Ajuste fiscal">
            <h2>Ajuste fiscal</h2>
            <?php if ($ajuste): ?>
                <p>Solicitud #<?= (int)$ajuste['id'] ?>: <?= $e($ajuste['tipo']) ?> · <?= $e($ajuste['estado']) ?>. <?= $e($ajuste['respuesta_mensaje']) ?></p>
                <p>No registres otro ajuste ni devuelvas pagos sin conciliar primero la CDR y el procedimiento de caja.</p>
            <?php elseif ($d['tipo'] === 'factura'): ?>
                <p>La anulación total de una factura aceptada requiere una nota de crédito. Prepararla no revierte el cobro.</p>
                <form id="form-ajuste" onsubmit="return enviarAjuste(event)">
                    <label for="motivo-ajuste" class="form-label">Motivo de anulación total</label>
                    <input id="motivo-ajuste" class="form-control" type="text" minlength="3" maxlength="250" required>
                    <button class="btn btn-primary" type="submit" style="min-height:44px">Preparar nota de crédito</button>
                </form>
            <?php else: ?>
                <p>La baja de una boleta no otorgada se informa mediante resumen diario. Si ya fue entregada al cliente, revisa con tu contador el procedimiento aplicable antes de solicitarla.</p>
                <form id="form-ajuste" onsubmit="return enviarAjuste(event)">
                    <label for="motivo-ajuste" class="form-label">Motivo de baja</label>
                    <input id="motivo-ajuste" class="form-control" type="text" minlength="3" maxlength="250" required>
                    <button class="btn btn-primary" type="submit" style="min-height:44px">Preparar baja</button>
                </form>
            <?php endif; ?>
            <p id="resultado-ajuste" role="status"></p>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php if ($puede_ajustar && !$ajuste): ?>
<script>
async function enviarAjuste(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const boton = form.querySelector('button');
    const resultado = document.getElementById('resultado-ajuste');
    boton.disabled = true;
    boton.textContent = 'Preparando…';
    try {
        const respuesta = await fetch(<?= json_encode(BASE_URL . '/api/solicitar_ajuste.php') ?>, {
            method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-Token':<?= json_encode(getCsrfToken()) ?>},
            body: JSON.stringify({documento_id: <?= (int)$d['id'] ?>,
                tipo: <?= json_encode($d['tipo'] === 'factura' ? 'nota_credito' : 'baja_boleta') ?>,
                motivo: document.getElementById('motivo-ajuste').value.trim()})
        });
        const datos = await respuesta.json();
        resultado.textContent = datos.message || 'No se pudo preparar el ajuste.';
        if (datos.success) window.location.reload();
    } catch (_) { resultado.textContent = 'No se pudo conectar para preparar el ajuste.'; }
    boton.disabled = false;
    boton.textContent = 'Preparar ajuste';
    return false;
}
</script>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>
