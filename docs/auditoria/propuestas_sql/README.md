# Diseño de cambios SQL para preparar durante la remediación

No se generaron migraciones ejecutables en esta auditoría. Hay dos restricciones de ubicación: el encargo de auditoría permite crear solo docs/auditoria/, mientras la regla crítica del proyecto exige SQL estructural o de datos iniciales en Database/. Para respetar ambas, este directorio contiene diseño y reversa, sin .sql ni escrituras a BD. Además, el esquema desplegado no fue inspeccionado y la atribución histórica de turnos necesita una decisión de negocio. No se presenta una migración adivinada como lista para producción.

## 1. Reparar instalador vacío (H-018)

Durante implementación preparar Database/NNN_instalacion_produccion.sql completo como alternativa de instalación, no ALTER incremental sobre una base con datos. Recuperar comprobantes de un DDL íntegro contrastado con 000: pagos_json, created_at, validación JSON según motor, ENGINE InnoDB/collation, PK, índices y FK. Conservar las 17 tablas, rol encargado y un solo usuario superadmin; no publicar ni copiar hashes a informes. Revisar índices/campos de todas las tablas, no solo conteo de CREATE.

Verificación: contrato estático actual deja de fallar al apuntar al nuevo instalador; desarrollador importa manualmente en base vacía, revisa SHOW CREATE TABLE y prueba comprobante. Reversa: descartar solo la base de ensayo fallida; no hay DROP de producción. Instalador no debe tener IF NOT EXISTS que oculte diferencias ni borrados destructivos.

## 2. Movimientos ligados a turno (H-001)

Crear un cambio aditivo con referencia nullable a turnos en pagos y gastos, índice de búsqueda por turno, datos del cierre y reversos trazables. La estructura exacta depende de elegir reverso como nuevo movimiento o registro separado; no introducir montos negativos en pagos si contradice su contrato vigente. La reapertura no debe sobreescribir movimientos previos ni cierres. pedidos puede conservar turno de apertura si se necesita, pero no reemplaza turno del cobro.

Backfill: usar fechas solo para proponer candidatos; cero o múltiples candidatos quedan en listado manual, nunca asignación con LIMIT 1. Mantener originales y reporte de reconciliación. Activar lectura nueva solo cuando todos los escritores llenen la relación y los casos ambiguos estén resueltos.

Reversa: apagar lectura nueva y volver al código anterior compatible con columnas aditivas. Exportar relaciones/ajustes nuevos; no DROP columnas/tablas mientras existan movimientos exclusivos del nuevo modelo. Reversión destructiva solo después de demostrar que no pierde datos y con importación manual del respaldo ensayado.

## 3. Índices (H-008)

Tras EXPLAIN preparar Database/NNN_indices_consultas.sql únicamente con índices realmente ausentes y elegidos para consultas finales. Candidatos en 03. Reversa elimina solo índices nuevos por nombre, nunca PK/UNIQUE/FK existentes. Estimar bloqueo/espacio de CREATE INDEX en motor real antes de aplicar. No duplicar idx_gastos_rest_fecha ni uq_turno_abierto_por_restaurante.

## 4. Registro de migraciones (H-015)

Tabla simple: version/nombre únicos, checksum del archivo, aplicado_en y aplicado_por. Aplicación manual con registro después de verificar el cambio. No incluir ejecución de SQL desde PHP. Antes de registrar baseline, comparar estructura, no asumir que el número de archivo equivale al estado real.

Reversa: mantener bitácora por su valor de auditoría; exportarla antes de cualquier retiro. El rollback de una migración se registra como evento nuevo; no borrar el hecho histórico.

## 5. Snapshot y validaciones (H-006/H-023)

Primero llenar nombre_producto ya existente y guardar opciones al crear el ítem. Nuevas columnas snapshot solo si no existe un lugar adecuado; no hacer UPDATE masivo del pasado con nombres actuales como si fueran históricos. Validaciones de monto/cantidad en servidor y luego CHECK compatibles con el motor tras localizar violaciones actuales. Reversa aditiva conserva snapshots y datos corregidos.

Cuando se creen los archivos definitivos se deberá avisar: «Creé el script Database/NNN_nombre.sql. Ejecútalo manualmente en phpMyAdmin antes de probar este módulo». En esta auditoría no hay un SQL nuevo para ejecutar.
