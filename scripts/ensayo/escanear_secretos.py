"""Escaneo local sin imprimir coincidencias. Devuelve código 1 si requiere revisión."""
import argparse
import pathlib
import re
import subprocess

PATRONES = [
    re.compile(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    re.compile(rb"(?i)(?:password|passwd|token|secret|api[_-]?key|cookie|authorization)\s*[:=]\s*['\"]?[A-Za-z0-9_./+=-]{12,}"),
    re.compile(rb"\$2[aby]\$\d{2}\$[./A-Za-z0-9]{53}"),
]


def hay_secreto(contenido: bytes) -> bool:
    return any(p.search(contenido) for p in PATRONES)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("ruta", type=pathlib.Path)
    parser.add_argument("--historial", action="store_true")
    args = parser.parse_args()
    raiz = args.ruta.resolve(strict=True)
    hallazgos = 0
    archivos = 0
    excluidos = 0
    errores = 0
    historial = 0
    for archivo in raiz.rglob("*"):
        if ".git" in archivo.parts:
            excluidos += 1
            continue
        if not archivo.is_file():
            continue
        if not archivo.resolve().is_relative_to(raiz):
            excluidos += 1
            continue
        archivos += 1
        if archivo.stat().st_size > 20_000_000:
            excluidos += 1
            continue
        try:
            hallazgos += hay_secreto(archivo.read_bytes())
        except OSError:
            errores += 1
    if args.historial:
        lista = subprocess.run(["git", "rev-list", "--objects", "--all"], cwd=raiz,
                               capture_output=True, check=True).stdout.splitlines()
        for linea in lista:
            oid = linea.split(maxsplit=1)[0]
            tipo = subprocess.run(["git", "cat-file", "-t", oid], cwd=raiz,
                                  capture_output=True, check=True).stdout.strip()
            if tipo != b"blob":
                continue
            historial += 1
            tam = int(subprocess.run(["git", "cat-file", "-s", oid], cwd=raiz,
                                     capture_output=True, check=True).stdout)
            if tam > 20_000_000:
                excluidos += 1
                continue
            contenido = subprocess.run(["git", "cat-file", "blob", oid], cwd=raiz,
                                      capture_output=True, check=True).stdout
            hallazgos += hay_secreto(contenido)
    print(f"Archivos actuales revisados: {archivos}. Blobs históricos revisados: {historial}. "
          f"Excluidos: {excluidos}. Errores de lectura: {errores}. Coincidencias: {hallazgos}.")
    return 1 if hallazgos or errores or excluidos else 0


if __name__ == "__main__":
    raise SystemExit(main())
