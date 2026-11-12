# Entrega técnica de la Ola 1

11 de noviembre de 2026. Base local de trabajo `393a2e9`, con la Ola 0 incorporada. Los cambios están en el árbol local. La aceptación integral está pendiente. [Guion de aceptación](../operaciones/aceptacion_ola_1.md).

| Hallazgo | Estado | Archivos principales | Evidencia |
|---|---|---|---|
| H-001 | Implementado, ensayo pendiente | `config/finanzas.php`, API de caja, reapertura y reverso, vistas de caja, SQL 004/005 | Contratos y lectura de cierres con PDO simulado |
| H-002 | Implementado, conciliación pendiente | `includes/reportes_financieros.php`, dashboards, reportes, historiales y detalle de pedido | Fecha contable, corte de medianoche y pagos mixtos con PDO simulado |
| H-003 | Implementado, integración pendiente | `auth/login.php`, `config/limite_login.php`, `config/credenciales.php`, escritores de usuario, SQL 006/007 | Política de contraseña y ventanas de intentos verificadas sin MariaDB |
| H-004 | Implementado, aceptación por roles pendiente | `config/session.php`, `config/db.php`, puntos de entrada y logout | Expiración con reloj de prueba y cookie/logout con PHP CGI |
| H-005 | Implementado, ensayo de dos pestañas pendiente | `auth/seleccionar_restaurante.php`, `config/db.php`, cabecera, sidebar y formularios operativos | Comparación de contexto y cabeceras con transporte simulado |
| H-007 | Implementado, concurrencia en InnoDB pendiente | API de pedidos, `config/finanzas.php`, `roles/atencion/comanda.php`, SQL 004/005 | Reintento de la interfaz con cuerpo y clave idénticos |
| H-021 | Implementado, ciclo completo pendiente | `config/finanzas.php`, API de cobro/comprobante/reapertura, vista de cobro | Tres ciclos de cálculo y cargo adicional con PDO simulado |
| H-022 | Implementado, consulta en MariaDB pendiente | `roles/superadmin/dashboard.php`, `includes/reportes_financieros.php` | Contrato de consulta y suma con restaurantes sintéticos |
| H-023 | Implementado, atomicidad en MariaDB pendiente | `config/finanzas.php`, API de cobro/comprobante, vista de cobro | Pagos inválidos, mixtos, insuficientes y excedentes con PDO simulado |
| H-015 | Auditoría implementada y recuperación documentada. Restauración pendiente | `config/db.php`, API financieras, módulos de gastos, guías de operaciones | Edición/anulación y rollback por fallo de auditoría con PDO simulado |
| H-006 | Pendiente en Ola 2 | Alta de ítems y copia de la venta al revertir | Conservación parcial de datos nuevos. Catálogo histórico pendiente |

## H-001 · Caja y reversos

Los rangos de actividad podían cambiar los importes de un cierre. Cobros y gastos nuevos se vinculan a un turno. Cada pago revertido queda ligado al turno del ajuste y conserva su importe original. La reapertura y la anulación de venta requieren un turno abierto. Los cierres conservan el resumen de gastos, métodos y responsables. El cierre comprueba el identificador del turno que la pantalla presentó para evitar cerrar otro turno por una solicitud repetida.

El modelo registra el pago bruto en su turno y resta el reverso en el turno donde ocurre. El histórico anterior al corte mantiene los totales que ya tenía guardados. La atribución faltante se deja pendiente de conciliación. El historial permite consultar el estado actual del pedido como información separada del resumen cerrado.

## H-002 · Fechas y responsables

Los ingresos se agrupan por fecha de pago en intervalos con fin exclusivo. Los gastos usan la fecha contable en resultados y el turno de registro en caja. La consulta de productos usa existencia de pagos para contar cada ítem una vez. El detalle excluye pagos anulados de los pagos vigentes e identifica al cobrador de cada pago. Una vista de ingresos vigentes puede variar tras una anulación, mientras los cierres conservan su resumen.

## H-003 · Intentos y contraseñas

El límite coordina cuenta e IP del servidor. Se permiten hasta 30 fallos de IP por ventana de 15 minutos y se aplica espera progresiva de hasta 60 segundos por cuenta desde el quinto fallo. Un intento rechazado durante la espera no amplía esa pausa. El acceso correcto reinicia el contador de cuenta. Las nuevas contraseñas requieren 12 caracteres y un máximo de 72 bytes con bcrypt. El acceso conserva compatibilidad con las credenciales existentes. La verificación de límites con conexiones concurrentes requiere el esquema de ensayo.

