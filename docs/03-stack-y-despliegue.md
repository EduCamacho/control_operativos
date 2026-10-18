# 03 · Stack y despliegue

## Decisión: PHP plano en hosting compartido con cPanel (sin VPS)

El sistema es pequeño: unos 30 usuarios, como máximo 26 operativos por turno y unos cientos de puntos al día. Por eso se usa el stack más simple que cumple:

| Capa | Elección | Por qué |
|---|---|---|
| Backend | **PHP 8.2+ plano**: PDO, sesiones nativas, un front controller | Sin framework ni Composer en producción. Se despliega subiendo archivos. |
| Base de datos | MySQL 8 o MariaDB 10.6+ del hosting | Se administra con phpMyAdmin. |
| Frontend | **Bootstrap 5.3 + Alpine.js 3 + Bootstrap Icons**, con copias locales en `public/assets/vendor/` | No depende de CDN en campo. Alpine da la reactividad sin compilar nada. |
| Mapa | **Google Maps JavaScript API, solo en las pantallas del SUPERVISOR** (RN-21) | Ya se conoce. El resto de vistas usa enlaces a Google Maps, que no consumen la API, así que el uso queda muy por debajo de las 10 000 cargas gratis al mes. |
| Ubicación | API Geolocation del navegador (`enableHighAccuracy: true`, `maximumAge: 0`, `timeout: 15000`) | Requiere HTTPS. |
| "Tiempo real" | **Consulta periódica (polling) cada 30 s** a `/api/mapa` (JSON) | En hosting compartido no hay procesos persistentes ni websockets, y para 26 marcadores no hace falta más. |
| HTTPS | AutoSSL de cPanel (Let's Encrypt) | Sin HTTPS el navegador no entrega el GPS. |

**Cuándo pasar a un VPS**: si se necesitan websockets, colas o tareas pesadas en segundo plano, si se alojan varios sistemas institucionales juntos, o si se exige que los datos estén en un servidor dedicado o institucional. Por ahora no aplica.

## Estructura de carpetas

```
control-operativos/
├── CLAUDE.md
├── docs/                      # estos documentos (no se suben al hosting)
├── sql/                       # 001_esquema.sql, 002_datos_iniciales.sql, 003_... (cambios numerados)
├── herramientas/              # uso local, NO se suben: crear_admin.php, empaquetar.php
├── tests/                     # PHPUnit, solo en local
├── app/                       # FUERA del document root
│   ├── config.php             # credenciales BD, API key, tolerancias (no versionar; usar config.ejemplo.php)
│   ├── inicio.php             # arranque: config, zona horaria, errores, e(), armar_url(), responder_json()
│   ├── rutas.php              # lista blanca de rutas → archivo de paginas/ o api/
│   ├── db.php                 # conexión PDO + SET time_zone = '-05:00'
│   ├── sesion.php             # configuración de sesión, login, CSRF
│   ├── autorizacion.php       # requiere_rol(), unidad_de_sesion()
│   ├── dominio.php            # funciones puras (ver "Lógica de dominio")
│   ├── bitacora.php
│   ├── formularios.php        # ayudas para formularios (errores, marcar, ids de la URL)
│   ├── operativos.php         # consultas de operativos compartidas por páginas y API
│   ├── reportes.php           # datos de los reportes y su PDF (Dompdf)
│   ├── lib/dompdf/            # Dompdf 3.1.6 con sus dependencias incluidas (paquete oficial, sin Composer)
│   ├── iconos/                # íconos SVG de los conceptos de resultado (Tabler Icons, MIT)
│   ├── paginas/               # un archivo por pantalla/acción (controlador + vista)
│   ├── api/                   # endpoints JSON: ubicaciones, finalizar, coordenadas (y mapa en la fase 3)
│   ├── vistas/                # layout, parciales
│   └── almacen/               # sesiones/ (session.save_path propio) y registros/errores.log
└── public/                    # DOCUMENT ROOT del subdominio
    ├── index.php              # front controller: enruta ?r= o path → app/paginas/*
    ├── .htaccess              # reescritura a index.php, fuerza HTTPS
    └── assets/ (css, js, img/logo.png = escudo de la PNP del ingreso y los reportes, vendor/)  # js/envio.js (reintento sin conexión), js/ubicacion.js (GPS e ingreso manual)
```

En cPanel, crear un subdominio (por ejemplo `operativos.tudominio.com`) con el **document root apuntando a `control-operativos/public`**, de modo que `app/` quede fuera del alcance web.

## Configuración crítica en hosting compartido

- **Sesiones de hasta 12 horas sin actividad (RN-18).** En un hosting compartido, la limpieza de sesiones de otros sitios del servidor puede borrar las tuyas a los ~24 minutos. Por eso hay que usar una carpeta propia y fijar los tiempos antes de `session_start()`:
  ```php
  ini_set('session.save_path', __DIR__.'/almacen/sesiones');
  ini_set('session.gc_maxlifetime', 43200);
  session_set_cookie_params(['lifetime'=>43200,'secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
  ```
- **Zona horaria**: `date_default_timezone_set('America/Lima')` en PHP y `SET time_zone = '-05:00'` al abrir cada conexión PDO. El servidor del hosting suele estar en UTC o en hora de EE. UU.
- **Versión de PHP**: elegir 8.2 o superior en el "MultiPHP Manager" de cPanel.
- **API key de Google Maps** (en Google Cloud):
  - Restringida por *HTTP referrer* al subdominio, y con solo "Maps JavaScript API" habilitada.
  - **Cuota diaria**, por ejemplo 300 cargas por día, para que un abuso de la clave se corte en lugar de generar cobros.
  - **Alerta de presupuesto**, por ejemplo de US$ 1.
  - Si se usan `AdvancedMarkerElement`, hay que crear un *Map ID*.
  - La clave solo se incluye en las páginas del SUPERVISOR.

## Seguridad (obligatorio)

- Todas las consultas con **PDO y sentencias preparadas**, sin concatenar SQL.
- **Escapar toda salida** con el helper `e()` (`htmlspecialchars`).
- **Token CSRF** en todos los POST.
- `password_hash()` y `password_verify()`, `session_regenerate_id(true)` al ingresar, y espera creciente de la cuenta desde el 5.º intento fallido (RN-19).
- **Defensas del ingreso** (RN-19a, `procesar_ingreso()`), en este orden: límite por IP (tabla `intentos_ingreso`), campo trampa para bots, Cloudflare Turnstile y, al final, usuario y clave.
- **IP real** con `obtener_ip_cliente()`: detrás de Cloudflare se toma `CF-Connecting-IP`, pero solo si la petición llega desde un rango de Cloudflare (`RANGOS_CLOUDFLARE` en `app/dominio.php`; revisar https://www.cloudflare.com/ips/ si Cloudflare los cambia). La bitácora usa esa IP.
- **Cabeceras**: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS en producción, `X-Robots-Tag: noindex` (y `public/robots.txt`), y la **CSP** de `armar_politica_contenido()`. La CSP empieza en modo informe (`CSP_APLICAR = false`): revisar la consola del navegador en el ingreso, las pantallas de la unidad y el mapa del supervisor, y recién entonces poner `true`.
- **Toda consulta de una cuenta UNIDAD se filtra por `unidad_de_sesion()` en el servidor (RN-16)**. Nunca se confía en un `unidad_id` que llegue del navegador.
- Las horas las pone el servidor (RN-11). Del navegador solo se aceptan latitud, longitud y precisión, y se validan con `dentro_de_la_libertad()`.
- Se registra en bitácora cada acción sobre operativos, usuarios y controles.

## Cloudflare (delante del servidor)

El dominio pasa por Cloudflare (plan gratuito). Configuración en el panel de Cloudflare:

1. **DNS**: el registro del subdominio con la nube **naranja** (proxy activado).
2. **SSL/TLS**: modo **Full (strict)**. En cPanel, el certificado de AutoSSL sigue siendo necesario.
3. **Turnstile**: en *Turnstile → Add widget*, el dominio del sistema y el modo **Managed**. Copiar la *Site key* y la *Secret key* en `TURNSTILE_SITE_KEY` y `TURNSTILE_SECRET_KEY` de `app/config.php`. La secreta no se comparte ni se versiona. Son dos valores **distintos**.
   - Las claves reales solo funcionan en los dominios del widget. **En local** se usan las claves de prueba de Cloudflare (ver `app/config.ejemplo.php`).
   - Si la verificación no carga, el ingreso muestra un aviso con un **código para soporte**: `110200` = dominio no autorizado en el widget (o claves reales en local); `sin-conexion` = el script de Cloudflare no llegó a cargar (bloqueador o sin red); otros códigos, ver la documentación de Turnstile.
4. **Bloqueo por país** (decisión de Eduardo, 18/10/2026): en *Security → WAF → Custom rules*, regla "Solo Perú": si `ip.src.country ne "PE"`, acción **Block**. Quien esté fuera del Perú no puede ingresar.
5. **Límite de peticiones al ingreso**: en *Security → WAF → Rate limiting rules*, si la ruta es `/login` y el método `POST`, más de 10 peticiones en 1 minuto por IP → **Block** durante 10 minutos. Es un primer filtro; el sistema tiene además su propio límite (RN-19a).
6. **Bots**: dejar **Bot Fight Mode desactivado**. Responde con desafíos de JavaScript que los envíos del celular (`fetch()` a `/api/`, puntos y cierres) no pueden resolver, y en el plan gratuito no se puede excluir esa ruta: el jefe vería "No se pudo guardar". Los bots ya los frenan Turnstile, el límite por IP, el campo trampa y las reglas de los pasos 4 y 5. Si se quiere probar, hacerlo un día y verificar desde celulares reales con datos móviles que se registren puntos y se finalice.
7. **Origen solo para Cloudflare** (recomendado): que el servidor solo acepte conexiones desde las IP de Cloudflare, para que nadie se salte estas defensas llamando directo a la IP del hosting. En cPanel no siempre se puede en el firewall; se puede con un `Require ip` de los rangos de Cloudflare en `public/.htaccess`. Probarlo primero, porque un error deja el sistema fuera de línea.

## Lógica de dominio (`app/dominio.php`, con tests)

Son funciones puras, sin acceso a la base de datos, y cada una tiene sus tests en PHPUnit:

- `clasificar_turno(string $horaInicio): string`: devuelve `MANANA`, `TARDE`, `NOCHE` o `MADRUGADA` según la hora de inicio (RN-02).
- `calcular_horario_turno(string $fecha, string $horaInicio, string $horaFin): array`: devuelve `[turno, inicio, fin]`, con la MADRUGADA al día siguiente y el cruce de medianoche (RN-02).
- `fecha_servicio_vigente(DateTimeImmutable $ahora): string`: hoy desde las 06:00 y ayer antes de esa hora.
- `dentro_de_la_libertad(float $lat, float $lng): bool` (RN-08).
- `parsear_coordenadas(string $texto): ?array`: separa en `[lat, lng]` lo que se copió de Google Maps (decimal, URL con `@` o grados, minutos y segundos) y devuelve `null` si no lo reconoce (RN-09).
- `distancia_m(float $lat1, float $lng1, float $lat2, float $lng2): int`: fórmula de Haversine (RN-15).
- `estado_visible(array $operativo, DateTimeImmutable $ahora): string`: devuelve `NO_INICIADO`, `SIN_CIERRE` o el estado guardado (RN-14). `pendiente_nota(array $operativo): bool` aparte, porque es un distintivo.
- `puede_iniciar()`, `puede_eliminar()`, `campos_editables(string $estado, bool $paso_hora_inicio): array` (RN-05, RN-06, RN-07).
- `normalizar_nota_informativa(string $texto): ?string`: deja solo los dígitos (RN-13).
- `programacion_permitida(DateTimeImmutable $inicio, DateTimeImmutable $ahora, bool $es_admin): bool`: el inicio debe ser futuro, salvo para ADMIN (RN-03).

## Desarrollo en local

- **Laragon** con PHP 8.2 o superior: en Laragon, *Menú → PHP → versión 8.3*, o llamar directamente a `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` (el `php` del PATH puede ser 8.1).
- **Tests**: Composer se usa **solo en local**: `composer install` y luego `vendor/bin/phpunit`. La carpeta `vendor/` no se sube.
- **Servidor de prueba** sin configurar Apache: `php -S 127.0.0.1:8000 -t public public/index.php`, con `ENTORNO = 'local'` en `app/config.php`. El navegador entrega el GPS en `127.0.0.1` sin HTTPS; desde el celular por la red local, no.
- **Clave de Google Maps**: usar una clave aparte para local (restringida a `http://127.0.0.1:8000/*`), distinta de la de producción.

## Despliegue en cPanel (sin SSH)

### Requisitos del hosting

- PHP 8.2 o superior (*MultiPHP Manager*), con las extensiones `pdo_mysql`, `mbstring` y `json`, que vienen activas casi siempre. No hace falta `intl` ni `zip` en el servidor.
- MySQL 8 o MariaDB 10.6+, phpMyAdmin y AutoSSL (Let's Encrypt).
- Un subdominio, por ejemplo `operativos.tudominio.com`.

### Estructura en el servidor

```
/home/USUARIO/control_operativos/      ← se extrae aquí el .zip
├── app/                               ← fuera del alcance web
│   ├── config.php                     ← se crea una sola vez en el servidor
│   └── almacen/ (sesiones/, registros/)
└── public/                            ← DOCUMENT ROOT del subdominio
```

### Primera instalación

1. **Paquete**: en local, `php herramientas/empaquetar.php`. Corre los tests y arma `dist/control_operativos_FECHA_COMMIT.zip` solo con los archivos versionados de `app/` y `public/` (nunca `config.php`, sesiones ni registros).
2. **Subdominio**: en *Dominios* (o *Subdominios*), crear `operativos.tudominio.com` con el **document root en `control_operativos/public`**.
3. **Base de datos**: en *Bases de datos MySQL*:
   - Crear la base (por ejemplo `usuario_operativos`) y un usuario con clave larga.
   - Dar al usuario solo **SELECT, INSERT, UPDATE y DELETE** sobre esa base. Los cambios de estructura se aplican desde phpMyAdmin con la cuenta de cPanel, no con este usuario.
4. **Esquema**: en phpMyAdmin, elegir la base e importar **en orden** `sql/001_esquema.sql`, `002_datos_iniciales.sql`, `003_madrugada_origen_sesion.sql`, `004_id_envio_ubicaciones.sql`, `005_id_envio_controles.sql`, `006_coordenadas_lugar_programado.sql`, `007_quitar_nombre_plan.sql`, `008_varios_operativos_por_turno.sql`, `009_dispositivo_distancia_ubicaciones.sql`, `010_referencia_opcional_ubicaciones.sql`, `011_intentos_ingreso.sql` y `012_icono_conceptos.sql`. Anotar en la tabla de abajo cuáles se aplicaron.
5. **Archivos**: en el *Administrador de archivos*, crear `/home/USUARIO/control_operativos/`, subir el `.zip` ahí y usar *Extract*. Borrar el `.zip` después.
6. **Configuración**: copiar `app/config.ejemplo.php` como `app/config.php` y completar:
   - `ENTORNO = 'produccion'` y `URL_BASE = ''`.
   - `BD_HOST` (normalmente `localhost`), `BD_NOMBRE`, `BD_USUARIO` y `BD_CLAVE` del paso 3.
   - `GOOGLE_MAPS_API_KEY` y `GOOGLE_MAPS_MAP_ID` de producción.
   - Tolerancias (15, 30 y 60 min), `SESION_INACTIVIDAD_S` y `SESION_MAXIMA_S` por rol (RN-18; si faltan, se usan los valores de `config.ejemplo.php`) y `DURACION_SESION_S` (43200 = 12 h: vida de la cookie, al menos la mayor inactividad).
   - Cloudflare Turnstile: `TURNSTILE_SITE_KEY` y `TURNSTILE_SECRET_KEY` (ver "Cloudflare"), y `CSP_APLICAR` (empieza en `false`).
   - Ayuda de las unidades: `SOPORTE_NOMBRE`, `SOPORTE_CELULAR`, `SOPORTE_WHATSAPP` y `VIDEO_TUTORIAL_UNIDAD_URL` (video de YouTube subido como "No listado"). Si un `config.php` ya creado no las tiene, el sistema las toma vacías y la Ayuda indica consultar al administrador; para completarlas, agregarlas a mano en el servidor.
7. **Permisos**: `app/almacen/sesiones/` y `app/almacen/registros/` deben poder escribirse por PHP (755 suele bastar en cPanel). `app/config.php` con 600 o 640.
8. **PHP**: en *MultiPHP Manager*, elegir 8.2 o superior para el subdominio.
9. **HTTPS**: en *SSL/TLS Status*, ejecutar AutoSSL para el subdominio y comprobar que `http://operativos.tudominio.com` redirige a `https://` (lo fuerza `public/.htaccess`).
10. **Primer ADMIN**: en local, `php herramientas/crear_admin.php usuario "clave larga" "Nombre completo"`. Copiar el `INSERT` que imprime y ejecutarlo en phpMyAdmin (pestaña *SQL*). Borrar el comando del historial de la terminal.
11. **Google Maps** (Google Cloud Console), para la clave de producción:
    - *Restricción de aplicaciones*: **Sitios web (HTTP referrer)** con `https://operativos.tudominio.com/*`.
    - *Restricción de API*: solo **Maps JavaScript API**.
    - **Cuota diaria** de cargas de mapa (por ejemplo 300) y **alerta de presupuesto** (por ejemplo US$ 1).
    - Un **Map ID** de tipo JavaScript (vectorial) para los marcadores avanzados.
12. **Comprobación** (lista de abajo).

### Actualizaciones

1. En local: commit de los cambios y `php herramientas/empaquetar.php`.
2. Si hay archivos `sql/NNN_*.sql` nuevos, aplicarlos en phpMyAdmin **antes** de subir el paquete, en orden y una sola vez (ver la tabla).
3. Subir el `.zip` a `/home/USUARIO/control_operativos/` y extraerlo **encima** (sobrescribir). El paquete no trae `app/config.php`, sesiones ni registros, así que no los toca.
4. Si se borró un archivo del proyecto, borrarlo también a mano en el servidor (extraer encima no elimina archivos viejos).
5. Comprobar que la **versión** al pie del menú lateral coincide con el commit del paquete.

Las sesiones abiertas se mantienen durante la actualización: no hace falta que las unidades vuelvan a ingresar.

### Comprobación después de instalar o actualizar

- [ ] `http://` redirige a `https://` y el candado del navegador es válido.
- [ ] `https://operativos.tudominio.com/app/config.php` responde "No encontramos esta página": `app/` está fuera del document root y no tiene URL.
- [ ] El ADMIN ingresa y ve la versión correcta al pie del menú.
- [ ] La hora que muestra el inicio coincide con la de Lima.
- [ ] El SUPERVISOR ve el mapa cargado (sin el aviso de la clave) y la vista de lista.
- [ ] Desde un celular con la cuenta de una unidad: programar, **iniciar con GPS**, registrar un punto, probar el ingreso manual y finalizar.
- [ ] Con el celular en modo avión al registrar un punto: aparece "Sin conexión" y el punto se envía solo al volver la señal.
- [ ] `app/almacen/registros/errores.log` sigue vacío.

### Respaldos

No hay tareas programadas (cron), así que el respaldo es manual: al menos una vez por semana, en phpMyAdmin, *Exportar* la base completa (formato SQL) y guardarla fuera del hosting. Si el plan de cPanel incluye *Copias de seguridad*, activarlas también.

### Registro de cambios de base de datos aplicados en producción

| Archivo | Fecha de aplicación | Quién |
|---|---|---|
| `001_esquema.sql` | | |
| `002_datos_iniciales.sql` | | |
| `003_madrugada_origen_sesion.sql` | | |
| `004_id_envio_ubicaciones.sql` | | |
| `005_id_envio_controles.sql` | | |
| `006_coordenadas_lugar_programado.sql` | | |
| `007_quitar_nombre_plan.sql` | | |
| `008_varios_operativos_por_turno.sql` | | |
| `009_dispositivo_distancia_ubicaciones.sql` | | |
| `010_referencia_opcional_ubicaciones.sql` | | |
| `011_intentos_ingreso.sql` | | |
| `012_icono_conceptos.sql` | | |

## Dompdf (reportes en PDF)

- Está en `app/lib/dompdf/`, versionado en Git, para que `empaquetar.php` lo suba sin Composer. Es el paquete oficial que ya incluye sus dependencias (`dompdf-X.Y.Z.zip` de https://github.com/dompdf/dompdf/releases), cargado con `autoload.inc.php`.
- Requiere las extensiones `dom` y `mbstring` (cPanel las trae casi siempre).
- Configuración en `enviar_pdf()` (`app/reportes.php`): sin PHP embebido ni archivos remotos, `chroot` en `app/` y estilos de impresión (`defaultMediaType = print`).
- **Actualizar**: descargar el zip nuevo, reemplazar la carpeta `app/lib/dompdf/`, generar un reporte de prueba y revisar el PDF.
