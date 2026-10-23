<?php
/**
 * UNIDAD: "Mis resultados" (RN-22, docs/04). Resultados de sus operativos finalizados
 * en un período (atajos o rango de servicios diarios), en pantalla y en PDF.
 * Filtrado por la unidad de la sesión (RN-16).
 */
declare(strict_types=1);

$usuario = requiere_rol('UNIDAD');
$ahora = obtener_ahora();
['desde' => $desde, 'hasta' => $hasta, 'atajo' => $atajo] = calcular_periodo_resultados($_GET, $ahora);
$reporte = armar_resultados_unidad(unidad_de_sesion(), $desde, $hasta, $ahora);
$unidad_nombre = $usuario['unidad_nombre'];
$generado_por = $usuario['nombre'];
$en_pantalla = true;

$titulo = 'Mis resultados';
$estilos = ['assets/css/reporte.css'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto">Mis resultados</h1>
    <a href="<?= e(armar_url('resultados/pdf', ['desde' => $desde, 'hasta' => $hasta])) ?>" class="btn btn-institucional">
        <i class="bi bi-file-earmark-arrow-down"></i> Descargar PDF
    </a>
</div>

<!-- Barra de filtros: otro fondo y sin sombra, para que no parezca parte del reporte. -->
<form method="get" action="<?= e(armar_url('resultados')) ?>" class="barra-filtros mb-4">
    <div class="barra-filtros-titulo"><i class="bi bi-funnel"></i> Período</div>
    <div class="chips mb-2" role="group" aria-label="Períodos frecuentes">
        <?php foreach (PERIODOS_RESULTADOS as $clave => $etiqueta): ?>
            <a href="<?= e(armar_url('resultados', ['periodo' => $clave])) ?>" class="chip<?= $atajo === $clave ? ' activo' : '' ?>"
               <?= $atajo === $clave ? 'aria-current="true"' : '' ?>><?= e($etiqueta) ?></a>
        <?php endforeach; ?>
    </div>
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
            <button type="submit" class="btn btn-outline-institucional w-100"><i class="bi bi-arrow-repeat"></i> Ver resultados</button>
        </div>
    </div>
    <div class="form-text">Fechas de servicio diario (de las 06:00 a las 05:59 del día siguiente). Hasta un año.</div>
</form>

<div class="card card-body shadow-sm mb-4">
    <?php require RUTA_APP . '/vistas/reporte_resultados.php'; ?>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
