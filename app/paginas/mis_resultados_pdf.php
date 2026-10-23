<?php
/**
 * UNIDAD: descarga de "Mis resultados" en PDF (A4 vertical), con el mismo período y
 * estilos que /resultados. Filtrado por la unidad de la sesión (RN-16). Queda en la bitácora.
 */
declare(strict_types=1);

$usuario = requiere_rol('UNIDAD');
$ahora = obtener_ahora();
['desde' => $desde, 'hasta' => $hasta] = calcular_periodo_resultados($_GET, $ahora);
$reporte = armar_resultados_unidad(unidad_de_sesion(), $desde, $hasta, $ahora);
$unidad_nombre = $usuario['unidad_nombre'];
$generado_por = $usuario['nombre'];
$en_pantalla = false;
$ruta_ficha = 'operativos/ver';

ob_start();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resultados de operativos <?= e($desde) ?> al <?= e($hasta) ?></title>
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

registrar_bitacora('REPORTE_PDF', null, null, ['reporte' => 'resultados_unidad', 'desde' => $desde, 'hasta' => $hasta]);
enviar_pdf($html, nombre_archivo_resultados($unidad_nombre, $desde, $hasta));
