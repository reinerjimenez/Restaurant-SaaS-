# Respaldo, restauración y conciliación de Ola 1

**Dictamen de recuperación: BLOQUEADA.** No existe VM MariaDB disponible ni evidencia de un corte ejecutado. El respaldo, la importación y las comparaciones quedan pendientes. La restauración necesita autorización para escribir exclusivamente en `restaurant_ola1_restore`.

La revisión P-03 detectó que la comprobación previa de existencia no reservaba la ruta de forma atómica. `reservar_respaldo.py` crea ahora un directorio y un marcador exclusivos; el volcado se genera como temporal y se publica mediante renombrado en Windows que falla si `ola1.sql` ya existe. La evidencia JSONL registra reserva, publicación, tamaño y SHA-256. La importación manual en phpMyAdmin queda sustituida por `restaurar_respaldo.php`, que valida el archivo, comprueba VM/servidor/cuenta/esquema por conexión, adquiere un bloqueo de aplicación y verifica que el destino no tenga objetos antes de importar. Deben estar retirados los permisos de escritura de cualquier otra cuenta sobre el destino durante el ensayo: el bloqueo serializa las restauraciones controladas y no bloquea clientes SQL ajenos. [Remediación del paquete](18_remediacion_paquete_ensayo.md) y [dictamen P-03](20_validacion_respaldo_ola1.md).

El formato restringido admitido por `scripts/ensayo/validar_respaldo.py` contiene únicamente `CREATE TABLE` InnoDB e `INSERT INTO ... VALUES` sobre la lista exacta de tablas del manifiesto privado del corte. Comprueba SHA-256, tamaño, nombres de tabla, ausencia de comentarios SQL y ausencia de sentencias adicionales o referencias calificadas a esquemas. Admite opciones de tabla InnoDB con `AUTO_INCREMENT`, charset y collation. La prueba unitaria usa SQL escrito a mano y no reproduce ni acredita la salida de MariaDB. No aceptar instrucciones de sesión, directivas condicionales ni otras sentencias: generar el archivo con las opciones `--compact --skip-extended-insert` y comprobar la salida íntegra. La compatibilidad con MariaDB 10.4 sigue pendiente de exportación real.

Para conciliar, ejecutar `php scripts/ensayo/ejecutar_lecturas.php origen` y después `php scripts/ensayo/ejecutar_lecturas.php destino`. Cada archivo usa nombres de tabla calificados para su esquema. Registrar los resultados y comparar únicamente después de verificar que cada conexión llegó al servidor y base autorizados.

## Procedimiento reproducible

1. Confirmar con [verificar_aislamiento.sql](../../scripts/ensayo/verificar_aislamiento.sql) que origen y destino son los esquemas correctos de la misma VM aislada y que la cuenta de restauración carece de permisos sobre el origen. Detener Apache y otras escrituras de ensayo, registrar fecha/hora y capturar el inventario, las versiones, los IDs sintéticos y [consultas de conciliación](../../tests/integration/conciliacion.sql) del corte. El corte base previo a 004 usa el inventario y conteos básicos de [diagnostico_esquema.sql](../../tests/integration/diagnostico_esquema.sql), ya que todavía carece de objetos de Ola 1.
2. Crear una carpeta privada nueva por corte con `python scripts/ensayo/reservar_respaldo.py C:/ensayo_privado/cortes/CORTE_NUEVO`. El programa reserva el directorio y `ola1.sql` con creación exclusiva. Con ACL solo para operador y restaurador. Registrar inventario autorizado de tablas desde la conexión de solo lectura.
3. Usar el cliente MariaDB 10.4 de la VM con permisos de lectura sobre el origen. `--password` solicita el secreto sin pasarlo en argumentos. El archivo temporal debe estar en el directorio reservado. La selección de tablas se obtiene del inventario y no puede contener comodines ni nombres con esquema.

   ```powershell
   $directorio = 'C:\ensayo_privado\cortes\CORTE_NUEVO'
   $temporal = Join-Path $directorio 'ola1.sql.tmp'
   & C:\xampp\mysql\bin\mysqldump.exe --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=ola1_origen_ro --password --single-transaction --quick --skip-lock-tables --compact --skip-triggers --skip-extended-insert --default-character-set=utf8mb4 --result-file=$temporal restaurant_ola1_test
   if ($LASTEXITCODE -ne 0) { throw 'El volcado falló. Conservar la salida para diagnóstico.' }
   python scripts/ensayo/reservar_respaldo.py --publicar $directorio
   Get-Item -LiteralPath (Join-Path $directorio 'ola1.sql') | Select-Object Length, LastWriteTime
   Get-FileHash -LiteralPath (Join-Path $directorio 'ola1.sql') -Algorithm SHA256
   ```

