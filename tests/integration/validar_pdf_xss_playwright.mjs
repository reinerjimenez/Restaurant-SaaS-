/**
 * Validación visual/XSS en navegador real para la auditoría multitenant.
 * Solo usa el servidor protegido que apunta a mesora_aislamiento_test.
 */
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

const raiz = process.cwd();
const base = 'http://127.0.0.1:8098/MESORA';
const baseFiscal = process.env.FISCAL_BASE ?? 'http://127.0.0.1:8100/MESORA';
const evidencia = join(raiz, 'docs', 'auditoria', 'pruebas', 'playwright_2026-11-20');
const clave = 'Test!aislamiento-2026';
const carga = '<img src=x onerror=alert(document.cookie)> " \' <svg/onload=alert(1)>';

if (!existsSync(evidencia)) mkdirSync(evidencia, { recursive: true });
for (const archivo of readdirSync(evidencia)) {
    if (archivo.endsWith('.png') || archivo.endsWith('.json')) rmSync(join(evidencia, archivo));
}

const browser = await chromium.launch({ headless: true });
const fallos = [];
const resultados = { base, pdf: [], xss: {}, eventos: [] };

async function nuevaPagina() {
    const context = await browser.newContext({ viewport: { width: 1365, height: 1000 }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    page.setDefaultTimeout(8000);
    page.on('dialog', async dialog => {
        resultados.eventos.push({ tipo: 'dialog', mensaje: dialog.message() });
        await dialog.dismiss();
    });
    page.on('pageerror', error => resultados.eventos.push({ tipo: 'pageerror', mensaje: error.message }));
    return { context, page };
}

async function iniciarSesion(page, email, servidor = base, contraseña = clave) {
    await page.goto(servidor + '/index.php', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(contraseña);
    await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/index.php')),
        page.locator('#form-login').evaluate((formulario) => formulario.requestSubmit()),
    ]);
}

async function seleccionarRestaurante(page, restauranteId) {
    const estado = await page.evaluate(async (id) => {
        const respuesta = await fetch('/MESORA/auth/seleccionar_restaurante.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': window.CSRF_TOKEN,
            },
            body: new URLSearchParams({ restaurante_id: String(id) }),
            redirect: 'manual',
        });
        return { codigo: respuesta.status, cuerpo: await respuesta.text() };
    }, restauranteId);
    assert.ok(estado.codigo === 0 || estado.codigo === 303,
        `No se pudo seleccionar el restaurante ${restauranteId}: ${estado.codigo} ${estado.cuerpo.slice(0, 180)}`);
    await page.goto(base + '/roles/admin/dashboard.php', { waitUntil: 'networkidle' });
}

async function abrirDetalle(page) {
    await page.goto(base + '/roles/admin/mesas.php', { waitUntil: 'networkidle' });
    await page.evaluate(() => window.verDetalle(2, 1));
    await page.locator('#modal-detalle-body .pedido-item-opciones').filter({ hasText: '<img src=x' }).first().waitFor();
    const detalle = page.locator('#modal-detalle-body');
    const estado = await detalle.evaluate((nodo) => ({
        texto: nodo.textContent,
        imagenes: nodo.querySelectorAll('img').length,
        svgs: nodo.querySelectorAll('svg').length,
        etiquetasInyectadas: nodo.querySelectorAll('[onerror], [onload]').length,
    }));
    assert.ok(estado.texto.includes('<img src=x onerror=alert(document.cookie)>'), 'La nota no se mostró literalmente.');
    assert.equal(estado.imagenes, 0, 'La nota creó una imagen en el DOM.');
    assert.equal(estado.svgs, 0, 'La nota creó un SVG en el DOM.');
    assert.equal(estado.etiquetasInyectadas, 0, 'La nota creó atributos de evento en el DOM.');
    return estado;
}

