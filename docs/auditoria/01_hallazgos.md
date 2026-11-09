# Hallazgos de auditoría

Fecha: 2026-11-09. Base ca007d3. Revisión de código y contratos estáticos; no explotación ni modificación de BD. CONFIRMADO significa demostrable por flujo de código, no probado sobre producción. Las prioridades no prueban incidentes ocurridos.

## Resumen

| Severidad | Cantidad |
|---|---|
| Crítico | 1 |
| Alto | 10 |
| Medio | 9 |

| Categoría | Cantidad |
|---|---|
| Seguridad | 6 |
| Base de datos | 2 |
| Dinero/Integridad | 6 |
| Multi-inquilino | 1 |
| Rendimiento | 2 |
| Operación | 2 |
| Legal | 1 |

## [H-010] XSS almacenado alcanza sesiones con mayor autoridad

- Severidad: **Crítico**. Confianza: **CONFIRMADO**.
- Categoría: Seguridad.
- Ubicación: api/agregar_item.php:39-55; roles/admin/mesas.php:378-408; roles/cocina/dashboard.php:217-250; roles/admin/menu_productos.php:92,208,250,280; assets/js/main.js:166-170.
- Evidencia: `${item.notas} ... innerHTML = html`.
- Escenario de fallo: Atención guarda una nota con HTML que crea un manejador; admin o superadmin abre el detalle. El navegador interpreta la nota como HTML. Nombres/opciones ofrecen otras entradas.
- Impacto en el negocio: El código corre con la sesión de la víctima y puede realizar operaciones con su CSRF. Si la víctima es superadmin, alcanza su autoridad global; esa cadena no se ejecutó contra usuarios reales.
- Recomendación: Usar textContent y construcción DOM para datos; eliminar datos interpolados en onclick y serializar JSON fuera de atributos. Escapar por contexto; CSP sin unsafe-inline como defensa adicional después de retirar handlers.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Alto.
- Cómo verificar la corrección: Guardar etiquetas inertes, comillas y apóstrofes en cada entrada; verificar que se ven literalmente en detalle, cocina, menú e historial con todos los roles.
- Prompt de corrección: «Corrige H-010: Usar textContent y construcción DOM para datos; eliminar datos interpolados en onclick y serializar JSON fuera de atributos. Escapar por contexto; CSP sin unsafe-inline como defensa adicional después de retirar handlers. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-000] .env con token no vacío está versionado

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Seguridad.
- Ubicación: .env:1-7; .gitignore:1-2; config/db.php:32,54; git log --all -- .env.
- Evidencia: `loadEnv(__DIR__ . '/../.env');`.
- Escenario de fallo: Un clon o un despliegue desde Git incorpora el archivo con token; un checkout puede sustituir la configuración del servidor si se despliega también .env.
- Impacto en el negocio: Exposición del token a quienes tienen el repositorio y riesgo de agotar su cuota. La validez actual del token y la visibilidad pública del remoto no se pudieron comprobar.
- Recomendación: Rotar el token con su proveedor, retirar .env del seguimiento y agregar .env.example vacío; desplegar configuración fuera del artefacto. Coordinar limpieza del historial y clones después de rotar.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: git ls-files .env no devuelve archivo; escáner sin valores; despliegue de ensayo mantiene configuración del servidor; token anterior rechazado por el proveedor.
- Prompt de corrección: «Corrige H-000: Rotar el token con su proveedor, retirar .env del seguimiento y agregar .env.example vacío; desplegar configuración fuera del artefacto. Coordinar limpieza del historial y clones después de rotar. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-018] El instalador de producción no crea comprobantes utilizable

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Base de datos.
- Ubicación: Database/002_produccion_limpia.sql:55-79,499-500; Database/000_restaurante_db.sql:80-106,620-628; api/guardar_comprobante.php:128-157.
- Evidencia: `MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;`.
- Escenario de fallo: En el instalador 002 faltan pagos_json, created_at, ENGINE/collation y los índices/FK de comprobantes. Se intenta AUTO_INCREMENT sin que id tenga índice. El importador puede dejar un esquema parcial al fallar.
- Impacto en el negocio: Una instalación nueva no está lista para cobrar con comprobante. No se afirma que las instancias actuales tengan ese esquema.
- Recomendación: Reconstruir 002 con DDL completo y comparar tabla por tabla contra un dump válido; conservar solo el superadmin. No importar sobre producción existente. Validar importación manual en base vacía y revertir descartando solo esa base de ensayo.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Alto.
- Cómo verificar la corrección: validar_contratos.py detecta dos columnas y PK ausentes; luego importación manual completa y ciclo crear/cobrar/imprimir. No basta contar 17 CREATE TABLE.
- Prompt de corrección: «Corrige H-018: Reconstruir 002 con DDL completo y comparar tabla por tabla contra un dump válido; conservar solo el superadmin. No importar sobre producción existente. Validar importación manual en base vacía y revertir descartando solo esa base de ensayo. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-019] Encargado puede degradar y cambiar credenciales del admin

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Seguridad.
- Ubicación: api/admin_usuarios.php:68-99.
- Evidencia: `if ($usuarioTarget['rol'] === 'superadmin') ...`.
- Escenario de fallo: Encargado manda editar con id de un admin del mismo restaurante, nuevo password y rol atencion. El rol solicitado está permitido y el rol actual del objetivo solo se compara con superadmin.
- Impacto en el negocio: Desactiva/degrada al administrador y cambia su contraseña. No puede conservar rol admin con este payload: no se presenta como escalada directa a admin.
- Recomendación: Antes de editar, verificar tanto rol actual del objetivo como rol solicitado contra una matriz de jerarquía; encargado solo gestiona atención/cocina. Aplicar también a borrado y a la propia cuenta.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: S. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Encargado intenta editar cada rol con y sin password; admin permanece intacto; operaciones autorizadas sobre atención/cocina siguen funcionando.
- Prompt de corrección: «Corrige H-019: Antes de editar, verificar tanto rol actual del objetivo como rol solicitado contra una matriz de jerarquía; encargado solo gestiona atención/cocina. Aplicar también a borrado y a la propia cuenta. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-020] Desactivar usuario o restaurante no revoca sesiones

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Seguridad.
- Ubicación: config/db.php:126-175; auth/login.php:41-56; api/superadmin_restaurantes.php:41-49.
- Evidencia: `in_array($_SESSION['rol'], $rolesPermitidos)`.
- Escenario de fallo: Usuario inicia sesión; admin lo desactiva/cambia de rol/password o superadmin desactiva el restaurante. La siguiente petición solo usa los atributos que quedaron en sesión. Login tampoco comprueba restaurantes.activo.
- Impacto en el negocio: Un exempleado conserva permisos; una cuenta puede volver a ingresar aunque su restaurante esté desactivado si usuarios.activo sigue en 1.
- Recomendación: Revalidar usuario activo, rol y restaurante activo centralmente, y usar versión de sesión/revocación al cambiar credenciales; no confiar indefinidamente en el rol cacheado.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Con dos sesiones, desactivar/reducir permisos/cambiar contraseña y comprobar rechazo en la siguiente petición y en un nuevo login a restaurante inactivo.
- Prompt de corrección: «Corrige H-020: Revalidar usuario activo, rol y restaurante activo centralmente, y usar versión de sesión/revocación al cambiar credenciales; no confiar indefinidamente en el rol cacheado. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-001] Las correcciones de ventas reescriben cajas cerradas

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Dinero/Integridad.
- Ubicación: api/cerrar_turno.php:43-53; api/reabrir_venta.php:64-108; api/eliminar_venta.php:51-98; roles/admin/caja.php:63-74; roles/admin/turno_detalle.php:36-85.
- Evidencia: `pe.updated_at BETWEEN ? AND NOW(); UPDATE turnos SET total_general = GREATEST(0, total_general - ?)`.
- Escenario de fallo: Cerrar turno T con S/100; reabrir/eliminar una venta de S/40. El código busca un turno por fecha de pagos y resta incluso si está cerrado. Historial recalcula pedidos/gastos con datos vivos.
- Impacto en el negocio: T pasa de S/100 a S/60 y su desglose puede cambiar. Rangos inclusivos pueden asignar el instante frontera a dos turnos; no hay turno_id para atribución inequívoca.
- Recomendación: Relacionar movimientos de cobro/gasto con turno y registrar reversos en turno actual; conservar cierre inmutable. Bloquear el turno en cobro y cierre con el mismo orden de locks. No reconstruir cierre desde updated_at.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: L. Riesgo de regresión: Alto.
- Cómo verificar la corrección: Cerrar, exportar totales y detalle; reabrir, recobrar, editar gasto y comprobar que el cierre exportado sigue igual y el ajuste aparece en turno actual.
- Prompt de corrección: «Corrige H-001: Relacionar movimientos de cobro/gasto con turno y registrar reversos en turno actual; conservar cierre inmutable. Bloquear el turno en cobro y cierre con el mismo orden de locks. No reconstruir cierre desde updated_at. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-021] Recobrar venta reabierta aplica ajustes sobre total ya ajustado

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Dinero/Integridad.
- Ubicación: api/reabrir_venta.php:115-116; api/cobrar_pedido.php:26-44,80-81; api/guardar_comprobante.php:65-66; roles/atencion/cobrar.php:251,389-392.
- Evidencia: `$totalFinal = max(0, $totalOriginal + $cargo_extra - $descuento);`.
- Escenario de fallo: Ítems S/100, descuento S/10: total guardado S/90. Reabrir solo cambia estado. Volver a introducir S/10 de descuento calcula S/80 sobre los S/90; con ajuste cero quedan campos de ajuste que ya no explican el total.
- Impacto en el negocio: S/10 menos por repetición en ese ejemplo y pérdida de conciliación entre ítems/ajustes/total. Cargo extra repetido produce el efecto contrario.
- Recomendación: Calcular subtotal de ítems de servidor bajo lock y aplicar una única vez los ajustes vigentes. Definir explícitamente qué ajustes conserva la reapertura.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Alto.
- Cómo verificar la corrección: Repetir tres ciclos de reabrir/cobrar, con descuento/cargo y agregando/eliminando platos; total = suma ítems + cargo - descuento en cada ciclo.
- Prompt de corrección: «Corrige H-021: Calcular subtotal de ítems de servidor bajo lock y aplicar una única vez los ajustes vigentes. Definir explícitamente qué ajustes conserva la reapertura. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-007] Agregar ítems puede terminar después del cobro o cancelación

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Dinero/Integridad.
- Ubicación: api/agregar_item.php:25-27,51-55,82-83; api/cobrar_pedido.php:30-35,80-81; api/cancelar_pedido.php:24-25,50-51.
- Evidencia: `SELECT id, total FROM pedidos WHERE ... estado = 'activo'`.
- Escenario de fallo: Sesión A lee pedido activo sin lock y se pausa. B cobra y confirma. A inserta un ítem y actualiza total por id sin comprobar estado nuevamente. Las sesiones distintas no se serializan por el lock de sesión PHP.
- Impacto en el negocio: Pedido cobrado/cancelado con ítems nuevos y total no respaldado por pagos; cocina puede no mostrarlo. Secuencia demostrable en código; no se ejecutó carrera en BD.
- Recomendación: Bloquear el mismo pedido FOR UPDATE antes de validar estado en agregar_item; mantener orden de locks compatible con cobro, cancelación y eliminación. Añadir idempotencia de envío para respuesta perdida.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Alto.
- Cómo verificar la corrección: Intercalar agregar/cobrar y agregar/cancelar desde sesiones diferentes: una operación espera y rechaza tras el cambio de estado; reintentar un envío no duplica ítems.
- Prompt de corrección: «Corrige H-007: Bloquear el mismo pedido FOR UPDATE antes de validar estado en agregar_item; mantener orden de locks compatible con cobro, cancelación y eliminación. Añadir idempotencia de envío para respuesta perdida. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-023] Pagos rechazados sí alteran acumuladores y snapshot

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Dinero/Integridad.
- Ubicación: api/cobrar_pedido.php:50-62,100-123; api/guardar_comprobante.php:156-175,209-231.
- Evidencia: `if (!in_array($metodo, $metodosValidos) || $monto <= 0) continue;`.
- Escenario de fallo: Total S/100, pagos efectivo +100 y efectivo -40. Inserción descarta -40 y valida 100. Segundo bucle suma ambas entradas al acumulador: efectivo +60, general +100. El comprobante guarda el array original.
- Impacto en el negocio: Diferencia S/40 entre pagos, turno y comprobante. Descuento/cargo/cantidades tampoco tienen límites consistentes ni autorización especial por rol.
- Recomendación: Validar y normalizar todo el array una vez antes de escribir; rechazar entradas inválidas y usar solo pagos aceptados para insert, JSON y acumuladores. Trabajar en céntimos y limitar ajustes según política acordada.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Alto.
- Cómo verificar la corrección: Probar negativos, cero, método desconocido, fracciones menores a céntimo y descuentos excesivos; todo inválido falla íntegramente sin filas ni cambios parciales.
- Prompt de corrección: «Corrige H-023: Validar y normalizar todo el array una vez antes de escribir; rechazar entradas inválidas y usar solo pagos aceptados para insert, JSON y acumuladores. Trabajar en céntimos y limitar ajustes según política acordada. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-002] Reportes atribuyen ventas y gastos a fechas diferentes

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Dinero/Integridad.
- Ubicación: roles/admin/dashboard.php:16-27; roles/admin/reportes.php:16-57; roles/admin/caja.php:26-55; roles/admin/turno_detalle.php:60-75; api/get_pedido_detalle.php:49-51.
- Evidencia: `DATE(pa.created_at) = ? / DATE(pe.created_at) BETWEEN ? AND ? / gastos.fecha`.
- Escenario de fallo: Pedido abierto 23:50 y pagado 00:10: ventas por apertura lo ponen en día anterior; dashboard por pago lo pone en siguiente. Gasto registrado hoy con fecha ayer cambia utilidad de ayer pero caja de hoy. Detalle agrupa otros aunque enum es otro.
- Impacto en el negocio: El mismo dinero no concilia por día/turno/cajero; detalle de pagos además devuelve anulados como realizados y atribuye cobro al creador pe.usuario_id en lugar del cobrador pagos.usuario_id.
- Recomendación: Definir fecha operativa de cada movimiento y etiqueta de cada reporte; usar pago/turno para caja, fecha contable para gasto con distinción visible; filtrar anulados y corregir otro/cobrador.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Alto.
- Cómo verificar la corrección: Pedido cruzando medianoche, pago otro, usuario creador distinto del cobrador, gasto retroactivo y recobro: conciliar todas las pantallas con tabla de movimientos.
- Prompt de corrección: «Corrige H-002: Definir fecha operativa de cada movimiento y etiqueta de cada reporte; usar pago/turno para caja, fecha contable para gasto con distinción visible; filtrar anulados y corregir otro/cobrador. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-022] Panel global multiplica importes al combinar varias relaciones

