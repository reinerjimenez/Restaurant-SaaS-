# Cobertura, hipótesis y límites

Fecha: 2026-11-09. Revisión base ca007d3.

**Cobertura de archivos fuente/configuración inventariados: 65/65 (100 %) revisados estáticamente.** Esto mide archivos examinados, no cobertura de ejecución, de todas las ramas ni certificación de seguridad. La revisión se enfocó en controladores, SQL, entradas/salidas dinámicas, JS, configuración, estilos táctiles e impresión. No se ensayaron navegador, servidor ni BD.

API: 24. Páginas de roles: 24. Matriz: API + roles + auth/login/logout + index = 51 rutas. Plantillas internas/config se inventarían aparte. Artefactos nuevos de auditoría no se cuentan en el denominador del código auditado.

| Archivo | Líneas | Estado | Alcance |
|---|---:|---|---|
| `.env` | 7 | REVISADO ESTÁTICAMENTE | Presencia/longitud de claves y configuración local; valores omitidos |
| `.gitignore` | 2 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `.htaccess` | 40 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `Database/000_restaurante_db.sql` | 945 | REVISADO ESTÁTICAMENTE | DDL, índices/FK, datos iniciales clasificados; sin ejecución ni hashes en informe |
| `Database/001_rol_encargado.sql` | 21 | REVISADO ESTÁTICAMENTE | DDL, índices/FK, datos iniciales clasificados; sin ejecución ni hashes en informe |
| `Database/002_produccion_limpia.sql` | 692 | REVISADO ESTÁTICAMENTE | DDL, índices/FK, datos iniciales clasificados; sin ejecución ni hashes en informe |
| `README.md` | 774 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `api/abrir_turno.php` | 51 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/admin_categorias.php` | 61 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/admin_mesas.php` | 69 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/admin_productos.php` | 155 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/admin_usuarios.php` | 127 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/agregar_item.php` | 100 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/anular_comprobante.php` | 35 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/cancelar_pedido.php` | 68 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/cerrar_turno.php` | 119 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/cobrar_pedido.php` | 135 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/consultar_documento.php` | 105 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/crear_pedido.php` | 87 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/eliminar_item.php` | 64 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/eliminar_venta.php` | 112 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/get_menu.php` | 43 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/get_mesas.php` | 28 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/get_opciones.php` | 39 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/get_pedido_detalle.php` | 53 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/get_pedidos_activos.php` | 69 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/get_reportes.php` | 70 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/guardar_comprobante.php` | 249 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/marcar_item_listo.php` | 61 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/reabrir_venta.php` | 132 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `api/superadmin_restaurantes.php` | 78 | REVISADO ESTÁTICAMENTE | Flujo completo: rol, método, CSRF, propiedad, consultas/escrituras y salida |
| `assets/css/main.css` | 2803 | REVISADO ESTÁTICAMENTE | Reglas de controles táctiles, móvil e impresión; sin render visual |
| `assets/css/print.css` | 221 | REVISADO ESTÁTICAMENTE | Reglas de controles táctiles, móvil e impresión; sin render visual |
| `assets/js/main.js` | 299 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `auth/login.php` | 76 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `auth/logout.php` | 21 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `config/db.php` | 225 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `includes/footer.php` | 20 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `includes/header.php` | 155 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `includes/sidebar.php` | 126 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `index.php` | 147 | REVISADO ESTÁTICAMENTE | Configuración/flujo compartido o documentación y dependencias |
| `roles/admin/auditoria.php` | 221 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/caja.php` | 622 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/config_facturacion.php` | 322 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/dashboard.php` | 326 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/gastos.php` | 250 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/historial.php` | 301 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/historial_comprobantes.php` | 334 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/menu_categorias.php` | 199 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/menu_productos.php` | 372 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/mesas.php` | 528 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/reportes.php` | 196 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/turno_detalle.php` | 201 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/usuarios.php` | 266 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/admin/ver_comprobante.php` | 289 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/atencion/cobrar.php` | 548 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/atencion/comanda.php` | 491 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/atencion/comprobante_view.php` | 256 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/atencion/dashboard.php` | 359 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/atencion/gastos.php` | 157 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/atencion/historial.php` | 496 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/cocina/dashboard.php` | 359 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/superadmin/dashboard.php` | 175 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/superadmin/perfil.php` | 126 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |
| `roles/superadmin/restaurantes.php` | 271 | REVISADO ESTÁTICAMENTE | Controlador, SQL, autorización, salida dinámica y JS inline; sin pruebas visuales |

