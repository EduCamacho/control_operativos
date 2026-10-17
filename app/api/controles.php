<?php
/**
 * POST /api/controles — registra un control inopinado (RN-15). Solo SUPERVISOR.
 *
 * Cuerpo JSON: operativo_id, resultado, observacion e id_envio.
 * El control no registra la ubicación del supervisor ni calcula distancias: a
 * veces el General controla con un dron, sin ir al lugar. Si llegan
 * coordenadas, se ignoran. Las columnas de ubicación y distancia de
 * controles_inopinados se conservan solo por los controles ya registrados.
 * Un reintento con el mismo id_envio devuelve el control sin duplicarlo.
 */
declare(strict_types=1);

requiere_rol('SUPERVISOR');
requiere_post_api();
$entrada = leer_json();
$ahora = obtener_ahora();

$operativo_id = validar_entero($entrada['operativo_id'] ?? null, 1, PHP_INT_MAX);
if ($operativo_id === null) {
    responder_json(['error' => 'Solicitud incompleta.'], 400);
}

['datos' => $control, 'errores' => $errores] = validar_control($entrada);
if ($errores !== []) {
    responder_json(['error' => reset($errores), 'errores' => $errores], 422);
}

$ya_registrado = static fn (): ?array => obtener_control_por_envio($control['id_envio']);
$anterior = $ya_registrado();
if ($anterior !== null) {
    responder_json(['ok' => true, 'repetido' => true, 'control' => presentar_control($anterior)]);
}

$operativo = obtener_operativo($operativo_id);
if ($operativo === null) {
    responder_json(['error' => 'El operativo no existe.'], 404);
}
if (!operativo_controlable($operativo, fecha_servicio_vigente($ahora))) {
    responder_json(['error' => 'Solo se registran controles sobre operativos del servicio diario vigente.'], 409);
}

try {
    consultar(
        'INSERT INTO controles_inopinados (operativo_id, usuario_id, id_envio, resultado, observacion, registrado_en)
         VALUES (:operativo_id, :usuario_id, :id_envio, :resultado, :observacion, :ahora)',
        $control + [
            'operativo_id' => $operativo_id,
            'usuario_id'   => usuario_actual()['id'],
            'ahora'        => $ahora->format(FORMATO_FECHA_HORA),
        ],
    );
} catch (PDOException $error) {
    // Dos reintentos simultáneos con el mismo id_envio: el segundo choca con la clave única.
    if (es_error_duplicado($error) && ($anterior = $ya_registrado()) !== null) {
        responder_json(['ok' => true, 'repetido' => true, 'control' => presentar_control($anterior)]);
    }
    throw $error;
}
$control_id = (int) db()->lastInsertId();

registrar_bitacora('CONTROL', 'operativos', $operativo_id, [
    'control_id' => $control_id,
    'resultado'  => $control['resultado'],
]);

responder_json([
    'ok'      => true,
    'mensaje' => 'Control registrado.',
    'control' => presentar_control($ya_registrado()),
]);
