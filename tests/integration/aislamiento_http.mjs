import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { writeFileSync } from 'node:fs';

// Solo contra la base desechable y el servidor HTTP con guardia de base.
const base = 'http://127.0.0.1:8098/MESORA';
const mysql = 'C:/xampp/mysql/bin/mysql.exe';
const baseEnsayo = 'mesora_aislamiento_test';
const clave = 'Test!aislamiento-2026';
const evidencias = [];

function sql(consulta) {
    return execFileSync(mysql, ['--no-defaults', '--host=127.0.0.1', '--port=3306', '--user=root',
        '--database=' + baseEnsayo, '--batch', '--skip-column-names', '--raw', '--execute=' + consulta],
    { encoding: 'utf8' }).trim().replace(/\r/g, '');
}
assert.equal(sql('SELECT DATABASE()'), baseEnsayo);
const guardia = await fetch('http://127.0.0.1:8098/__aislamiento_db_check');
assert.equal(await guardia.text(), baseEnsayo);

function huellaB() {
    return sql(`SELECT * FROM restaurantes WHERE id=2;
SELECT * FROM usuarios WHERE restaurante_id=2 ORDER BY id;
SELECT * FROM mesas WHERE restaurante_id=2 ORDER BY id;
SELECT * FROM categorias WHERE restaurante_id=2 ORDER BY id;
SELECT * FROM productos WHERE restaurante_id=2 ORDER BY id;
SELECT og.* FROM opciones_grupo og JOIN productos p ON p.id=og.producto_id WHERE p.restaurante_id=2 ORDER BY og.id;
SELECT ov.* FROM opciones_valor ov JOIN opciones_grupo og ON og.id=ov.grupo_id JOIN productos p ON p.id=og.producto_id WHERE p.restaurante_id=2 ORDER BY ov.id;
SELECT * FROM pedidos WHERE restaurante_id=2 ORDER BY id;
SELECT pi.* FROM pedido_items pi JOIN pedidos p ON p.id=pi.pedido_id WHERE p.restaurante_id=2 ORDER BY pi.id;
SELECT pio.* FROM pedido_item_opciones pio JOIN pedido_items pi ON pi.id=pio.item_id JOIN pedidos p ON p.id=pi.pedido_id WHERE p.restaurante_id=2 ORDER BY pio.id;
SELECT pa.* FROM pagos pa JOIN pedidos p ON p.id=pa.pedido_id WHERE p.restaurante_id=2 ORDER BY pa.id;
SELECT * FROM comprobantes WHERE restaurante_id=2 ORDER BY id;
SELECT * FROM turnos WHERE restaurante_id=2 ORDER BY id;`);
}

