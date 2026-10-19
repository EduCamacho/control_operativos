-- Control de Operativos — hasta dos cuentas por unidad, con los datos de la persona (RN-17, RN-20)
-- Aplicar después de 012_icono_conceptos.sql.
-- Cada unidad pasa de una cuenta compartida a dos personales (por ejemplo, el
-- secretario y el jefe de unidad). El límite de dos lo controla el sistema.
-- grado, cip y celular son obligatorios para las cuentas UNIDAD (los valida la
-- aplicación); en ADMIN y SUPERVISOR son opcionales, por eso admiten NULL.
SET NAMES utf8mb4;

-- La clave foránea necesita un índice sobre unidad_id: se crea el normal antes de
-- quitar el único.
ALTER TABLE usuarios
  ADD KEY idx_usuarios_unidad (unidad_id);

ALTER TABLE usuarios
  DROP INDEX uq_usuarios_unidad;

ALTER TABLE usuarios
  ADD COLUMN grado   VARCHAR(30) NULL AFTER nombre,
  ADD COLUMN cip     VARCHAR(8)  NULL AFTER grado,
  ADD COLUMN celular VARCHAR(15) NULL AFTER cip,
  ADD UNIQUE KEY uq_usuarios_cip (cip);
