<?php
/**
 * ADMIN: buscar operativos de cualquier unidad para corregirlos (RN-05, docs/04).
 * Filtros por fechas de servicio, unidad, turno y estado visible.
 */
declare(strict_types=1);

requiere_rol('ADMIN');

const OPERATIVOS_POR_PAGINA = 50;

$ahora = obtener_ahora();
$vigente = fecha_servicio_vigente($ahora);
$fecha_valida = static function (mixed $valor, string $por_defecto): string {
    try {
        return is_string($valor) && $valor !== '' ? validar_fecha($valor)->format('Y-m-d') : $por_defecto;
    } catch (InvalidArgumentException) {
        return $por_defecto;
    }
};

$estados_filtro = ETIQUETAS_ESTADO;
unset($estados_filtro['SIN_PROGRAMAR']);
$estados_filtro['PENDIENTE_NOTA'] = 'Finalizado sin N.I';

$filtros = [
    'desde'     => $fecha_valida($_GET['desde'] ?? null, validar_fecha($vigente)->modify('-7 days')->format('Y-m-d')),
    'hasta'     => $fecha_valida($_GET['hasta'] ?? null, validar_fecha($vigente)->modify('+7 days')->format('Y-m-d')),
    'unidad_id' => obtener_id_get('unidad_id'),
    'turno'     => in_array($_GET['turno'] ?? '', TURNOS, true) ? $_GET['turno'] : '',
    'estado'    => array_key_exists($_GET['estado'] ?? '', $estados_filtro) ? $_GET['estado'] : '',
];
$pagina = validar_entero($_GET['pagina'] ?? 1, 1, PHP_INT_MAX) ?? 1;

$resultado = buscar_operativos($filtros, $ahora, OPERATIVOS_POR_PAGINA, ($pagina - 1) * OPERATIVOS_POR_PAGINA);
$paginas = max(1, (int) ceil($resultado['total'] / OPERATIVOS_POR_PAGINA));
$unidades = consultar('SELECT id, nombre_corto FROM unidades ORDER BY orden, nombre_corto')->fetchAll();
$url_pagina = static fn (int $numero): string => armar_url('admin/operativos', array_filter($filtros + ['pagina' => $numero]));

$titulo = 'Operativos';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Operativos</h1>
    <a href="<?= e(armar_url('admin/operativos/editar')) ?>" class="btn btn-success boton-accion">
        <i class="bi bi-calendar-plus"></i> Programar en nombre de una unidad
    </a>
</div>

<form method="get" action="<?= e(armar_url('admin/operativos')) ?>" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label for="desde" class="form-label small">Desde</label>
            <input type="date" id="desde" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="hasta" class="form-label small">Hasta</label>
            <input type="date" id="hasta" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label for="unidad_id" class="form-label small">Unidad</label>
            <select id="unidad_id" name="unidad_id" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($unidades as $unidad): ?>
                    <option value="<?= e($unidad['id']) ?>"<?= marcar($filtros['unidad_id'] === (int) $unidad['id'], 'selected') ?>><?= e($unidad['nombre_corto']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="turno" class="form-label small">Turno</label>
            <select id="turno" name="turno" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (ETIQUETAS_TURNO as $clave => $etiqueta): ?>
                    <option value="<?= e($clave) ?>"<?= marcar($filtros['turno'] === $clave, 'selected') ?>><?= e($etiqueta) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="estado" class="form-label small">Estado</label>
            <select id="estado" name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($estados_filtro as $clave => $etiqueta): ?>
                    <option value="<?= e($clave) ?>"<?= marcar($filtros['estado'] === $clave, 'selected') ?>><?= e($etiqueta) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-1 d-grid">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i><span class="d-md-none"> Buscar</span></button>
        </div>
    </div>
</form>

<p class="small text-body-secondary">
    <?= $resultado['total'] === 1 ? '1 operativo' : $resultado['total'] . ' operativos' ?>
    <?php if ($paginas > 1): ?>· página <?= min($pagina, $paginas) ?> de <?= $paginas ?><?php endif; ?>
</p>

<div class="list-group mb-3">
    <?php foreach ($resultado['filas'] as $operativo): ?>
        <?php $estado = calcular_estado_visible($operativo, $ahora); ?>
        <a class="list-group-item list-group-item-action d-flex flex-wrap align-items-center gap-2"
           href="<?= e(armar_url('admin/operativos/editar', ['id' => $operativo['id']])) ?>">
            <span class="me-auto">
                <span class="fw-semibold"><?= e($operativo['unidad_nombre']) ?></span>
                <span class="text-body-secondary small">
                    · <?= e(validar_fecha($operativo['fecha_servicio'])->format('d/m/Y')) ?> · <?= e(obtener_etiqueta_turno($operativo['turno'])) ?>
                    · <?= e(formatear_hora($operativo['inicio_programado'])) ?>–<?= e(formatear_hora($operativo['fin_programado'])) ?>
                    · <?= e($operativo['lugar_programado']) ?>
                </span>
            </span>
            <?= mostrar_estado($estado, pendiente_nota($operativo)) ?>
            <i class="bi bi-pencil text-body-secondary"></i>
        </a>
    <?php endforeach; ?>
    <?php if ($resultado['filas'] === []): ?>
        <div class="list-group-item text-body-secondary">No hay operativos con estos filtros.</div>
    <?php endif; ?>
</div>

<?php if ($paginas > 1): ?>
    <nav aria-label="Páginas">
        <ul class="pagination pagination-sm">
            <li class="page-item<?= $pagina <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($url_pagina($pagina - 1)) ?>">Anterior</a></li>
            <li class="page-item active" aria-current="page"><span class="page-link"><?= min($pagina, $paginas) ?></span></li>
            <li class="page-item<?= $pagina >= $paginas ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($url_pagina($pagina + 1)) ?>">Siguiente</a></li>
        </ul>
    </nav>
<?php endif; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
