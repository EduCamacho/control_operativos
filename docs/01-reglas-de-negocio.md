# 01 · Reglas de negocio — Control de Operativos

## Objetivo

El General de la Región Policial La Libertad dispuso que las comisarías y unidades especializadas ejecuten **un operativo por turno** (mañana, tarde y noche). El sistema permite:

1. Que cada unidad **programe** sus operativos con anticipación.
2. Que el **jefe del operativo**, desde su celular, **inicie** el operativo y registre **cada cambio de ubicación**; el sistema captura la posición con el GPS del teléfono.
3. Que el General vea en un **mapa** dónde se están ejecutando los operativos para realizar **controles inopinados**.

Reemplaza el Excel "LLENAR FORMATO COMISARÍAS Y DIVINCRI", que deja de usarse. Es un sistema **independiente** (no se integra con SICAD ni SICPIP).

> Motivo de capturar la ubicación por GPS: en el último Excel, 22 de 75 coordenadas (≈30 %) caían fuera de La Libertad por signo omitido o punto decimal perdido, y muchos enlaces de Google Maps no apuntaban al lugar real.

## Roles

| Rol | Quién lo usa | Qué puede hacer |
|---|---|---|
| `ADMIN` | UNITIC | Gestiona unidades, usuarios y catálogos. Ve todo. Puede corregir cualquier operativo (queda en bitácora). |
| `SUPERVISOR` | El General y quien él designe | Ve el mapa y el tablero de cumplimiento de todas las unidades. Registra controles inopinados. **No** crea ni modifica operativos. |
| `UNIDAD` | Una cuenta por comisaría o unidad, usada por el jefe de operativo de turno y por quien programa en la unidad | Programa, inicia, registra puntos, finaliza y registra resultados **solo de su unidad**. |

## Unidades

Son 26 unidades en 3 divisiones. El catálogo lo administra ADMIN; los nombres completos los proporcionará Eduardo.

- **DIVOPUS Trujillo** (18 comisarías): Ayacucho, El Alambre, La Noria, Buenos Aires, Nicolás Alcázar, Sánchez Carrión, Florencia de Mora, Alto Trujillo, Jerusalén, Bellavista, Huanchaco, El Milagro, Miramar, Moche, Salaverry, Laredo, Poroto y Simbal.
- **DUE** (5): UNEME-SECEME, Escuadrón Verde, UTSEVI Trujillo, Halcones Trujillo y UPROVE.
- **DIVINCRI La Libertad** (3): DEPINCRI Centro, DEPINCRI Este y DEPINCRI Norte.

## Ciclo de vida del operativo

```
PROGRAMADO ──Iniciar──▶ EN_CURSO ──Finalizar──▶ FINALIZADO
     │                     │
     │                     └─ Nuevo punto (n veces)
     └─ Eliminar (solo antes de la hora de inicio programada)
```

Los **estados derivados** se calculan al consultar y no se guardan en la base de datos:

| Estado derivado | Condición |
|---|---|
| `NO_INICIADO` | `PROGRAMADO` y ahora > `inicio_programado` + 15 min |
| `SIN_CIERRE` | `EN_CURSO` y ahora > `fin_programado` + 30 min |
| `PENDIENTE_NOTA` | `FINALIZADO` sin número de nota informativa |

## Reglas

**Programación**

- **RN-01** — Solo puede haber **un operativo por unidad, fecha de servicio y turno**. Lo garantiza una clave única en la base de datos.
- **RN-02** — La **fecha de servicio** es el día en que empieza el turno. Si la hora de fin es menor que la de inicio, el operativo termina al día siguiente (por ejemplo, el turno noche de 20:00 a 02:00). El sistema arma `inicio_programado` y `fin_programado` como fecha y hora completas.
- **RN-03** — **No hay hora límite para programar**: se puede hacer en cualquier momento, para hoy o para días futuros. No se permiten fechas pasadas, salvo el turno NOCHE del día anterior antes de las 06:00.
- **RN-04** — Datos de programación:
  - Tipo(s) de operativo: uno o más, del catálogo.
  - Nombre del plan: opcional, por ejemplo "Saltamonte 2026" o "Estado de Emergencia Trujillo-V-2026".
  - Lugar programado: texto con calle, avenida, sector o barrio.
  - Hora de inicio y hora de fin.
  - Jefe del operativo: grado, nombres y celular.
  - Fuerzas propias: entero ≥ 1.
  - Fuerzas de apoyo y unidades móviles: enteros ≥ 0.
- **RN-05** — Qué se puede editar según el estado:
  - `PROGRAMADO`: todo.
  - `EN_CURSO`: fuerzas, unidades móviles, jefe y observaciones.
  - `FINALIZADO`: solo la nota informativa y las observaciones.
  - ADMIN puede corregir cualquier dato, y la corrección queda en bitácora.
- **RN-06** — La unidad solo puede **eliminar** un operativo `PROGRAMADO` **antes de su hora de inicio programada**. Después queda visible como `NO_INICIADO`, para que no se puedan ocultar incumplimientos.

**Ejecución en campo**