function cookieDe(respuesta, anterior = '') {
    return respuesta.headers.get('set-cookie')?.split(';')[0] ?? anterior;
}
async function cliente(email) {
    const inicio = await fetch(base + '/index.php');
    const html = await inicio.text();
    const csrf = html.match(/name="csrf_token" value="([^"]+)"/)?.[1];
    assert.equal(inicio.status, 200);
    assert.ok(csrf);
    let cookie = cookieDe(inicio);
    const respuesta = await fetch(base + '/auth/login.php', {
        method: 'POST', redirect: 'manual', headers: { cookie, 'content-type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ email, password: clave, csrf_token: csrf }),
    });
    assert.equal(respuesta.status, 303, `Login ${email}`);
    assert.notEqual(respuesta.headers.get('location'), '/MESORA/index.php', `Login ${email}`);
    cookie = cookieDe(respuesta, cookie);
    const panel = await fetch('http://127.0.0.1:8098' + respuesta.headers.get('location'), { headers: { cookie } });
    const panelHtml = await panel.text();
    const csrfNuevo = panelHtml.match(/window\.CSRF_TOKEN = "([^"]+)"/)?.[1];
    assert.equal(panel.status, 200, `Panel ${email}`);
    assert.ok(csrfNuevo, `CSRF del panel ${email}`);
    return { cookie: cookieDe(panel, cookie), csrf: csrfNuevo, contexto: null };
}
async function pedir(c, ruta, cuerpo = undefined, tipo = 'json') {
    const post = cuerpo !== undefined;
    const headers = { cookie: c.cookie };
    if (c.contexto) {
        headers['X-Restaurante-Esperado'] = String(c.contexto.id);
        headers['X-Contexto-Restaurante'] = c.contexto.token;
    }
    if (post) {
        headers['X-CSRF-Token'] = c.csrf;
        headers['Content-Type'] = tipo === 'json' ? 'application/json' : 'application/x-www-form-urlencoded';
    }
    const respuesta = await fetch((c.base ?? base) + ruta, {
        method: post ? 'POST' : 'GET', headers, redirect: 'manual',
        body: post ? (tipo === 'json' ? JSON.stringify(cuerpo) : new URLSearchParams(cuerpo)) : undefined,
    });
    const texto = await respuesta.text();
    c.cookie = cookieDe(respuesta, c.cookie);
    let json = null;
    try { json = JSON.parse(texto); } catch { /* La respuesta puede ser HTML. */ }
    return { status: respuesta.status, json, texto, location: respuesta.headers.get('location') };
}
function registrar(categoria, nombre, ruta, metodo, carga, resultado, paso) {
    evidencias.push({ categoria, nombre, peticion: { metodo, ruta, carga },
        respuesta: { status: resultado.status, success: resultado.json?.success ?? null,
            message: resultado.json?.message ?? resultado.location ?? resultado.texto.slice(0, 140) }, paso });
}
async function ataqueB(categoria, nombre, c, ruta, carga) {
    const antes = huellaB();
    const r = await pedir(c, ruta, carga);
    const despues = huellaB();
    const paso = antes === despues && r.json?.success !== true;
    registrar(categoria, nombre, ruta, 'POST', carga, r, paso);
    if (antes !== despues) throw new Error(`FUGA DE ESCRITURA EN B: ${nombre}`);
    return r;
}
function id(consulta) { return Number(sql(consulta)); }
const aAdmin = await cliente('admin.A@aislamiento.test');
const aAtencion = await cliente('atencion.A@aislamiento.test');
const aEncargado = await cliente('encargado.A@aislamiento.test');
const aCocina = await cliente('cocina.A@aislamiento.test');
const bAdmin = await cliente('admin.B@aislamiento.test');
const bAtencion = await cliente('atencion.B@aislamiento.test');

