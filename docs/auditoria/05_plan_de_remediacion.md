# Plan de remediación ejecutable por etapas

Fecha: 2026-11-09. **Propuestas, no correcciones aplicadas.** Estimaciones por tarea: S <2 h, M hasta un día, L varios días; los plazos por ola son orientativos y requieren equipo/disponibilidad. Priorizar la dependencia antes que el calendario.

Cada entrega conserva contrato JSON y autorización del servidor, se prueba en copia aislada y se despliega sola. Scripts estructurales/datos iniciales: Database/NNN_*.sql manualmente, con copia previa y reversión. No ejecutar instaladores completos sobre una base existente.

## Ola 0 — Urgente (24–48 h)

No añadir restaurantes. Restringir acceso a vistas/operaciones afectadas hasta corregirlas; retirar instalador defectuoso del procedimiento de altas.

| Hallazgo | Tarea | Esfuerzo | Regresión | Dependencia |
|---|---|---|---|---|
| H-010 | XSS almacenado alcanza sesiones con mayor autoridad | M | Alto | Ola anterior o independiente dentro de la ola |
| H-000 | .env con token no vacío está versionado | M | Medio | Ola anterior o independiente dentro de la ola |
| H-019 | Encargado puede degradar y cambiar credenciales del admin | S | Medio | Ola anterior o independiente dentro de la ola |
| H-020 | Desactivar usuario o restaurante no revoca sesiones | M | Medio | política de sesión |
| H-018 | El instalador de producción no crea comprobantes utilizable | M | Alto | DDL verificado por contrato |

### H-010: entrega y reversa

**Prompt:** Corrige H-010 en api/agregar_item.php:39-55; roles/admin/mesas.php:378-408; roles/cocina/dashboard.php:217-250; roles/admin/menu_productos.php:92,208,250,280; assets/js/main.js:166-170. Usar textContent y construcción DOM para datos; eliminar datos interpolados en onclick y serializar JSON fuera de atributos. Escapar por contexto; CSP sin unsafe-inline como defensa adicional después de retirar handlers. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Guardar etiquetas inertes, comillas y apóstrofes en cada entrada; verificar que se ven literalmente en detalle, cocina, menú e historial con todos los roles.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-000: entrega y reversa

**Prompt:** Corrige H-000 en .env:1-7; .gitignore:1-2; config/db.php:32,54; git log --all -- .env. Rotar el token con su proveedor, retirar .env del seguimiento y agregar .env.example vacío; desplegar configuración fuera del artefacto. Coordinar limpieza del historial y clones después de rotar. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** git ls-files .env no devuelve archivo; escáner sin valores; despliegue de ensayo mantiene configuración del servidor; token anterior rechazado por el proveedor.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-019: entrega y reversa

**Prompt:** Corrige H-019 en api/admin_usuarios.php:68-99. Antes de editar, verificar tanto rol actual del objetivo como rol solicitado contra una matriz de jerarquía; encargado solo gestiona atención/cocina. Aplicar también a borrado y a la propia cuenta. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Encargado intenta editar cada rol con y sin password; admin permanece intacto; operaciones autorizadas sobre atención/cocina siguen funcionando.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-020: entrega y reversa

**Prompt:** Corrige H-020 en config/db.php:126-175; auth/login.php:41-56; api/superadmin_restaurantes.php:41-49. Revalidar usuario activo, rol y restaurante activo centralmente, y usar versión de sesión/revocación al cambiar credenciales; no confiar indefinidamente en el rol cacheado. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Con dos sesiones, desactivar/reducir permisos/cambiar contraseña y comprobar rechazo en la siguiente petición y en un nuevo login a restaurante inactivo.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-018: entrega y reversa

**Prompt:** Corrige H-018 en Database/002_produccion_limpia.sql:55-79,499-500; Database/000_restaurante_db.sql:80-106,620-628; api/guardar_comprobante.php:128-157. Reconstruir 002 con DDL completo y comparar tabla por tabla contra un dump válido; conservar solo el superadmin. No importar sobre producción existente. Validar importación manual en base vacía y revertir descartando solo esa base de ensayo. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** validar_contratos.py detecta dos columnas y PK ausentes; luego importación manual completa y ciclo crear/cobrar/imprimir. No basta contar 17 CREATE TABLE.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

**Criterio de salida:** Se cerraron entradas XSS comprobadas, editar admin por encargado se rechaza, sesiones revocan, token se rotó y un instalador válido pasó ensayo manual.