## Hipótesis originales

| ID | Estado | Evidencia y límite |
|---|---|---|
| H-000 | CONFIRMADA PARCIALMENTE / PENDIENTE exposición pública | git ls-files .env y cat-file fb5d91b:.env confirman seguimiento; introducción visible 73c0219 (2026-10-08), no fb5d91b. Token presente, longitud 41; validez no comprobada. Remoto GitHub identificado; consulta web falló, no se verificó público. DB apunta a configuración local; contraseña BD vacía. .env.example ausente. |
| H-001 | CONFIRMADA | cerrar_turno:43-53; reabrir_venta:64-108. Sin turno_id y con resta incluso a turno cerrado. |
| H-002 | CONFIRMADA | dashboard:20-27, caja:30-55, reportes:21-57: fechas de apertura/cobro/registro/contable distintas. |
| H-003 | CONFIRMADA en aplicación | login:15-38 no limita; controles externos pendientes. |
| H-004 | PENDIENTE configuración efectiva | Sin política explícita en PHP; flags y expiración del hosting no inspeccionados. Hallazgo PROBABLE. |
| H-005 | CONFIRMADA | config/db.php:135-148 cambia contexto por GET y sesión compartida. |
| H-006 | CONFIRMADA | admin_productos:66-69, categorias:55, mesas:63, usuarios:120 y FK del DDL. Pérdida concreta de opciones/snapshot; algunos DELETE usuarios fallan por FK. |
| H-007 | CONFIRMADA con corrección de causa | agregar_item:25-27 sin FOR UPDATE; carrera contra cobrar. Se descarta que dos sumas SQL atómicas por sí solas pierdan incrementos. |
| H-008 | CONFIRMADA consultas/índices; PENDIENTE plan real | DATE en get_reportes:23 y otros; faltan índices compuestos candidatos. No se afirma full scan universal: algunos motores reescriben DATE. EXPLAIN pendiente. |
| H-009 | CONFIRMADA | requireRole repetido en rutas y excepciones por acción en admin_usuarios:28-34,68-88,107-109. Remediación en 04/05, sin hallazgo separado por estilo. |
| H-010 | CONFIRMADA | notas/opciones/nombres persistidos terminan en innerHTML; detalles y cocina reproducibles por lectura de código. No se ejecutó payload contra usuarios. |
| H-011 | CONFIRMADA ambigüedad; PENDIENTE evaluación legal | Títulos en ver_comprobante:140-141; no integración fiscal en el árbol. No se dictamina infracción ni régimen. |
| H-012 | CONFIRMADA generación de hits; PENDIENTE saturación real | cocina 10s, mapa 25s; proveedor documenta 50.000 hits/día; confirmar cuenta, tráfico, CPU/conexiones. |
| H-013 | DESCARTADA carga arbitraria PHP por ruta revisada; PENDIENTE handler | config_facturacion:36-74 valida MIME/tamaño y fija extensión raster, no SVG. No se demostró RCE. No hay regla de ejecución específica de logos; servidor no observado. |
| H-014 | CONFIRMADA falta de límite propio | consultar_documento:41-59 llama al proveedor por GET autorizado sin contador/caché; cuota y abuso real desconocidos. |
| H-015 | CONFIRMADA ausencia en árbol; PENDIENTE operación externa | Sin suite, registro de migraciones o runbook/restore medido; auditoría/errores parciales presentes. No declarar inexistentes copias externas que no se pueden ver. |
| H-016 | PENDIENTE validación dinámica; no IDOR directo demostrado | Rutas por id revisadas filtran restaurante o validan padre antes de operar; matriz completa en 02. El NO-GO también depende de XSS/contexto y autorización. |
| H-017 | DESCARTADA discrepancia PHP/PDO indicada | config/db.php:56,70 America/Lima y -05:00 coinciden. SQL UTC al importar no prueba desfase en ejecución. Zona de navegador y configuración desplegada pendientes. |

