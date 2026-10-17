# 04 · Pantallas y diseño

## Principios

- **Pensado primero para el celular.** El jefe usa el sistema en campo, a veces de noche y con una sola mano, y el General lo usa desde la camioneta.
- Botones de acción grandes (mínimo 48 px de alto, de ancho completo en el celular).
- **Menú en barra lateral izquierda**: fija en pantallas grandes (desde 992 px) y, en el celular, panel deslizable (offcanvas) que se abre con el botón ☰ de la barra superior, para no quitarle pantalla al jefe en campo.
- Bootstrap 5.3 con tema claro. La tipografía es la del sistema (`system-ui`): carga rápido y se lee bien.
- **Color institucional** (`#1f6f50`, el del menú lateral; se define una sola vez en `--color-institucional` de `app.css`) para los botones de acción de los formularios (`.btn-institucional`, `.btn-outline-institucional`) y el foco de los campos. El azul de Bootstrap (`primary`) se reserva para el estado FINALIZADO.
- Los textos de la interfaz van en español y con la terminología de la PNP (ver 05-glosario).

## Colores por estado (los mismos en mapa, tarjetas y tablero)

| Estado visible | Color | Bootstrap |
|---|---|---|
| SIN_PROGRAMAR | amarillo | `warning` (solo en el tablero: celda vacía) |
| PROGRAMADO | gris | `secondary` |
| EN_CURSO | verde | `success` |
| NO_INICIADO | rojo | `danger` |
| SIN_CIERRE | naranja | clase propia `.bg-naranja` (`#fd7e14`), para no confundirse con el amarillo de SIN_PROGRAMAR |
| FINALIZADO | azul | `primary` |

`PENDIENTE_NOTA` no tiene color propio: se muestra como un distintivo "Sin nota" sobre el azul de FINALIZADO.

## Ubicaciones fuera del mapa del General

El mapa interactivo solo existe en las pantallas del SUPERVISOR (RN-21). En las vistas de UNIDAD y ADMIN, cada ubicación se muestra como texto ("Av. España con Jr. Grau · 16:52 · precisión 8 m") junto a un botón o enlace **Ver en Google Maps** (`https://www.google.com/maps/search/?api=1&query=LAT,LNG`, en una pestaña nueva). Esas páginas no cargan la API de Google Maps.

## Rol UNIDAD

1. **Mis operativos (inicio)**
   - Muestra el servicio diario vigente con **4 tarjetas fijas: MAÑANA, TARDE, NOCHE y MADRUGADA**.
   - Cada tarjeta tiene su estado y **un solo botón** con la acción que toca: *Programar*, *Iniciar*, *Nuevo punto / Finalizar* o *Agregar nota*.
   - Debajo, los operativos de los próximos días y el historial.
2. **Programar / editar**: asistente en **4 pasos**, con la barra de progreso arriba y los botones **Atrás** y **Siguiente** fijos al pie, al alcance del pulgar.
   1. **Cuándo**: fecha de servicio, hora de inicio y hora de fin (24 horas). El turno no se elige: se muestra calculado al escribir la hora, junto con el día y la hora reales ("MADRUGADA · empieza el sáb 18/10 a las 02:00"). Avisa si la unidad ya tiene un operativo en ese turno.
   2. **Qué y dónde**: arriba, los tipos de operativo como píldoras pequeñas que se marcan al tocarlas; debajo, la tarjeta destacada **¿Dónde será el operativo?** con la dirección o referencia y las **coordenadas del lugar** (opcionales: el botón **Abrir Google Maps** busca el lugar escrito; el usuario mantiene presionado el punto, copia las coordenadas y las pega).
   3. **Jefe y fuerzas**: grado (lista desplegable), nombres y celular, que se completan con el jefe del último operativo de la unidad; fuerzas propias, de apoyo y unidades móviles con botones − y +; observaciones.
   4. **Revisar**: resumen de los tres bloques, cada uno con **Cambiar**, y el botón **Programar operativo**.
   - Cada paso se valida antes de avanzar; el servidor vuelve a validar todo y, si rechaza algo, el asistente abre el paso con el error.
   - **Al editar** abre directo en **Revisar**. Los bloques que el estado no permite cambiar (RN-05) muestran "No se puede cambiar"; en curso solo se cambia el bloque 3.
   - El formulario del ADMIN usa los mismos bloques, pero en una sola página.
3. **Operativo en curso**
   - Arriba, la ubicación actual en texto con el enlace **Ver en Google Maps**. No hay mapa.
   - Dos botones grandes: **Registrar nuevo punto** y **Finalizar operativo**.
   - Debajo, la lista de puntos con hora, referencia y enlace a Google Maps.
4. **Captura de ubicación** (al iniciar y al registrar un nuevo punto)
   - Mientras obtiene el GPS muestra el mensaje "Obteniendo ubicación…" y luego la precisión lograda.
   - Si la precisión es peor que 100 m, ofrece *Reintentar*, *Usar de todos modos* o *Ingresar manualmente*.
   - Si el permiso está denegado o el GPS no responde, muestra los pasos para activar la ubicación en el navegador del celular, un botón **Reintentar** y la opción *Ingresar manualmente* (RN-09).
   - **Ingreso manual**: tiene un campo "Pegar coordenadas de Google Maps", con la ayuda "En Google Maps, mantén presionado el lugar (o haz clic derecho en la PC) y copia las coordenadas". Al pegar, se llenan solos los campos de latitud y longitud, que el usuario revisa. Hay un enlace **Ver en Google Maps** para comprobar el punto antes de guardar. Si las coordenadas caen fuera de La Libertad, se muestra el error y no se guardan.
   - Pide la referencia, que es obligatoria.
   - Si no hay conexión de datos al enviar, avisa "Sin conexión: se reintentará el envío", conserva lo capturado y reintenta solo cuando vuelve la señal o al pulsar **Reintentar** (RN-11a).
