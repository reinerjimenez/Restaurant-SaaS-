<?php
/**
 * roles/cocina/dashboard.php — Vista de cocina en tiempo real
 * MESORA | Sistema SaaS para restaurantes
 */
require_once '../../config/db.php';
requireRole(['cocina', 'admin', 'encargado', 'superadmin']);

$restauranteId = $_SESSION['restaurante_id'];
$pageTitle     = 'Panel Cocina';
require_once '../../includes/header.php';
?>

<style>
/* Forzar header oscuro en cocina */
.navbar { background: #0F0F1A; border-bottom: none; }

/* Ajustar colores de texto para el estado vacío ahora que el fondo es blanco */
.cocina-vacia { color: var(--text-secondary); }
.cocina-vacia .icon { color: var(--border); }
.cocina-config { position:fixed; z-index:1200; right:16px; top:72px; width:min(320px,calc(100vw - 32px)); padding:18px; background:#fff; color:#172033; border:1px solid #cbd5e1; border-radius:12px; box-shadow:0 12px 36px #0003; }
.cocina-config[hidden] { display:none; }
.cocina-config button,.cocina-config select { min-height:44px; }
/* La tarjeta y sus controles crecen junto con la tipografía. */
body { --ck-card-width: 180px; --ck-space: 4px; --ck-gap: 10px; --ck-qty-size: 18px; --ck-icon-box: 24px; --ck-icon-font: .75rem; --ck-font: .7rem; --ck-mesa-font: .85rem; --ck-secondary-font: .6rem; --ck-tipo-font: .72rem; --ck-time-font: .6rem; --ck-button-font: .75rem; }
body.cocina-lectura-grande { --ck-card-width: 240px; --ck-space: 9px; --ck-gap: 13px; --ck-qty-size: 30px; --ck-icon-box: 28px; --ck-icon-font: .85rem; --ck-font: 1.05rem; --ck-mesa-font: 1.05rem; --ck-secondary-font: .85rem; --ck-tipo-font: .96rem; --ck-time-font: .9rem; --ck-button-font: .95rem; }
body.cocina-lectura-extra { --ck-card-width: 310px; --ck-space: 14px; --ck-gap: 16px; --ck-qty-size: 38px; --ck-icon-box: 36px; --ck-icon-font: 1.05rem; --ck-font: 1.35rem; --ck-mesa-font: 1.25rem; --ck-secondary-font: 1.05rem; --ck-tipo-font: 1.13rem; --ck-time-font: 1.15rem; --ck-button-font: 1.15rem; }
.cocina-grid { gap: var(--ck-gap); }
.cocina-card { flex: 0 0 min(var(--ck-card-width), calc(100vw - 40px)); }
.cocina-card-header { padding: var(--ck-space); gap: var(--ck-space); }
.ck-items { padding: var(--ck-space); }
.ck-item { align-items: center; gap: var(--ck-space); padding: var(--ck-space) 0; }
.ck-item-info { min-width: 0; overflow-wrap: anywhere; }
.ck-qty { width: var(--ck-qty-size); height: var(--ck-qty-size); font-size: var(--ck-secondary-font); }
.ck-mesa { font-size: var(--ck-mesa-font); }
.ck-tipo span { font-size: var(--ck-secondary-font) !important; }
.ck-tipo .ck-etiqueta-tipo { display:inline-flex; align-items:center; gap:3px; padding:2px 5px; border-radius:4px; font-size:var(--ck-tipo-font) !important; font-weight:700; line-height:1.15; white-space:nowrap; color:#fff; background:#253149; }
.ck-tipo .ck-etiqueta-tipo.aqui { background:#17445a; }
.ck-btn-listo { position:relative; display:inline-flex; align-items:center; justify-content:center; min-width:44px; min-height:44px; padding:0; background:transparent !important; }
.ck-btn-listo::before { content:""; position:absolute; width:var(--ck-icon-box); height:var(--ck-icon-box); border-radius:4px; background:var(--warning-bg); }
.ck-btn-listo.listo::before { background:var(--success-bg); }
.ck-btn-listo i { position:relative; font-size:var(--ck-icon-font); }
.ck-card-footer { padding: var(--ck-space) !important; }
.ck-card-footer .ck-btn { position:relative; isolation:isolate; min-height:44px; padding:var(--ck-space) !important; font-size:var(--ck-button-font) !important; background:transparent !important; }
.ck-card-footer .ck-btn::before { content:""; position:absolute; inset:2px 0; z-index:-1; border-radius:6px; background:var(--warning-bg); }
.ck-card-footer .ck-btn.listo::before { background:var(--success-bg); }
body.cocina-palette-suave .cocina-header, body.cocina-palette-suave .cocina-card, body.cocina-palette-suave .cocina-card-header { background:#263746; border-color:#526879; }
body.cocina-palette-suave .cocina-card.urgente .cocina-card-header { background:#694b43; }
body.cocina-palette-suave .ck-nombre { color:#fff; }
.ck-nombre { font-size:var(--ck-font, 1rem) !important; }
.ck-opciones,.ck-tipo { font-size:var(--ck-secondary-font) !important; color:#e2e8f0 !important; }
.ck-tiempo { font-size:var(--ck-time-font) !important; padding: calc(var(--ck-space) / 2) var(--ck-space); }

/* ── Fullscreen: clase aplicada por JS al body ── */
body.cocina-fs-activo .navbar         { display: none !important; }
body.cocina-fs-activo .sidebar        { display: none !important; }
body.cocina-fs-activo .sidebar-overlay{ display: none !important; }
body.cocina-fs-activo .main-content   {
    margin-left: 0 !important;
    padding: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
}

/* Botón fullscreen */
#btn-fullscreen {
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.3);
    color: white;
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: background 0.2s, border-color 0.2s;
    flex-shrink: 0;
    white-space: nowrap;
}
#btn-fullscreen:hover {
    background: rgba(255,255,255,.2);
    border-color: rgba(255,255,255,.5);
}
#btn-fullscreen .fs-label { display: none; }
@media (min-width: 768px) {
    #btn-fullscreen {
        width: auto;
        padding: 0 10px;
    }
    #btn-fullscreen .fs-label { display: inline; font-size: 0.8rem; }
}
</style>

<!-- Audio de alerta -->
<audio id="timbre-audio" preload="auto">
    <source src="<?= BASE_URL ?>/assets/timbre.mp3" type="audio/mpeg">
</audio>

<!-- Cocina Header personalizada -->
<div class="cocina-header">
    <div>
        <div style="display:flex; align-items:center; gap: 12px;">
            <h1><i class="fa-solid fa-kitchen-set"></i> Panel de Cocina</h1>
            <button id="btn-activar-audio" class="btn btn-sm" style="background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.3); color:white; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; cursor:pointer; transition:all 0.2s;" onclick="activarAudio()">
                <i class="fa-solid fa-volume-xmark"></i> Activar Sonido
            </button>
            <button class="btn btn-sm" style="min-height:44px" onclick="document.getElementById('cocina-config').hidden = !document.getElementById('cocina-config').hidden"><i class="fa-solid fa-sliders"></i> Configuración</button>
            <button id="btn-fullscreen" title="Pantalla completa" onclick="toggleFullscreen()">
                <i class="fa-solid fa-expand" id="ico-fullscreen"></i>
                <span class="fs-label">Full Screen</span>
            </button>
        </div>
        <div style="font-size:.75rem;opacity:.7;margin-top:4px;" id="cocina-reloj"></div>
    </div>
    <div class="cocina-status">
        <div class="dot"></div>
        <span id="cocina-conexion">Conectando…</span>
        <span id="cocina-count" style="background:rgba(255,255,255,.15);border-radius:999px;padding:4px 12px;font-weight:700;"></span>
    </div>
</div>
<section id="cocina-config" class="cocina-config" hidden aria-label="Configuración de lectura">
    <h2>Lectura en cocina</h2>
    <label for="ck-tamano">Tamaño del texto</label>
    <select id="ck-tamano" class="form-control"><option value="normal">Normal</option><option value="grande">Grande</option><option value="extra">Extra grande</option></select>
    <label for="ck-colores">Colores</label>
    <select id="ck-colores" class="form-control"><option value="vivos">Estándar</option><option value="suave">Menos saturados</option></select>
</section>

<div id="cocina-contenido" style="padding: 4px 16px 16px; height: calc(100vh - 110px); display: flex; flex-direction: column; overflow: hidden;">
    <div class="cocina-vacia">
        <div class="icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <p>Cargando pedidos...</p>
    </div>
</div>

<script>
const RESTAURANTE_ID = <?= $restauranteId ?>;

let estadoPrevio = null;
let audioActivado = false;
let cocinaUltimoExito = 0;
const preferenciasKey = `mesora:cocina:${RESTAURANTE_ID}`;
function aplicarPreferenciasCocina() {
    let p = {}; try { p = JSON.parse(localStorage.getItem(preferenciasKey) || '{}'); } catch (_) {}
    document.body.classList.toggle('cocina-lectura-grande', p.tamano === 'grande');
    document.body.classList.toggle('cocina-lectura-extra', p.tamano === 'extra');
    document.body.classList.toggle('cocina-palette-suave', p.colores === 'suave');
    document.getElementById('ck-tamano').value = p.tamano || 'normal';
    document.getElementById('ck-colores').value = p.colores || 'vivos';
}
function guardarPreferenciasCocina() {
    try { localStorage.setItem(preferenciasKey, JSON.stringify({tamano:document.getElementById('ck-tamano').value,colores:document.getElementById('ck-colores').value})); } catch (_) {}
    aplicarPreferenciasCocina();
}
document.getElementById('ck-tamano').addEventListener('change', guardarPreferenciasCocina);
document.getElementById('ck-colores').addEventListener('change', guardarPreferenciasCocina);
aplicarPreferenciasCocina();

function activarAudio() {
    const audio = document.getElementById('timbre-audio');
    if (!audio) return;
    audio.play().then(() => {
        audio.pause();
        audio.currentTime = 0;
        audioActivado = true;
        const btn = document.getElementById('btn-activar-audio');
        if (btn) {
            btn.innerHTML = '<i class="fa-solid fa-volume-high"></i> Sonido Activado';
            btn.style.borderColor = 'var(--success)';
            btn.style.color = 'var(--success)';
            btn.style.background = 'rgba(40, 167, 69, 0.1)';
        }
        if (typeof Toast !== 'undefined') Toast.exito('Alertas sonoras activadas');
    }).catch(e => console.error('Error al activar audio:', e));
}

function reproducirTimbre() {
    if (!audioActivado) return;
    const audio = document.getElementById('timbre-audio');
    if (!audio) return;
    audio.currentTime = 0;
    audio.play().catch(() => {
        audioActivado = false;
        const btn = document.getElementById('btn-activar-audio');
        if(btn) {
            btn.innerHTML = '<i class="fa-solid fa-volume-xmark"></i> Activar Sonido';
            btn.style.borderColor = 'var(--danger)';
            btn.style.color = 'var(--danger)';
        }
    });
}

function detectarCambiosYAlertar(pedidos) {
    if (estadoPrevio === null) {
        estadoPrevio = construirSnapshot(pedidos);
        return;
    }
    const nuevoSnapshot = construirSnapshot(pedidos);
    let hayNovedad = false;
    for (const [id, count] of nuevoSnapshot) {
        if (!estadoPrevio.has(id)) { hayNovedad = true; break; }
        if (count > estadoPrevio.get(id)) { hayNovedad = true; break; }
    }
    if (hayNovedad) reproducirTimbre();
    estadoPrevio = nuevoSnapshot;
}

function construirSnapshot(pedidos) {
    const map = new Map();
    (pedidos || []).forEach(p => map.set(p.id, (p.items || []).length));
    return map;
}

function actualizarReloj() {
    const el = document.getElementById('cocina-reloj');
    if (el) el.textContent = new Date().toLocaleTimeString('es-PE');
}
actualizarReloj();
setInterval(actualizarReloj, 1000);

function renderPedidos(pedidos) {
    const cont  = document.getElementById('cocina-contenido');
    const count = document.getElementById('cocina-count');

    detectarCambiosYAlertar(pedidos);
    cocinaUltimoExito = Date.now();
    document.getElementById('cocina-conexion').textContent = 'Conectado · actualizado ahora';

    if (!pedidos || pedidos.length === 0) {
        count.textContent = '0 pedidos';
        cont.innerHTML = `
            <div class="cocina-vacia">
                <div class="icon"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i></div>
                <p>No hay pedidos activos — ¡Todo listo!</p>
            </div>`;
        return;
    }

    count.textContent = `${pedidos.length} pedido(s)`;

    const grid = document.createElement('div');
    grid.className = 'cocina-grid';

    // Invertimos el orden para mostrar las mesas nuevas primero
    const pedidosMostrados = [...pedidos].reverse();

    pedidosMostrados.forEach(p => {
        const minutos = Math.max(0, Math.floor(((Date.now() - cocinaUltimoExito) / 1000 + (parseInt(p.segundos_transcurridos) || parseInt(p.minutos_transcurridos) * 60)) / 60));
        const urgente = minutos >= 20;

        const card = document.createElement('div');
        card.className = `cocina-card${urgente ? ' urgente' : ''}`;
        card.id = `ck-pedido-${p.id}`;
        card.dataset.segundos = parseInt(p.segundos_transcurridos) || 0;
        card.dataset.recibido = cocinaUltimoExito;

        const todosLosIds = (p.items || []).map(i => i.id);
        const todosListos = (p.items || []).length > 0 && (p.items || []).every(i => i.estado === 'listo');
        const todosLosIdsJson = JSON.stringify(todosLosIds);

        const grupos = agruparItems(p.items || []);
        // — XSS: Construir ítems de cocina con DOM (nombre, opciones y notas son datos de usuario) —
        const ckItemsContainer = document.createElement('div');
        ckItemsContainer.className = 'ck-items';

        grupos.forEach(grupo => {
            const estadoClass = grupo.estado || 'pendiente';
            const idsArray    = JSON.parse(JSON.stringify(grupo.ids)); // copia limpia de enteros

            const ckItem = document.createElement('div');
            ckItem.className = 'ck-item';

            const qtyDiv = document.createElement('div');
            qtyDiv.className = 'ck-qty';
            qtyDiv.textContent = grupo.cantidad;

            const infoDiv = document.createElement('div');
            infoDiv.className = 'ck-item-info';

            const nombreDiv = document.createElement('div');
            nombreDiv.className = 'ck-nombre';
            nombreDiv.textContent = grupo.nombre;         // textContent — sin XSS
            infoDiv.appendChild(nombreDiv);

            if (grupo.opciones) {
                const opDiv = document.createElement('div');
                opDiv.className = 'ck-opciones';
                opDiv.textContent = `· ${grupo.opciones}`; // textContent — sin XSS
                infoDiv.appendChild(opDiv);
            }
            if (grupo.notas) {
                const notaDiv = document.createElement('div');
                notaDiv.className = 'ck-opciones';
                const noteIcon = document.createElement('i');
                noteIcon.className = 'fa-solid fa-note-sticky';
                notaDiv.appendChild(noteIcon);
                notaDiv.appendChild(document.createTextNode(` ${grupo.notas}`)); // sin XSS
                infoDiv.appendChild(notaDiv);
            }

            const listoBtn = document.createElement('button');
            listoBtn.className = `ck-btn-listo ck-btn ${estadoClass === 'listo' ? 'listo' : 'pendiente'}`;
            listoBtn.title = estadoClass === 'listo'
                ? 'Listo (click para ocultar)'
                : 'Pendiente (click para marcar listo)';
            listoBtn.innerHTML = estadoClass === 'listo'
                ? '<i class="fa-solid fa-circle-check" style="color:var(--success)"></i>'
                : '<i class="fa-solid fa-hourglass-half"></i>';
            // IDs son enteros del servidor — seguros; estado es enum del servidor
            listoBtn.dataset.ids    = JSON.stringify(idsArray);
            listoBtn.dataset.estado = estadoClass;
            listoBtn.addEventListener('click', function() {
                cambiarEstadoItem(this, JSON.parse(this.dataset.ids), this.dataset.estado);
            });

            ckItem.appendChild(qtyDiv);
            ckItem.appendChild(infoDiv);
            ckItem.appendChild(listoBtn);
            ckItemsContainer.appendChild(ckItem);
        });

        // Cabecera de la card: mesa_numero e id son enteros del servidor; tipo es enum
        card.innerHTML = `
            <div class="cocina-card-header">
                <div>
                    <div class="ck-mesa">${p.mesa_numero ? 'Mesa ' + parseInt(p.mesa_numero) : 'Llevar'}</div>
                    <div class="ck-tipo" style="margin-top:2px;">
                        <span class="ck-etiqueta-tipo ${p.tipo === 'aqui' ? 'aqui' : 'llevar'}">
                            <i class="fa-solid ${p.tipo === 'aqui' ? 'fa-house' : 'fa-bag-shopping'}"></i>
                            ${p.tipo === 'aqui' ? 'Comer aquí' : 'Para llevar'}
                        </span>
                        <span style="opacity: 0.7; margin-left: 4px; font-size: 0.6rem;">· #${parseInt(p.id)}</span>
                    </div>
                </div>
                <div class="ck-tiempo">${parseInt(minutos)} min${urgente ? ' <i class="fa-solid fa-triangle-exclamation"></i>' : ''}</div>
            </div>`;

        // Insertar ítems (DOM seguro) después del header
        card.appendChild(ckItemsContainer);

        // Footer: botón "marcar todo / despachar" — IDs son enteros, estado es enum
        const footer = document.createElement('div');
        footer.className = 'ck-card-footer';
        footer.style.cssText = 'padding: 6px; background: rgba(0,0,0,0.25); border-top: 1px solid rgba(255,255,255,0.06); flex-shrink: 0;';

        const todoBtn = document.createElement('button');
        todoBtn.className = `ck-btn ${todosListos ? 'listo' : 'pendiente'}`;
        todoBtn.style.cssText = 'width: 100%; padding: 6px 4px; font-size: 0.75rem; border-radius: 6px; cursor: pointer; border: none; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;';
        todoBtn.title = todosListos
            ? 'Despachar todos los platos de esta comanda'
            : 'Marcar todos los platos de esta comanda como listos';
        todoBtn.dataset.ids    = JSON.stringify(todosLosIds);
        todoBtn.dataset.estado = todosListos ? 'listo' : 'pendiente';
        todoBtn.innerHTML = `<i class="fa-solid ${todosListos ? 'fa-circle-check' : 'fa-check-double'}"></i>
                             ${todosListos ? 'Despachar Comanda' : 'Marcar Todo Listo'}`;
        todoBtn.addEventListener('click', function() {
            cambiarEstadoItem(this, JSON.parse(this.dataset.ids), this.dataset.estado);
        });

        footer.appendChild(todoBtn);
        card.appendChild(footer);

        grid.appendChild(card);
    });

    cont.innerHTML = '';
    cont.appendChild(grid);
}

function agruparItems(items) {
    const grupos = new Map();
    items.forEach(item => {
        const key = [item.nombre, item.estado || 'pendiente', item.opciones || '', item.notas || ''].join('||');
        if (grupos.has(key)) {
            const g = grupos.get(key);
            g.cantidad += (item.cantidad || 1);
            g.ids.push(item.id);
        } else {
            grupos.set(key, { ...item, cantidad: item.cantidad || 1, ids: [item.id] });
        }
    });
    return Array.from(grupos.values());
}

async function cambiarEstadoItem(btn, ids, estadoActual) {
    // Transición simplificada: pendiente -> listo -> entregado (desaparece)
    const siguiente = (estadoActual === 'listo') ? 'entregado' : 'listo';
    const itemIds   = Array.isArray(ids) ? ids : [ids];
    
    // Feedback visual inmediato
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const res  = await fetch(BASE_URL + '/api/marcar_item_listo.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ item_ids: itemIds, estado: siguiente })
        });
        const json = await res.json();
        if (!json.success) {
            Toast.advertencia(json.message);
            btn.disabled = false;
        } else {
            // Refrescar inmediatamente el panel completo
            cargarPedidosAhora();
        }
    } catch(e) {
        btn.disabled = false;
    }
}

let consultaEnCurso = false;
async function cargarPedidosAhora() {
    if (consultaEnCurso) return;
    consultaEnCurso = true;
    try {
        const r = await fetch(`${BASE_URL}/api/get_pedidos_activos.php`, {cache:'no-store', headers:{'X-Requested-With':'XMLHttpRequest'}});
        if (r.status === 401) { window.location.href = `${BASE_URL}/index.php`; return; }
        const j = await r.json();
        if (!r.ok || !j.success) throw new Error(j.message || 'No se pudo actualizar');
        renderPedidos(j.data);
    } catch (e) {
        const badge = document.getElementById('cocina-conexion');
        badge.textContent = 'Sin conexión · reintentando';
        badge.style.color = '#ffd0a8';
        console.warn('Fallo al actualizar cocina:', e);
    } finally { consultaEnCurso = false; }
}
setInterval(cargarPedidosAhora, 5000);
document.addEventListener('DOMContentLoaded', cargarPedidosAhora);
setInterval(() => { if (cocinaUltimoExito) renderizarMinutos(); }, 1000);
function renderizarMinutos() {
    document.querySelectorAll('.cocina-card').forEach(card => {
        const base = Number(card.dataset.segundos || 0);
        const elapsed = base + Math.floor((Date.now() - Number(card.dataset.recibido || cocinaUltimoExito)) / 1000);
        const min = Math.floor(elapsed / 60);
        const el = card.querySelector('.ck-tiempo');
        if (el) el.textContent = `${min} min`;
        card.classList.toggle('urgente', min >= 20);
    });
}
document.addEventListener('visibilitychange', () => { if (!document.hidden) cargarPedidosAhora(); });
window.addEventListener('online', cargarPedidosAhora);

/* ── Fullscreen API ── */
function aplicarModoFS(activo) {
    const ico   = document.getElementById('ico-fullscreen');
    const label = document.querySelector('#btn-fullscreen .fs-label');

    if (activo) {
        document.body.classList.add('cocina-fs-activo');
        if (ico)   { ico.classList.remove('fa-expand');   ico.classList.add('fa-compress'); }
        if (label) label.textContent = 'Salir';
    } else {
        document.body.classList.remove('cocina-fs-activo');
        if (ico)   { ico.classList.remove('fa-compress'); ico.classList.add('fa-expand'); }
        if (label) label.textContent = 'Full Screen';
    }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen()
            .then(() => aplicarModoFS(true))
            .catch(err => console.warn('Error fullscreen:', err));
    } else {
        document.exitFullscreen()
            .then(() => aplicarModoFS(false));
    }
}

// Detecta Escape u otras formas de salir del fullscreen
document.addEventListener('fullscreenchange', () => {
    aplicarModoFS(!!document.fullscreenElement);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
