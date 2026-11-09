# Arquitectura, operación, capacidad y evolución

Fecha: 2026-11-09. Base ca007d3. Evaluación estática de PHP puro con usuarios simultáneos y dinero real; 1, 10, 50 y 200 restaurantes son escenarios, no cargas probadas.

## Estado actual y cambios que sí reducen riesgo

Cada página abre sesión, carga config/db.php, autoriza roles, consulta BD y genera HTML con JS inline. Las API reutilizan PDO/JSON/CSRF, pero repiten reglas de cobro y cálculos de caja. Es una estructura entendible para el tamaño actual. El problema concreto es que dos flujos cambian dinero con reglas diferentes, no que existan archivos largos.

config/db.php reúne configuración, conexión, permisos, cambio de contexto, CSRF y auditoría. Separar por responsabilidades al tocar esos controles facilita probarlos sin abrir BD; no requiere framework ni punto de entrada nuevo para arreglar los riesgos actuales. Los includes de cabecera/pie no son rutas autónomas y deben impedir acceso directo (header usa constantes/sesión definidas por el padre).

Los arrays de permisos se repiten en 26 API/páginas operativas; el encargado tiene rutas compartidas con admin pero excepciones por acción en usuarios/gastos/cancelaciones. Centralizar actor × acción × objetivo reduce el defecto H-019. No introducir herencia o un motor genérico de políticas: una función compartida con matriz explícita es suficiente.

## Incrementos compatibles ahora

| Paso | Beneficio concreto | Esfuerzo | Riesgo | Dependencia |
|---|---|---|---|---|
| Validación de pertenencia y permisos sobre objetivo en helper existente | cierra degradación de admin y revocación | M | Medio | matriz aprobada de acciones |
| Cálculo único en servidor de ítems y ajustes | corrige descuento repetido y pagos inconsistentes | M | Alto | escenarios H-021/H-023 |
| Rutina transaccional compartida de cobro | evita divergencia entre cobrar y guardar comprobante | L | Alto | contrato monetario estable y pruebas |
| Movimiento de caja con turno y reversos | cierres estables y reconciliables | L | Alto | decisión sobre pedidos pendientes, scripts manuales |
| Render DOM seguro y datos fuera de handlers | bloquea XSS conservando interfaz | M por conjunto de vistas | Medio | inventario de sinks de 01 |
| Respuestas JSON/HTTP y errores coherentes | clientes detectan fallo sin mostrar trazas | M | Bajo | conservar success/data/message |
| Idempotencia en envíos y registro de resultado | evita doble envío al perder respuesta | M | Medio | clave de operación, índice si hace falta |
| Runbook, backups y registro de despliegues | recuperación comprobable | M/L | Bajo | responsable y ensayo aislado |

Migrar después partes complejas a servicios de pedido/cobro/caja y repositorios con tenant obligatorio solo cuando se extraigan reglas que ya se duplican. Mantener vistas, URLs y contratos actuales durante cada paso. Un router o una reescritura completa no son requisitos de corrección.

## Robustez y frontend

