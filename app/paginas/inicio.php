<?php
/**
 * Pantalla de inicio según el rol: la UNIDAD ve sus operativos, el SUPERVISOR
 * el mapa y ADMIN un resumen del servicio diario con lo que requiere atención.
 */
declare(strict_types=1);

$usuario = requiere_login();
if ($usuario['rol'] === 'UNIDAD') {
    require RUTA_APP . '/paginas/mis_operativos.php';
    return;
}
if ($usuario['rol'] === 'SUPERVISOR') {
    require RUTA_APP . '/paginas/supervisor_mapa.php';
    return;
}

// ADMIN: resumen del servicio diario vigente, con la misma matriz que el reporte
// (unidades activas; un turno puede tener uno o más operativos, RN-01).
$ahora = obtener_ahora();
$vigente = fecha_servicio_vigente($ahora);
$matriz = armar_matriz_servicio(
    listar_unidades_activas(),
    listar_operativos_del_servicio($vigente),
    $vigente,
    $ahora,
    TOLERANCIA_NO_INICIADO_MIN,
    TOLERANCIA_SIN_CIERRE_MIN,
);
$totales = $matriz['totales'];
$cifras = contar_realizados_por_division($matriz);

// Alertas (no iniciados y sin cierre) y operativos por división, para el avance.
$alertas = [];
$programados_division = array_fill_keys(array_keys($cifras['divisiones']), 0);
foreach ($matriz['filas'] as $fila) {
    $programados_division[$fila['unidad']['division']] += $fila['total'];
    foreach ($fila['turnos'] as $celda) {
        foreach ($celda['operativos'] as ['operativo' => $operativo, 'estado' => $estado]) {
            if (in_array($estado, ['NO_INICIADO', 'SIN_CIERRE'], true)) {
                $alertas[] = ['operativo' => $operativo, 'estado' => $estado];
            }
        }
    }
}

$sin_cuenta = (int) consultar(
    'SELECT COUNT(*) FROM unidades un LEFT JOIN usuarios u ON u.unidad_id = un.id WHERE un.activo = 1 AND u.id IS NULL',
)->fetchColumn();
$bloqueadas = (int) consultar('SELECT COUNT(*) FROM usuarios WHERE bloqueado_hasta > :ahora', ['ahora' => $ahora->format(FORMATO_FECHA_HORA)])->fetchColumn();
$hace_7_dias = validar_fecha($vigente)->modify('-7 days')->format('Y-m-d');
$sin_nota = (int) consultar(
    "SELECT COUNT(*) FROM operativos WHERE estado = 'FINALIZADO' AND (nota_informativa IS NULL OR nota_informativa = '')
     AND fecha_servicio BETWEEN :desde AND :hasta",
    ['desde' => $hace_7_dias, 'hasta' => $vigente],
)->fetchColumn();

// Tarjetas de estado: primero lo que avanza, luego lo que requiere atención.
$tarjetas_estado = [
    'EN_CURSO'      => 'broadcast',
    'FINALIZADO'    => 'check2-circle',
    'PROGRAMADO'    => 'clock',
    'NO_INICIADO'   => 'exclamation-octagon',
    'SIN_CIERRE'    => 'hourglass-bottom',
    'SIN_PROGRAMAR' => 'calendar-x',
];
$estados_problema = ['NO_INICIADO', 'SIN_CIERRE', 'SIN_PROGRAMAR'];
$pendientes = [
    ['Finalizados sin N.I', 'Últimos 7 días', 'file-earmark-text', $sin_nota, 'primary',
     armar_url('admin/operativos', ['estado' => 'PENDIENTE_NOTA', 'desde' => $hace_7_dias, 'hasta' => $vigente])],
    ['Unidades sin cuenta', 'Unidades activas', 'person-x', $sin_cuenta, 'warning', armar_url('admin/usuarios')],
    ['Cuentas bloqueadas', 'En este momento', 'lock', $bloqueadas, 'danger', armar_url('admin/usuarios')],
];
$accesos = [
    ['supervisor/tablero', 'Tablero', 'grid-3x3'],
    ['admin/operativos/editar', 'Programar', 'calendar-plus'],
    ['admin/operativos', 'Operativos', 'list-ul'],
    ['reportes', 'Reportes', 'file-earmark-text'],
    ['admin/unidades', 'Unidades', 'building'],
    ['admin/usuarios', 'Usuarios', 'people'],
];

$titulo = 'Inicio';
require RUTA_APP . '/vistas/cabecera.php';
?>
<h1 class="h4 mb-1">Bienvenido, <?= e($usuario['nombre']) ?></h1>
<h2 class="titulo-seccion titulo-seccion-principal">
    <i class="bi bi-calendar-day"></i> Servicio del día <?= e(formatear_servicio_diario(validar_fecha($vigente))) ?>
    <span class="titulo-seccion-detalle">· <?= e($ahora->format('H:i')) ?></span>
