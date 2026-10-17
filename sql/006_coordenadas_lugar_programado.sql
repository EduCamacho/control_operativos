-- Control de Operativos — coordenadas del lugar programado
-- Aplicar después de 005_id_envio_controles.sql.
SET NAMES utf8mb4;

-- Opcionales: la unidad las pega de Google Maps al programar. Permiten "Cómo llegar"
-- al lugar programado aunque el operativo todavía no se haya iniciado.
ALTER TABLE operativos
  ADD COLUMN lugar_latitud  DECIMAL(10,7) NULL AFTER lugar_programado,
  ADD COLUMN lugar_longitud DECIMAL(10,7) NULL AFTER lugar_latitud;