- Severidad: **Alto**. Confianza: **CONFIRMADO**.
- Categoría: Dinero/Integridad.
- Ubicación: roles/superadmin/dashboard.php:26-37.
- Evidencia: `LEFT JOIN usuarios ... LEFT JOIN mesas ... LEFT JOIN pedidos ... SUM(pe.total)`.
- Escenario de fallo: Restaurante con 2 usuarios, 3 mesas y un pedido cobrado S/100 produce 6 filas antes de agrupar; SUM(pe.total) devuelve S/600 aunque COUNT(DISTINCT pedido) sea 1.
- Impacto en el negocio: El superadmin interpreta S/600 de ventas cuando hubo S/100. La tarjeta global y la fila por restaurante no coinciden.
- Recomendación: Agregar ventas por restaurante antes de unir a agregados separados de usuarios/mesas; no usar SUM(DISTINCT total), que perdería pedidos de igual importe.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: S. Riesgo de regresión: Bajo.
- Cómo verificar la corrección: Dos pedidos iguales de S/100, 2 usuarios y 3 mesas: fila S/200, nunca S/1200 ni S/100.
- Prompt de corrección: «Corrige H-022: Agregar ventas por restaurante antes de unir a agregados separados de usuarios/mesas; no usar SUM(DISTINCT total), que perdería pedidos de igual importe. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-006] Borrados y edición de opciones alteran el histórico

