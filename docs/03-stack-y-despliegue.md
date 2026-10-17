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
├── herramientas/              # scripts de uso local (crear_admin.php) — NO se suben
├── tests/                     # PHPUnit, solo en local
├── app/                       # FUERA del document root
│   ├── config.php             # credenciales BD, API key, tolerancias (no versionar; usar config.ejemplo.php)
│   ├── db.php                 # conexión PDO + SET time_zone = '-05:00'
│   ├── sesion.php             # configuración de sesión, login, CSRF
│   ├── autorizacion.php       # requiere_rol(), unidad_de_sesion()
│   ├── dominio.php            # funciones puras (ver "Lógica de dominio")
│   ├── bitacora.php
│   ├── paginas/               # un archivo por pantalla/acción (controlador + vista)
│   ├── api/                   # endpoints JSON (mapa, ubicaciones)
│   ├── vistas/                # layout, parciales
│   └── almacen/sesiones/      # session.save_path propio
└── public/                    # DOCUMENT ROOT del subdominio
    ├── index.php              # front controller: enruta ?r= o path → app/paginas/*
    ├── .htaccess              # reescritura a index.php, fuerza HTTPS
    └── assets/ (css, js, img, vendor/)
```

En cPanel, crear un subdominio (por ejemplo `operativos.tudominio.com`) con el **document root apuntando a `control-operativos/public`**, de modo que `app/` quede fuera del alcance web.

## Configuración crítica en hosting compartido

- **Sesiones de 12 horas (RN-18).** En un hosting compartido, la limpieza de sesiones de otros sitios del servidor puede borrar las tuyas a los ~24 minutos. Por eso hay que usar una carpeta propia y fijar los tiempos antes de `session_start()`:
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
- `password_hash()` y `password_verify()`, `session_regenerate_id(true)` al ingresar, y bloqueo tras 5 intentos fallidos (RN-19).
- **Toda consulta de una cuenta UNIDAD se filtra por `unidad_de_sesion()` en el servidor (RN-16)**. Nunca se confía en un `unidad_id` que llegue del navegador.
- Las horas las pone el servidor (RN-11). Del navegador solo se aceptan latitud, longitud y precisión, y se validan con `dentro_de_la_libertad()`.
- Se registra en bitácora cada acción sobre operativos, usuarios y controles.

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

## Desarrollo y despliegue sin SSH

1. **En local (Laragon)**: desarrollar y correr los tests. Composer se usa **solo en local** (`composer require --dev phpunit/phpunit`). La carpeta `vendor/` no se sube.
2. **Base de datos**: importar `sql/001_esquema.sql` y `sql/002_datos_iniciales.sql` en phpMyAdmin. Cada cambio posterior va en un archivo nuevo numerado (`003_...sql`), que se aplica a mano en el mismo orden.
3. **Primer ADMIN**: ejecutar en local `php herramientas/crear_admin.php usuario clave`, que imprime un `INSERT` con el hash, y ejecutar ese INSERT en phpMyAdmin.
4. **Subir archivos**: por FTPS (FileZilla) o con el Administrador de archivos de cPanel. Si el plan incluye "Git Version Control", se puede desplegar desde el repositorio. `app/config.php` se crea una sola vez en el servidor y no se sobrescribe.
5. Activar AutoSSL y verificar que `http://` redirija a `https://`.
