# 04 · Pantallas y diseño

## Principios

- **Pensado primero para el celular.** El jefe usa el sistema en campo, a veces de noche y con una sola mano, y el General lo usa desde la camioneta.
- Botones de acción grandes (mínimo 48 px de alto, de ancho completo en el celular).
- **Menú en barra lateral izquierda**: fija en pantallas grandes (desde 992 px) y, en el celular, panel deslizable (offcanvas) que se abre con el botón ☰ de la barra superior, para no quitarle pantalla al jefe en campo.
- Bootstrap 5.3 con tema claro. La tipografía es la del sistema (`system-ui`): carga rápido y se lee bien.
- **Cambiar clave** (`/cuenta/clave`), para todas las cuentas, con el enlace al pie del menú lateral. Pide la clave actual, la nueva y su confirmación, y ofrece "Cerrar la sesión en los demás dispositivos" (desmarcado por defecto). En la cuenta de una unidad recuerda avisar la clave nueva a quienes la usan.
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

`PENDIENTE_NOTA` no tiene color propio: se muestra como un distintivo "Sin N.I" (sin nota informativa) sobre el azul de FINALIZADO.

## Ubicaciones fuera del mapa del General

El mapa interactivo solo existe en las pantallas del SUPERVISOR (RN-21). En las vistas de UNIDAD y ADMIN, cada ubicación se muestra como texto ("Av. España con Jr. Grau · 16:52 · precisión 8 m") junto a un botón o enlace **Ver en Google Maps** (`https://www.google.com/maps/search/?api=1&query=LAT,LNG`, en una pestaña nueva). Esas páginas no cargan la API de Google Maps.

## Rol UNIDAD

1. **Mis operativos (inicio)**
   - Encabezado con el nombre de la unidad y el botón **Programar** a la derecha. Debajo, el título de sección **Servicio diario · Sábado 17/10 · 13:20** sobre los turnos y **Otros días** sobre las pestañas, para dar contexto en el celular, donde no se ve el menú.
   - Los **4 turnos** del servicio diario vigente (MAÑANA, TARDE, NOCHE y MADRUGADA), en una columna en el celular y en cuatro en la PC, con el borde izquierdo del color del estado. El **turno actual** se resalta con "● AHORA".
     - Turno vacío: una sola línea con el rango de inicio y **+ Programar**; si ya no se puede programar, "Sin programar" en amarillo.
     - Turno con operativos: **uno o más** (RN-01), cada uno con su horario, lugar, jefe, estado y **un solo botón** con la acción que toca: *Iniciar operativo*, *Nuevo punto / Finalizar* (verde institucional) o *Agregar N.I* (nota informativa; contorno). Si el turno tiene varios, lo indica ("2 operativos"); mientras se pueda programar, ofrece **+ Agregar otro operativo en este turno**.
   - Si un operativo del servicio anterior sigue en curso, aparece arriba como aviso naranja con su acción.
   - Debajo, pestañas **Próximos** (con la cantidad) e **Historial**, con los operativos agrupados por día ("Domingo 18/10"). El historial muestra los últimos 7 días con operativos y **Ver más** llega hasta 30.
2. **Programar / editar**: asistente en **4 pasos**, con la barra de progreso arriba y los botones **Atrás** y **Siguiente** fijos al pie, al alcance del pulgar.
   1. **Cuándo**: fecha de servicio, hora de inicio y hora de fin (24 horas). El turno no se elige: se muestra calculado al escribir la hora, junto con el día y la hora reales ("MADRUGADA · empieza el sáb 18/10 a las 02:00"). Si la unidad ya tiene operativos en ese turno, lo informa sin impedir programar otro.
   2. **Qué y dónde**: arriba, los tipos de operativo como casillas (fila completa tocable, una columna en el celular y dos en la PC); debajo, la tarjeta destacada **¿Dónde será el operativo?** con la dirección o referencia y las **coordenadas del lugar** (obligatorias: el campo lleva pegado a su derecha el botón **Buscar**, que abre Google Maps con el lugar escrito; debajo, un recuadro "¿Cómo obtener las coordenadas?" con la lista de 4 pasos: buscar, mantener presionado el punto, copiar y pegar).
   3. **Jefe y fuerzas**, en dos tarjetas (**Jefe del operativo** y **Fuerzas**): grado (lista desplegable), nombres y celular, que se completan con el jefe del último operativo de la unidad; fuerzas propias, de apoyo y unidades móviles con botones − y +; observaciones.
   4. **Revisar**: resumen de los tres bloques, cada uno con **Cambiar**, y el botón **Programar operativo**.
   - Cada paso se valida antes de avanzar; el servidor vuelve a validar todo y, si rechaza algo, el asistente abre el paso con el error.
   - **Al editar** abre directo en **Revisar**. Los bloques que el estado no permite cambiar (RN-05) muestran "No se puede cambiar"; en curso solo se cambia el bloque 3.
   - El formulario del ADMIN usa los mismos bloques, pero en una sola página.