- Errores PDO se registran en varios endpoints, pero categorías/mesas/usuarios y POST de gastos/configuración no tienen tratamiento uniforme. Entrada con tipos incorrectos puede lanzar TypeError (p. ej. array en trim/token) fuera del catch PDO. Validar estructura y límites antes de utilizar campos.
- jsonResponse devuelve HTTP 200 por defecto incluso para denegaciones/errores (`config/db.php:88-96,161-175`). Adoptar códigos 400/401/403/409/500 preservando el JSON estándar; getDB no incluye data en su error (`74-78`). No introducir cambios incompatibles sin actualizar consumidores.
- Los botones principales y controles tienen 48 px (`assets/css/main.css:582,718`), pero btn-sm tiene 38, varios controles de grupos 32 y borrar ítem 28 (`main.css:596,1401`; menu_productos:252). El mínimo de 44 px del proyecto no se cumple en todos. Ampliar área táctil manteniendo apariencia.
- El header limita zoom con maximum-scale=1.0 (`includes/header.php:56`); modales no gestionan foco/Escape/ARIA como diálogo (`assets/js/main.js:49-73`). Mejorar accesibilidad al corregir esos componentes; no se hizo prueba visual ni lector de pantalla.
- Comanda conserva nuevosItems solo en memoria (`roles/atencion/comanda.php:267,306,462-486`). Una recarga/navegación pierde lo no enviado; una respuesta perdida después del commit puede provocar duplicación si el usuario reintenta. Borrador local por usuario/restaurante/pedido y confirmación de recepción con idempotencia son mejoras concretas. No guardar datos personales en almacenamiento local sin necesidad.
- `roles/admin/historial.php:276` redirige tras reabrir a roles/atencion/index.php, ruta ausente en inventario; corregir a una ruta real al implementar H-021. Frontend permite confirmar diferencia > S/0,10 mientras backend rechaza > S/0,01 (`cobrar.php:435`; cobrar_pedido:71): unificar validación y mensaje, no aceptar pérdidas por diferencia arbitraria.
- Logos validan MIME real/tamaño/extensión, sin SVG; nombre ligado a restaurante. No hay .htaccess específico de logos. No se demostró carga ejecutable; confirmar handler del servidor y negar ejecución como defensa adicional. Opcionalmente redecodificar imagen para eliminar contenido extra, si librería existente lo permite. Limpiar logos antiguos mediante proceso explícito con referencia comprobada.
- Google Fonts requiere red; Font Awesome está fijado a 6.5.1 con SRI (`includes/header.php:60-64`). CSS/JS propios usan filemtime; login usa time() y evita caché de CSS (`index.php:37`). No se comprobó disponibilidad sin internet. Subir recursos esenciales a assets evita depender de CDN; no requiere nueva dependencia.
- CSP incluye unsafe-inline, .htaccess usa Order/Deny heredados y no bloquea explícitamente .git, graphify-out ni docs/auditoria. Options -Indexes no impide descargar un nombre conocido. Probar acceso desplegado y reglas compatibles con el servidor, sin publicar informes de seguridad en web. No se ha demostrado que .env/sql se descarguen: sí existen reglas para bloquear extensiones (`.htaccess:9-25`).

## Observabilidad y pruebas mínimas

Ahora: eventos de caja/gastos/ajustes con actor, restaurante, ids y valores antes/después; id de operación que también aparece en errores; log privado sin contraseñas/token/DNI completo. Evitar auditoría post-commit que puede perderse sin aviso en cambios monetarios. Separar métricas por restaurante y cuenta de hosting. El admin de restaurante no tiene una ruta para borrar auditoría; un operador con acceso a BD sí podría hacerlo. La auditoría no es un registro inalterable frente a un DBA.

Pruebas prioritarias: matriz actor/objetivo, bloqueo de ids de otro restaurante, sumas en céntimos, dos sesiones cobrando, agregar contra cobrar/cancelar, cierres y reversos, reapertura con ajustes, comprobante y snapshot, restauración. Pueden ser scripts PHP CLI y peticiones HTTP a una copia local sin Composer. Datos de ensayo se preparan manualmente mediante scripts Database/; no conectar a datos reales. Separar casos de dominio puros de integraciones con BD. La comprobación estática ejecutada durante esta auditoría está en pruebas/; PHP lint quedó bloqueado por Windows.

## Capacidad de polling y límite del proveedor

