<?php
/**
 * POST /api/coordenadas — separa en latitud y longitud el texto copiado de
 * Google Maps (RN-09). Lo usan todos los roles en el ingreso manual.
 * No guarda nada: solo analiza el texto con parsear_coordenadas().
 */
declare(strict_types=1);

requiere_login();
requiere_post_api();

$texto = mb_substr((string) (leer_json()['texto'] ?? ''), 0, 2000);
$coordenadas = parsear_coordenadas($texto);

if ($coordenadas === null) {
    responder_json([
        'error' => 'No se reconocen las coordenadas. En Google Maps, mantenga presionado el lugar y copie los números que aparecen (por ejemplo: -8.1116, -79.0288).',
    ], 422);
}

[$latitud, $longitud] = $coordenadas;
responder_json([
    'latitud'               => $latitud,
    'longitud'              => $longitud,
    'dentro_de_la_libertad' => dentro_de_la_libertad($latitud, $longitud),
]);
