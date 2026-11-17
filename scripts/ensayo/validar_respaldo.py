"""Valida sin ejecutar el subconjunto SQL de respaldos sintéticos autorizado."""
import hashlib
import json
import pathlib
import re
import sys

IDENTIFICADOR = re.compile(r'^[a-z_][a-z_0-9]*$')
PROHIBIDAS = {
    'ALTER', 'BENCHMARK', 'CALL', 'CREATE', 'DO', 'DROP', 'DUMPFILE',
    'EXECUTE', 'GET_LOCK', 'GRANT', 'INSERT', 'INTO', 'IS_FREE_LOCK', 'LOAD_FILE',
    'OUTFILE', 'RELEASE_ALL_LOCKS', 'RELEASE_LOCK', 'REPLACE', 'REVOKE', 'SELECT',
    'SLEEP', 'TRUNCATE', 'USE',
}
DIRECTIVAS_MARIADB = {
    'guardar_charset': re.compile(r'/\*!\s*40101\s+SET\s+@saved_cs_client\s*=\s*@@character_set_client\s*\*/', re.I),
    'usar_utf8': re.compile(r'/\*!\s*40101\s+SET\s+character_set_client\s*=\s*utf8\s*\*/', re.I),
    'restaurar_charset': re.compile(r'/\*!\s*40101\s+SET\s+character_set_client\s*=\s*@saved_cs_client\s*\*/', re.I),
}


def tokenizar(contenido: str) -> list[tuple[str, list[tuple[str, str]]]]:
    """Lexea comillas, identificadores, comentarios y terminadores SQL."""
    resultado: list[tuple[str, list[tuple[str, str]]]] = []
    tokens: list[tuple[str, str]] = []
    inicio = 0
    i = 0
    n = len(contenido)
    while i < n:
        c = contenido[i]
        if c.isspace():
            i += 1
            continue
        if contenido.startswith('/*!', i):
            fin = contenido.find('*/', i + 3)
            if fin < 0:
                raise ValueError('Directiva ejecutable incompleta')
            directiva = contenido[i:fin + 2]
            autorizada = next((nombre for nombre, patron in DIRECTIVAS_MARIADB.items()
                               if patron.fullmatch(directiva)), None)
            if autorizada is None:
                raise ValueError('Directiva ejecutable no autorizada')
            tokens.append(('directiva', autorizada))
            i = fin + 2
            continue
        if c == '#' or contenido.startswith('/*', i) or (
            contenido.startswith('--', i) and (i + 2 == n or contenido[i + 2].isspace())
        ):
            raise ValueError('Comentarios SQL no autorizados')
        if c in "'\"`":
            tipo = 'ident' if c == '`' else 'cadena'
            cierre = c
            i += 1
            valor = []
            while i < n:
                actual = contenido[i]
                if actual == '\\' and c != '`':
                    if i + 1 >= n:
                        raise ValueError('Escape incompleto')
                    # El lexer mantiene el carácter escapado dentro del mismo literal.
                    valor.extend((actual, contenido[i + 1]))
                    i += 2
                    continue
                if actual == cierre:
                    if i + 1 < n and contenido[i + 1] == cierre:
                        valor.extend((cierre, cierre))
                        i += 2
                        continue
                    i += 1
                    break
                valor.append(actual)
                i += 1
            else:
                raise ValueError('Literal o identificador sin cerrar')
            if tipo == 'ident':
                texto = ''.join(valor).replace('``', '`')
            else:
                texto = ''.join(valor)
            tokens.append((tipo, texto))
            continue
        if c == ';':
            if tokens:
                resultado.append((contenido[inicio:i].strip(), tokens))
            inicio = i + 1
            tokens = []
            i += 1
            continue
        if c.isalpha() or c == '_':
            fin = i + 1
            while fin < n and (contenido[fin].isalnum() or contenido[fin] in '_$'):
                fin += 1
            tokens.append(('palabra', contenido[i:fin].upper()))
            i = fin
            continue
        if c.isdigit() or (c == '.' and i + 1 < n and contenido[i + 1].isdigit()):
            numero = re.match(r'(?:0[xX][0-9a-fA-F]+|0[bB][01]+|(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?)', contenido[i:])
            if numero is None:
                raise ValueError('Número SQL inválido')
            fin = i + len(numero.group(0))
            tokens.append(('numero', numero.group(0).upper()))
            i = fin
            continue
        tokens.append(('simbolo', c))
        i += 1
    if tokens:
        raise ValueError('La última sentencia no termina en punto y coma')
    return resultado


def _es_identificador(token: tuple[str, str]) -> bool:
    return token[0] in ('ident', 'palabra') and IDENTIFICADOR.fullmatch(token[1].lower()) is not None


