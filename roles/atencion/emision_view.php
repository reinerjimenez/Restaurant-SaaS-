<?php
require_once '../../config/db.php';
requireRole(['atencion', 'admin', 'encargado', 'superadmin']);
require_once '../../includes/emision_fiscal.php';
require_once '../../includes/nubefact_config.php';
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
$es_nubefact = ($s['proveedor'] ?? 'directo') === 'nubefact';
$es_prueba = $d['ambiente'] === 'pruebas';
$archivos_nubefact = null;
if ($es_nubefact && nubefact_esquema_disponible($db)) {
    $stProveedor = $db->prepare('SELECT pdf_url,xml_url,cdr_url,referencia_ajuste FROM emision_nubefact_documentos
        WHERE documento_id=? AND restaurante_id=?');
    $stProveedor->execute([$id, (int)$_SESSION['restaurante_id']]);
    $archivos_nubefact = $stProveedor->fetch() ?: null;
}
$descarga_nubefact = $es_nubefact && ($aceptado || ($es_prueba && $d['estado'] === 'prueba_aceptada'));
$admin_fiscal = in_array($_SESSION['rol'] ?? '', ['admin','superadmin'], true);
$puede_ajustar = !$es_nubefact && $admin_fiscal
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
        <p role="status"><strong><?= $es_nubefact ? 'NubeFacT · ' : '' ?><?= $es_prueba ? 'Pruebas' : 'Producción' ?> · <?= $e([
            'pendiente'=>'Pendiente de envío', 'procesando'=>'En proceso', 'pendiente_ticket'=>'Pendiente de confirmación',
            'incierto'=>'En revisión', 'aceptado'=>'Aceptado', 'aceptado_observado'=>'Aceptado con observaciones',
            'rechazado'=>'Rechazado', 'prueba_aceptada'=>'Ensayo aceptado', 'error'=>'Error',
        ][$d['estado']] ?? $d['estado']) ?></strong></p>
        <?php if ($es_prueba): ?>
            <p>Documento de ensayo, sin validez tributaria. No lo entregues al cliente.</p>
        <?php elseif (!$aceptado): ?>
            <p>La aceptación fiscal debe confirmarse antes de entregar el comprobante.</p>
        <?php endif; ?>
        <h2><?= $e($s['emisor']['razon_social']) ?></h2>
        <p>RUC <?= $e($s['emisor']['ruc']) ?> · <?= $e($s['emisor']['direccion']) ?></p>
        <p><?= $e($d['tipo']) ?> · <?= $e($d['serie'] . '-' . $d['correlativo']) ?> · Fecha <?= $e($s['fecha']) ?></p>
        <p>Cliente: <?= $e($s['cliente']['nombre']) ?> · <?= $e($s['cliente']['numero_documento']) ?></p>
        <div style="overflow-x:auto"><table class="table"><thead><tr><th>Descripción</th><th>Cantidad</th><th>Precio</th><th>Importe</th></tr></thead><tbody>
        <?php foreach ($s['items'] as $item): ?><tr><td><?= $e($item['descripcion']) ?></td><td><?= $e($item['cantidad']) ?></td><td>S/ <?= $e($item['precio_unitario']) ?></td><td>S/ <?= $e($item['subtotal']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p><strong>Total: S/ <?= $e($s['total']) ?></strong> · Tasa registrada: <?= $e($s['tasa_igv']) ?> %</p>
        <p><?= $e($d['respuesta_mensaje']) ?></p>
        <?php if (!$es_nubefact || ($descarga_nubefact && $archivos_nubefact)): ?>
        <form method="post" action="<?= $e(BASE_URL) ?>/api/descargar_emision.php">
            <input type="hidden" name="csrf_token" value="<?= $e(getCsrfToken()) ?>"><?= campos_contexto_restaurante() ?>
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <?php foreach (['xml'=>'XML','cdr'=>'CDR','pdf'=>'PDF con QR'] as $archivo => $etiqueta): ?>
                <?php if (!$es_nubefact || !empty($archivos_nubefact[$archivo . '_url'])): ?>
                <button type="submit" class="btn btn-ghost" style="min-height:44px" name="archivo" value="<?= $e($archivo) ?>">Descargar <?= $e($etiqueta) ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
        <?php endif; ?>
        <?php if ($es_nubefact && !$archivos_nubefact): ?>
            <p>El vínculo con el proveedor no está disponible. Contacta a soporte.</p>
        <?php elseif ($es_nubefact && !in_array($d['estado'], ['rechazado','error'], true)): ?>
            <?php if ($descarga_nubefact && (empty($archivos_nubefact['pdf_url']) || empty($archivos_nubefact['xml_url']) || empty($archivos_nubefact['cdr_url']))): ?>
                <p>Puede haber archivos pendientes. Usa «Consultar estado» para actualizar su disponibilidad.</p>
            <?php endif; ?>
            <form method="post" action="<?= $e(BASE_URL) ?>/api/gestionar_nubefact.php" data-accion-nubefact>
                <input type="hidden" name="csrf_token" value="<?= $e(getCsrfToken()) ?>"><?= campos_contexto_restaurante() ?>
                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <input type="hidden" name="accion" value="<?= $d['estado'] === 'pendiente' ? 'enviar' : 'consultar' ?>">
                <button type="submit" class="btn btn-ghost" style="min-height:44px"><?= $d['estado'] === 'pendiente' ? 'Enviar a NubeFacT' : 'Consultar estado' ?></button>
            </form>
        <?php endif; ?>
        <?php if (!$es_nubefact): ?>
            <button type="button" class="btn btn-ghost" style="min-height:44px" onclick="window.print()">Imprimir detalle de estado</button>
        <?php endif; ?>
        <a class="btn btn-ghost" style="min-height:44px" href="dashboard.php">Volver</a>
        <?php if ($es_nubefact && $aceptado && $archivos_nubefact): ?>
        <section class="card" style="margin-top:18px" aria-label="Ajuste externo">
            <h2>Ajuste en NubeFacT</h2>
            <p>Tramita las notas o bajas en NubeFacT. Registrar la referencia del trámite no confirma su aceptación ni revierte el cobro.</p>
            <?php if ($archivos_nubefact['referencia_ajuste']): ?>
                <p>Referencia registrada: <?= $e($archivos_nubefact['referencia_ajuste']) ?></p>
            <?php elseif ($admin_fiscal): ?>
                <form method="post" action="<?= $e(BASE_URL) ?>/api/gestionar_nubefact.php" data-accion-nubefact>
                    <input type="hidden" name="csrf_token" value="<?= $e(getCsrfToken()) ?>"><?= campos_contexto_restaurante() ?>
                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="accion" value="conciliar">
                    <label for="referencia-nubefact" class="form-label">Referencia del ajuste</label>
                    <input type="text" id="referencia-nubefact" name="referencia" class="form-control" maxlength="120" required>
                    <button type="submit" class="btn btn-primary" style="min-height:44px">Registrar referencia</button>
                </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if ($es_nubefact): ?><p id="estado-accion-nubefact" role="status"></p><?php endif; ?>
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
<?php if ($es_nubefact): ?>
<script>
document.querySelectorAll('[data-accion-nubefact]').forEach(formulario => {
    formulario.addEventListener('submit', async event => {
        event.preventDefault();
        const boton = formulario.querySelector('button');
        const resultado = document.getElementById('estado-accion-nubefact');
        boton.disabled = true;
        resultado.textContent = 'Procesando solicitud…';
        try {
            const respuesta = await fetch(formulario.action, {
                method: 'POST',
                headers: {'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'},
                body: new URLSearchParams(new FormData(formulario))
            });
            if (respuesta.ok && respuesta.redirected) { window.location.reload(); return; }
            const datos = await respuesta.json();
            if (datos.success) { window.location.reload(); return; }
            resultado.textContent = datos.message || 'No se pudo completar la solicitud.';
        } catch (_) { resultado.textContent = 'No se confirmó la solicitud. Recarga la página para revisar el estado.'; }
        boton.disabled = false;
    });
});
</script>
<?php endif; ?>
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
