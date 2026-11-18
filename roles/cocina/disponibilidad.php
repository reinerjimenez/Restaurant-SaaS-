<?php
require_once '../../config/db.php';
requireRole(['cocina']);
$db = getDB();
$st = $db->prepare('SELECT id, nombre, categoria_id, sin_stock FROM productos WHERE restaurante_id = ? AND activo = 1 ORDER BY categoria_id, nombre');
$st->execute([(int)$_SESSION['restaurante_id']]);
$productos = $st->fetchAll();
$pageTitle = 'Disponibilidad de platos';
require_once '../../includes/header.php';
?>
<main class="container" style="padding:24px">
    <h1>Disponibilidad de platos</h1>
    <p>Marca como agotados los platos que ya no se pueden preparar.</p>
    <div style="max-width:420px; margin:20px 0 8px">
        <label for="buscar-plato" class="form-label">Buscar plato</label>
        <input id="buscar-plato" class="form-control" type="search" placeholder="Escribe el nombre del plato" autocomplete="off" aria-controls="tabla-platos" style="min-height:44px">
    </div>
    <p id="resultado-busqueda" role="status" aria-live="polite"><?= count($productos) ?> plato(s)</p>
    <div class="table-responsive"><table class="table" id="tabla-platos"><thead><tr><th>Plato</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
    <?php foreach ($productos as $p): ?><tr class="fila-plato">
        <td><?= htmlspecialchars($p['nombre']) ?></td>
        <td id="estado-<?= (int)$p['id'] ?>"><?= $p['sin_stock'] ? 'Agotado' : 'Disponible' ?></td>
        <td><button class="btn btn-sm" style="min-height:44px" onclick="cambiarDisponibilidad(<?= (int)$p['id'] ?>, <?= $p['sin_stock'] ? 'false' : 'true' ?>)"><?= $p['sin_stock'] ? 'Marcar disponible' : 'Marcar agotado' ?></button></td>
    </tr><?php endforeach; ?>
    <tr id="sin-resultados" hidden><td colspan="3">No se encontraron platos con ese nombre.</td></tr>
    </tbody></table></div>
</main>
<script>
const buscarPlato = document.getElementById('buscar-plato');
const filasPlato = [...document.querySelectorAll('.fila-plato')];
function textoBusqueda(valor) {
    return valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
}
buscarPlato.addEventListener('input', () => {
    const consulta = textoBusqueda(buscarPlato.value);
    let visibles = 0;
    filasPlato.forEach(fila => {
        const coincide = textoBusqueda(fila.cells[0].textContent).includes(consulta);
        fila.style.display = coincide ? '' : 'none';
        if (coincide) visibles++;
    });
    document.getElementById('sin-resultados').hidden = visibles !== 0;
    document.getElementById('resultado-busqueda').textContent = `${visibles} plato(s)`;
});
async function cambiarDisponibilidad(id, agotado) {
    try {
        const r = await fetch(`${BASE_URL}/api/cocina_disponibilidad.php`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({producto_id:id,sin_stock:agotado})});
        const j = await r.json();
        if (!j.success) { Toast.error(j.message); return; }
        location.reload();
    } catch (_) { Toast.error('No se pudo guardar el cambio. Intenta nuevamente.'); }
}
</script>
<?php require_once '../../includes/footer.php'; ?>