- Severidad: **Medio**. Confianza: **CONFIRMADO**.
- Categoría: Base de datos.
- Ubicación: api/admin_productos.php:66-69,131-136; api/admin_categorias.php:49-56; api/admin_mesas.php:59-64; api/agregar_item.php:51-55; Database/002_produccion_limpia.sql:630-667.
- Evidencia: `DELETE FROM opciones_grupo WHERE producto_id = ?; ON DELETE CASCADE`.
- Escenario de fallo: Editar un producto borra/recrea sus opciones: cascada borra pedido_item_opciones antiguos. Borrar categoría sin productos activos puede eliminar productos inactivos; nombre_producto no se llena al insertar ítems. Mesas borradas quedan sin número en pedidos.
- Impacto en el negocio: Históricos cambian de nombre, pierden opciones o dejan de aparecer en consultas con JOIN productos. Usuarios con pedidos pueden fallar por FK en vez de borrarse.
- Recomendación: Inactivar catálogo/mesas/usuarios con historia; guardar nombre y opciones como snapshot al crear ítem; preservar opciones referenciadas al editar. Capturar FK con respuesta JSON adecuada.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Registrar pedido con opciones, renombrar/editar/inactivar/borrar catálogo: detalle, estadísticas y comprobante mantienen su contenido histórico.
- Prompt de corrección: «Corrige H-006: Inactivar catálogo/mesas/usuarios con historia; guardar nombre y opciones como snapshot al crear ítem; preservar opciones referenciadas al editar. Capturar FK con respuesta JSON adecuada. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-003] Login sin limitación propia de intentos

