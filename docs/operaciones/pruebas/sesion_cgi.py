"""Comprueba cookies de sesión anónima con PHP CGI. No abre una conexión a BD."""
from email.parser import BytesHeaderParser
from html.parser import HTMLParser
from http.cookies import SimpleCookie
from pathlib import Path
import os
import subprocess
import sys
import tempfile
from urllib.parse import urlencode


class Formulario(HTMLParser):
    token = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') == 'csrf_token':
            self.token = attrs.get('value')


def comprobar(condicion, mensaje):
    if not condicion:
        raise AssertionError(mensaje)


def ejecutar():
    raiz = Path(__file__).resolve().parents[3]
    cgi = sys.argv[1] if len(sys.argv) > 1 else 'C:/xampp/php/php-cgi.exe'
    with tempfile.TemporaryDirectory(prefix='rest_sesion_cgi_', dir=Path(__file__).resolve().parent) as sesiones:
        def peticion(ruta, https=True, cookie='', datos=None):
            archivo = raiz / ruta
            cuerpo = urlencode(datos or {}).encode()
            entorno = os.environ.copy()
            entorno.pop('QUERY_STRING', None)
            entorno.update({
                'REDIRECT_STATUS': '200', 'SERVER_PROTOCOL': 'HTTP/1.1',
                'REQUEST_METHOD': 'POST' if datos is not None else 'GET',
                'HTTP_HOST': 'localhost', 'SERVER_NAME': 'localhost',
                'SERVER_PORT': '443' if https else '80', 'HTTPS': 'on' if https else 'off',
                'SCRIPT_NAME': '/system-restaurant/' + ruta, 'SCRIPT_FILENAME': str(archivo),
                'DOCUMENT_ROOT': str(raiz.parent), 'HTTP_COOKIE': cookie,
                'CONTENT_LENGTH': str(len(cuerpo)), 'CONTENT_TYPE': 'application/x-www-form-urlencoded',
            })
            resultado = subprocess.run([cgi, '-d', 'session.save_handler=files', '-d', f'session.save_path="{sesiones}"',
                                        '-d', 'display_errors=stderr'],
                                      env=entorno, cwd=archivo.parent, input=cuerpo,
                                      capture_output=True, timeout=10, check=True)
            encabezados, contenido = resultado.stdout.replace(b'\r\n', b'\n').split(b'\n\n', 1)
            return BytesHeaderParser().parsebytes(encabezados), contenido

        def cookie_sesion(encabezados):
            cookies = SimpleCookie()
            for valor in encabezados.get_all('Set-Cookie', []):
                cookies.load(valor)
            comprobar(len(cookies) == 1, 'Se esperaba una cookie de sesión')
            return next(iter(cookies.values()))

        encabezados, contenido = peticion('index.php')
        cookie = cookie_sesion(encabezados)
        comprobar(cookie['secure'] and cookie['httponly'] and cookie['samesite'].lower() == 'lax',
                   'La cookie HTTPS debe ser Secure, HttpOnly y SameSite=Lax')
        token = Formulario()
        token.feed(contenido.decode('utf-8', errors='replace'))
        comprobar(bool(token.token), 'El formulario debe incluir CSRF')
        cookie_actual = f'{cookie.key}={cookie.value}'
        peticion('auth/logout.php', cookie=cookie_actual)
        _, contenido = peticion('index.php', cookie=cookie_actual)
        token_despues = Formulario()
        token_despues.feed(contenido.decode('utf-8', errors='replace'))
        comprobar(token_despues.token == token.token, 'GET no debe destruir la sesión')
        encabezados, _ = peticion('auth/logout.php', cookie=cookie_actual, datos={'csrf_token': token.token})
        comprobar(any('Max-Age=0' in valor for valor in encabezados.get_all('Set-Cookie', [])),
                   'POST de salida debe expirar la cookie')
        encabezados, _ = peticion('index.php', cookie=cookie_actual)
        comprobar(cookie_sesion(encabezados).value != cookie.value, 'La sesión cerrada no debe reutilizarse')
        encabezados, _ = peticion('index.php', https=False)
        cookie_http = cookie_sesion(encabezados)
        comprobar(not cookie_http['secure'] and cookie_http['httponly'],
                   'XAMPP por HTTP debe conservar HttpOnly sin exigir una cookie Secure')
    print('OK: cookies HTTPS/HTTP, CSRF anónimo, logout por POST e identificador revocado; sin BD.')


if __name__ == '__main__':
    ejecutar()
