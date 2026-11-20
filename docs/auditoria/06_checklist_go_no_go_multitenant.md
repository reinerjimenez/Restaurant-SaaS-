# Prueba dinámica de aislamiento entre restaurantes

Fecha: **2026-11-20**. Veredicto: **GO**.

Se ejecutaron **35 casos con peticiones HTTP reales**, cookies, sesiones, CSRF y roles contra la base nueva mesora_aislamiento_test. Se instaló Database/008_mesora_instalacion_oficial.sql; este instalador ya incorpora los cambios de 009 y 010, por lo que no se reaplicaron. Las peticiones, cargas y respuestas completas están en [la evidencia JSON](pruebas/aislamiento_http_2026-11-19.json). El ejecutor es tests/integration/aislamiento_http.mjs.

Los servidores PHP de ensayo comprobaron DB_NAME y SELECT DATABASE() antes de servir las pruebas. La apertura simultánea de caja usó dos procesos PHP conectados a esa misma base de ensayo.

## IDs generados y utilizados

| Entidad | Restaurante A (ID 1) | Restaurante B (ID 2) |
|---|---|---|
| Usuarios iniciales | admin 2, encargado 3, atención 4, cocina 5 | admin 6, encargado 7, atención 8, cocina 9 |
| Mesas | 1, 2 | 3, 4 |
| Categoría | 1 | 2 |
| Productos | 1, 2 | 3, 4 |
| Grupos de opciones iniciales | 1, 2 | 3, 4 |
| Valores de opciones iniciales | 1, 2 | 3, 4 |
| Turno inicial | 1 | 2 |
| Pedidos usados | activo 2 | activo 1, cobrado 3 |
| Ítem y comprobante usados | ítems del pedido 2 | ítem 1, comprobante 1 |
| Turnos abiertos al terminar la última corrida | 17 | 18 |

IDs adicionales acumulados en las repeticiones: usuarios temporales de A 10–19 (desactivados); ítems de A 7, 9, 11, 13, 15, 17, 19, 21, 23, 25; ítems de B 1 y 4; turnos de A 1, 3, 6, 8, 9, 12, 14, 15, 17; turnos de B 2, 4, 5, 7, 10, 11, 13, 16, 18. El grupo y valor 5 fueron creados en A durante la primera prueba y permanecen allí. El grupo y valor iniciales 1 de A fueron reemplazados durante aquella edición previa a la corrección; la matriz de IDs iniciales conserva sus números para reconstruir la secuencia.

El superadmin inicial tiene ID 1. Los turnos 1 y 2 se cerraron al repetir las pruebas. El caso de revocación creó usuarios temporales desactivados de A. En una corrida anterior a la corrección, una petición con ID de grupo B creó en A un grupo nuevo con ID 5 y nombre de B; no se guardó una referencia cruzada. El envío de un ID de grupo ajeno ya se rechaza. Los IDs de los turnos nuevos varían si se repite el ejecutor.

## Resultados de los ocho escenarios

Las rutas se solicitaron bajo /MESORA. El JSON enlazado incluye el método, ruta, carga, estado HTTP, mensaje y resultado de cada caso.

