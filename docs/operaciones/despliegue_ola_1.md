# Preparación de despliegue de la Ola 1

Esta guía describe un procedimiento manual. La implementación local no ha desplegado código ni migrado ninguna base.

## Antes del corte

1. Confirmar la versión y el esquema del destino. El binario local es MariaDB 10.4.32 con PHP 8.2.35; la versión y permisos del servidor remoto quedan por verificar. Ensayar los scripts y el flujo completo en un destino aislado antes del corte.
2. Asegurar que la Ola 0 está presente en código y esquema (`usuarios.session_version`, comprobantes completos, configuración privada). Una base nueva usa el instalador 002; una base existente conserva sus datos y recibe solo las migraciones que le falten. No importar un instalador sobre una base con datos.
3. Registrar la versión anterior, sus migraciones y la copia recuperable. Cerrar turnos mediante la versión vigente, guardar sus informes y detener escrituras antes de exportar y migrar. No asignar un turno por fecha a filas históricas sin evidencia inequívoca.
4. Verificar el respaldo siguiendo [Respaldo y restauración](backup_restore.md). No continuar con una importación parcial, un turno abierto o discrepancias en la conciliación de ensayo.

## Aplicación manual en phpMyAdmin

- **Avance:** ejecutar `Database/004_ola_1_finanzas.sql` y `Database/006_ola_1_intentos_login.sql` en la base elegida, en ese orden. Leer sus encabezados y verificar columnas, claves y tablas luego de cada archivo. Los archivos 005 y 007 son reversión, no pasos del avance.
- El DDL de MariaDB puede confirmar de forma implícita. Una transacción envolvente no convierte el conjunto de cambios en una operación atómica. Si un paso falla, detenerse y comparar esquema contra el respaldo antes de reintentar. [Documentación de MariaDB: confirmaciones implícitas](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).
- Habilitar el código nuevo solo después de verificar el esquema. Mantener copia privada de los archivos anteriores. No incluir secretos, dumps, pruebas, informes de auditoría ni `.git` en la carpeta pública. Los usuarios con sesiones anteriores al cambio pueden necesitar autenticarse de nuevo.
- Pasar el [guion de aceptación](aceptacion_ola_1.md) y registrar los resultados antes de abrir el tráfico general. Si algún caso requiere escrituras, usar solo la instalación de ensayo.

## Reversión

- Antes de volver al código anterior, detener escrituras y exportar el estado actual. Si no existen datos nuevos dependientes de 004, ejecutar su reversión manual 005 siguiendo sus guardas. La reversión 007 quita el limitador al volver a código que no lo use; sus contadores no contienen contraseñas.
- Si ya hay movimientos o cierres del modelo nuevo, no ejecutar 005, ni volver a habilitar el código antiguo de caja. Este no interpreta los reversos ni conserva las nuevas invariantes. Preferir corregir el código hacia adelante. Si hay que recuperar un corte anterior, restaurar en otro destino y conciliar todas las operaciones posteriores con el responsable de caja antes de servir tráfico.
- Restituir archivos de la versión anterior no deshace cobros, devoluciones, gastos, anulaciones ni comprobantes. Mantener trazabilidad de cada diferencia y conservar ambas copias.

| Registro | Completar al desplegar |
|---|---|
| Fecha/hora, ambiente, responsable | Pendiente |
| Versión anterior/nueva e inventario de archivos | Pendiente |
| Respaldo, SHA-256 y ensayo de recuperación | Pendiente |
| Scripts ejecutados y verificación de esquema | Pendiente |
| Evidencia A–F, decisión y reversa | Pendiente |
