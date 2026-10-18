<?php
/**
 * Arranque común: configuración, zona horaria, errores y funciones auxiliares.
 * Lo carga public/index.php antes de cada página.
 */
declare(strict_types=1);

const RUTA_APP = __DIR__;

if (!is_file(RUTA_APP . '/config.php')) {
    http_response_code(500);
    exit('Falta app/config.php. Cópielo desde app/config.ejemplo.php y complételo.');
}
require RUTA_APP . '/config.php';

// Constantes agregadas después de crear app/config.php en el servidor: vacías si faltan.
defined('SOPORTE_NOMBRE') || define('SOPORTE_NOMBRE', '');
defined('SOPORTE_CELULAR') || define('SOPORTE_CELULAR', '');
defined('SOPORTE_WHATSAPP') || define('SOPORTE_WHATSAPP', false);
defined('VIDEO_TUTORIAL_UNIDAD_URL') || define('VIDEO_TUTORIAL_UNIDAD_URL', '');
defined('TURNSTILE_SITE_KEY') || define('TURNSTILE_SITE_KEY', '');
defined('TURNSTILE_SECRET_KEY') || define('TURNSTILE_SECRET_KEY', '');
defined('CSP_APLICAR') || define('CSP_APLICAR', false);
defined('SESION_INACTIVIDAD_S') || define('SESION_INACTIVIDAD_S', ['ADMIN' => 1800, 'SUPERVISOR' => 14400, 'UNIDAD' => 43200]);
defined('SESION_MAXIMA_S') || define('SESION_MAXIMA_S', ['ADMIN' => 28800, 'SUPERVISOR' => 57600, 'UNIDAD' => 86400]);

date_default_timezone_set('America/Lima');
mb_internal_encoding('UTF-8');

ini_set('display_errors', ENTORNO === 'local' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', RUTA_APP . '/almacen/registros/errores.log');
error_reporting(E_ALL);

require RUTA_APP . '/dominio.php';
require RUTA_APP . '/db.php';
require RUTA_APP . '/bitacora.php';
require RUTA_APP . '/sesion.php';
require RUTA_APP . '/autorizacion.php';
require RUTA_APP . '/formularios.php';
require RUTA_APP . '/operativos.php';

set_exception_handler(static function (Throwable $error): void {
    error_log((string) $error);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (es_peticion_api()) {
        responder_json(['error' => 'Ocurrió un error en el servidor.'], 500);
    }
    echo ENTORNO === 'local'
        ? '<pre>' . e((string) $error) . '</pre>'
        : 'Ocurrió un error en el servidor. Intente nuevamente.';
    exit;
});

/** Escapa texto para HTML. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function armar_url(string $ruta = '', array $parametros = []): string
{
    $url = URL_BASE . '/' . ltrim($ruta, '/');
    return $parametros === [] ? $url : $url . '?' . http_build_query($parametros);
}

function redirigir(string $ruta, array $parametros = []): never
{
    header('Location: ' . armar_url($ruta, $parametros));
    exit;
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function es_peticion_api(): bool
{
    return str_starts_with(obtener_ruta_actual(), 'api/');
}

/** Ruta pedida sin URL_BASE ni barras, por ejemplo 'login' o 'api/mapa'. */
function obtener_ruta_actual(): string
{
    $camino = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (URL_BASE !== '' && str_starts_with($camino, URL_BASE)) {
        $camino = substr($camino, strlen(URL_BASE));
    }
    $camino = trim($camino, '/');
    return $camino === 'index.php' ? '' : $camino;
}

/** true si todos los textos (también dentro de listas) son UTF-8 válido. */
function entrada_es_utf8_valida(array $valores): bool
{
    foreach ($valores as $clave => $valor) {
        if (!mb_check_encoding((string) $clave, 'UTF-8')) {
            return false;
        }
        if (is_array($valor) ? !entrada_es_utf8_valida($valor) : !mb_check_encoding((string) $valor, 'UTF-8')) {
            return false;
        }
    }
    return true;
}

/** Cuerpo JSON de la petición (la API recibe JSON desde fetch). */
function leer_json(): array
{
    $datos = json_decode((string) file_get_contents('php://input'), true);
    return is_array($datos) ? $datos : [];
}

/** La API solo acepta POST con token CSRF. */
function requiere_post_api(): void
{
    if (!es_post()) {
        responder_json(['error' => 'Método no permitido.'], 405);
    }
    verificar_csrf();
}

function responder_json(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Versión instalada ("abc1234 · 2026-10-17 13:20"), que escribe herramientas/empaquetar.php. null en desarrollo. */
function obtener_version(): ?string
{
    $archivo = RUTA_APP . '/version.txt';
    return is_file($archivo) ? trim((string) file_get_contents($archivo)) : null;
}

/** Hora actual del servidor en Lima (RN-11). */
function obtener_ahora(): DateTimeImmutable
{
    return new DateTimeImmutable('now', obtener_zona_horaria());
}

/** Mensajes que se muestran una sola vez en la página siguiente. */
function guardar_mensaje(string $tipo, string $texto): void
{
    $_SESSION['mensajes'][] = ['tipo' => $tipo, 'texto' => $texto];
}

/** @return list<array{tipo: string, texto: string}> */
function obtener_mensajes(): array
{
    $mensajes = $_SESSION['mensajes'] ?? [];
    unset($_SESSION['mensajes']);
    return $mensajes;
}

function enviar_cabeceras_seguridad(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
    // Sistema interno: que los buscadores no lo indexen (también public/robots.txt).
    header('X-Robots-Tag: noindex, nofollow');
    // CSP (RN-19a): con CSP_APLICAR = false solo informa en la consola lo que bloquearía.
    header((CSP_APLICAR ? 'Content-Security-Policy: ' : 'Content-Security-Policy-Report-Only: ') . armar_politica_contenido());
    if (ENTORNO === 'produccion') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/**
 * Política de contenido: solo recursos propios, más Cloudflare Turnstile (ingreso)
 * y Google Maps (pantallas del SUPERVISOR, RN-21). Alpine.js necesita 'unsafe-eval'
 * y hay scripts y estilos en línea, por eso 'unsafe-inline'; aun así impide cargar
 * scripts de otros dominios, enviar datos a ellos y que el sitio se incruste en otro.
 */
function armar_politica_contenido(): string
{
    $google = 'https://maps.googleapis.com https://maps.gstatic.com';
    return implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com $google",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "img-src 'self' data: blob: $google https://*.googleapis.com https://*.gstatic.com https://*.ggpht.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "connect-src 'self' https://challenges.cloudflare.com $google https://*.googleapis.com",
        'frame-src https://challenges.cloudflare.com',
        "worker-src 'self' blob:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]);
}
