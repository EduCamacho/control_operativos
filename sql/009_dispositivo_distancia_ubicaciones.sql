-- Control de Operativos — dispositivo y distancia al lugar programado de cada ubicación
-- Aplicar después de 008_varios_operativos_por_turno.sql.
-- RN-09a: se avisa (sin bloquear) cuando se inicia lejos del lugar programado o desde una PC,
-- y queda guardado para que el supervisor lo vea. Las filas existentes quedan en NULL (sin dato).
SET NAMES utf8mb4;

ALTER TABLE operativo_ubicaciones
  ADD COLUMN dispositivo       ENUM('CELULAR','PC') NULL AFTER origen,
  ADD COLUMN distancia_lugar_m INT UNSIGNED NULL AFTER dispositivo;
