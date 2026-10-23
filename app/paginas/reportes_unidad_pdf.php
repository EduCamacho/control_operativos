<?php
/**
 * SUPERVISOR y ADMIN: descarga en PDF (A4 vertical) del reporte de resultados de una
 * unidad (RN-22a), con el mismo período y estilos que /reportes/unidad. Queda en la bitácora.
 */
declare(strict_types=1);

$usuario = requiere_rol('SUPERVISOR', 'ADMIN');
$ahora = obtener_ahora();
['desde' => $desde, 'hasta' => $hasta] = calcular_periodo_resultados($_GET, $ahora);

$unidad_id = obtener_id_get('unidad');
$unidad = null;
foreach (listar_unidades() as $candidata) {
    if ((int) $candidata['id'] === $unidad_id) {
        $unidad = $candidata;
        break;
    }
}
if ($unidad === null) {
    guardar_mensaje('danger', 'Elija una unidad para descargar su reporte.');
    redirigir('reportes/unidad', ['desde' => $desde, 'hasta' => $hasta]);
}

$reporte = armar_resultados_unidad((int) $unidad['id'], $desde, $hasta, $ahora);
$unidad_nombre = $unidad['nombre_corto'];
$generado_por = $usuario['nombre'];
$en_pantalla = false;
$ruta_ficha = 'supervisor/operativo';

ob_start();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resultados de operativos <?= e($unidad_nombre) ?> <?= e($desde) ?> al <?= e($hasta) ?></title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        <?= file_get_contents(dirname(RUTA_APP) . '/public/assets/css/reporte.css') ?>
    </style>
</head>
<body>
<?php require RUTA_APP . '/vistas/reporte_resultados.php'; ?>
</body>
</html>
<?php
$html = (string) ob_get_clean();

registrar_bitacora('REPORTE_PDF', null, null, ['reporte' => 'resultados_unidad', 'unidad_id' => (int) $unidad['id'], 'desde' => $desde, 'hasta' => $hasta]);
enviar_pdf($html, nombre_archivo_resultados($unidad_nombre, $desde, $hasta));