| # | Petición de ejemplo y resultado real | Estado |
|---|---|---|
| 1. Lectura cruzada | Admin A: GET /api/get_pedido_detalle.php?id=1 → HTTP 200, success:false, «Pedido no encontrado»; GET /api/get_opciones.php?producto_id=3 → HTTP 200, success:false, «Producto no válido»; GET /roles/admin/ver_comprobante.php?id=1 y GET /roles/admin/turno_detalle.php?id=2 → HTTP 302 a sus listados. Como control, admin B abrió los cuatro recursos propios con HTTP 200 y contenido de B. | **Pasó: 4/4.** |
| 2. Escritura cruzada | Admin, atención o cocina de A enviaron IDs B a 19 POST: crear pedido con mesa 4, agregar/cobrar/cancelar pedido 1, eliminar o marcar ítem 1, editar/eliminar producto, mesa, categoría y usuario B, reabrir/eliminar venta, emitir/anular comprobante, cambiar disponibilidad y cerrar turno B. Todos respondieron success:false; por ejemplo «Mesa no encontrada» e «Ítem no encontrado en este restaurante». Se compararon antes/después de cada petición todas las filas B de restaurantes, usuarios, mesas, categorías, productos, opciones, pedidos, ítems, pagos, comprobantes y turnos. Ninguna cambió. | **Pasó: 19/19.** |
| 3. Opciones cruzadas | POST /api/agregar_item.php con pedido A 2, producto A 1, grupo B 3 y valor B 3 → HTTP 200, success:false, «Una opción ya no está disponible para este producto». POST /api/admin_productos.php con producto A 1 y grupo id 3 → HTTP 200, success:false, «Los grupos deben definirse sin identificadores ajenos». Las tablas de vínculos y grupos quedaron iguales. | **Pasó: 2/2.** |
| 4. Roles | Cocina A: POST /api/cobrar_pedido.php; encargado A: POST /api/reabrir_venta.php y POST /api/admin_usuarios.php para editar al admin A 2. Las tres respuestas fueron HTTP 403 y success:false. | **Pasó: 3/3.** |
| 5. Contexto superadmin | Tras seleccionar A y cargar /roles/admin/gastos.php, el superadmin seleccionó B y envió el formulario viejo de A mediante POST /roles/admin/gastos.php con restaurante_esperado=1. Respuesta HTTP 409: «El restaurante de esta pestaña cambió». El conteo de gastos no cambió. | **Pasó: 1/1.** |
| 6. Sesión revocada | Admin A creó un usuario temporal de atención, este inició sesión y admin A lo desactivó. El siguiente GET /api/get_menu.php de la sesión antigua devolvió HTTP 401, success:false, «La sesión ya no es válida». | **Pasó: 1/1.** |
| 7. XSS cruzado | Playwright/Chromium abrió el detalle como admin A y superadmin con A seleccionado. La carga `<img ... onerror>` se mostró literalmente; el contenedor tuvo 0 `img`, 0 `svg` y 0 atributos de evento. Tras cambiar el superadmin a B, `get_pedido_detalle?id=2` respondió `success:false`, «Pedido no encontrado». En el formulario real de preparación fiscal se inyectaron las cargas de cliente/comprobante sin crear nodos ejecutables. En toda la corrida: 0 `dialog` y 0 `pageerror`. Evidencias: [resultado](pruebas/playwright_2026-11-20/resultado_playwright.json) y PNG en `pruebas/playwright_2026-11-20/`. | **Pasó.** |
| 8. Caja concurrente | Tras cerrar los turnos por HTTP, se enviaron simultáneamente dos POST /api/abrir_turno.php por los puertos 8098 y 8099, uno con admin A y otro con admin B. Ambos devolvieron HTTP 200, success:true, «Caja abierta correctamente». La BD mostró exactamente un turno abierto por restaurante. | **Pasó: 2/2.** |

## Fallos encontrados y correcciones

- api/marcar_item_listo.php respondía éxito por un ítem B sin modificarlo. Ahora revierte y responde success:false.
- api/admin_mesas.php y api/admin_categorias.php respondían éxito al editar IDs B aunque afectaran cero filas. Ahora verifican pertenencia.
- api/admin_productos.php ignoraba IDs de grupos enviados y creaba un grupo nuevo en A. Ahora rechaza IDs de grupos en la carga.
- config/db.php revalidaba sesiones GET cada 60 segundos. Ahora revalida cada petición protegida; auth/login.php ya no guarda el marcador obsoleto.
- api/admin_usuarios.php rechazaba la edición de un admin por un encargado con HTTP 200. Ahora usa HTTP 403 y success:false.
- config/db.php ahora respeta también las variables del proceso obtenidas mediante getenv() al cargar .env.

## Incidente durante la preparación

El primer servidor PHP CLI arrancó con variables_order=GPCS. Eso dejó $_ENV vacío y loadEnv() sustituyó el DB_NAME del proceso por el valor de .env, que apunta a mesora. **Hubo un único POST de login fallido antes de detectar el desvío y detener ese servidor.** El 2026-11-20 se consultó `mesora` exclusivamente con SELECT: `login_limites` tenía dos contadores con un fallo cada uno, iniciados el 2026-11-19 a las 10:16:59 y 10:17:00. Uno corresponde a la IP local 127.0.0.1 y el otro a un identificador que no coincide con ninguna cuenta existente; ninguno tenía bloqueo vigente. No se desbloqueó ni modificó ninguna fila. No se ejecutaron allí el instalador, la semilla, los ataques ni sentencias SQL manuales de escritura. Después se corrigió la precedencia de getenv(), se arrancaron servidores con variables_order=EGPCS y una guardia que exigió mesora_aislamiento_test. La ruta /__aislamiento_db_check confirmó esa base antes de continuar.

## Decisión y pendientes

**GO (revisado el 2026-11-20).** La inspección de solo lectura de `mesora` confirmó que el incidente no mantiene ninguna cuenta bloqueada. Playwright/Chromium completó la inspección de DOM y de contexto del caso XSS sin eventos ejecutados; las evidencias PNG y JSON quedan enlazadas arriba. Esta matriz no afirma cobertura exhaustiva de todos los endpoints de la aplicación.

La base mesora_aislamiento_test **se conserva como evidencia** y puede borrarse manualmente después de revisar el informe. No se creó ni ejecutó una migración estructural nueva.
