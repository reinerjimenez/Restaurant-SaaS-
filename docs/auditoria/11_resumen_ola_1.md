# Ola 1 · Informe resumido de cambios

La revisión contra Git encontró **58 archivos modificados y 21 archivos nuevos** en la entrega de la Ola 1, sobre la base local `393a2e9`. Con este informe son **22 archivos nuevos**. El trabajo sigue en el árbol local y la aceptación integral está pendiente. [Informe técnico por hallazgo](10_entrega_ola_1.md).

## Los cuatro scripts SQL

| Archivo | Para qué sirve | Cuándo se usa |
|---|---|---|
| [004_ola_1_finanzas.sql](../../Database/004_ola_1_finanzas.sql) | Agrega `pagos.turno_id`, `gastos.turno_id`, `turnos.cierre_json` y las tablas `reversos_pago` y `envios_comanda`. Los movimientos antiguos quedan sin atribución automática. | **Avance.** Ejecutar manualmente en phpMyAdmin con respaldo, escrituras detenidas y todos los turnos cerrados. Primero en la base aislada. |
| [005_reversion_ola_1_finanzas.sql](../../Database/005_reversion_ola_1_finanzas.sql) | Retira las dos tablas, las tres columnas y las claves agregadas por 004. | **Solo reversión.** Aplicable sobre un 004 completo, con respaldo y escrituras detenidas. El script bloquea la reversión si hay un turno abierto, un cierre nuevo, pagos o gastos vinculados, reversos o envíos registrados. Si bloquea, conservar la estructura y los datos. Un 004 aplicado parcialmente requiere revisar el esquema antes de continuar. |
| [006_ola_1_intentos_login.sql](../../Database/006_ola_1_intentos_login.sql) | Crea `login_limites` para registrar contadores temporales por cuenta e IP. | **Avance.** Ejecutar manualmente en phpMyAdmin después de 004 y antes de probar el login nuevo. |
| [007_reversion_ola_1_intentos_login.sql](../../Database/007_reversion_ola_1_intentos_login.sql) | Retira `login_limites` y sus contadores. | **Solo reversión.** Usar con accesos detenidos después de volver a una versión del login que no dependa de esa tabla. |

**Orden normal de ensayo: 004 → 006 → verificar esquema → probar el código.** El 005 y el 007 se guardan para una reversión. Si ya se registraron operaciones financieras con el esquema nuevo, mantener la caja en mantenimiento mientras se concilia y se elige una versión compatible. [Guía de despliegue y reversión](../operaciones/despliegue_ola_1.md).

## 58 archivos modificados

