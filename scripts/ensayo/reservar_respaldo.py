"""Reserva una ruta de respaldo sin reemplazar archivos existentes."""
import hashlib
import json
import os
import pathlib
import sys
from datetime import datetime, timezone


def registrar(ruta: pathlib.Path, evento: dict) -> None:
    with ruta.open('a', encoding='utf-8') as evidencia:
        evidencia.write(json.dumps(evento, ensure_ascii=False, sort_keys=True) + '\n')


def reservar(destino: pathlib.Path, raiz: pathlib.Path) -> pathlib.Path:
    destino = destino.resolve(strict=False)
    raiz = raiz.resolve(strict=True)
    if destino.parent != raiz or destino.name in ('', '.', '..'):
        raise ValueError('Destino fuera del área privada')
    destino.mkdir(mode=0o700)
    marcador = destino / 'ola1.sql.reserve'
    descriptor = os.open(marcador, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    os.close(descriptor)
    (destino / 'evidencia.jsonl').open('x', encoding='utf-8').close()
    registrar(destino / 'evidencia.jsonl', {
        'evento': 'reserva_exclusiva', 'utc': datetime.now(timezone.utc).isoformat(),
        'archivo': 'ola1.sql',
    })
    return marcador


def publicar(directorio: pathlib.Path) -> pathlib.Path:
    directorio = directorio.resolve(strict=True)
    marcador = directorio / 'ola1.sql.reserve'
    temporal = directorio / 'ola1.sql.tmp'
    final = directorio / 'ola1.sql'
    if not marcador.is_file() or not temporal.is_file() or final.exists():
        raise ValueError('Reserva, temporal o destino final inválido')
    os.rename(temporal, final)  # en Windows falla si el destino ya existe
    contenido = final.read_bytes()
    marcador.unlink()
    registrar(directorio / 'evidencia.jsonl', {
        'evento': 'publicacion', 'utc': datetime.now(timezone.utc).isoformat(),
        'archivo': final.name, 'tamano': len(contenido),
        'sha256': hashlib.sha256(contenido).hexdigest(),
    })
    return final


if __name__ == '__main__':
    try:
        if len(sys.argv) == 3 and sys.argv[1] == '--publicar':
            salida = publicar(pathlib.Path(sys.argv[2]))
            print(f'Respaldo publicado sin reemplazo: {salida}')
        elif len(sys.argv) == 2:
            marcador = reservar(pathlib.Path(sys.argv[1]), pathlib.Path('C:/ensayo_privado/cortes'))
            print(f'Reserva exclusiva creada: {marcador}')
        else:
            raise ValueError('Argumentos inválidos')
    except (IndexError, OSError, ValueError):
        print('No se reservó ni publicó el respaldo.')
        raise SystemExit(1)
