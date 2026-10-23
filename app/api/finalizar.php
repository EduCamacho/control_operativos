<?php
/**
 * POST /api/finalizar — finaliza el operativo con sus resultados (RN-12, RN-13).
 * Solo cuentas UNIDAD, sobre operativos de su unidad.
 *
 * Cuerpo JSON: operativo_id, cantidades {concepto_id: entero}, observaciones, nota_informativa.
 * Si el operativo ya está FINALIZADO (reintento tras cortarse la señal), devuelve éxito.
 */
declare(strict_types=1);

requiere_rol('UNIDAD');
requiere_post_api();
$unidad_id = unidad_de_sesion();
$entrada = leer_json();
$ahora = obtener_ahora();

$operativo_id = validar_entero($entrada['operativo_id'] ?? null, 1, PHP_INT_MAX);
if ($operativo_id === null) {
    responder_json(['error' => 'Solicitud incompleta.'], 400);
}

$conceptos = obtener_conceptos_activos();
$ids_conceptos = array_map(static fn (array $c): int => (int) $c['id'], $conceptos);
['datos' => $cantidades, 'errores' => $errores] = validar_cantidades_resultado((array) ($entrada['cantidades'] ?? []), $ids_conceptos);

try {
    $nota = normalizar_nota_informativa((string) ($entrada['nota_informativa'] ?? ''));
} catch (InvalidArgumentException $error) {
    $errores['nota_informativa'] = $error->getMessage();
}

$observaciones = trim((string) ($entrada['observaciones'] ?? ''));
if (mb_strlen($observaciones) > MAX_CARACTERES_OBSERVACIONES) {
    $errores['observaciones'] = 'Las observaciones admiten hasta ' . MAX_CARACTERES_OBSERVACIONES . ' caracteres.';
}

if ($errores !== []) {
    responder_json(['error' => 'Revise los datos marcados.', 'errores' => $errores], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $operativo = obtener_operativo_de_unidad($operativo_id, $unidad_id, true);
    if ($operativo === null) {
        $pdo->rollBack();
        responder_json(['error' => 'El operativo no existe.'], 404);
    }
    if ($operativo['estado'] === 'FINALIZADO') {
        $pdo->rollBack();
        responder_json(['ok' => true, 'repetido' => true, 'mensaje' => 'El operativo ya estaba finalizado.']);
    }
    if (!puede_finalizar($operativo)) {
        $pdo->rollBack();
        responder_json(['error' => 'Solo se puede finalizar un operativo en curso.'], 409);
    }

    consultar(
        "UPDATE operativos
         SET estado = 'FINALIZADO', fin_real = :ahora, observaciones = :observaciones, nota_informativa = :nota
         WHERE id = :id AND unidad_id = :unidad_id AND estado = 'EN_CURSO'",
        [
            'ahora'         => $ahora->format(FORMATO_FECHA_HORA),
            'observaciones' => $observaciones === '' ? null : $observaciones,
            'nota'          => $nota,
            'id'            => $operativo_id,
            'unidad_id'     => $unidad_id,
        ],
    );

    $insertar = db()->prepare(
        'INSERT INTO operativo_resultados (operativo_id, concepto_resultado_id, cantidad) VALUES (:operativo_id, :concepto_id, :cantidad)',
    );
    foreach ($cantidades as $concepto_id => $cantidad) {
        $insertar->execute(['operativo_id' => $operativo_id, 'concepto_id' => $concepto_id, 'cantidad' => $cantidad]);
    }

    registrar_bitacora('FINALIZAR', 'operativos', $operativo_id, [
        'cantidades'       => array_filter($cantidades),
        'negativo'         => es_resultado_negativo($cantidades),
        'nota_informativa' => $nota,
    ]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

responder_json(['ok' => true, 'mensaje' => 'Operativo finalizado.']);
