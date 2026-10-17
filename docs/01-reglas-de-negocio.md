# 01 · Reglas de negocio — Control de Operativos

## Objetivo

El General de la Región Policial La Libertad dispuso que las comisarías y unidades especializadas ejecuten **al menos un operativo por turno** (pueden ser varios) (mañana, tarde, noche y madrugada). El sistema permite:

1. Que cada unidad **programe** sus operativos con anticipación.
2. Que el **jefe del operativo**, desde su celular, **inicie** el operativo y registre **cada cambio de ubicación**; el sistema captura la posición con el GPS del teléfono.
3. Que el General vea en un **mapa** dónde se están ejecutando los operativos para realizar **controles inopinados**.

Reemplaza el Excel "LLENAR FORMATO COMISARÍAS Y DIVINCRI", que deja de usarse. Es un sistema **independiente** (no se integra con SICAD ni SICPIP).

> Motivo de capturar la ubicación por GPS (y de validar y marcar como "Manual" las que se ingresan a mano, RN-09): en el último Excel, 22 de 75 coordenadas (≈30 %) caían fuera de La Libertad por signo omitido o punto decimal perdido, y muchos enlaces de Google Maps no apuntaban al lugar real.

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

## Servicio diario y turnos

El **servicio diario** va de las 06:00 de un día a las 05:59 del día siguiente, y lo identifica la `fecha_servicio` (el día en que empieza el servicio diario). Tiene cuatro turnos, en este orden:

| Turno | Hora de inicio programada | Día calendario del inicio |
|---|---|---|
| `MANANA` | 06:00 a 11:59 | `fecha_servicio` |
| `TARDE` | 12:00 a 17:59 | `fecha_servicio` |
| `NOCHE` | 18:00 a 23:59 | `fecha_servicio` |
| `MADRUGADA` | 00:00 a 05:59 | `fecha_servicio` + 1 día |

- El usuario **no elige el turno**: el sistema lo clasifica según la hora de inicio que se ingresa, en formato de 24 horas.
- La hora de fin no cambia el turno y **no hay duración máxima**. Por ejemplo, un operativo de 22:00 a 02:00 es de turno NOCHE.
- Ejemplo: el operativo que empieza el sábado 18 a las 02:00 es el turno MADRUGADA del servicio diario del viernes 17.
- El **servicio diario vigente** es el de hoy desde las 06:00, y el de ayer entre las 00:00 y las 05:59. Es el que muestran por defecto la pantalla de la unidad, el mapa y el tablero.

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
| `SIN_PROGRAMAR` | La unidad no tiene ningún operativo en ese servicio diario y turno (solo en el tablero) |
| `NO_INICIADO` | `PROGRAMADO` y ahora > `inicio_programado` + 15 min |
| `SIN_CIERRE` | `EN_CURSO` y ahora > `fin_programado` + 30 min |
| `PENDIENTE_NOTA` | `FINALIZADO` sin número de nota informativa. Se muestra como distintivo sobre `FINALIZADO`, no como un estado aparte. |

## Reglas

**Programación**

- **RN-01** — Una unidad puede programar **uno o más operativos en el mismo turno** de un servicio diario (cambio comunicado el 17/10/2026; antes era uno solo). Lo que se controla es que cada turno tenga **al menos uno**: el turno sin ninguno queda como `SIN_PROGRAMAR`. Ya no hay clave única en la base de datos (`sql/008`).
- **RN-02** — El usuario ingresa la fecha de servicio, la hora de inicio y la hora de fin. El sistema clasifica el turno según la hora de inicio (ver "Servicio diario y turnos") y arma `inicio_programado` y `fin_programado` como fecha y hora completas:
  - Si la hora de inicio es anterior a las 06:00 (MADRUGADA), el inicio cae el día siguiente a la fecha de servicio.
  - El fin es la primera vez que llega la hora de fin después del inicio. Si la hora de fin es menor que la de inicio, cae al día siguiente del inicio (por ejemplo, de 20:00 a 02:00).
  - La hora de inicio y la de fin no pueden ser iguales.
- **RN-03** — **No hay hora límite para programar**: se puede hacer en cualquier momento, siempre que `inicio_programado` sea posterior a la hora actual. **No se puede programar con la hora de inicio ya pasada**; eso solo lo puede hacer ADMIN, y queda en bitácora.
- **RN-04** — Datos de programación:
  - Tipo(s) de operativo: uno o más, del catálogo.
  - Lugar programado: texto con calle, avenida, sector o barrio.
  - Coordenadas del lugar programado: **obligatorias**. Se pegan desde Google Maps (botón **Buscar** junto al campo) y se validan dentro de La Libertad. Ubican en el mapa del General al operativo que todavía no registra puntos, y permiten "Cómo llegar" al lugar programado.
  - Hora de inicio y hora de fin.
  - Jefe del operativo: grado, nombres y celular. El grado se elige de una lista fija, en este orden: ALFZ, TNTE, CAP, MAY, CMDTE, CRNL y GRAL; luego S3, S2, S1, ST3, ST2, ST1, SB y SS.
  - Fuerzas propias: entero ≥ 1.
  - Fuerzas de apoyo y unidades móviles: enteros ≥ 0.