- Severidad: **Medio**. Confianza: **CONFIRMADO**.
- Categoría: Seguridad.
- Ubicación: auth/login.php:15-38; api/admin_usuarios.php:41.
- Evidencia: `WHERE email = :email LIMIT 1; password_verify(...)`.
- Escenario de fallo: Un cliente repite POST con token de su propia sesión y contraseñas distintas. No hay contador, espera progresiva ni bloqueo de la aplicación.
- Impacto en el negocio: Aumenta el riesgo de adivinar contraseñas de seis caracteres; el límite real del servidor no se conoce y no sustituye el control por cuenta.
- Recomendación: Limitar por cuenta e IP con ventana temporal y retardo, sin permitir bloqueo permanente provocado por terceros; registrar intentos sin contraseña. Definir una política de contraseñas superior.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Repetir intentos contra cuenta de ensayo y comprobar límite, recuperación y no enumeración. No se hizo fuerza bruta en esta auditoría.
- Prompt de corrección: «Corrige H-003: Limitar por cuenta e IP con ventana temporal y retardo, sin permitir bloqueo permanente provocado por terceros; registrar intentos sin contraseña. Definir una política de contraseñas superior. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-004] La aplicación no fija política de sesión

- Severidad: **Medio**. Confianza: **PROBABLE**.
- Categoría: Seguridad.
- Ubicación: config/db.php:126-129,179-183; auth/login.php:6,51; auth/logout.php:20-22.
- Evidencia: `session_start(); session_regenerate_id(true);`.
- Escenario de fallo: Las cookies y duración efectiva dependen de php.ini/hosting. No hay session_set_cookie_params, caducidad por inactividad ni control de strict_mode en el código.
- Impacto en el negocio: Si el hosting usa valores débiles, cookie accesible desde JS o enviada sin HTTPS y sesión prolongada. La ausencia de flags efectivos en producción no fue comprobada.
- Recomendación: Configurar HttpOnly, Secure con HTTPS, SameSite y strict_mode antes del primer session_start; controlar inactividad/tiempo absoluto; caducar cookie al salir.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Inspeccionar Set-Cookie bajo HTTPS, rechazar id no emitido, medir expiración y comprobar logout. No inferir flags efectivos solo leyendo PHP.
- Prompt de corrección: «Corrige H-004: Configurar HttpOnly, Secure con HTTPS, SameSite y strict_mode antes del primer session_start; controlar inactividad/tiempo absoluto; caducar cookie al salir. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-005] Cambiar restaurante por GET afecta a pestañas antiguas

