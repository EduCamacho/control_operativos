<?php
/**
 * Bloque "Lugar del operativo": lugar programado y sus coordenadas (opcionales),
 * con el botón para buscarlo en Google Maps y copiar las coordenadas.
 * Espera $valores, $errores y $deshabilitado.
 */
declare(strict_types=1);
?>
<div class="mb-3">
    <label for="lugar_programado" class="form-label">Dirección o referencia</label>
    <input type="text" id="lugar_programado" name="lugar_programado" maxlength="255" required
           class="form-control form-control-lg<?= clase_error($errores, 'lugar_programado') ?>" value="<?= e($valores['lugar_programado']) ?>"
           placeholder="Av. España con Jr. Grau, sector Centro"<?= $deshabilitado('lugar_programado') ?>>
    <?= mostrar_error($errores, 'lugar_programado') ?>
</div>
<div class="mb-1" x-data="coordenadasLugar(<?= e(json_encode(['url' => armar_url('api/coordenadas'), 'texto' => (string) ($valores['lugar_coordenadas'] ?? '')])) ?>)">
    <label for="lugar_coordenadas" class="form-label">Coordenadas del lugar <span class="text-body-secondary">(opcional)</span></label>
    <?php $coordenadas_bloqueadas = $deshabilitado('lugar_coordenadas') !== ''; ?>
    <?php if (!$coordenadas_bloqueadas): ?>
        <a :href="enlaceBuscar()" target="_blank" rel="noopener" class="btn btn-outline-institucional w-100 boton-accion mb-2">
            <i class="bi bi-map"></i> Abrir Google Maps
        </a>
    <?php endif; ?>
    <input type="text" id="lugar_coordenadas" name="lugar_coordenadas" x-model="texto" inputmode="decimal" autocomplete="off"
           @paste="$nextTick(() => analizar())" @change="analizar()" :data-valido="estado === 'error' ? 'false' : 'true'"
           :class="{ 'is-invalid': estado === 'error', 'is-valid': estado === 'ok' }"
           class="form-control<?= clase_error($errores, 'lugar_coordenadas') ?>" value="<?= e($valores['lugar_coordenadas'] ?? '') ?>"
           placeholder="Pegue aquí: -8.111600, -79.028800"<?= $deshabilitado('lugar_coordenadas') ?>>
    <?= mostrar_error($errores, 'lugar_coordenadas') ?>
    <div class="small mt-1" aria-live="polite">
        <span x-show="estado === 'analizando'" class="text-body-secondary">Revisando…</span>
        <span x-show="estado === 'ok'" class="text-success" x-cloak>
            <i class="bi bi-check-circle-fill"></i> <span x-text="latitud + ', ' + longitud"></span> ·
            <a :href="enlaceVer()" target="_blank" rel="noopener">Ver en Google Maps</a>
        </span>
        <span x-show="estado === 'error'" class="text-danger" x-cloak x-text="mensaje"></span>
    </div>
    <?php if (!$coordenadas_bloqueadas): ?>
        <div class="form-text">
            Busque el lugar en Google Maps, <strong>mantenga presionado</strong> el punto exacto (clic derecho en la PC),
            copie las coordenadas que aparecen y péguelas aquí.
        </div>
    <?php endif; ?>
</div>
