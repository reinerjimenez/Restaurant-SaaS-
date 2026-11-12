# Respaldo y restauración

**Estado:** procedimiento preparado. El ensayo de recuperación y los objetivos de negocio siguen pendientes. El desarrollador opera phpMyAdmin manualmente; el proyecto no importa SQL ni ejecuta migraciones desde PHP.

## Qué se guarda y cuándo

- Exportación SQL de estructura y datos de todas las tablas, incluidas las de auditoría, caja, reversos y operaciones. Tomar una copia antes de cada cambio de esquema, al cierre de jornada y antes de un corte de versión. Ajustar la frecuencia al volumen de ventas que el negocio puede recuperar a mano.
- Copia de la versión de aplicación que se usó al exportar, sus recursos y logotipos. Guardar `.env` de cada entorno aparte, en un gestor de secretos o en un archivo cifrado de acceso restringido. No guardar contraseñas, tokens ni datos de clientes en Git, tickets o carpetas servidas por Apache.
- Mantener copias cifradas y verificadas fuera del servidor web, con acceso limitado al responsable de recuperación. Punto de partida sujeto a aprobación: siete diarias y cuatro semanales. Validar la retención de comprobantes, documentos personales y auditoría con el responsable del negocio antes de purgar respaldos.
- Registrar fecha y hora de corte, origen, versión de PHP y MariaDB, código, migraciones presentes, tamaño y SHA-256 del archivo. Volver a verificar el hash al descargar la copia. Un hash correcto comprueba transferencia, no restauración.

## Exportar desde phpMyAdmin

1. Identificar visualmente servidor y base origen. Para un corte de despliegue, impedir escrituras y confirmar que no quedan peticiones de caja en ejecución. Cerrar turnos con la versión actual antes de la migración financiera y guardar sus cierres.
2. En **Exportar → Personalizado → SQL**, seleccionar todas las tablas, estructura y datos. Guardar archivo, codificación UTF-8 y compresión si ayuda al tamaño. Evitar instrucciones `CREATE DATABASE`/`USE` que impidan elegir el destino de ensayo; revisar el archivo localmente antes de importar.
3. Confirmar que el volcado contiene las definiciones, índices y claves foráneas; exportar disparadores, vistas o rutinas si esa instalación los usa. Obtener conteos y totales del mismo corte, no de una base que ya recibió nuevas ventas. Cifrar y registrar el respaldo.

Referencia: [exportación e importación de phpMyAdmin](https://docs.phpmyadmin.net/en/master/faq.html).

## Restauración de ensayo y conciliación

1. Preparar una base **vacía y aislada**, con cuenta de prueba sin permisos sobre producción. Deshabilitar integraciones externas, envíos y credenciales reales en la copia de aplicación. Verificar servidor/base al iniciar y justo antes de importar.
2. Anotar hora de inicio. Verificar SHA-256, abrir la base de ensayo, usar **Importar → SQL** con el respaldo, revisar todos los mensajes y registrar versión de motor. Una importación parcial no es una restauración válida; repetir en otra base vacía después de corregir el error.
3. Comparar origen de ensayo y restaurado: lista de tablas, columnas, claves, restricciones y conteo de restaurantes, usuarios, mesas, pedidos, ítems, pagos, gastos, turnos, reversos y comprobantes. Comparar también auditoría y operaciones si estaban presentes. Buscar huérfanos y relaciones entre restaurantes que no deberían existir. No agregar manualmente relaciones a registros históricos ambiguos.
4. Por restaurante y período, comparar pagos vigentes por método y cantidad, importes anulados por separado, gastos activos por fecha contable, comprobantes vigentes/anulados y totales guardados de cada cierre. Para turnos nuevos: sumar pagos brutos ligados al turno, restar reversos ligados al mismo turno y comparar con el cierre y su detalle guardado. Confirmar que gastos pertenecen al turno y no solo coinciden por fecha. Conciliar con céntimos exactos; cualquier diferencia queda abierta con los IDs y causa. Los turnos antiguos pueden carecer de un detalle verificable: comparar sus campos persistidos y los informes archivados.
5. Verificar login con cuenta de ensayo, listar dos restaurantes sin cruces y abrir un comprobante restaurado. Si se habilita una copia de trabajo aparte, el desarrollador puede recorrer el [guion de aceptación](aceptacion_ola_1.md) manualmente. Conservar intacta la copia de conciliación.
6. Anotar hora de importación, hora de conciliación y hora en que el servicio quedó utilizable. Registrar duración total y fecha del respaldo para comparar tiempo de recuperación y ventana de datos perdidos. Una prueba pasa solo si no quedan discrepancias sin explicar.

| Dato del ensayo | Valor |
|---|---|
| Responsable, fecha, entorno y versión | Pendiente |
| Respaldo y SHA-256 | Pendiente |
| Inicio / fin importación / fin conciliación | Pendiente |
| Conteos, claves, totales y diferencias | Pendiente |
| RPO (cuánto dato es aceptable perder) | Acordar con el negocio |
| RTO (cuánto puede durar la interrupción) | Acordar con el negocio |

## Incidente real

Detener escrituras, guardar una copia del estado afectado y establecer el último corte conciliado. Restaurar primero a un destino independiente y comparar las transacciones entre el corte y el incidente. Registrar ventas o devoluciones recuperadas mediante un procedimiento autorizado con sus comprobantes y actor. Nunca resolver diferencias sobrescribiendo una base con ventas posteriores ni repitiendo cobros a ciegas. Cambiar el destino de la aplicación solo después de validar integridad, permisos y operación.