## Archivos generados y exclusiones explícitas

- graphify-out/: grafo/manifest/informes/cache son artefactos generados, usados para navegación y relación de archivos; no forman parte de fuente ejecutable revisada. Se consultó el grafo existente con python -m graphify query. El .exe fue bloqueado por Windows. No se reconstruyó el grafo: el alcance de la auditoría impide modificar fuera de docs/auditoria y no cambió código de aplicación.
- .git/: metadatos de estado/historial/seguimiento consultados, sin imprimir secretos. No se leyó todo el historial ni todos los objetos; limpieza histórica requiere un análisis privado específico. git diff inicial estaba limpio.
- assets/logos/*.webp y timbre.mp3: recursos binarios inventariados por presencia, excluidos de análisis de contenido/sanitización. La ruta assets/logo.png usada como fallback no aparece en el inventario actual; revisar despliegue.
- No se encontraron otros archivos de configuración de aplicación fuera de los inventariados; no se dispone de php.ini/httpd.conf/VistaPanel/producción. Archivos de skills e instrucciones del usuario no son parte de fuente del producto.
- Database/demo_actual.sql mencionado en el chat anterior y Database/produccion_database.sql citado por un commit histórico no están presentes en el árbol actual. No se inventa su contenido actual.

## Comprobaciones y evidencias guardadas

- Contrato estático SQL ejecutado: 17 tablas y un INSERT usuarios, pero comprobantes carece de pagos_json/created_at y PK; resultado no satisfactorio. El script imprime metadatos, no credenciales, y no abre conexiones.
- Intento PHP lint bloqueado por política de Windows (WinError 4551), antes de analizar los archivos. **No se aprobó la sintaxis PHP.**
- Sin ejecución HTTP/dinámica, sin dos tenants creados, sin EXPLAIN, carga ni restore. Los escenarios correspondientes son condiciones pendientes del Go, no resultados aprobados.
- Inventario y SHA256 de fuentes no secretas guardados en inventario_sha256.json para detectar cambios posteriores; .env no se digiere ni copia al informe.

## Autoevaluación de la entrega

- [x] Inventario de fuentes/configuración con estado individual y alcance definido.
- [x] Matriz de rutas con roles, CSRF, tenant, auditoría, tablas y mutación; notas de efectos globales y permisos por acción.
- [x] H-000 a H-017 tienen estado y límites, sin convertir hipótesis del prompt en hechos.
- [x] Cada hallazgo tiene ubicación, evidencia, escenario, confianza y prueba propuesta.
- [x] Fuentes/fechas para hosting y referencias oficiales; precios y límites efectivos desconocidos marcados a verificar.
- [x] Informes sin tokens, contraseñas ni hashes de cuenta completos.
- [x] NO-GO explícito, condiciones y reversa en 06.
- [x] Plan por etapas, prompts y criterios de salida en 05.
- [x] Méritos del sistema documentados en 01.
- [ ] Pruebas dinámicas multitenant, concurrencia, importación y restore: pendientes, no realizadas.
- [ ] Sintaxis PHP y controles efectivos de servidor: pendientes por acceso/entorno.
- [ ] Migraciones SQL ejecutables: no se generan por ubicación contradictoria y ausencia de esquema real/decisiones de movimientos. Diseño y reversas en propuestas_sql/README.md. Los scripts definitivos irán en Database/ durante la remediación.

La auditoría estática está entregada; la validación operativa completa queda abierta y el NO-GO permanece. La cobertura de archivos no debe usarse como autorización de despliegue.
