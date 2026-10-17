<?php
/**
 * POST /api/controles — registra un control inopinado (RN-15). Solo SUPERVISOR.
 *
 * Cuerpo JSON: operativo_id, resultado, observacion, id_envio y, si se obtuvo,
 * latitud, longitud, precision_m y origen. La ubicación del supervisor es
 * opcional y no se valida contra el rectángulo de La Libertad.
 * Devuelve la distancia a la última ubicación del operativo antes del control.
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

$con_ubicacion = isset($entrada['latitud']) && $entrada['latitud'] !== '' && $entrada['latitud'] !== null;
$ubicacion = ['latitud' => null, 'longitud' => null, 'precision_m' => null, 'origen' => null];
if ($con_ubicacion) {
    ['datos' => $datos_ubicacion, 'errores' => $errores_ubicacion] = validar_ubicacion($entrada, false, false);
    unset($errores_ubicacion['id_envio'], $datos_ubicacion['id_envio']);
    $errores += $errores_ubicacion;
    $ubicacion = array_intersect_key($datos_ubicacion, $ubicacion) + $ubicacion;
}

if ($errores !== []) {
    responder_json(['error' => reset($errores), 'errores' => $errores], 422);
}

$anterior = consultar(
    'SELECT distancia_m FROM controles_inopinados WHERE id_envio = :id_envio AND operativo_id = :operativo_id',
    ['id_envio' => $control['id_envio'], 'operativo_id' => $operativo_id],
)->fetch();
if ($anterior) {
    responder_json(['ok' => true, 'repetido' => true, 'distancia_m' => $anterior['distancia_m'] === null ? null : (int) $anterior['distancia_m'], 'control' => presentar_control(obtener_control_por_envio($control['id_envio']))]);
}

$operativo = obtener_operativo($operativo_id);
if ($operativo === null) {
    responder_json(['error' => 'El operativo no existe.'], 404);
}
if (!operativo_controlable($operativo, fecha_servicio_vigente($ahora))) {
    responder_json(['error' => 'Solo se registran controles sobre operativos del servicio diario vigente.'], 409);
}

// Última ubicación que el operativo registró antes del control (RN-15).
$ultima = consultar(
    'SELECT latitud, longitud FROM operativo_ubicaciones
     WHERE operativo_id = :id AND registrado_en <= :ahora
     ORDER BY registrado_en DESC, id DESC LIMIT 1',
    ['id' => $operativo_id, 'ahora' => $ahora->format(FORMATO_FECHA_HORA)],
)->fetch();
$distancia = ($ultima && $ubicacion['latitud'] !== null)
    ? distancia_m($ubicacion['latitud'], $ubicacion['longitud'], (float) $ultima['latitud'], (float) $ultima['longitud'])
    : null;

try {
    consultar(
        'INSERT INTO controles_inopinados
            (operativo_id, usuario_id, id_envio, latitud, longitud, precision_m, origen, distancia_m, resultado, observacion, registrado_en)
         VALUES (:operativo_id, :usuario_id, :id_envio, :latitud, :longitud, :precision_m, :origen, :distancia_m, :resultado, :observacion, :ahora)',
        $ubicacion + $control + [
            'operativo_id' => $operativo_id,
            'usuario_id'   => usuario_actual()['id'],
            'distancia_m'  => $distancia,
            'ahora'        => $ahora->format(FORMATO_FECHA_HORA),
        ],
    );
} catch (PDOException $error) {
    if (es_error_duplicado($error)) {
        responder_json(['ok' => true, 'repetido' => true, 'distancia_m' => $distancia, 'control' => presentar_control(obtener_control_por_envio($control['id_envio']))]);
    }
    throw $error;
}
$control_id = (int) db()->lastInsertId();

registrar_bitacora('CONTROL', 'operativos', $operativo_id, [
    'control_id'  => $control_id,
    'resultado'   => $control['resultado'],
    'distancia_m' => $distancia,
    'origen'      => $ubicacion['origen'],
]);

responder_json([
    'ok'          => true,
    'distancia_m' => $distancia,
    'mensaje'     => 'Control registrado.',
    'control'     => presentar_control(obtener_control_por_envio($control['id_envio'])),
]);
