import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { webcrypto } from 'node:crypto';

const vista = readFileSync(new URL('../roles/atencion/comanda.php', import.meta.url), 'utf8').replace(/\r\n/g, '\n');
const funciones = vista.slice(vista.indexOf('async function enviarACocina()'), vista.indexOf('\ntry {\n    const guardado'));
const envios = [];
const almacen = new Map();
const entorno = {
    PEDIDO_ID: 20, BASE_URL: '', CLAVE_ENVIO: 'comanda-prueba', crypto: webcrypto,
    nuevosItems: [{ producto_id: 5, precio: 10, cantidad: 1 }], envioPendiente: null, enviando: false,
    document: { getElementById: () => ({}) }, renderNuevosItems() {},
    Toast: { exito() {}, error() {} }, setTimeout() {},
    sessionStorage: { setItem: (k, v) => almacen.set(k, v), removeItem: k => almacen.delete(k) },
    async fetch(url, opciones) {
        envios.push(opciones.body);
        if (envios.length === 1) throw new Error('Respuesta perdida');
        return { json: async () => ({ success: true }) };
    },
};
runInNewContext(funciones, entorno);
await entorno.enviarACocina();
assert.equal(almacen.get('comanda-prueba'), envios[0]);
await entorno.enviarACocina();
assert.equal(envios[0], envios[1]);
assert.match(JSON.parse(envios[0]).operacion_id, /^[a-f0-9]{32}$/);
assert.equal(almacen.size, 0);
console.log('OK H-007: respuesta perdida y reintento con el mismo cuerpo y clave, sin red ni BD.');
