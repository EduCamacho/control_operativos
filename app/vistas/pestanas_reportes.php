<?php
/**
 * Pestañas de Reportes (SUPERVISOR y ADMIN): Servicio diario y Por unidad.
 * Usa $ruta_actual de cabecera.php.
 */
declare(strict_types=1);

$pestanas_reportes = ['reportes' => 'Servicio diario', 'reportes/unidad' => 'Por unidad'];
?>
<nav class="pestanas mb-3" aria-label="Tipo de reporte">
    <?php foreach ($pestanas_reportes as $ruta_pestana => $etiqueta_pestana): ?>
        <a href="<?= e(armar_url($ruta_pestana)) ?>" class="pestana<?= $ruta_actual === $ruta_pestana ? ' activa' : '' ?>"
           <?= $ruta_actual === $ruta_pestana ? 'aria-current="page"' : '' ?>><?= e($etiqueta_pestana) ?></a>
    <?php endforeach; ?>
</nav>
