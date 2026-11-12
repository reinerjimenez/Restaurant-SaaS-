# Resultados de pruebas MariaDB/InnoDB de Ola 1

**Ejecución real BLOQUEADA.** La inspección local no encontró una instancia aislada activa. Las bases, la aplicación de ensayo y los permisos para importar y ejecutar casos con escrituras no están confirmados. No se abrieron conexiones MariaDB ni se generaron datos. El binario 10.4.32 se identificó por CLI.

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

Para cada caso ejecutado, anotar versión efectiva, esquema, corte, IDs sintéticos, tiempo inicial/final y resultado. Registrar SQLSTATE y código de error, si los hay, y quién mantuvo el bloqueo. Adjuntar consultas antes/después y evidencia de filas y totales. Usar APROBADA solo si se observó lo esperado; FALLIDA cuando difiera; BLOQUEADA si falta una dependencia; NO EJECUTADA para un caso disponible que todavía no se corrió. Saneamiento obligatorio de secretos, cookies y tokens.
