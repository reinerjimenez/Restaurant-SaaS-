# Matriz de endpoints y modelo de amenazas

Fecha: 2026-11-09. Revisión estática. Base de referencia: commit ca007d3. No se hicieron peticiones a producción ni se abrió una conexión a BD.

¹ `config/db.php:200-227` valida CSRF para POST antes de despachar. GET no requiere token, incluido cambio de contexto y logout. Métodos GET de API representan su uso previsto; varias lecturas no rechazan otros verbos. La matriz no certifica el comportamiento HTTP desplegado.
² Toda ruta que llama requireRole puede mutar el contexto del superadmin si recibe set_rest_id (`config/db.php:135-148`); por ello la idempotencia es condicional. Incluidos header/footer son plantillas internas, no endpoints de negocio; deben impedir acceso directo.

| Ruta | Método | Roles | CSRF | Aislamiento | Auditoría | Tablas | Modifica datos | Idempotente |
|---|---|---|---|---|---|---|---|---|
| `api/abrir_turno.php` | POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | turnos | Sí | No |
| `api/admin_categorias.php` | POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | categorias, productos | Sí | Depende de acción; crear no |
| `api/admin_mesas.php` | POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | mesas, pedidos | Sí | Depende de acción; crear no |
| `api/admin_productos.php` | POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | categorias, opciones_grupo, opciones_valor, pedido_items, pedidos, productos | Sí | Depende de acción; crear no |
| `api/admin_usuarios.php` | POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Crear/eliminar; editar no | usuarios | Sí | Depende de acción; crear no |
| `api/agregar_item.php` | POST | atencion, admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | opciones_grupo, opciones_valor, pedido_item_opciones, pedido_items, pedidos, productos | Sí | No |
| `api/anular_comprobante.php` | POST | admin, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | comprobantes | Sí | No |
| `api/cancelar_pedido.php` | POST | atencion, admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | mesas, pedido_items, pedidos | Sí | No |
| `api/cerrar_turno.php` | POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | pagos, pedidos, turnos | Sí | No |
| `api/cobrar_pedido.php` | POST | atencion, admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | mesas, pagos, pedidos, turnos | Sí | No |
| `api/consultar_documento.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Sin consultas por restaurante | No | — | No² | Sí² |
| `api/crear_pedido.php` | POST | atencion, admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | mesas, pedidos, turnos | Sí | No |
| `api/eliminar_item.php` | POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | pedido_item_opciones, pedido_items, pedidos | Sí | No |
| `api/eliminar_venta.php` | POST | admin, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | comprobantes, pagos, pedidos, turnos | Sí | No |
| `api/get_menu.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | categorias, productos | No² | Sí² |
| `api/get_mesas.php` | GET | atencion, admin, encargado, superadmin, cocina | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | mesas, pedido_items, pedidos | No² | Sí² |
| `api/get_opciones.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | opciones_grupo, opciones_valor, productos | No² | Sí² |
| `api/get_pedido_detalle.php` | GET | admin, encargado, superadmin, atencion | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | mesas, opciones_valor, pagos, pedido_item_opciones, pedido_items, pedidos, productos | No² | Sí² |
| `api/get_pedidos_activos.php` | GET | cocina, atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | mesas, opciones_valor, pedido_item_opciones, pedido_items, pedidos, productos | No² | Sí² |
| `api/get_reportes.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | pagos, pedido_items, pedidos, productos, turnos | No² | Sí² |
| `api/guardar_comprobante.php` | POST | atencion, admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | comprobantes, facturacion_config, mesas, opciones_valor, pagos, pedido_item_opciones, pedido_items, pedidos, productos, turnos | Sí | No |
| `api/marcar_item_listo.php` | POST | cocina, admin, encargado, superadmin, atencion | POST central¹ | Filtro restaurante y/o padre validado | No | pedido_items, pedidos | Sí | No |
| `api/reabrir_venta.php` | POST | admin, superadmin | POST central¹ | Filtro restaurante y/o padre validado | Sí | comprobantes, mesas, pagos, pedidos, turnos | Sí | No |
| `api/superadmin_restaurantes.php` | POST | superadmin | POST central¹ | Global exclusivo superadmin | No | restaurantes, usuarios | Sí | Depende de acción; crear no |
| `auth/login.php` | POST (GET redirige) | Público | POST central¹ | Identidad global por email; sin verificar restaurante activo | Sí | logs_acceso, usuarios | Sí | No |
| `auth/logout.php` | GET | Sesión opcional (logout) | No para GET | Solo sesión actual | logs_acceso | logs_acceso | Sí | No |
| `index.php` | GET | Público | No para GET; POST central¹ | Sin consultas por restaurante | No | — | No² | Sí² |
| `roles/admin/auditoria.php` | GET | admin, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | auditoria | No² | Sí² |
| `roles/admin/caja.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | gastos, pagos, pedidos, turnos, usuarios | No² | Sí² |
| `roles/admin/config_facturacion.php` | GET/POST | admin, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | facturacion_config, restaurantes | Sí | No |
| `roles/admin/dashboard.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | facturacion_config, gastos, mesas, pagos, pedido_items, pedidos, productos, restaurantes, usuarios | No² | Sí² |
| `roles/admin/gastos.php` | GET/POST | admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | gastos, usuarios | Sí | No |
| `roles/admin/historial.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | comprobantes, mesas, pedidos, usuarios | No² | Sí² |
| `roles/admin/historial_comprobantes.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | comprobantes, usuarios | No² | Sí² |
| `roles/admin/menu_categorias.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | categorias | No² | Sí² |
| `roles/admin/menu_productos.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | categorias, productos | No² | Sí² |
| `roles/admin/mesas.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | mesas, pedido_items, pedidos, turnos | No² | Sí² |
| `roles/admin/reportes.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | gastos, pagos, pedido_items, pedidos, productos | No² | Sí² |
| `roles/admin/turno_detalle.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | gastos, pagos, pedidos, turnos, usuarios | No² | Sí² |
| `roles/admin/usuarios.php` | GET | admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | usuarios | No² | Sí² |
| `roles/admin/ver_comprobante.php` | GET | admin, encargado, superadmin, atencion | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | comprobantes, facturacion_config, mesas, pedidos, usuarios | No² | Sí² |
| `roles/atencion/cobrar.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | mesas, opciones_valor, pedido_item_opciones, pedido_items, pedidos, productos | No² | Sí² |
| `roles/atencion/comanda.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | categorias, mesas, opciones_valor, pedido_item_opciones, pedido_items, pedidos, productos | No² | Sí² |
| `roles/atencion/comprobante_view.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | comprobantes, facturacion_config, usuarios | No² | Sí² |
| `roles/atencion/dashboard.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | mesas, pedido_items, pedidos, turnos | No² | Sí² |
| `roles/atencion/gastos.php` | GET/POST | atencion, admin, encargado, superadmin | POST central¹ | Filtro restaurante y/o padre validado | No | gastos, usuarios | Sí | No |
| `roles/atencion/historial.php` | GET | atencion, admin, encargado, superadmin | No para GET; POST central¹ | Filtro restaurante y/o padre validado | No | comprobantes, gastos, mesas, pagos, pedidos, turnos, usuarios | No² | Sí² |
| `roles/cocina/dashboard.php` | GET | cocina, admin, encargado, superadmin | No para GET; POST central¹ | Sin SQL; API propia usa sesión | No | — | No² | Sí² |
| `roles/superadmin/dashboard.php` | GET | superadmin | No para GET; POST central¹ | Global exclusivo superadmin | No (solo lee logs) | logs_acceso, mesas, pedidos, restaurantes, usuarios | No² | Sí² |
| `roles/superadmin/perfil.php` | GET/POST | superadmin | POST central¹ | Global exclusivo superadmin | No | usuarios | Sí | No |
| `roles/superadmin/restaurantes.php` | GET | superadmin | No para GET; POST central¹ | Global exclusivo superadmin | No | mesas, restaurantes, usuarios | No² | Sí² |

## Lectura de los controles

Se revisaron los SELECT iniciales de propiedad y sus escrituras posteriores: no se clasifica como IDOR una sentencia por id que ya está precedida por una validación del padre en el mismo flujo. Ejemplos: get_pedido_detalle:19-45, agregar_item:25-75, guardar_comprobante:51-58, reabrir_venta:25-47. Cambios de propietario no existen en esos flujos normales. Las FK actuales no impiden relaciones cruzadas insertadas por otro escritor externo. Tablas listadas corresponden a consultas de la ruta; las rutas que llaman registrarAuditoria escriben además auditoria de forma indirecta. Login también escribe auditoria tras establecer sesión si tiene restaurante. “Sí” en auditoría no garantiza evento por cada rama ni entrega atómica; ver H-015.

## Flujo de sesión

index.php → config/db.php (crea token) → formulario POST login.php → consulta preparada por email → password_verify → activo → logs_acceso → session_regenerate_id(true) → rol/restaurante en sesión → requireRole en cada endpoint. Logout registra y destruye la sesión actual por GET. No se revalida activo/rol/estado de restaurante en cada petición, no hay revocación global ni caducidad propia. Contexto del superadmin compartido entre pestañas.

## Modelo de amenazas

| Actor | Activo | Vector comprobable / control |
|---|---|---|
| Anónimo | Cuenta, disponibilidad | Login sin límite propio; bcrypt y CSRF presentes |
| Cocina | Flujo del pedido | Cambia estados de cualquier ítem del restaurante; enum permitido, no transición ni estado del padre |
| Atención | Cobros y gastos | Descuento sin límite por rol; registrar gastos; no edita usuarios |
| Encargado | Cuenta administradora | Cambia password/rol de un admin mediante admin_usuarios; requiere sesión y token válidos |
| Admin malicioso A | Datos B / sesión global | Nombres y opciones persistidas llegan a HTML/onclick; un superadmin que las ve puede ejecutar contenido con su propia autoridad |
| Admin B | Datos A | Manipular ids se frena mediante restaurante o padre; resta comprobar en entorno aislado |
| Superadmin | Todos los restaurantes | Selección por GET y contexto compartido; error de pestaña puede dirigir creación al restaurante equivocado |
| Atacante externo | Token, código, históricos | .env en Git; publicación del repositorio y efectividad real de Apache pendientes |

## Inventario de responsabilidades

config/db.php: entorno, PDO, JSON, sesión, permisos, contexto, CSRF y auditoría. api/: 24 rutas JSON. auth/: login/logout. roles/: páginas por perfil con SQL y JS inline. includes/: HTML y navegación compartidos. assets/js/main.js: modales, polling, dinero y opciones. assets/css/: interfaz e impresión. Database/: instalador demo, migración de encargado e instalador limpio. No Composer, router ni cola presentes. El inventario exacto y los límites de cobertura están en 07_cobertura.md.
