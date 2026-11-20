# Plan de emisión electrónica SUNAT para MESORA

Fecha de investigación: 2026-11-19. **Implementación local parcial verificada con base de ensayo; no se ha emitido ni enviado ningún comprobante real.**

## Estado de implementación

Se implementaron configuración y activación por restaurante, solicitudes idempotentes separadas del cobro, preparación fiscal dentro de la transacción de caja, XML UBL y firma con OpenSSL, ZIP, transporte y consulta SUNAT preparados, resumen de boletas con ticket, historial y descarga privada de XML/CDR/PDF con QR. Los ensayos locales distinguen expresamente la respuesta simulada de una aceptación real. La migración 011 se ejecutó solo en la base desechable autorizada `mesora_sunat_test`; la reversión 012 queda preparada. No se modificó la base de producción.

La guía operativa vigente es [docs/operaciones/emision_electronica.md](../operaciones/emision_electronica.md). Se cerraron localmente descuentos, cargos, afectaciones por ítem, boleta sin identificación bajo el umbral documentado, reemisión explícita de rechazados, nota de crédito total de factura y baja de boleta por resumen. Sigue pendiente la nota de crédito de boleta por resumen 07, notas de débito y ajustes parciales, revisión visual del PDF, cierre del NO-GO multitenant y homologación/aceptación con SUNAT y un CDT habilitado. Se realizaron pruebas HTTP/BD en la base desechable. Por esos pendientes, **no se declara listo para activación fiscal ni se garantiza que bastará configurar el RUC sin más desarrollo**. La sección siguiente conserva el objetivo y las fases para cerrar esa diferencia.

## Objetivo principal

Quien cobre una venta en MESORA debe poder entregar una boleta o factura con validez tributaria y comprobar su estado real, sin confundir el comprobante interno con uno aceptado por SUNAT. **El código, la migración, las pantallas y las pruebas simuladas deben quedar terminados antes de la vigencia del RUC**, para que la activación posterior consista en configurar datos reales y superar la verificación externa, sin una fase de desarrollo prevista para ese momento.

## Decisión de producto

**Decisión del titular: emisión automática desde MESORA y sin cuota de proveedor, preparada para todos los restaurantes.** La ruta principal es **SEE del contribuyente**, con certificado digital, XML firmado y servicios web oficiales. El RUC del restaurante del titular aún no está vigente; su régimen será **Régimen Especial (RER)** y declaró Clave SOL activa. **Aún no dispone de certificado y solicitará el CDT gratuito**, sujeto a los requisitos de SUNAT. Los demás restaurantes pueden tener regímenes y obligaciones distintos; cada uno debe configurarse y habilitarse por separado. Esta ruta evita una cuota de PSE/OSE, pero requiere desarrollo, mantenimiento e infraestructura. **SEE-SOL manual queda como contingencia**, porque no cumple el requisito de emisión automática.

**Criterio de «preparado»:** antes de la vigencia del RUC deben estar implementados y ensayados el flujo de caja, XML, firma con certificado de ensayo, transporte, estados, reintentos, conciliación, anulación fiscal, almacenamiento privado, interfaz y reversión. La emisión real permanecerá desactivada **por restaurante**, sin habilitación global implícita. Tras la vigencia faltarán inevitablemente la obtención/carga segura del CDT, habilitación ante SUNAT, series, credenciales y una prueba real de aceptación **para cada restaurante que vaya a emitir**. No se puede garantizar ausencia absoluta de ajustes hasta recibir una CDR real de SUNAT.

