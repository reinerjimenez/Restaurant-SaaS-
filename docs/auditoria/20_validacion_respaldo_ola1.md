# Validación de respaldo y restauración de Ola 1

**Dictamen P-03: NO APROBADO.** No hay una VM MariaDB 10.4 de ensayo disponible y no se ejecutó una exportación real. Las pruebas locales verifican reglas de aceptación y rechazo con SQL escrito a mano; no demuestran compatibilidad del exportador ni recuperación del motor.

## Qué acepta el validador

`scripts/ensayo/validar_respaldo.py` acepta solo sentencias completas de `CREATE TABLE` InnoDB e `INSERT INTO ... VALUES` para el inventario exacto del manifiesto. Comprueba nombre de origen y destino, tamaño positivo e idéntico al manifiesto, SHA-256, UTF-8, ausencia de comentarios SQL fuera de literales, ausencia de `@`, ausencia de nombres calificados y ausencia de cualquier instrucción que no sea CREATE/INSERT autorizado. Cada tabla debe tener un CREATE único antes de su INSERT. No ejecuta SQL ni inspecciona el destino; un resultado válido solo acredita el archivo.

Se amplió el reconocimiento de opciones de tabla para admitir `AUTO_INCREMENT`, charset y collation en la cláusula habitual de InnoDB. Sigue siendo un subconjunto y puede rechazar variaciones legítimas. Las pruebas unitarias escritas a mano cubren ejemplos representativos, no sustituyen un dump de MariaDB.

## Diferencias frente a una salida estándar de MariaDB 10.4

Un `mariadb-dump`/`mysqldump` estándar puede emitir comentarios de cabecera y cierre, directivas ejecutables de versión, `SET` de sesión/carácter, bloqueos, desactivación de claves y `DROP TABLE`. También puede emitir opciones de tabla como `AUTO_INCREMENT`, charset/collation no predeterminados, comentarios de columnas, múltiples filas por INSERT, o cláusulas de inserción de campos. El validador rechaza las instrucciones auxiliares y los comentarios, y no admite todas las variantes del DDL ni formatos de INSERT. No se debe relajar el rechazo a ciegas: hay que exportar con opciones que eviten las instrucciones auxiliares y luego comprobar la salida íntegra.

Para MariaDB, `--compact` desactiva `ADD DROP TABLE`, `ADD LOCKS`, comentarios, desactivación de claves y `SET CHARSET`; `--skip-extended-insert` emite un INSERT por fila. El procedimiento añade `--skip-triggers`, ya que triggers se incluyen por defecto y el validador no ejecuta `CREATE TRIGGER`. La documentación oficial describe esas opciones, pero esto no prueba que el binario concreto de MariaDB 10.4 produzca un archivo aceptado. Referencia: [mariadb-dump — documentación oficial](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).

## Procedimiento de compatibilidad cuando exista la VM

1. Confirmar aislamiento de la VM desde el hipervisor y ejecutar `verificar_entorno.php`/`diagnostico_mariadb.php`. El manifiesto privado debe incluir UUID de VM, hostname, ejecutable, PID, datadir, versión exacta y `mariadb_server_id`. El servidor consultado debe coincidir con esos datos. No conectarse a producción.
2. Preparar únicamente bases sintéticas `restaurant_ola1_test` y `restaurant_ola1_restore`, con identificadores y datos ficticios. No cargar datos reales ni ejecutar migraciones 004/006 o scripts 005/007. Restringir ACL del área privada a las cuentas de operador/restaurador.
3. Consultar el inventario exacto de tablas de origen con `ola1_origen_ro` y registrar columnas, motores y cantidad. Reservar un corte exclusivo:

   ```powershell
   python scripts/ensayo/reservar_respaldo.py C:/ensayo_privado/cortes/CORTE_NUEVO
   ```

   La operación crea el directorio por `mkdir` exclusivo, un marcador `ola1.sql.reserve` por `O_EXCL` y un `evidencia.jsonl`. El temporal de exportación será `ola1.sql.tmp`; el archivo final se publicará por renombrado atómico de Windows que falla si ya existe.

