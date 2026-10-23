# Control de Operativos — REGPOL La Libertad

Es un sistema web para que 26 comisarías y unidades especializadas programen uno o más operativos por turno (mañana, tarde, noche y madrugada) e inicien y actualicen su ubicación desde el celular del jefe del operativo con GPS. El General ve los operativos en un mapa para hacer controles inopinados. Es independiente de SICAD.

## Leer antes de programar

1. `docs/01-reglas-de-negocio.md`: roles, ciclo de vida y reglas RN-01 a RN-21. **Es la fuente de verdad.**
2. `docs/02-modelo-de-datos.md`: tablas, decisiones de diseño y consultas de referencia. El esquema está en `sql/001_esquema.sql`.
3. `docs/03-stack-y-despliegue.md`: stack, estructura de carpetas, seguridad y despliegue en cPanel sin SSH.
4. `docs/04-pantallas-y-diseno.md`: pantallas por rol, colores por estado y diseño pensado para el celular.
5. `docs/05-glosario.md`: vocabulario PNP que debe usarse en la interfaz y en el código.

## Stack (resumen)

PHP 8.2+ plano con PDO, MySQL o MariaDB, Bootstrap 5.3 y Alpine.js 3. El hosting es compartido con cPanel.
La Google Maps JS API se carga **solo en las pantallas del SUPERVISOR** (RN-21). En las demás vistas, cada ubicación es un enlace `https://www.google.com/maps/search/?api=1&query=LAT,LNG`. Para ubicar, en el celular se intenta primero el GPS del navegador; en la PC se va directo a pegar las coordenadas (de WhatsApp o Google Maps), que quedan marcadas como `MANUAL` (RN-09).
**No usar** frameworks, Composer en producción, Node, bundlers, websockets ni tareas cron. El mapa se actualiza consultando `/api/mapa` cada 30 s.

## Reglas de código

- **Nombres en español**, sin tildes ni ñ en los identificadores (`fecha_servicio`, `MANANA`). En los textos de la interfaz sí se usan tildes.
- **Tablas**: en plural y `snake_case` (`operativos`, `operativo_ubicaciones`). Las tablas pivote o de detalle llevan el prefijo de la entidad dueña en singular (`operativo_tipos`).
- **Columnas**: en `snake_case`. La clave primaria es `id`, las foráneas `<entidad_singular>_id` y las de auditoría `creado_en` y `actualizado_en`.
- **Funciones PHP**: en `snake_case` y con verbo (`obtener_operativo()`, `registrar_ubicacion()`). Las constantes van en `MAYUSCULAS`.
- Cada pantalla es un archivo en `app/paginas/`, y los endpoints JSON van en `app/api/`. La lógica de negocio pura va en `app/dominio.php`.
- SQL **siempre** con sentencias preparadas de PDO. Toda salida HTML se escapa con `e()`. Todo POST lleva token CSRF.
- **Toda consulta de una cuenta UNIDAD se filtra con `unidad_de_sesion()` en el servidor** (RN-16). Nunca se acepta un `unidad_id` que venga del navegador.
- Las fechas y horas de los eventos las pone el servidor (RN-11).
- Cada acción sobre operativos, usuarios o controles se registra en `bitacora`.
- Los cambios de base de datos van en un archivo nuevo `sql/NNN_descripcion.sql`. Nunca se editan los archivos ya aplicados.
- En elementos con `x-show` de Alpine no usar las clases `d-flex`, `d-grid`, `d-block`, etc. de Bootstrap (tienen `!important` y el elemento nunca se oculta): usar `.fila-flex` o envolver en otro elemento.

## Tests

PHPUnit **solo en local** (Laragon). Toda función de `app/dominio.php` debe tener tests, incluidos los casos borde:

- Clasificación del turno en los límites de cada rango (05:59, 06:00, 11:59, 12:00…) y operativos que cruzan la medianoche.
- Servicio diario vigente antes y después de las 06:00 (la MADRUGADA pertenece a la fecha de servicio anterior).
- Coordenadas dentro y fuera de La Libertad, incluidos los bordes del rectángulo; las de signo invertido o sin punto decimal deben quedar rechazadas.
- `parsear_coordenadas()` con los formatos de Google Maps (decimal, URL con `@`, grados, minutos y segundos, coma decimal) y con textos que no reconoce.
- Tolerancias de los estados derivados.
- Eliminar justo antes y justo después de la hora de inicio.

## Flujo de trabajo

- Antes de cada `git push`, detenerse y esperar la revisión de Eduardo.
- Si una regla de negocio es ambigua o falta, **preguntar** en lugar de suponer, y anotarla en "Pendientes por confirmar" de `docs/01`.
