<?php
/**
 * SUPERVISOR y ADMIN: tablero de cumplimiento (docs/04). Matriz de unidades
 * activas × 4 turnos de un servicio diario, cada celda con el color de su estado.
 * Una celda vacía es SIN_PROGRAMAR (amarillo) cuando ya no se puede programar.
 * No usa la API de Google Maps (RN-21).
 */
declare(strict_types=1);

requiere_rol('SUPERVISOR', 'ADMIN');
$ahora = obtener_ahora();
$vigente = fecha_servicio_vigente($ahora);

try {
    $fecha = validar_fecha((string) ($_GET['fecha'] ?? $vigente))->format('Y-m-d');
} catch (InvalidArgumentException) {
    $fecha = $vigente;
}
$division = array_key_exists($_GET['division'] ?? '', DIVISIONES) ? $_GET['division'] : '';

$unidades = array_values(array_filter(
    listar_unidades_activas(),
    static fn (array $u): bool => $division === '' || $u['division'] === $division,
));
$por_unidad = [];
foreach (listar_operativos_del_servicio($fecha) as $operativo) {
    $por_unidad[(int) $operativo['unidad_id']][$operativo['turno']] = $operativo;
}

// Celdas y totales por estado.
$celdas = [];
$totales = array_fill_keys(array_keys(ETIQUETAS_ESTADO), 0);
$pendientes = 0;
foreach ($unidades as $unidad) {
    foreach (TURNOS as $turno) {
        $operativo = $por_unidad[(int) $unidad['id']][$turno] ?? null;
        $estado = calcular_estado_celda($operativo, $fecha, $turno, $ahora, TOLERANCIA_NO_INICIADO_MIN, TOLERANCIA_SIN_CIERRE_MIN);
        $celdas[$unidad['id']][$turno] = ['operativo' => $operativo, 'estado' => $estado];
        if ($estado === null) {
            $pendientes++;
        } else {
            $totales[$estado]++;
        }
    }
}
$total_celdas = count($unidades) * count(TURNOS);
$ejecutados = $totales['EN_CURSO'] + $totales['SIN_CIERRE'] + $totales['FINALIZADO'];

$dia = validar_fecha($fecha);
$url_fecha = static fn (string $otra): string => armar_url('supervisor/tablero', array_filter(['fecha' => $otra, 'division' => $division]));

$titulo = 'Tablero';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto">Tablero de cumplimiento</h1>
    <form method="get" action="<?= e(armar_url('supervisor/tablero')) ?>" class="d-flex flex-wrap gap-2">
        <div class="input-group input-group-sm w-auto">
            <a class="btn btn-outline-secondary" href="<?= e($url_fecha($dia->modify('-1 day')->format('Y-m-d'))) ?>" aria-label="Día anterior"><i class="bi bi-chevron-left"></i></a>
            <input type="date" name="fecha" class="form-control" value="<?= e($fecha) ?>" onchange="this.form.submit()" aria-label="Fecha de servicio">
            <a class="btn btn-outline-secondary" href="<?= e($url_fecha($dia->modify('+1 day')->format('Y-m-d'))) ?>" aria-label="Día siguiente"><i class="bi bi-chevron-right"></i></a>
        </div>
        <select name="division" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="División">
            <option value="">Todas las divisiones</option>
            <?php foreach (DIVISIONES as $clave => $etiqueta): ?>
                <option value="<?= e($clave) ?>"<?= marcar($division === $clave, 'selected') ?>><?= e($etiqueta) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($fecha !== $vigente): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= e($url_fecha($vigente)) ?>">Hoy</a>
        <?php endif; ?>
    </form>
</div>

<p class="text-body-secondary mb-2">
    Servicio diario del <strong><?= e(formatear_fecha_larga($dia)) ?></strong>
    <?php if ($fecha === $vigente): ?><span class="badge text-bg-success">vigente</span><?php endif; ?>
</p>

<div class="d-flex flex-wrap gap-2 mb-3 small">
    <span class="badge text-bg-light border fs-6 fw-normal">Ejecutados: <strong><?= $ejecutados ?></strong> de <?= $total_celdas ?></span>
    <?php foreach ($totales as $estado => $cantidad): ?>
        <?php if ($cantidad > 0): ?>
            <span class="badge <?= e(obtener_clase_estado($estado)) ?> fs-6 fw-normal"><?= e(obtener_etiqueta_estado($estado)) ?>: <?= $cantidad ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($pendientes > 0): ?>
        <span class="badge text-bg-light border fs-6 fw-normal text-body-secondary">Aún pueden programarse: <?= $pendientes ?></span>
    <?php endif; ?>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle tablero">
        <thead class="table-light">
            <tr>
                <th scope="col">Unidad</th>
                <?php foreach (TURNOS as $turno): ?>
                    <th scope="col" class="text-center"><?= e(obtener_etiqueta_turno($turno)) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $division_actual = null; ?>
            <?php foreach ($unidades as $unidad): ?>
                <?php if ($unidad['division'] !== $division_actual): ?>
                    <?php $division_actual = $unidad['division']; ?>
                    <tr class="table-group-divider"><th colspan="5" class="small text-uppercase text-body-secondary bg-body-tertiary"><?= e(DIVISIONES[$division_actual]) ?></th></tr>
                <?php endif; ?>
                <tr>
                    <th scope="row" class="text-nowrap small"><?= e($unidad['nombre_corto']) ?></th>
                    <?php foreach (TURNOS as $turno): ?>
                        <?php ['operativo' => $operativo, 'estado' => $estado] = $celdas[$unidad['id']][$turno]; ?>
                        <?php if ($operativo !== null): ?>
                            <td class="p-0 celda-tablero">
                                <a href="<?= e(armar_url('supervisor/operativo', ['id' => $operativo['id']])) ?>"
                                   class="d-block text-center text-decoration-none py-2 px-1 <?= e(obtener_clase_estado($estado)) ?>"
                                   title="<?= e(obtener_etiqueta_estado($estado) . ' · ' . $operativo['lugar_programado']) ?>">
                                    <span class="d-block small fw-semibold"><?= e(obtener_etiqueta_estado($estado)) ?></span>
                                    <span class="d-block small"><?= e(formatear_hora($operativo['inicio_programado'])) ?>–<?= e(formatear_hora($operativo['fin_programado'])) ?><?= pendiente_nota($operativo) ? ' · sin nota' : '' ?></span>
                                </a>
                            </td>
                        <?php elseif ($estado === 'SIN_PROGRAMAR'): ?>
                            <td class="p-0 celda-tablero">
                                <span class="d-block text-center py-2 px-1 small fw-semibold <?= e(obtener_clase_estado($estado)) ?>">Sin programar</span>
                            </td>
                        <?php else: ?>
                            <td class="text-center text-body-secondary small celda-tablero" title="Todavía puede programarse">—</td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($unidades === []): ?>
                <tr><td colspan="5" class="text-center text-body-secondary py-4">No hay unidades activas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