- Severidad: **Medio**. Confianza: **CONFIRMADO**.
- Categoría: Multi-inquilino.
- Ubicación: config/db.php:135-148; includes/sidebar.php:91-97; includes/header.php:65-85.
- Evidencia: `$_SESSION['restaurante_id'] = $nuevoRestId;`.
- Escenario de fallo: Superadmin abre formulario nuevo de A, cambia a B en otra pestaña y guarda el de A. La API toma restaurante_id de sesión y crea en B. Un enlace GET puede cambiar la selección sin token.
- Impacto en el negocio: Creaciones o acciones se aplican al contexto inesperado. IDs de A suelen ser rechazados en B, pero formularios de creación no tienen esa protección.
- Recomendación: Cambio explícito POST+CSRF; enviar contexto esperado ligado a cada formulario/petición y rechazar discrepancia. Mostrar restaurante actual también al superadmin operativo.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Dos pestañas con A/B; formulario viejo no crea ni cambia datos. GET set_rest_id no modifica sesión.
- Prompt de corrección: «Corrige H-005: Cambio explícito POST+CSRF; enviar contexto esperado ligado a cada formulario/petición y rechazar discrepancia. Mostrar restaurante actual también al superadmin operativo. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-008] Filtros de fecha y falta de índices compuestos encarecen consultas

