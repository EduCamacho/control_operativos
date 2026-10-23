<?php
/**
 * SUPERVISOR y ADMIN: reporte del servicio diario (docs/04). Filtros de fecha
 * y división, el reporte en pantalla y el botón para descargarlo en PDF.
 * La pestaña "Por unidad" es reportes_unidad.php.
 * No usa la API de Google Maps (RN-21).
 */
declare(strict_types=1);

$usuario = requiere_rol('SUPERVISOR', 'ADMIN');
$ahora = obtener_ahora();
['fecha' => $fecha, 'division' => $division] = leer_filtros_reporte($_GET, $ahora);
$reporte = armar_reporte_servicio($fecha, $division, $ahora);
$generado_por = $usuario['nombre'];
$con_estado = $usuario['rol'] === 'ADMIN'; // "Estado de los operativos" solo para el ADMIN
$parametros = array_filter(['fecha' => $fecha, 'division' => $division]);

$titulo = 'Reportes';
$estilos = ['assets/css/reporte.css'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto">Reportes</h1>
    <a href="<?= e(armar_url('reportes/pdf', $parametros)) ?>" class="btn btn-institucional">
        <i class="bi bi-file-earmark-arrow-down"></i> Descargar PDF
    </a>
</div>

<?php require RUTA_APP . '/vistas/pestanas_reportes.php'; ?>

<!-- Barra de filtros: otro fondo y sin sombra, para que no parezca parte del reporte. -->
<form method="get" action="<?= e(armar_url('reportes')) ?>" class="barra-filtros mb-4">
    <div class="barra-filtros-titulo"><i class="bi bi-funnel"></i> Filtrar</div>
    <div class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label for="fecha" class="form-label small mb-1">Servicio diario</label>
            <input type="date" id="fecha" name="fecha" value="<?= e($fecha) ?>" class="form-control" required>
        </div>
        <div class="col-sm-4">
            <label for="division" class="form-label small mb-1">División</label>
            <select id="division" name="division" class="form-select">
                <option value="">Todas</option>
                <?php foreach (DIVISIONES as $clave => $nombre): ?>
                    <option value="<?= e($clave) ?>"<?= $clave === $division ? ' selected' : '' ?>><?= e($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4">
            <button type="submit" class="btn btn-outline-institucional w-100"><i class="bi bi-arrow-repeat"></i> Ver reporte</button>
        </div>
    </div>
    <div class="form-text">El servicio diario va de las 06:00 a las 05:59 del día siguiente.</div>
</form>

<div class="card card-body shadow-sm mb-4">
    <?php require RUTA_APP . '/vistas/reporte_servicio.php'; ?>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
