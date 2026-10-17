# 02 · Modelo de datos

La estructura completa está en `sql/001_esquema.sql` y los catálogos iniciales en `sql/002_datos_iniciales.sql`.
Motor: MySQL 8 o MariaDB 10.6+, con `utf8mb4` y tablas InnoDB.

## Diagrama

```mermaid
erDiagram
    unidades ||--o| usuarios : "tiene (rol UNIDAD)"
    unidades ||--o{ operativos : programa
    operativos }o--o{ tipos_operativo : "operativo_tipos"
    operativos ||--|{ operativo_ubicaciones : recorrido
    operativos ||--o{ operativo_resultados : resultados
    conceptos_resultado ||--o{ operativo_resultados : ""
    operativos ||--o{ controles_inopinados : controles
    usuarios ||--o{ controles_inopinados : registra
    usuarios ||--o{ bitacora : acciones
```

## Tablas

| Tabla | Contenido | Claves y notas |
|---|---|---|
| `unidades` | Las 26 comisarías y unidades | `division`: `DIVOPUS`, `DUE` o `DIVINCRI`. `nombre` es el nombre completo y `nombre_corto` el que se muestra en el mapa. |
| `usuarios` | Cuentas del sistema | `rol`: `ADMIN`, `SUPERVISOR` o `UNIDAD`. `unidad_id` es único: una cuenta por unidad. Las cuentas ADMIN y SUPERVISOR tienen `unidad_id` NULL. |
| `tipos_operativo` | Catálogo: control de identidad, rastrillaje, etc. | Lo administra ADMIN. |
| `conceptos_resultado` | Catálogo: detenidos en flagrancia, papeletas IRNT, etc. | Lo administra ADMIN. El formulario de cierre se genera a partir de este catálogo. |
| `operativos` | Un registro por unidad, fecha de servicio y turno | **Clave única** `(unidad_id, fecha_servicio, turno)` (RN-01). `estado`: `PROGRAMADO`, `EN_CURSO` o `FINALIZADO`. |
| `operativo_tipos` | Relación N:M entre operativo y tipos | Clave primaria compuesta. |
| `operativo_ubicaciones` | Puntos del recorrido | La última por `registrado_en` es la posición actual. Siempre viene del GPS (RN-09) e incluye la precisión en metros. |
| `operativo_resultados` | Cantidad por concepto | Clave primaria `(operativo_id, concepto_resultado_id)`. Se crean al finalizar. |
| `controles_inopinados` | Controles del General | `distancia_m` se calcula con Haversine contra la última ubicación del operativo. |
| `bitacora` | Auditoría de acciones | Es obligatoria porque las cuentas de unidad son compartidas (RN-17). |

## Decisiones de diseño

- **`turno`** se guarda como `ENUM('MANANA','TARDE','NOCHE')`, sin Ñ en el código. En pantalla se muestra "MAÑANA".
- **`inicio_programado` y `fin_programado` son DATETIME**, no TIME, porque el turno noche cruza la medianoche (RN-02).
- **Las coordenadas son `DECIMAL(10,7)`**, que da precisión de alrededor de 1 cm. No se usan tipos espaciales, porque no hacen falta sin jurisdicciones.
- **Los resultados están normalizados** (una fila por concepto), así que el reporte de la fase 2 se resuelve con un `SUM ... GROUP BY`, y agregar un concepto nuevo no requiere modificar tablas.
- **Los estados derivados no se guardan**: se calculan en la consulta (ver abajo) o en `app/dominio.php`.
- **No se usan `CHECK` constraints**, porque no todos los hostings los respetan. Las validaciones (RN-04, RN-20) van en PHP.

## Consultas de referencia

**Mapa: operativos vigentes con su última ubicación**

```sql
SELECT o.*, u.nombre_corto,
       ub.latitud, ub.longitud, ub.precision_m, ub.registrado_en AS ubicacion_en,
       CASE
         WHEN o.estado = 'PROGRAMADO' AND NOW() > o.inicio_programado + INTERVAL 15 MINUTE THEN 'NO_INICIADO'
         WHEN o.estado = 'EN_CURSO'   AND NOW() > o.fin_programado   + INTERVAL 30 MINUTE THEN 'SIN_CIERRE'
         ELSE o.estado
       END AS estado_visible
FROM operativos o
JOIN unidades u ON u.id = o.unidad_id
LEFT JOIN operativo_ubicaciones ub ON ub.id = (
    SELECT id FROM operativo_ubicaciones
    WHERE operativo_id = o.id
    ORDER BY registrado_en DESC, id DESC LIMIT 1)
WHERE o.fecha_servicio = :fecha
   OR (o.fecha_servicio = :fecha - INTERVAL 1 DAY AND o.turno = 'NOCHE' AND o.estado <> 'FINALIZADO');
```

**Reporte de la fase 2: resultados por unidad en un rango de fechas**

```sql
SELECT u.nombre_corto, c.nombre AS concepto, SUM(r.cantidad) AS total
FROM operativo_resultados r
JOIN operativos o ON o.id = r.operativo_id
JOIN unidades u ON u.id = o.unidad_id
JOIN conceptos_resultado c ON c.id = r.concepto_resultado_id
WHERE o.fecha_servicio BETWEEN :desde AND :hasta
GROUP BY u.id, c.id
ORDER BY u.orden, c.orden;
```

**Cumplimiento: operativos programados contra ejecutados**

```sql
SELECT u.nombre_corto,
       COUNT(o.id)                                    AS programados,
       SUM(o.estado IN ('EN_CURSO','FINALIZADO'))     AS ejecutados,
       SUM(o.estado = 'PROGRAMADO' AND NOW() > o.inicio_programado + INTERVAL 15 MINUTE) AS no_iniciados
FROM unidades u
LEFT JOIN operativos o ON o.unidad_id = u.id AND o.fecha_servicio BETWEEN :desde AND :hasta
WHERE u.activo = 1
GROUP BY u.id
ORDER BY u.orden;
```

> `NOW()` depende de la zona horaria de la conexión: `app/db.php` ejecuta `SET time_zone = '-05:00'` al conectar (ver 03).
