<?php
/**
 * Bloque "Tipo de operativo": píldoras que se marcan al tocarlas (una o más).
 * Espera $valores, $errores, $deshabilitado y $tipos_formulario.
 */
declare(strict_types=1);

$tipos_marcados = array_map('intval', (array) $valores['tipos']);
?>
<fieldset<?= $deshabilitado('tipos') ?>>
    <legend class="visually-hidden">Tipo(s) de operativo</legend>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($tipos_formulario as $tipo): ?>
            <input type="checkbox" class="btn-check" name="tipos[]" autocomplete="off"
                   id="tipo-<?= e($tipo['id']) ?>" value="<?= e($tipo['id']) ?>"
                   <?= marcar(in_array((int) $tipo['id'], $tipos_marcados, true)) ?>>
            <label class="btn btn-sm btn-outline-institucional rounded-pill boton-tipo" for="tipo-<?= e($tipo['id']) ?>">
                <i class="bi bi-check-lg icono-marcado" aria-hidden="true"></i>
                <span class="nombre-tipo"><?= e($tipo['nombre']) ?></span><?= $tipo['activo'] ? '' : ' <small>(inactivo)</small>' ?>
            </label>
        <?php endforeach; ?>
    </div>
    <?php if (isset($errores['tipos'])): ?><div class="text-danger small mt-2"><?= e($errores['tipos']) ?></div><?php endif; ?>
</fieldset>
