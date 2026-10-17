<?php
/**
 * Campo numérico con botones − y + (docs/04). Espera $parametros con:
 * nombre, etiqueta, valor, minimo, maximo, deshabilitado y errores.
 * Opcional: ayuda (texto bajo el campo) e id.
 */
declare(strict_types=1);

$id_campo = $parametros['id'] ?? 'campo-' . $parametros['nombre'];
?>
<div class="mb-3" x-data="{ valor: <?= (int) $parametros['valor'] ?>, min: <?= (int) $parametros['minimo'] ?>, max: <?= (int) $parametros['maximo'] ?> }">
    <label for="<?= e($id_campo) ?>" class="form-label mb-1"><?= e($parametros['etiqueta']) ?></label>
    <div class="input-group input-group-lg has-validation campo-contador">
        <button type="button" class="btn btn-outline-secondary" aria-label="Restar uno a <?= e($parametros['etiqueta']) ?>"
                @click="valor = Math.max(min, (parseInt(valor) || 0) - 1)"<?= $parametros['deshabilitado'] ? ' disabled' : '' ?>>
            <i class="bi bi-dash-lg"></i>
        </button>
        <input type="number" id="<?= e($id_campo) ?>" name="<?= e($parametros['nombre']) ?>" x-model.number="valor"
               min="<?= (int) $parametros['minimo'] ?>" max="<?= (int) $parametros['maximo'] ?>" step="1" inputmode="numeric"
               value="<?= (int) $parametros['valor'] ?>"
               class="form-control text-center<?= clase_error($parametros['errores'], $parametros['nombre']) ?>"
               <?= isset($parametros['ayuda']) ? 'aria-describedby="' . e($id_campo) . '-ayuda"' : '' ?>
               <?= $parametros['deshabilitado'] ? ' disabled' : '' ?>>
        <button type="button" class="btn btn-outline-secondary" aria-label="Sumar uno a <?= e($parametros['etiqueta']) ?>"
                @click="valor = Math.min(max, (parseInt(valor) || 0) + 1)"<?= $parametros['deshabilitado'] ? ' disabled' : '' ?>>
            <i class="bi bi-plus-lg"></i>
        </button>
        <?= mostrar_error($parametros['errores'], $parametros['nombre']) ?>
    </div>
    <?php if (isset($parametros['ayuda'])): ?>
        <div id="<?= e($id_campo) ?>-ayuda" class="form-text"><?= e($parametros['ayuda']) ?></div>
    <?php endif; ?>
</div>
