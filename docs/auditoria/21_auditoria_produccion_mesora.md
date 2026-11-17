# Auditoría de preparación para producción de MESORA

**Fecha:** 16 de noviembre de 2026  
**Dictamen:** **NO APTO PARA PRODUCCIÓN**

## Alcance y evidencia

Se revisaron estáticamente el código PHP/PDO, configuración de Apache, README, instalador SQL 008 y los informes de aceptación y despliegue de Ola 1. Se consultó el MariaDB local mediante SELECT solamente. No se importó SQL ni se hicieron escrituras.

La instancia observada es MariaDB 10.4.32, XAMPP local, datadir `C:\xampp\mysql\data`. La base `mesora` tiene 21 tablas; 0 tablas fuera de InnoDB; existen las tres columnas de Ola 1 comprobadas (`pagos.turno_id`, `gastos.turno_id`, `turnos.cierre_json`); tiene 2 usuarios, 1 superadmin y 1 restaurante. Estos conteos muestran que no es una base nueva ni vacía. No se inspeccionaron datos personales ni importes.

## Hallazgos

### Críticos

1. **Faltan pruebas de dinero y concurrencia con dos conexiones.** Los informes 12, 14 y 16 confirman que no se han probado con MariaDB los cobros simultáneos, reversos, cierres históricos, rollback de auditoría ni conciliación financiera. Una simulación PDO no demuestra atomicidad InnoDB.
   - **Corrección requerida:** cargar datos sintéticos en una base de ensayo separada, ejecutar los casos de `tests/integration/` y `tests/concurrency/`, guardar consultas y resultados, y conciliar pagos, gastos, turnos, comprobantes y cierres.

2. **No hay una restauración real demostrada.** El informe 20 la mantiene NO APROBADA: no hay exportación MariaDB auténtica, restauración aislada ni conciliación de origen y destino.
   - **Corrección requerida:** generar un respaldo de ensayo, restaurarlo en una segunda base vacía y documentar hash, duración, conteos, relaciones y totales.

