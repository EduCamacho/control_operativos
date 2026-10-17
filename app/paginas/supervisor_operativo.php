<?php
/**
 * SUPERVISOR y ADMIN: ficha de un operativo de cualquier unidad.
 * Con ?fragmento=1 devuelve solo la ficha, para el panel del mapa.
 * "Volver" lleva al tablero (con su fecha, división y filtro) si se llegó desde
 * ahí; si no, al mapa (SUPERVISOR) o al tablero (ADMIN).
 */
declare(strict_types=1);

$usuario = requiere_rol('SUPERVISOR', 'ADMIN');
$ahora = obtener_ahora();

$id = obtener_id_get();
$operativo = $id === null ? null : obtener_operativo($id);
$es_fragmento = ($_GET['fragmento'] ?? '') === '1';

if ($operativo === null) {
    if ($es_fragmento) {
        http_response_code(404);
        exit('<div class="alert alert-warning">El operativo ya no existe.</div>');
    }
    guardar_mensaje('danger', 'El operativo no existe.');
    redirigir('');
}

$tipos = obtener_tipos_de_operativo($id);
$ubicaciones = obtener_ubicaciones($id);
$puede_controlar = $usuario['rol'] === 'SUPERVISOR' && operativo_controlable($operativo, fecha_servicio_vigente($ahora));
$en_pagina = !$es_fragmento;

if ($es_fragmento) {
    header('Cache-Control: no-store');
    require RUTA_APP . '/vistas/ficha_operativo.php';
    exit;
}

// Volver: al tablero si se llegó desde ahí (mismo sitio), conservando solo sus filtros.
$volver = $usuario['rol'] === 'SUPERVISOR' ? ['', 'Mapa'] : ['supervisor/tablero', 'Tablero'];
$volver_parametros = [];
$origen = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
if (is_array($origen)
    && ($origen['host'] ?? '') === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)
    && ($origen['path'] ?? '') === parse_url(armar_url('supervisor/tablero'), PHP_URL_PATH)) {
    $volver = ['supervisor/tablero', 'Tablero'];
    parse_str($origen['query'] ?? '', $consulta);
    foreach (['fecha', 'division', 'alertas'] as $clave) {
        if (isset($consulta[$clave]) && is_string($consulta[$clave]) && $consulta[$clave] !== '') {
            $volver_parametros[$clave] = $consulta[$clave];
        }
    }
}

$titulo = $operativo['unidad_nombre'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="formulario-angosto mx-auto">
    <a href="<?= e(armar_url($volver[0], $volver_parametros)) ?>" class="d-inline-block small text-body-secondary text-decoration-none mb-2">
        <i class="bi bi-arrow-left"></i> <?= e($volver[1]) ?>
    </a>
    <?php require RUTA_APP . '/vistas/ficha_operativo.php'; ?>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
