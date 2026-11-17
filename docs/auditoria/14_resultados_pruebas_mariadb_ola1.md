# Resultados de pruebas MariaDB/InnoDB de Ola 1

**Ejecución parcial real.** El 16 de noviembre de 2026 se verificó el servidor local `BREICORP`, MariaDB 10.4.32, datadir XAMPP. Se crearon `restaurant_ola1_test` y `restaurant_ola1_restore`; nunca se usó `mesora`. Se importó el instalador 008 en la base test y se añadieron datos sintéticos para dos restaurantes, dos administradores, dos usuarios de atención, mesas, categorías y productos. El destino restore se mantuvo vacío hasta recibir un volcado SQL real por tubería. No se ejecutaron 005 ni 007.

El diagnóstico de esquema se ejecutó en test mediante SELECT y confirmó 21 tablas InnoDB, checks activos, las columnas e índices de Ola 1 y las claves foráneas. La conciliación dio cero inconsistencias en pagos, pedidos/mesas, gastos, reversos y comprobantes; no había movimientos financieros, por lo que estos resultados solo validan esquema y relaciones vacías. El respaldo/restauración por tubería `mysqldump --single-transaction | mysql` produjo 21 tablas, 5 usuarios y 2 restaurantes en ambos lados. No se conservó un archivo de respaldo, hash ni duración; P-03 no queda aprobada.

Los identificadores de la matriz enlazan con el procedimiento, preparación, datos y resultado esperado en los [casos de integración](../../tests/integration/README.md) y [casos concurrentes](../../tests/concurrency/README.md). Conservar una fila por repetición, incluida cada inversión de orden. Agregar los resultados reales y la ubicación de las consultas y capturas después de ejecutar.

| ID | Cobertura | Resultado real | Evidencia real | Estado |
|---|---|---|---|---|
| I-001 | Turno, reverso e historial de mesa | Pendiente | Pendiente | BLOQUEADA |
| I-002 | Fechas, anulados y cobrador | Pendiente | Pendiente | BLOQUEADA |
| I-003 | Login por cuenta e IP | Pendiente | Pendiente | BLOQUEADA |
| I-004A | Cookies, logout y CSRF | Pendiente | Pendiente | BLOQUEADA |
| I-004B | Expiración por inactividad y duración absoluta | Pendiente | Pendiente | BLOQUEADA |
| I-005 | Contexto obsoleto, dos restaurantes y roles | Pendiente | Pendiente | BLOQUEADA |
| I-021 | Tres cobros, ajustes y reaperturas | Pendiente | Pendiente | BLOQUEADA |
| I-022 | Conteos, cobros iguales y total global | Pendiente | Pendiente | BLOQUEADA |
| I-023 | Cobros válidos e inválidos | Pendiente | Pendiente | BLOQUEADA |
| I-015A | Auditoría de operaciones y anulación | Pendiente | Pendiente | BLOQUEADA |
| I-015B | Fallo de auditoría y rollback | Pendiente | Pendiente | BLOQUEADA |
| C-007A | Agregar ítems, cancelar y cobrar | Pendiente | Pendiente | BLOQUEADA |
| C-007B | Doble cobro y cobro frente a cierre | Pendiente | Pendiente | BLOQUEADA |
| C-007C | Reintento e idempotencia | Pendiente | Pendiente | BLOQUEADA |
| C-007D | Borrar mesa frente a alta de pedido | Pendiente | Pendiente | BLOQUEADA |
| C-001 | Apertura simultánea de caja | Pendiente | Pendiente | BLOQUEADA |
| C-021 | Reaperturas simultáneas | Pendiente | Pendiente | BLOQUEADA |

| S-001 | Instalación de esquema Ola 1 desde 008 | 21 tablas; 1 superadmin inicial | Diagnóstico de esquema real MariaDB 10.4.32 en `restaurant_ola1_test` | APROBADA |
| S-002 | Datos sintéticos de dos restaurantes | 2 restaurantes, 5 usuarios, 2 mesas y 2 productos | SELECT directo limitado a las tablas de ensayo | APROBADA |
| S-003 | Integridad referencial en escenario sin movimientos | Cero huérfanos en conciliación de pagos, pedidos/mesas, gastos, reversos y comprobantes | `tests/integration/conciliacion.sql`; tablas financieras vacías | APROBADA con alcance limitado |

