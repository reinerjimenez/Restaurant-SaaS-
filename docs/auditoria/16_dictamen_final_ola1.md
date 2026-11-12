# Dictamen de preproducción de Ola 1

**NO APTO PARA PREPRODUCCIÓN.**

## Evidencia y hallazgos

| Revisión | Resultado |
|---|---|
| Entrega y correcciones | La entrega local cubre H-001, H-002, H-003, H-004, H-005, H-007, H-021, H-022, H-023 y H-015. Se corrigieron la guarda de 004, el número histórico de mesa y la coordinación de borrado de mesa con alta de pedido. H-006 continúa en Ola 2. Las pruebas anteriores de predicados, PDO simulado y sesión CGI tienen el alcance descrito en sus informes. [Entrega](10_entrega_ola_1.md), [aceptación y correcciones](12_aceptacion_integral_ola_1.md). |
| Migraciones | 004 y 006 son compatibles por diseño con el esquema de referencia. Faltan verificación real, permiso efectivo, ejecución y contraste posterior. 005 y 007 se revisaron por lectura. [Validación de migraciones](13_validacion_migraciones_ola1.md). |
| Integridad financiera, concurrencia y aislamiento por rol | Faltan pruebas con InnoDB, dos conexiones y sesiones reales. Queda sin demostrar la persistencia de cierres, la ausencia de doble cobro, el aislamiento de restaurantes y el rollback ante fallo de auditoría. [Matriz de pruebas](14_resultados_pruebas_mariadb_ola1.md). |
| Recuperación | No existen mediciones ni comparaciones de un respaldo restaurado. [Plan y registro](15_respaldo_restauracion_ola1.md). |
| Secretos | `.env` está ignorado y solo `.env.example` apareció entre los nombres revisados de configuración privada y claves. El escaneo de patrones frecuentes en los archivos de código/configuración inspeccionados no encontró coincidencias. Es una revisión parcial del árbol actual. Quedan el historial completo, artefactos, rotación si procede y verificación del paquete que se desplegará. |
| Errores y logs | El `php.ini` de XAMPP inspeccionado tiene `display_errors=On`, `log_errors=On` y log bajo `C:\xampp\php\logs`. Confirmar en el SAPI de destino `display_errors=Off`, logs habilitados, acceso limitado y ausencia de secretos en los registros. |
| HTTPS, sesiones y permisos | El código configura HttpOnly, SameSite y Secure condicional a HTTPS. Falta demostrar el comportamiento con el hosting, certificado y proxy efectivos, el aislamiento de sesiones y los permisos NTFS/hosting sobre configuración, directorios de sesión, archivos, logs y respaldos. Verificar 403/404 para archivos privados. Excluir `.git`, volcados, evidencia y scripts de la raíz pública. |
| Hosting | Solo se identificó el binario local MariaDB 10.4.32. Falta comprobar en el destino autorizado PHP/SAPI, módulos, versión MariaDB, InnoDB, SQL mode, permisos DDL/DML, zona horaria, reglas Apache y límites de importación. |

## Condiciones para liberar

1. Aprovisionar y verificar el aislamiento de la VM. Acreditar identidad de la instancia y esquema antes de cualquier cambio. [Paquete de entorno](../operaciones/paquete_entorno_ola1.md).
2. Obtener autorización expresa y verificar 004/006 en ensayo con el inventario y respaldo base. Resolver cualquier discrepancia antes de abrir el código nuevo.
3. Completar casos de caja, historial, concurrencia, seguridad y rol, y conciliar pagos, gastos, reversos y comprobantes. Resolver cualquier fallo crítico mediante una corrección autorizada y volver a probar el caso.
4. Demostrar restauración, medir tiempos, acordar RPO/RTO y verificar el hosting autorizado y sus diferencias respecto al ensayo.

## Despliegue y reversión

El despliegue requiere autorización independiente. En la ventana aprobada, verificar esquema y respaldo restaurable, cerrar turnos con la versión vigente, detener escrituras, aplicar 004 y 006 manualmente y comprobar el inventario antes de habilitar el código nuevo. Ejecutar los controles de humo y de conciliación con los permisos del destino. [Procedimiento de despliegue](../operaciones/despliegue_ola_1.md).

Ante un fallo, detener escrituras y preservar evidencia y el corte posterior. Si hay movimientos que dependen de Ola 1, conservar la estructura y conciliar los cambios. Recuperar primero en un destino independiente o corregir hacia adelante. La reversión 005 está limitada a una instalación completa y sin dependencias de Ola 1. La 007 elimina contadores tras volver al login anterior. Cualquier reversión en destino requiere autorización, respaldo y revisión de sus guardas. Durante este ensayo no se ejecutaron 005 ni 007. [Procedimiento de recuperación](../operaciones/backup_restore.md).