try {
    // Vista previa construida por la misma función que alimenta a Dompdf; Chromium la captura en modo impresión.
    const vistas = readdirSync(join(raiz, 'tmp', 'pdfs')).filter((nombre) => nombre.endsWith('.html')).sort();
    assert.ok(vistas.length >= 6, 'Faltan las vistas previas; ejecuta C:\\xampp\\php\\php.exe tests\\emision_pdf.php.');
    for (const vista of vistas) {
        const { context, page } = await nuevaPagina();
        await page.emulateMedia({ media: 'print' });
        await page.goto(pathToFileURL(resolve(raiz, 'tmp', 'pdfs', vista)).href, { waitUntil: 'load' });
        const destino = join(evidencia, basename(vista, '.html') + '.png');
        await page.screenshot({ path: destino, fullPage: true });
        const contenido = await page.locator('body').innerText();
        assert.match(contenido, /RUC:\s*20123456786/, `No se ve el RUC en ${vista}.`);
        const qr = page.locator('img.qr');
        assert.equal(await qr.count(), 1, `No se ve el QR en ${vista}.`);
        assert.ok(await qr.evaluate((imagen) => imagen.complete && imagen.naturalWidth > 0), `El QR no cargó en ${vista}.`);
        resultados.pdf.push({ archivo: vista.replace(/\.html$/, '.pdf'), png: basename(destino), texto_visiblemente_renderizado: true });
        await context.close();
    }

    // Admin A abre el registro malicioso ya persistido por la prueba HTTP y comprueba el DOM real.
    {
        const { context, page } = await nuevaPagina();
        await iniciarSesion(page, 'admin.A@aislamiento.test');
        resultados.xss.admin_a = await abrirDetalle(page);
        await page.screenshot({ path: join(evidencia, 'xss_admin_a_detalle.png'), fullPage: true });
        await context.close();
    }

    // El superadmin debe verlo solo bajo el contexto A y dejar de verlo tras cambiar a B.
    {
        const { context, page } = await nuevaPagina();
        await iniciarSesion(page, 'super@aislamiento.test');
        await seleccionarRestaurante(page, 1);
        resultados.xss.superadmin_a = await abrirDetalle(page);
        await page.screenshot({ path: join(evidencia, 'xss_superadmin_a_detalle.png'), fullPage: true });
        await seleccionarRestaurante(page, 2);
        const respuestaB = await page.evaluate(async () => (await fetch('/MESORA/api/get_pedido_detalle.php?id=2')).text());
        assert.match(respuestaB, /Pedido no encontrado|success[^]*false/i, 'El contexto B pudo leer el pedido de A.');
        resultados.xss.superadmin_b = { respuesta: respuestaB.slice(0, 300) };
        await page.screenshot({ path: join(evidencia, 'xss_superadmin_b_aislado.png'), fullPage: true });
        await context.close();
    }

    // El formulario fiscal usa la segunda base desechable autorizada, que sí contiene las migraciones fiscales.
    {
        const { context, page } = await nuevaPagina();
        await iniciarSesion(page, 'sunat.a.admin@example.test', baseFiscal, 'SunatEnsayo-2026!');
        await page.goto(baseFiscal + '/roles/admin/emision_electronica.php', { waitUntil: 'networkidle' });
        await page.locator('form input#nombre').fill(carga);
        const valor = await page.locator('form input#nombre').inputValue();
        assert.equal(valor, carga, 'El campo cliente alteró el texto inyectado.');
        assert.equal(await page.locator('form img, form svg, form [onerror], form [onload]').count(), 0,
            'El payload creó nodos ejecutables en los campos de comprobante.');
        resultados.xss.campos_comprobante = { valor_literal: valor, nodos_ejecutables: 0 };
        await page.screenshot({ path: join(evidencia, 'xss_campos_comprobante.png'), fullPage: true });
        await context.close();
    }

    assert.deepEqual(resultados.eventos, [], 'El navegador registró dialog o pageerror.');
    resultados.estado = 'OK';
    writeFileSync(join(evidencia, 'resultado_playwright.json'), JSON.stringify(resultados, null, 2));
    console.log(JSON.stringify(resultados, null, 2));
} catch (error) {
    fallos.push(error instanceof Error ? error.message : String(error));
    resultados.estado = 'FALLO';
    resultados.fallos = fallos;
    writeFileSync(join(evidencia, 'resultado_playwright.json'), JSON.stringify(resultados, null, 2));
    console.error(JSON.stringify(resultados, null, 2));
    process.exitCode = 1;
} finally {
    await browser.close();
}
