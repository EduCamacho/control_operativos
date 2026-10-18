<?php
/**
 * SUPERVISOR y ADMIN: descarga del reporte del servicio diario en PDF (A4
 * vertical), con los mismos filtros y estilos que /reportes. Queda en la bitácora.
 */
declare(strict_types=1);

$usuario = requiere_rol('SUPERVISOR', 'ADMIN');
$ahora = obtener_ahora();
['fecha' => $fecha, 'division' => $division] = leer_filtros_reporte($_GET, $ahora);
$reporte = armar_reporte_servicio($fecha, $division, $ahora);
$generado_por = $usuario['nombre'];
$con_estado = $usuario['rol'] === 'ADMIN'; // "Estado de los operativos" solo para el ADMIN

ob_start();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte del servicio diario <?= e($fecha) ?></title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        <?= file_get_contents(dirname(RUTA_APP) . '/public/assets/css/reporte.css') ?>
    </style>
</head>
<body>
<?php require RUTA_APP . '/vistas/reporte_servicio.php'; ?>
</body>
</html>
<?php
$html = (string) ob_get_clean();

registrar_bitacora('REPORTE_PDF', null, null, ['reporte' => 'servicio_diario', 'fecha' => $fecha, 'division' => $division]);
enviar_pdf($html, nombre_archivo_reporte($fecha, $division));
