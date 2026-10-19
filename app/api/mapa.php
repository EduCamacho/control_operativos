<?php
/**
 * GET /api/mapa — operativos del servicio diario vigente para el mapa del
 * SUPERVISOR, con su estado visible y su última ubicación (RN-14, RN-21).
 * El mapa lo consulta cada 30 s: no hay websockets ni tareas en segundo plano.
 */
declare(strict_types=1);

requiere_rol('SUPERVISOR');
$ahora = obtener_ahora();
$servicio = fecha_servicio_vigente($ahora);

$operativos = [];
foreach (listar_operativos_para_mapa($servicio) as $fila) {
    $estado = calcular_estado_visible($fila, $ahora);
    $operativos[] = [
        'id'                => (int) $fila['id'],
        'unidad'            => $fila['unidad_nombre'],
        'division'          => $fila['division'],
        'turno'             => $fila['turno'],
        'turno_etiqueta'    => obtener_etiqueta_turno($fila['turno']),
        'servicio_anterior' => $fila['fecha_servicio'] !== $servicio,
        'estado'            => $estado,
        'estado_etiqueta'   => obtener_etiqueta_estado($estado),
        'pendiente_nota'    => pendiente_nota($fila),
        'lugar_programado'  => $fila['lugar_programado'],
        'inicio_programado' => formatear_hora($fila['inicio_programado']),
        'fin_programado'    => formatear_hora($fila['fin_programado']),
        'jefe'              => $fila['jefe_grado'] . ' ' . $fila['jefe_nombres'],
        'jefe_celular'      => $fila['jefe_celular'],
        'fuerzas_propias'   => (int) $fila['fuerzas_propias'],
        'fuerzas_apoyo'     => (int) $fila['fuerzas_apoyo'],
        'unidades_moviles'  => (int) $fila['unidades_moviles'],
        'puntos'            => (int) $fila['puntos'],
        // Lugar programado: ubica en el mapa al operativo que aún no registra puntos.
        'lugar'             => $fila['lugar_latitud'] === null ? null : [
            'latitud'  => (float) $fila['lugar_latitud'],
            'longitud' => (float) $fila['lugar_longitud'],
        ],
        'controles'         => (int) $fila['controles'],
        'ubicacion'         => $fila['latitud'] === null ? null : [
            'latitud'     => (float) $fila['latitud'],
            'longitud'    => (float) $fila['longitud'],
            'precision_m' => $fila['precision_m'] === null ? null : (int) $fila['precision_m'],
            'origen'      => $fila['origen'],
            'referencia'  => $fila['referencia'] ?? 'Sin referencia',
            'hora'        => formatear_hora($fila['ubicacion_en']),
            'hace'        => formatear_hace($fila['ubicacion_en'], $ahora),
        ],
    ];
}

responder_json([
    'servicio_diario'   => $servicio,
    'servicio_etiqueta' => formatear_fecha_larga(validar_fecha($servicio)),
    'hora_servidor'     => $ahora->format('H:i:s'),
    // Cuadro "En este momento": de todos los operativos, sin los filtros de la pantalla.
    'en_curso'          => sumar_fuerzas_en_curso($operativos),
    'operativos'        => $operativos,
]);
