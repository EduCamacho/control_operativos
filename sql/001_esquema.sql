-- Control de Operativos — esquema inicial
-- Importar en phpMyAdmin en este orden: 001_esquema.sql y luego 002_datos_iniciales.sql
SET NAMES utf8mb4;
SET time_zone = '-05:00';

CREATE TABLE unidades (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  division       ENUM('DIVOPUS','DUE','DIVINCRI') NOT NULL,
  nombre         VARCHAR(150) NOT NULL,
  nombre_corto   VARCHAR(60)  NOT NULL,
  orden          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unidades_nombre_corto (nombre_corto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario           VARCHAR(50)  NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  nombre            VARCHAR(150) NOT NULL,
  rol               ENUM('ADMIN','SUPERVISOR','UNIDAD') NOT NULL,
  unidad_id         INT UNSIGNED NULL,
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta   DATETIME NULL,
  ultimo_acceso     DATETIME NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_usuario (usuario),
  UNIQUE KEY uq_usuarios_unidad (unidad_id),            -- una cuenta por unidad (RN-20); NULL se permite repetido
  CONSTRAINT fk_usuarios_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tipos_operativo (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  orden  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_tipos_operativo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conceptos_resultado (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_conceptos_resultado_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operativos (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidad_id         INT UNSIGNED NOT NULL,
  fecha_servicio    DATE NOT NULL,
  turno             ENUM('MANANA','TARDE','NOCHE') NOT NULL,
  nombre_plan       VARCHAR(150) NULL,
  lugar_programado  VARCHAR(255) NOT NULL,
  inicio_programado DATETIME NOT NULL,
  fin_programado    DATETIME NOT NULL,
  jefe_grado        VARCHAR(30)  NOT NULL,
  jefe_nombres      VARCHAR(150) NOT NULL,
  jefe_celular      VARCHAR(15)  NOT NULL,
  fuerzas_propias   SMALLINT UNSIGNED NOT NULL,
  fuerzas_apoyo     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  unidades_moviles  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estado            ENUM('PROGRAMADO','EN_CURSO','FINALIZADO') NOT NULL DEFAULT 'PROGRAMADO',
  inicio_real       DATETIME NULL,
  fin_real          DATETIME NULL,
  nota_informativa  VARCHAR(20) NULL,                   -- solo dígitos (RN-13)
  observaciones     TEXT NULL,
  creado_por        INT UNSIGNED NOT NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_operativos_unidad_fecha_turno (unidad_id, fecha_servicio, turno),   -- RN-01
  KEY ix_operativos_fecha_estado (fecha_servicio, estado),
  CONSTRAINT fk_operativos_unidad  FOREIGN KEY (unidad_id)  REFERENCES unidades(id),
  CONSTRAINT fk_operativos_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operativo_tipos (
  operativo_id      INT UNSIGNED NOT NULL,
  tipo_operativo_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (operativo_id, tipo_operativo_id),
  CONSTRAINT fk_operativo_tipos_operativo FOREIGN KEY (operativo_id) REFERENCES operativos(id) ON DELETE CASCADE,
  CONSTRAINT fk_operativo_tipos_tipo      FOREIGN KEY (tipo_operativo_id) REFERENCES tipos_operativo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operativo_ubicaciones (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  operativo_id   INT UNSIGNED NOT NULL,
  latitud        DECIMAL(10,7) NOT NULL,
  longitud       DECIMAL(10,7) NOT NULL,
  precision_m    SMALLINT UNSIGNED NULL,               -- siempre GPS (RN-09)
  referencia     VARCHAR(255) NOT NULL,
  registrado_por INT UNSIGNED NOT NULL,
  registrado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_operativo_ubicaciones_operativo (operativo_id, registrado_en),
  CONSTRAINT fk_operativo_ubicaciones_operativo FOREIGN KEY (operativo_id) REFERENCES operativos(id) ON DELETE CASCADE,
  CONSTRAINT fk_operativo_ubicaciones_usuario   FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operativo_resultados (
  operativo_id          INT UNSIGNED NOT NULL,
  concepto_resultado_id INT UNSIGNED NOT NULL,
  cantidad              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (operativo_id, concepto_resultado_id),
  CONSTRAINT fk_operativo_resultados_operativo FOREIGN KEY (operativo_id) REFERENCES operativos(id) ON DELETE CASCADE,
  CONSTRAINT fk_operativo_resultados_concepto  FOREIGN KEY (concepto_resultado_id) REFERENCES conceptos_resultado(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE controles_inopinados (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  operativo_id  INT UNSIGNED NOT NULL,
  usuario_id    INT UNSIGNED NOT NULL,
  latitud       DECIMAL(10,7) NULL,
  longitud      DECIMAL(10,7) NULL,
  precision_m   SMALLINT UNSIGNED NULL,
  distancia_m   INT UNSIGNED NULL,                      -- a la última ubicación del operativo
  resultado     ENUM('CONFORME','CON_OBSERVACIONES','NO_ENCONTRADO') NOT NULL,
  observacion   TEXT NULL,
  registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_controles_operativo (operativo_id),
  CONSTRAINT fk_controles_operativo FOREIGN KEY (operativo_id) REFERENCES operativos(id),
  CONSTRAINT fk_controles_usuario   FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bitacora (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED NULL,
  accion        VARCHAR(50) NOT NULL,                   -- LOGIN, PROGRAMAR, INICIAR, NUEVO_PUNTO, FINALIZAR, CONTROL, EDITAR, ELIMINAR...
  entidad       VARCHAR(50) NULL,
  entidad_id    INT UNSIGNED NULL,
  detalle       TEXT NULL,                              -- JSON
  ip            VARCHAR(45) NULL,
  user_agent    VARCHAR(255) NULL,
  registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bitacora_entidad (entidad, entidad_id),
  KEY ix_bitacora_usuario (usuario_id, registrado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
