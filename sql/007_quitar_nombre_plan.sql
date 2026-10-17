-- Control de Operativos — se elimina el nombre del plan
-- Aplicar después de 006_coordenadas_lugar_programado.sql.
-- El campo dejó de pedirse al programar (decisión de Eduardo, revisión de diseño).
SET NAMES utf8mb4;

ALTER TABLE operativos
  DROP COLUMN nombre_plan;
