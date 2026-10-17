-- Control de Operativos — turno MADRUGADA, origen de la ubicación y cierre de sesiones
-- Aplicar después de 002_datos_iniciales.sql.
SET NAMES utf8mb4;

-- Cuarto turno del servicio diario (00:00 a 05:59, RN-02).
ALTER TABLE operativos
  MODIFY turno ENUM('MANANA','TARDE','NOCHE','MADRUGADA') NOT NULL;

-- Origen de cada ubicación: GPS o pegada desde Google Maps (RN-09).
-- Las filas existentes quedan como GPS; luego se quita el valor por defecto para que la aplicación lo indique siempre.
ALTER TABLE operativo_ubicaciones
  ADD COLUMN origen ENUM('GPS','MANUAL') NOT NULL DEFAULT 'GPS' AFTER precision_m;
ALTER TABLE operativo_ubicaciones
  ALTER COLUMN origen DROP DEFAULT;

-- En los controles la ubicación del supervisor es opcional (RN-15): NULL si no se registró.
ALTER TABLE controles_inopinados
  ADD COLUMN origen ENUM('GPS','MANUAL') NULL AFTER precision_m;

-- Se incrementa al resetear la clave o desactivar la cuenta para cerrar sus sesiones abiertas (RN-19).
ALTER TABLE usuarios
  ADD COLUMN version_sesion INT UNSIGNED NOT NULL DEFAULT 0 AFTER ultimo_acceso;
