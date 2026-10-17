-- Control de Operativos — varios operativos por turno
-- Aplicar después de 007_quitar_nombre_plan.sql.
-- RN-01 cambia: una unidad puede programar uno o más operativos en el mismo turno.
-- Se quita la clave única (unidad, fecha de servicio, turno) y queda un índice normal
-- para las consultas; el índice nuevo se agrega antes porque también sostiene la
-- clave foránea de unidad_id.
SET NAMES utf8mb4;

ALTER TABLE operativos
  ADD KEY ix_operativos_unidad_fecha_turno (unidad_id, fecha_servicio, turno),
  DROP INDEX uq_operativos_unidad_fecha_turno;