5. **Finalizar**
   - Un campo numérico por cada concepto de resultado, con 0 por defecto y botones − y +.
   - Observaciones y número de nota informativa, ambos opcionales.
   - Al final pide confirmación.

## Rol SUPERVISOR (General)

- Su pantalla principal es el **mapa**. El menú tiene Mapa y Tablero.
- El mapa tiene además una **vista de lista** con los mismos filtros, que funciona aunque falte la clave de Google Maps o no cargue el mapa.
- Si `GOOGLE_MAPS_MAP_ID` está configurado, se usan marcadores avanzados con el nombre corto de la unidad; si no, círculos de color con la etiqueta debajo.

1. **Mapa (pantalla principal)**
   - Ocupa toda la pantalla y muestra la fecha de servicio vigente.
   - Marcadores coloreados por estado, cada uno con el nombre corto de la unidad. Si la última ubicación es manual, el marcador lleva el distintivo "Manual".
   - Filtros por turno y por división. Los FINALIZADOS se pueden mostrar u ocultar.
   - Se actualiza cada 30 s e indica "Actualizado hace X s".
   - Una **barra de alertas** muestra cuántos operativos están NO_INICIADO y SIN_CIERRE. Al tocarla, despliega la lista con el lugar programado, porque los NO_INICIADO aún no tienen ubicación.
2. **Ficha del operativo**: se abre como panel inferior al tocar un marcador.
   - Muestra unidad, tipos, plan, jefe, fuerzas, horario programado y real, lugar, última actualización ("hace 12 min") y recorrido.
   - Botones: **Cómo llegar** (`https://www.google.com/maps/dir/?api=1&destination=LAT,LNG`), **Llamar al jefe** (`tel:`) y **Registrar control**.
3. **Registrar control inopinado**
   - Intenta capturar el GPS del supervisor. Si no lo consigue, permite ingresar las coordenadas manualmente o guardar sin ubicación (RN-15).
   - Pide el resultado: CONFORME, CON OBSERVACIONES o NO ENCONTRADO.
   - Pide una observación.
   - Al guardar, muestra la distancia calculada al último punto del operativo.
4. **Tablero de cumplimiento**
   - Matriz de **unidades activas × 4 turnos** de la fecha elegida (por defecto, el servicio diario vigente), con flechas para el día anterior y el siguiente y filtro por división. Cada celda tiene el color de su estado y abre la ficha.
   - Una celda sin operativo es SIN_PROGRAMAR (amarillo) cuando ya pasó la última hora de inicio posible del turno; antes se muestra "—", porque todavía puede programarse.
   - Arriba, los totales: ejecutados (en curso, sin cierre y finalizados) sobre el total de turnos, y la cantidad por estado.
   - También lo ve ADMIN, porque no usa la API de Google Maps.
   - Sirve para ver de un vistazo quién no programó o no inició.

## Rol ADMIN

- **Unidades** (`/admin/unidades`): crear, editar, activar y desactivar, y fijar el orden. La lista se agrupa por división y avisa qué unidades no tienen cuenta.
- **Usuarios** (`/admin/usuarios`): crear, asignar rol y unidad, resetear clave, desbloquear y activar o desactivar.
  - Al crear una cuenta, la clave es opcional: si se deja vacía, el sistema genera una de 12 caracteres sin letras confusas (sin 0/o ni 1/l/i) y la muestra **una sola vez**. Al resetear la clave pasa lo mismo.
  - En la lista de unidades para una cuenta UNIDAD, las que ya tienen cuenta aparecen deshabilitadas (RN-20).
  - Un ADMIN no puede quitarse su propio rol ni desactivar su propia cuenta, y siempre debe quedar al menos un ADMIN activo.
- **Catálogos** (`/admin/catalogos`): tipos de operativo y conceptos de resultado. Los nombres se guardan en mayúsculas. No se eliminan, porque los operativos los referencian: se desactivan, y dejan de aparecer en los formularios nuevos.
- **Operativos** (`/admin/operativos`): búsqueda por fechas de servicio (por defecto, 7 días antes y después del vigente), unidad, turno y estado visible (incluidos no iniciado, sin cierre y finalizado sin nota). La ficha de corrección permite cambiar todo: la programación (con el mismo formulario de la unidad, pero sin bloqueos ni fecha mínima), el estado y las horas reales, la nota y los resultados. Pide el motivo. También permite **programar en nombre de una unidad**, incluso con la hora pasada, y **eliminar** con motivo.
- **Inicio**: resumen del servicio diario vigente (cantidad por estado, alertas de no iniciados y sin cierre) y accesos a finalizados sin nota, unidades sin cuenta y cuentas bloqueadas.
- **Bitácora** (`/admin/bitacora`): consulta filtrada por usuario, acción y rango de fechas (por defecto, los últimos 7 días), con 50 registros por página. Las ediciones muestran cada campo como "antes → después".
