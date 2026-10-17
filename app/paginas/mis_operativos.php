<?php
/**
 * UNIDAD: pantalla principal (docs/04). Los cuatro turnos del servicio
 * diario vigente (el actual resaltado), cada uno con la acción que toca;
 * debajo, pestañas Próximos / Historial agrupadas por día.
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


// Turno en el que estamos ahora (se resalta con "AHORA").
$turno_actual = clasificar_turno($ahora->format('H:i'));

/**
 * La acción que toca en el turno: [etiqueta, ruta, parámetros, clase, icono] o null.
 */
$calcular_accion = static function (?array $operativo, string $turno) use ($ahora, $vigente): ?array {
    if ($operativo === null) {
        $rango = calcular_rango_turno($vigente, $turno);
        return $rango['hasta'] > $ahora
            ? ['Programar', 'operativos/programar', ['fecha' => $vigente, 'turno' => $turno], 'btn-outline-institucional', 'plus-lg']
            : null;
    }
    $parametros = ['id' => $operativo['id']];
    return match (true) {
        puede_iniciar($operativo, $ahora, VENTANA_INICIO_MIN) => ['Iniciar operativo', 'operativos/ver', $parametros, 'btn-institucional', 'play-fill'],
        $operativo['estado'] === 'EN_CURSO'                   => ['Nuevo punto / Finalizar', 'operativos/ver', $parametros, 'btn-institucional', 'geo-alt-fill'],
        puede_agregar_nota($operativo)                        => ['Agregar N.I', 'operativos/ver', $parametros, 'btn-outline-institucional', 'file-earmark-plus'],
        default                                               => null,
    };
};

/** "Domingo 18/10" para agrupar las listas por día. */
$titulo_dia = static function (string $fecha): string {
    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $dia = validar_fecha($fecha);
    return $dias[(int) $dia->format('w')] . ' ' . $dia->format('d/m');
};

/** @return array<string, list<array>> Operativos agrupados por fecha de servicio. */
$agrupar_por_dia = static function (array $operativos): array {
    $grupos = [];
    foreach ($operativos as $operativo) {
        $grupos[$operativo['fecha_servicio']][] = $operativo;
    }
    return $grupos;
};

$grupos_proximos = $agrupar_por_dia($proximos);
$grupos_historial = $agrupar_por_dia($historial);
$dias_historial_visibles = 7;

$titulo = 'Mis operativos';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto"><?= e(usuario_actual()['unidad_nombre']) ?></h1>
    <a href="<?= e(armar_url('operativos/programar')) ?>" class="btn btn-institucional">
        <i class="bi bi-plus-lg"></i> Programar
    </a>
</div>

<?php foreach ($anteriores_en_curso as $operativo): ?>
    <div class="turno fila-estado tarjeta-sin_cierre aviso-anterior mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-naranja"></i>
            <span class="me-auto small">
                Sigue en curso el turno <strong><?= e(obtener_etiqueta_turno($operativo['turno'])) ?></strong>
                del <?= e($titulo_dia($operativo['fecha_servicio'])) ?>.
            </span>
            <a href="<?= e(armar_url('operativos/ver', ['id' => $operativo['id']])) ?>" class="btn btn-sm btn-institucional">
                <i class="bi bi-geo-alt-fill"></i> Nuevo punto / Finalizar
            </a>
        </div>
    </div>
<?php endforeach; ?>

<!-- Los cuatro turnos del servicio diario vigente -->
<h2 class="titulo-seccion">
    <i class="bi bi-calendar-day"></i> Servicio diario
    <span class="titulo-seccion-detalle">· <?= e($titulo_dia($vigente)) ?> · <?= e($ahora->format('H:i')) ?></span>
