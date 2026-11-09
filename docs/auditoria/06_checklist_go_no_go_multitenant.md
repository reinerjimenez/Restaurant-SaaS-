# Veredicto para el segundo restaurante

Fecha: 2026-11-09. **NO-GO para compartir instancia en producción todavía.** No se demostró un IDOR directo en las rutas revisadas; no equivale a demostrar aislamiento completo. Hay XSS almacenado en contenido administrable que un superadmin puede visualizar y ejecutar con su sesión global (`roles/admin/menu_productos.php:92,208,250,280`; `assets/js/main.js:166-170`). Esto rompe la separación de autoridad incluso con SQL filtrado. Además, el contexto cambia por GET y se comparte entre pestañas (`config/db.php:135-148`).

El encargado puede cambiar password y rol de un admin del propio restaurante (`api/admin_usuarios.php:68-99`). Este defecto es vertical dentro del restaurante; no se lo presenta como IDOR entre restaurantes. Las sesiones desactivadas siguen funcionando por falta de revalidación (`config/db.php:161-173`).

## Condiciones obligatorias de salida

- [ ] Corregir XSS en atributos, JS y DOM; verificar con textos que contengan comillas, etiquetas y apóstrofes.
- [ ] Autorizar actor × rol actual del destinatario × rol solicitado antes de cambiar un usuario.
- [ ] Revocar sesiones al desactivar usuario/restaurante, cambiar contraseña o reducir permisos.
- [ ] Seleccionar restaurante por POST con CSRF y vincular el contexto esperado a cada operación; rechazar pestañas obsoletas.
- [ ] Corregir instalador limpio: comprobantes incompleto y sin PK declarada (ver H-018).
- [ ] Resolver relaciones de caja y cierres mutables antes de consolidar dinero real.
- [ ] Rotar token expuesto y retirar .env de seguimiento; comprobar secretos del historial y visibilidad del repositorio.
- [ ] Restaurar un respaldo en un entorno aislado y reconciliar filas e importes.
- [ ] Ejecutar matriz dinámica con dos restaurantes y todos los roles; resultado documentado por ruta.
- [ ] Verificar restricciones web de .env, .git, Database, graphify-out y docs/auditoria.

## Escenarios pendientes de ejecución

Preparar los datos mediante SQL numerado en Database/, ejecutado manualmente por el desarrollador. Esta auditoría no crea ni modifica BD, incluso descartables.

| Prueba | Entrada | Resultado exigido |
|---|---|---|
| Lecturas cruzadas | A solicita ids de pedido, producto/opciones, comprobante y turno de B | Sin datos de B; no basta ocultar botones |
| Escrituras cruzadas | A envía ids de B a todos los POST operativos con token válido | Ninguna fila de B cambia; comprobar BD antes/después |
| Opciones cruzadas | Pedido y producto A, grupo/valor B | Rechazo; no insertar referencias cruzadas |
| Roles | Cocina llama cobro; encargado cambia admin/reabre venta | Rechazo del servidor en ambos casos |
| Contexto | Superadmin abre A, luego B en otra pestaña y guarda formulario antiguo de A | Rechazo por contexto obsoleto, no escribir en B |
| Sesión | Desactivar A o su usuario con sesión abierta | Siguiente petición rechazada |
| XSS | Nombre/opción de A contiene marcador HTML inerte y comillas | Se imprime texto; no se crean nodos ni manejadores |
| Caja | Dos cobros/cierre/reapertura simultáneos desde sesiones distintas | Un cobro por pedido; cierre estable y conciliado |

## Reversa operativa

Antes de consolidar: respaldar por instancia, fijar ventana sin escrituras y conservar exportaciones y aplicación anterior. Si falla aislamiento, detener altas y escrituras, aislar las instancias y restaurar el último respaldo conciliado. No mezclar automáticamente bases restauradas con ventas nuevas: registrar el corte y reconciliar manualmente. El proceso no ha sido ensayado.

## Decisión al terminar Fase 1

Se guarda y presenta este NO-GO antes de proseguir con seguridad, integridad y crecimiento. Continuar la auditoría no habilita compartir la instancia.
