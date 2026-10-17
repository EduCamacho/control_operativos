<?php
declare(strict_types=1);

$titulo = 'Página no encontrada';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="text-center my-5">
    <i class="bi bi-signpost-split display-4 text-secondary"></i>
    <h1 class="h4 mt-3">No encontramos esta página</h1>
    <a href="<?= e(armar_url()) ?>" class="btn btn-primary mt-3">Volver al inicio</a>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
