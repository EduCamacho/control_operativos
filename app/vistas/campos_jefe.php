<?php
/**
 * Bloque "Jefe del operativo": grado (lista fija), nombres y celular.
 * Espera $valores, $errores y $deshabilitado.
 */
declare(strict_types=1);
?>
<div class="row g-2">
    <div class="col-4">
        <label for="jefe_grado" class="form-label">Grado</label>
        <select id="jefe_grado" name="jefe_grado" required class="form-select form-select-lg<?= clase_error($errores, 'jefe_grado') ?>"<?= $deshabilitado('jefe_grado') ?>>
            <option value="">—</option>
            <optgroup label="Oficiales">
                <?php foreach (array_slice(GRADOS, 0, 7) as $grado): ?>
                    <option<?= marcar($valores['jefe_grado'] === $grado, 'selected') ?>><?= e($grado) ?></option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Suboficiales">
                <?php foreach (array_slice(GRADOS, 7) as $grado): ?>
                    <option<?= marcar($valores['jefe_grado'] === $grado, 'selected') ?>><?= e($grado) ?></option>
                <?php endforeach; ?>
            </optgroup>
        </select>
        <?= mostrar_error($errores, 'jefe_grado') ?>
    </div>
    <div class="col-8">
        <label for="jefe_nombres" class="form-label">Nombres y apellidos</label>
        <input type="text" id="jefe_nombres" name="jefe_nombres" maxlength="150" required autocomplete="off" autocapitalize="words"
               class="form-control form-control-lg<?= clase_error($errores, 'jefe_nombres') ?>" value="<?= e($valores['jefe_nombres']) ?>"<?= $deshabilitado('jefe_nombres') ?>>
        <?= mostrar_error($errores, 'jefe_nombres') ?>
    </div>
    <div class="col-12">
        <label for="jefe_celular" class="form-label">Celular</label>
        <input type="tel" id="jefe_celular" name="jefe_celular" maxlength="15" required inputmode="tel" autocomplete="off"
               class="form-control form-control-lg<?= clase_error($errores, 'jefe_celular') ?>" value="<?= e($valores['jefe_celular']) ?>"
               placeholder="987654321"<?= $deshabilitado('jefe_celular') ?>>
        <?= mostrar_error($errores, 'jefe_celular') ?>
    </div>
</div>