let pedidoBId = id('SELECT COALESCE(MAX(id),0) FROM pedidos WHERE restaurante_id=2 AND estado="activo"');
if (!pedidoBId) {
    const pedidoB = await pedir(bAtencion, '/api/crear_pedido.php', { mesa_id: 3, tipo: 'aqui' });
    assert.equal(pedidoB.json?.success, true, pedidoB.json?.message);
    pedidoBId = Number(pedidoB.json.data.pedido_id);
}
let itemBId = id(`SELECT COALESCE(MAX(id),0) FROM pedido_items WHERE pedido_id=${pedidoBId}`);
if (!itemBId) {
    const itemB = await pedir(bAtencion, '/api/agregar_item.php', { pedido_id: pedidoBId,
        operacion_id: randomBytes(16).toString('hex'), items: [{ producto_id: 3, cantidad: 1,
            selecciones: [{ grupo_id: 3, valor_id: 3 }] }] });
    assert.equal(itemB.json?.success, true, itemB.json?.message);
    itemBId = id(`SELECT id FROM pedido_items WHERE pedido_id=${pedidoBId} LIMIT 1`);
}
let pedidoAId = id('SELECT COALESCE(MAX(id),0) FROM pedidos WHERE restaurante_id=1 AND estado="activo"');
if (!pedidoAId) {
    const pedidoA = await pedir(aAtencion, '/api/crear_pedido.php', { mesa_id: 1, tipo: 'aqui' });
    assert.equal(pedidoA.json?.success, true);
    pedidoAId = Number(pedidoA.json.data.pedido_id);
}
let pedidoBCobradoId = id('SELECT COALESCE(MAX(id),0) FROM pedidos WHERE restaurante_id=2 AND estado="cobrado"');
if (!pedidoBCobradoId) {
    const nuevo = await pedir(bAtencion, '/api/crear_pedido.php', { mesa_id: 0, tipo: 'llevar' });
    assert.equal(nuevo.json?.success, true, nuevo.json?.message);
    pedidoBCobradoId = Number(nuevo.json.data.pedido_id);
    const agregar = await pedir(bAtencion, '/api/agregar_item.php', { pedido_id: pedidoBCobradoId,
        operacion_id: randomBytes(16).toString('hex'), items: [{ producto_id: 4, cantidad: 1,
            selecciones: [{ grupo_id: 4, valor_id: 4 }] }] });
    assert.equal(agregar.json?.success, true, agregar.json?.message);
    const cobrar = await pedir(bAdmin, '/api/guardar_comprobante.php', { pedido_id: pedidoBCobradoId,
        tipo: 'simple', pagos: [{ metodo: 'efectivo', monto: '10.00' }] });
    assert.equal(cobrar.json?.success, true, cobrar.json?.message);
}
const comprobanteBId = id(`SELECT id FROM comprobantes WHERE pedido_id=${pedidoBCobradoId} ORDER BY id DESC LIMIT 1`);

// 1. Lecturas cruzadas con IDs existentes de B.
for (const [nombre, ruta, marcador] of [
    ['pedido B', `/api/get_pedido_detalle.php?id=${pedidoBId}`, 'items'],
    ['producto y opciones B', '/api/get_opciones.php?producto_id=3', 'Grupo B 1 TEST'],
    ['comprobante B', `/roles/admin/ver_comprobante.php?id=${comprobanteBId}`, 'Comprobante'],
    ['turno B', '/roles/admin/turno_detalle.php?id=2', 'Detalles del Turno #2'],
]) {
    const controlB = await pedir(bAdmin, ruta);
    const r = await pedir(aAdmin, ruta);
    const controlVisible = controlB.status === 200 && (nombre === 'pedido B'
        ? controlB.json?.success === true : controlB.texto.includes(marcador));
    const paso = controlVisible && !r.texto.includes('Producto B 1 TEST')
        && !r.texto.includes('Producto B 2 TEST')
        && (r.json?.success === false || r.status === 303 || r.status === 302);
    registrar(1, nombre, ruta, 'GET', null, r, paso);
}