</h2>

<!-- Estados del servicio: los de problema solo resaltan cuando hay alguno. -->
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-2 mb-4">
    <?php foreach ($tarjetas_estado as $estado => $icono): ?>
        <?php $cantidad = $totales[$estado]; ?>
        <div class="col">
            <a class="inicio-estado tarjeta-<?= e(strtolower($estado)) ?><?= $cantidad === 0 ? ' en-cero' : '' ?><?= $cantidad > 0 && in_array($estado, $estados_problema, true) ? ' con-problema' : '' ?>"
               href="<?= e(armar_url('supervisor/tablero')) ?>">
                <i class="bi bi-<?= e($icono) ?> inicio-estado-icono" aria-hidden="true"></i>
                <span class="inicio-estado-cifra"><?= $cantidad ?></span>
                <span class="inicio-estado-etiqueta"><?= e(obtener_etiqueta_estado($estado)) ?></span>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle <?= $alertas === [] ? 'text-body-secondary' : 'text-danger' ?>"></i>
                Requiere atención
                <?php if ($alertas !== []): ?><span class="badge text-bg-danger rounded-pill"><?= count($alertas) ?></span><?php endif; ?>
            </div>
            <?php if ($alertas === []): ?>
                <div class="card-body inicio-vacio">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    <div>
                        <strong>Todo en orden</strong>
                        <span class="d-block small text-body-secondary">Ningún operativo no iniciado ni sin cierre.</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($alertas as ['operativo' => $operativo, 'estado' => $estado]): ?>
                        <a class="list-group-item list-group-item-action d-flex flex-wrap align-items-center gap-2"
                           href="<?= e(armar_url('admin/operativos/editar', ['id' => $operativo['id']])) ?>">
                            <span class="me-auto">
                                <strong><?= e($operativo['unidad_nombre']) ?></strong>
                                <span class="d-block small text-body-secondary"><?= e(obtener_etiqueta_turno($operativo['turno'])) ?> ·
                                    <?= e(formatear_hora($operativo['inicio_programado'])) ?>–<?= e(formatear_hora($operativo['fin_programado'])) ?></span>
                            </span>
                            <?= mostrar_estado($estado) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5 d-flex flex-column gap-3">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-inbox"></i> Pendientes</div>
            <div class="list-group list-group-flush">
                <?php foreach ($pendientes as [$texto, $detalle, $icono, $cantidad, $color, $url]): ?>
                    <a class="list-group-item list-group-item-action inicio-pendiente<?= $cantidad === 0 ? ' en-cero' : '' ?>" href="<?= e($url) ?>">
                        <i class="bi bi-<?= e($icono) ?> inicio-pendiente-icono" aria-hidden="true"></i>
                        <span class="me-auto"><?= e($texto) ?><span class="d-block small text-body-secondary"><?= e($detalle) ?></span></span>
                        <span class="badge rounded-pill text-bg-<?= $cantidad === 0 ? 'light' : e($color) ?> fs-6"><?= $cantidad ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Avance: realizados (en curso o finalizados, como en el reporte) sobre los programados de cada división. -->
        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart"></i> Avance por división</span>
                <span class="text-body-secondary fw-normal small"><?= $cifras['realizados'] ?> de <?= $matriz['operativos'] ?> realizados</span>
            </div>
            <div class="card-body">
                <?php foreach ($cifras['divisiones'] as $division => $realizados): ?>
                    <?php $programados = $programados_division[$division]; ?>
                    <div class="inicio-avance division-<?= e(strtolower($division)) ?>">
                        <div class="d-flex justify-content-between small mb-1">
                            <strong><?= e($division) ?></strong>
                            <span class="text-body-secondary"><?= $realizados ?> / <?= $programados ?></span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Avance de <?= e($division) ?>"
                             aria-valuenow="<?= $realizados ?>" aria-valuemin="0" aria-valuemax="<?= $programados ?>">
                            <div class="progress-bar" style="width: <?= $programados > 0 ? round($realizados * 100 / $programados) : 0 ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<h2 class="titulo-seccion">Accesos rápidos</h2>
<div class="row row-cols-3 row-cols-md-6 g-2">
    <?php foreach ($accesos as [$ruta, $texto, $icono]): ?>
        <div class="col">
            <a class="inicio-acceso" href="<?= e(armar_url($ruta)) ?>">
                <i class="bi bi-<?= e($icono) ?>" aria-hidden="true"></i><?= e($texto) ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