| Alternativa | Emisión real | Integración directa con PHP | Costo de servicio publicado | Consecuencia |
|---|---|---|---|---|
| SEE-SOL | Sí, en SUNAT | No se encontró API oficial de emisión SEE-SOL | Gratuito | Requiere transcripción/conciliación manual. |
| SEE Facturador SUNAT (SFS) | Sí | Aplicación por archivos; no API REST para hosting PHP | Programa SUNAT; verificar infraestructura | Requiere instalación, certificado y operación fuera del hosting compartido. |
| SEE del contribuyente | Sí | Servicios web SOAP oficiales, XML UBL y firma | Sin cuota de proveedor indicada por SUNAT; puede existir CDT gratuito si se cumplen requisitos | Desarrollo y soporte técnico altos. |
| PSE/OSE comercial | Sí, según contrato y modalidad | API solo si el proveedor la documenta y habilita | No asumir plan gratuito permanente. NubeFact anuncia integración desde S/ 70 al mes y pago anual S/ 700 | Menor trabajo técnico inicial, cuota recurrente. |

**Distinción:** un PSE puede generar y firmar en nombre del emisor, previa autorización en SOL; un OSE comprueba documentos. No se debe tratar a un OSE cualquiera como API que construye, firma y entrega PDF/XML. Un PDF por sí solo no acredita aceptación tributaria.

## Fase 0. Descubrir documentación y contratos vigentes

**Cómo sirve al objetivo principal:** evita implementar un protocolo o una regla tributaria supuesta que produzca documentos inválidos.