## Ola 1 — Estabilizar (1–2 semanas)

Depende de Ola 0. Antes de H-001 acordar política de turnos/reversos y preparar respaldo. H-002 define fechas antes de ajustar índices.

| Hallazgo | Tarea | Esfuerzo | Regresión | Dependencia |
|---|---|---|---|---|
| H-007 | Agregar ítems puede terminar después del cobro o cancelación | M | Alto | Ola anterior o independiente dentro de la ola |
| H-021 | Recobrar venta reabierta aplica ajustes sobre total ya ajustado | M | Alto | H-007; fórmula de ajustes |
| H-023 | Pagos rechazados sí alteran acumuladores y snapshot | M | Alto | Ola anterior o independiente dentro de la ola |
| H-002 | Reportes atribuyen ventas y gastos a fechas diferentes | M | Alto | Ola anterior o independiente dentro de la ola |
| H-001 | Las correcciones de ventas reescriben cajas cerradas | L | Alto | H-002, H-021, H-023; respaldo |
| H-003 | Login sin limitación propia de intentos | M | Medio | Ola anterior o independiente dentro de la ola |
| H-004 | La aplicación no fija política de sesión | M | Medio | Ola anterior o independiente dentro de la ola |
| H-005 | Cambiar restaurante por GET afecta a pestañas antiguas | M | Medio | H-010; contexto esperado |
| H-015 | Operación carece de evidencia de restauración y trazabilidad completa | L | Medio | responsable de restore |

### H-007: entrega y reversa

**Prompt:** Corrige H-007 en api/agregar_item.php:25-27,51-55,82-83; api/cobrar_pedido.php:30-35,80-81; api/cancelar_pedido.php:24-25,50-51. Bloquear el mismo pedido FOR UPDATE antes de validar estado en agregar_item; mantener orden de locks compatible con cobro, cancelación y eliminación. Añadir idempotencia de envío para respuesta perdida. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Intercalar agregar/cobrar y agregar/cancelar desde sesiones diferentes: una operación espera y rechaza tras el cambio de estado; reintentar un envío no duplica ítems.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-021: entrega y reversa

**Prompt:** Corrige H-021 en api/reabrir_venta.php:115-116; api/cobrar_pedido.php:26-44,80-81; api/guardar_comprobante.php:65-66; roles/atencion/cobrar.php:251,389-392. Calcular subtotal de ítems de servidor bajo lock y aplicar una única vez los ajustes vigentes. Definir explícitamente qué ajustes conserva la reapertura. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Repetir tres ciclos de reabrir/cobrar, con descuento/cargo y agregando/eliminando platos; total = suma ítems + cargo - descuento en cada ciclo.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-023: entrega y reversa

**Prompt:** Corrige H-023 en api/cobrar_pedido.php:50-62,100-123; api/guardar_comprobante.php:156-175,209-231. Validar y normalizar todo el array una vez antes de escribir; rechazar entradas inválidas y usar solo pagos aceptados para insert, JSON y acumuladores. Trabajar en céntimos y limitar ajustes según política acordada. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Probar negativos, cero, método desconocido, fracciones menores a céntimo y descuentos excesivos; todo inválido falla íntegramente sin filas ni cambios parciales.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-002: entrega y reversa

**Prompt:** Corrige H-002 en roles/admin/dashboard.php:16-27; roles/admin/reportes.php:16-57; roles/admin/caja.php:26-55; roles/admin/turno_detalle.php:60-75; api/get_pedido_detalle.php:49-51. Definir fecha operativa de cada movimiento y etiqueta de cada reporte; usar pago/turno para caja, fecha contable para gasto con distinción visible; filtrar anulados y corregir otro/cobrador. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Pedido cruzando medianoche, pago otro, usuario creador distinto del cobrador, gasto retroactivo y recobro: conciliar todas las pantallas con tabla de movimientos.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-001: entrega y reversa

**Prompt:** Corrige H-001 en api/cerrar_turno.php:43-53; api/reabrir_venta.php:64-108; api/eliminar_venta.php:51-98; roles/admin/caja.php:63-74; roles/admin/turno_detalle.php:36-85. Relacionar movimientos de cobro/gasto con turno y registrar reversos en turno actual; conservar cierre inmutable. Bloquear el turno en cobro y cierre con el mismo orden de locks. No reconstruir cierre desde updated_at. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Cerrar, exportar totales y detalle; reabrir, recobrar, editar gasto y comprobar que el cierre exportado sigue igual y el ajuste aparece en turno actual.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-003: entrega y reversa