// 2. Escrituras cruzadas: cada intento compara todas las filas de B.
const intentos = [
    ['mesa B en pedido', aAtencion, '/api/crear_pedido.php', { mesa_id: 4, tipo: 'aqui' }],
    ['agregar ítem a pedido B', aAtencion, '/api/agregar_item.php', { pedido_id: pedidoBId,
        operacion_id: randomBytes(16).toString('hex'), items: [{ producto_id: 1, cantidad: 1, selecciones: [] }] }],
    ['cobrar pedido B', aAdmin, '/api/cobrar_pedido.php', { pedido_id: pedidoBId, pagos: [{ metodo: 'efectivo', monto: '10.00' }] }],
    ['cancelar pedido B', aAdmin, '/api/cancelar_pedido.php', { id: pedidoBId }],
    ['eliminar ítem B', aAdmin, '/api/eliminar_item.php', { item_id: itemBId }],
    ['marcar ítem B listo', aCocina, '/api/marcar_item_listo.php', { item_id: itemBId, estado: 'listo' }],
    ['editar producto B', aAdmin, '/api/admin_productos.php', { accion: 'editar', id: 3, categoria_id: 1,
        nombre: 'ATAQUE', precio: 10, activo: 1, tiene_opciones: 0 }],
    ['crear producto con categoría B', aAdmin, '/api/admin_productos.php', { accion: 'crear', categoria_id: 2,
        nombre: 'ATAQUE', precio: 10, activo: 1, tiene_opciones: 0 }],
    ['editar mesa B', aAdmin, '/api/admin_mesas.php', { accion: 'editar', id: 4, numero: 90, capacidad: 4, activo: 1 }],
    ['editar categoría B', aAdmin, '/api/admin_categorias.php', { accion: 'editar', id: 2, nombre: 'ATAQUE', activo: 1 }],
    ['eliminar mesa B', aAdmin, '/api/admin_mesas.php', { accion: 'eliminar', id: 4 }],
    ['eliminar producto B', aAdmin, '/api/admin_productos.php', { accion: 'eliminar', id: 4 }],
    ['editar usuario B', aAdmin, '/api/admin_usuarios.php', { accion: 'editar', id: 6,
        nombre: 'ATAQUE', email: 'admin.B@aislamiento.test', rol: 'encargado', activo: 1 }],
    ['reabrir venta B', aAdmin, '/api/reabrir_venta.php', { id: pedidoBCobradoId }],
    ['eliminar venta B', aAdmin, '/api/eliminar_venta.php', { id: pedidoBCobradoId }],
    ['comprobante para pedido B', aAdmin, '/api/guardar_comprobante.php', { pedido_id: pedidoBId,
        tipo: 'simple', pagos: [{ metodo: 'efectivo', monto: '10.00' }] }],
    ['anular comprobante B', aAdmin, '/api/anular_comprobante.php', { comprobante_id: comprobanteBId, motivo: 'Prueba cruzada' }],
    ['disponibilidad B', aCocina, '/api/cocina_disponibilidad.php', { producto_id: 3, sin_stock: true }],
    ['cerrar turno B', aAdmin, '/api/cerrar_turno.php', { turno_id: id('SELECT id FROM turnos WHERE restaurante_id=2 AND fin IS NULL LIMIT 1'), reabrir: false }],
];
for (const [nombre, c, ruta, carga] of intentos) await ataqueB(2, nombre, c, ruta, carga);

// 3. Opciones de B contra pedido/producto de A.
for (const [nombre, ruta, carga] of [
    ['opción B en pedido A', '/api/agregar_item.php', { pedido_id: pedidoAId,
        operacion_id: randomBytes(16).toString('hex'), items: [{ producto_id: 1, cantidad: 1,
            selecciones: [{ grupo_id: 3, valor_id: 3 }] }] }],
    ['grupo B en producto A', '/api/admin_productos.php', { accion: 'editar', id: 1, categoria_id: 1,
        nombre: 'Producto A 1 TEST', precio: 10, activo: 1, tiene_opciones: 1,
        grupos: [{ id: 3, nombre: 'Grupo B 1 TEST', valores: ['Valor B 1 TEST'] }] }],
]) {
    const antes = sql(`SELECT * FROM pedido_item_opciones ORDER BY id; SELECT * FROM opciones_grupo ORDER BY id;`);
    const r = await pedir(aAdmin, ruta, carga);
    const despues = sql(`SELECT * FROM pedido_item_opciones ORDER BY id; SELECT * FROM opciones_grupo ORDER BY id;`);
    registrar(3, nombre, ruta, 'POST', carga, r, antes === despues && r.json?.success === false);
}

// 4. Roles comprobados por el servidor.
for (const [nombre, c, ruta, carga] of [
    ['cocina intenta cobrar', aCocina, '/api/cobrar_pedido.php', { pedido_id: pedidoBId, pagos: [{ metodo: 'efectivo', monto: '10.00' }] }],
    ['encargado intenta reabrir', aEncargado, '/api/reabrir_venta.php', { id: pedidoBId }],
    ['encargado edita admin', aEncargado, '/api/admin_usuarios.php', { accion: 'editar', id: 2,
        nombre: 'Admin A TEST', email: 'admin.A@aislamiento.test', rol: 'admin', activo: 1 }],
]) {
    const r = await pedir(c, ruta, carga);
    registrar(4, nombre, ruta, 'POST', carga, r, r.status === 403 && r.json?.success === false);
}

