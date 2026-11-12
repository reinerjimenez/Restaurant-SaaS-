import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

// Se ejecuta el JavaScript del encabezado con un fetch simulado, sin red ni BD.
const plantilla = readFileSync(new URL('../includes/header.php', import.meta.url), 'utf8');
const codigo = plantilla.match(/<script>\s*(window\.BASE_URL[\s\S]*?)<\/script>/)[1];
const valores = ['"/system-restaurant"', '"csrf-sintetico"', '{"id":17,"token":"contexto-sintetico"}'];
const llamadas = [];
const window = {
    location: {
        origin: 'https://local.test',
        href: 'https://local.test/system-restaurant/roles/admin/dashboard.php',
    },
    fetch(...argumentos) {
        llamadas.push(argumentos);
        return Promise.resolve();
    },
};
runInNewContext(codigo.replace(/<\?=[\s\S]*?\?>/g, () => valores.shift()), { window, Request, URL, Headers });

await window.fetch('/system-restaurant/api/get_menu.php');
assert.equal(llamadas[0][1].headers.get('X-Restaurante-Esperado'), '17');
assert.equal(llamadas[0][1].headers.get('X-Contexto-Restaurante'), 'contexto-sintetico');
assert.equal(llamadas[0][1].headers.get('X-CSRF-Token'), null);

await window.fetch(new Request('https://local.test/system-restaurant/api/cobrar_pedido.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
}));
assert.equal(llamadas[1][1].headers.get('X-CSRF-Token'), 'csrf-sintetico');
assert.equal(llamadas[1][1].headers.get('Content-Type'), 'application/json');
assert.equal(llamadas[1][1].redirect, 'error');

await window.fetch('https://tercero.test/system-restaurant/api/prueba.php', { method: 'POST' });
await window.fetch('https://local.test/otra-app/api/prueba.php', { method: 'POST' });
assert.equal(llamadas[2][1].headers, undefined);
assert.equal(llamadas[3][1].headers, undefined);
console.log('Cabeceras de contexto: 8 comprobaciones sin red ni base de datos.');
