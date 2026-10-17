<?php
/**
 * Formulario de programación completo en una sola página (lo usa ADMIN en
 * admin_operativo_formulario.php). La UNIDAD usa los mismos bloques repartidos
 * en pasos (asistente_programacion.php).
 * Va dentro de un <form x-data="formularioProgramacion(...)"> y requiere
 * assets/js/programacion.js. Espera $valores, $errores, $deshabilitado,
 * $tipos_formulario y $fecha_minima.
 */
declare(strict_types=1);
?>
<div class="card card-body shadow-sm mb-3">
    <h2 class="h6">Fecha y horario</h2>
    <?php require RUTA_APP . '/vistas/campos_horario.php'; ?>
</div>
<div class="card card-body shadow-sm mb-3">
    <h2 class="h6">Operativo</h2>
    <?php require RUTA_APP . '/vistas/campos_operativo.php'; ?>
</div>
<div class="card card-body shadow-sm mb-3">
    <h2 class="h6">Jefe del operativo</h2>
    <?php require RUTA_APP . '/vistas/campos_jefe.php'; ?>
</div>
<div class="card card-body shadow-sm mb-3">
    <h2 class="h6">Fuerzas</h2>
    <?php require RUTA_APP . '/vistas/campos_fuerzas.php'; ?>
</div>