// 5. El formulario del superadmin conserva el contexto de A tras cambiar a B.
const superadmin = await cliente('super@aislamiento.test');
const seleccionarA = await pedir(superadmin, '/auth/seleccionar_restaurante.php',
    { restaurante_id: 1, csrf_token: superadmin.csrf }, 'form');
assert.equal(seleccionarA.status, 303);
const formularioA = await pedir(superadmin, '/roles/admin/gastos.php');
assert.equal(formularioA.status, 200);
const contextoA = formularioA.texto.match(/name="contexto_restaurante" value="([^"]+)"/)?.[1];
const esperadoA = formularioA.texto.match(/name="restaurante_esperado" value="([^"]+)"/)?.[1];
assert.ok(contextoA);
assert.equal(esperadoA, '1');
const seleccionarB = await pedir(superadmin, '/auth/seleccionar_restaurante.php',
    { restaurante_id: 2, csrf_token: superadmin.csrf }, 'form');
assert.equal(seleccionarB.status, 303);
const panelSuperB = await pedir(superadmin, '/roles/admin/dashboard.php');
const tokenB = panelSuperB.texto.match(/"id":2,"token":"([a-f0-9]+)"/)?.[1];
assert.ok(tokenB);
superadmin.contexto = { id: 2, token: tokenB };
const gastosAntes = sql('SELECT COUNT(*) FROM gastos');
const cargaVieja = { csrf_token: superadmin.csrf, restaurante_esperado: esperadoA,
    contexto_restaurante: contextoA, accion: 'registrar', categoria: 'Insumos',
    descripcion: 'Pestaña antigua A', monto: '1.00', metodo_pago: 'efectivo', fecha: '2026-11-19' };