- **RN-07** — **Inicio**: solo desde `PROGRAMADO`, y desde 60 minutos antes de la hora programada. Captura el GPS (primera ubicación) y guarda `inicio_real` con la hora del servidor.
- **RN-08** — **Validación de la ubicación**:
  - Se usa el GPS del navegador en alta precisión.
  - Se rechaza un punto fuera del rectángulo de La Libertad (latitud de −9.00 a −6.90, longitud de −79.80 a −76.80).
  - Si la precisión es peor que 100 m, se muestra una advertencia y el usuario decide si reintenta o acepta.
  - Siempre se guarda la precisión.
- **RN-09** — **El GPS es obligatorio**: sin ubicación GPS no se puede iniciar el operativo ni registrar un punto. No hay ingreso manual de coordenadas ni mapa para marcar. Si el permiso está denegado o el GPS no responde, el sistema muestra cómo activar la ubicación en el navegador y un botón **Reintentar**.
- **RN-10** — **Nuevo punto**: solo en `EN_CURSO`. Requiere GPS y una **referencia obligatoria** (calle, avenida o sector). La última ubicación es la posición actual y el historial de puntos forma el recorrido. No hay límite de puntos.
- **RN-11** — Todas las horas las pone el **servidor** (America/Lima), nunca el celular.
- **RN-12** — **Finalizar**: solo desde `EN_CURSO`. Se registra:
  - La cantidad por cada concepto de resultado: enteros ≥ 0, con 0 por defecto. Si todo queda en 0, el resultado es "negativo".
  - Observaciones: opcionales.
  - Número de nota informativa: opcional.

  `fin_real` toma la hora del servidor.
- **RN-13** — **Nota informativa SICPIP**: se guarda solo el número, en dígitos (por ejemplo, `202601802185`), y se muestra como "NOTA INFORMATIVA N° 202601802185". Si falta, el operativo queda `PENDIENTE_NOTA` y la unidad puede agregarla después.

**Supervisión**

- **RN-14** — **Alertas**: `NO_INICIADO` y `SIN_CIERRE`, según la tabla de estados derivados. Las tolerancias (15 y 30 min) son configurables.
- **RN-15** — **Control inopinado**:
  - Lo registra un SUPERVISOR sobre operativos de la fecha de servicio vigente.
  - Guarda la ubicación GPS del supervisor, el resultado (`CONFORME`, `CON_OBSERVACIONES` o `NO_ENCONTRADO`) y una observación.
  - El sistema calcula la distancia a la última ubicación que el operativo registró antes del control.
  - Un operativo puede tener varios controles.
- **RN-16** — **Visibilidad**: una cuenta UNIDAD solo ve y modifica los operativos de su unidad. El filtro por la unidad de la sesión se aplica **en el servidor**, en cada consulta. SUPERVISOR y ADMIN ven todo.
- **RN-21** — **El mapa interactivo es solo del SUPERVISOR.** En las vistas de UNIDAD y ADMIN, cada ubicación se muestra como un **enlace a Google Maps** armado con las coordenadas que guardó el sistema: `https://www.google.com/maps/search/?api=1&query=LAT,LNG`. Esos enlaces no consumen la API.

**Cuentas y seguridad**

- **RN-17** — Hay **una sola cuenta por unidad**, compartida. Puede estar abierta a la vez en varios dispositivos (la PC de la comisaría y el celular del jefe). Cada acción queda en bitácora con usuario, IP y dispositivo, y el operativo guarda el jefe declarado.
- **RN-18** — La sesión dura **al menos 12 horas**, para que el jefe no tenga que volver a ingresar en campo.
- **RN-19** — Tras 5 intentos fallidos de ingreso, la cuenta se bloquea 15 minutos. ADMIN puede resetear claves. **HTTPS es obligatorio**, porque sin él el navegador no entrega el GPS.
- **RN-20** — Una cuenta con rol UNIDAD debe tener una unidad asignada, y cada unidad tiene como máximo una cuenta. Las cuentas ADMIN y SUPERVISOR no tienen unidad.

## Flujo típico de un turno

1. El operador de la comisaría programa el operativo del turno tarde, por ejemplo a las 10:00.
2. A las 15:55, el jefe, con la cuenta de la comisaría en su celular, pulsa **Iniciar**. Se captura el GPS y el operativo aparece en el mapa del General.
3. A las 16:50 se desplazan a otra avenida. El jefe pulsa **Nuevo punto**, escribe la referencia, y el marcador se mueve y se dibuja el recorrido.
4. El General ve el punto, pulsa **Cómo llegar**, llega sin aviso y registra el **control inopinado**: conforme.
5. A las 18:05 el jefe pulsa **Finalizar** y registra 2 detenidos, 4 papeletas IRNT y la nota informativa.

## Fuera de alcance de la versión 1

- Jurisdicciones (GeoJSON) y validación por jurisdicción.
- Reporte exportable: queda para la fase 2. El modelo de datos ya lo soporta, porque los resultados están normalizados.
- Integración con SICAD o SICPIP.
- Notificaciones push.

## Pendientes por confirmar

- [ ] Nombres completos de las 26 unidades (Eduardo enviará la lista).
- [ ] Quiénes, además del General, tendrán rol SUPERVISOR.
- [ ] Catálogos iniciales de tipos de operativo y conceptos de resultado (ver `sql/002_datos_iniciales.sql`).
- [ ] Tolerancias (15 y 30 min) y la ventana para iniciar (60 min antes).
