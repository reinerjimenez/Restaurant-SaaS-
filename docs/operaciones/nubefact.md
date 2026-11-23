# Facturación electrónica con NubeFacT

MESORA prepara la boleta o factura con los datos del pedido. NubeFacT genera y firma el XML, lo tramita por el canal fiscal aplicable y ofrece la representación PDF y la constancia de recepción (CDR). Que el proveedor reciba la solicitud o genere un PDF no confirma su aceptación fiscal. [Integración oficial de NubeFacT](https://www.nubefact.com/integracion).

## Preparar el entorno

1. En una instalación nueva, utiliza el instalador oficial 008 que incluye las tablas de NubeFacT. En una instalación existente con el esquema fiscal actualizado, importa manualmente `Database/021_nubefact.sql` en phpMyAdmin. `022_reversion_nubefact.sql` se reserva para una reversión sin documentos vinculados. Respalda antes de aplicar cambios de esquema.
2. Configura `MESORA_NUBEFACT_KEY` en el entorno privado de PHP con 32 bytes aleatorios codificados en base64. Conserva esa clave de forma segura para la restauración. El Token de cada conexión se guarda cifrado. El formulario solo permite reemplazarlo y conserva el guardado cuando se deja vacío.
3. Comprueba en el hosting el acceso HTTPS de PHP/cURL al destino oficial del proveedor, la disponibilidad de OpenSSL y DOM y la custodia privada de la configuración. En PHP 8.1 el acceso al proveedor usa IPv4. No habilites producción si alguna comprobación falla.

## Conectar un restaurante

1. El titular contrata **NubeFacT Online con integración API REST/JSON** para el RUC del restaurante y completa el alta que le indique el proveedor. Un servicio de solo validación OSE puede requerir un XML previamente firmado. [Soluciones de NubeFacT](https://www.nubefact.com/soluciones) · [Conceptos PSE y OSE de SUNAT](https://cpe.sunat.gob.pe/informacion_general/conceptos_generales).
2. En MESORA guarda los datos fiscales del restaurante en **Emisión electrónica**. En **Configurar NubeFacT** registra la Ruta, el Token y las series del ambiente de pruebas. Usa series distintas de las de los ensayos directos a SUNAT. Selecciona **Pruebas** y verifica una boleta y una factura con pedidos de ensayo en una instalación de prueba. El flujo registra el cobro en esa instalación y los comprobantes de ensayo carecen de validez tributaria.
3. Configura por separado la conexión de producción. Reserva en NubeFacT series exclusivas para MESORA y verifica que no estén usadas. Una vez conciliados el RUC y la cuenta del proveedor, el operador del SaaS configura `NUBEFACT_PRODUCCION_AUTORIZADA_<restaurante_id>=1` y el superadministrador aprueba la conexión con el RUC. El restaurante debe seleccionar el modo **Producción** antes de una emisión real. Cambiar el Token de producción requiere una nueva revisión.
4. En el hosting, emite una boleta y una factura de ensayo y compara el estado y los archivos contra el portal del proveedor. La primera emisión real debe corresponder a una venta autorizada. Verifica emisor, receptor, serie, número, impuestos, importe, estado fiscal y archivos antes de usar el flujo habitualmente.

## Operar desde caja

- **Cobrar → Boleta o Factura** registra el cobro y prepara una sola identidad de documento. El primer envío al proveedor se realiza después de confirmar el cobro. Si el proveedor tarda, revisa el estado en **Emisión electrónica → Ver comprobante**.
- Si el estado queda pendiente o incierto, usa **Consultar estado**. La consulta conserva el tipo, la serie y el número. No vuelvas a cobrar al cliente ni emitas un comprobante diferente antes de conciliar.
- Cuando se confirme la aceptación, descarga el PDF/XML del proveedor y la CDR cuando esté disponible. Si falta un archivo, consulta de nuevo el documento. Conserva los archivos conforme al procedimiento de respaldo del restaurante.
- MESORA compara el RUC, la serie y el número del XML descargado con el comprobante registrado. El PDF y la CDR se recuperan por los enlaces de la respuesta del proveedor. Durante el piloto revisa que la CDR corresponda a la identidad fiscal y al estado indicados en NubeFacT.
- Los ajustes fiscales se tramitan en el portal de NubeFacT. El administrador puede registrar en MESORA la referencia de ese trámite. Esa referencia no confirma su aceptación ni revierte la venta.

Bizlinks y Efact quedan fuera de esta entrega. El flujo directo a SUNAT conserva sus propios documentos y procesadores. El envío NubeFacT desde caja no depende de tareas programadas. Mientras no se confirme la compatibilidad de una automatización con el alojamiento, la recuperación de pendientes se realiza mediante la acción autenticada **Consultar estado**.
