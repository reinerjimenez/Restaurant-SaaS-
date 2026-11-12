"""Valida el subconjunto SQL autorizado de un respaldo de ensayo."""
import hashlib
import json
import pathlib
import sys


def sentencias(contenido: str) -> list[str]:
    """Divide sentencias y rechaza comentarios SQL fuera de literales."""
    piezas, inicio, comilla, escape = [], 0, None, False
    for i, letra in enumerate(contenido):
        if comilla:
            if escape:
                escape = False
            elif letra == "\\":
                escape = True
            elif letra == comilla:
                if i + 1 < len(contenido) and contenido[i + 1] == comilla:
                    escape = True
                else:
                    comilla = None
        elif contenido.startswith(('--', '/*', '#'), i):
            raise ValueError('Comentarios SQL no autorizados')
        elif letra in ("'", '"', '`'):
            comilla = letra
        elif letra == ';':
            piezas.append(contenido[inicio:i].strip())
            inicio = i + 1
    if comilla or contenido[inicio:].strip():
        raise ValueError('SQL incompleto')
    return [pieza for pieza in piezas if pieza]


def validar(archivo: pathlib.Path, manifiesto: pathlib.Path) -> bool:
    datos = json.loads(manifiesto.read_text(encoding='utf-8'))
    if datos.get('origen') != 'restaurant_ola1_test' or datos.get('destino') != 'restaurant_ola1_restore':
        return False
    if not archivo.is_file() or archivo.is_symlink() or archivo.stat().st_size == 0:
        return False
    bruto = archivo.read_bytes()
    if len(bruto) != datos.get('tamano') or hashlib.sha256(bruto).hexdigest() != datos.get('sha256'):
        return False
    if not isinstance(datos.get('tablas'), list) or not datos['tablas']:
        return False
    texto = bruto.decode('utf-8-sig')
    if '@' in texto:
        return False
    import re
    permitidas = set(datos['tablas'])
    vistas = set()
    try:
        partes = sentencias(texto)
    except ValueError:
        return False
    for parte in partes:
        crear = re.fullmatch(
            r'CREATE\s+TABLE\s+`?([a-z_][a-z_0-9]*)`?\s*\(.+\)\s*'
            r'ENGINE\s*=\s*InnoDB(?:\s+AUTO_INCREMENT\s*=\s*\d+)?'
            r'(?:\s+DEFAULT\s+CHARSET\s*=\s*[a-z0-9_]+)?'
            r'(?:\s+COLLATE\s*=\s*[a-z0-9_]+)?', parte, re.I | re.S)
        insertar = re.fullmatch(r'INSERT\s+INTO\s+`?([a-z_][a-z_0-9]*)`?\s+VALUES\s*\(.+\)', parte, re.I | re.S)
        if not crear and not insertar:
            return False
        tabla = (crear or insertar).group(1)
        if tabla not in permitidas or re.search(r'\b(?:SELECT|OUTFILE|LOAD_FILE|CREATE\s+DATABASE|DROP\s+DATABASE|USE)\b', parte, re.I):
            return False
        if crear:
            if tabla in vistas:
                return False
            vistas.add(tabla)
        elif tabla not in vistas:
            return False
        if re.search(r'`?[a-z_][a-z_0-9]*`?\s*\.\s*`?[a-z_][a-z_0-9]*`?', parte, re.I):
            return False
    return vistas == permitidas


if __name__ == '__main__':
    try:
        resultado = len(sys.argv) == 3 and validar(pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2]))
    except (OSError, ValueError, UnicodeError, TypeError):
        resultado = False
    print('SQL del respaldo validado; aún requiere preflight e importación controlada.' if resultado else 'Volcado rechazado.')
    raise SystemExit(0 if resultado else 1)
