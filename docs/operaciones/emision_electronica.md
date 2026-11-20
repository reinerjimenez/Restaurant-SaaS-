# Preparación de emisión electrónica por restaurante

Fecha: 2026-11-19. **Estado: implementado y ensayado localmente con producción desactivada por defecto. Pendiente de validación externa; no declarar listo para uso tributario cotidiano.**

MESORA añade configuración y documentos fiscales separados por restaurante, generación UBL, firma OpenSSL, transporte cURL, envío de facturas por sendBill y boletas por resumen RC con ticket y consulta posterior. Esta entrega no acredita homologación ni aceptación real SUNAT. Los comprobantes de la tabla histórica `comprobantes` se imprimen con el aviso «DOCUMENTO INTERNO — SIN VALIDEZ TRIBUTARIA».

## Instalar el esquema

Para una base nueva y vacía, `Database/008_mesora_instalacion_oficial.sql` ya incluye las tablas de emisión; no ejecutes después 011. Para una base MESORA existente instalada con la versión anterior de 008, creé el script `Database/011_emision_electronica.sql`. Ejecútalo manualmente en phpMyAdmin antes de probar este módulo. Revisa primero la base seleccionada y conserva respaldo. El desarrollo no ejecuta DDL desde PHP ni modifica automáticamente el esquema.

La reversión está en `Database/012_reversion_emision_electronica.sql`; elimina las tablas nuevas y su evidencia de ensayo. Exporta esa evidencia antes de una reversión y ejecútala solo de forma manual y autorizada. El código debe revertirse también si se retira el módulo.

Sin el esquema nuevo, el flujo normal de cobro y comprobante simple sigue disponible y la pantalla de emisión muestra la instalación pendiente.

## Configurar un restaurante para ensayo

1. Iniciar sesión como admin del restaurante o como superadmin con el restaurante seleccionado.
2. Abrir **Emisión electrónica**, disponible en el menú y desde Mi Restaurante.
3. Completar RUC, razón social, dirección, ubigeo, régimen individual, tasa IGV validada por contador y series de ensayo. Elegir **Pruebas sin envío SUNAT**. Se admite RER, RMT o General por restaurante; el régimen de un restaurante no habilita a otro.
4. Usar datos de ensayo. Se admiten descuentos y cargos desglosados, productos gravados, exonerados o inafectos, y boleta sin identificación del cliente hasta S/ 700; sobre ese monto se exige identificación. La factura exige RUC y dirección del cliente. No cargar certificados ni Clave SOL en el formulario.

En **Cobrar pedido**, las opciones boleta/factura aparecen según configuración y autorización de ese restaurante. Registran el cobro y preparan el documento en la misma transacción: si falla la preparación, el cobro se revierte. El mensaje distingue ensayo de emisión pendiente de aceptación. El servidor obtiene el ambiente desde la configuración, nunca de una selección arbitraria del cliente. El endpoint histórico sin solicitud explícita de preparación fiscal rechaza boleta/factura antes de acceder a la base o cobrar. Después de preparar, se abre la vista de estado y descarga XML, CDR y una representación PDF con QR del restaurante. El PDF muestra de forma visible si es prueba, pendiente o aceptado; no convierte un documento pendiente en aceptado.

También se puede preparar desde Emisión electrónica un documento para un pedido ya cobrado del mismo restaurante. Esta operación no vuelve a cobrar. Si el documento anterior quedó rechazado de forma definitiva, el administrador puede solicitar una reemisión explícita; conserva el XML, CDR y correlativo del intento rechazado y crea un nuevo intento. No se reemite un documento pendiente, incierto ni aceptado.

Las operaciones de reabrir o eliminar venta rechazan una venta que ya tenga evidencia en el módulo fiscal, para impedir un reverso local que deje inconsistencia con el documento. Los documentos se listan en la pantalla del restaurante, con límite de 50 filas.

## Procesamiento y configuración de producción

Solo después de resolver los pendientes siguientes, verificar CDT/series/régimen con el contador y aprobar una emisión controlada, el administrador del servidor puede configurar las variables por ID de restaurante. Ejemplo con ID ficticio 21:

```text
SUNAT_PRODUCCION_AUTORIZADA_21=1
SUNAT_21_P12_PATH=/ruta/privada/certificado.p12
SUNAT_21_P12_PASSWORD=(secreto)
SUNAT_21_USUARIO=(RUC de 11 dígitos concatenado con usuario SOL autorizado)
SUNAT_21_CLAVE=(secreto)
```

El certificado debe quedar fuera del proyecto y del directorio público. No subirlo ni copiar secretos al repositorio, formularios, mensajes o logs. La configuración de emisión debe tener modo producción para el mismo restaurante; el permiso de servidor solo no cambia un restaurante desactivado. El trabajador compara el RUC del snapshot con la configuración y el usuario SOL, y el motor comprueba la identidad del certificado al firmar.

El trabajador exige `DB_NAME` explícito y comprueba la base conectada antes de procesar. La tarea programada del servidor, que no se instaló durante el desarrollo, debe ejecutar periódicamente:

```text
php bin/enviar_emision.php --pendientes 21
```