3. **Configuración de InfinityFree requiere datos propios de la cuenta.** El host MySQL debe tomarse del panel y el nombre completo de base puede incluir prefijo de cuenta. La versión local usa `localhost` y `DB_NAME=mesora`, valores que no deben copiarse al hosting. El instalador 008 ahora exige una base seleccionada y vacía, sin asumir el nombre. La conexión real sigue pendiente. [Conexión y nombres MySQL de InfinityFree](https://forum.infinityfree.com/t/common-mysql-connection-errors/49338).
   - **Corrección aplicada:** se retiró la comparación fija con `mesora`; completar `.env` de producción con `DB_HOST`, `DB_NAME`, usuario y contraseña exactos del panel, fuera del repositorio.

### Altos

4. **Compatibilidad con PHP 8.4 sin validar.** InfinityFree anunció PHP 8.4 en sus servidores gratuitos; la inspección local anterior identificó PHP 8.2.35. El proyecto declara PHP 8.1+, pero no se ha ejecutado la aplicación en 8.4 ni revisado su salida de errores allí. [Anuncio de PHP 8.4](https://forum.infinityfree.com/t/free-hosting-is-now-upgraded-to-php-8-4/120906).
   - **Corrección requerida:** pasar lint y recorridos de login, caja, pedidos, comprobantes y reportes bajo la versión efectiva del hosting.

5. **Exposición de archivos internos aún no verificada en InfinityFree.** `.htaccess` ahora deniega `.git`, `docs`, `tests`, `scripts`, `Database`, `graphify-out`, archivos Markdown, archivos de entorno y SQL. La prueba local responde 403; InfinityFree debe confirmar el resultado bajo su Apache.
   - **Corrección aplicada:** se agregaron reglas de bloqueo compatibles con `mod_authz_core` y `mod_access_compat`; publicar solo el paquete runtime documentado en `docs/operaciones/paquete_produccion_mesora.md`.

6. **El contenido auditado aún dice NO APTO.** Los informes 12, 14, 15, 16 y 20 registran pruebas bloqueadas; no hay evidencia posterior que cierre esos gates. Este informe conserva el dictamen mientras sigan faltando pruebas críticas.

## Pruebas y estado

| Prueba | Estado | Evidencia |
|---|---|---|
| Conexión local y versión MariaDB | APROBADA | SELECT real a 127.0.0.1:3306 devuelve MariaDB 10.4.32. |
| Conteo y motor de tablas `mesora` | APROBADA | SELECT devuelve 21 tablas; 0 motores distintos de InnoDB. |
| Columnas finales de Ola 1 | APROBADA | SELECT encuentra `turno_id` en pagos/gastos y `cierre_json` en turnos. |
| Confirmar base vacía para instalación | FALLIDA | Hay 21 tablas, 2 usuarios y 1 restaurante; no importar 008 aquí. |
| Cobros, reversos y cierres con operaciones reales | BLOQUEADA | Requiere datos de ensayo y escrituras en una base separada. |
| Concurrencia con conexiones independientes | BLOQUEADA | Requiere escenario sintético y ejecución coordinada. |
| Restauración y conciliación | BLOQUEADA | Falta respaldo real de ensayo y segunda base aislada. |
| PHP 8.4 e integración con InfinityFree | BLOQUEADA | No hay acceso técnico al hosting ni sus datos concretos de MySQL. |
| Bloqueo HTTP local de docs, SQL, `.env` y README | APROBADA | GET a Apache local devuelve 403 en las cuatro rutas. La raíz de MESORA devuelve 200. |
| Acceso HTTP a archivos internos en InfinityFree | NO EJECUTADA | No existe despliegue de ensayo en el hosting para verificar sus reglas Apache. |

## Exclusión de documentación en el despliegue

Se revisaron 34 archivos bajo `docs`, con aproximadamente 306 KiB. El espacio es pequeño frente al riesgo de perder evidencias históricas de seguridad y aceptación. Se conservan en el repositorio y se excluyen del paquete web, junto con pruebas, SQL, respaldos, scripts de ensayo, `.git` y `graphify-out`.

## Próximos pasos para cambiar el dictamen

1. Preparar una base aislada sintética para integración y otra para restauración. No usar la base `mesora`, que contiene datos.
2. Ejecutar y documentar pagos, turnos, reversos, aislamiento de tenant, sesiones, CSRF, concurrencia y rollback de auditoría.
3. Probar respaldo, restauración y conciliación en MariaDB/InnoDB.
4. Resolver la diferencia de nombre/host de base para InfinityFree y verificar PHP 8.4 y reglas HTTP en el hosting.
5. Repetir los gates críticos y emitir un dictamen nuevo con evidencia.

## Ensayo autorizado posterior

Con autorización del operador se crearon únicamente `restaurant_ola1_test` y `restaurant_ola1_restore` en el MariaDB local. Se importó 008 en test, se cargaron datos sintéticos para dos restaurantes, cinco usuarios, dos mesas y dos productos, se ejecutaron los SELECT de diagnóstico y conciliación, y se restauró un volcado transmitido por tubería. Ambas bases terminaron con 21 tablas, cinco usuarios y dos restaurantes; las relaciones comprobadas no mostraron inconsistencias. No se escribieron filas financieras sintéticas y no se alteró la base `mesora`.

La restauración no dejó archivo persistente ni evidencia de hash/duración y no probó totales financieros. Por eso las pruebas de cobros, integridad financiera, concurrencia y recuperación completa siguen bloqueadas. El dictamen permanece **NO APTO PARA PRODUCCIÓN**.

También pasaron las suites locales simuladas: pruebas PHP de cobros, seguridad, reportes/gastos y perfiles; 8 pruebas Python de infraestructura; y dos pruebas JavaScript de contexto HTTP e idempotencia de comanda. No ejecutan cobros en la aplicación ni sustituyen concurrencia web real. Todos los archivos PHP pasaron `php -l` en las revisiones puntuales realizadas; no se usó PHP 8.4.

## Addendum de pruebas HTTP reales — 16 de noviembre de 2026

Se ejecutaron los flujos de login por rol, apertura/cierre de caja, pedido por mesa, envío idempotente a cocina, cobro mixto, aislamiento de restaurante, rechazo CSRF y conservación del número de mesa histórica en `restaurant_ola1_test`. El cierre registró S/ 60.00 en efectivo y S/ 40.00 en Yape; el `cierre_json` conservó el número 12 aunque la mesa actual se cambió temporalmente a 112. La conciliación MariaDB/InnoDB dio cero relaciones inconsistentes. Evidencia detallada y estados individuales: [resultados de pruebas de Ola 1](14_resultados_pruebas_mariadb_ola1.md).

No se ejecutó concurrencia real con dos conexiones HTTP, reversos, reaperturas, rollback por fallo de auditoría ni restauración financiera posterior a estas operaciones. La decisión permanece **NO APTO PARA PRODUCCIÓN**.

### Corrección sobre uso de la base `mesora`

Una verificación posterior del servidor encontró que inicialmente resolvía `DB_NAME=mesora`; algunas solicitudes de login de prueba enviadas antes de aislarlo pudieron escribir en `login_limites`. No se inspeccionó ni limpió esa base. En consecuencia, el texto anterior de este informe que afirmaba que `mesora` no se alteró no debe considerarse confirmado. Tras detectar el problema, se detuvo el servidor; todos los flujos financieros descritos en el addendum se ejecutaron solamente después de validar por HTTP `restaurant_ola1_test` como base efectiva.
## Addendum financiero posterior: reverso y reapertura

Se probó por las rutas web, en `restaurant_ola1_test`, un reverso de S/ 100.00 en un turno posterior, reapertura del pedido, segundo ítem, descuento de S/ 10.00, cargo de S/ 5.00, recobro por S/ 195.00 y cierre del tercer turno con neto S/ 95.00. La consulta confirmó que el cierre original (S/ 100.00, mesa histórica 12) permaneció intacto y que las relaciones financieras siguieron conciliadas sin huérfanos. La evidencia y límites están en el segundo addendum de [resultados de aceptación](14_resultados_pruebas_mariadb_ola1.md).

Las tres iteraciones de reapertura, la concurrencia real, rollback provocado de auditoría y restauración financiera siguen pendientes. El dictamen sigue **NO APTO PARA PRODUCCIÓN**.