4. Crear el manifiesto privado con origen `restaurant_ola1_test`, destino `restaurant_ola1_restore`, inventario exacto de tablas y tamaño/SHA-256 observados. `validar_respaldo.py ola1.sql corte.json` verifica SQL, inventario, tamaño y hash. Un resultado positivo solo habilita preflight; no declara compatibilidad del motor.
5. Ejecutar en CLI `php scripts/ensayo/restaurar_respaldo.php <ruta-privada>/ola1.sql <ruta-privada>/corte.json`, con `OLA1_RESTAURADOR_PASS` configurada temporalmente. Antes de importar, el proceso verifica la identidad de VM, versión, host, `server_id`, datadir, cuenta autorizada y esquema mediante PDO; bajo `GET_LOCK` consulta `information_schema.TABLES` en la conexión destino y exige cero objetos. El bloqueo se mantiene hasta concluir; las demás cuentas deben carecer de escritura sobre el esquema. La importación nunca incluye `DROP`, `USE`, DDL de esquema o SQL fuera de la lista validada. Ante error puede quedar un destino parcial: no repetir encima; registrar evidencia y solicitar una nueva base descartable de ensayo conforme a la [guía](../operaciones/preparacion_entorno_ola1.md).
6. Comparar con las cuentas de lectura de cada esquema. Exportar el inventario de tablas, columnas, tipos, PK/FK, índices, restricciones y motores con las mismas consultas de metadatos de la fase 2, adaptando el literal de esquema al destino ya verificado. Ejecutar `conciliacion.sql` en ambos y comparar conteos exactos, importes por restaurante/turno/método/estado, huella del cierre, inconsistencias de relaciones y auditoría. Usar la misma zona horaria SQL. Para la igualdad de filas, generar una exportación adicional de solo datos en cada base con el mismo cliente y opciones `--no-create-info --skip-triggers --skip-comments --skip-dump-date --order-by-primary --skip-extended-insert --hex-blob`, además de las opciones de conexión y de bloqueo del paso 3. Comparar hashes de esas dos exportaciones. Si difieren, comparar filas por PK y registrar cada diferencia. Preservar la copia conciliada sin accesos web que escriban registros.
7. Para cierres nuevos, verificar bruto de pagos menos reversos del turno contra `total_general`, y comparar los gastos por separado con el resumen persistido. Para cierres previos a 004, comparar los importes históricos tal como están. Comprobar conteos y filas de auditoría, comprobantes, líneas e idempotencia. Registrar también cruces de restaurante y cualquier diferencia por ID. Una diferencia financiera o relacional sin explicar impide aprobar.

## Registro de evidencia

| Campo | Resultado real |
|---|---|
| Responsable, fecha, versión, corte de origen | Pendiente |
| Archivo, tamaño, método y SHA-256 | Pendiente |
| Inicio, fin y duración de respaldo | Pendiente |
| Inicio, fin y duración de restauración | Pendiente |
| Hora de conciliación y recuperación utilizable | Pendiente |
| Tablas, conteos exactos, índices y FK comparados | Pendiente |
| Pagos, reversos, gastos, turnos, cierres y comprobantes | Pendiente |
| Auditoría, relaciones, huellas y diferencias | Pendiente |
| RPO/RTO objetivo y medición | Objetivos por acordar. Sin medición. |

Actualizar el dictamen únicamente con exportación MariaDB real, restauración controlada y conciliación completa. Mantener el origen y los respaldos existentes intactos. [Procedimiento operativo de respaldo](../operaciones/backup_restore.md).
