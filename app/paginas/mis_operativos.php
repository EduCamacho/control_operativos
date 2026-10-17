<?php
/**
 * UNIDAD: pantalla principal (docs/04). Cuatro tarjetas fijas del servicio
 * diario vigente, cada una con un solo botón según lo que toca hacer;
 * debajo, los próximos días y el historial.
 */
declare(strict_types=1);

$unidad_id = unidad_de_sesion();
$ahora = obtener_ahora();
$vigente = fecha_servicio_vigente($ahora);
$dia_vigente = validar_fecha($vigente);

$del_servicio = [];
foreach (listar_operativos_de_unidad($unidad_id, $vigente, $vigente) as $operativo) {
    $del_servicio[$operativo['turno']] = $operativo;
}

// Sin duración máxima: un operativo del servicio anterior puede seguir en curso.
$anteriores_en_curso = array_values(array_filter(
    listar_operativos_de_unidad($unidad_id, $dia_vigente->modify('-7 days')->format('Y-m-d'), $dia_vigente->modify('-1 day')->format('Y-m-d')),
    static fn (array $o): bool => $o['estado'] === 'EN_CURSO',
));

$proximos = listar_operativos_de_unidad(
    $unidad_id,
    $dia_vigente->modify('+1 day')->format('Y-m-d'),
    $dia_vigente->modify('+60 days')->format('Y-m-d'),
);
$historial = array_reverse(listar_operativos_de_unidad(
    $unidad_id,
    $dia_vigente->modify('-30 days')->format('Y-m-d'),
    $dia_vigente->modify('-1 day')->format('Y-m-d'),
));

/**
 * La acción que toca en la tarjeta: [etiqueta, ruta, parámetros, clase, icono] o null.
 */
$calcular_accion = static function (?array $operativo, string $turno) use ($ahora, $vigente): ?array {
    if ($operativo === null) {
        $rango = calcular_rango_turno($vigente, $turno);
        return $rango['hasta'] > $ahora
            ? ['Programar', 'operativos/programar', ['fecha' => $vigente, 'turno' => $turno], 'btn-success', 'calendar-plus']
            : null;
    }
    $parametros = ['id' => $operativo['id']];
    return match (true) {
        puede_iniciar($operativo, $ahora, VENTANA_INICIO_MIN) => ['Iniciar', 'operativos/ver', $parametros, 'btn-success', 'play-fill'],
        $operativo['estado'] === 'EN_CURSO'                   => ['Nuevo punto / Finalizar', 'operativos/ver', $parametros, 'btn-primary', 'geo-alt'],
        puede_agregar_nota($operativo)                        => ['Agregar nota', 'operativos/ver', $parametros, 'btn-outline-primary', 'file-earmark-plus'],
        default                                               => null,
    };
};

$titulo = 'Mis operativos';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="mb-3">
    <h1 class="h4 mb-0"><?= e(usuario_actual()['unidad_nombre']) ?></h1>
    <div class="text-body-secondary">
        Servicio diario del <strong><?= e(formatear_fecha_larga($dia_vigente)) ?></strong>
        · <?= e($ahora->format('H:i')) ?>
    </div>
</div>