- Severidad: **Medio**. Confianza: **CONFIRMADO**.
- Categoría: Rendimiento.
- Ubicación: api/get_reportes.php:20-52; roles/admin/caja.php:63-74; Database/002_produccion_limpia.sql:418-430; Database/000_restaurante_db.sql:683-689.
- Evidencia: `DATE(created_at) BETWEEN ? AND ?; ADD KEY restaurante_id (restaurante_id)`.
- Escenario de fallo: Con historial creciente se filtran todas las filas del restaurante y luego fechas; caja agrega subconsultas para cada turno devuelto.
- Impacto en el negocio: Más CPU y latencia con crecimiento. No se midió un tiempo ni se ejecutó EXPLAIN; otros índices existentes pueden servir parcialmente. No se afirma que DATE inutilice todos los índices en cualquier MariaDB.
- Recomendación: Usar rangos [inicio, siguiente día) y evaluar índices restaurante/estado/fecha y pagos/pedido/fecha con EXPLAIN en motor real. Índices solo después de corregir semántica de fecha.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Comparar EXPLAIN, filas examinadas y p95 sobre copias con tamaños representativos; conservar resultados antes/después.
- Prompt de corrección: «Corrige H-008: Usar rangos [inicio, siguiente día) y evaluar índices restaurante/estado/fecha y pagos/pedido/fecha con EXPLAIN en motor real. Índices solo después de corregir semántica de fecha. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-012] Polling continuo consume capacidad compartida

