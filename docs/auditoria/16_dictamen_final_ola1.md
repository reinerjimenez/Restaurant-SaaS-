# Dictamen de preproducción de Ola 1

**APTO PARA PREPRODUCCIÓN según los tres bloques de verificación local.** C-001 pasó la repetición tras la corrección.

## Verificación real del 21 de noviembre de 2026

Ensayo local con Apache de XAMPP 2.4.58, PHP 8.2.35 y MariaDB 10.4.32/InnoDB. El servidor TLS de loopback confirmó por HTTP y SQL la base sintética `mesora_ola1_test`, instalada desde 008 con 24 tablas. Se usó un segundo restaurante sintético para el control de contexto. El destino vacío del restore fue `mesora_ola1_restore`. Se conserva abajo el dictamen anterior como historial.

| Bloque | Estado | Evidencia observada |
|---|---|---|
| 1. Concurrencia y cifras | **Pasó tras corregir C-001** | La primera tanda aprobó 31 carreras y detectó el interbloqueo de apertura entre restaurantes. La corrección pasó las dos repeticiones entre restaurantes y las dos de apertura duplicada dentro de un restaurante. También se verificaron tres ciclos reales de recobro y diez rechazos atómicos. [Evidencia inicial](pruebas/ola1_innodb_2026-11-21.jsonl) · [Repetición de C-001](pruebas/ola1_c001_correccion_2026-11-21.jsonl). |
| 2. Seguridad y contexto | **Pasó** | HTTPS real con certificado local: el login emitió `Secure`, `HttpOnly` y `SameSite=Lax`. La aplicación rechazó y reemplazó un ID de sesión no emitido. Logout expiró la cookie e invalidó la sesión anterior. Tras **1 801,548 s** de inactividad real, el primer intento de abrir el panel redirigió al login. El quinto fallo de cuenta activó `Retry-After: 2`; la contraseña correcta se rechazó durante la pausa y funcionó después. El límite por IP devolvió `Retry-After: 426`, rechazó una credencial correcta y permitió el login una vez cumplida la espera. Cuenta inexistente y contraseña incorrecta devolvieron el mismo estado, destino y mensaje. Chromium con dos pestañas obtuvo 409 al enviar el formulario viejo de A tras seleccionar B. El GET legado mantuvo B y el conteo de gastos quedó en 0. |
| 3. Respaldo y restauración | **Pasó localmente** | Volcado de 93 709 bytes con `mysqldump --single-transaction --quick --skip-lock-tables --triggers`, importado a un esquema nuevo con el cliente MariaDB. Exportación 0,923 s, importación 0,593 s y conciliación 0,581 s. Exportación e importación sumaron 1,517 s. Coincidieron los conteos exactos de las 24 tablas, los 19 cierres y sus huellas, cobros brutos y reversos por turno, y S/ 7,00 de gastos activos. |

### Resultados de caja y de la suite

- C-007A: agregar contra cobrar y cancelar en ambos órdenes. Si el cobro ganó, quedó un ítem de S/ 10,00 y un pago. Si ganó el alta, hubo dos ítems y un pago de S/ 20,00. Si la cancelación ganó, no se incorporaron ítems. Dos altas simultáneas conciliaron S/ 30,00. C-007C conservó un ítem para el mismo envío y rechazó reutilizar la clave con otro cuerpo.
- C-007B y C-007E: doble cobro dejó un solo pago y un comprobante. Cobro frente a cierre dejó un pago si ganó el cobro, o cero si ganó el cierre. C-001, misma caja: una sola apertura. C-007D: si la creación del pedido ganó, se conservó la mesa y se rechazó borrarla. En orden inverso, se rechazó el pedido. C-021: un único reverso tras dos reaperturas concurrentes.
- H-001: cierre frente a reapertura y eliminación, en ambos órdenes. Las dos sesiones esperaron en el índice primario de `restaurantes`. Cuando ganó el ajuste, quedó un reverso de S/ 10,00 en el turno actual y el cierre anterior conservó su importe y huella. Cuando ganó el cierre, el ajuste se rechazó y no hubo reverso.
- H-021: tres ciclos con altas y bajas de platos. Descuento S/ 2,00 y cargo S/ 1,00 se conservaron y aplicaron una vez. Subtotales S/ 30,00, 50,00 y 70,00 produjeron S/ 29,00, 49,00 y 69,00.
- H-023: ambos endpoints de cobro rechazaron pagos negativos, cero, método desconocido, milésimas y descuento superior al subtotal. En las diez pruebas coincidieron antes y después las huellas de pedido, pagos, comprobantes, turnos, correlativo y conteo de auditoría.
- H-002: el pedido 23 se fechó 23:50 y sus primeros pagos 00:10 como datos del ensayo. La venta recobrada se atribuyó al día del pago y al cobrador 2, distinto del creador 3. El gasto retroactivo de S/ 3,00 apareció en resultados del día anterior y en caja en el turno 18. El cierre 18 conservó S/ 10,00 de ventas y S/ 3,00 de gastos. El turno 19 registró S/ 20,00 brutos y S/ 10,00 de reversos; el detalle vigente mostró solo el recobro.