## H-004 · Sesiones

El arranque central aplica modo estricto y cookies HttpOnly, SameSite=Lax y Secure en HTTPS. Elegí 30 minutos sin peticiones válidas y 12 horas de duración absoluta. El logout por POST valida CSRF, destruye la sesión y expira su cookie. Las sesiones previas sin marcas de tiempo tendrán que autenticarse de nuevo. Se conserva la comprobación de versión, rol y estado de la Ola 0.

## H-005 · Contexto de restaurante

La selección se hace por POST con CSRF. Las API operativas de superadmin reciben el restaurante esperado y un token nuevo por selección. Los formularios operativos llevan los mismos valores y las peticiones obsoletas se rechazan. La cabecera muestra el restaurante seleccionado y las páginas globales mantienen su ámbito. Las navegaciones GET normales presentan la selección vigente.

## H-007 · Concurrencia e idempotencia

Las transacciones financieras coordinan el acceso con un bloqueo por restaurante y validan el estado bajo bloqueo de pedido. La clave y el resultado de cada envío se guardan junto con sus ítems. La interfaz conserva el envío pendiente en la pestaña, reintenta el mismo cuerpo si se pierde la respuesta y evita que se edite mientras el resultado es incierto. El ensayo concurrente en InnoDB sigue pendiente. El bloqueo por restaurante favorece un orden claro y puede reducir el paralelismo dentro de un restaurante.

## H-021 · Reapertura y ajustes

El cobro deriva el subtotal de los ítems persistidos y aplica los ajustes en céntimos. La decisión aplicada es conservar descuento y cargo al reabrir, mostrarlos en el siguiente cobro y volver a calcular sobre el subtotal vigente. Se comprobó el cálculo con un total cobrado anterior y con ítems nuevos.

## H-022 · Panel global

Usuarios, mesas y ventas se agregan por separado por restaurante. La tarjeta de ventas suma las cifras de las filas. El caso sintético de dos cobros iguales por S/ 100 conserva ambos importes. La prueba inspecciona el contrato de consulta y usa resultados simulados.

## H-023 · Pagos y comprobantes

Los dos flujos de cobro comparten validación y cálculo. Un método inválido, referencia inválida, importe fuera de rango, negativo, cero, con fracciones de céntimo o suma distinta del total rechaza el conjunto. La lista normalizada alimenta pagos y comprobante. Se mantiene el alcance de roles existente para ajustes y se exige un total positivo.

## H-015 · Auditoría y recuperación

Aperturas, cierres, gastos, cobros, anulaciones de comprobante y reversos registran auditoría dentro de la transacción. Los gastos de turno abierto pueden ajustarse con auditoría. Las anulaciones de gasto conservan el registro con estado inactivo. Elegí requerir un turno abierto para registrar gastos de caja y proteger los gastos ya cerrados o sin turno identificable. Se preparó el registro de despliegue, el respaldo y la conciliación. El RPO y RTO requieren decisión del negocio. [Respaldo y restauración](../operaciones/backup_restore.md).

## H-006 · Histórico de catálogo

Las nuevas líneas guardan el nombre y el reverso captura el detalle disponible y los ajustes antes de editar el pedido reabierto. El catálogo puede seguir perdiendo referencias antiguas a opciones. La captura de reverso conserva lo disponible al revertir y no recupera referencias que ya se perdieron. Mantener la revisión de borrados y edición de opciones en la Ola 2.

## SQL y reversión

| Script | Acción manual | Reversión |
|---|---|---|
| `004_ola_1_finanzas.sql` | Añade vínculos de turno, resumen de cierre, reversos y registro de envíos. Requiere detener escrituras y cerrar turnos antes del corte. Deja sin asignar el legado. | `005_reversion_ola_1_finanzas.sql` solo antes de que existan datos dependientes. Si ya hay movimientos, conservarlos y conciliar antes de recuperar una versión anterior. |
| `006_ola_1_intentos_login.sql` | Añade contadores temporales de acceso. | `007_reversion_ola_1_intentos_login.sql` retira los contadores después de restaurar el código previo. |

Los scripts se prepararon para la estructura local y el binario MariaDB 10.4.32. La importación, permisos y versión del servidor de destino siguen pendientes. Ejecutar el avance manualmente en phpMyAdmin antes de usar el código en ensayo. [Guía de despliegue y reversión](../operaciones/despliegue_ola_1.md).

## Pruebas ejecutadas

