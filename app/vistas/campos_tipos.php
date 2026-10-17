<?php
/**
 * Bloque "Tipo de operativo": casillas para marcar uno o más tipos.
 * Cada fila es tocable completa (mínimo 44 px de alto, pensado para el celular).
 * Espera $valores, $errores, $deshabilitado y $tipos_formulario.
 */
declare(strict_types=1);

$tipos_marcados = array_map('intval', (array) $valores['tipos']);
?>
<fieldset<?= $deshabilitado('tipos') ?>>
    <legend class="visually-hidden">Tipo(s) de operativo</legend>
    <div class="row row-cols-1 row-cols-sm-2 gx-3">
        <?php foreach ($tipos_formulario as $tipo): ?>
            <div class="col">
                <div class="form-check casilla-tipo">
                    <input class="form-check-input<?= clase_error($errores, 'tipos') ?>" type="checkbox" name="tipos[]"
                           id="tipo-<?= e($tipo['id']) ?>" value="<?= e($tipo['id']) ?>"
                           <?= marcar(in_array((int) $tipo['id'], $tipos_marcados, true)) ?>>
                    <label class="form-check-label" for="tipo-<?= e($tipo['id']) ?>">
                        <span class="nombre-tipo"><?= e($tipo['nombre']) ?></span><?= $tipo['activo'] ? '' : ' <small class="text-body-secondary">(inactivo)</small>' ?>
                    </label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (isset($errores['tipos'])): ?><div class="text-danger small mt-1"><?= e($errores['tipos']) ?></div><?php endif; ?>
</fieldset>
