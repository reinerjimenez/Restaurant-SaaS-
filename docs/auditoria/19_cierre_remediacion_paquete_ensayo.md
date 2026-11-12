# Cierre de la remediación del paquete de ensayo de Ola 1

**Dictamen: PAQUETE NO APROBADO.** La revisión y las pruebas locales se hicieron sin MariaDB. El paquete incorpora controles de rechazo reproducibles, pero el validador de respaldo aún no ha demostrado compatibilidad con un volcado real y la comprobación de destino vacío depende de una declaración manual. El entorno tampoco existe. El dictamen del informe 17 y la iteración del informe 18 se conservan.

| Hallazgo | Estado | Evidencia local y límite |
|---|---|---|
| P-01 | Parcial | `aprovisionar_bases.php` valida manifiesto, hash de SQL, versión, host, puerto, datadir, cuenta y ausencia de ambas bases antes de CREATE. El archivo SQL es una referencia de las dos sentencias exactas. Falta probar PID/listener y comportamiento de creación en VM. |
| P-02 | Implementado localmente, pendiente en VM | Hay consultas calificadas separadas para origen y destino. `ejecutar_lecturas.php` valida VM antes de PDO, base y servidor antes de consultar y restringe el archivo SQL. La prueba sintética aceptó los tres archivos autorizados y rechazó esquema externo y DDL. No se ejecutaron consultas reales. |
| P-03 | Implementación local, compatibilidad real pendiente | El validador comprueba tamaño, SHA-256, inventario exacto y solo `CREATE TABLE` InnoDB/`INSERT VALUES`; rechaza comentarios, otras instrucciones y referencias calificadas. La reserva usa directorio/marcador exclusivos y publicación sin reemplazo; la restauración controlada valida VM/servidor/cuenta/esquema y destino vacío por PDO bajo bloqueo, y registra eventos en JSONL. Las pruebas locales cubren el subconjunto SQL y la reserva, pero no son una exportación. No hay MariaDB de ensayo disponible: falta generar, validar y restaurar un respaldo sintético real. Ver [dictamen P-03](20_validacion_respaldo_ola1.md). |
| P-04 | Parcial | El manifiesto privado incorpora UUID, host, OS, datadir, PID, binario, versión y hashes de configuración de MariaDB y Apache. PowerShell revisa adaptadores activos, rutas predeterminadas, listeners y procesos de túnel conocidos antes de PDO. La evidencia externa del hipervisor y la efectividad en Windows VM siguen pendientes. |
| P-05 | Implementado localmente, pendiente en VM | `perfil_privilegios.php` comprueba grants permitidos por cuenta. `verificar_privilegios.php` revisa cinco cuentas desde el operador local después de validar VM y servidor. La prueba sintética rechazó privilegio global, GRANT OPTION, otra base, DDL en la cuenta web y un rol. Los grants reales no se consultaron. |
| P-06 | Preparado, pendiente de ejecución | El guion de concurrencia describe barrera, sesiones HTTP B/C, observador D, orden inverso, IDs, tiempos, espera, estado final y captura de interbloqueos para los cinco casos pedidos. La simultaneidad real no se observó. |
| P-07 | Implementado localmente, pendiente de aprobación real | `validar_autorizacion.py` exige hoja individual, hash de SQL y respaldo, VM, host, puerto, esquema, operador, fecha, ventana, preflight y aprobación. Rechaza 006 sin marca de 004 verificada. Las pruebas sintéticas rechazaron host, puerto, esquema, hash y aprobación incorrectos. La veracidad de la marca de 004 necesita evidencia real y revisión humana. |
| P-08 | Escaneo ejecutado, revisión pendiente | El escáner revisó 333 archivos actuales y 292 blobs históricos. Reportó 866 entradas excluidas, 0 errores de lectura y 34 coincidencias o posibles falsos positivos, sin imprimir valores. Los patrones sintéticos de clave privada y token se detectaron. Las coincidencias requieren revisión privada; el análisis por patrones no prueba ausencia absoluta de secretos. |

## Matriz de permisos prevista

| Cuenta | Base | Permitidos | Prohibidos relevantes |
|---|---|---|---|
| `ola1_app` | `restaurant_ola1_test` | SELECT, INSERT, UPDATE, DELETE | DDL, globales, roles y otras bases. |
| `ola1_migrador` | `restaurant_ola1_test` | SELECT, INSERT, CREATE, ALTER, INDEX | Globales, otras bases, GRANT OPTION. |
| `ola1_restaurador` | `restaurant_ola1_restore` | SELECT, INSERT, CREATE, ALTER, INDEX | Origen, globales, GRANT OPTION. |
| `ola1_origen_ro` | `restaurant_ola1_test` | SELECT | Escritura y DDL. |
| `ola1_restore_ro` | `restaurant_ola1_restore` | SELECT | Escritura y DDL. |

`REFERENCES` solo debe añadirse si una importación real autorizada demuestra que MariaDB lo exige para una FK concreta; en ese caso se documenta el permiso exacto antes de repetir la autorización.

## Pruebas ejecutadas

- `python -m unittest discover -s tests/infrastructure -p 'test_*.py' -v` ejecutó pruebas locales con SQL artesanal y archivos temporales. Cubren autorización, rechazo de SQL, opciones de tabla representativas, SHA-256/tamaño y reserva/publicación exclusiva. No ejecutan MariaDB ni simulan su exportador.
- `php tests/infrastructure/test_perfiles.php` pasó. Validó grants sintéticos, consultas de los tres archivos admitidos y rechazos de permisos, base externa y DDL.
- Las verificaciones de sintaxis de PHP/Python y `git diff --check` son controles locales. No equivalen a la validación del motor.

**Resultados reales de MariaDB, VM, permisos, concurrencia y restauración: BLOQUEADOS. P-03: NO APROBADO.** No se abrió una conexión SQL ni se ejecutaron 004, 006, 005 o 007.

## Archivos y riesgos pendientes

El cambio tocó `scripts/ensayo/`, `tests/integration/`, `tests/concurrency/README.md`, `tests/concurrency/observar_bloqueos.sql`, `docs/operaciones/paquete_entorno_ola1.md`, `docs/auditoria/13_validacion_migraciones_ola1.md`, `docs/auditoria/15_respaldo_restauracion_ola1.md` y `docs/auditoria/18_remediacion_paquete_ensayo.md`. Se añadieron `tests/infrastructure/` y este informe. El historial local de los informes 17 y 18 permanece disponible.

Antes de aprovisionar, el operador debe confirmar desde el hipervisor la VM y su aislamiento, registrar el manifiesto privado con ACL restrictiva, revisar coincidencias de secretos sin publicar valores y repetir pruebas negativas en la copia descartable. Para cerrar P-03 debe exportar una base sintética real con MariaDB 10.4, conservar el original, validar y restaurar por el proceso controlado, y completar la conciliación. Luego se requieren hojas independientes de 004 y 006, grants efectivos y resultados de pruebas de concurrencia. No usar phpMyAdmin para importar el respaldo.