- PHP 8.2.35 verificó la sintaxis de 65 archivos PHP. Node verificó 18 scripts embebidos con valores PHP sustituidos.
- `tests/ola_1_cobros.php` comprobó la fórmula y las entradas inválidas y mixtas.
- `tests/ola1_reportes_gastos.php` comprobó fechas, contratos de consulta y auditoría de gastos con PDO simulado.
- `tests/ola_1_seguridad.php` completó 27 comprobaciones. `tests/ola_1_contexto_http.mjs` completó 8 con transporte simulado.
- `tests/ola_1_comanda.mjs` comprobó el reintento de una respuesta perdida. `docs/operaciones/pruebas/sesion_cgi.py` comprobó cookies HTTP/HTTPS, token del formulario y logout.
- Graphify se actualizó con extracción estática. Su entorno carece de extractor SQL.

La regla de base de datos deja pendientes la importación, los bloqueos concurrentes, la conciliación sobre datos de ensayo, las dos pestañas autenticadas y la restauración medida. Los instaladores previos y el plan de remediación se conservaron. H-022 se incluye por instrucción del encargo aunque no figure en la tabla de Ola 1 del plan. El trabajo quedó sin commit, push ni despliegue.

## Inventario de archivos

**Modificados:**

- `api/abrir_turno.php`
- `api/admin_categorias.php`
- `api/admin_mesas.php`
- `api/admin_productos.php`
- `api/admin_usuarios.php`
- `api/agregar_item.php`
- `api/anular_comprobante.php`
- `api/cancelar_pedido.php`
- `api/cerrar_turno.php`
- `api/cobrar_pedido.php`
- `api/consultar_documento.php`
- `api/crear_pedido.php`
- `api/eliminar_item.php`
- `api/eliminar_venta.php`
- `api/get_menu.php`
- `api/get_mesas.php`
- `api/get_opciones.php`
- `api/get_pedido_detalle.php`
- `api/get_pedidos_activos.php`
- `api/get_reportes.php`
- `api/guardar_comprobante.php`
- `api/marcar_item_listo.php`
- `api/reabrir_venta.php`
- `api/superadmin_restaurantes.php`
- `auth/login.php`
- `auth/logout.php`
- `config/db.php`
- `graphify-out/GRAPH_REPORT.md`
- `graphify-out/graph.html`
- `graphify-out/graph.json`
- `graphify-out/manifest.json`
- `includes/header.php`
- `includes/sidebar.php`
- `index.php`
- `roles/admin/auditoria.php`
- `roles/admin/caja.php`
- `roles/admin/config_facturacion.php`
- `roles/admin/dashboard.php`
- `roles/admin/gastos.php`
- `roles/admin/historial.php`
- `roles/admin/historial_comprobantes.php`
- `roles/admin/menu_categorias.php`
- `roles/admin/menu_productos.php`
- `roles/admin/mesas.php`
- `roles/admin/reportes.php`
- `roles/admin/turno_detalle.php`
- `roles/admin/usuarios.php`
- `roles/admin/ver_comprobante.php`
- `roles/atencion/cobrar.php`
- `roles/atencion/comanda.php`
- `roles/atencion/comprobante_view.php`
- `roles/atencion/dashboard.php`
- `roles/atencion/gastos.php`
- `roles/atencion/historial.php`
- `roles/cocina/dashboard.php`
- `roles/superadmin/dashboard.php`
- `roles/superadmin/perfil.php`
- `roles/superadmin/restaurantes.php`

**Creados:**

- `Database/004_ola_1_finanzas.sql`
- `Database/005_reversion_ola_1_finanzas.sql`
- `Database/006_ola_1_intentos_login.sql`
- `Database/007_reversion_ola_1_intentos_login.sql`
- `auth/seleccionar_restaurante.php`
- `config/credenciales.php`
- `config/finanzas.php`
- `config/limite_login.php`
- `config/session.php`
- `docs/auditoria/10_entrega_ola_1.md`
- `docs/operaciones/aceptacion_ola_1.md`
- `docs/operaciones/backup_restore.md`
- `docs/operaciones/despliegue_ola_1.md`
- `docs/operaciones/pruebas/sesion_cgi.py`
- `includes/gastos_financieros.php`
- `includes/reportes_financieros.php`
- `tests/ola1_reportes_gastos.php`
- `tests/ola_1_cobros.php`
- `tests/ola_1_comanda.mjs`
- `tests/ola_1_contexto_http.mjs`
- `tests/ola_1_seguridad.php`

