<?php
/**
 * SUPERVISOR y ADMIN: tablero de cumplimiento (docs/04). Matriz de unidades
 * activas × 4 turnos de un servicio diario, con poco texto: el color dice el
 * estado y cada operativo se muestra con su hora de inicio. Un turno sin
 * operativos es SIN_PROGRAMAR (amarillo) cuando ya no se puede programar.
 * "Solo alertas" deja solo las unidades con algo pendiente.
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
$solo_alertas = ($_GET['alertas'] ?? '') === '1';

const TURNOS_ABREVIADOS = ['MANANA' => 'MAÑ', 'TARDE' => 'TAR', 'NOCHE' => 'NOC', 'MADRUGADA' => 'MAD'];
const ESTADOS_ALERTA = ['NO_INICIADO', 'SIN_CIERRE', 'SIN_PROGRAMAR'];

$unidades = array_values(array_filter(
    listar_unidades_activas(),
    static fn (array $u): bool => $division === '' || $u['division'] === $division,
));
// Un turno puede tener uno o más operativos (RN-01): cada celda es una lista.
$por_unidad = [];
foreach (listar_operativos_del_servicio($fecha) as $operativo) {
    $por_unidad[(int) $operativo['unidad_id']][$operativo['turno']][] = $operativo;
}

// Celdas y totales (de todas las unidades de la división, aunque se filtren las alertas).
$celdas = [];
$con_alerta = [];
$totales = array_fill_keys(array_keys(ETIQUETAS_ESTADO), 0);
foreach ($unidades as $unidad) {
    foreach (TURNOS as $turno) {
        $operativos = [];
        foreach ($por_unidad[(int) $unidad['id']][$turno] ?? [] as $operativo) {
            $estado = calcular_estado_visible($operativo, $ahora);
            $operativos[] = ['operativo' => $operativo, 'estado' => $estado];
            $totales[$estado]++;
            if (in_array($estado, ESTADOS_ALERTA, true)) {
                $con_alerta[$unidad['id']] = true;
            }
        }
        $estado_vacio = $operativos === []
            ? calcular_estado_celda(null, $fecha, $turno, $ahora, TOLERANCIA_NO_INICIADO_MIN, TOLERANCIA_SIN_CIERRE_MIN)
            : null;
        if ($estado_vacio !== null) {
            $totales[$estado_vacio]++;
            $con_alerta[$unidad['id']] = true;
        }
        $celdas[$unidad['id']][$turno] = ['operativos' => $operativos, 'estado_vacio' => $estado_vacio];
    }
}
$filas = $solo_alertas
    ? array_values(array_filter($unidades, static fn (array $u): bool => isset($con_alerta[$u['id']])))
    : $unidades;

$dia = validar_fecha($fecha);
$dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$fecha_corta = $dias[(int) $dia->format('w')] . ' ' . $dia->format('d/m');
$url = static fn (array $cambios = []): string => armar_url('supervisor/tablero', array_filter(
    array_merge(['fecha' => $fecha, 'division' => $division, 'alertas' => $solo_alertas ? '1' : ''], $cambios),
    static fn ($v): bool => $v !== '' && $v !== null,
));

$titulo = 'Tablero';
require RUTA_APP . '/vistas/cabecera.php';
?>
<!-- Título y fecha -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0 me-auto">Tablero</h1>
    <div class="selector-fecha">
        <a class="btn btn-sm btn-outline-institucional" href="<?= e($url(['fecha' => $dia->modify('-1 day')->format('Y-m-d')])) ?>" aria-label="Día anterior">
            <i class="bi bi-chevron-left"></i>
        </a>
        <form method="get" action="<?= e(armar_url('supervisor/tablero')) ?>" class="fecha-tocable">
            <?php if ($division !== ''): ?><input type="hidden" name="division" value="<?= e($division) ?>"><?php endif; ?>
            <?php if ($solo_alertas): ?><input type="hidden" name="alertas" value="1"><?php endif; ?>
            <span class="fw-semibold"><?= e($fecha_corta) ?></span>
            <!-- Encima del texto, invisible: tocar la fecha abre el calendario del dispositivo. -->
            <input type="date" name="fecha" value="<?= e($fecha) ?>" onchange="this.form.submit()" aria-label="Elegir fecha">
        </form>
        <a class="btn btn-sm btn-outline-institucional" href="<?= e($url(['fecha' => $dia->modify('+1 day')->format('Y-m-d')])) ?>" aria-label="Día siguiente">
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php if ($fecha !== $vigente): ?>
            <a class="btn btn-sm btn-institucional" href="<?= e($url(['fecha' => $vigente])) ?>">Hoy</a>
        <?php endif; ?>
    </div>
</div>

<!-- División y "Solo alertas" -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="chips">
        <a class="chip<?= $division === '' ? ' activo' : '' ?>" href="<?= e($url(['division' => ''])) ?>">Todas</a>
        <?php foreach (array_keys(DIVISIONES) as $clave): ?>
            <a class="chip<?= $division === $clave ? ' activo' : '' ?>" href="<?= e($url(['division' => $clave])) ?>"><?= e($clave) ?></a>
        <?php endforeach; ?>
    </div>
    <a class="chip chip-alertas ms-auto<?= $solo_alertas ? ' activo' : '' ?>" href="<?= e($url(['alertas' => $solo_alertas ? '' : '1'])) ?>"
       aria-pressed="<?= $solo_alertas ? 'true' : 'false' ?>">
        <i class="bi <?= $solo_alertas ? 'bi-check-square-fill' : 'bi-square' ?>"></i> Solo alertas
    </a>
</div>

<!-- Resumen: una tarjeta pequeña por estado (hace de leyenda); 3 por fila en el celular, 6 en la PC -->
<div class="cifras-estado mb-3">
    <?php foreach (['EN_CURSO', 'SIN_CIERRE', 'NO_INICIADO', 'PROGRAMADO', 'FINALIZADO', 'SIN_PROGRAMAR'] as $estado): ?>
        <div class="cifra-estado">
            <strong><?= $totales[$estado] ?></strong>
            <span class="etiqueta-estado"><?= e(obtener_etiqueta_estado($estado)) ?></span>
            <span class="franja-estado <?= e(obtener_clase_estado($estado)) ?>"></span>
        </div>
    <?php endforeach; ?>
</div>

<!-- Matriz: encabezado y columna de unidades fijos -->
<div class="tablero-contenedor">
    <table class="tablero-compacto">
        <thead>
            <tr>
                <th scope="col" class="col-unidad">Unidad</th>
                <?php foreach (TURNOS as $turno): ?>
                    <th scope="col" title="<?= e(obtener_etiqueta_turno($turno)) ?>"><?= e(TURNOS_ABREVIADOS[$turno]) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $division_actual = null; ?>
            <?php foreach ($filas as $unidad): ?>
                <?php if ($unidad['division'] !== $division_actual): ?>
                    <?php $division_actual = $unidad['division']; ?>
                    <tr class="fila-division"><th colspan="5" scope="colgroup"><?= e(DIVISIONES[$division_actual]) ?></th></tr>
                <?php endif; ?>
                <tr>
                    <th scope="row" class="col-unidad"><?= e($unidad['nombre_corto']) ?></th>
                    <?php foreach (TURNOS as $turno): ?>
                        <?php ['operativos' => $operativos, 'estado_vacio' => $estado_vacio] = $celdas[$unidad['id']][$turno]; ?>
                        <td>
                            <?php if ($operativos !== []): ?>
                                <?php foreach ($operativos as ['operativo' => $operativo, 'estado' => $estado]): ?>
                                    <a href="<?= e(armar_url('supervisor/operativo', ['id' => $operativo['id']])) ?>"
                                       class="pildora-operativo <?= e(obtener_clase_estado($estado)) ?><?= pendiente_nota($operativo) ? ' sin-ni' : '' ?>"
                                       title="<?= e(obtener_etiqueta_estado($estado) . (pendiente_nota($operativo) ? ' · sin N.I' : '') . ' · ' . $operativo['lugar_programado']) ?>">
                                        <?= e(formatear_hora($operativo['inicio_programado'])) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php elseif ($estado_vacio === 'SIN_PROGRAMAR'): ?>
                                <span class="pildora-operativo <?= e(obtener_clase_estado('SIN_PROGRAMAR')) ?> vacia" title="Sin programar"></span>
                            <?php else: ?>
                                <span class="celda-pendiente" title="Todavía puede programarse">·</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($filas === []): ?>
                <tr><td colspan="5" class="text-center text-body-secondary py-4">
                    <?= $solo_alertas ? 'Sin alertas en este servicio diario.' : 'No hay unidades activas.' ?>
                </td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