- Confirmar en la documentación SUNAT vigente la modalidad elegida y su especificación técnica actual. Fuentes permitidas iniciales: [SEE-SOL](https://cpe.sunat.gob.pe/sistema_emision/see_sol), [SEE del contribuyente](https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente), [SFS](https://cpe.sunat.gob.pe/sistema_emision/facturador_sunat), [manual del programador](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), [boleta y resumen](https://cpe.sunat.gob.pe/tipos_de_comprobantes/boleta), [CDT](https://cpe.sunat.gob.pe/certificado-digital) y [PSE/OSE](https://cpe.sunat.gob.pe/informacion_general/conceptos_generales).
- Lista de APIs permitidas **solo para la rama SEE del contribuyente**, según el manual oficial consultado: billService.sendBill para XML ZIP y CDR ZIP, sendSummary para resumen/comunicación de baja y ticket, getStatus para consultar ticket. El manual también describe sendPack y getStatusCdr; no usarlos sin necesidad y sin verificar versión vigente, modalidad y contrato exacto.
- Si se elige proveedor, obtener sus manuales actuales, sandbox, contrato de tarifa, esquema JSON y respuestas antes de definir cliente PHP. El [precio publicado de NubeFact](https://www.nubefact.com/precios) muestra API REST desde S/ 70/mes y S/ 700 anual, no un plan permanente de emisión API gratuita. No inventar endpoints ni asumir que «prueba gratis» equivale a uso gratuito indefinido.
- Verificación: acta con canal elegido, reglas actuales, contrato técnico y ejemplos oficiales de aceptación, rechazo, consulta y anulación. Guardia: no codificar a partir de ejemplos antiguos sin cotejo vigente.

## Fase 1. Confirmar condiciones tributarias y operativas

**Cómo sirve al objetivo principal:** determina qué documentos puede emitir el RUC y qué datos y cálculos deben ir en ellos.

- Documentar ahora el **RER del restaurante del titular** y validar con su contador la afectación IGV de los productos, moneda, tipos de cliente y series fiscales previstas. Modelar régimen y configuración fiscal **por restaurante**, sin heredar RER a otros. Dejar como **verificaciones de activación por restaurante**, sin bloquear el desarrollo previo, el RUC activo/habido, condición de emisor electrónico, CDT emitido y fecha efectiva. No solicitar ni guardar Clave SOL en el repositorio ni en este plan.
- El botón de emisión automática ya está decidido. Validar ahora el hosting para OpenSSL, XML, creación de ZIP, transporte SOAP o cURL, almacenamiento privado, conexiones salientes y tareas fiables. En el PHP CLI local se comprobó PHP 8.2.35 con cURL/DOM/OpenSSL, **sin extensiones SOAP ni ZIP cargadas**; el diseño deberá resolver y probar ambas necesidades antes de declarar listo el sistema. Si el hosting no permite el procesamiento, definir un ejecutor externo antes de la vigencia del RUC. No cambiar configuración del servidor ni instalar dependencias sin autorización.
- Resolver el **NO-GO multitenant** de docs/auditoria/06_checklist_go_no_go_multitenant.md antes de activar emisión real para cualquier restaurante en la instancia compartida. Cada RUC, régimen, credencial, certificado, serie, XML, ticket, CDR, PDF y respuesta debe quedar aislado por restaurante. Probar intentos cruzados de lectura, descarga, emisión, reintento y anulación con usuarios A y B.
- Verificación: ficha de requisitos aprobada por negocio/contador y comprobación de entorno local. Guardia: no dar por universal el IGV 18 % ni que todo RUC pueda emitir factura.

## Fase 2. Corregir la frontera entre cobro y emisión fiscal

**Cómo sirve al objetivo principal:** impide entregar como tributario un documento que solo existe en la base local.

- Partir de docs/auditoria/01_hallazgos.md:289-301 (H-011) y api/guardar_comprobante.php:6-122. Ese endpoint admite factura/boleta/simple, asigna B001/F001/T001, calcula IGV fijo, cobra y registra todo en una transacción, y responde «emitido» sin SUNAT. api/anular_comprobante.php:1-39 solo anula localmente.
- Etiquetar explícitamente vistas e impresiones actuales como documentos internos hasta que exista confirmación fiscal. Revisar roles/atencion/comprobante_view.php:112-118, roles/admin/ver_comprobante.php:136-140 y roles/admin/config_facturacion.php:220-285. Validar el tipo también en servidor, pues ocultar botones no bloquea api/guardar_comprobante.php.
- Separar registro de cobro y solicitud fiscal con estados claros (interno, pendiente de emisión, enviado, aceptado, aceptado con observación, rechazado, anulación pendiente/anulado según canal). Diseñar transición e idempotencia antes de cambiar código. No hacer una llamada remota durante la transacción que bloquea restaurante/pedido/caja.
- Preparar una migración numerada en Database para configuración fiscal y estado **por restaurante**, clave única por restaurante/tipo/serie/número, respuesta CDR/ticket y referencias a XML/PDF almacenados fuera del árbol público; incluir reversión y ensayo en una base desechable. **No ejecutar SQL en la base del restaurante** sin autorización expresa y específica. Instalar la migración aprobada antes de la vigencia del RUC para que no quede trabajo de esquema en el día de activación. Evitar renumerar silenciosamente los B001/F001 internos: los números fiscales deben salir de una serie fiscal confirmada, sin conflicto con documentos previos.
- Verificación: prueba de que los documentos internos no se anuncian como fiscales y de que fallo remoto no revierte ni duplica el cobro. Guardia: no convertir un PDF local ni una respuesta HTTP 200 del proveedor en «aceptado».

## Fase 3A. Emisión automática sin proveedor: SEE del contribuyente

**Cómo sirve al objetivo principal:** permite que MESORA inicie y siga la emisión desde su propia interfaz sin cuota por documento de un PSE, si se acepta el esfuerzo técnico.

- Implementar ahora la interfaz de certificado, firma y credenciales usando **material de ensayo**, sin RUC ni CDT real. Preparar configuración para solicitar el CDT gratuito si cumple los [requisitos SUNAT](https://cpe.sunat.gob.pe/certificado-digital), cargarlo de forma segura y verificar su vigencia cuando esté disponible. Obtener la calidad de emisor y credenciales técnicas antes de habilitar el envío real. Proteger certificado y secretos fuera del repositorio y del directorio web.
- Construir XML UBL conforme a la especificación vigente; firmarlo; enviarlo en ZIP mediante los métodos del manual oficial; persistir XML firmado, ticket, CDR y código/mensaje. La aceptación o aceptación con observación tiene validez; el rechazo no la tiene según [SUNAT](https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente). Para boletas, confirmar si aplica envío individual o resumen diario en la modalidad elegida; no universalizar un único flujo.
- Usar una cola transaccional e idempotencia por venta/serie/número; procesar con un trabajador fuera de la transacción de caja. Ante timeout, consultar estado antes de reintentar; nunca generar automáticamente otro número para el mismo cobro. Implementar notas, baja y contingencia conforme a documentación vigente, con conciliación diaria.
- Verificación **antes de vigencia**: pruebas locales con XML y certificado de ensayo, respuestas SUNAT simuladas para aceptada/rechazada/observada/timeout/duplicado, PDF/XML/CDR accesibles solo al restaurante correcto y conciliación entre ventas y documentos. Si SUNAT permite homologación anticipada para la cuenta concreta, ejecutarla; si no, dejar su guion preparado. Guardia: no publicar certificado ni registrar payload sensible en logs.

## Fase 3B. Contingencia de emisión manual: SEE-SOL

**Cómo sirve al objetivo principal:** mantiene una vía de emisión tributaria sin cuota si falla temporalmente la integración automática, sin atribuir a MESORA una emisión que hizo el portal.

- Preparar datos de la venta e instrucciones hacia el portal oficial. Registrar después tipo, serie, número, fecha, total, documento del cliente y archivo emitido en SUNAT; la numeración SEE-SOL la asigna SUNAT. Mantener el estado de MESORA como pendiente hasta verificar el documento.
- Conciliar por pedido/cobro, evitar duplicados y usar el procedimiento fiscal correcto para notas o bajas. Nunca sustituirlo por anulado=1 local ni automatizar el portal mediante scraping.
- Verificación: el documento se consulta en SUNAT y coincide exactamente con el cobro. Guardia: no almacenar Clave SOL para simular una API ni presentar el documento interno como fiscal.

## Fase final. Ensayo previo y activación posterior

**Cómo sirve al objetivo principal:** demuestra que cada documento entregado corresponde al canal fiscal real y que un fallo no oculta una deuda de emisión.

- Comparar cada implementación con la documentación reunida en Fase 0. Revisar todas las rutas de cobro, impresión, historial, anulación, permisos, CSRF y aislamiento multitenant; comprobar que no queden textos «emitido» para estados internos o pendientes.
- **Antes de la vigencia del RUC:** ejecutar pruebas locales con base de ensayo, servicio simulado y certificado de ensayo; ensayar actualización/rollback de la migración, permisos, fallas de red, reintentos y conciliación. Preparar un runbook de activación de una página con los campos exactos que faltarán y un modo desactivado seguro. Conservar reversión de código y esquema.
- **Cuando esté vigente cada RUC:** verificar condición activa/habida y régimen con el contador correspondiente; obtener y cargar el CDT fuera del directorio público; registrar series y credenciales **de ese restaurante**; habilitar solo su emisor y ejecutar una emisión real controlada. Confirmar CDR, consulta posterior, XML/PDF, conciliación y aislamiento respecto de otro restaurante antes de permitir emisión cotidiana. Si falla, mantener desactivada la emisión de ese restaurante y usar su flujo de contingencia, sin mostrar éxito fiscal.
- La ejecución de la migración en la base del restaurante y la activación real requieren autorizaciones expresas en su momento. No desplegar con el NO-GO multitenant sin resolver.

## Datos pendientes para configurar y activar

1. Confirmar cuando esté vigente el RUC del titular: condición activa/habida, RER y reglas de boleta, factura e IGV con su contador. La Clave SOL activa ya fue declarada. Para otros restaurantes, recopilar régimen y datos tributarios individualmente antes de activarles la emisión.
2. Confirmación de SUNAT de que el RUC puede obtener el CDT gratuito, y certificado emitido y vigente antes de activar la firma real. La decisión de solicitarlo ya está tomada.
3. Capacidades reales del hosting y resolución del NO-GO multitenant; el alcance de producto ya quedó definido para todos los restaurantes, con activación individual.
