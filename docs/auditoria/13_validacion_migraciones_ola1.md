# Validación de migraciones de Ola 1

**Estado real de 004 y 006: BLOQUEADA.** Se revisaron completos el instalador 002 y los scripts 003 a 007. El contraste usa la estructura versionada/local y el código PHP. Quedan pendientes las consultas en la instancia aislada y la autorización de las importaciones. MariaDB 10.4.32 es la versión del binario XAMPP observado.

Cada migración requiere una hoja de autorización independiente según [el paquete actualizado](../operaciones/paquete_entorno_ola1.md). Identificar operador, VM, host, puerto, esquema, hash del SQL y del respaldo, fecha, ventana y preflight. La autorización de 006 exige evidencia real de que 004 se ejecutó y verificó. Un cambio de SQL, servidor, esquema o respaldo invalida la hoja anterior.

Ejecutar el diagnóstico previo mediante `scripts/ensayo/ejecutar_lecturas.php esquema` con la cuenta lectora de origen y el manifiesto privado verificado. El SQL de integración no debe importarse directamente en phpMyAdmin.

Se repitió `python tests/ola_1_preflight_004.py`. La evaluación de predicados con SELECT en memoria aceptó los metadatos de 002 y rechazó 12 variantes incompatibles. No se importó el SQL ni se verificaron el DDL, el cliente de importación o InnoDB.

## Esquema esperado

002 crea 18 tablas InnoDB con `utf8mb4` y el usuario inicial. 004 requiere 16 de esas tablas y sus columnas enumeradas en la guarda. `categorias` y `comprobante_items` completan el esquema de aplicación aunque la guarda de 004 no las enumere. 002 incluye `usuarios.session_version INT NOT NULL DEFAULT 1`, por lo que 003 solo aplica a una instalación anterior que carezca de la columna. Revisar especialmente los índices previos `uq_turno_abierto_por_restaurante` sobre `turnos._turno_abierto_key`, `uk_mesa_numero`, `uk_comprobante` y la FK de `pedidos.mesa_id` con `ON DELETE SET NULL`. Los cuatro identificadores referenciados por 004 (`pagos`, `turnos`, `usuarios` y `restaurantes`) son `INT` con signo y PK individual en 002.

| Avance y objeto | Tipo, claves y restricciones | Consumidor |
|---|---|---|
| 004 · `pagos.turno_id` | `INT NULL`, índice `idx_pagos_turno`, FK `fk_pagos_turno` a `turnos.id`. Sin reasignar pagos antiguos. | `config/finanzas.php`, `includes/reportes_financieros.php`. |
| 004 · `gastos.turno_id` | `INT NULL`, índice `idx_gastos_turno`, FK `fk_gastos_turno` a `turnos.id`. Sin reasignar gastos antiguos. | `includes/gastos_financieros.php`, `roles/admin/gastos.php`, resumen de caja. |
| 004 · `turnos.cierre_json` | `LONGTEXT NULL`, `utf8mb4_bin`, `CHECK(JSON_VALID(cierre_json))`. Cierres antiguos quedan en NULL. | `api/cerrar_turno.php`, `roles/atencion/historial.php`, reportes. |
| 004 · `reversos_pago` | InnoDB. `pago_id INT NOT NULL` PK, `turno_id INT NOT NULL` indexado, `usuario_id INT NOT NULL`, FK a sus tres tablas. `motivo VARCHAR(200) NOT NULL`, `venta_json LONGTEXT NULL utf8mb4_bin` con CHECK JSON y `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`. | Reapertura, anulación de venta y resumen por turno mediante `config/finanzas.php`. |
| 004 · `envios_comanda` | InnoDB. `restaurante_id INT`, `usuario_id INT`, `operacion_id CHAR(32) ascii_bin` forman PK y son NOT NULL. `huella CHAR(64) ascii_bin NOT NULL`, `resultado_json LONGTEXT utf8mb4_bin NOT NULL` con CHECK JSON, `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`. FK solo de restaurante. | `api/agregar_item.php`, idempotencia. |
| 006 · `login_limites` | InnoDB. `clave CHAR(64) ascii_bin NOT NULL` PK, `inicio BIGINT UNSIGNED NOT NULL` indexado por `idx_login_limites_inicio`, `fallos INT UNSIGNED NOT NULL DEFAULT 0` y `proximo_en BIGINT UNSIGNED NOT NULL DEFAULT 0`. Sin FK ni datos iniciales. | `config/limite_login.php`, llamado por `auth/login.php`. |