- Severidad: **Medio**. Confianza: **PROBABLE**.
- Categoría: Rendimiento.
- Ubicación: assets/js/main.js:91-124; roles/cocina/dashboard.php:318-323; roles/atencion/dashboard.php:341-355.
- Evidencia: `return setInterval(() => { fetch(endpoint) ... }, intervalo);`.
- Escenario de fallo: Una cocina a 10 s y dos mapas a 25 s durante 12 h generan unas 7.776 solicitudes/día por restaurante solo en polling, aun en pestañas ocultas; con 10 son 77.760.
- Impacto en el negocio: Puede agotar cupos de la cuenta y suspender todos los restaurantes alojados allí; falta medir CPU, tráfico real y límites del panel.
- Recomendación: Pausar pestañas ocultas, evitar solicitudes solapadas y aplicar retroceso al fallar; medir cuotas por cuenta y migrar antes del margen de saturación. Ver fuentes en 04.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Bajo.
- Cómo verificar la corrección: Contar requests una hora por pestaña visible/oculta y simular latencia; comparar uso agregado con panel del proveedor.
- Prompt de corrección: «Corrige H-012: Pausar pestañas ocultas, evitar solicitudes solapadas y aplicar retroceso al fallar; medir cuotas por cuenta y migrar antes del margen de saturación. Ver fuentes en 04. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-014] Consulta DNI/RUC no limita cuota por usuario

- Severidad: **Medio**. Confianza: **CONFIRMADO**.
- Categoría: Operación.
- Ubicación: api/consultar_documento.php:10-18,41-59.
- Evidencia: `curl_setopt_array(... CURLOPT_TIMEOUT => 8 ...);`.
- Escenario de fallo: Usuario autorizado repite GET válidos con números distintos; cada solicitud llama al proveedor usando un único token compartido. El debounce del navegador no controla clientes directos.
- Impacto en el negocio: Agota cuota externa y bloquea búsquedas para otros restaurantes. Coste y límite contratados no conocidos.
- Recomendación: Límite por usuario/restaurante y presupuesto global; cache corto con acceso controlado y minimización de datos personales; fallback manual ya disponible.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Bajo.
- Cómo verificar la corrección: Cuenta de ensayo alcanza límite local sin superar cuota real; verificar que se rechaza antes de cURL y conserva carga manual.
- Prompt de corrección: «Corrige H-014: Límite por usuario/restaurante y presupuesto global; cache corto con acceso controlado y minimización de datos personales; fallback manual ya disponible. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-015] Operación carece de evidencia de restauración y trazabilidad completa

- Severidad: **Medio**. Confianza: **CONFIRMADO**.
- Categoría: Operación.
- Ubicación: config/db.php:101-119; api/abrir_turno.php:44-45; api/cerrar_turno.php:112-113; roles/admin/gastos.php:19-43; roles/atencion/gastos.php:18-26; api/superadmin_restaurantes.php:20-74; README.md:313-395.
- Evidencia: `registrarAuditoria captura Throwable; gastos UPDATE/DELETE no llaman registrarAuditoria`.
- Escenario de fallo: Admin borra un gasto o se abre/cierra caja sin evento de auditoría. El helper puede fallar tras el commit y no bloquear la venta. No hay runbook, control de migraciones ni pruebas de restauración/pruebas automáticas en el árbol auditado.
- Impacto en el negocio: Ajustes de dinero sin explicación y recuperación no demostrada. No se concluye que no existan respaldos externos: su existencia es pendiente.
- Recomendación: Registrar eventos de dinero junto al cambio y proteger su retención; runbook y ensayo de restore manual; registro de despliegues, errores con id y pruebas de caja/aislamiento.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: L. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Eliminar/editar gasto y abrir/cerrar: antes/después y actor quedan trazados. Restaurar exportación en base aislada, conciliar conteos/totales y medir tiempo.
- Prompt de corrección: «Corrige H-015: Registrar eventos de dinero junto al cambio y proteger su retención; runbook y ensayo de restore manual; registro de despliegues, errores con id y pruebas de caja/aislamiento. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## [H-011] Comprobantes internos se presentan como boleta o factura

