# Resumen ejecutivo — Restaurant SaaS

Fecha: 9 de noviembre de 2026. Código auditado: ca007d3. **Decisión: NO-GO para operar varios restaurantes dentro de una instancia en producción todavía.** La revisión estática examinó los 65 archivos de código/configuración inventariados y 51 rutas; las pruebas operativas siguen pendientes. Se documentaron **20 hallazgos: 1 crítico, 10 altos y 9 medios**. No se modificaron archivos de aplicación ni se conectó a la base de datos.

El sistema ya tiene controles útiles: consultas preparadas, contraseñas verificadas con bcrypt, protección CSRF central y transacciones que bloquean el pedido al cobrar. No necesita una reescritura para corregir lo urgente. Sin embargo, contenido guardado por un usuario puede interpretarse como código al abrirlo otro con más permisos. Esto puede saltar controles de negocio aunque las consultas estén filtradas por restaurante.

No se encontró un acceso directo demostrado al cambiar un id de otro restaurante en las rutas revisadas. Eso no basta para dar el alta al segundo: quedan el XSS, selección de restaurante compartida entre pestañas, permisos de edición de usuarios y revocación de sesiones. El checklist 06 define condiciones concretas para cambiar el veredicto.

## Diez prioridades del negocio

| Orden | Hallazgo | Qué puede pasar | Qué hacer primero |
|---|---|---|---|
| 1 | H-010 | Una nota/opción/nombre ejecuta código en la sesión del admin/superadmin que lo ve | Corregir render y datos dentro de onclick; comprobar todas las vistas |
| 2 | H-000 | El token compartido viaja en .env versionado y un despliegue puede sustituir configuración | Rotar token, retirar seguimiento y proteger configuración de despliegue |
| 3 | H-019 | Encargado degrada/cambia contraseña del admin del propio local | Validar rol actual del destinatario además del rol solicitado |
| 4 | H-020 | Cuenta o restaurante desactivados siguen operando con sesiones abiertas | Revalidar y revocar sesiones centralmente |
| 5 | H-018 | El instalador limpio anterior falla y deja estructura incompleta | Repararlo y ensayar importación manual en base vacía antes de usarlo |
| 6 | H-001 | Un turno cerrado cambia después de reabrir/eliminar una venta | Cierres inmutables y ajustes ligados a turno |
| 7 | H-007 | Se agregan platos después de cobrar/cancelar por carrera entre usuarios | Bloquear pedido también al agregar y validar estado bajo ese lock |
| 8 | H-021 | Reapertura aplica descuento nuevamente sobre total ya descontado | Calcular desde ítems, aplicar ajuste una vez |
| 9 | H-023 | Pagos negativos descartados por un bucle alteran otro acumulador | Validar/normalizar pagos una vez para todas las escrituras |
| 10 | H-002/H-022 | Pantallas atribuyen dinero a fechas distintas y el panel global multiplica ventas | Definir fecha de cobro y preagregar ventas antes de unir tablas |

Ejemplos demostrables leyendo el código: S/100 menos descuento S/10 se guardan como S/90; al reabrir y aplicar de nuevo S/10 se calcula S/80. Un pedido de S/100 con 2 usuarios y 3 mesas puede mostrarse como S/600 en la fila global del restaurante. Son ejemplos aritméticos de fallos, no pérdidas reales medidas en clientes.

**Advertencia sobre el archivo preparado en este chat anteriormente:** Database/002_produccion_limpia.sql no está listo para importarse. Faltan pagos_json, created_at y claves de comprobantes; contar 17 tablas no verificaba su integridad. La comprobación de contratos de esta auditoría detecta ese defecto. El informe no implica que una base ya desplegada tenga el mismo problema.

## Evaluación orientativa (0–10)

Son juicios del auditor basados en código y alcance estático, no métricas de disponibilidad ni certificación. 10 significa controles claros y evidencia operativa; valores bajos indican riesgos concretos abiertos.

