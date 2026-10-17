<?php
declare(strict_types=1);

$titulo = 'Sin permiso';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="text-center my-5">
    <i class="bi bi-slash-circle display-4 text-danger"></i>
    <h1 class="h4 mt-3">No tiene permiso para ver esta página</h1>
    <a href="<?= e(armar_url()) ?>" class="btn btn-primary mt-3">Volver al inicio</a>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
