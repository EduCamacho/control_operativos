-- Control de Operativos — ícono de cada concepto de resultado (reportes)
-- Aplicar después de 011_intentos_ingreso.sql.
-- Nombre de un archivo de app/iconos/ (sin .svg). NULL: se usa el genérico.
-- El ADMIN lo elige en Catálogos; aquí se asignan los de los conceptos iniciales.
SET NAMES utf8mb4;

ALTER TABLE conceptos_resultado
  ADD COLUMN icono VARCHAR(30) NULL AFTER descripcion;

UPDATE conceptos_resultado SET icono = CASE nombre
  WHEN 'DETENIDOS EN FLAGRANCIA'                       THEN 'lock'
  WHEN 'REQUISITORIADOS CAPTURADOS'                    THEN 'user-search'
  WHEN 'EXTRANJEROS EN SITUACIÓN MIGRATORIA IRREGULAR' THEN 'world'
  WHEN 'PERSONAS RETENIDAS PARA IDENTIFICACIÓN'        THEN 'id'
  WHEN 'BANDAS DESARTICULADAS'                         THEN 'users-group'
  WHEN 'PAPELETAS POR IRNT'                            THEN 'receipt'
  WHEN 'VEHÍCULOS INTERNADOS EN DEPÓSITO'              THEN 'car-garage'
  WHEN 'VEHÍCULOS REQUISITORIADOS RECUPERADOS'         THEN 'car'
  WHEN 'ARMAS DE FUEGO INCAUTADAS'                     THEN 'crosshair'
  WHEN 'MUNICIONES INCAUTADAS'                         THEN 'box-multiple'
  WHEN 'ENVOLTORIOS DE DROGA DECOMISADOS'              THEN 'pills'
  ELSE icono
END;