def _sin_referencias_externas(tokens: list[tuple[str, str]]) -> bool:
    for indice, token in enumerate(tokens):
        if token == ('simbolo', '@'):
            return False
        if (indice + 2 < len(tokens) and _es_identificador(token) and
                tokens[indice + 1] == ('simbolo', '.') and _es_identificador(tokens[indice + 2])):
            return False
    return True


def _validar_create(tokens: list[tuple[str, str]]) -> str | None:
    if len(tokens) < 8 or tokens[:2] != [('palabra', 'CREATE'), ('palabra', 'TABLE')]:
        return None
    if not _es_identificador(tokens[2]) or tokens[3] != ('simbolo', '('):
        return None
    tabla = tokens[2][1].lower()
    profundidad = 0
    cierre = None
    for indice in range(3, len(tokens)):
        if tokens[indice] == ('simbolo', '('):
            profundidad += 1
        elif tokens[indice] == ('simbolo', ')'):
            profundidad -= 1
            if profundidad < 0:
                return None
            if profundidad == 0:
                cierre = indice
                break
    if cierre is None or cierre <= 4 or not _opciones_tabla_validas(tokens[cierre + 1:]):
        return None
    cuerpo = tokens[4:cierre]
    if not _sin_referencias_externas(cuerpo):
        return None
    if any(tipo == 'palabra' and valor in PROHIBIDAS for tipo, valor in cuerpo):
        return None
    return tabla


def _opciones_tabla_validas(tokens: list[tuple[str, str]]) -> bool:
    """Acepta opciones de tabla generadas habitualmente por MariaDB 10.4."""
    i = 0
    if len(tokens) < 3 or tokens[:3] != [('palabra', 'ENGINE'), ('simbolo', '='), ('palabra', 'INNODB')]:
        return False
    i = 3
    while i < len(tokens):
        if tokens[i] == ('palabra', 'AUTO_INCREMENT'):
            if i + 2 >= len(tokens) or tokens[i + 1] != ('simbolo', '=') or tokens[i + 2][0] != 'numero':
                return False
            i += 3
        elif tokens[i] == ('palabra', 'DEFAULT') and i + 3 < len(tokens) and tokens[i + 1] == ('palabra', 'CHARSET'):
            if tokens[i + 2] != ('simbolo', '=') or not _es_identificador(tokens[i + 3]):
                return False
            i += 4
        elif tokens[i] == ('palabra', 'CHARSET') and i + 2 < len(tokens):
            if tokens[i + 1] != ('simbolo', '=') or not _es_identificador(tokens[i + 2]):
                return False
            i += 3
        elif tokens[i] == ('palabra', 'COLLATE') and i + 2 < len(tokens):
            if tokens[i + 1] != ('simbolo', '=') or not _es_identificador(tokens[i + 2]):
                return False
            i += 3
        elif tokens[i] == ('palabra', 'ROW_FORMAT') and i + 2 < len(tokens):
            if tokens[i + 1] != ('simbolo', '=') or tokens[i + 2][1] not in {
                'FIXED', 'DYNAMIC', 'COMPRESSED', 'REDUNDANT', 'COMPACT', 'PAGE'
            }:
                return False
            i += 3
        elif tokens[i] == ('palabra', 'COMMENT') and i + 2 < len(tokens):
            if tokens[i + 1] != ('simbolo', '=') or tokens[i + 2][0] != 'cadena':
                return False
            i += 3
        else:
            return False
    return True


