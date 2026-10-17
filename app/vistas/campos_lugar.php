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
    <!-- El campo es lo principal; el botón de Google Maps va pegado a su derecha como ayuda. -->
    <div class="input-group input-group-lg has-validation">
        <input type="text" id="lugar_coordenadas" name="lugar_coordenadas" x-model="texto" inputmode="decimal" autocomplete="off"
               @paste="$nextTick(() => analizar())" @change="analizar()" :data-valido="estado === 'error' ? 'false' : 'true'"
               :class="{ 'is-invalid': estado === 'error', 'is-valid': estado === 'ok' }"
               class="form-control<?= clase_error($errores, 'lugar_coordenadas') ?>" value="<?= e($valores['lugar_coordenadas'] ?? '') ?>"
               placeholder="Pegue aquí: -8.1116, -79.0288"<?= $deshabilitado('lugar_coordenadas') ?>
               <?= $coordenadas_bloqueadas ? '' : 'aria-describedby="ayuda-coordenadas"' ?>>
        <?php if (!$coordenadas_bloqueadas): ?>
            <a :href="enlaceBuscar()" target="_blank" rel="noopener" class="btn btn-outline-institucional boton-buscar-mapa"
               title="Buscar el lugar en Google Maps">
                <i class="bi bi-map"></i> Buscar
            </a>
        <?php endif; ?>
        <?= mostrar_error($errores, 'lugar_coordenadas') ?>
    </div>
    <div class="small mt-1" aria-live="polite">
        <span x-show="estado === 'analizando'" class="text-body-secondary">Revisando…</span>
        <span x-show="estado === 'ok'" class="text-success" x-cloak>
            <i class="bi bi-check-circle-fill"></i> <span x-text="latitud + ', ' + longitud"></span> ·
            <a :href="enlaceVer()" target="_blank" rel="noopener">Ver en Google Maps</a>
        </span>
        <span x-show="estado === 'error'" class="text-danger" x-cloak x-text="mensaje"></span>
    </div>
    <?php if (!$coordenadas_bloqueadas): ?>
        <ol id="ayuda-coordenadas" class="pasos-coordenadas form-text list-unstyled mb-0 mt-1">
            <li><span class="numero">1</span> Toque <strong>Buscar</strong></li>
            <li><span class="numero">2</span> <strong>Mantenga presionado</strong> el punto <span class="text-nowrap">(clic derecho en la PC)</span></li>
            <li><span class="numero">3</span> Copie las coordenadas y péguelas aquí</li>
        </ol>
    <?php endif; ?>
</div>
