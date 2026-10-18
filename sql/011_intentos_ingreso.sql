-- Control de Operativos — intentos de ingreso fallidos por conexión (IP)
-- Aplicar después de 010_referencia_opcional_ubicaciones.sql.
-- RN-19a: 15 fallos desde la misma IP en 15 minutos frenan esa conexión, sin
-- bloquear la cuenta. Cada fallo queda aquí y los de más de un día se borran
-- al registrar uno nuevo (no hay tareas cron en el hosting).
SET NAMES utf8mb4;

CREATE TABLE intentos_ingreso (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip            VARCHAR(45) NOT NULL,
  usuario       VARCHAR(50) NULL,                       -- lo que se escribió, aunque no exista
  motivo        ENUM('CLAVE','TRAMPA','VERIFICACION') NOT NULL,
  registrado_en DATETIME NOT NULL,
  KEY ix_intentos_ingreso_ip (ip, registrado_en),
  KEY ix_intentos_ingreso_registrado (registrado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