Procesa hasta 20 documentos de ese restaurante por corrida. También admite `--enviar 21 DOCUMENTO_ID` y `--consultar 21 DOCUMENTO_ID` para control individual. El XML firmado se persiste antes de enviar, con transacciones breves: ninguna llamada SUNAT mantiene bloqueada la caja. Un error de transporte deja el documento incierto y nunca provoca reenvío automático ciego ni otro correlativo. La factura incierta consulta getStatusCdr. La boleta conserva su Invoice individual firmado y un RC firmado separado; registra ticket, consulta getStatus y solo marca aceptada al validar la CDR. Código 98 conserva pendiente; 99 sin CDR informa rechazo del resumen. Si se pierde el ticket, se requiere conciliación manual sin reenvío.

Los RC usan secuencia diaria por restaurante bajo el bloqueo del restaurante, con límite diario 99999. No se reutilizan los correlativos internos B001/F001 para declarar documentos históricos como fiscales. Las pruebas simuladas se ejecutan mediante `bin/procesar_emision.php --simular RESTAURANTE_ID DOCUMENTO_ID`, restringido a base desechable y ambiente pruebas; sus resultados están etiquetados como ficticios.

## Verificación local realizada

- `php tests/comprobantes_frontera_fiscal.php`: pasó; ejecuta la validación del endpoint con dobles, comprueba rechazo de boleta/factura fuera de ensayo antes de BD/cobro, rechazo de tipo inventado y continuidad del comprobante simple hasta la frontera de BD. No prueba una escritura real.
- `php tests/ola_1_cobros.php`: pasó con PDO simulado, para pagos, ajustes y totales.
- `php tests/emision_procesador.php`: pasó con PDO y transporte dobles, sin red ni BD: persiste antes de enviar, libera transacción antes del transporte, conserva timeout incierto, bloquea reenvío, no repite aceptados, respeta permiso de producción y procesa boleta RC por ticket con resultados 98/99. El motor construye XML real de muestra en el test; la firma y la conexión real se prueban aparte.
- Sintaxis PHP revisada en guardar_comprobante, ambas vistas de impresión, configuración, sidebar, reabrir_venta y eliminar_venta; JavaScript extraído de cobrar.php compiló con Node.
- `php tests/sunat_motor.php`, `php tests/emision_fiscal.php`, `php tests/comprobantes_frontera_fiscal.php` y `php tests/emision_pdf.php`: pasaron localmente. El PDF generado empezó con `%PDF-` y el QR produjo los datos fiscales esperados.
- `node tests/integration/validar_pdf_xss_playwright.mjs`: pasó en Chromium headless. Renderizó las seis vistas previas que usan el mismo HTML de Dompdf y generó PNG verificables en `docs/auditoria/pruebas/playwright_2026-11-20/`; RUC, QR, estructura, márgenes y textos de estado se apreciaron correctamente. El mismo recorrido inyectó payloads XSS en el formulario fiscal y en la nota ya persistida de la prueba multitenant, sin `dialog`, `pageerror` ni nodos ejecutables.
- Ensayo HTTP/BD en `mesora_sunat_test`: se instalaron 008 y 011 y datos ficticios A/B. La boleta de A registró el cobro y la preparación; su PDF se descargó con HTTP 200. La vista y descarga de A para B respondieron 404; el CSRF inválido respondió 403. Una boleta sin preparación no cobró el pedido. Un descuento no soportado devolvió error y dejó pedido, pagos y documento sin cambios.
- `php tests/emision_ajustes.php`: pasó para nota de crédito de factura tipo 07 y baja de boleta por RC condición 3, con CDR ficticias aceptada, observada y rechazada. `php tests/sunat_motor.php` pasó 45 comprobaciones, incluidos descuentos, cargos y afectaciones IGV.

Estas pruebas no equivalen a homologación ni a aceptación de SUNAT. La inspección visual se realizó con Playwright/Chromium sobre la vista previa que alimenta al PDF de Dompdf; se conserva la evidencia PNG.

## Pendientes antes de emisión real

Existe código de envío y configuración de producción, pero habilitarlos no sustituye las verificaciones tributarias y técnicas siguientes.

Faltan las pruebas con el RUC activo/habido y CDT real, homologación aplicable y aceptación externa; notas de débito, notas de crédito de boleta mediante resumen 07 y otros ajustes parciales. Cada restaurante requiere su propia validación fiscal y técnica.

Antes de activar, revisar el hosting para XML, firma OpenSSL, transporte SOAP mediante cURL, PharData para ZIP, tareas confiables y almacenamiento privado. El motor usa PharData y cURL nativos para funcionar sin extensiones SOAP ni ZIP; verificar su disponibilidad también en el hosting. No modificar el servidor ni instalar dependencias sin autorización.

El titular solicitará CDT gratuito para su restaurante RER. Cuando SUNAT lo habilite, obtener y almacenar el certificado y credenciales en un lugar privado, completar series y configuración validadas con contador, ejecutar una emisión controlada y verificar CDR y consulta externa. Hasta completar los pendientes, usar un canal fiscal habilitado como SEE-SOL y conciliar sin presentar el documento interno como comprobante fiscal.

Fuentes primarias del protocolo: [manual del programador SUNAT](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), [guías SUNAT](https://cpe.sunat.gob.pe/guias-y-manuales), [boleta electrónica](https://cpe.sunat.gob.pe/tipos_de_comprobantes/boleta) y [CDT](https://cpe.sunat.gob.pe/certificado-digital). La interoperabilidad y firma SHA256 siguen pendientes de confirmación con el ambiente SUNAT correspondiente.
