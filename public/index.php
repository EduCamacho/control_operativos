<?php
/**
 * Front controller: toda petición que no sea un archivo de assets/ entra aquí
 * y se enruta a app/paginas/ o app/api/ según la lista blanca de app/rutas.php.
 */
declare(strict_types=1);

// Servidor integrado de PHP (php -S) en local: entrega los archivos estáticos.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/app/inicio.php';
require RUTA_APP . '/rutas.php';

enviar_cabeceras_seguridad();
iniciar_sesion();

$ruta = obtener_ruta_actual();

// Un texto mal codificado no debe llegar a la base de datos (respondería con error 500).
if (!entrada_es_utf8_valida($_GET) || !entrada_es_utf8_valida($_POST)) {
    if (es_peticion_api()) {
        responder_json(['error' => 'El texto enviado tiene caracteres no válidos.'], 400);
    }
    http_response_code(400);
    exit('El texto enviado tiene caracteres no válidos. Vuelva atrás y escríbalo de nuevo.');
}

if (!array_key_exists($ruta, RUTAS)) {
    if (es_peticion_api()) {
        responder_json(['error' => 'Ruta no encontrada.'], 404);
    }
    http_response_code(404);
    require RUTA_APP . '/paginas/no_encontrado.php';
    exit;
}

require RUTA_APP . '/' . RUTAS[$ruta];
