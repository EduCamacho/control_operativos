-- Control de Operativos — referencia opcional en el nuevo punto
-- Aplicar después de 009_dispositivo_distancia_ubicaciones.sql.
-- RN-10 cambia: la referencia es obligatoria al iniciar y opcional en el nuevo punto (NULL si no se escribió).
SET NAMES utf8mb4;

ALTER TABLE operativo_ubicaciones
  MODIFY referencia VARCHAR(255) NULL;
