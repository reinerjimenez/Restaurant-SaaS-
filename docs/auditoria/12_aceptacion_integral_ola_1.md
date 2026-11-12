# Aceptación integral de la Ola 1

**Decisión: NO APTO PARA PRODUCCIÓN.** Se implementaron las tres correcciones autorizadas y pasaron sus pruebas locales. La importación real, la concurrencia en InnoDB, la conciliación de caja y la restauración siguen pendientes. Los resultados de la auditoría inicial se conservan como evidencia previa a la corrección.

## Correcciones y resultados posteriores a la auditoría

| Defecto | Causa y solución | Prueba ejecutada | Estado |
|---|---|---|---|
| Validación de 004 | La guarda contaba tablas sin revisar las columnas de las que depende la aplicación. Se añadieron las dependencias por tabla y se verifica que los identificadores de destino de las nuevas FK sean `INT` con signo y PK individual. La lectura de turnos se prepara después de validar metadatos. Las cinco operaciones DDL siguen condicionadas a esquema válido y ausencia de turnos abiertos. | `python tests/ola_1_preflight_004.py`. Evalúa los predicados extraídos del archivo SQL mediante SELECT en memoria, con metadatos sintéticos derivados de 002. Aceptó el esquema compatible y rechazó 12 variantes, entre ellas tablas o columnas ausentes, motor incorrecto, PK compuesta, tipo sin signo, turno abierto y migración parcial. | APROBADA en prueba de predicados. Importación MariaDB BLOQUEADA. |
| Número de mesa histórico | El listado resolvía la mesa mediante el catálogo actual. El cierre guarda un mapa de pedido a número de mesa en el `cierre_json` existente. Al listar cobros de un cierre se elimina la unión con `mesas` y se lee el valor guardado. Los cierres sin mapa muestran `—`. | `php tests/ola1_reportes_gastos.php`. PDO simulado. Capturó mesa 12, comprobó que la consulta vigente muestra 99 y que el histórico conserva 12 sin consultar la tabla de mesas. También verificó un cierre sin mapa y un pedido sin mesa. | APROBADA en PDO simulado. Cierre y edición reales BLOQUEADOS. |
| Borrado concurrente de mesa | La consulta de pedidos activos y el borrado ocurrían sin una transacción común. El borrado ahora adquiere el bloqueo por restaurante y el de la fila de mesa, verifica pedidos activos mediante una lectura con bloqueo y confirma el borrado en la misma transacción. | `php tests/ola1_reportes_gastos.php`. PDO simulado. Verificó orden de consultas, borrado de mesa libre y rechazo de mesa con pedido activo. También verificó que el alta y el borrado usan el bloqueo por restaurante dentro de la transacción. | APROBADA en revisión de contrato y PDO simulado. Concurrencia real BLOQUEADA. |

### Orden de bloqueos

- Alta de pedido: **restaurante → turno abierto → mesa → comprobación e inserción del pedido → confirmación**.
- Borrado: **restaurante → mesa → comprobación de pedidos activos → borrado → confirmación**. Si falla alguna comprobación, el endpoint revierte la transacción.

Ambos flujos adquieren la misma fila del restaurante antes de tomar bloqueos secundarios. Para el mismo restaurante, la segunda operación espera antes de retener mesa, turno o pedido. Así se evita un ciclo entre estos dos flujos. Si gana el alta, el borrado encuentra el pedido activo. Si gana el borrado, el alta no encuentra la mesa. Falta observar ambas secuencias en InnoDB con dos conexiones.

### Regresiones y archivos

Las seis pruebas locales existentes volvieron a pasar: cobros, reportes y gastos, seguridad, cabeceras de contexto, reintento de comanda y sesión CGI. `php -l` aprobó los seis PHP tocados. `git diff --check` no reportó errores. Graphify se actualizó con extracción estática.

- Migración y prueba: `Database/004_ola_1_finanzas.sql`, `tests/ola_1_preflight_004.py`.
- Historial: `api/cerrar_turno.php`, `includes/reportes_financieros.php`, `roles/atencion/historial.php`.
- Bloqueos: `api/admin_mesas.php`, `config/finanzas.php`.
- Pruebas PHP e informe: `tests/ola1_reportes_gastos.php` y este documento. Graphify regeneró `GRAPH_REPORT.md`, `graph.html`, `graph.json` y `manifest.json` en `graphify-out/`.