def _validar_insert(tokens: list[tuple[str, str]]) -> str | None:
    if len(tokens) < 7 or tokens[:2] != [('palabra', 'INSERT'), ('palabra', 'INTO')]:
        return None
    if not _es_identificador(tokens[2]):
        return None
    tabla = tokens[2][1].lower()
    i = 3
    if i < len(tokens) and tokens[i] == ('simbolo', '('):
        i += 1
        while i < len(tokens) and tokens[i] != ('simbolo', ')'):
            if not _es_identificador(tokens[i]):
                return None
            i += 1
            if i < len(tokens) and tokens[i] == ('simbolo', ','):
                i += 1
            elif i >= len(tokens) or tokens[i] != ('simbolo', ')'):
                return None
        if i >= len(tokens):
            return None
        i += 1
    if i >= len(tokens) or tokens[i] != ('palabra', 'VALUES'):
        return None
    i += 1
    filas = 0
    while i < len(tokens):
        if tokens[i] != ('simbolo', '('):
            return None
        i += 1
        celdas = 0
        while i < len(tokens) and tokens[i] != ('simbolo', ')'):
            if tokens[i] in (('simbolo', '+'), ('simbolo', '-')):
                if i + 1 >= len(tokens) or tokens[i + 1][0] != 'numero':
                    return None
                i += 2
                celdas += 1
                if i < len(tokens) and tokens[i] == ('simbolo', ','):
                    i += 1
                    if i >= len(tokens) or tokens[i] == ('simbolo', ')'):
                        return None
                elif i < len(tokens) and tokens[i] == ('simbolo', ')'):
                    break
                else:
                    return None
                continue
            if tokens[i][0] == 'simbolo' and tokens[i][1] not in ('+', '-'):
                return None
            if tokens[i][0] == 'palabra' and tokens[i][1] not in ('NULL', 'TRUE', 'FALSE', 'X', 'B', 'N', '_BINARY', '_UTF8', '_UTF8MB4', '_LATIN1'):
                return None
            if tokens[i][0] not in ('cadena', 'numero', 'palabra', 'simbolo'):
                return None
            if tokens[i][1] in (';', '@', '.'):
                return None
            if tokens[i][1] in ('X', 'B', 'N', '_BINARY', '_UTF8', '_UTF8MB4', '_LATIN1'):
                if i + 1 >= len(tokens) or tokens[i + 1][0] not in ('cadena', 'numero'):
                    return None
                i += 1
            i += 1
            celdas += 1
            if i < len(tokens) and tokens[i] == ('simbolo', ','):
                i += 1
                if i >= len(tokens) or tokens[i] == ('simbolo', ')'):
                    return None
            elif i < len(tokens) and tokens[i] == ('simbolo', ')'):
                break
            else:
                return None
        if celdas == 0 or i >= len(tokens) or tokens[i] != ('simbolo', ')'):
            return None
        i += 1
        filas += 1
        if i == len(tokens):
            break
        if tokens[i] != ('simbolo', ','):
            return None
        i += 1
    return tabla if filas else None


def validar(archivo: pathlib.Path, manifiesto: pathlib.Path) -> bool:
    datos = json.loads(manifiesto.read_text(encoding='utf-8'))
    claves = {'origen', 'destino', 'tablas', 'tamano', 'sha256'}
    if set(datos) != claves or datos.get('origen') != 'restaurant_ola1_test' or datos.get('destino') != 'restaurant_ola1_restore':
        return False
    tablas = datos.get('tablas')
    if (not isinstance(tablas, list) or not tablas or
            any(not isinstance(tabla, str) or not IDENTIFICADOR.fullmatch(tabla) for tabla in tablas) or
            len(set(tablas)) != len(tablas)):
        return False
    if not isinstance(datos.get('tamano'), int) or isinstance(datos.get('tamano'), bool) or datos['tamano'] <= 0:
        return False
    if not isinstance(datos.get('sha256'), str) or not re.fullmatch(r'[0-9a-f]{64}', datos['sha256']):
        return False
    if not archivo.is_file() or archivo.is_symlink() or archivo.stat().st_size != datos['tamano']:
        return False
    bruto = archivo.read_bytes()
    if len(bruto) != datos['tamano'] or hashlib.sha256(bruto).hexdigest() != datos['sha256']:
        return False
    try:
        contenido = bruto.decode('utf-8-sig')
        sentencias = tokenizar(contenido)
    except (UnicodeError, ValueError):
        return False
    permitidas = set(tablas)
    creadas: set[str] = set()
    estado_charset = 'inactivo'
    for _, tokens in sentencias:
        if tokens[0][0] == 'directiva':
            nombre = tokens[0][1]
            transiciones = {
                ('inactivo', 'guardar_charset'): 'guardado',
                ('guardado', 'usar_utf8'): 'utf8',
                ('utf8', 'restaurar_charset'): 'inactivo',
            }
            if len(tokens) != 1 or (estado_charset, nombre) not in transiciones:
                return False
            estado_charset = transiciones[(estado_charset, nombre)]
            continue
        if not _sin_referencias_externas(tokens):
            return False
        if tokens[0] == ('palabra', 'CREATE'):
            tabla = _validar_create(tokens)
            if tabla not in permitidas or tabla in creadas:
                return False
            creadas.add(tabla)
        elif tokens[0] == ('palabra', 'INSERT'):
            tabla = _validar_insert(tokens)
            if tabla not in creadas:
                return False
        else:
            return False
    return creadas == permitidas and estado_charset == 'inactivo'


if __name__ == '__main__':
    try:
        resultado = len(sys.argv) == 3 and validar(pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2]))
    except (OSError, ValueError, UnicodeError, TypeError):
        resultado = False
    print('SQL del respaldo validado; aún requiere preflight e importación controlada.' if resultado else 'Volcado rechazado.')
    raise SystemExit(0 if resultado else 1)
