(() => {
    const estado = document.getElementById('ticket-rawbt-estado');
    if (!estado || !/Android/i.test(navigator.userAgent)) return;

    const retorno = new URL(window.location.origin);
    retorno.pathname = window.location.pathname;
    const consulta = new URLSearchParams(window.location.search);
    retorno.searchParams.set('id', consulta.get('id') ?? '');
    retorno.searchParams.set('impresion', 'rawbt_no_disponible');
    const sufijo = '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;'
        + 'S.browser_fallback_url=' + encodeURIComponent(retorno.href) + ';end';
    const intent = bytes => 'intent:base64,' + btoa(bytes) + sufijo;
    const maximoIntent = 8 * 1024;

    const enlace = document.querySelector('.ticket-rawbt');
    const errorAlAbrir = 'Android no pudo abrir RawBT. Comprueba que esté instalada o imprime desde el navegador.';
    const usarNavegador = 'La impresión directa no está disponible para este ticket. Puedes imprimir desde el navegador.';
    const regresoDeRawBT = consulta.get('impresion') === 'rawbt_no_disponible';
    if (!enlace || enlace.dataset.ticket.length + sufijo.length + 'intent:base64,'.length > maximoIntent) {
        estado.textContent = regresoDeRawBT ? errorAlAbrir : usarNavegador;
        return;
    }
    const texto = atob(enlace.dataset.ticket);
    const soloTexto = intent(texto);

    // GS v 0, escala 1:1. Los puntos blancos se quedan en cero, incluso en el relleno de cada fila.
    function rasterMonocromo(imagen) {
        if (!imagen.naturalWidth || !imagen.naturalHeight
            || new URL(imagen.currentSrc, window.location.href).origin !== window.location.origin) return '';

        const escala = Math.min(1, 192 / imagen.naturalWidth, 80 / imagen.naturalHeight);
        const ancho = Math.max(1, Math.floor(imagen.naturalWidth * escala));
        const alto = Math.max(1, Math.floor(imagen.naturalHeight * escala));
        const lienzo = document.createElement('canvas');
        lienzo.width = ancho;
        lienzo.height = alto;
        const contexto = lienzo.getContext('2d');
        if (!contexto) return '';
        contexto.fillStyle = '#fff';
        contexto.fillRect(0, 0, ancho, alto);
        contexto.drawImage(imagen, 0, 0, ancho, alto);
        const pixeles = contexto.getImageData(0, 0, ancho, alto).data;
        const bytesFila = Math.ceil(ancho / 8);
        const mapa = new Uint8Array(bytesFila * alto);
        for (let y = 0; y < alto; y++) {
            for (let x = 0; x < ancho; x++) {
                const i = (y * ancho + x) * 4;
                const luminancia = (299 * pixeles[i] + 587 * pixeles[i + 1] + 114 * pixeles[i + 2]) / 1000;
                if (luminancia < 160) mapa[y * bytesFila + (x >> 3)] |= 0x80 >> (x & 7);
            }
        }
        if (!mapa.some(byte => byte !== 0)) return '';
        return '\x1dv0\x00' + String.fromCharCode(bytesFila, 0, alto, 0, ...mapa);
    }

    let bloqueadoHasta = 0;
    enlace.addEventListener('click', evento => {
        const ahora = Date.now();
        if (!enlace.hasAttribute('href') || ahora < bloqueadoHasta) {
            evento.preventDefault();
            return;
        }
        bloqueadoHasta = ahora + 1500;
        enlace.setAttribute('aria-disabled', 'true');
        window.setTimeout(() => enlace.removeAttribute('aria-disabled'), 1500);
    });

    let preparado = false;
    function habilitar(imagen) {
        if (preparado) return;
        preparado = true;
        let destino = soloTexto;
        if (imagen) {
            try {
                const logo = rasterMonocromo(imagen);
                const centrado = '\x1ba\x01';
                const inicio = texto.indexOf(centrado);
                if (logo && inicio !== -1) {
                    const posicion = inicio + centrado.length;
                    const conLogo = intent(texto.slice(0, posicion) + logo + texto.slice(posicion));
                    if (conLogo.length <= maximoIntent) destino = conLogo;
                }
            } catch {
                // Un logo ilegible no impide imprimir el resto del comprobante.
            }
        }
        enlace.href = destino;
        enlace.removeAttribute('aria-disabled');
        estado.textContent = regresoDeRawBT ? errorAlAbrir : '';
    }

    enlace.hidden = false;
    const imagen = document.querySelector('#comprobante-print-root .ticket-logo img');
    if (!imagen || imagen.complete) {
        habilitar(imagen);
    } else {
        enlace.setAttribute('aria-disabled', 'true');
        estado.textContent = 'Preparando el ticket para RawBT…';
        imagen.addEventListener('load', () => habilitar(imagen), { once: true });
        imagen.addEventListener('error', () => habilitar(), { once: true });
        window.setTimeout(() => habilitar(), 1200);
    }
})();