</h2>
<div class="turnos mb-4">
    <?php foreach (TURNOS as $turno): ?>
        <?php
        $operativo = $del_servicio[$turno] ?? null;
        $rango = calcular_rango_turno($vigente, $turno);
        $estado = $operativo === null
            ? ($rango['hasta'] > $ahora ? null : 'SIN_PROGRAMAR')
            : calcular_estado_visible($operativo, $ahora);
        $accion = $calcular_accion($operativo, $turno);
        $es_ahora = $turno === $turno_actual;
        ?>
        <div class="turno fila-estado<?= $estado !== null ? ' tarjeta-' . strtolower($estado) : '' ?><?= $es_ahora ? ' ahora' : '' ?>">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold"><?= e(obtener_etiqueta_turno($turno)) ?></span>
                <?php if ($es_ahora): ?><span class="badge insignia-ahora">● AHORA</span><?php endif; ?>
                <span class="ms-auto">
                    <?php if ($estado !== null): ?>
                        <?= mostrar_estado($estado, $operativo !== null && pendiente_nota($operativo)) ?>
                    <?php elseif ($accion !== null): ?>
                        <a href="<?= e(armar_url($accion[1], $accion[2])) ?>" class="btn btn-sm <?= e($accion[3]) ?>">
                            <i class="bi bi-<?= e($accion[4]) ?>"></i> <?= e($accion[0]) ?>
                        </a>
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($operativo === null): ?>
                <div class="small text-body-secondary">
                    <?= $estado === 'SIN_PROGRAMAR'
                        ? 'No se programó operativo para este turno.'
                        : 'Inicio entre las ' . e(formatear_hora($rango['desde'])) . ' y las ' . e(formatear_hora($rango['hasta'])) ?>
                </div>
            <?php else: ?>
                <a href="<?= e(armar_url('operativos/ver', ['id' => $operativo['id']])) ?>" class="d-block text-reset text-decoration-none mt-1">
                    <span class="fs-5 fw-semibold"><?= e(formatear_hora($operativo['inicio_programado'])) ?>–<?= e(formatear_hora($operativo['fin_programado'])) ?></span>
                    <span class="d-block small text-body-secondary text-truncate"><i class="bi bi-geo-alt"></i> <?= e($operativo['lugar_programado']) ?></span>
                    <span class="d-block small text-body-secondary text-truncate"><i class="bi bi-person-badge"></i> <?= e($operativo['jefe_grado'] . ' ' . $operativo['jefe_nombres']) ?></span>
                </a>
                <?php if ($operativo['estado'] === 'PROGRAMADO' && !puede_iniciar($operativo, $ahora, VENTANA_INICIO_MIN) && $ahora < convertir_a_fecha_hora($operativo['fin_programado'])): ?>
                    <div class="small text-body-secondary mt-1">
                        <i class="bi bi-clock"></i>
                        Podrá iniciarlo desde las <?= e(formatear_hora(convertir_a_fecha_hora($operativo['inicio_programado'])->modify('-' . VENTANA_INICIO_MIN . ' minutes'))) ?>.
                    </div>
                <?php endif; ?>
                <?php if ($accion !== null): ?>
                    <a href="<?= e(armar_url($accion[1], $accion[2])) ?>" class="btn <?= e($accion[3]) ?> w-100 boton-accion mt-2">
                        <i class="bi bi-<?= e($accion[4]) ?>"></i> <?= e($accion[0]) ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
/** Una lista de operativos agrupada por día. */
$mostrar_grupos = static function (array $grupos, ?int $dias_visibles) use ($ahora, $titulo_dia): void {
    $numero = 0;
    foreach ($grupos as $fecha => $operativos) {
        $numero++;
        $oculto = $dias_visibles !== null && $numero > $dias_visibles;
        echo '<div class="grupo-dia"' . ($oculto ? ' x-show="mas" x-cloak' : '') . '>';
        echo '<div class="titulo-dia">' . e($titulo_dia((string) $fecha)) . '</div>';
        foreach ($operativos as $operativo) {
            $estado = calcular_estado_visible($operativo, $ahora);
            echo '<a class="fila-operativo fila-estado tarjeta-' . e(strtolower($estado)) . '" href="'
                . e(armar_url('operativos/ver', ['id' => $operativo['id']])) . '">'
                . '<span class="me-auto text-truncate">'
                . '<span class="fw-semibold">' . e(obtener_etiqueta_turno($operativo['turno'])) . '</span> '
                . '<span class="small">' . e(formatear_hora($operativo['inicio_programado'])) . '–' . e(formatear_hora($operativo['fin_programado'])) . '</span>'
                . '<span class="d-block small text-body-secondary text-truncate">' . e($operativo['lugar_programado']) . '</span>'
                . '</span>'
                . mostrar_estado($estado, pendiente_nota($operativo))
                . '</a>';
        }
        echo '</div>';
    }
};
?>

<!-- Próximos días e historial en pestañas -->
<h2 class="titulo-seccion"><i class="bi bi-calendar-range"></i> Otros días</h2>
<div x-data="{ pestana: 'proximos', mas: false }">
    <div class="pestanas mb-3" role="tablist">
        <button type="button" role="tab" class="pestana" :class="pestana === 'proximos' && 'activa'" :aria-selected="pestana === 'proximos'"
                @click="pestana = 'proximos'">
            Próximos <span class="badge rounded-pill"><?= count($proximos) ?></span>
        </button>
        <button type="button" role="tab" class="pestana" :class="pestana === 'historial' && 'activa'" :aria-selected="pestana === 'historial'"
                @click="pestana = 'historial'">
            Historial
        </button>
    </div>

    <div role="tabpanel" x-show="pestana === 'proximos'">
        <?php if ($proximos === []): ?>
            <p class="text-body-secondary small">No hay operativos programados para los próximos días.</p>
        <?php else: ?>
            <?php $mostrar_grupos($grupos_proximos, null); ?>
        <?php endif; ?>
    </div>

    <div role="tabpanel" x-show="pestana === 'historial'" x-cloak>
        <?php if ($historial === []): ?>
            <p class="text-body-secondary small">Sin operativos en los últimos 30 días.</p>
        <?php else: ?>
            <?php $mostrar_grupos($grupos_historial, $dias_historial_visibles); ?>
            <?php if (count($grupos_historial) > $dias_historial_visibles): ?>
                <button type="button" class="btn btn-sm btn-outline-institucional w-100" x-show="!mas" @click="mas = true">
                    <i class="bi bi-chevron-down"></i> Ver más (hasta 30 días)
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