- Severidad: **Medio**. Confianza: **CONFIRMADO**.
- Categoría: Legal.
- Ubicación: roles/admin/ver_comprobante.php:140-141; roles/atencion/comprobante_view.php:117-118; api/guardar_comprobante.php:81-99,119-125; README.md:89-94.
- Evidencia: `if ($comp['tipo'] === 'boleta') echo 'Boleta de Venta';`.
- Escenario de fallo: La API admite boleta/factura y genera números y cálculo fijo de IGV; no implementa emisión/aceptación SUNAT. Ocultar opciones en HTML no deshabilita los tipos del endpoint.
- Impacto en el negocio: Cliente puede confundir un documento interno con uno fiscal. Se confirma ambigüedad de producto, no una infracción legal ni el régimen tributario de un restaurante.
- Recomendación: Etiquetar documento interno sin validez tributaria y separar emisión fiscal real. Revisar régimen/IGV y comunicación con contador; privacidad con asesor especializado. Fuentes oficiales en 04.
- Compatibilidad: Ahora (PHP puro/hosting compartido); actuaciones del proveedor requieren su panel. Migración de hosting indicada aparte.
- Esfuerzo: M. Riesgo de regresión: Medio.
- Cómo verificar la corrección: Imprimir cada tipo y comprobar aviso; verificar que facturación real se emite por canal autorizado y no se anuncia capacidad inexistente.
- Prompt de corrección: «Corrige H-011: Etiquetar documento interno sin validez tributaria y separar emisión fiscal real. Revisar régimen/IGV y comunicación con contador; privacidad con asesor especializado. Fuentes oficiales en 04. Conserva las autorizaciones existentes, comentarios en español y JSON estándar. Agrega una comprobación específica del escenario descrito. Cualquier cambio de BD irá en Database/NNN_*.sql con reversión y ejecución manual, nunca desde PHP.»

## Lo que está bien hecho

- PDO usa excepciones y preparación nativa (`config/db.php:65-70`); valores de búsqueda y ids se enlazan. Las columnas dinámicas de correlativo se eligen de un conjunto fijo (`api/guardar_comprobante.php:81-100`), sin inyección SQL demostrada.
- Contraseñas mediante bcrypt/password_verify y regeneración al login (`api/admin_usuarios.php:48`; `auth/login.php:34,51`). No se imprimieron hashes en los informes.
- Token aleatorio con random_bytes y hash_equals; protección central de POST (`config/db.php:179-227`), también formularios de login/gastos.
- Cobro y emisión bloquean pedido FOR UPDATE y validan estado en transacción; la segunda confirmación del mismo pedido no vuelve a cobrar (`api/cobrar_pedido.php:23-40`; `api/guardar_comprobante.php:47-63`).
- Propiedad de pedido/producto/turno/comprobante se valida en rutas operativas; opciones se vinculan con grupo y producto (`api/agregar_item.php:65-72`).
- Importes almacenados DECIMAL, índice único de mesa por restaurante y turno abierto por restaurante (`Database/002_produccion_limpia.sql:199-209,397-399,466-470`).
- Logo limitado por MIME real, extensión derivada y tamaño; SVG excluido (`roles/admin/config_facturacion.php:36-74`). No se demostró ejecución remota de código por carga de logo.
- cURL con verificación TLS y timeout de 8 segundos, y entrada manual alternativa (`api/consultar_documento.php:45-64`).