- **RN-05** — Qué se puede editar según el estado:
  - `PROGRAMADO`, antes de la hora de inicio programada: todo, incluidos la fecha de servicio y las horas. Al cambiar la hora de inicio puede cambiar el turno.
  - `PROGRAMADO`, desde la hora de inicio programada: todo, **excepto la fecha de servicio y las horas** (y por tanto el turno), para que no se pueda mover el horario y ocultar un `NO_INICIADO`.
  - `EN_CURSO`: fuerzas, unidades móviles, jefe y observaciones.
  - `FINALIZADO`: la unidad no puede modificar nada. Solo puede **agregar** la nota informativa si falta (RN-13).
  - ADMIN puede corregir cualquier dato en cualquier estado, eliminar operativos, reabrir uno `FINALIZADO` y programar en nombre de una unidad, también con la hora ya pasada. Cada corrección queda en bitácora con el valor anterior y el nuevo.
    - Cada corrección o eliminación de ADMIN exige un **motivo**, que queda en la bitácora con los cambios.
    - El estado se corrige respetando el ciclo de vida: PROGRAMADO sin horas reales ni puntos; EN_CURSO con inicio real y sin fin; FINALIZADO con inicio y fin reales (el fin no antes del inicio). Las horas reales no pueden estar en el futuro.
    - **Reabrir** (de FINALIZADO a EN_CURSO) borra los resultados y la nota; la copia queda en la bitácora y la unidad puede volver a finalizarlo.
    - ADMIN tampoco puede eliminar un operativo con controles inopinados (RN-06).
- **RN-06** — La unidad solo puede **eliminar** un operativo `PROGRAMADO` **antes de su hora de inicio programada**. Después queda visible como `NO_INICIADO`, para que no se puedan ocultar incumplimientos. Tampoco se puede eliminar un operativo que ya tiene un control inopinado: el control es evidencia de supervisión.

**Ejecución en campo**

- **RN-07** — **Inicio**: solo desde `PROGRAMADO`, desde 60 minutos antes de `inicio_programado` y **hasta antes de `fin_programado`**. Se puede iniciar tarde, pero queda registrado el `inicio_real`. Si llega la hora de fin sin haberse iniciado, ya no se puede iniciar y queda como `NO_INICIADO` de forma permanente (solo ADMIN puede corregirlo). El inicio captura el GPS (primera ubicación) y guarda `inicio_real` con la hora del servidor.
- **RN-08** — **Validación de la ubicación**:
  - Se usa el GPS del navegador en alta precisión.
  - Se rechaza un punto fuera del rectángulo de La Libertad (latitud de −9.00 a −6.90, longitud de −79.80 a −76.80).
  - Si la precisión es peor que 100 m, se muestra una advertencia y el usuario decide si reintenta o acepta.
  - Siempre se guarda la precisión.
- **RN-09** — **Primero el GPS, y el ingreso manual como respaldo**:
  - Siempre se intenta primero el GPS. Si el permiso está denegado o el GPS no responde, el sistema muestra cómo activar la ubicación en el navegador y un botón **Reintentar**.
  - Si el GPS falla, o si la precisión es peor que 100 m (lo habitual en una PC de escritorio), se ofrece **Ingresar coordenadas manualmente**. Está disponible para todos los roles.
  - En el ingreso manual, el usuario pega las coordenadas copiadas de Google Maps en un solo campo, y el sistema las separa en latitud y longitud (`parsear_coordenadas()`). Luego se muestran los dos campos ya llenos, para que el usuario los revise antes de guardar.
  - Formatos aceptados: `-8.1116, -79.0287`; la URL de Google Maps con `@-8.1116,-79.0287,17z`; y grados, minutos y segundos (`8°06'41.8"S 79°01'43.3"W`). La coma decimal de la configuración regional (`-8,1116`) también se acepta.
  - Cada ubicación guarda su **origen** (`GPS` o `MANUAL`). Las manuales no tienen precisión y se muestran con el distintivo "Manual" en el mapa, la ficha y las listas, para que el supervisor sepa que no las capturó el GPS.
  - Las coordenadas manuales de un operativo también se validan con el rectángulo de La Libertad (RN-08). Las del supervisor no (RN-15).
- **RN-10** — **Nuevo punto**: solo en `EN_CURSO`. Requiere una ubicación (por GPS o manual, RN-09) y una **referencia obligatoria** (calle, avenida o sector). La última ubicación es la posición actual y el historial de puntos forma el recorrido. No hay límite de puntos.
- **RN-11** — Todas las horas las pone el **servidor** (America/Lima), nunca el celular.
- **RN-11a** — **Sin conexión de datos**: si el envío de un inicio, un punto o un cierre falla, el celular avisa al usuario, conserva lo capturado en pantalla y reintenta el envío (de forma automática cuando vuelve la conexión, o con el botón **Reintentar**). La hora registrada es la de llegada al servidor (RN-11).
- **RN-12** — **Finalizar**: solo desde `EN_CURSO`. Se registra:
  - La cantidad por cada concepto de resultado: enteros ≥ 0, con 0 por defecto. Si todo queda en 0, el resultado es "negativo".
  - Observaciones: opcionales.
  - Número de nota informativa: opcional.

  `fin_real` toma la hora del servidor. Una vez finalizado, la unidad ya no puede modificar los resultados ni ningún otro dato; solo ADMIN (RN-05).
