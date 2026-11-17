"""Reserva una ruta de respaldo sin reemplazar archivos existentes."""
import hashlib
import json
import os
import pathlib
import sys
from datetime import datetime, timezone


def registrar(ruta: pathlib.Path, evento: dict) -> None:
    descriptor = os.open(ruta, os.O_CREAT | os.O_APPEND | os.O_WRONLY, 0o600)
    try:
        linea = (json.dumps(evento, ensure_ascii=False, sort_keys=True) + '\n').encode('utf-8')
        if os.write(descriptor, linea) != len(linea):
            raise OSError('Escritura incompleta de evidencia')
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def reservar(destino: pathlib.Path, raiz: pathlib.Path) -> pathlib.Path:
    destino = destino.resolve(strict=False)
    raiz = raiz.resolve(strict=True)
    if destino.parent != raiz or destino.name in ('', '.', '..'):
        raise ValueError('Destino fuera del área privada')
    destino.mkdir(mode=0o700)
    marcador = destino / 'ola1.sql.reserve'
    descriptor = os.open(marcador, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    os.close(descriptor)
    descriptor = os.open(destino / 'evidencia.jsonl', os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    os.close(descriptor)
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
    contenido = temporal.read_bytes()
    evidencia = directorio / 'evidencia.jsonl'
    evento = {'utc': datetime.now(timezone.utc).isoformat(), 'archivo': final.name,
              'tamano': len(contenido), 'sha256': hashlib.sha256(contenido).hexdigest()}
    registrar(evidencia, {'evento': 'publicacion_iniciada', **evento})
    if os.name == 'nt':
        os.rename(temporal, final)  # MoveFileEx sin reemplazo en Windows
    else:
        os.link(temporal, final)  # enlace POSIX con creación exclusiva
        temporal.unlink()
    marcador.unlink()
    publicado = final.read_bytes()
    if len(publicado) != evento['tamano'] or hashlib.sha256(publicado).hexdigest() != evento['sha256']:
        raise OSError('El archivo publicado difiere del temporal verificado')
    registrar(evidencia, {'evento': 'publicacion_completada', **evento})
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
