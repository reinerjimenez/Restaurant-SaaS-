# SQL de MESORA

## Instalación nueva

Importa **solo** [008_mesora_instalacion_oficial.sql](008_mesora_instalacion_oficial.sql) en la base MariaDB `mesora`, nueva y vacía, seleccionada en phpMyAdmin. El archivo contiene el esquema completo de Ola 0 y Ola 1, InnoDB, claves foráneas, límites de login y un único superadmin. No contiene restaurantes, pedidos, pagos ni otros datos de demostración. El preflight bloquea la ejecución si la base seleccionada no se llama `mesora` o ya contiene tablas.

No ejecutes 001–007 después de 008. Los archivos 000 y 002 conservan el esquema anterior como referencia; 001, 003, 004 y 006 son migraciones para instalaciones anteriores; 005 y 007 son reversiones. El volcado de demostración `002_db_demo_actual.sql` se retiró del proyecto.

Antes de importar, confirma en la barra de phpMyAdmin el servidor y la base elegida. El instalador no crea ni elimina bases de datos y verifica que la base seleccionada no tenga tablas. No lo importes sobre una instalación existente. Si aparece cualquier error, detén la importación y revísalo: MariaDB confirma los cambios de estructura por sentencia y una importación parcial no se repara repitiendo el archivo.

Al terminar, las consultas finales deben indicar **21 tablas, 1 usuario, 1 superadmin y 0 restaurantes**. El correo inicial es `admin@mesora.local`; la contraseña se entrega al operador por separado y no se guarda en este repositorio en texto plano. Cámbiala después del primer acceso.

Este script no repara un servidor MariaDB que no arranca. Primero debe resolverse el error InnoDB de XAMPP y conservarse una copia del directorio de datos afectado antes de cualquier recuperación.

Credenciales después de la importación:

- Correo: admin@mesora.local
- Contraseña: nLvjXElOt8ox2V8dUH5DBM_8JzZaa9lz