| Cambio | Archivos |
|---|---|
| Caja y cierres | `api/abrir_turno.php`, `api/cerrar_turno.php`, `roles/admin/caja.php`, `roles/admin/turno_detalle.php`. Bloqueos, registro de caja y lectura del cierre guardado. |
| Pedido y comanda | `api/crear_pedido.php`, `api/agregar_item.php`, `api/eliminar_item.php`, `api/cancelar_pedido.php`, `roles/atencion/comanda.php`. Orden de bloqueos, subtotal y reintento con la misma clave. |
| Cobros, comprobantes y reversos | `api/cobrar_pedido.php`, `api/guardar_comprobante.php`, `api/anular_comprobante.php`, `api/reabrir_venta.php`, `api/eliminar_venta.php`, `roles/atencion/cobrar.php`. Validación en céntimos, ajustes al reabrir, reversos y auditoría. |
| Gastos | `roles/admin/gastos.php`, `roles/atencion/gastos.php`. Turno obligatorio, anulación lógica y formularios ligados al restaurante. |
| Reportes e historiales | `api/get_pedido_detalle.php`, `api/get_reportes.php`, `roles/admin/dashboard.php`, `roles/admin/reportes.php`, `roles/admin/historial.php`, `roles/atencion/historial.php`, `roles/superadmin/dashboard.php`. Fechas de cobro, cobrador y ventas agregadas por restaurante. |
| Sesión, contexto y navegación | `auth/login.php`, `auth/logout.php`, `config/db.php`, `includes/header.php`, `includes/sidebar.php`, `index.php`, `roles/admin/config_facturacion.php`. Inicio central, expiración, logout POST, contexto en peticiones y auditoría obligatoria. |
| Contraseñas y gestión de usuarios | `api/admin_usuarios.php`, `api/superadmin_restaurantes.php`, `roles/admin/usuarios.php`, `roles/superadmin/perfil.php`, `roles/superadmin/restaurantes.php`. Política uniforme y mensajes de formulario. |
| Solo inicio central de sesión, API | `api/admin_categorias.php`, `api/admin_mesas.php`, `api/admin_productos.php`, `api/consultar_documento.php`, `api/get_menu.php`, `api/get_mesas.php`, `api/get_opciones.php`, `api/get_pedidos_activos.php`, `api/marcar_item_listo.php`. Se retiró la llamada local a `session_start()`. |
| Solo inicio central de sesión, pantallas | `roles/admin/auditoria.php`, `roles/admin/historial_comprobantes.php`, `roles/admin/menu_categorias.php`, `roles/admin/menu_productos.php`, `roles/admin/mesas.php`, `roles/admin/ver_comprobante.php`, `roles/atencion/comprobante_view.php`, `roles/atencion/dashboard.php`, `roles/cocina/dashboard.php`. Se retiró la llamada local a `session_start()`. |
| Grafo generado | `graphify-out/GRAPH_REPORT.md`, `graphify-out/graph.html`, `graphify-out/graph.json`, `graphify-out/manifest.json`. Se regeneraron al actualizar Graphify. |

## 22 archivos nuevos, incluido este informe

| Grupo | Archivos y propósito |
|---|---|
| SQL, 4 | `Database/004_ola_1_finanzas.sql`, `Database/005_reversion_ola_1_finanzas.sql`, `Database/006_ola_1_intentos_login.sql`, `Database/007_reversion_ola_1_intentos_login.sql`. Avance y reversión descritos arriba. |
| Acceso, 4 | `auth/seleccionar_restaurante.php` cambia contexto por POST. `config/credenciales.php` valida contraseñas. `config/limite_login.php` controla intentos. `config/session.php` inicia, expira y destruye sesiones. |
| Finanzas, 3 | `config/finanzas.php` calcula y persiste operaciones de caja. `includes/gastos_financieros.php` guarda y audita gastos. `includes/reportes_financieros.php` comparte consultas y cálculo de reportes. |
| Pruebas, 6 | `tests/ola_1_cobros.php` cubre ajustes y pagos. `tests/ola1_reportes_gastos.php` cubre reportes y auditoría con PDO simulado. `tests/ola_1_comanda.mjs` cubre reintentos. `tests/ola_1_contexto_http.mjs` comprueba cabeceras. `tests/ola_1_seguridad.php` verifica credenciales y sesiones. `docs/operaciones/pruebas/sesion_cgi.py` comprueba cookies y logout. |
| Documentación, 5 | `docs/auditoria/10_entrega_ola_1.md` documenta los hallazgos. `docs/auditoria/11_resumen_ola_1.md` es este inventario. `docs/operaciones/aceptacion_ola_1.md` lista casos de ensayo. `docs/operaciones/backup_restore.md` guía la recuperación. `docs/operaciones/despliegue_ola_1.md` indica el corte y la reversión. |

## Estado de validación

- Pasaron la sintaxis de 65 archivos PHP y 18 bloques JavaScript, y las seis pruebas locales de la tabla. Es evidencia de lógica, contratos, reintentos y cookies sin conectar a MariaDB.
- Quedan pendientes la importación de 004 y 006, los bloqueos concurrentes, la conciliación de cierres y reversos, las dos pestañas autenticadas y la restauración de un respaldo. H-006, conservación histórica del catálogo, sigue en Ola 2. [Guion de aceptación](../operaciones/aceptacion_ola_1.md).