### C-001: fallo inicial y repetición tras corregirlo

La barrera liberó ambos restaurantes cuando dos solicitudes de `api/abrir_turno.php` ya esperaban. A abrió caja y B recibió HTTP 200 con `success:false` y «Ocurrió un error interno en el servidor». Se repitió con B primero y A primero y quedó el mismo resultado en ambos órdenes. Los logs de PHP registraron `PDOException (40001)` a las 10:36:01, 10:36:43 y 10:36:45. La última captura de `SHOW ENGINE INNODB STATUS` confirmó:

| Conexión | Transacción | Espera en `mesora_ola1_test.turnos` |
|---|---|---|
| 774 | 17414 | Intención de inserción con bloqueo exclusivo en el índice `restaurante_id`. |
| 777 | 17415 | Retenía un bloqueo exclusivo sobre `restaurante_id` y esperaba intención de inserción en `PRIMARY`. InnoDB revirtió esta transacción. |

Ambas ejecutaban el `INSERT INTO turnos`. La base quedó con una sola apertura. En `api/abrir_turno.php` se conservó el bloqueo exclusivo de la fila del restaurante. Bajo ese bloqueo, la primera lectura consistente comprueba si ya hay un turno abierto, sin tomar el bloqueo de rango en `turnos` que provocó el interbloqueo. La transacción sigue incluyendo la apertura y su evento de auditoría.

En la repetición, la conexión observadora confirmó que las dos solicitudes HTTP esperaban antes de liberar la barrera. Con A primero y con B primero se confirmó una apertura por restaurante. En los controles con el mismo restaurante, la primera sesión abrió una caja, se guardó un evento de auditoría y la segunda recibió «Ya existe una caja abierta en este momento». Se repitió intercambiando las sesiones. La prueba usó el aislamiento `REPEATABLE READ` de la instancia existente y no requirió migración.

### Auditoría y copia restaurada

El corte de las 10:24 contenía 2 restaurantes, 4 usuarios, 23 pedidos, 25 pagos, 13 reversos, 19 turnos, 3 gastos, 2 comprobantes internos y 161 eventos de auditoría. Los cierres sumaban S/ 268,00 en ventas netas de reversos. La edición y anulación del gasto de ensayo registraron importes 4,00 → 5,00 → 0,00 con el actor 2 y fecha. La copia conserva los eventos de apertura y cierre del turno 11 con el mismo actor, el saldo inicial de S/ 0,00 y el resumen final de S/ 4,00 en gastos. Los dos disparadores restaurados de 019 rechazaron `UPDATE` y `DELETE` con SQLSTATE `45000`. Los conteos y totales siguieron iguales después de probarlos. Las carreras adicionales con barreras sobre pedido y mesa se ejecutaron después del corte.

En el alojamiento compartido, el error `#1142` reportado para el usuario del sitio confirma que la protección de 019 queda pendiente allí por falta del permiso `TRIGGER`. El instalador 008 crea la tabla de auditoría y la aplicación puede seguir registrando sus eventos.

## Evidencia y hallazgos del dictamen anterior

