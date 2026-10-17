-- Control de Operativos — identificador de envío de cada ubicación
-- Aplicar después de 003_madrugada_origen_sesion.sql.
SET NAMES utf8mb4;

-- Lo genera el celular en cada captura. Si se corta la señal después de que el
-- servidor guardó el punto, el reintento trae el mismo id_envio y no se duplica (RN-11a).
ALTER TABLE operativo_ubicaciones
  ADD COLUMN id_envio VARCHAR(36) NULL AFTER registrado_por,
  ADD UNIQUE KEY uq_operativo_ubicaciones_id_envio (id_envio);
