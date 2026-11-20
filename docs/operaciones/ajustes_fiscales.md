# Notas de crédito y bajas de boleta

El ajuste fiscal tiene registro, XML, ticket y CDR propios en `emision_ajustes`; el original y el cobro permanecen intactos. Instalación manual: `Database/015_ajustes_emision_electronica.sql` sobre una base que ya tenga 011 y 013; la reversión está en 016. El instalador 008 de una base nueva ya contiene esta estructura. **No se ejecutó 015 en ninguna base en este desarrollo.**

## Flujo disponible

1. Un administrador abre el estado de una factura aceptada por SUNAT (o ficticiamente aceptada en pruebas) y prepara una nota de crédito de **anulación total, código 01** con motivo. Una factura rechazada, pendiente o incierta no admite esta acción. La nota recibe serie `FC01` y correlativo independiente por restaurante y ambiente, con referencia al original. Una segunda solicitud sobre el mismo original se bloquea.
2. Para una boleta aceptada que **no fue otorgada**, el administrador puede preparar una baja por resumen diario RC, condición 3. Antes de hacerlo debe confirmar la situación de entrega y los plazos SUNAT; la interfaz lo advierte. No se revierte el pago por esta acción.
3. Un trabajador procesa los ajustes fuera de la transacción de caja. Requiere el mismo permiso y credenciales privadas de producción que el emisor. Comando: `php bin/enviar_ajuste.php --pendientes RESTAURANTE_ID`, o `--enviar RESTAURANTE_ID AJUSTE_ID`; `--consultar` concilia una baja por ticket o una nota por `getStatusCdr`. El comando exige `DB_NAME` explícito y comprueba la base conectada. La CDR verificada decide `aceptado`, `aceptado_observado` o `rechazado`. Una incertidumbre no dispara reenvío automático.
4. `api/anular_comprobante.php` solo anula documentos internos. Si la venta tiene evidencia fiscal, bloquea la anulación local y remite al flujo de ajuste.

## Límites que siguen abiertos

- Nota de crédito de **boleta otorgada**: requiere el resumen 07 de notas vinculadas; esta variante está bloqueada hasta implementar y verificar ese resumen. La baja de boleta no debe usarse para sustituir una nota cuando la boleta ya fue entregada.
- El código 01 solo modela anulación total y conserva los importes originales. Correcciones parciales, descuentos posteriores, errores de RUC/descripción y devolución parcial requieren sus motivos y montos específicos. No se ofrecen como si estuvieran implementados.
- No se validó el XML con XSD actual ni se obtuvo una CDR real. Antes de producción faltan homologación, CDT y verificación del flujo en SUNAT.

Referencias oficiales: [guía SUNAT de nota de crédito UBL 2.1](https://cpe.sunat.gob.pe/sites/default/files/inline-files/guia%2Bxml%2Bnota%20de%20cr%C3%A9dito%2Bversion%202-1%2B1%2B0_0_0%20%282%29.pdf), [boleta y comunicación de baja](https://cpe.sunat.gob.pe/tipos_de_comprobantes/boleta), [manual del programador, getStatusCdr de notas y getStatus de resúmenes](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf).
