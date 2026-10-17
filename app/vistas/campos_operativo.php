<?php
/**
 * Bloque "Qué y dónde": tipos de operativo (botones que se marcan al tocarlos),
 * nombre del plan y lugar programado.
 * Espera $valores, $errores, $deshabilitado y $tipos_formulario.
 */
declare(strict_types=1);

$tipos_marcados = array_map('intval', (array) $valores['tipos']);
?>
<fieldset class="mb-3"<?= $deshabilitado('tipos') ?>>
    <legend class="form-label fs-6">Tipo(s) de operativo <span class="text-body-secondary small">— toque uno o más</span></legend>
    <div class="row row-cols-1 row-cols-sm-2 g-2">
        <?php foreach ($tipos_formulario as $tipo): ?>
            <div class="col">
                <input type="checkbox" class="btn-check" name="tipos[]" autocomplete="off"
                       id="tipo-<?= e($tipo['id']) ?>" value="<?= e($tipo['id']) ?>"
                       <?= marcar(in_array((int) $tipo['id'], $tipos_marcados, true)) ?>>
                <label class="btn btn-outline-primary w-100 text-start boton-tipo" for="tipo-<?= e($tipo['id']) ?>">
                    <i class="bi bi-check-circle-fill icono-marcado" aria-hidden="true"></i>
                    <i class="bi bi-circle icono-sin-marcar" aria-hidden="true"></i>
                    <span class="nombre-tipo"><?= e($tipo['nombre']) ?></span><?= $tipo['activo'] ? '' : ' <small>(inactivo)</small>' ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (isset($errores['tipos'])): ?><div class="text-danger small mt-1"><?= e($errores['tipos']) ?></div><?php endif; ?>
</fieldset>
<div class="mb-3">
    <label for="lugar_programado" class="form-label">Lugar programado</label>
    <input type="text" id="lugar_programado" name="lugar_programado" maxlength="255" required
           class="form-control form-control-lg<?= clase_error($errores, 'lugar_programado') ?>" value="<?= e($valores['lugar_programado']) ?>"
           placeholder="Av. España con Jr. Grau, sector Centro"<?= $deshabilitado('lugar_programado') ?>>
    <?= mostrar_error($errores, 'lugar_programado') ?>
</div>
<div class="mb-1">
    <label for="nombre_plan" class="form-label">Nombre del plan <span class="text-body-secondary">(opcional)</span></label>
    <input type="text" id="nombre_plan" name="nombre_plan" maxlength="150"
           class="form-control<?= clase_error($errores, 'nombre_plan') ?>" value="<?= e($valores['nombre_plan'] ?? '') ?>"
           placeholder="Saltamonte 2026"<?= $deshabilitado('nombre_plan') ?>>
    <?= mostrar_error($errores, 'nombre_plan') ?>
</div>