| Dimensión | Nota | Evidencia que condiciona la nota |
|---|---:|---|
| Seguridad | 3 | XSS y edición de admin por encargado; bcrypt/CSRF presentes |
| Aislamiento | 4 | Filtros de tenant observados, pero autoridad global expuesta a contenido y contexto por sesión |
| Integridad del dinero | 3 | Bloqueo al cobrar, pero reapertura, cierre mutable y agregación incorrecta |
| Base de datos | 4 | DECIMAL/FK útiles; instalador incompleto y turno no relacionado a movimientos |
| Arquitectura | 6 | PHP simple y helpers aprovechables; reglas monetarias y permisos dispersos |
| Rendimiento | 5 | Carga no medida; polling continuo y consultas de fecha con índices por revisar |
| Operación | 3 | Sin evidencia de restore, revocación o eventos completos de dinero; secretos versionados |

## Ruta para mejorar sin reescribir

Ola 0: seguridad inmediata, sesiones y bloqueo del instalador defectuoso. Ola 1: ciclo de pedido/cobro, cierres estables, fechas coherentes y respaldo restaurado. Ola 2: historia del menú, índices medidos, control de cuotas, permisos/registro centralizados y comunicación fiscal. Ola 3: piloto de consolidación y alojamiento elegido por carga y recuperación exigidas. Detalle, prompts, pruebas y reversas en 05.

No hay autorización técnica para consolidar por alcanzar una fecha o por terminar de leer el código. Cambiar a Go requiere completar pruebas de dos restaurantes, todas las acciones/roles, concurrencia y restauración de 06. No se ha conectado a producción para verificarlas. Mientras tanto, mantener el aislamiento por instancia existente y corregir en todas las copias los defectos comunes.

## Alcance y mejoras incorporadas a la auditoría

Se revisaron permisos por objetivo, no solo arrays de roles; se distinguió el id sin filtro que ya tenía un padre validado; se contrastaron DDL y columnas usadas por PHP; se corrigió la hipótesis de suma incremental (la suma SQL es atómica); se separó configuración ausente en código de cookies efectivas del servidor. Las hipótesis del prompt no se asumieron como hechos. El token está versionado, pero visibilidad pública y validez actual quedaron pendientes; el origen del seguimiento visible no coincide con el commit supuesto en el prompt.

No se ejecutó SQL, no se crearon cuentas/restaurantes, no se llamó a la API de documentos y no se generaron migraciones ejecutables sin conocer esquema real y política de movimientos. Windows bloqueó PHP lint. Las mejoras de aplicación quedaron especificadas en tareas concretas, respetando la auditoría de solo lectura; **los riesgos reportados siguen sin corregirse en el código**. Diseño SQL/reversión en propuestas_sql/README.md y evidencia de cobertura en 07.

## Archivos de lectura recomendada

- 01_hallazgos.md: evidencia y escenario de cada problema, más controles que conviene conservar.
- 02_matriz_endpoints.md: 51 rutas, permisos, CSRF, pertenencia, tablas y amenazas.
- 03_base_de_datos.md: modelo, conciliación, crecimiento, migraciones/consolidación y restore.
- 04_arquitectura_y_evolucion.md: mejoras incrementales, capacidad, costos pendientes y fuentes oficiales.
- 05_plan_de_remediacion.md: tareas por ola con aceptación, prompts y reversa.
- 06_checklist_go_no_go_multitenant.md: condiciones obligatorias antes del segundo restaurante.
- 07_cobertura.md: archivos, hipótesis H-000 a H-017 y límites de validación.
- 08_mejoras_al_prompt.md: cambios para que futuras auditorías no hereden supuestos incorrectos.

No desplegar docs/auditoria al directorio público: contiene información técnica de seguridad. Las fuentes de hosting/normativa se citan con fecha en 04; precios del SaaS, planes, métricas y límites efectivos de cuenta siguen a verificar.
