<?php
require_once '../../config/db.php';
requireRole(['admin', 'superadmin']);
require_once '../../includes/emision_fiscal.php';
require_once '../../includes/nubefact_config.php';

header('Cache-Control: private, no-store');
$db = getDB();
$restaurante_id = (int)$_SESSION['restaurante_id'];
$disponible = emision_esquema_disponible($db) && nubefact_esquema_disponible($db);
$es_superadmin = ($_SESSION['rol'] ?? '') === 'superadmin';
$error = $mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$disponible) throw new DomainException('Falta instalar la migración 021 de NubeFacT.');
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'guardar') {
            $ambiente = $_POST['ambiente'] ?? '';
            if (!is_string($ambiente)) throw new DomainException('Ambiente inválido.');
            nubefact_config_guardar($db, $restaurante_id, $ambiente, $_POST);
            $mensaje = 'Conexión guardada.';
        } elseif (in_array($accion, ['aprobar', 'pausar'], true)) {
            $ruc = $_POST['confirmar_ruc'] ?? '';
            nubefact_config_habilitar($db, $restaurante_id, $accion === 'aprobar', is_string($ruc) ? trim($ruc) : '',
                $es_superadmin, ($_POST['confirmar_series'] ?? '') === '1');
            $mensaje = $accion === 'aprobar' ? 'Conexión de producción aprobada.' : 'Producción pausada.';
        } else {
            throw new DomainException('Acción no válida.');
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Falló la configuración privada de NubeFacT.');
        $error = 'No se pudo guardar la configuración.';
    }
}
$ruc = '';
if ($disponible) {
    $consulta = $db->prepare('SELECT ruc FROM emision_config WHERE restaurante_id=?');
    $consulta->execute([$restaurante_id]);
    $ruc = (string)$consulta->fetchColumn();
}
$e = static fn(mixed $valor): string => htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Conexión NubeFacT';
$activeMenu = 'facturacion';
require_once '../../includes/header.php';
?>
<div class="page-content">
    <h1>Conexión NubeFacT</h1>
    <p>Contrata NubeFacT Online con el RUC de tu restaurante. Usa la Ruta y el Token de API de esa cuenta.</p>
    <p><a class="btn btn-ghost" style="min-height:44px" href="emision_electronica.php">Volver a emisión electrónica</a></p>
    <?php if ($error !== ''): ?><p class="card" role="alert"><?= $e($error) ?></p><?php endif; ?>
    <?php if ($mensaje !== ''): ?><p class="card" role="status"><?= $e($mensaje) ?></p><?php endif; ?>
    <?php if (!$disponible): ?>
        <p class="card" role="alert">Ejecuta manualmente Database/021_nubefact.sql en phpMyAdmin después de actualizar el esquema fiscal.</p>
    <?php elseif ($ruc === ''): ?>
        <p class="card" role="alert">Guarda primero el RUC en Emisión electrónica.</p>
    <?php else: ?>
        <?php foreach (['pruebas' => 'Pruebas', 'produccion' => 'Producción'] as $ambiente => $titulo):
            $cfg = nubefact_config($db, $restaurante_id, $ambiente);
            $estado = $cfg && nubefact_config_activa($cfg) ? 'habilitada' : 'desactivada';
            ?>
            <section class="card mb-20" aria-labelledby="titulo-<?= $ambiente ?>">
                <h2 id="titulo-<?= $ambiente ?>"><?= $titulo ?></h2>
                <p>Conexión <?= $estado ?>. RUC <?= $e($ruc) ?>.</p>
                <?php if ($ambiente === 'pruebas'): ?>
                    <p>Los documentos de prueba no tienen validez tributaria.</p>
                <?php else: ?>
                    <p>Usa series nuevas, sin comprobantes previos, reservadas para MESORA desde el número 1. No emitas desde el portal del proveedor con esas series. Cambiar el Token pausa la conexión hasta una nueva revisión.</p>
                <?php endif; ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $e(getCsrfToken()) ?>"><?= campos_contexto_restaurante() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="ambiente" value="<?= $ambiente ?>">
                    <input type="hidden" name="ruc" value="<?= $e($ruc) ?>">
                    <label for="ruta-<?= $ambiente ?>" class="form-label">Ruta HTTPS <?= $cfg ? '(opcional para conservar la guardada)' : '' ?></label>
                    <input id="ruta-<?= $ambiente ?>" name="ruta" type="url" class="form-control" maxlength="512" <?= $cfg ? '' : 'required' ?>>
                    <label for="token-<?= $ambiente ?>" class="form-label">Token <?= $cfg ? '(opcional para conservar el guardado)' : '' ?></label>
                    <input id="token-<?= $ambiente ?>" name="token" type="password" class="form-control" autocomplete="new-password" maxlength="512" <?= $cfg ? '' : 'required' ?>>
                    <label for="boleta-<?= $ambiente ?>" class="form-label">Serie de boleta</label>
                    <input id="boleta-<?= $ambiente ?>" name="serie_boleta" class="form-control" maxlength="4" value="<?= $e($cfg['serie_boleta'] ?? '') ?>" required>
                    <label for="factura-<?= $ambiente ?>" class="form-label">Serie de factura</label>
                    <input id="factura-<?= $ambiente ?>" name="serie_factura" class="form-control" maxlength="4" value="<?= $e($cfg['serie_factura'] ?? '') ?>" required>
                    <?php if ($ambiente === 'pruebas'): ?>
                        <label class="form-label" style="display:flex;align-items:center;min-height:44px"><input type="checkbox" name="habilitado" value="1" <?= !empty($cfg['habilitado']) ? 'checked' : '' ?>> Habilitar conexión de pruebas</label>
                    <?php endif; ?>
                    <button class="btn btn-primary" style="min-height:44px" type="submit">Guardar conexión</button>
                </form>
                <?php if ($ambiente === 'produccion' && $cfg): ?>
                    <form method="post" class="mt-16">
                        <input type="hidden" name="csrf_token" value="<?= $e(getCsrfToken()) ?>"><?= campos_contexto_restaurante() ?>
                        <?php if ($es_superadmin && nubefact_produccion_autorizada($restaurante_id)): ?>
                            <label for="confirmar_ruc" class="form-label">Después de revisar la cuenta y las series en NubeFacT, escribe el RUC para aprobar producción</label>
                            <input id="confirmar_ruc" name="confirmar_ruc" class="form-control" maxlength="11" inputmode="numeric" required>
                            <label class="form-label" style="display:flex;align-items:center;min-height:44px"><input type="checkbox" name="confirmar_series" value="1" required> Verifiqué la titularidad y que las series son nuevas y exclusivas de MESORA, o que se está reactivando la misma conexión con sus correlativos conservados</label>
                            <button class="btn btn-primary" name="accion" value="aprobar" style="min-height:44px" type="submit">Aprobar producción</button>
                        <?php endif; ?>
                        <button class="btn btn-ghost" name="accion" value="pausar" style="min-height:44px" type="submit" formnovalidate>Pausar producción</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>
