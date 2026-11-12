"""Valida una hoja privada de autorización sin abrir conexiones SQL."""
import hashlib
import json
import pathlib
import sys


def validar(hoja: pathlib.Path, sql: pathlib.Path, manifiesto: pathlib.Path) -> bool:
    permiso = json.loads(hoja.read_text(encoding="utf-8"))
    vm = json.loads(manifiesto.read_text(encoding="utf-8"))
    campos = ("id", "operador", "vm_uuid", "hostname", "puerto", "esquema",
              "sql_sha256", "respaldo_sha256", "fecha", "ventana", "preflight",
              "aprobacion_expresa")
    if not all(permiso.get(campo) for campo in campos):
        return False
    if permiso["vm_uuid"] != vm.get("vm_uuid") or permiso["hostname"] != vm.get("hostname"):
        return False
    if permiso["puerto"] != 3306 or permiso["esquema"] != "restaurant_ola1_test":
        return False
    if permiso["sql_sha256"].lower() != hashlib.sha256(sql.read_bytes()).hexdigest():
        return False
    if not isinstance(permiso["respaldo_sha256"], str) or len(permiso["respaldo_sha256"]) != 64:
        return False
    if sql.name.startswith("006_") and permiso.get("004_verificada") is not True:
        return False
    return sql.name.startswith(("004_", "006_"))


if __name__ == "__main__":
    try:
        valido = validar(*(pathlib.Path(x) for x in sys.argv[1:4])) if len(sys.argv) == 4 else False
    except (OSError, ValueError, KeyError, TypeError):
        valido = False
    print("Autorización válida para revisión manual." if valido else "Autorización incompleta o inválida.")
    raise SystemExit(0 if valido else 1)
