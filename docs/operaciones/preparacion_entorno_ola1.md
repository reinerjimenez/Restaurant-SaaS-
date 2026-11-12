# Preparación del entorno real de ensayo de Ola 1

**Estado de la preparación.** Se inspeccionaron los binarios, la configuración local y los scripts del proyecto. La instancia aislada, las dos bases, las importaciones y las pruebas de aceptación quedan pendientes de ejecución manual autorizada. El dictamen de la Ola 1 sigue siendo **NO APTO PARA PRODUCCIÓN**. [Informe de aceptación](../auditoria/12_aceptacion_integral_ola_1.md).

## 1. Inventario y requisitos

| Componente | Evidencia observada en el equipo |
|---|---|
| Servidor instalado | `C:\xampp\mysql\bin\mysqld.exe --no-defaults --version` devolvió **10.4.32-MariaDB, Win64 AMD64**. Los binarios `mysql.exe` y `mysqldump.exe` también reportaron distribución 10.4.32. |
| Servidor en ejecución | Las consultas a procesos y puertos no mostraron un proceso MariaDB de XAMPP ni un listener en 3306 durante la inspección. Versión, instancia y variables efectivas por SQL **pendientes**. |
| MariaDB en disco | `C:\xampp\mysql\bin\my.ini` define puerto 3306, datos en `C:/xampp/mysql/data`, opciones de InnoDB, `utf8mb4`, `utf8mb4_general_ci` y `sql_mode=NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION`. La línea `bind-address` está comentada. |
| PHP | `C:\xampp\php\php.exe` reportó **8.2.35 CLI**, con PDO, `pdo_mysql`, `mysqli` y `session`. Comprobar los módulos de la instancia que sirve la aplicación. |
| Apache y phpMyAdmin | El Apache del equipo tiene `Listen 80`, `AllowOverride All` en htdocs y el alias de phpMyAdmin con `Require local`. Su configuración de phpMyAdmin apunta a `127.0.0.1` por TCP, usa autenticación `config` y permite contraseñas vacías. La copia de ensayo debe usar autenticación `cookie` y cuentas con contraseña. |
| Proyecto | HEAD local `393a2e9` más cambios de Ola 0 y Ola 1. Registrar hashes SHA-256 de 002, 004 y 006 y la versión de la copia de trabajo empleada. |

