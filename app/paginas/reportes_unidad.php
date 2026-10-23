<?php
/**
 * SUPERVISOR y ADMIN: reporte de resultados de una unidad en un período (RN-22a,
 * docs/04), pestaña "Por unidad" de Reportes. Es el mismo reporte de "Mis
 * resultados" de la UNIDAD, con un selector de unidad (también las inactivas,
 * porque tienen historial). La unidad viene de la URL: solo se acepta una que exista.
 */
declare(strict_types=1);

$usuario = requiere_rol('SUPERVISOR', 'ADMIN');
$ahora = obtener_ahora();
['desde' => $desde, 'hasta' => $hasta, 'atajo' => $atajo] = calcular_periodo_resultados($_GET, $ahora);

$unidades = listar_unidades();
$unidad_id = obtener_id_get('unidad');
$unidad = null;
foreach ($unidades as $candidata) {
    if ((int) $candidata['id'] === $unidad_id) {
        $unidad = $candidata;
        break;
    }
}

if ($unidad !== null) {
    $reporte = armar_resultados_unidad((int) $unidad['id'], $desde, $hasta, $ahora);
    $unidad_nombre = $unidad['nombre_corto'];
    $generado_por = $usuario['nombre'];
    $en_pantalla = true;
    $ruta_ficha = 'supervisor/operativo';
}

// Activas agrupadas por división, en el orden del tablero; las inactivas al final.
$grupos = [];
foreach ($unidades as $candidata) {
    $grupo = $candidata['activo'] ? (DIVISIONES[$candidata['division']] ?? $candidata['division']) : 'Unidades inactivas';
    $grupos[$grupo][] = $candidata;
}

$titulo = 'Reporte por unidad';
$estilos = ['assets/css/reporte.css'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto">Reportes</h1>
    <?php if ($unidad !== null): ?>
        <a href="<?= e(armar_url('reportes/unidad/pdf', ['unidad' => $unidad['id'], 'desde' => $desde, 'hasta' => $hasta])) ?>" class="btn btn-institucional">
            <i class="bi bi-file-earmark-arrow-down"></i> Descargar PDF
        </a>
    <?php endif; ?>
</div>

<?php require RUTA_APP . '/vistas/pestanas_reportes.php'; ?>

<!-- Barra de filtros: otro fondo y sin sombra, para que no parezca parte del reporte. -->
<form method="get" action="<?= e(armar_url('reportes/unidad')) ?>" class="barra-filtros mb-4">
    <div class="barra-filtros-titulo"><i class="bi bi-funnel"></i> Filtrar</div>
    <div class="mb-2">
        <label for="unidad" class="form-label small mb-1">Unidad</label>
        <select id="unidad" name="unidad" class="form-select" required>
            <option value="">Elija una unidad…</option>
            <?php foreach ($grupos as $nombre_grupo => $unidades_grupo): ?>
                <optgroup label="<?= e($nombre_grupo) ?>">
                    <?php foreach ($unidades_grupo as $opcion): ?>
                        <option value="<?= e($opcion['id']) ?>"<?= $unidad !== null && (int) $opcion['id'] === (int) $unidad['id'] ? ' selected' : '' ?>>
                            <?= e($opcion['nombre_corto']) ?><?= $opcion['activo'] ? '' : ' (inactiva)' ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($unidad !== null): // Los atajos cambian el período de la unidad elegida. ?>
        <div class="chips mb-2" role="group" aria-label="Períodos frecuentes">
            <?php foreach (PERIODOS_RESULTADOS as $clave => $etiqueta): ?>
                <a href="<?= e(armar_url('reportes/unidad', ['unidad' => $unidad['id'], 'periodo' => $clave])) ?>" class="chip<?= $atajo === $clave ? ' activo' : '' ?>"
                   <?= $atajo === $clave ? 'aria-current="true"' : '' ?>><?= e($etiqueta) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-sm-4">
            <label for="desde" class="form-label small mb-1">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= e($desde) ?>" class="form-control" required>
        </div>
        <div class="col-6 col-sm-4">
            <label for="hasta" class="form-label small mb-1">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= e($hasta) ?>" class="form-control" required>
        </div>
        <div class="col-sm-4">
            <button type="submit" class="btn btn-outline-institucional w-100"><i class="bi bi-arrow-repeat"></i> Ver reporte</button>
        </div>
    </div>
    <div class="form-text">Fechas de servicio diario (de las 06:00 a las 05:59 del día siguiente). Hasta un año. Solo operativos finalizados.</div>
</form>

<?php if ($unidad === null): ?>
    <p class="text-body-secondary">
        <i class="bi bi-building"></i>
        <?= $unidad_id === null ? 'Elija una unidad para ver sus resultados.' : 'La unidad elegida no existe. Elija otra de la lista.' ?>
    </p>
<?php else: ?>
    <div class="card card-body shadow-sm mb-4">
        <?php require RUTA_APP . '/vistas/reporte_resultados.php'; ?>
    </div>
<?php endif; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
