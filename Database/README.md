# SQL de MESORA

## Gastos por método de pago

En una instalación existente, ejecuta manualmente `010_metodo_pago_gastos.sql` después de respaldar la base y antes de probar el registro de gastos o los reportes actualizados. Los gastos anteriores quedarán sin método especificado. La migración 010 es independiente de la 009; aplica ambas si necesitas también disponibilidad de productos. En una instalación nueva, utiliza únicamente el instalador 008 actualizado.

## Instalación nueva

Importa [008_mesora_instalacion_oficial.sql](008_mesora_instalacion_oficial.sql) en una base MariaDB nueva y vacía, seleccionada en phpMyAdmin. El archivo contiene el esquema base con InnoDB, claves foráneas, límites de login y un único superadmin. No contiene datos de demostración. La comprobación inicial exige una base seleccionada sin tablas y admite nombres con prefijo del alojamiento.

No ejecutes 001–007, 011, 013, 015, 017 ni 021 después de importar el 008 actualizado. La protección de la bitácora contra edición y borrado se instala por separado mediante [019](019_proteger_auditoria.sql) con una cuenta que tenga el permiso `TRIGGER` sobre `auditoria`. Si el alojamiento deniega ese permiso, la aplicación puede seguir registrando eventos y la protección adicional de la bitácora queda pendiente.

Para habilitar NubeFacT en una instalación existente con el esquema fiscal actualizado, aplica manualmente [021](021_nubefact.sql). La reversión [022](022_reversion_nubefact.sql) se bloquea si existen comprobantes del proveedor. Consulta la [guía de operación](../docs/operaciones/nubefact.md).

Antes de importar, confirma en la barra de phpMyAdmin el servidor y la base elegida. El instalador no crea ni elimina bases de datos y verifica que la base seleccionada no tenga tablas. No lo importes sobre una instalación existente. Si aparece cualquier error, detén la importación y revísalo: MariaDB confirma los cambios de estructura por sentencia y una importación parcial no se repara repitiendo el archivo.

Al terminar, las consultas finales deben indicar **26 tablas, 1 usuario, 1 superadmin y 0 restaurantes**. El correo inicial es `admin@mesora.local`. Cambia la contraseña después del primer acceso.

Este script no repara un servidor MariaDB que no arranca. Primero debe resolverse el error InnoDB de XAMPP y conservarse una copia del directorio de datos afectado antes de cualquier recuperación.

Credenciales después de la importación:

- Correo: admin@mesora.local
- Contraseña: nLvjXElOt8ox2V8dUH5DBM_8JzZaa9lz