3. **Ficha del operativo** (`/operativos/ver`)
   - **Encabezado** con la unidad, el estado, el turno y el horario, con el borde izquierdo del color del estado; debajo, la hora de inicio real y hace cuánto.
   - **En curso**: tarjeta **Posición actual** (la referencia en grande, hora, hace cuánto, precisión o "Manual" y **Ver en Google Maps**). Debajo, dos botones grandes: **Registrar nuevo punto** (verde institucional) y **Finalizar operativo** (contorno rojo). No hay mapa (RN-21).
   - **Programado**: botón **Iniciar operativo**, o el aviso "Podrá iniciarlo desde las HH:MM".
   - **Finalizado**: **Resultados** como recuadros con la cifra grande (o "Negativo") y la **Nota informativa**, con el campo para agregarla si falta.
   - **Recorrido** como línea de tiempo vertical, el punto más reciente arriba y resaltado, el primero marcado como "Inicio". Muestra los 5 más recientes y el botón **Ver los N puntos anteriores** despliega el resto. En el celular es la **última tarjeta** (después de los datos del operativo), porque puede crecer mucho.
   - **Datos del operativo** con ícono por dato (tipos, lugar programado con su enlace, jefe con el botón **Llamar** y fuerzas en tres recuadros con la cifra), y **Editar** en el encabezado de la tarjeta. **Eliminar** va al pie, discreto, solo cuando se permite (RN-06).
   - En la PC, dos columnas: a la izquierda la acción y el recorrido; a la derecha los datos.
4. **Captura de ubicación** (al iniciar y al registrar un nuevo punto), en un **modal**: hoja inferior en el celular (sale desde abajo, hasta el 85 % de la altura) y centrado en la PC, con **Cancelar** y el botón de envío fijos al pie. Es estático (no se cierra al tocar fuera ni con Escape) y no se puede cerrar mientras hay un envío pendiente, para no perder la ubicación capturada.
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
   - Los operativos que todavía no registran puntos (programados y no iniciados) se marcan en su **lugar programado**, con un marcador de fondo blanco y borde punteado del color de su estado, para no confundirlos con una posición real. La leyenda lo explica.
   - Filtros por turno y por división. Los FINALIZADOS se pueden mostrar u ocultar.
   - Se actualiza cada 30 s e indica "Actualizado hace X s".
   - Una **barra de alertas** muestra cuántos operativos están NO_INICIADO y SIN_CIERRE. Al tocarla, despliega la lista con el lugar programado, porque los NO_INICIADO aún no tienen ubicación.
2. **Ficha del operativo**: se abre como panel inferior al tocar un marcador. En vez de la lista del recorrido tiene el botón **Ver recorrido (N puntos)**: oculta la ficha y encuadra el mapa sobre el recorrido (línea **magenta** `#C2185B` con borde blanco y flechas de dirección; el magenta no lo usa ningún estado ni el mapa de Google), con los puntos numerados del mismo color (tocar uno muestra su hora y referencia) y una barra **← Volver a la ficha** / **✕**. Sin mapa (vista Lista o página de la ficha), el botón despliega la lista de puntos.
   - Muestra unidad, tipos, plan, jefe, fuerzas, horario programado y real, lugar, última actualización ("hace 12 min") y recorrido.
   - Botones: **Cómo llegar** (`https://www.google.com/maps/dir/?api=1&destination=LAT,LNG`), **Llamar al jefe** (`tel:`) y **Registrar control**.