Los cinco DDL de 004 están condicionados al resultado de la guarda. La estructura usa `SELECT`, `ALTER TABLE` y `CREATE TABLE` preparables en MariaDB 10.4 y comprobaciones `JSON_VALID`. La compatibilidad descrita es de diseño y sigue sin validarse en el motor local. [Sentencias preparadas](https://mariadb.com/docs/server/reference/sql-statements/prepared-statements/prepare-statement), [JSON_VALID](https://mariadb.com/docs/server/reference/sql-functions/special-functions/json-functions/json_valid).

## Diferencias y riesgos

- La guarda comprueba presencia de columnas, los cuatro tipos/PK para FK, InnoDB y ausencia de objetos de avance. No compara los tipos, valores predeterminados y relaciones de todas las columnas base ni detecta todos los nombres de índices o FK en conflicto. Comparar **todo el inventario** contra 002 o el esquema previo aprobado y detener el corte si hay discrepancias. Las FK nuevas unen identificadores, sin validar por sí mismas que pedido, turno y reverso pertenezcan al mismo restaurante.
- 004 lee turnos abiertos antes de sus DDL. Detener Apache, tareas y otros clientes de escritura durante preflight y migración. `ALTER TABLE` puede esperar por metadatos o reconstruir tablas. Registrar esperas, espacio libre y duración. El DDL tiene commit implícito, así que un error intermedio puede dejar una instalación parcial. Tomar respaldo y demostrar recuperación antes de iniciar. [Confirmaciones implícitas](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).
- 006 falla si la tabla ya existe y no valida una instalación previa. Cargar el login nuevo tras comprobar la tabla e índices. No cambiar credenciales ni poblar contadores manualmente.
- 005 invierte los cinco cambios de 004 en orden compatible con las FK. Requiere una instalación completa y guarda contra turno abierto, JSON de cierre, pagos/gastos vinculados, reversos y comandas. Sobre un esquema parcial puede fallar antes de informar la guarda. Requiere mantenimiento y no debe utilizarse para revertir ventas ya registradas.
- 007 elimina únicamente `login_limites` y sus contadores. Es coherente con 006 para un código anterior que no consuma la tabla, con accesos detenidos. Se pierde el estado temporal de limitación. **005 y 007 no forman parte del ensayo de avance.** Se revisaron por lectura.

## Consultas y procedimiento manual

1. Identificar la VM, su directorio de datos y `restaurant_ola1_test` con [el paquete de entorno](../operaciones/paquete_entorno_ola1.md). Importar 002 manualmente tras autorización para preparar la base. Conservar la huella de 002, 004 y 006 y el corte base restaurable.
2. Ejecutar [diagnostico_esquema.sql](../../tests/integration/diagnostico_esquema.sql) con la cuenta migradora antes de 004. Exporta tablas, motores, columnas con tipos y defaults, índices con orden, FK con reglas, CHECK, conteos y totales previos. Esperar 18 tablas en la base limpia, 0 turnos abiertos y ausencia de los objetos de 004 y 006. Comparar los nombres y columnas que exige la guarda directamente con el SQL de 004. Conservar las consultas y salidas.
3. Con **autorización expresa** y respaldos verificados, importar 004 entero desde phpMyAdmin en una sola conexión sobre la base de ensayo. Esperar `Precondiciones de corte satisfechas` y ninguna omisión. Repetir el inventario. Debe haber 20 tablas y las tres columnas agregadas. Comprobar con las consultas siguientes los registros heredados antes de abrir la aplicación.

   ```sql
   SELECT COUNT(turno_id) AS legados_con_turno FROM pagos;
   SELECT COUNT(turno_id) AS legados_con_turno FROM gastos;
   SELECT COUNT(cierre_json) AS legados_con_json FROM turnos;
   SELECT COUNT(*) AS reversos FROM reversos_pago;
   SELECT COUNT(*) AS envios FROM envios_comanda;
   ```

   Los cinco conteos deben ser 0 inmediatamente después de 004. Comparar conteos e importes previos contra los posteriores. Para demostrar conservación con filas preexistentes, repetir sobre una rama desechable del ensayo con movimientos sintéticos anteriores a 004 y turnos cerrados. Para probar la guarda negativa, usar otra rama con un turno abierto o dependencia ausente y demostrar ausencia de las cinco modificaciones antes y después.
4. Confirmar que `login_limites` está ausente y, bajo la misma autorización de avance, importar 006 manualmente en `restaurant_ola1_test`. Repetir el inventario. Debe haber 21 tablas y `SELECT COUNT(*) FROM login_limites` debe devolver 0 antes del primer login.
5. Si hay errores o un inventario difiere, preservar evidencia, detener el ensayo y conciliar con el respaldo base. Registrar versión efectiva, usuario, duración, los tres inventarios y resultado de cada prueba en [el informe de ejecución](14_resultados_pruebas_mariadb_ola1.md). [Procedimiento de despliegue de Ola 1](../operaciones/despliegue_ola_1.md).
