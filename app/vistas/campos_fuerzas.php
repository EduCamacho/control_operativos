<?php
/**
 * Bloque "Fuerzas": fuerzas propias, de apoyo y unidades móviles con − y +, y observaciones.
 * Espera $valores, $errores y $deshabilitado.
 */
declare(strict_types=1);

$contadores = [
    'fuerzas_propias'  => ['Fuerzas propias', 1],
    'fuerzas_apoyo'    => ['Fuerzas de apoyo (DIROPES, etc)', 0],
    'unidades_moviles' => ['Unidades móviles', 0],
];
?>
<?php foreach ($contadores as $campo => [$etiqueta, $minimo]): ?>
    <?php $parametros = [
        'nombre'        => $campo,
        'etiqueta'      => $etiqueta,
        'valor'         => $valores[$campo],
        'minimo'        => $minimo,
        'maximo'        => MAX_FUERZAS,
        'deshabilitado' => $deshabilitado($campo) !== '',
        'errores'       => $errores,
    ]; ?>
    <?php require RUTA_APP . '/vistas/campo_contador.php'; ?>
<?php endforeach; ?>
<div class="mb-1">
    <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
    <textarea id="observaciones" name="observaciones" rows="3" maxlength="<?= MAX_CARACTERES_OBSERVACIONES ?>"
              class="form-control<?= clase_error($errores, 'observaciones') ?>"<?= $deshabilitado('observaciones') ?>><?= e($valores['observaciones'] ?? '') ?></textarea>
    <?= mostrar_error($errores, 'observaciones') ?>
</div>