El 004 actualizado sigue siendo de avance manual en phpMyAdmin para una base de ensayo con respaldo. Una base que ya tenga el 004 aplicado no debe reaplicarlo. Esta corrección del historial usa la columna `cierre_json` existente y no requiere DDL adicional. El número de mesa que no se capturó al cerrar queda sin atribución. Las pruebas reales y el ensayo de restauración siguen pendientes según la matriz inferior.

## Alcance y entorno

Base Git `393a2e9` y cambios locales de Ola 1. Antes de crear este informe se verificaron 58 archivos modificados y 22 nuevos. Se consultó Graphify y se leyeron los cinco documentos del encargo: [entrega técnica](10_entrega_ola_1.md), [resumen de archivos](11_resumen_ola_1.md), [aceptación](../operaciones/aceptacion_ola_1.md), [despliegue](../operaciones/despliegue_ola_1.md) y [respaldo](../operaciones/backup_restore.md).

Los binarios locales identificaron PHP 8.2.35 y MariaDB 10.4.32. Esa comprobación identifica el software instalado. El servidor y su configuración efectiva siguen sin verificar. En el directorio `C:/xampp/mysql/data/test` solo se encontró un archivo `.opt`. Se encontraron `.env` y `.env.example`, pero ninguna configuración separada de ensayo ni una base de la aplicación identificada como reservada para este trabajo. El nombre genérico `test` y un instalador SQL no acreditan un entorno aislado.

La auditoría se realizó sin conectar a bases de datos. La regla del proyecto reserva la preparación e importación de SQL al desarrollador. Se conservó el código local y se añadió este informe.

## Pruebas ejecutadas en la auditoría inicial

| Prueba | Estado | Evidencia y alcance |
|---|---|---|
| Sintaxis PHP | APROBADA | `C:/xampp/php/php.exe -l` sobre 65 archivos de aplicación y pruebas. Sin fallos. |
| Sintaxis JavaScript embebido | APROBADA | `node --check -` sobre 18 bloques, con valores PHP sustituidos. Sin fallos. |
| H-021, H-023. Ajustes y pagos | APROBADA | `php tests/ola_1_cobros.php`, salida 0. PDO simulado. Subtotal persistido, ciclos con descuento/cargo, pagos mixtos, negativos, cero, fracciones de céntimo y diferencias contra el total. |
| H-001, H-002, H-022, H-015. Reportes y gastos | APROBADA | `php tests/ola1_reportes_gastos.php`, salida 0. Contratos de consultas, fechas, totales, rol de gasto, lectura del resumen y fallo de auditoría con PDO simulado. |
| H-003, H-004, H-005. Reglas de acceso y sesión | APROBADA | `php tests/ola_1_seguridad.php`, salida 0. 27 comprobaciones de reglas, límites temporales, credenciales y contexto. |
| H-005. Cabeceras por contexto | APROBADA | `node tests/ola_1_contexto_http.mjs`, salida 0. Ocho comprobaciones con transporte simulado, incluidas las cabeceras en la API y la separación de orígenes. |
| H-007. Reintento del cliente | APROBADA | `node tests/ola_1_comanda.mjs`, salida 0. Una respuesta perdida y un reintento conservan el mismo cuerpo y clave. |
| H-004. Cookies y logout | APROBADA | `python docs/operaciones/pruebas/sesion_cgi.py`, salida 0. PHP CGI local con sesión anónima, cookies HTTPS/HTTP, logout por POST y reemplazo de la sesión anterior. |
| CSRF inválido | APROBADA | Se amplió en memoria el guion CGI para enviar un token inválido al logout anónimo y luego el válido. El inválido devolvió HTTP 403 y el logout válido expiró la cookie. No se cambió el guion en disco. |
| Consistencia del diff | APROBADA | `git -c core.safecrlf=false diff --check`, salida 0. |

Las pruebas con PDO o transporte simulado prueban las reglas y los contratos que ejercitan. El caso CGI ejecutó PHP y observó cabeceras reales en el entorno local. Las operaciones contra el motor, los permisos efectivos y la configuración del servidor de destino siguen pendientes.

## Migraciones y reversión