- **RN-13** — **Nota informativa SICPIP**: se guarda solo el número, en dígitos (por ejemplo, `202601802185`), y se muestra como "NOTA INFORMATIVA N° 202601802185". Si falta, el operativo queda `PENDIENTE_NOTA` y la unidad puede agregarla después; una vez guardada, solo ADMIN puede cambiarla.

**Supervisión**

- **RN-14** — **Alertas**: `NO_INICIADO` y `SIN_CIERRE`, según la tabla de estados derivados. Las tolerancias (15 y 30 min) y la ventana de inicio (60 min) se configuran en `app/config.php`.
- **RN-15** — **Control inopinado**:
  - Lo registra un SUPERVISOR sobre operativos del servicio diario vigente, en cualquier estado.
  - Guarda el resultado (`CONFORME`, `CON_OBSERVACIONES` o `NO_ENCONTRADO`) y una observación.
  - **No registra la ubicación del supervisor ni calcula distancias**: a veces el General controla con un dron, sin ir al lugar (decisión de Eduardo, 17/10/2026). Las columnas de ubicación y distancia de `controles_inopinados` se conservan solo por los controles registrados antes.
  - Un operativo puede tener varios controles.
  - La observación es obligatoria cuando el resultado es `CON_OBSERVACIONES` o `NO_ENCONTRADO`, y opcional cuando es `CONFORME`.
  - Un reintento sin señal no duplica el control (`id_envio`, RN-11a).
- **RN-16** — **Visibilidad**: una cuenta UNIDAD solo ve y modifica los operativos de su unidad. El filtro por la unidad de la sesión se aplica **en el servidor**, en cada consulta. SUPERVISOR y ADMIN ven todo.
- **RN-21** — **El mapa interactivo es solo del SUPERVISOR.** En las vistas de UNIDAD y ADMIN, cada ubicación se muestra como un **enlace a Google Maps** armado con las coordenadas que guardó el sistema: `https://www.google.com/maps/search/?api=1&query=LAT,LNG`. Esos enlaces no consumen la API.

**Cuentas y seguridad**

- **RN-17** — Hay **una sola cuenta por unidad**, compartida. Puede estar abierta a la vez en varios dispositivos (la PC de la comisaría y el celular del jefe). Cada acción queda en bitácora con usuario, IP y dispositivo, y el operativo guarda el jefe declarado.
- **RN-18** — La sesión dura **al menos 12 horas**, para que el jefe no tenga que volver a ingresar en campo.
- **RN-19** — Cada usuario puede **cambiar su propia clave** (clave actual, nueva de al menos 8 caracteres y distinta, y confirmación). La sesión actual sigue abierta y las de los demás dispositivos solo se cierran si el usuario lo elige, porque la cuenta UNIDAD es compartida (RN-17). Tras 5 intentos fallidos de ingreso, la cuenta se bloquea 15 minutos. El bloqueo es **por cuenta**, sin importar la IP. ADMIN puede resetear claves y desbloquear. Al resetear la clave o desactivar una cuenta, **se cierran todas sus sesiones abiertas**. **HTTPS es obligatorio**, porque sin él el navegador no entrega el GPS.
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

- [x] Uno o más operativos por turno (RN-01, comunicado a Eduardo el 17/10/2026).

- [ ] Nombres completos de las 26 unidades (Eduardo enviará la lista; los de `sql/002_datos_iniciales.sql` son provisionales).
- [ ] Quiénes, además del General, tendrán rol SUPERVISOR.
- [ ] Catálogos iniciales de tipos de operativo y conceptos de resultado (ver `sql/002_datos_iniciales.sql`).
- [x] Tolerancias (15 y 30 min) y ventana para iniciar (60 min antes): se configuran en `app/config.php` (`TOLERANCIA_NO_INICIADO_MIN`, `TOLERANCIA_SIN_CIERRE_MIN`, `VENTANA_INICIO_MIN`). No hay pantalla de ADMIN.
- [x] El grado de Comandante se abrevia CMDTE (confirmado por Eduardo).
- [x] Servicio diario de 06:00 a 05:59 y rangos horarios de los turnos (confirmado por Eduardo).
- [ ] Observación del control inopinado opcional cuando el resultado es CONFORME (propuesta de Claude, por validar).
- [x] Coordenadas del lugar programado obligatorias (confirmado por Eduardo: permiten poner el marcador en el mapa).
- [ ] SUPERVISOR y ADMIN ven el tablero y la ficha de cualquier operativo; el mapa y el registro de controles son solo del SUPERVISOR (propuesta de Claude, por validar).
