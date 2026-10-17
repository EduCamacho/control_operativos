<?php
/**
 * POST /api/ubicaciones — inicia el operativo (primera ubicación) o registra un
 * nuevo punto (RN-07 a RN-11a). Solo cuentas UNIDAD, sobre operativos de su unidad.
 *
 * Cuerpo JSON: operativo_id, accion (INICIAR | PUNTO), latitud, longitud,
 * precision_m, origen (GPS | MANUAL), referencia, id_envio.
 * Un reintento con el mismo id_envio devuelve éxito sin duplicar el punto.
 */
declare(strict_types=1);

requiere_rol('UNIDAD');
requiere_post_api();
$unidad_id = unidad_de_sesion();
$entrada = leer_json();
$ahora = obtener_ahora();

$operativo_id = validar_entero($entrada['operativo_id'] ?? null, 1, PHP_INT_MAX);
$accion = $entrada['accion'] ?? null;
if ($operativo_id === null || !in_array($accion, ['INICIAR', 'PUNTO'], true)) {
    responder_json(['error' => 'Solicitud incompleta.'], 400);
}

['datos' => $ubicacion, 'errores' => $errores] = validar_ubicacion($entrada, true, true);
if ($errores !== []) {
    responder_json(['error' => reset($errores), 'errores' => $errores], 422);
}

/** El envío ya se guardó antes (se cortó la señal al responder): éxito sin duplicar. */
$ya_registrado = static fn (): bool => (bool) consultar(
    'SELECT 1 FROM operativo_ubicaciones ub JOIN operativos o ON o.id = ub.operativo_id
     WHERE ub.id_envio = :id_envio AND o.id = :operativo_id AND o.unidad_id = :unidad_id',
    ['id_envio' => $ubicacion['id_envio'], 'operativo_id' => $operativo_id, 'unidad_id' => $unidad_id],
)->fetchColumn();

if ($ya_registrado()) {
    responder_json(['ok' => true, 'repetido' => true, 'mensaje' => 'La ubicación ya estaba registrada.']);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $operativo = obtener_operativo_de_unidad($operativo_id, $unidad_id, true);
    if ($operativo === null) {
        $pdo->rollBack();
        responder_json(['error' => 'El operativo no existe.'], 404);
    }

    if ($accion === 'INICIAR') {
        if (!puede_iniciar($operativo, $ahora, VENTANA_INICIO_MIN)) {
            $pdo->rollBack();
            $desde = convertir_a_fecha_hora($operativo['inicio_programado'])->modify('-' . VENTANA_INICIO_MIN . ' minutes');
            $mensaje = match (true) {
                $operativo['estado'] !== 'PROGRAMADO' => 'El operativo ya fue iniciado.',
                $ahora < $desde => 'Todavía no se puede iniciar: desde las ' . formatear_hora($desde) . '.',
                default => 'Ya pasó la hora de fin programada: el operativo no se puede iniciar.',
            };
            responder_json(['error' => $mensaje], 409);
        }
        consultar(
            "UPDATE operativos SET estado = 'EN_CURSO', inicio_real = :ahora
             WHERE id = :id AND unidad_id = :unidad_id AND estado = 'PROGRAMADO'",
            ['ahora' => $ahora->format(FORMATO_FECHA_HORA), 'id' => $operativo_id, 'unidad_id' => $unidad_id],
        );
    } elseif (!puede_registrar_punto($operativo)) {
        $pdo->rollBack();
        responder_json(['error' => 'El operativo no está en curso: no se pueden registrar puntos.'], 409);
    }

    consultar(
        'INSERT INTO operativo_ubicaciones
            (operativo_id, latitud, longitud, precision_m, origen, referencia, registrado_por, id_envio, registrado_en)
         VALUES (:operativo_id, :latitud, :longitud, :precision_m, :origen, :referencia, :registrado_por, :id_envio, :ahora)',
        $ubicacion + [
            'operativo_id'   => $operativo_id,
            'registrado_por' => usuario_actual()['id'],
            'ahora'          => $ahora->format(FORMATO_FECHA_HORA),
        ],
    );
    $ubicacion_id = (int) $pdo->lastInsertId();

    registrar_bitacora($accion === 'INICIAR' ? 'INICIAR' : 'NUEVO_PUNTO', 'operativos', $operativo_id, [
        'ubicacion_id' => $ubicacion_id,
        'latitud'      => $ubicacion['latitud'],
        'longitud'     => $ubicacion['longitud'],
        'precision_m'  => $ubicacion['precision_m'],
        'origen'       => $ubicacion['origen'],
        'referencia'   => $ubicacion['referencia'],
    ]);
    $pdo->commit();
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Dos reintentos simultáneos con el mismo id_envio: el segundo choca con la clave única.
    if (es_error_duplicado($error) && $ya_registrado()) {
        responder_json(['ok' => true, 'repetido' => true, 'mensaje' => 'La ubicación ya estaba registrada.']);
    }
    throw $error;
}

responder_json([
    'ok'      => true,
    'mensaje' => $accion === 'INICIAR' ? 'Operativo iniciado.' : 'Punto registrado.',
]);