Usar una **máquina virtual Windows desechable**, con una instalación independiente de XAMPP, un directorio de datos inicializado para esa instancia y navegador dentro de la VM. Preparar el instalador y la copia local de la aplicación antes de desconectar la VM. Mantener el adaptador virtual desconectado, sin NAT, puente, reenvío de puertos, VPN ni carpetas compartidas con datos o secretos. Usar el XAMPP 10.4.32 para reproducir el entorno local. La serie 10.4 terminó su mantenimiento comunitario. La compatibilidad con el servidor de destino se debe verificar aparte con su versión y configuración. [Política de mantenimiento de MariaDB Community Server](https://mariadb.com/docs/release-notes/community-server/about/maintenance-policy).

Dentro de la VM, configurar MariaDB con `bind-address=127.0.0.1` y puerto 3306. Revisar todos los `Listen` efectivos de Apache, incluido el archivo SSL, y restringirlos a loopback. Servir la copia en `/system-restaurant-ola1`. Mantener phpMyAdmin en loopback, con `Require local`, un único servidor TCP `127.0.0.1:3306`, `auth_type=cookie`, `AllowNoPassword=false` y sin selección arbitraria de servidor. Para la prueba de cookies `Secure`, habilitar HTTPS local con un certificado de ensayo confiable dentro de la VM y con el SAN correspondiente al nombre o IP usado en el navegador.

## 2. Bases, cuentas y configuración privada

| Base | Uso | Acceso |
|---|---|---|
| `restaurant_ola1_test` | Instalación nueva, importación 002 → 004 → 006, datos sintéticos y pruebas A–F. | Operador local para importación. Cuenta `ola1_app` con `SELECT, INSERT, UPDATE, DELETE` limitados a este esquema. |
| `restaurant_ola1_restore` | Destino vacío para comprobar la restauración del respaldo de ensayo. | Operador local para importación y una cuenta distinta `ola1_restore_ro` de solo lectura para conciliar. |

1. Tras autorización, iniciar únicamente los servicios de la VM. Verificar adaptadores, listeners e identidad de la instancia según la sección 3 antes de crear las bases. En phpMyAdmin de la VM, un administrador local crea manualmente las dos bases con `utf8mb4` y `utf8mb4_general_ci`. Crear las cuentas con contraseñas únicas de ensayo, restringidas al host local que autentica la conexión TCP. Confirmarlo con `CURRENT_USER()`. El operador usa permisos de estructura e importación solo en los esquemas de ensayo. La cuenta web carece de DDL y de acceso a restauración. Verificar con `SHOW GRANTS` que los permisos sean exactos. En un permiso de nivel de base, los guiones bajos deben escaparse para evitar que actúen como comodines, por ejemplo `restaurant\_ola1\_test`.*. [Comodines en permisos de base](https://mariadb.com/docs/maxscale/maxscale-security/authentication-modules).
2. Copiar los archivos de aplicación del árbol local a la VM, excluyendo `.env`, copias de seguridad, datos de XAMPP, credenciales, `.git` y archivos ajenos a la aplicación. Preservar `.htaccess`. Mantener los SQL de instalación, volcados y evidencia fuera de htdocs. El archivo `.env` está ignorado por Git en el proyecto y se crea **solo en la raíz de la copia de ensayo**, con permisos NTFS limitados. Guardar las contraseñas en un gestor de secretos y usar en la VM únicamente las de ensayo. No incorporar un `.env` de ensayo al repositorio.

   ```dotenv
   DB_HOST=127.0.0.1
   DB_NAME=restaurant_ola1_test
   DB_USER=ola1_app
   DB_PASS=<contraseña única de ensayo>
   DB_CHARSET=utf8mb4
   BASE_URL=/system-restaurant-ola1
   APIS_NET_TOKEN=
   ```

   La aplicación lee `.env` desde su raíz. Las variables ya definidas en `$_SERVER` o `$_ENV` tienen prioridad. Revisar el entorno del proceso de Apache y cualquier `SetEnv` heredado, en especial `DB_*`, `BASE_URL` y `APIS_NET_TOKEN`. El DSN usa el puerto predeterminado del cliente si no se indica uno y el código carece de una variable `DB_PORT`. Usar 3306 en la VM y verificarlo en SQL. Si faltan variables, el código puede usar `localhost`, `restaurante_db` y `root`; asegurar que el administrador local tenga contraseña y que en la VM solo existan los esquemas de aplicación de ensayo. El token vacío habilita ingreso manual en el flujo de consulta de documento. Usar comprobantes de ensayo y evitar llamadas externas. [Configuración PDO](../../config/db.php), [consulta de documento](../../api/consultar_documento.php).
3. La conciliación usa el cliente SQL o phpMyAdmin con la cuenta de lectura de restauración. Mantener la configuración web apuntando a `restaurant_ola1_test`. Un login escribe registros y contadores, así que cualquier prueba web sobre una copia restaurada requiere una copia de trabajo aparte después de conservar la evidencia de conciliación.

## 3. Verificar identidad, motor y aislamiento

Completar esta lista en la VM **antes de cualquier importación o arranque de la aplicación**. Repetir la identidad del destino en cada importación.

1. Confirmar desde el hipervisor el adaptador desconectado. Dentro de la VM, revisar `Get-NetAdapter`, `route print` y `netstat -ano -p tcp`. Registrar ausencia de conectividad externa y que Apache y MariaDB escuchen solo en loopback. Verificar PID, binario y directorio de datos de la VM. Esta comprobación no requiere probar ninguna dirección de producción.
2. Conectarse por TCP a `127.0.0.1:3306` desde la VM y ejecutar estas consultas de lectura. En la primera comprobación, antes de crear las bases, `DATABASE()` puede ser `NULL`. Confirmar versión efectiva, `datadir` propio, `InnoDB` disponible con soporte de transacciones, modo SQL y variables requeridas. Después de crear las bases, repetir identidad y permisos con cada cuenta limitada y su esquema seleccionado. Los nombres de bases de sistema visibles dependen de los permisos. Como prueba negativa dentro de la VM, intentar seleccionar `restaurant_ola1_restore` con `ola1_app` y registrar la denegación. La cuenta web debe poder seleccionar solo el esquema de ensayo entre las bases de aplicación.

   ```sql
   SELECT VERSION() AS version_servidor, @@hostname AS servidor, @@port AS puerto,
          @@datadir AS directorio_datos, DATABASE() AS base, CURRENT_USER() AS cuenta;
   SHOW ENGINES;
   SHOW GRANTS;
   SHOW VARIABLES WHERE Variable_name IN (
       'bind_address', 'default_storage_engine', 'sql_mode', 'foreign_key_checks',
       'check_constraint_checks', 'transaction_isolation', 'tx_isolation',
       'innodb_lock_wait_timeout', 'character_set_server', 'collation_server', 'time_zone',
       'max_prepared_stmt_count'
   );
   SELECT JSON_VALID('{}') AS json_valido, JSON_VALID('{') AS json_invalido;
   ```

3. Confirmar que `.env` no se sirve por HTTP. Hacer una solicitud HEAD al `.env` de la copia aislada y comprobar denegación o ausencia (403 o 404), sin capturar su contenido. Tras aplicar los scripts, hacer el primer login sintético y comprobar que sus registros de acceso aparecen en `restaurant_ola1_test`. Registrar esquema, cuenta, versión, host, puerto y resultado de las verificaciones, excluyendo secretos y cookies. Si falla cualquier control de aislamiento, detener el ensayo.

## 4. Importación manual en phpMyAdmin

1. Con la aplicación detenida y `restaurant_ola1_test` vacía, importar [002_produccion_limpia.sql](../../Database/002_produccion_limpia.sql). El instalador crea el esquema base y un superadmin inicial cuyo hash es un marcador. Antes del primer login, sustituir manualmente el correo por uno de ensayo y el marcador por un hash bcrypt generado localmente con `password_hash` para una contraseña exclusiva de ensayo. Verificar que `usuarios.session_version` ya existe en 002. La [003](../../Database/003_session_version_y_restaurante_activo.sql) solo se necesita si el esquema anterior carece de esa columna.
2. Confirmar cero turnos abiertos (`SELECT COUNT(*) FROM turnos WHERE fin IS NULL`). Verificar los **16 InnoDB** requeridos por 004: `restaurantes`, `usuarios`, `mesas`, `pedidos`, `pedido_items`, `pedido_item_opciones`, `pagos`, `gastos`, `turnos`, `comprobantes`, `auditoria`, `facturacion_config`, `logs_acceso`, `productos`, `opciones_grupo` y `opciones_valor`. La lista de columnas requeridas está en la guarda del archivo. Los `id` de `pagos`, `turnos`, `usuarios` y `restaurantes` deben ser `INT` con signo y claves primarias de una sola columna. Deben estar ausentes las tres columnas y las dos tablas que agrega 004, y `login_limites` para 006.
3. Tomar el respaldo base con la aplicación detenida y verificar su restauración en `restaurant_ola1_restore` según la sección 6. Para que vuelva a estar vacía antes de la restauración final, conservar un punto de control de la VM con los dos esquemas preparados, el volcado base guardado y el destino aún vacío. Tras conciliar la primera restauración, conservar la evidencia y volver a ese punto **antes** de aplicar 004. El punto de control no sustituye la restauración desde SQL.
4. En la barra de phpMyAdmin, confirmar servidor y base. Seleccionar **Importar → SQL** y cargar [004_ola_1_finanzas.sql](../../Database/004_ola_1_finanzas.sql) completo. Mantener la ejecución en una sola conexión por las variables de sesión y las sentencias preparadas. Confirmar el mensaje `Precondiciones de corte satisfechas`, revisar todos los mensajes y verificar las cinco modificaciones en metadatos. Un mensaje `BLOQUEADO` o `Omitido por precondición` deja el paso sin aprobar aunque phpMyAdmin termine de procesar el archivo.
5. En el mismo esquema y con la aplicación aún detenida, importar [006_ola_1_intentos_login.sql](../../Database/006_ola_1_intentos_login.sql). Confirmar la creación de `login_limites` y revisar sus índices. Este script crea una tabla vacía y no necesita datos iniciales. Los scripts 005 y 007 quedan excluidos de todo el ensayo.
6. Si un DDL falla, guardar salida y esquema y detener el avance. Los cambios de estructura tienen confirmaciones implícitas. Una importación parcial se debe investigar y repetir desde una copia de ensayo limpia. [Sentencias preparadas](https://mariadb.com/docs/server/reference/sql-statements/prepared-statements/prepare-statement), [confirmaciones implícitas](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).

### Verificaciones posteriores

| Objeto | Resultado esperado |
|---|---|
| `pagos.turno_id`, `gastos.turno_id` | `INT NULL`, índices `idx_pagos_turno` y `idx_gastos_turno`, FK a `turnos.id`. Los movimientos preexistentes conservan filas e importes y quedan con turno `NULL`. |
| `turnos.cierre_json` | `LONGTEXT NULL` con comprobación `JSON_VALID`. Cierres preexistentes quedan en `NULL`. Se conserva la restricción de un turno abierto por restaurante. |
| `reversos_pago` | InnoDB, PK `pago_id`, índice de turno y FK a pago, turno y usuario. `venta_json` con comprobación JSON. |
| `envios_comanda` | InnoDB, PK `(restaurante_id, usuario_id, operacion_id)`, FK de restaurante, resultado con comprobación JSON. |
| `login_limites` | InnoDB, PK `clave` (`CHAR(64) ascii_bin`), `inicio`, `fallos` y `proximo_en` sin signo, índice `idx_login_limites_inicio`. Vacía antes del primer login. |

Guardar `SHOW CREATE TABLE` de las seis tablas, los resultados de `SHOW INDEX` y los conteos y totales antes/después. Para probar conservación de filas no vacías o rechazo de dependencias faltantes, usar ramas desechables de un punto de control de ensayo con datos sintéticos y evidencia por separado. Por ejemplo, retirar una dependencia o dejar un turno abierto en una rama y comprobar que 004 deja ausentes sus cinco cambios. La instalación base vacía demuestra el avance limpio, pero no verifica conservación de movimientos históricos. Antes de abrir la web, registrar la verificación de todos los objetos.

## 5. Datos sintéticos y concurrencia

Crear datos desde la aplicación de ensayo tras las verificaciones, registrando IDs generados, actor, fecha contable, turno y hora local. Usar correos bajo `example.test`, contraseñas distintas, series de comprobantes de ensayo y nombres ficticios. Usar comprobantes simples para la línea base financiera.

| Datos | Restaurante A «Ola1 Norte» | Restaurante B «Ola1 Sur» |
|---|---|---|
| Usuarios | `admin.norte@example.test`, `caja.norte@example.test` (atención). | `admin.sur@example.test`, `caja.sur@example.test` (atención). |
| Mesas y catálogo | Mesas 12, 13 y 14. Productos S/ 100 y S/ 20, más una opción de comanda. | Mesas 12 y 21 para probar números repetidos entre restaurantes. Productos S/ 45 y S/ 15. |
| Control para tarjeta global | Dos pedidos de S/ 100 cobrados sin ajustes. Capturar el reporte en ese punto, con 2 usuarios y 3 mesas en A. | Un pedido de S/ 45 cobrado por tarjeta. En la fecha y con solo estos movimientos, la suma esperada es S/ 245. Tomar antes el control de B sin ventas. |
| Cierres y reversos | En otro turno, pedido de S/ 100 en mesa 12 con S/ 10 de descuento. Cobrar S/ 60 en efectivo y S/ 30 en `otro`. Cerrar y guardar el detalle. Renumerar la mesa a 99 y verificar que el cierre conserva 12. Reabrir en un turno posterior, agregar S/ 20 y recobrar S/ 110. Esperar reversos de S/ 90 y neto de cobros de S/ 20 en ese turno. Repetir con cargo adicional en un tercer turno. | Un turno con cobro y cierre propios. Comparar que los cambios de A no alteran totales, pagos ni el detalle de B. |
| Gastos | Gasto activo S/ 15 y otro S/ 7 que se editará o anulará en un turno abierto. Usar también un gasto de fecha contable distinta a la fecha de registro. | Gasto activo S/ 5. Verificar caja, reporte por fecha contable y aislamiento. |

Conservar pedidos y mesas adicionales para casos concurrentes, fuera del corte de conteos del control global. Usar el superadmin de ensayo para el cambio de restaurante. Agregar después cuentas sintéticas de cocina y encargado si se necesita comprobar permisos por rol. Para el caso de medianoche, capturar un pedido antes de medianoche y su cobro después con el reloj de la VM y la zona de aplicación `America/Lima`. Registrar ambas fechas y el ID de turno.

### Pruebas con dos conexiones

Usar dos perfiles de navegador con sesiones distintas y cuentas autorizadas. Para cada par, preparar datos independientes y repetir invirtiendo qué operación entra primero. Registrar hora, IDs, estados HTTP, tiempos de espera, resultado final, pagos, ítems, reversos y auditoría. Si ambas peticiones simplemente se ejecutan una después de la otra, registrar que no se observó contención. Cuando haga falta controlar el orden, desde una tercera conexión local de ensayo el operador puede mantener brevemente un bloqueo de fila con `SELECT … FOR UPDATE` dentro de una transacción sobre el recurso de ensayo, observar la espera y liberar con `ROLLBACK`. Para alta contra borrado, usar la fila de la mesa como barrera secundaria y confirmar cuál petición tomó primero el restaurante. Capturar esperas con `information_schema.INNODB_TRX` e `INNODB_LOCK_WAITS` desde una cuenta operadora con permisos de diagnóstico. El sitio debe usar la cuenta de aplicación limitada.

| Operaciones | Resultado que debe demostrarse |
|---|---|
| Borrar mesa contra crear pedido en esa mesa | Ambos pasan primero por el bloqueo de restaurante. Si gana el alta, el borrado rechaza el pedido activo. Si gana el borrado, el alta rechaza la mesa ausente. No queda un pedido nuevo con referencia inconsistente. |
| Abrir turno dos veces en el mismo restaurante | Queda un solo turno abierto. Repetir con A y B para comprobar la independencia. |
| Cobrar dos veces el mismo pedido | Un solo cobro confirmado, sin pagos duplicados ni doble comprobante. |
| Cobrar contra cerrar turno | Si gana el cobro, queda incluido en el cierre. Si gana el cierre, el cobro se rechaza sin escritura parcial. |
| Agregar comanda contra cobrar o cancelar | Resultado serial, sin ítems agregados después del cobro o cancelación. |
| Reintentar la misma comanda y reutilizar su clave con otro contenido | Mismo resultado para el reintento sin duplicar ítems, y rechazo de la clave con contenido diferente. |
| Dos reaperturas sobre el mismo pedido | Un reverso por pago original, sin doble devolución ni cambios al cierre ya guardado. |

Completar también los casos de sesiones, CSRF, login, formularios obsoletos y fallo de auditoría del [guion de aceptación](aceptacion_ola_1.md). Registrar errores de espera o interbloqueos con su código y estado transaccional. La ausencia de duplicados se comprueba con filas y totales, además de las respuestas HTTP.

## 6. Respaldo, restauración y conciliación

1. Tomar dos cortes: base antes de 004 y resultado después de ejecutar el guion. Detener escrituras y guardar hora, versión, tablas, conteos e importes del mismo corte. Desde `restaurant_ola1_test` usar **Exportar → Personalizado → SQL**, todas las tablas, estructura y datos. Incluir los objetos adicionales solo si existen. Conservar los archivos fuera de htdocs y registrar tamaño y SHA-256 con `Get-FileHash -Algorithm SHA256`.
2. Revisar el archivo antes de importar. Debe estar libre de `CREATE DATABASE`, `DROP DATABASE`, `USE` y referencias calificadas al origen. Verificar que procede exclusivamente del ensayo y que el SHA-256 coincide con el de la exportación. Confirmar `SELECT DATABASE()` e inventario vacío de tablas de `restaurant_ola1_restore` justo antes de **Importar → SQL**. Registrar hora inicial/final, advertencias y errores. Tras una importación parcial, detener y repetir desde un destino vacío.
3. Con la cuenta de lectura, comparar `SHOW CREATE TABLE`, motores, índices, FK y conteos para **todas** las tablas. Comprobar huérfanos y cruces de restaurante en mesas, pedidos, pagos, gastos, turnos y comprobantes. Comparar pagos por restaurante, método y estado de anulación, gastos por fecha, estado y turno, comprobantes por estado, reversos por turno y valores guardados de cada cierre, incluido el mapa de mesas. Conciliar en céntimos. Para cada turno nuevo, comparar pagos brutos asignados menos reversos asignados al turno contra el total de cobros del cierre y comprobar gastos aparte. El importe bruto incluye pagos originales revertidos después. Registrar diferencias por ID y mantener intacta la copia conciliada.
4. Registrar hash, duración de importación y conciliación y el corte que pudo recuperarse. La restauración pasa cuando hay coincidencia de estructura, datos y totales, y cualquier diferencia está explicada con evidencia. Consultar el [procedimiento de respaldo y restauración](backup_restore.md).

## 7. Riesgos y estado al entregar

- **Instancia aislada, identidad efectiva e InnoDB: pendientes.** Se identificó la versión del binario y se comprobó la configuración en disco. Falta aprovisionar la VM y registrar las comprobaciones de aislamiento y motor.
- **Importación, historial persistido, concurrencia y restauración: no ejecutados.** Requieren preparar manualmente los esquemas y las cuentas de ensayo. Ninguna prueba de aceptación real se declara aprobada.
- **Cierres anteriores sin número capturado:** el historial muestra `—`. Conservar los cierres antiguos tal como están.
- **Diferencias con producción:** confirmar versión, modo SQL, índices, permisos y volumen del destino en una validación autorizada. Sin salida externa pueden faltar estilos o recursos de CDN. Registrar si ello afecta alguna prueba de interfaz. Los objetivos RPO y RTO siguen pendientes de acuerdo.
