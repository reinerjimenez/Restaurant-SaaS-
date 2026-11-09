"""Verifica contratos del instalador sin ejecutar SQL ni conectar una BD."""
from pathlib import Path
import json
import re

raiz = Path(__file__).resolve().parents[3]
sql = (raiz / "Database/002_produccion_limpia.sql").read_text(encoding="utf-8-sig")
match = re.search(r"CREATE TABLE `comprobantes` \((.*?)\n\).*?;", sql, re.S)
bloque = match.group(1) if match else ""
columnas = set(re.findall(r"^\s*`([^`]+)`", bloque, re.M))
requeridas = {"id", "restaurante_id", "pedido_id", "usuario_id", "items_json", "pagos_json", "created_at"}
primaria = bool(re.search(r"ALTER TABLE `comprobantes`\s+ADD PRIMARY KEY", sql))
resultado = {"conexion_bd": False, "faltan_columnas_comprobantes": sorted(requeridas-columnas), "pk_comprobantes_declarada": primaria, "tablas": len(re.findall(r"CREATE TABLE", sql)), "inserts": re.findall(r"^INSERT INTO `([^`]+)`", sql, re.M)}
print(json.dumps(resultado, ensure_ascii=False, indent=2))
raise SystemExit(1 if requeridas-columnas or not primaria else 0)