Fuente primaria consultada el 2026-11-09: [InfinityFree, explicación del límite de hits](https://forum.infinityfree.com/t/what-does-the-hits-limit-mean/49330/1/). Documenta 50.000 solicitudes por día y suspensión de 24 horas al excederlas; incluye archivos y consultas en segundo plano. No equivale a visitantes. El panel y plan de esta cuenta deben confirmar el límite efectivo, reinicio y uso actual. No se conocieron CPU, memoria ni conexiones máximas contratadas; no se inventan.

Código real: cocina 10 segundos (`roles/cocina/dashboard.php:318-323`), mapa atención 25 segundos (`roles/atencion/dashboard.php:341-355`). Supuesto: 1 cocina + 2 mapas abiertos 12 horas/día. Tasa = 60/10 + 2×60/25 = **10,8 peticiones/minuto/restaurante**, unas **7.776/día**. No incluye carga inicial, CSS/imágenes, apertura de páginas, pedidos, cobros ni consultas a documentos.

| Restaurantes en la misma cuenta | Peticiones/min | Polling en 12 h/día | Relación con 50.000 |
|---|---:|---:|---|
| 1 | 10,8 | 7.776 | margen por medir |
| 10 | 108 | 77.760 | excede solo con polling |
| 50 | 540 | 388.800 | excede |
| 200 | 2.160 | 1.555.200 | excede |

Con estos supuestos 6 consumen 46.656 solicitudes sin operaciones; 7 ya superan 50.000. No es una promesa de que seis funcionen: CPU/conexiones y tráfico restante pueden limitar antes. Si las pestañas quedan 24 h, estas cifras se duplican. El helper usa setInterval sin pausa, backoff ni control de petición en vuelo; latencias de proveedor pueden acumular requests y retener locks de sesión. Usar un ciclo que espere finalización y sleep entre lecturas, liberar sesión después de leer contexto cuando sea seguro y medir concurrencia. No requiere WebSockets.

## Costos y punto de cambio

No hay precio comercial del SaaS ni facturas/contrato de hosting en el repositorio; no se puede afirmar una rentabilidad ni presupuesto mensual real. Modelo para decidir: ingreso mensual = N × tarifa; coste = hosting + copias externas + dominio + horas de soporte × coste/hora + servicios de documentos. Margen = ingreso - coste. Si se acuerda destinar una fracción b de ingresos a infraestructura, presupuesto mensual máximo = N × tarifa × b. b es una decisión del negocio, no un hecho medido.

Ahora: captar métricas de una semana y reservar margen, por ejemplo investigar alternativa cuando el consumo previsto alcance 50 % de cuota o p95 exceda el objetivo que acuerde el negocio. Es un disparador preventivo propuesto, no un límite técnico. Cotizar hosting PHP+MySQL con copias, SSL, logs y capacidad documentados antes de comprometer el piloto. Precios vigentes de planes, moneda y tarifa del producto quedan a verificar; este informe no recomienda comprar un proveedor concreto.

Evolución: hosting con cron/backups verificables cuando el RPO exigido no se cubra manualmente; workers/colas cuando trabajos externos superen la latencia de caja; caché por tenant si medición muestra consultas repetidas costosas; SSE/WebSockets cuando polling medido domine coste/latencia y el servidor soporte procesos duraderos. Contenedores para homogeneizar entorno y despliegues si se opera infraestructura propia, no para corregir cuentas/ventas. Adoptar framework solo si hay varios desarrolladores, repetición transversal y presupuesto para una migración probada; PHP puro sigue siendo viable para remediar todo lo urgente.

Ante caída: mostrar estado de conexión y última actualización, evitar confirmaciones falsas y reintentos monetarios ciegos. Acordar procedimiento manual de comanda/cobro y reconciliación posterior con numeración separada; no inventar validez tributaria de documentos de contingencia. RTO/RPO y un responsable de incidentes deben quedar en contrato operativo.

## Privacidad y documentos fiscales

Se almacenan nombres/email de empleados, IPs, DNI/RUC, nombres/direcciones de clientes y snapshots de comprobantes (`Database/000_restaurante_db.sql:80-106,164-170,585-594`). No se encontró política de retención/acceso o runbook de derechos del titular en el árbol. Revisar finalidad, minimización, acceso por rol, contratos con hosting/API, exportaciones privadas y procedimiento de solicitudes/incidentes con asesor competente. No se emite dictamen ni se afirma incumplimiento legal por la sola ausencia de una página.

Fuente primaria consultada 2026-11-09: [ANPD, DS 016-2024-JUS, Reglamento de la Ley 29733](https://www.gob.pe/institucion/anpd/normas-legales/6554453-16-2024-jus). El marco exige evaluación de tratamiento de datos según el caso; la aplicabilidad y plazos concretos requieren revisión profesional.

La API actual genera documentos internos; consultar DNI/RUC no los convierte en emisión fiscal. La [descripción oficial de SEE-SOL de SUNAT](https://cpe.sunat.gob.pe/sistema_emision/see_sol), consultada 2026-11-09, describe su canal de emisión. La ausencia de integración en el repositorio y los títulos boleta/factura justifican aclarar el producto (H-011), sin concluir sobre la situación tributaria de cada negocio ni asumir IGV universal del 18 %.