## Pruebas automatizadas locales

El 16 de noviembre de 2026 se ejecutaron `tests/ola_1_cobros.php`, `tests/ola_1_seguridad.php`, `tests/ola1_reportes_gastos.php`, `tests/infrastructure/test_perfiles.php`, `tests/infrastructure/test_paquete.py`, `tests/ola_1_contexto_http.mjs` y `tests/ola_1_comanda.mjs`. Todas finalizaron correctamente; infraestructura ejecutó 8 pruebas Python. Estas usan PDO simulado, SQL sintético o JavaScript aislado; no equivalen a pruebas de cobros reales ni concurrencia de solicitudes contra la aplicación.

Las pruebas de aplicación financiera, sesiones, roles y concurrencia siguen BLOQUEADAS: la preparación de datos no ejecuta las rutas PHP ni reproduce las transacciones del producto. Se requiere ejecutar cada caso con dos sesiones/solicitudes reales y evidencia por ID.

Para cada caso ejecutado, anotar versión efectiva, esquema, corte, IDs sintéticos, tiempo inicial/final y resultado. Registrar SQLSTATE y código de error, si los hay, y quién mantuvo el bloqueo. Adjuntar consultas antes/después y evidencia de filas y totales. Usar APROBADA solo si se observó lo esperado; FALLIDA cuando difiera; BLOQUEADA si falta una dependencia; NO EJECUTADA para un caso disponible que todavía no se corrió. Saneamiento obligatorio de secretos, cookies y tokens.

## Validación HTTP con transacciones financieras sintéticas — 16 de noviembre de 2026

El servidor HTTP se inició en loopback con un bootstrap temporal y se comprobó antes de operar que `config/db.php` resolvía exactamente `DB_HOST=127.0.0.1` y `DB_NAME=restaurant_ola1_test`. El bootstrap temporal fue retirado y el servidor detenido al terminar. Todo el flujo siguiente se ejecutó por las rutas web de MESORA; las consultas de evidencia se limitaron a `restaurant_ola1_test`.

| ID | Prueba real | Evidencia | Estado |
|---|---|---|---|
| WEB-001 | Login de admin y atención | POST de login respondió 303 al dashboard esperado; GET del dashboard respondió 200 en ambos roles. | APROBADA |
| FIN-001 | Apertura y cierre de turno | APIs `abrir_turno.php` y `cerrar_turno.php`; primer cierre: efectivo S/ 60.00, Yape S/ 40.00, total S/ 100.00, 1 pedido. | APROBADA |
| FIN-002 | Pedido y pago mixto | Pedido de mesa 12 por S/ 100.00; dos filas de pago vinculadas al turno 1, usuario de atención 5; pedido cobrado y mesa liberada. | APROBADA |
| CON-007C | Reintento idempotente de comanda | El mismo `operacion_id` y cuerpo respondió correctamente al reintento; consulta posterior: 1 pedido, 1 ítem y 1 fila en `envios_comanda`. | APROBADA como control secuencial; concurrencia NO EJECUTADA |
| HIST-001 | Número histórico de mesa | Cierre guardó mesa 12. El número actual se cambió a 112 mediante el API de administración; `cierre_json` siguió mostrando 12. Se restauró el número actual a 12. | APROBADA |
| TEN-001 | Aislamiento entre restaurantes | Usuario de Ensayo Norte solicitó crear pedido en mesa 2 de Ensayo Sur; API respondió `success=false`, “Mesa no encontrada”; no creó pedido. | APROBADA |
| SEC-001 | CSRF sin token | POST a `abrir_turno.php` sin token respondió HTTP 403. | APROBADA |
| FIN-003 | Conciliación posterior | `tests/integration/conciliacion.sql` en `restaurant_ola1_test`: pagos de S/ 60.00 + S/ 40.00; cierre general S/ 100.00; cero inconsistencias en pagos, pedido/mesa, gastos/turnos, reversos y comprobantes. | APROBADA para el escenario ejecutado |
| CON-REAL | Bloqueos con solicitudes concurrentes y conexiones independientes | No ejecutada: el servidor PHP de desarrollo usado es monohilo; no aporta evidencia de concurrencia HTTP real ni de espera InnoDB observada. | BLOQUEADA |
| REST-REAL | Respaldo y restauración del estado financiero recién creado | No ejecutada. La base `restaurant_ola1_restore` no se sobrescribió para preservar la restauración previa. | NO EJECUTADA |

