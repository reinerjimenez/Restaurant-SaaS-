"""Evalúa el predicado del 004 sobre metadatos sintéticos, con SELECT en memoria."""
from pathlib import Path
import re
import sqlite3

raiz = Path(__file__).resolve().parents[1]
instalador = (raiz / 'Database/002_produccion_limpia.sql').read_text(encoding='utf-8-sig')
migracion = (raiz / 'Database/004_ola_1_finanzas.sql').read_text(encoding='utf-8-sig')
esquema = re.search(r'SET @ola1_esquema_ok = (\(.*?\n\));', migracion, re.S)[1]
turnos = re.search(r"'SELECT (NOT EXISTS \(SELECT 1 FROM turnos WHERE fin IS NULL\)) INTO @ola1_turnos_ok'", migracion)[1]
assert 'SET @ola1_finanzas_ok = @ola1_esquema_ok AND @ola1_turnos_ok;' in migracion
assert migracion.count('SET @ddl_finanzas = IF(@ola1_finanzas_ok,') == 5

tablas, columnas, indices = [], [], []
for tabla, bloque, motor in re.findall(r'CREATE TABLE `([^`]+)` \((.*?)\n\) ENGINE=(\w+)', instalador, re.S):
    tablas.append(('ensayo', tabla, motor))
    for nombre, tipo in re.findall(r'^\s+`([^`]+)` (\w+(?:\([^)]*\))?(?: unsigned)?)', bloque, re.M):
        columnas.append(('ensayo', tabla, nombre, re.match(r'\w+', tipo)[0], tipo))
    for n, nombre in enumerate(re.findall(r'`([^`]+)`', re.search(r'PRIMARY KEY \(([^)]*)\)', bloque)[1]), 1):
        indices.append(('ensayo', tabla, 'PRIMARY', n, nombre))


def evaluar(t=tablas, c=columnas, i=indices, abierto=False):
    consulta = esquema.replace('information_schema.', '').replace('DATABASE()', "'ensayo'")
    with sqlite3.connect(':memory:') as db:
        db.create_function('FIND_IN_SET', 2, lambda nombre, lista: int(nombre in lista.split(',')))
        parametros = [valor for grupo in (t, c, i) for fila in grupo for valor in fila]
        ctes = []
        for cabecera, grupo in [('tables(TABLE_SCHEMA,TABLE_NAME,ENGINE)', t),
                                ('columns(TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_TYPE)', c),
                                ('statistics(TABLE_SCHEMA,TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME)', i)]:
            fila = '(' + ','.join('?' for _ in grupo[0]) + ')'
            ctes.append(f'{cabecera} AS (VALUES ' + ','.join([fila] * len(grupo)) + ')')
        metadata_ok = db.execute('WITH ' + ', '.join(ctes) + ' SELECT ' + consulta, parametros).fetchone()[0]
        if not metadata_ok:
            return False
        return bool(db.execute('WITH turnos(fin) AS (VALUES (?)) SELECT ' + turnos,
                               (None if abierto else '2026-11-11 17:00:00',)).fetchone()[0])


assert evaluar(), '002 compatible'
assert not evaluar(abierto=True), 'turno abierto'
for tabla, columna in [('usuarios', 'session_version'), ('pagos', 'anulado_en'), ('turnos', 'fin')]:
    assert not evaluar(c=[fila for fila in columnas if fila[1:3] != (tabla, columna)]), columna
for tabla in ['facturacion_config', 'logs_acceso', 'turnos']:
    assert not evaluar(t=[fila for fila in tablas if fila[1] != tabla]), tabla
assert not evaluar(t=[(s, t, 'MyISAM' if t == 'pagos' else m) for s, t, m in tablas]), 'motor'
assert not evaluar(c=[(*f[:4], 'int unsigned') if f[1:3] == ('turnos', 'id') else f for f in columnas]), 'tipo FK'
assert not evaluar(i=indices + [('ensayo', 'pagos', 'PRIMARY', 2, 'pedido_id')]), 'PK compuesta'
assert not evaluar(c=columnas + [('ensayo', 'pagos', 'turno_id', 'int', 'int')]), 'migración parcial'
assert not evaluar(t=tablas + [('ensayo', 'envios_comanda', 'InnoDB')]), 'tabla de migración ya creada'
print('OK 004: esquema 002 aceptado y 12 variantes incompatibles rechazadas con SELECT en memoria.')
