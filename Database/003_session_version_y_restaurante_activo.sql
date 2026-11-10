-- ============================================================
-- Script: 003_session_version_y_restaurante_activo.sql
-- Propósito: Agregar columna session_version a usuarios para
--            invalidar sesiones activas al cambiar contraseña,
--            rol o estado. Parte de la corrección H-020.
-- Fecha: 2026-11-10
-- Autor: R.DEV
-- Instrucciones: Ejecutar en phpMyAdmin ANTES de desplegar
--                los cambios de código.
-- ============================================================

-- Agregar session_version a usuarios (valor inicial 1)
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `session_version` int(11) NOT NULL DEFAULT 1;

-- ============================================================
-- REVERSIÓN (si necesitas deshacer este script):
-- ALTER TABLE `usuarios` DROP COLUMN `session_version`;
-- ============================================================

