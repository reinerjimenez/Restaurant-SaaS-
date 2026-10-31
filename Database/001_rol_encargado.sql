-- ============================================
-- Archivo: 001_rol_encargado.sql
-- Descripción: Agrega el rol encargado y crea la tabla de auditoría
-- INSTRUCCIONES: Ejecutar manualmente en phpMyAdmin antes de usar esta versión
-- ============================================

ALTER TABLE usuarios
  MODIFY COLUMN rol ENUM('superadmin','admin','encargado','atencion','cocina') NOT NULL;

CREATE TABLE IF NOT EXISTS auditoria (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id  INT NOT NULL,
    usuario_id      INT NOT NULL,
    usuario_nombre  VARCHAR(100) NOT NULL,
    accion          VARCHAR(100) NOT NULL,
    detalle         TEXT NULL,
    ip              VARCHAR(45) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
    INDEX idx_restaurante_fecha (restaurante_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
