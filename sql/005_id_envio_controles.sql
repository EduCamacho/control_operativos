-- Control de Operativos — identificador de envío de cada control inopinado
-- Aplicar después de 004_id_envio_ubicaciones.sql.
SET NAMES utf8mb4;

-- Igual que en las ubicaciones: un reintento sin señal no duplica el control (RN-11a, RN-15).
ALTER TABLE controles_inopinados
  ADD COLUMN id_envio VARCHAR(36) NULL AFTER usuario_id,
  ADD UNIQUE KEY uq_controles_inopinados_id_envio (id_envio);