4. Exportar desde la VM con el cliente MariaDB 10.4 y la cuenta de solo lectura. `--password` solicita la credencial de ensayo de forma interactiva; no pasarla en argumentos ni guardar credenciales reales. El `--result-file` apunta al temporal del directorio reservado. Ajustar el ejecutable si la instalación lo llama `mariadb-dump.exe`:

   ```powershell
   $directorio = 'C:\ensayo_privado\cortes\CORTE_NUEVO'
   $temporal = Join-Path $directorio 'ola1.sql.tmp'
   & C:\xampp\mysql\bin\mysqldump.exe --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=ola1_origen_ro --password --single-transaction --quick --skip-lock-tables --compact --skip-triggers --skip-extended-insert --default-character-set=utf8mb4 --result-file=$temporal restaurant_ola1_test
   if ($LASTEXITCODE -ne 0) { throw 'Exportación fallida; preservar evidencia y no publicar.' }
   python scripts/ensayo/reservar_respaldo.py --publicar $directorio
   Get-Item -LiteralPath (Join-Path $directorio 'ola1.sql') | Select-Object Length, LastWriteTime
   Get-FileHash -LiteralPath (Join-Path $directorio 'ola1.sql') -Algorithm SHA256
   ```

   Registrar inicio, fin, duración, comando sin secreto, versión del cliente y salida/código de retorno. La publicación registra automáticamente hora UTC, tamaño y SHA-256. No modificar el original después de publicarlo.

5. Crear el manifiesto privado del corte con origen y destino fijos, lista de tablas observada, tamaño y SHA-256. Comparar su lista con el inventario de la conexión, no con una declaración informal. Ejecutar la prueba negativa local y la validación del archivo:

   ```powershell
   python scripts/ensayo/validar_respaldo.py $directorio\ola1.sql $directorio\corte.json
   ```

   Guardar el resultado y `evidencia.jsonl`. Un rechazo implica detenerse y estudiar la salida exacta antes de cambiar el validador.

6. Con el único usuario escritor de `restaurant_ola1_restore` como restaurador, retirar temporalmente cualquier otro permiso de escritura al destino. Configurar `OLA1_RESTAURADOR_PASS` solo en el proceso interactivo, confirmar que el manifiesto de VM incluye `mariadb_server_id`, y ejecutar:

   ```powershell
   php scripts/ensayo/restaurar_respaldo.php $directorio\ola1.sql $directorio\corte.json
   ```

   El proceso vuelve a verificar hash/tamaño, identidad de VM y servidor (UUID, host, versión, PID/datadir y `server_id`), cuenta autorizada y `DATABASE()`. Mantiene `GET_LOCK` en la misma conexión que consulta `information_schema.TABLES` y exige cero objetos antes de ejecutar. El bloqueo elimina la carrera entre esta comprobación y las restauraciones que usan el mismo control; las ACL/grants deben impedir escrituras de otros clientes durante la operación. No se aceptan `DROP`, `USE`, SQL de otras bases ni comandos auxiliares.

   El importador conserva el archivo original y verifica su hash/tamaño al finalizar. DDL hace commit implícito en MariaDB; si falla una sentencia, el destino puede quedar parcial. No repetir sobre ese esquema. Preservar evidencia y preparar un nuevo destino sintético vacío por el procedimiento autorizado. La evidencia JSONL registra éxito o error, tiempos, identidad, hash y cantidad de sentencias.

7. Ejecutar conciliación de esquema y datos entre origen y destino con cuentas de solo lectura. Comparar inventario, conteos, relaciones e importes sintéticos. Registrar las salidas, discrepancias y duración. Preservar la evidencia de manera inmutable.

## Resultado de esta revisión

| Control | Resultado actual |
|---|---|
| Reglas SQL, lista de tablas, tamaño y SHA-256 | Pruebas locales automatizadas con fixtures artesanales; no dump real |
| Reserva exclusiva y publicación sin reemplazo | Prueba local de sistema de archivos |
| Conexión autorizada, identidad de VM/servidor/esquema | Implementado en importador; sin ejecutar porque no hay VM |
| Destino vacío y control de carrera | Implementado con consulta PDO bajo `GET_LOCK`; sin ejecución real |
| Restauración y conciliación sintéticas | Pendientes |
| Original intacto y evidencia de operación | Verificación y registro implementados; sin ejecución real |
| Compatibilidad de MariaDB 10.4 | No demostrada |

**Cierre:** P-03 **NO APROBADO** hasta ejecutar el procedimiento completo con exportación auténtica de una base sintética MariaDB 10.4, validar el dump real, restaurarlo en destino vacío controlado y conciliarlo. Ningún resultado de pruebas locales se presentará como exportación real.