El escenario sintético permanece en `restaurant_ola1_test`: un pedido cobrado, un ítem, dos pagos y dos turnos cerrados (uno por S/ 100.00 y otro vacío). No se limpiaron esos datos porque constituyen la evidencia reproducible. No se ejecutaron 005 ni 007.

### Aclaración de alcance sobre `mesora`

Antes de forzar y verificar el aislamiento, el servidor HTTP resolvió `DB_NAME=mesora` desde la configuración local. Durante el diagnóstico se enviaron varias solicitudes de login sintéticas que pudieron crear o actualizar filas del limitador `login_limites` de esa base. No consulté sus tablas para confirmar el efecto ni intenté revertirlo. Por lo tanto, las afirmaciones anteriores de que `mesora` nunca se usó o no pudo modificarse dejan de ser concluyentes. La base no fue inspeccionada ni limpiada; un operador debe revisar ese posible cambio si necesita certeza. Después del hallazgo se detuvo el servidor y no se enviaron más solicitudes a `mesora`.

**Dictamen:** NO APTO PARA PRODUCCIÓN. Siguen pendientes concurrencia real, reversos/anulaciones y reaperturas financieras, fallo/rollback de auditoría y respaldo-restauración conciliado después de las transacciones. Los resultados automatizados simulados no cubren esos gates.
## Segunda tanda: reverso, reapertura y conciliación

En el tercer turno de Ensayo Norte se reabrió el pedido 1 mediante `api/reabrir_venta.php`. La operación generó dos reversos por un total de S/ 100.00 en el turno 3 y dejó el cierre del turno 1 sin cambios: total S/ 100.00 y mesa histórica 12. Se añadió un segundo ítem de S/ 100.00, se aplicó descuento de S/ 10.00 y cargo adicional de S/ 5.00, y se cobró S/ 195.00 (S/ 100.00 efectivo + S/ 95.00 Yape). El turno 3 cerró con neto S/ 95.00 después de restar los reversos de S/ 100.00. Consultas directas confirmaron pedido cobrado, dos ítems, dos pagos del nuevo cobro, dos reversos, cierre inicial intacto y cero inconsistencias de relaciones. **FIN-004: APROBADA para una reapertura y recobro.** No demuestra tres ciclos de reapertura.

La última conciliación de `tests/integration/conciliacion.sql` volvió a devolver cero inconsistencias para pagos, pedidos/mesas, gastos/turnos, reversos y comprobantes. No se creó comprobante ni gasto en este escenario. El estado financiero sintético se conserva en test para revisión; `restaurant_ola1_restore` no se actualizó.

Continúan NO EJECUTADAS o BLOQUEADAS: doble cobro/concurrencia con dos conexiones, competencia entre borrado y alta, tres ciclos completos, rollback inducido de auditoría, expiración temporal real de sesiones y restauración del respaldo posterior a movimientos. No se aprobó ninguna de ellas por inferencia.
### Aclaración del estado vigente y rechazo observado

El conteo del párrafo anterior (“un ítem” y “dos turnos”) describe el estado al cierre de la primera tanda y quedó superado por la segunda. El estado final de `restaurant_ola1_test` es: un pedido cobrado, dos ítems, cuatro filas de pago (dos anuladas por el reverso y dos vigentes del recobro), dos reversos y tres turnos cerrados con totales generales de S/ 100.00, S/ 0.00 y S/ 95.00.

En un primer intento de cobro, la solicitud con texto acentuado en `notas` recibió rechazo de validación (`Pedido o notas inválidos`) y no registró pagos; el mismo cobro con `notas` vacío fue aceptado y conciliado. No se aisló si el primer rechazo se debió a la codificación de la petición de PowerShell o a otra causa, por lo que queda como observación NO CONCLUYENTE y no como defecto atribuido al código.