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

function responder_json(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
    if (ENTORNO === 'produccion') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