**Prompt:** Corrige H-003 en auth/login.php:15-38; api/admin_usuarios.php:41. Limitar por cuenta e IP con ventana temporal y retardo, sin permitir bloqueo permanente provocado por terceros; registrar intentos sin contraseña. Definir una política de contraseñas superior. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Repetir intentos contra cuenta de ensayo y comprobar límite, recuperación y no enumeración. No se hizo fuerza bruta en esta auditoría.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-004: entrega y reversa

**Prompt:** Corrige H-004 en config/db.php:126-129,179-183; auth/login.php:6,51; auth/logout.php:20-22. Configurar HttpOnly, Secure con HTTPS, SameSite y strict_mode antes del primer session_start; controlar inactividad/tiempo absoluto; caducar cookie al salir. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Inspeccionar Set-Cookie bajo HTTPS, rechazar id no emitido, medir expiración y comprobar logout. No inferir flags efectivos solo leyendo PHP.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-005: entrega y reversa

**Prompt:** Corrige H-005 en config/db.php:135-148; includes/sidebar.php:91-97; includes/header.php:65-85. Cambio explícito POST+CSRF; enviar contexto esperado ligado a cada formulario/petición y rechazar discrepancia. Mostrar restaurante actual también al superadmin operativo. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Dos pestañas con A/B; formulario viejo no crea ni cambia datos. GET set_rest_id no modifica sesión.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-015: entrega y reversa

**Prompt:** Corrige H-015 en config/db.php:101-119; api/abrir_turno.php:44-45; api/cerrar_turno.php:112-113; roles/admin/gastos.php:19-43; roles/atencion/gastos.php:18-26; api/superadmin_restaurantes.php:20-74; README.md:313-395. Registrar eventos de dinero junto al cambio y proteger su retención; runbook y ensayo de restore manual; registro de despliegues, errores con id y pruebas de caja/aislamiento. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Eliminar/editar gasto y abrir/cerrar: antes/después y actor quedan trazados. Restaurar exportación en base aislada, conciliar conteos/totales y medir tiempo.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

**Criterio de salida:** Cobro/reapertura/cierre concilian bajo concurrencia y medianoche; cierres exportados no cambian; sesión/limites/contexto pasan pruebas; restore ensayado y registrado.

## Ola 2 — Preparar crecimiento (1–2 meses)

Depende de semántica de dinero/fechas estable. Centralizar permisos al implementar H-019 y reforzarlos aquí; documentar migraciones/observabilidad de H-015.

| Hallazgo | Tarea | Esfuerzo | Regresión | Dependencia |
|---|---|---|---|---|
| H-006 | Borrados y edición de opciones alteran el histórico | M | Medio | Ola anterior o independiente dentro de la ola |
| H-008 | Filtros de fecha y falta de índices compuestos encarecen consultas | M | Medio | H-002; EXPLAIN |
| H-012 | Polling continuo consume capacidad compartida | M | Bajo | Ola anterior o independiente dentro de la ola |
| H-014 | Consulta DNI/RUC no limita cuota por usuario | M | Bajo | Ola anterior o independiente dentro de la ola |
| H-011 | Comprobantes internos se presentan como boleta o factura | M | Medio | Ola anterior o independiente dentro de la ola |

### H-006: entrega y reversa

**Prompt:** Corrige H-006 en api/admin_productos.php:66-69,131-136; api/admin_categorias.php:49-56; api/admin_mesas.php:59-64; api/agregar_item.php:51-55; Database/002_produccion_limpia.sql:630-667. Inactivar catálogo/mesas/usuarios con historia; guardar nombre y opciones como snapshot al crear ítem; preservar opciones referenciadas al editar. Capturar FK con respuesta JSON adecuada. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Registrar pedido con opciones, renombrar/editar/inactivar/borrar catálogo: detalle, estadísticas y comprobante mantienen su contenido histórico.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-008: entrega y reversa