3. **Registrar control inopinado** (`/supervisor/control`), con poco texto:
   - **← Mapa** y el encabezado del operativo (unidad, estado, turno y horario) con el borde del color del estado.
   - Una línea con el estado del GPS del supervisor: "Obteniendo su ubicación…", "Su ubicación ✓" (con **Quitar**) o "Sin ubicación" (con **Reintentar** · **Manual** para pegar coordenadas). Es opcional (RN-15).
   - **Resultado**: tres botones grandes en una fila, con ícono: ✓ Conforme (verde), ⚠ Con observaciones (amarillo) y ✕ No encontrado (rojo).
   - **Observación** (opcional si es Conforme) y **Guardar control** fijo al pie.
   - Al guardar: ✓ **Control registrado** con la distancia al último punto en cifra grande y **Volver al mapa**.
   - Al pie, **Controles anteriores** del operativo (resultado, hora y distancia; el nombre del supervisor solo si es otro). Los controles se ven aquí, donde se registran, y no en la ficha del mapa. El recién registrado aparece arriba de inmediato, resaltado, sin recargar la página.
4. **Tablero de cumplimiento**
   - Matriz de **unidades activas × 4 turnos** de la fecha elegida (por defecto, el servicio diario vigente), con flechas para el día anterior y el siguiente y filtro por división. Cada celda tiene el color de su estado y abre la ficha.
   - Una celda sin operativo es SIN_PROGRAMAR (amarillo) cuando ya pasó la última hora de inicio posible del turno; antes se muestra "—", porque todavía puede programarse.
   - Cada celda muestra **todos** los operativos de esa unidad y turno, uno debajo del otro. Arriba, los totales: turnos con al menos un operativo sobre el total de turnos, cantidad de operativos y ejecutados (en curso, sin cierre y finalizados), y la cantidad por estado.
   - También lo ve ADMIN, porque no usa la API de Google Maps.
   - Sirve para ver de un vistazo quién no programó o no inició.

## Rol ADMIN

- **Unidades** (`/admin/unidades`): crear, editar, activar y desactivar, y fijar el orden. La lista se agrupa por división y avisa qué unidades no tienen cuenta.
- **Usuarios** (`/admin/usuarios`): crear, asignar rol y unidad, resetear clave, desbloquear y activar o desactivar.
  - Al crear una cuenta, la clave es opcional: si se deja vacía, el sistema genera una de 12 caracteres sin letras confusas (sin 0/o ni 1/l/i) y la muestra **una sola vez**. Al resetear la clave pasa lo mismo.
  - En la lista de unidades para una cuenta UNIDAD, las que ya tienen cuenta aparecen deshabilitadas (RN-20).
  - Un ADMIN no puede quitarse su propio rol ni desactivar su propia cuenta, y siempre debe quedar al menos un ADMIN activo.
- **Catálogos** (`/admin/catalogos`): tipos de operativo y conceptos de resultado. Los nombres se guardan en mayúsculas. No se eliminan, porque los operativos los referencian: se desactivan, y dejan de aparecer en los formularios nuevos.
- **Operativos** (`/admin/operativos`): búsqueda por fechas de servicio (por defecto, 7 días antes y después del vigente), unidad, turno y estado visible (incluidos no iniciado, sin cierre y finalizado sin N.I). La ficha de corrección permite cambiar todo: la programación (con el mismo formulario de la unidad, pero sin bloqueos ni fecha mínima), el estado y las horas reales, la nota y los resultados. Pide el motivo. También permite **programar en nombre de una unidad**, incluso con la hora pasada, y **eliminar** con motivo.
- **Inicio**: resumen del servicio diario vigente (cantidad por estado, alertas de no iniciados y sin cierre) y accesos a finalizados sin N.I, unidades sin cuenta y cuentas bloqueadas.
- **Bitácora** (`/admin/bitacora`): consulta filtrada por usuario, acción y rango de fechas (por defecto, los últimos 7 días), con 50 registros por página. Las ediciones muestran cada campo como "antes → después".
