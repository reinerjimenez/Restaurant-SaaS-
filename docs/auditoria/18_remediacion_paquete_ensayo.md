# Remediación del paquete de ensayo de Ola 1

**Seguimiento posterior:** [19_cierre_remediacion_paquete_ensayo.md](19_cierre_remediacion_paquete_ensayo.md). Se conserva el estado de este informe como registro de la iteración anterior.

**Dictamen: PAQUETE NO APROBADO.** Se aplicaron controles locales de identidad y se endurecieron rutas de consulta. Las barreras de restauración y de privilegios aún no son suficientes para aprobar el aprovisionamiento completo. Esta revisión no conectó a MariaDB, no ejecutó SQL y no aprovisionó una VM.

| Hallazgo | Estado posterior | Cambio y límite |
|---|---|---|
| P-01 · ALTO | Parcial | `aprovisionar_bases.php` reemplaza la importación directa. Comprueba manifiesto de VM, red y proceso antes de PDO, valida identidad del servidor, cuenta, versión, hash y ausencia de las dos bases antes del CREATE. Las dos sentencias son exactas. Queda por probarlo en la VM y verificar que el manifiesto fue creado independientemente con ACL privada. |
| P-02 · ALTO | Parcial | `diagnostico_esquema.sql` y `conciliacion.sql` califican las tablas del origen. `conciliacion_restore.sql` califica las del destino. Las consultas informativas de identidad aún dependen de la selección de esquema y los archivos SQL no abortan por sí solos si el esquema actual no coincide. Ejecutarlos únicamente tras comprobar identidad por un ejecutor controlado. |
| P-03 · ALTO | Pendiente | Se identificó la carrera del ejemplo de `Test-Path` y se suspendió su uso. Falta implementar un parser restrictivo del formato de volcado autorizado, reserva atómica del archivo y bloqueo técnico de destino no vacío antes de importar. No se declara segura la restauración. |
| P-04 · MEDIO | Parcial | Se añadió manifiesto privado y verificación local de UUID, OS, hostname, PID, ejecutable, listeners, adaptadores, rutas y procesos de túnel conocidos. El diagnóstico ejecuta esa verificación antes de PDO. Falta medir estos controles en una VM y comprobar mecanismos de túnel no enumerados. |
| P-05 · MEDIO | Pendiente | Las cuentas propuestas y las consultas de permisos siguen siendo informativas. Falta rechazo automático para grants globales, roles, DDL excesivo y acceso cruzado de migrador, restaurador y lectores. No se concedieron permisos. |
| P-06 · MEDIO | Pendiente | Los dos perfiles y el observador constan en el guion anterior. Falta una barrera exacta y una medición de solapamiento por cada caso. Una observación vacía no demuestra ausencia de espera. |
| P-07 · MEDIO | Parcial | Hay hojas separadas y validador local de campos, hash de SQL y vínculo con el manifiesto. La hoja 006 requiere el campo `004_verificada`. La veracidad de esa condición y la firma/aprobación del operador requieren comprobación manual y evidencia real. |
| P-08 · BAJO | Parcial | `escanear_secretos.py` revisa archivos, ocultos e historial Git opcional y solo imprime conteos. Requiere ejecutar el análisis sobre la copia final y revisar privadamente coincidencias, temporales y archivos grandes. Los patrones no detectan todo secreto posible. |

## Comprobaciones locales

Se ejecutó `php -l` en los tres PHP tocados, análisis sintáctico de los dos Python y `git diff --check`, sin errores. Los cambios en consultas SQL se inspeccionaron por texto. No se ejecutaron pruebas automatizadas de identidad errónea, grants, respaldos o autorización, ni una conexión MariaDB. Esas pruebas quedan **NO EJECUTADAS**. Las pruebas de identidad efectiva de VM, grants, concurrencia y restauración quedan **BLOQUEADAS** hasta que exista una VM aislada y se completen P-02, P-03, P-05 y P-06.

## Archivos del cambio

- Modificados: `scripts/ensayo/verificar_entorno.php`, `scripts/ensayo/diagnostico_mariadb.php`, `scripts/ensayo/crear_bases_ensayo.sql`, `tests/integration/diagnostico_esquema.sql`, `tests/integration/conciliacion.sql`, `docs/operaciones/paquete_entorno_ola1.md`, `docs/auditoria/13_validacion_migraciones_ola1.md`, `docs/auditoria/15_respaldo_restauracion_ola1.md` y `docs/auditoria/17_auditoria_paquete_ensayo.md`.
- Nuevos: `scripts/ensayo/verificar_vm.ps1`, `scripts/ensayo/aprovisionar_bases.php`, `scripts/ensayo/validar_autorizacion.py`, `scripts/ensayo/escanear_secretos.py`, `tests/integration/conciliacion_restore.sql` y este informe.

## Próximo paso manual

El operador debe registrar el manifiesto privado durante la creación de la VM, confirmar en el hipervisor que no hay conectividad externa y mantener los archivos privados fuera de Git y htdocs. Antes de cualquier CREATE o migración, resolver los hallazgos pendientes, probar las barreras con identidades y esquemas equivocados en una VM descartable y adjuntar los resultados. La autorización de 004 y 006 permanece separada y posterior a esas comprobaciones. Los scripts 005 y 007 siguen fuera del avance.