<?php foreach ($anteriores_en_curso as $operativo): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span class="me-auto">
            Sigue en curso el turno <?= e(obtener_etiqueta_turno($operativo['turno'])) ?> del
            <?= e(formatear_fecha_larga(validar_fecha($operativo['fecha_servicio']))) ?>.
        </span>
        <a href="<?= e(armar_url('operativos/ver', ['id' => $operativo['id']])) ?>" class="btn btn-warning boton-accion">
            Nuevo punto / Finalizar
        </a>
    </div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
    <?php foreach (TURNOS as $turno): ?>
        <?php
        $operativo = $del_servicio[$turno] ?? null;
        $rango = calcular_rango_turno($vigente, $turno);
        $estado = $operativo === null
            ? ($rango['hasta'] > $ahora ? null : 'SIN_PROGRAMAR')
            : calcular_estado_visible($operativo, $ahora);
        $accion = $calcular_accion($operativo, $turno);
        ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 shadow-sm tarjeta-turno<?= $estado !== null ? ' tarjeta-' . strtolower($estado) : '' ?>">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent">
                    <span class="fw-semibold"><?= e(obtener_etiqueta_turno($turno)) ?></span>
                    <?php if ($estado !== null): ?>
                        <?= mostrar_estado($estado, $operativo !== null && pendiente_nota($operativo)) ?>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($operativo === null): ?>
                        <p class="text-body-secondary mb-0">
                            <?= $estado === 'SIN_PROGRAMAR'
                                ? 'No se programó operativo para este turno.'
                                : 'Sin programar. Inicio entre las ' . e(formatear_hora($rango['desde'])) . ' y las ' . e(formatear_hora($rango['hasta'])) . '.' ?>
                        </p>
                    <?php else: ?>
                        <a href="<?= e(armar_url('operativos/ver', ['id' => $operativo['id']])) ?>" class="stretched-link text-reset text-decoration-none">
                            <div class="fs-5 fw-semibold">
                                <?= e(formatear_hora($operativo['inicio_programado'])) ?> – <?= e(formatear_hora($operativo['fin_programado'])) ?>
                            </div>
                        </a>
                        <div class="small text-body-secondary text-truncate"><?= e($operativo['lugar_programado']) ?></div>
                        <div class="small text-body-secondary text-truncate">
                            <?= e($operativo['jefe_grado'] . ' ' . $operativo['jefe_nombres']) ?>
                        </div>
                        <?php if ($operativo['estado'] === 'PROGRAMADO' && !puede_iniciar($operativo, $ahora, VENTANA_INICIO_MIN) && $ahora < convertir_a_fecha_hora($operativo['fin_programado'])): ?>
                            <div class="small mt-2">
                                <i class="bi bi-clock"></i>
                                Podrá iniciarlo desde las <?= e(formatear_hora(convertir_a_fecha_hora($operativo['inicio_programado'])->modify('-' . VENTANA_INICIO_MIN . ' minutes'))) ?>.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($accion !== null): ?>
                    <div class="card-footer bg-transparent border-0 pt-0 position-relative">
                        <a href="<?= e(armar_url($accion[1], $accion[2])) ?>" class="btn <?= e($accion[3]) ?> w-100 boton-accion">
                            <i class="bi bi-<?= e($accion[4]) ?>"></i> <?= e($accion[0]) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$mostrar_lista = static function (array $operativos, string $vacio) use ($ahora): void {
    if ($operativos === []) {
        echo '<p class="text-body-secondary small">' . e($vacio) . '</p>';
        return;
    }
    echo '<div class="list-group mb-4">';
    foreach ($operativos as $operativo) {
        $estado = calcular_estado_visible($operativo, $ahora);
        echo '<a class="list-group-item list-group-item-action d-flex flex-wrap align-items-center gap-2" href="'
            . e(armar_url('operativos/ver', ['id' => $operativo['id']])) . '">'
            . '<span class="me-auto"><span class="fw-semibold">'
            . e(validar_fecha($operativo['fecha_servicio'])->format('d/m')) . ' · ' . e(obtener_etiqueta_turno($operativo['turno']))
            . '</span> <span class="text-body-secondary small">'
            . e(formatear_hora($operativo['inicio_programado'])) . '–' . e(formatear_hora($operativo['fin_programado']))
            . ' · ' . e($operativo['lugar_programado']) . '</span></span>'
            . mostrar_estado($estado, pendiente_nota($operativo))
            . '</a>';
    }
    echo '</div>';
};
?>

<div class="d-flex justify-content-between align-items-center">
    <h2 class="h6 text-uppercase text-body-secondary mb-2">Próximos días</h2>
    <a href="<?= e(armar_url('operativos/programar')) ?>" class="btn btn-sm btn-outline-success mb-2">
        <i class="bi bi-calendar-plus"></i> Programar otro día
    </a>
</div>
<?php $mostrar_lista($proximos, 'No hay operativos programados para los próximos días.'); ?>

<h2 class="h6 text-uppercase text-body-secondary mb-2">Historial (últimos 30 días)</h2>
<?php $mostrar_lista($historial, 'Sin operativos en los últimos 30 días.'); ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