| Revisión | Resultado |
|---|---|
| Entrega y correcciones | La entrega local cubre H-001, H-002, H-003, H-004, H-005, H-007, H-021, H-022, H-023 y H-015. Se corrigieron la guarda de 004, el número histórico de mesa y la coordinación de borrado de mesa con alta de pedido. H-006 continúa en Ola 2. Las pruebas anteriores de predicados, PDO simulado y sesión CGI tienen el alcance descrito en sus informes. [Entrega](10_entrega_ola_1.md), [aceptación y correcciones](12_aceptacion_integral_ola_1.md). |
| Migraciones | 004 y 006 son compatibles por diseño con el esquema de referencia. Faltan verificación real, permiso efectivo, ejecución y contraste posterior. 005 y 007 se revisaron por lectura. [Validación de migraciones](13_validacion_migraciones_ola1.md). |
| Integridad financiera, concurrencia y aislamiento por rol | Faltan pruebas con InnoDB, dos conexiones y sesiones reales. Queda sin demostrar la persistencia de cierres, la ausencia de doble cobro, el aislamiento de restaurantes y el rollback ante fallo de auditoría. [Matriz de pruebas](14_resultados_pruebas_mariadb_ola1.md). |
| Recuperación | No existen mediciones ni comparaciones de un respaldo restaurado. [Plan y registro](15_respaldo_restauracion_ola1.md). |
| Secretos | `.env` está ignorado y solo `.env.example` apareció entre los nombres revisados de configuración privada y claves. El escaneo de patrones frecuentes en los archivos de código/configuración inspeccionados no encontró coincidencias. Es una revisión parcial del árbol actual. Quedan el historial completo, artefactos, rotación si procede y verificación del paquete que se desplegará. |
| Errores y logs | El `php.ini` de XAMPP inspeccionado tiene `display_errors=On`, `log_errors=On` y log bajo `C:\xampp\php\logs`. Confirmar en el SAPI de destino `display_errors=Off`, logs habilitados, acceso limitado y ausencia de secretos en los registros. |
| HTTPS, sesiones y permisos | El código configura HttpOnly, SameSite y Secure condicional a HTTPS. Falta demostrar el comportamiento con el hosting, certificado y proxy efectivos, el aislamiento de sesiones y los permisos NTFS/hosting sobre configuración, directorios de sesión, archivos, logs y respaldos. Verificar 403/404 para archivos privados. Excluir `.git`, volcados, evidencia y scripts de la raíz pública. |
| Hosting | Solo se identificó el binario local MariaDB 10.4.32. Falta comprobar en el destino autorizado PHP/SAPI, módulos, versión MariaDB, InnoDB, SQL mode, permisos DDL/DML, zona horaria, reglas Apache y límites de importación. |

## Condiciones del dictamen anterior

1. Aprovisionar y verificar el aislamiento de la VM. Acreditar identidad de la instancia y esquema antes de cualquier cambio. [Paquete de entorno](../operaciones/paquete_entorno_ola1.md).
2. Obtener autorización expresa y verificar 004/006 en ensayo con el inventario y respaldo base. Resolver cualquier discrepancia antes de abrir el código nuevo.
3. Completar casos de caja, historial, concurrencia, seguridad y rol, y conciliar pagos, gastos, reversos y comprobantes. Resolver cualquier fallo crítico mediante una corrección autorizada y volver a probar el caso.
4. Demostrar restauración, medir tiempos, acordar RPO/RTO y verificar el hosting autorizado y sus diferencias respecto al ensayo.

## Despliegue y reversión

El despliegue requiere autorización independiente. En la ventana aprobada, verificar esquema y respaldo restaurable, cerrar turnos con la versión vigente, detener escrituras, aplicar 004 y 006 manualmente y comprobar el inventario antes de habilitar el código nuevo. Ejecutar los controles de humo y de conciliación con los permisos del destino. [Procedimiento de despliegue](../operaciones/despliegue_ola_1.md).

Ante un fallo, detener escrituras y preservar evidencia y el corte posterior. Si hay movimientos que dependen de Ola 1, conservar la estructura y conciliar los cambios. Recuperar primero en un destino independiente o corregir hacia adelante. La reversión 005 está limitada a una instalación completa y sin dependencias de Ola 1. La 007 elimina contadores tras volver al login anterior. Cualquier reversión en destino requiere autorización, respaldo y revisión de sus guardas. Durante este ensayo no se ejecutaron 005 ni 007. [Procedimiento de recuperación](../operaciones/backup_restore.md).