**Prompt:** Corrige H-008 en api/get_reportes.php:20-52; roles/admin/caja.php:63-74; Database/002_produccion_limpia.sql:418-430; Database/000_restaurante_db.sql:683-689. Usar rangos [inicio, siguiente día) y evaluar índices restaurante/estado/fecha y pagos/pedido/fecha con EXPLAIN en motor real. Índices solo después de corregir semántica de fecha. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Comparar EXPLAIN, filas examinadas y p95 sobre copias con tamaños representativos; conservar resultados antes/después.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-012: entrega y reversa

**Prompt:** Corrige H-012 en assets/js/main.js:91-124; roles/cocina/dashboard.php:318-323; roles/atencion/dashboard.php:341-355. Pausar pestañas ocultas, evitar solicitudes solapadas y aplicar retroceso al fallar; medir cuotas por cuenta y migrar antes del margen de saturación. Ver fuentes en 04. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Contar requests una hora por pestaña visible/oculta y simular latencia; comparar uso agregado con panel del proveedor.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-014: entrega y reversa

**Prompt:** Corrige H-014 en api/consultar_documento.php:10-18,41-59. Límite por usuario/restaurante y presupuesto global; cache corto con acceso controlado y minimización de datos personales; fallback manual ya disponible. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Cuenta de ensayo alcanza límite local sin superar cuota real; verificar que se rechaza antes de cURL y conserva carga manual.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

### H-011: entrega y reversa

**Prompt:** Corrige H-011 en roles/admin/ver_comprobante.php:140-141; roles/atencion/comprobante_view.php:117-118; api/guardar_comprobante.php:81-99,119-125; README.md:89-94. Etiquetar documento interno sin validez tributaria y separar emisión fiscal real. Revisar régimen/IGV y comunicación con contador; privacidad con asesor especializado. Fuentes oficiales en 04. Conserva comentarios en español, PHP/BD snake_case, controles táctiles de 44 px y JSON estándar. No cambies BD automáticamente. Si requiere esquema, prepara Database/NNN_*.sql y su reversión para ejecución manual. Añade una prueba del escenario, sin depender de datos de producción.

**Aceptación:** Imprimir cada tipo y comprobar aviso; verificar que facturación real se emite por canal autorizado y no se anuncia capacidad inexistente.

**Despliegue/reversa:** Publicar una versión identificada del cambio y probar el flujo afectado antes de habilitarlo. Conservar código anterior. Si falla, detener el flujo afectado, volver al código anterior y reconciliar movimientos nuevos antes de restaurar datos; una reversión de código no deshace cobros. Cambios aditivos de esquema se dejan compatibles si quitarlos perdería datos. Para H-018 se descarta únicamente la base vacía de ensayo fallida; jamás se borra producción.

**Criterio de salida:** Históricos preservados al editar menú; EXPLAIN/p95 medidos; cuota/polling con margen; documentos internos identificados; matriz A/B completa y veredicto revisado.

## Ola 3 — Escalar

Depende del checklist Go/No-Go completo. No consolidar por calendario: piloto primero, métricas después.

1. Medir una semana real y recalcular presupuesto de capacidad de 04.
2. Elegir alojamiento con capacidad y respaldo comprobados; precio del SaaS/coste aún pendientes.
3. Ensayar mapeo de ids y consolidación de 03, conservando instancias origen y ventana de corte.
4. Extraer servicios compartidos solo cuando los flujos de dinero tengan pruebas; mantener URLs.
5. Introducir jobs/caché/SSE/framework solo con los disparadores documentados en 04.

**Prompt:** Ejecuta un piloto de consolidación según 03 y 06 con exportaciones privadas, mapeo de ids, validación de pertenencia y conciliación por restaurante. Produce plan de corte y reversa revisables antes de cualquier importación. Ninguna escritura a BD automática; todo SQL inicial/esquema se entrega en Database/. No publicar ni desplegar los informes de auditoría.

**Criterio de salida:** Piloto estable con monitoreo, restore por instancia y reversa de consolidación ensayada; proveedor/costo/RPO/RTO aprobados para carga objetivo.

## Priorización operativa

H-011 se puede comunicar y etiquetar inmediatamente aunque su revisión funcional esté en Ola 2. H-008 no debe retrasar arreglos monetarios. H-012 puede exigir cambio de hosting antes de consolidar aun si el código es correcto. No instalar una tabla turno_id sin modificar todos los escritores/lectores y definir la atribución histórica. No reconstruir datos históricos por heurística silenciosa.

Mejoras del proceso de auditoría: 08_mejoras_al_prompt.md. Diseño de propuestas SQL y reversa: propuestas_sql/README.md.
