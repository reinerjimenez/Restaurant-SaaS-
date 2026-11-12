# Paquete de ensayo de Ola 1

## Revisión de la guía

La [guía de preparación](preparacion_entorno_ola1.md) define el aislamiento y el flujo manual. Quedaban por fijar los recursos de VM, la identidad que debe comprobar cada herramienta, los permisos por cuenta, un formato verificable para el `.env` y la evidencia que habilita la siguiente fase. Este paquete completa esos puntos. El binario local es MariaDB **10.4.32** con PHP **8.2.35**. La inspección de procesos y sockets locales no mostró una instancia activa en 3306. Todavía se desconoce la configuración efectiva de la VM y del hosting.

## VM y aprovisionamiento manual

1. Usar una VM desechable Windows 11 x64 compatible con el XAMPP de referencia, 2 vCPU, 8 GB de RAM y disco dinámico de 80 GB con espacio adicional en el host para puntos de control y volcados. Son valores de partida para Apache, MariaDB y dos navegadores. Los mínimos de Windows 11 son 2 vCPU, 4 GB y 64 GB. En Hyper-V, usar generación 2 con Secure Boot y vTPM. Utilizar el instalador local autorizado. [Requisitos de Windows 11](https://learn.microsoft.com/en-us/windows/whats-new/windows-11-requirements).
2. Copiar desde un medio local solo el código, la documentación y los scripts necesarios. Excluir `.env`, datos de XAMPP, copias SQL con datos, logs, secretos y carpetas de Git. Guardar código en `C:\xampp\htdocs\system-restaurant-ola1`, conservar `.htaccess` y colocar verificadores, SQL y evidencias fuera de htdocs. Usar un `datadir` propio y comprobarlo con SQL. Configurar MariaDB `bind-address=127.0.0.1`, puerto 3306, y Apache HTTP/HTTPS en loopback. Configurar phpMyAdmin en loopback con un solo servidor TCP local, `auth_type=cookie`, `AllowNoPassword=false`, `AllowArbitraryServer=false` y una cuenta administradora local con contraseña. Crear un certificado TLS de ensayo confiable en la VM con el SAN del host usado.
3. Desconectar o quitar el adaptador virtual desde el hipervisor. Revisar que no haya NAT, puente, reenvíos, VPN, proxy, túneles o carpetas con datos reales. En la VM, guardar salidas de `Get-NetAdapter`, `route print` y `Get-NetTCPConnection -State Listen` filtrada a MariaDB y Apache. Las rutas IPv4/IPv6 y listeners deben corroborar que los servicios están confinados a loopback. Verificar propietarios por PID. La prueba de aislamiento se hace con configuración y permisos locales. No sondear direcciones de producción.
4. Iniciar los servicios **solo en la VM**. El operador registra en `C:/ensayo_privado/manifiesto_vm.json` los valores observados durante el aprovisionamiento: `vm_uuid`, `hostname`, `os_caption`, `mariadb_pid`, `mariadb_exe`, `datadir` y `mariadb_version`. Restringir la ACL de ese archivo. El operador verifica que los adaptadores estén deshabilitados, que no existan rutas predeterminadas ni túneles y que los listeners estén en loopback. Registrar la autorización privada `autorizar_bases.json` con `vm_uuid`, `aprobado: true` y `sql_sha256` del archivo de bases. Ejecutar manualmente `php scripts/ensayo/aprovisionar_bases.php --crear-bases` dentro de la VM. El ejecutor comprueba la VM antes de abrir PDO y valida identidad, versión, cuenta y ausencia de ambas bases antes de ejecutar las dos sentencias exactas. No importar [crear_bases_ensayo.sql](../../scripts/ensayo/crear_bases_ensayo.sql) directamente en phpMyAdmin. Las dos sentencias confirman por separado y, si la segunda falla, hay que detenerse y conciliar el estado.
5. Crear manualmente las cuentas locales en phpMyAdmin. El siguiente perfil asume importaciones SQL sin `DROP`, `LOCK TABLES`, rutinas, vistas, eventos ni triggers. Ajustar los permisos del operador solo si un objeto legítimo de ensayo lo exige. Ninguna cuenta de aplicación tiene privilegios globales, roles, `GRANT OPTION` ni `PROCESS`.

| Cuenta | Alcance | Privilegios |
|---|---|---|
| `ola1_app` | Solo `restaurant_ola1_test` | `SELECT, INSERT, UPDATE, DELETE`. |
| `ola1_migrador` | Solo `restaurant_ola1_test` | `SELECT, CREATE, ALTER, INDEX, INSERT` para 002/004/006. El administrador local cambia el marcador del superadmin o concede temporalmente `UPDATE` sobre las columnas necesarias. |
| `ola1_restaurador` | Solo `restaurant_ola1_restore` | `SELECT, CREATE, ALTER, INDEX, INSERT` para importar el volcado. |
| `ola1_origen_ro`, `ola1_restore_ro` | Cada cuenta solo en su base | `SELECT` para exportar y comparar. |
| Observador DBA | Solo durante el ensayo en la VM | `PROCESS` para observar esperas InnoDB, sin reutilizar esta cuenta en la aplicación. |

Crear las cuentas con contraseña única y host `127.0.0.1` o el host local confirmado con `CURRENT_USER()`. Usar permisos de base con los guiones bajos escapados, como en el ejemplo manual siguiente. Usar el nombre del host autenticado al asignar el permiso. Comprobar los grants efectivos localmente y registrar solo la lista de privilegios, ya que el resultado de `SHOW GRANTS` puede contener un hash. [Privilegios MariaDB](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant), [comodines de esquema](https://mariadb.com/docs/maxscale/maxscale-security/authentication-modules).

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON `restaurant\_ola1\_test`.*
TO 'ola1_app'@'127.0.0.1';
```

## Configuración local y comprobaciones

Crear `.env` únicamente en la raíz de la copia de ensayo. Generar la contraseña de `ola1_app` en el gestor de secretos, con al menos 24 caracteres aleatorios base64url. Rellenar el marcador localmente, sin comillas, espacios ni BOM. Restringir la ACL NTFS al operador y al usuario de servicio de Apache. Las credenciales de operador se introducen en phpMyAdmin con autenticación por cookie y quedan fuera de `.env`. Mantener la evidencia fuera de Git y htdocs.

```dotenv
DB_HOST=127.0.0.1
DB_NAME=restaurant_ola1_test
DB_USER=ola1_app
DB_PASS=<rellenar solo en la VM>
DB_CHARSET=utf8mb4
BASE_URL=/system-restaurant-ola1
APIS_NET_TOKEN=
```

Dentro de la VM, desde la carpeta privada que contiene `scripts`, ejecutar en PowerShell:

```powershell
$copia = 'C:\xampp\htdocs\system-restaurant-ola1'
$datos = 'C:\xampp\mysql\data'
$equipo = '<nombre registrado de la VM>'
& C:\xampp\php\php.exe scripts/ensayo/verificar_entorno.php $copia $equipo $datos
& C:\xampp\php\php.exe scripts/ensayo/diagnostico_mariadb.php $copia $equipo $datos
```

El primer comando no abre PDO. Valida ruta, hostname, extensión, claves exactas, ausencia de variables heredadas en CLI y destino local. El segundo abre solo TCP loopback, valida identidad, permisos, motor y opciones, y emite JSON de diagnóstico. Ejecutar [verificar_aislamiento.sql](../../scripts/ensayo/verificar_aislamiento.sql) con cada cuenta limitada. Con `ola1_app`, seleccionar el esquema de restauración como comprobación negativa **dentro de la VM** y registrar que se deniega. Antes de cada importación, repetir identidad y permisos con el operador correspondiente.

Las consultas de integración se ejecutan mediante `php scripts/ensayo/ejecutar_lecturas.php esquema|origen|destino`. El ejecutor comprueba la VM antes de PDO, usa una cuenta de lectura distinta por esquema, confirma versión y datadir y rechaza archivos fuera del perfil antes de consultar. No importarlas como SQL libre en phpMyAdmin. Comprobar los cinco perfiles con `php scripts/ensayo/verificar_privilegios.php` antes de habilitar la aplicación. Este comando necesita un operador local con capacidad de ver los grants y no imprime los hashes.

Revisar también `SetEnv` y el entorno de **Apache**, ya que el verificador CLI no ve ese proceso. `config/db.php` da prioridad a variables heredadas y tiene valores de respaldo si faltan claves. Mantener la cuenta administradora local con contraseña, restringir la VM a loopback y conservar el adaptador desconectado aunque cambie `.env`. Hacer HEAD a `.env` sin mostrar su contenido y exigir 403/404. Después de 006, confirmar que el primer login sintético genera su registro solo en `restaurant_ola1_test`. Si difieren identidad, grants, ACL, TLS o aislamiento, mantener la aplicación detenida.

## Datos sintéticos y evidencia

Crear Norte y Sur desde la interfaz después de verificar las migraciones. Usar las cuentas `admin.norte@example.test`, `caja.norte@example.test`, `admin.sur@example.test` y `caja.sur@example.test`. Norte usa mesas 12/13/14 y productos de S/ 100 y S/ 20. Sur usa mesas 12/21 y productos de S/ 45 y S/ 15. El caso global inicial utiliza dos cobros de S/ 100 y uno de S/ 45. En turnos separados, registrar un cobro mixto de S/ 90, un recobro de S/ 110 con reverso de S/ 90, gastos de S/ 15, S/ 7 y S/ 5, y una mesa renumerada 12→99 tras un cierre. Usar series de comprobante sintéticas y token externo vacío. Registrar IDs por caso y guardar el control de S/ 245 antes de cargar movimientos adicionales. [Casos de integración](../../tests/integration/README.md).

El operador debe entregar **antes de 004** el hostname de la VM, versión efectiva y ruta de datos, JSON del diagnóstico, evidencia de red y listeners, permisos saneados, resultado de denegación entre esquemas, hashes de scripts, inventario previo y prueba de que se pudo restaurar el corte base. Con esa evidencia, confirmar la autorización expresa de 004 y 006. Después entregar el inventario de cada importación, salida de phpMyAdmin, IDs de ensayo, capturas saneadas de concurrencia y métricas de restauración. Mantener contraseñas, hashes de autenticación, cookies y tokens fuera de la evidencia.

Para 004 y 006 preparar **dos hojas privadas separadas**. Cada una registra ID, operador, VM, host, puerto, esquema, SHA-256 del SQL y respaldo, fecha, ventana, preflight y aprobación expresa. La hoja de 006 incluye `004_verificada: true` sustentado por las consultas posteriores a 004. Comprobarlas sin conexión con `python scripts/ensayo/validar_autorizacion.py <hoja> <sql> C:/ensayo_privado/manifiesto_vm.json`. Si cambia cualquier artefacto o destino, emitir una hoja nueva. Este validador comprueba formato y hashes, no sustituye la aprobación humana.

Antes de copiar el paquete, ejecutar `python scripts/ensayo/escanear_secretos.py <carpeta_del_paquete>` y revisar el historial disponible con `--historial` desde el repositorio. Incluir ocultos, configuraciones, temporales y respaldos que se pretendan copiar. El escáner solo comunica conteos. Una coincidencia o archivo demasiado grande bloquea la copia hasta revisión privada. El análisis por patrones no acredita ausencia absoluta de secretos.

## Comprobaciones locales ejecutadas

`php -l` pasó en los dos verificadores. El verificador de configuración aceptó un archivo sintético válido y rechazó host remoto, esquema ajeno, token externo no vacío, clave duplicada y contraseña de marcador. El diagnóstico también rechazó la configuración inválida antes de abrir PDO. Los resultados validan las guardas locales. El diagnóstico conectado y los controles de red de la VM están pendientes.
