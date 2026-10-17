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

// ADMIN: resumen del servicio diario vigente.
$ahora = obtener_ahora();
$vigente = fecha_servicio_vigente($ahora);
$unidades = listar_unidades_activas();
// Un turno puede tener uno o más operativos (RN-01).
$por_unidad = [];
foreach (listar_operativos_del_servicio($vigente) as $operativo) {
    $por_unidad[(int) $operativo['unidad_id']][$operativo['turno']][] = $operativo;
}
$totales = array_fill_keys(array_keys(ETIQUETAS_ESTADO), 0);
$alertas = [];
foreach ($unidades as $unidad) {
    foreach (TURNOS as $turno) {
        $operativos = $por_unidad[(int) $unidad['id']][$turno] ?? [];
        if ($operativos === []) {
            // Turno sin ningún operativo: SIN_PROGRAMAR solo si ya no se puede programar.
            $estado = calcular_estado_celda(null, $vigente, $turno, $ahora, TOLERANCIA_NO_INICIADO_MIN, TOLERANCIA_SIN_CIERRE_MIN);
            if ($estado !== null) {
                $totales[$estado]++;
            }
            continue;
        }
        foreach ($operativos as $operativo) {
            $estado = calcular_estado_visible($operativo, $ahora);
            $totales[$estado]++;
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
$sin_nota = (int) consultar(
    "SELECT COUNT(*) FROM operativos WHERE estado = 'FINALIZADO' AND (nota_informativa IS NULL OR nota_informativa = '')
     AND fecha_servicio BETWEEN :desde AND :hasta",
    ['desde' => validar_fecha($vigente)->modify('-7 days')->format('Y-m-d'), 'hasta' => $vigente],
)->fetchColumn();

$titulo = 'Inicio';
require RUTA_APP . '/vistas/cabecera.php';
?>
<h1 class="h4 mb-0">Bienvenido, <?= e($usuario['nombre']) ?></h1>
<p class="text-body-secondary mb-3">
    Servicio diario del <strong><?= e(formatear_fecha_larga(validar_fecha($vigente))) ?></strong> · <?= e($ahora->format('H:i')) ?>
</p>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($totales as $estado => $cantidad): ?>
        <a class="badge <?= e(obtener_clase_estado($estado)) ?> fs-6 fw-normal text-decoration-none" href="<?= e(armar_url('supervisor/tablero')) ?>">
            <?= e(obtener_etiqueta_estado($estado)) ?>: <?= $cantidad ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-exclamation-triangle text-danger"></i> Alertas del servicio diario
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($alertas as ['operativo' => $operativo, 'estado' => $estado]): ?>
                    <a class="list-group-item list-group-item-action d-flex flex-wrap align-items-center gap-2"
                       href="<?= e(armar_url('admin/operativos/editar', ['id' => $operativo['id']])) ?>">
                        <span class="me-auto">
                            <strong><?= e($operativo['unidad_nombre']) ?></strong>
                            <span class="small text-body-secondary">· <?= e(obtener_etiqueta_turno($operativo['turno'])) ?>
                                <?= e(formatear_hora($operativo['inicio_programado'])) ?>–<?= e(formatear_hora($operativo['fin_programado'])) ?></span>
                        </span>
                        <?= mostrar_estado($estado) ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($alertas === []): ?>
                    <div class="list-group-item text-body-secondary">Sin operativos no iniciados ni sin cierre.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="list-group shadow-sm">
            <a class="list-group-item list-group-item-action d-flex justify-content-between" href="<?= e(armar_url('admin/operativos', ['estado' => 'PENDIENTE_NOTA', 'desde' => validar_fecha($vigente)->modify('-7 days')->format('Y-m-d'), 'hasta' => $vigente])) ?>">
                Finalizados sin N.I (últimos 7 días) <span class="badge text-bg-<?= $sin_nota ? 'primary' : 'light' ?>"><?= $sin_nota ?></span>
            </a>
            <a class="list-group-item list-group-item-action d-flex justify-content-between" href="<?= e(armar_url('admin/usuarios')) ?>">
                Unidades activas sin cuenta <span class="badge text-bg-<?= $sin_cuenta ? 'warning' : 'light' ?>"><?= $sin_cuenta ?></span>
            </a>
            <a class="list-group-item list-group-item-action d-flex justify-content-between" href="<?= e(armar_url('admin/usuarios')) ?>">
                Cuentas bloqueadas ahora <span class="badge text-bg-<?= $bloqueadas ? 'danger' : 'light' ?>"><?= $bloqueadas ?></span>
            </a>
            <a class="list-group-item list-group-item-action" href="<?= e(armar_url('supervisor/tablero')) ?>"><i class="bi bi-grid-3x3"></i> Tablero de cumplimiento</a>
            <a class="list-group-item list-group-item-action" href="<?= e(armar_url('admin/operativos/editar')) ?>"><i class="bi bi-calendar-plus"></i> Programar en nombre de una unidad</a>
        </div>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
