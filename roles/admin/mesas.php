<?php
/**
 * roles/admin/mesas.php — Gestión de mesas + mapa interactivo
 * Sistema SaaS Restaurante | R.DEV
 */
session_start();
require_once '../../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);

$restauranteId = $_SESSION['restaurante_id'];
$db = getDB();

$stTurno = $db->prepare("SELECT id FROM turnos WHERE restaurante_id = ? AND fin IS NULL LIMIT 1");
$stTurno->execute([$restauranteId]);
$cajaAbierta = (bool)$stTurno->fetch();

$stMesas = $db->prepare("
    SELECT m.id, m.restaurante_id, m.numero, m.capacidad, m.activo,
           CASE WHEN p.id IS NOT NULL THEN 'ocupada' ELSE m.estado END AS estado,
           p.id  AS pedido_id,
           p.tipo,
           p.total,
           p.updated_at AS pedido_actividad,
           COUNT(pi.id) AS num_items
    FROM mesas m
    LEFT JOIN pedidos p  ON p.mesa_id = m.id AND p.estado = 'activo'
    LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
    WHERE m.restaurante_id = ?
    GROUP BY m.id, p.id
    ORDER BY m.numero ASC
");
$stMesas->execute([$restauranteId]);
$mesas = $stMesas->fetchAll();

$pageTitle  = 'Gestión de Mesas';
$activeMenu = 'mesas';
require_once '../../includes/header.php';
?>


        <div class="page-content">

            <div class="d-flex align-center justify-between mb-24">
                <div>
                    <h1><i class="fa-solid fa-chair" style="font-size:1rem;margin-right:6px;color:var(--primary);"></i> Gestión de Mesas</h1>
                    <p><?= count($mesas) ?> mesas configuradas</p>
                </div>
                <button class="btn btn-primario" onclick="Modal.abrir('modal-nueva-mesa')" id="btn-nueva-mesa">
                    <i class="fa-solid fa-plus"></i> Nueva Mesa
                </button>
            </div>

            <!-- Mapa interactivo -->
            <div class="card mb-16">
                <div class="card-title">
                    <i class="fa-solid fa-map-location-dot"></i> Mapa interactivo del restaurante
                    <span style="margin-left:auto;display:flex;gap:12px;font-size:.72rem;font-weight:600;">
                        <span style="display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:50%;background:var(--success);display:inline-block;"></span> Libre</span>
                        <span style="display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:50%;background:var(--primary);display:inline-block;"></span> Ocupada</span>
                        <span style="display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:50%;background:var(--warning);display:inline-block;"></span> Reservada</span>
                    </span>
                </div>
                <div class="mesas-map-grid" id="mesas-map-grid">
                    <?php foreach ($mesas as $m): ?>
                    <?php $min = $m['pedido_actividad'] ? max(0, round((time() - strtotime($m['pedido_actividad'])) / 60)) : 0; ?>
                    <div class="mesa-map-card <?= $m['activo'] ? $m['estado'] : 'inactiva' ?>"
                         data-id="<?= $m['id'] ?>"
                         onclick="<?= $m['activo'] ? "clickMesa(".htmlspecialchars(json_encode($m)).")" : "Toast.advertencia('Mesa inactiva')" ?>">

                        <?php if ($m['pedido_actividad']): ?>
                        <div class="map-tiempo" title="Desde la última actualización del pedido"><?= $min ?>m</div>
                        <?php endif; ?>

                        <?php if ($m['pedido_id']): ?>
                        <div class="map-badge"><i class="fa-solid fa-utensils"></i> <?= $m['num_items'] ?></div>
                        <?php endif; ?>

                        <div class="map-num"><?= $m['numero'] ?></div>
                        <div class="map-cap"><i class="fa-solid fa-users" style="font-size:.65rem;"></i> <?= $m['capacidad'] ?></div>

                        <?php if ($m['pedido_id']): ?>
                            <div class="map-est" style="color:var(--primary);font-weight:800;">S/ <?= number_format($m['total'], 2) ?></div>
                        <?php else: ?>
                            <div class="map-est"><?= $m['activo'] ? strtoupper($m['estado']) : 'INACTIVA' ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$mesas): ?>
                    <div class="empty-state" style="grid-column:1/-1;">
                        <div class="icon"><i class="fa-solid fa-chair"></i></div>
                        <h3>Sin mesas configuradas</h3>
                        <p>Agrega tu primera mesa para comenzar</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabla de gestión -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-table-list"></i> Lista detallada</div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Mesa</th>
                                <th>Capacidad</th>
                                <th>Estado</th>
                                <th>Activa</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-mesas">
                            <?php foreach ($mesas as $m): ?>
                            <tr data-id="<?= $m['id'] ?>">
                                <td><strong>Mesa <?= $m['numero'] ?></strong></td>
                                <td>
                                    <span style="display:flex;align-items:center;gap:5px;">
                                        <i class="fa-solid fa-users" style="color:var(--text-muted);font-size:.75rem;"></i>
                                        <?= $m['capacidad'] ?> personas
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= ['libre'=>'badge-verde','ocupada'=>'badge-azul','reservada'=>'badge-naranja'][$m['estado']] ?? 'badge-verde' ?>">
                                        <?= ucfirst($m['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $m['activo'] ? 'badge-verde' : 'badge-gris' ?>">
                                        <?= $m['activo'] ? 'Sí' : 'No' ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button class="btn btn-ghost btn-sm"
                                            onclick="editarMesa(<?= htmlspecialchars(json_encode($m)) ?>)">
                                            <i class="fa-solid fa-pen"></i> Editar
                                        </button>
                                        <button class="btn btn-peligro btn-sm"
                                            onclick="eliminarMesa(<?= $m['id'] ?>, <?= $m['numero'] ?>)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

<!-- MODAL: ¿Qué hacer con esta mesa? -->
<div class="modal-overlay" id="modal-mesa">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="modal-mesa-titulo"><i class="fa-solid fa-chair"></i> Mesa</div>
                <div style="font-size:.78rem;color:var(--text-secondary);margin-top:2px;" id="modal-mesa-sub"></div>
            </div>
            <button class="modal-close" onclick="Modal.cerrar('modal-mesa')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modal-mesa-body"></div>
    </div>
</div>

<!-- MODAL: Elegir tipo de pedido -->
<div class="modal-overlay" id="modal-tipo">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-circle-question"></i> ¿Cómo consume?</div>
        </div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:8px 0;">
                <button id="btn-aqui" class="btn btn-exito btn-lg"
                    style="flex-direction:column;gap:8px;height:100px;font-size:1rem;">
                    <i class="fa-solid fa-house fa-lg"></i>
                    <span>Comer aquí</span>
                </button>
                <button id="btn-llevar" class="btn btn-naranja btn-lg"
                    style="flex-direction:column;gap:8px;height:100px;font-size:1rem;">
                    <i class="fa-solid fa-bag-shopping fa-lg"></i>
                    <span>Para llevar</span>
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost btn-full" onclick="Modal.cerrar('modal-tipo')">Cancelar</button>
        </div>
    </div>
</div>

<!-- MODAL: Detalle del pedido -->
<div class="modal-overlay" id="modal-detalle">
    <div class="modal" style="max-height:85vh;display:flex;flex-direction:column;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-receipt"></i> Detalle del Pedido</div>
            <button class="modal-close" onclick="Modal.cerrar('modal-detalle')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modal-detalle-body" style="overflow-y:auto;">
            <div class="text-center"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<!-- MODAL: Nueva / Editar Mesa -->
<div class="modal-overlay" id="modal-nueva-mesa">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-mesa-titulo-form"><i class="fa-solid fa-plus"></i> Nueva Mesa</div>
            <button class="modal-close" onclick="Modal.cerrar('modal-nueva-mesa')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-mesa">
                <input type="hidden" id="mesa-id" name="id" value="">
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="mesa-numero">N° de Mesa</label>
                        <input type="number" id="mesa-numero" name="numero" class="form-control" min="1" max="999" required placeholder="Ej: 5">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mesa-capacidad">Capacidad (personas)</label>
                        <input type="number" id="mesa-capacidad" name="capacidad" class="form-control" min="1" max="50" value="4" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="mesa-activo">Estado</label>
                    <select id="mesa-activo" name="activo" class="form-control">
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost btn-full" onclick="Modal.cerrar('modal-nueva-mesa')">Cancelar</button>
            <button class="btn btn-primario btn-full" onclick="guardarMesa()" id="btn-guardar-mesa">
                <i class="fa-solid fa-floppy-disk"></i> Guardar
            </button>
        </div>
    </div>
</div>

<script>
const CAJA_ABIERTA = <?= json_encode($cajaAbierta) ?>;
let mesaActual = null;

function clickMesa(mesa) {
    mesaActual = mesa;
    const titulo = document.getElementById('modal-mesa-titulo');
    const sub    = document.getElementById('modal-mesa-sub');
    const body   = document.getElementById('modal-mesa-body');
    titulo.innerHTML = `<i class="fa-solid fa-chair"></i> Mesa ${mesa.numero}`;

    if (!mesa.pedido_id && mesa.estado === 'libre') {
        sub.innerHTML = `<i class="fa-solid fa-users"></i> ${mesa.capacidad} personas &middot; Libre`;
        if (!CAJA_ABIERTA) {
            body.innerHTML = `
                <div style="text-align:center;padding:10px 0;">
                    <div class="empty-state" style="padding:16px 0;">
                        <div class="icon" style="color:var(--danger);"><i class="fa-solid fa-lock"></i></div>
                        <p style="color:var(--danger);font-weight:600;">La caja está cerrada</p>
                        <p style="font-size:0.85rem;color:var(--text-secondary);">No se pueden iniciar nuevas comandas.</p>
                    </div>
                </div>`;
        } else {
            body.innerHTML = `
                <div style="text-align:center;padding:10px 0;">
                    <div class="empty-state" style="padding:16px 0;">
                        <div class="icon"><i class="fa-solid fa-utensils"></i></div>
                        <p>Esta mesa está libre.</p>
                    </div>
                    <button class="btn btn-primario btn-full btn-lg" onclick="iniciarPedido()">
                        <i class="fa-solid fa-plus"></i> Iniciar nueva comanda
                    </button>
                </div>`;
        }
    } else if (mesa.pedido_id) {
        const minutos = mesa.pedido_actividad
            ? Math.max(0, Math.round((Date.now() - new Date(mesa.pedido_actividad.replace(' ', 'T') + '-05:00').getTime()) / 60000)) : 0;
        sub.innerHTML = `<i class="fa-solid fa-hourglass-half"></i> Última actividad: ${minutos} min &middot; ${mesa.num_items} plato(s) &middot; S/ ${parseFloat(mesa.total).toFixed(2)}`;
        body.innerHTML = `
            <div style="display:flex;flex-direction:column;gap:10px;padding:8px 0;">
                <button onclick="verDetalle(${mesa.pedido_id}, ${mesa.id})"
                   class="btn btn-ghost btn-full btn-lg">
                   <i class="fa-solid fa-eye"></i> Ver pedido actual
                </button>
                <a href="<?= BASE_URL ?>/roles/atencion/comanda.php?pedido_id=${mesa.pedido_id}&mesa_id=${mesa.id}"
                   class="btn btn-naranja btn-full btn-lg">
                   <i class="fa-solid fa-plus"></i> Añadir platos al pedido
                </a>
                <a href="<?= BASE_URL ?>/roles/atencion/cobrar.php?pedido_id=${mesa.pedido_id}"
                   class="btn btn-exito btn-full btn-lg">
                   <i class="fa-solid fa-sack-dollar"></i> Cobrar (S/ ${parseFloat(mesa.total).toFixed(2)})
                </a>
                <button onclick="cancelarPedido(${mesa.pedido_id})"
                   class="btn btn-ghost btn-full mt-8" style="color:var(--danger);font-weight:600;">
                   <i class="fa-solid fa-xmark"></i> Desocupar Mesa (cancela la comanda)
                </button>
            </div>`;
    } else {
        sub.textContent = 'Mesa reservada';
        body.innerHTML  = '<p style="text-align:center;padding:20px 0;">Esta mesa está reservada.</p>';
    }
    Modal.abrir('modal-mesa');
}

function iniciarPedido() {
    Modal.cerrar('modal-mesa');
    setTimeout(() => Modal.abrir('modal-tipo'), 200);
    document.getElementById('btn-aqui').onclick   = () => crearPedidoYIr('aqui');
    document.getElementById('btn-llevar').onclick = () => crearPedidoYIr('llevar');
}

async function crearPedidoYIr(tipo) {
    Modal.cerrar('modal-tipo');
    Loading.show();
    try {
        const res  = await fetch(BASE_URL + '/api/crear_pedido.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({ mesa_id: mesaActual.id, tipo })
        });
        const json = await res.json();
        if (json.success) {
            window.location.href = `${BASE_URL}/roles/atencion/comanda.php?pedido_id=${json.data.pedido_id}&mesa_id=${mesaActual.id}`;
        } else { Toast.error(json.message); Loading.hide(); }
    } catch(e) { Toast.error('Error de conexión'); Loading.hide(); }
}

function cancelarPedido(id) {
    Modal.cerrar('modal-mesa');
    setTimeout(() => {
        confirmar('¿Seguro que quieres descartar este pedido? La mesa quedará libre nuevamente.', async () => {
            Loading.show();
            try {
                const res  = await fetch(BASE_URL + '/api/cancelar_pedido.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.success) { Toast.exito(json.message); setTimeout(() => location.reload(), 700); }
                else { Toast.error(json.message); Loading.hide(); }
            } catch(e) { Toast.error('Error de conexión'); Loading.hide(); }
        });
    }, 200);
}

async function verDetalle(pedidoId, mesaId) {
    Modal.cerrar('modal-mesa');
    setTimeout(() => Modal.abrir('modal-detalle'), 200);
    document.getElementById('modal-detalle-body').innerHTML = '<div class="text-center"><div class="spinner"></div></div>';
    try {
        const res  = await fetch(`${BASE_URL}/api/get_pedido_detalle.php?id=${pedidoId}`);
        const json = await res.json();
        if (!json.success) { document.getElementById('modal-detalle-body').innerHTML = '<p>Error al cargar</p>'; return; }
        renderDetalle(json.data, mesaId);
    } catch(e) { document.getElementById('modal-detalle-body').innerHTML = '<p>Error de conexión</p>'; }
}

function renderDetalle(p, mesaId) {
    // — Contenedor principal usando DOM para evitar XSS con datos de usuario —
    const container = document.createDocumentFragment();

    // Cabecera con badges (datos de sistema, no de usuario: id, tipo, mesa_numero son enteros/enum)
    const headerDiv = document.createElement('div');
    headerDiv.style.cssText = 'margin-bottom:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;';
    headerDiv.innerHTML = `<span class="badge badge-azul"><i class="fa-solid fa-hashtag"></i>${parseInt(p.id)}</span>
        <span class="badge ${p.tipo==='aqui'?'badge-azul':'badge-naranja'}">
            <i class="fa-solid ${p.tipo==='aqui'?'fa-house':'fa-bag-shopping'}"></i>
            ${p.tipo==='aqui'?'Comer aquí':'Para llevar'}
        </span>
        ${p.mesa_numero ? `<span class="badge badge-gris">Mesa ${parseInt(p.mesa_numero)}</span>` : ''}`;
    container.appendChild(headerDiv);

    if (!p.items || p.items.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'empty-state';
        emptyDiv.style.padding = '20px 0';
        emptyDiv.innerHTML = '<div class="icon"><i class="fa-solid fa-utensils"></i></div><p>Sin platos en este pedido</p>';
        container.appendChild(emptyDiv);
    } else {
        p.items.forEach(item => {
            // — Nodo completo del ítem sin interpolación de datos de usuario en HTML —
            const itemDiv = document.createElement('div');
            itemDiv.className = 'pedido-item';
            itemDiv.id = `det-item-${parseInt(item.id)}`;

            const infoDiv = document.createElement('div');
            infoDiv.style.flex = '1';

            const nombreDiv = document.createElement('div');
            nombreDiv.className = 'pedido-item-nombre';
            nombreDiv.textContent = `${parseInt(item.cantidad)}x ${item.nombre}`;
            infoDiv.appendChild(nombreDiv);

            if (item.opciones) {
                const opDiv = document.createElement('div');
                opDiv.className = 'pedido-item-opciones';
                opDiv.textContent = `· ${item.opciones}`;
                infoDiv.appendChild(opDiv);
            }
            if (item.notas) {
                const notaDiv = document.createElement('div');
                notaDiv.className = 'pedido-item-opciones';
                const noteIcon = document.createElement('i');
                noteIcon.className = 'fa-solid fa-note-sticky';
                notaDiv.appendChild(noteIcon);
                notaDiv.appendChild(document.createTextNode(` ${item.notas}`));
                infoDiv.appendChild(notaDiv);
            }

            const actionsDiv = document.createElement('div');
            actionsDiv.style.cssText = 'display:flex;align-items:center;gap:8px;';

            const precioDiv = document.createElement('div');
            precioDiv.className = 'pedido-item-precio';
            precioDiv.textContent = `S/ ${parseFloat(item.subtotal).toFixed(2)}`;

            const delBtn = document.createElement('button');
            delBtn.className = 'btn-del-item';
            delBtn.title = 'Eliminar plato';
            // IDs numéricos almacenados en data-* para leerlos en el listener
            delBtn.dataset.itemId   = parseInt(item.id);
            delBtn.dataset.pedidoId = parseInt(p.id);
            delBtn.dataset.mesaId   = parseInt(mesaId);
            delBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
            delBtn.addEventListener('click', function() {
                eliminarItemDetalle(
                    parseInt(this.dataset.itemId),
                    parseInt(this.dataset.pedidoId),
                    parseInt(this.dataset.mesaId)
                );
            });

            actionsDiv.appendChild(precioDiv);
            actionsDiv.appendChild(delBtn);
            itemDiv.appendChild(infoDiv);
            itemDiv.appendChild(actionsDiv);
            container.appendChild(itemDiv);
        });
    }

    // Total (datos numéricos del servidor — seguros en textContent)
    const totalDiv = document.createElement('div');
    totalDiv.className = 'pedido-total mt-12';
    totalDiv.id = 'det-total-row';
    const totalLabel = document.createElement('span');
    totalLabel.textContent = 'Total';
    const totalVal = document.createElement('span');
    totalVal.id = 'det-total-val';
    totalVal.style.color = 'var(--success)';
    totalVal.textContent = `S/ ${parseFloat(p.total).toFixed(2)}`;
    totalDiv.appendChild(totalLabel);
    totalDiv.appendChild(totalVal);
    container.appendChild(totalDiv);

    // Botones de acción (pedido_id y mesa_id son enteros del servidor)
    const botonesDiv = document.createElement('div');
    botonesDiv.style.cssText = 'display:flex;flex-direction:column;gap:8px;margin-top:16px;';
    const linkComanda = document.createElement('a');
    linkComanda.href = `<?= BASE_URL ?>/roles/atencion/comanda.php?pedido_id=${parseInt(p.id)}&mesa_id=${parseInt(mesaId)}`;
    linkComanda.className = 'btn btn-naranja btn-full';
    linkComanda.innerHTML = '<i class="fa-solid fa-plus"></i> Añadir más platos';
    const linkCobrar = document.createElement('a');
    linkCobrar.href = `<?= BASE_URL ?>/roles/atencion/cobrar.php?pedido_id=${parseInt(p.id)}`;
    linkCobrar.className = 'btn btn-exito btn-full';
    linkCobrar.innerHTML = '<i class="fa-solid fa-sack-dollar"></i> Cobrar pedido';
    botonesDiv.appendChild(linkComanda);
    botonesDiv.appendChild(linkCobrar);
    container.appendChild(botonesDiv);

    // Limpiar e insertar (un único DOM write)
    const modalBody = document.getElementById('modal-detalle-body');
    modalBody.innerHTML = '';
    modalBody.appendChild(container);
}

async function eliminarItemDetalle(itemId, pedidoId, mesaId) {
    Modal.cerrar('modal-detalle');
    setTimeout(() => {
        confirmar('¿Eliminar este plato del pedido?', async () => {
            try {
                const res  = await fetch(BASE_URL + '/api/eliminar_item.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({ item_id: itemId })
                });
                const json = await res.json();
                if (json.success) {
                    Toast.exito('Plato eliminado');
                    setTimeout(() => verDetalle(pedidoId, mesaId), 400);
                    refreshMesaCard(mesaId);
                } else {
                    Toast.error(json.message);
                    setTimeout(() => Modal.abrir('modal-detalle'), 300);
                }
            } catch(e) {
                Toast.error('Error de conexión');
                setTimeout(() => Modal.abrir('modal-detalle'), 300);
            }
        }, () => { setTimeout(() => Modal.abrir('modal-detalle'), 200); });
    }, 200);
}

async function refreshMesaCard(mesaId) {
    try {
        const res  = await fetch(BASE_URL + '/api/get_mesas.php');
        const json = await res.json();
        if (!json || !json.data) return;
        const m = json.data.find(x => x.id == mesaId);
        if (!m) return;
        const card = document.querySelector(`.mesa-map-card[data-id="${mesaId}"]`);
        if (!card) return;
        const estEl = card.querySelector('.map-est');
        if (estEl) estEl.textContent = m.pedido_id ? `S/ ${parseFloat(m.total).toFixed(2)}` : m.estado.toUpperCase();
        card.className = `mesa-map-card ${m.activo ? m.estado : 'inactiva'}`;
        let badge = card.querySelector('.map-badge');
        if (m.pedido_id && m.num_items > 0) {
            if (!badge) { badge = document.createElement('div'); badge.className = 'map-badge'; card.appendChild(badge); }
            badge.innerHTML = `<i class="fa-solid fa-utensils"></i> ${m.num_items}`;
        } else if (badge) badge.remove();
        card.onclick = m.activo ? () => clickMesa(m) : () => Toast.advertencia('Mesa inactiva');
    } catch(e) {}
}

/* ── Gestión CRUD de mesas ── */
let modoEdicion = false;

function editarMesa(mesa) {
    modoEdicion = true;
    document.getElementById('modal-mesa-titulo-form').innerHTML = `<i class="fa-solid fa-pen"></i> Editar Mesa ${mesa.numero}`;
    document.getElementById('mesa-id').value        = mesa.id;
    document.getElementById('mesa-numero').value    = mesa.numero;
    document.getElementById('mesa-capacidad').value = mesa.capacidad;
    document.getElementById('mesa-activo').value    = mesa.activo;
    Modal.abrir('modal-nueva-mesa');
}

document.getElementById('btn-nueva-mesa').addEventListener('click', function() {
    modoEdicion = false;
    document.getElementById('modal-mesa-titulo-form').innerHTML = '<i class="fa-solid fa-plus"></i> Nueva Mesa';
    document.getElementById('form-mesa').reset();
    document.getElementById('mesa-id').value        = '';
    document.getElementById('mesa-capacidad').value = '4';
});

async function guardarMesa() {
    const btn = document.getElementById('btn-guardar-mesa');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';

    const datos = {
        accion:    modoEdicion ? 'editar' : 'crear',
        id:        document.getElementById('mesa-id').value,
        numero:    document.getElementById('mesa-numero').value,
        capacidad: document.getElementById('mesa-capacidad').value,
        activo:    document.getElementById('mesa-activo').value,
    };

    try {
        const res  = await fetch(BASE_URL + '/api/admin_mesas.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(datos)
        });
        const json = await res.json();
        if (json.success) {
            Toast.exito(json.message);
            Modal.cerrar('modal-nueva-mesa');
            setTimeout(() => location.reload(), 800);
        } else Toast.error(json.message);
    } catch(e) { Toast.error('Error de conexión'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar';
    }
}

function eliminarMesa(id, numero) {
    confirmar(`¿Eliminar la Mesa ${numero}? Esta acción no se puede deshacer.`, async () => {
        try {
            const res  = await fetch(BASE_URL + '/api/admin_mesas.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({ accion: 'eliminar', id })
            });
            const json = await res.json();
            if (json.success) { Toast.exito(json.message); setTimeout(() => location.reload(), 700); }
            else Toast.error(json.message);
        } catch(e) { Toast.error('Error de conexión'); }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
