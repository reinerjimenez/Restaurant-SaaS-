<?php
require_once '../../config/db.php';
requireRole(['admin', 'superadmin']);
require_once '../../includes/emision_fiscal.php';
$db = getDB();
$restaurante_id = (int)$_SESSION['restaurante_id'];
$disponible = emision_esquema_disponible($db);
$error = '';
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$disponible) throw new DomainException('Falta ejecutar manualmente Database/011_emision_electronica.sql.');
        if (($_POST['accion'] ?? '') === 'configurar') {
            emision_guardar_config($db, $restaurante_id, $_POST);
            $mensaje = 'Configuración de emisión guardada.';
        } elseif (($_POST['accion'] ?? '') === 'solicitar') {
            $id = emision_solicitar($db, $restaurante_id, (int)$_SESSION['usuario_id'], $_POST);
            $mensaje = "Documento #$id preparado. No se volvió a cobrar la venta. Revisa su ambiente y estado en el historial.";
        } else throw new DomainException('Acción no válida.');
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Falló la preparación de emisión fiscal.');
        $error = 'No se pudo guardar la preparación fiscal. Intenta nuevamente.';
    }
}
$cfg = [];
$documentos = [];
if ($disponible) {
    $st = $db->prepare('SELECT * FROM emision_config WHERE restaurante_id = ?');
    $st->execute([$restaurante_id]);
    $cfg = $st->fetch() ?: [];
    $st = $db->prepare('SELECT id,pedido_id,ambiente,tipo,serie,correlativo,estado,respuesta_mensaje,created_at
        FROM emision_documentos WHERE restaurante_id = ? ORDER BY id DESC LIMIT 50');
    $st->execute([$restaurante_id]);
    $documentos = $st->fetchAll();
}
function fiscal_html(mixed $valor): string { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); }
$pageTitle = 'Emisión electrónica';
$activeMenu = 'facturacion';
require_once '../../includes/header.php';
?>
<div class="page-content">
    <h1>Emisión electrónica — preparación</h1>
    <p>Configura los documentos de este restaurante. Los ensayos no tienen validez tributaria; producción requiere habilitación individual en el servidor.</p>
    <p><a href="config_facturacion.php" class="btn btn-ghost" style="min-height:44px">Volver a datos del restaurante</a></p>
    <?php if (!$disponible): ?>
        <div class="card" role="alert">Creé el script Database/011_emision_electronica.sql. Ejecútalo manualmente en phpMyAdmin antes de probar este módulo.</div>
    <?php else: ?>
    <?php if ($error): ?><div class="card" role="alert"><?= fiscal_html($error) ?></div><?php endif; ?>
    <?php if ($mensaje): ?><div class="card" role="status"><?= fiscal_html($mensaje) ?></div><?php endif; ?>
    <form method="post" class="card mb-20">
        <h2>Datos fiscales</h2>
        <input type="hidden" name="csrf_token" value="<?= fiscal_html(getCsrfToken()) ?>">
        <?= campos_contexto_restaurante() ?>
        <input type="hidden" name="accion" value="configurar">
        <label class="form-label" for="modo">Modo</label>
        <select id="modo" name="modo" class="form-control">
            <?php foreach (['desactivado' => 'Desactivado', 'pruebas' => 'Pruebas sin envío SUNAT', 'produccion' => 'Producción (requiere autorización del servidor)'] as $valor => $etiqueta): ?>
            <option value="<?= $valor ?>" <?= ($cfg['modo'] ?? '') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
            <?php endforeach; ?>
        </select>
        <?php foreach (['ruc' => ['RUC',11], 'razon_social' => ['Razón social',200], 'direccion' => ['Dirección fiscal',300],
            'ubigeo' => ['Ubigeo',6], 'serie_boleta' => ['Serie de boleta de ensayo',4], 'serie_factura' => ['Serie de factura de ensayo',4],
            'tasa_igv' => ['Tasa IGV validada por contador (%)',5]] as $campo => [$etiqueta,$largo]): ?>
            <label class="form-label" for="<?= $campo ?>"><?= $etiqueta ?></label>
            <input class="form-control" id="<?= $campo ?>" name="<?= $campo ?>" maxlength="<?= $largo ?>" value="<?= fiscal_html($cfg[$campo] ?? ['serie_boleta'=>'B001','serie_factura'=>'F001'][$campo] ?? '') ?>" required>
        <?php endforeach; ?>
        <label class="form-label" for="regimen">Régimen de este restaurante</label>
        <select id="regimen" name="regimen" class="form-control" required>
            <option value="">Selecciona el régimen de este restaurante</option>
            <?php foreach (['RER','RMT','GENERAL'] as $regimen): ?><option <?= ($cfg['regimen'] ?? '') === $regimen ? 'selected' : '' ?>><?= $regimen ?></option><?php endforeach; ?>
        </select>
        <p>La producción requiere habilitación, certificado vigente, homologación y cierre de pendientes. No cargues Clave SOL ni certificados en este formulario.</p>
        <button class="btn btn-primary" style="min-height:44px" type="submit">Guardar configuración de ensayo</button>
    </form>
    <form method="post" class="card mb-20">
        <h2>Preparar boleta o factura</h2>
        <p>Indica el ID de un pedido ya cobrado de este restaurante. No genera un segundo cobro. Una reemisión solo procede tras un rechazo conciliado y conserva el documento anterior.</p>
        <input type="hidden" name="csrf_token" value="<?= fiscal_html(getCsrfToken()) ?>">
        <?= campos_contexto_restaurante() ?>
        <input type="hidden" name="accion" value="solicitar">
        <label class="form-label" for="pedido_id">ID del pedido cobrado</label><input class="form-control" id="pedido_id" name="pedido_id" type="number" min="1" required>
        <label class="form-label" for="tipo">Tipo</label><select class="form-control" id="tipo" name="tipo"><option value="boleta">Boleta de ensayo</option><option value="factura">Factura de ensayo</option></select>
        <label class="form-label" for="tipo_documento">Documento del cliente</label><select class="form-control" id="tipo_documento" name="tipo_documento"><option value="dni">DNI</option><option value="ruc">RUC</option></select>
        <?php foreach (['numero_documento'=>['Número de documento',11], 'nombre'=>['Nombre o razón social del cliente',200], 'direccion'=>['Dirección del cliente (obligatoria para factura)',300]] as $campo=>[$etiqueta,$largo]): ?>
        <label class="form-label" for="<?= $campo ?>"><?= $etiqueta ?></label><input class="form-control" id="<?= $campo ?>" name="<?= $campo ?>" maxlength="<?= $largo ?>" <?= $campo !== 'direccion' ? 'required' : '' ?>>
        <?php endforeach; ?>
        <label class="form-label"><input type="checkbox" name="reemision" value="1"> Reemitir tras rechazo confirmado (nuevo número fiscal)</label>
        <button type="submit" class="btn btn-primary" style="min-height:44px">Preparar documento de ensayo</button>
    </form>
    <div class="card"><h2>Últimos 50 documentos</h2><div style="overflow-x:auto"><table class="table"><thead><tr><th>ID</th><th>Pedido</th><th>Ambiente</th><th>Número</th><th>Estado</th><th>Resultado</th><th>Archivos</th></tr></thead><tbody>
    <?php foreach ($documentos as $d): ?><tr><td><?= (int)$d['id'] ?></td><td><?= (int)$d['pedido_id'] ?></td><td><?= fiscal_html($d['ambiente']) ?></td><td><?= fiscal_html($d['serie'] . '-' . $d['correlativo']) ?></td><td><?= fiscal_html($d['estado']) ?></td><td><?= fiscal_html($d['respuesta_mensaje']) ?></td><td>
        <form method="post" action="<?= fiscal_html(BASE_URL) ?>/api/descargar_emision.php">
            <input type="hidden" name="csrf_token" value="<?= fiscal_html(getCsrfToken()) ?>"><?= campos_contexto_restaurante() ?>
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button type="submit" name="archivo" value="xml" class="btn btn-ghost" style="min-height:44px">XML</button>
            <button type="submit" name="archivo" value="cdr" class="btn btn-ghost" style="min-height:44px">CDR</button>
            <button type="submit" name="archivo" value="pdf" class="btn btn-ghost" style="min-height:44px">PDF con QR</button>
        </form>
    </td></tr><?php endforeach; ?>
    <?php if (!$documentos): ?><tr><td colspan="7">Aún no hay documentos.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('form:not([action])').forEach(form => form.addEventListener('submit', () => {
    form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.textContent = 'Guardando…'; });
}));
</script>
<?php require_once '../../includes/footer.php'; ?>