const envioViejo = await fetch(base + '/roles/admin/gastos.php', { method: 'POST', redirect: 'manual',
    headers: { cookie: superadmin.cookie, 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(cargaVieja) });
const textoViejo = await envioViejo.text();
const contextoPaso = envioViejo.status === 409 && textoViejo.includes('pestaña cambió')
    && gastosAntes === sql('SELECT COUNT(*) FROM gastos');
evidencias.push({ categoria: 5, nombre: 'formulario obsoleto de A tras elegir B',
    peticion: { metodo: 'POST', ruta: '/roles/admin/gastos.php', carga: { ...cargaVieja, contexto_restaurante: '[oculto]', csrf_token: '[oculto]' } },
    respuesta: { status: envioViejo.status, message: textoViejo.slice(0, 140) }, paso: contextoPaso });

// 7. La nota peligrosa se guarda por HTTP y B no puede leerla.
const notaXss = `<img src=x onerror=alert(document.cookie)> " '`;
const notaGuardada = await pedir(aAtencion, '/api/agregar_item.php', { pedido_id: pedidoAId,
    operacion_id: randomBytes(16).toString('hex'), items: [{ producto_id: 2, cantidad: 1,
        notas: notaXss, selecciones: [{ grupo_id: 2, valor_id: 2 }] }] });
assert.equal(notaGuardada.json?.success, true, notaGuardada.json?.message);
registrar(7, 'guardar nota con HTML', '/api/agregar_item.php', 'POST',
    { pedido_id: pedidoAId, notas: notaXss }, notaGuardada, notaGuardada.json?.success === true);
const detalleA = await pedir(aAdmin, `/api/get_pedido_detalle.php?id=${pedidoAId}`);
const detalleB = await pedir(bAdmin, `/api/get_pedido_detalle.php?id=${pedidoAId}`);
const detalleSuperB = await pedir(superadmin, `/api/get_pedido_detalle.php?id=${pedidoAId}`);
const historialA = await pedir(aAdmin, '/roles/admin/historial.php');
registrar(7, 'nota A y lectura B rechazada', `/api/get_pedido_detalle.php?id=${pedidoAId}`, 'GET', null,
    detalleB, detalleA.json?.data?.items?.some(i => i.notas === notaXss)
        && detalleB.json?.success === false && detalleSuperB.json?.success === false && historialA.status === 200);
registrar(7, 'superadmin en B no lee nota A', `/api/get_pedido_detalle.php?id=${pedidoAId}`, 'GET', null,
    detalleSuperB, detalleSuperB.json?.success === false);

// 6. Se revoca una cuenta de ensayo desde otra sesión; su siguiente GET debe fallar.
const emailTemporal = `revocado.${randomBytes(5).toString('hex')}@aislamiento.test`;
const altaTemporal = await pedir(aAdmin, '/api/admin_usuarios.php', { accion: 'crear',
    nombre: 'Temporal A TEST', email: emailTemporal, rol: 'atencion', password: clave, activo: 1 });
assert.equal(altaTemporal.json?.success, true, altaTemporal.json?.message);
const temporal = await cliente(emailTemporal);
const bajaTemporal = await pedir(aAdmin, '/api/admin_usuarios.php', { accion: 'editar', id: Number(altaTemporal.json.data.id),
    nombre: 'Temporal A TEST', email: emailTemporal, rol: 'atencion', activo: 0 });
assert.equal(bajaTemporal.json?.success, true, bajaTemporal.json?.message);
const revocada = await pedir(temporal, '/api/get_menu.php');
registrar(6, 'GET siguiente con sesión desactivada', '/api/get_menu.php', 'GET', null,
    revocada, revocada.status === 401 && revocada.json?.success === false);

// 8. Dos servidores PHP atienden las aperturas en paralelo contra la misma base.
const guardia2 = await fetch('http://127.0.0.1:8099/__aislamiento_db_check');
assert.equal(await guardia2.text(), baseEnsayo);
const turnoA = id('SELECT id FROM turnos WHERE restaurante_id=1 AND fin IS NULL LIMIT 1');
const turnoB = id('SELECT id FROM turnos WHERE restaurante_id=2 AND fin IS NULL LIMIT 1');
assert.ok(turnoA && turnoB);
for (const [c, turno] of [[aAdmin, turnoA], [bAdmin, turnoB]]) {
    const cierre = await pedir(c, '/api/cerrar_turno.php', { turno_id: turno, reabrir: false });
    assert.equal(cierre.json?.success, true, cierre.json?.message);
}
const bEnSegundoServidor = { ...bAdmin, base: 'http://127.0.0.1:8099/MESORA' };
const [aperturaA, aperturaB] = await Promise.all([
    pedir(aAdmin, '/api/abrir_turno.php', {}),
    pedir(bEnSegundoServidor, '/api/abrir_turno.php', {}),
]);
const abiertos = sql('SELECT restaurante_id, COUNT(*) FROM turnos WHERE fin IS NULL GROUP BY restaurante_id ORDER BY restaurante_id');
const cajaPaso = aperturaA.json?.success === true && aperturaB.json?.success === true
    && abiertos === '1\t1\n2\t1';
registrar(8, 'apertura A simultánea', '/api/abrir_turno.php', 'POST', {}, aperturaA, cajaPaso);
registrar(8, 'apertura B simultánea', '/api/abrir_turno.php', 'POST', {}, aperturaB, cajaPaso);

const resultado = { fecha: '2026-11-19', base: baseEnsayo, servidor: base,
    pedidoAId, pedidoBId, pedidoBCobradoId, comprobanteBId, itemBId, evidencias };
if (process.argv.includes('--evidencia')) {
    writeFileSync(new URL('../../docs/auditoria/pruebas/aislamiento_http_2026-11-19.json', import.meta.url),
        JSON.stringify(resultado, null, 2) + '\n', 'utf8');
}
console.log(JSON.stringify(resultado, null, 2));
