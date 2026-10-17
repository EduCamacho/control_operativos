<?php
/**
 * SUPERVISOR y ADMIN: ficha de un operativo de cualquier unidad.
 * Con ?fragmento=1 devuelve solo la ficha, para el panel del mapa.
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
$controles = obtener_controles($id);
$puede_controlar = $usuario['rol'] === 'SUPERVISOR' && operativo_controlable($operativo, fecha_servicio_vigente($ahora));

if ($es_fragmento) {
    header('Cache-Control: no-store');
    require RUTA_APP . '/vistas/ficha_operativo.php';
    exit;
}

$titulo = $operativo['unidad_nombre'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url($usuario['rol'] === 'SUPERVISOR' ? '' : 'supervisor/tablero')) ?>"><?= $usuario['rol'] === 'SUPERVISOR' ? 'Mapa' : 'Tablero' ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($operativo['unidad_nombre']) ?></li>
    </ol>
</nav>
<div class="formulario-angosto">
    <?php require RUTA_APP . '/vistas/ficha_operativo.php'; ?>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