| Comprobación | Estado | Evidencia |
|---|---|---|
| Compatibilidad estática de 004 con el instalador 002 | APROBADA | Los identificadores referenciados de `turnos`, `pagos`, `usuarios` y `restaurantes` son PK `INT` con signo e InnoDB en 002. Las FK nuevas usan `INT` compatible. 004 añade índices de turno, PK por pago en reversos y PK compuesta por restaurante, usuario y clave en envíos. MariaDB 10.4 admite preparar las clases de DDL usadas. [004](../../Database/004_ola_1_finanzas.sql), [002](../../Database/002_produccion_limpia.sql), [PREPARE](https://mariadb.com/docs/server/reference/sql-statements/prepared-statements/prepare-statement), [claves foráneas](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/foreign-keys). |
| Preservación declarada por 004 | APROBADA | El avance agrega estructuras y deja los vínculos históricos en NULL. La guarda omite el DDL si detecta turnos abiertos o parte de la migración presente. No hay DML que reasigne históricos. La verificación de conteos en servidor sigue pendiente. |
| Validación suficiente del esquema antes del avance | FALLIDA | La guarda de 004 comprueba once tablas InnoDB y ausencia de objetos nuevos. Puede aprobar una estructura que carezca de `usuarios.session_version`, `pagos.anulado_en` o `facturacion_config`, aunque las rutas las requieren. La guía exige verificar 002/003, pero el script no lo confirma. Evidencia: `004:13–33`, `config/limite_login.php:85–105`, `config/finanzas.php:159–170`, `api/guardar_comprobante.php:52–66`. |
| Compatibilidad estática de 006 | APROBADA | Crea una tabla InnoDB con PK `CHAR(64)` ASCII binaria, contador, tiempos enteros e índice por inicio. No referencia otras tablas. El login también necesita el esquema de Ola 0 y `logs_acceso`. [006](../../Database/006_ola_1_intentos_login.sql). |
| Importación de 004/006, permisos, índices y FK reales | BLOQUEADA | Hace falta la base aislada preparada manualmente. Verificar versión, `sql_mode`, tipos, índices y restricciones en el servidor, y comparar filas y totales antes/después. |
| Revisión estática de 005 | APROBADA | Revierte en orden los objetos de un 004 completo. La guarda exige que no haya turno abierto, cierre nuevo, pagos o gastos ligados, reversos ni envíos. Un esquema parcial no satisface su procedimiento. [005](../../Database/005_reversion_ola_1_finanzas.sql). |
| Revisión estática de 007 | APROBADA | El único efecto declarado es eliminar la tabla del limitador y sus contadores. Requiere volver primero a una versión del código que no la use. La coordinación es operativa. [007](../../Database/007_reversion_ola_1_intentos_login.sql). |
| Ejecución de 005 y 007 | NO EJECUTADA | Excluida explícitamente del encargo. |

**Riesgo de corte.** Las cinco operaciones de DDL de 004 se ejecutan por separado y MariaDB puede confirmar implícitamente. Una falla después de un paso puede dejar el esquema parcial. La guarda impide reaplicar el archivo completo en ese estado. El procedimiento debe verificar cada tabla, columna, índice y FK y mantener escrituras detenidas hasta completar la conciliación. [Confirmaciones implícitas en MariaDB](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).

## Hallazgos por inspección antes de las correcciones

1. **FALLIDA · El historial detallado del turno sigue consultando la mesa actual.** El resumen monetario y el desglose de usuarios se leen del cierre guardado. El listado de cobros, en cambio, toma el número actual de `mesas` mediante `cobros_periodo()`. Si se renumera una mesa después del cierre, cambia la etiqueta histórica. Si se elimina, la FK permite que la referencia del pedido quede en NULL. Esto incumple la expectativa de conservar el mismo detalle del caso A si ese listado forma parte del cierre. Conservar el identificador y etiqueta en el detalle guardado al cerrar, o delimitar formalmente ese listado como consulta actual y ajustar el criterio de aceptación. Evidencia: `roles/atencion/historial.php:61–89`, `includes/reportes_financieros.php:123–139`, `002:331`.
2. **FALLIDA · Eliminación de mesa y alta de pedido no comparten el mismo bloqueo.** La eliminación consulta pedidos activos y ejecuta el borrado en otra sentencia sin el bloqueo por restaurante usado al crear una comanda. La separación deja una carrera operativa que puede desprender la mesa de un pedido recién creado por la FK `ON DELETE SET NULL`. El escenario se deduce del código. No se reprodujo en el servidor. Incluir la gestión de mesas en el mismo orden de bloqueo y verificar el caso en la base de ensayo. Evidencia: `api/admin_mesas.php:58–64`, `api/crear_pedido.php:22–37`, `002:331`.
3. **Riesgo pendiente · Cobros y caja expresan métricas distintas.** El reporte por fecha suma pagos vigentes. Caja registra el bruto original en su turno y resta el reverso en el turno del ajuste. Tras recobrar en otro turno, ambos reportes pueden mostrar importes distintos de manera consistente con el diseño. La aceptación debe conciliar bruto, reversos y pagos vigentes por separado. Evidencia: `includes/reportes_financieros.php:30–53`, `config/finanzas.php:187–249`. La anulación aislada de un comprobante cambia el documento y conserva el pago. La anulación de venta registra el reverso financiero. Ambos casos requieren resultados distintos. Evidencia: `api/anular_comprobante.php:22–37`, `api/eliminar_venta.php:19–36`.

## Pruebas integrales pendientes

| Caso | Estado | Evidencia que falta |
|---|---|---|
| Abrir y cerrar caja, doble apertura, cierre obsoleto | BLOQUEADA | Filas de turno antes/después, restricción de turno abierto, resumen persistido y rechazo del identificador obsoleto. |
| Cobro simple, mixto y con comprobante | BLOQUEADA | Importes por método, filas de pagos, correlativo, JSON y totales de pedido/cierre en MariaDB. |
| Pagos inválidos y rollback de cobro | BLOQUEADA | Comparación de filas y correlativo que demuestre cero escrituras tras un rechazo. |
| Reaperturas y recobros con descuento y cargo | BLOQUEADA | Tres ciclos reales y conciliación del ejemplo S/ 90 inicial, S/ 110 de recobro y S/ 20 netos en el segundo turno. |
| Anulación de comprobante, anulación de venta y reverso sin turno | BLOQUEADA | Comparar el efecto documental y financiero, el enlace a pagos originales y la ausencia de escrituras si la caja está cerrada. |
| Inmutabilidad de cierres y conciliación por fechas | BLOQUEADA | Comparar totales/snapshot antes y después, movimientos al cruzar medianoche, gastos por fecha contable y turno, detalle y tarjeta global. El problema del listado histórico se documenta arriba. |
| Dos sesiones agregando, cobrando o cancelando el mismo pedido | BLOQUEADA | Dos conexiones al mismo esquema de ensayo y evidencia del orden efectivo de bloqueo. |
| Dos cobros simultáneos y cobro frente a cierre | BLOQUEADA | Un único cobro confirmado, pagos íntegros, asignación a un solo turno y registro de esperas o errores. |
| Reintento real de comanda | BLOQUEADA | Resultado persistido, conteo de ítems tras reintentar y rechazo de reutilización con contenido distinto. |
| Limitador de login por IP/cuenta en el motor | BLOQUEADA | Cuentas sintéticas, persistencia de la ventana, recuperación, respuesta y comportamiento de conexiones concurrentes. |
| Sesión autenticada, roles y revocación | BLOQUEADA | Ejecución en la aplicación de ensayo con cuentas de cada rol. Verificar expiración y versión de sesión. |
| Cambio de restaurante y formularios de dos pestañas | BLOQUEADA | Dos restaurantes, una sesión superadmin en dos pestañas y comprobación de que las operaciones obsoletas se rechazan sin cruzar datos. |
| Falla real de auditoría dentro de la transacción | BLOQUEADA | Forzar el fallo controlado en la copia de ensayo y comprobar que el gasto, pago, comprobante o cierre se revierte con su evento. |
| Respaldo, restauración y comparación origen/destino | BLOQUEADA | Dos bases aisladas, respaldo sintético, hash, tiempos, conteos, FK y totales conciliados. |

## Restauración y siguiente paso

**Resultado de restauración: BLOQUEADA.** No se registraron tiempos, conteos ni totales de origen o destino. El procedimiento existente es coherente con exportar estructura y datos y verificar las cifras sobre el mismo corte. El RPO y el RTO siguen por acordar. [Procedimiento de respaldo y restauración](../operaciones/backup_restore.md).

Para continuar, el desarrollador debe identificar el servidor y una base **aislada de origen** con dos restaurantes, cuentas y datos sintéticos, y una **base vacía de destino** para restauración. La configuración de ensayo debe apuntar a esas bases con permisos limitados y con integraciones externas deshabilitadas. La preparación manual debe confirmar Ola 0, aplicar 004 y 006 en phpMyAdmin y registrar versión, esquema, índices y FK. Los secretos deben quedar en la configuración local del ensayo. Luego se podrán ejecutar los casos A–F y adjuntar conteos, importes, resultados y tiempos. Las correcciones derivadas de este informe quedan pendientes de autorización.

