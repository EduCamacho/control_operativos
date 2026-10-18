<?php
/**
 * Ficha del operativo para SUPERVISOR y ADMIN (docs/04). La usan el panel del
 * mapa (como fragmento HTML) y la página del operativo (desde el tablero).
 * Espera: $operativo, $tipos, $ubicaciones, $ahora, $puede_controlar y
 * $en_pagina (true en la página: el recorrido se lista; en el panel del mapa
 * es un botón que lo dibuja sobre el mapa).
 * Los controles inopinados se ven en la pantalla de registrar control.
 * El recorrido va en data-recorrido para que el mapa lo dibuje.
 */
declare(strict_types=1);

$estado_ficha = calcular_estado_visible($operativo, $ahora);
$ultima = $ubicaciones === [] ? null : $ubicaciones[array_key_last($ubicaciones)];
// Cada punto con su hora y referencia, para numerarlos en el mapa ("Ver recorrido").
$recorrido = array_map(static fn (array $u): array => [
    'lat'        => (float) $u['latitud'],
    'lng'        => (float) $u['longitud'],
    'hora'       => formatear_hora($u['registrado_en']),
    'referencia' => $u['referencia'] ?? 'Sin referencia',
], $ubicaciones);

// "Cómo llegar": a la última posición o, si todavía no hay, al lugar programado.
$destino = $ultima !== null
    ? [(float) $ultima['latitud'], (float) $ultima['longitud']]
    : ($operativo['lugar_latitud'] !== null ? [(float) $operativo['lugar_latitud'], (float) $operativo['lugar_longitud']] : null);
// La fecha solo se muestra si no es el servicio diario vigente.
$otra_fecha = $operativo['fecha_servicio'] !== fecha_servicio_vigente($ahora);
$moviles = (int) $operativo['unidades_moviles'];
?>
<div class="ficha-operativo" data-recorrido="<?= e(json_encode($recorrido)) ?>" data-operativo-id="<?= e($operativo['id']) ?>">
    <!-- Encabezado con el borde del color del estado (como en Registrar control) -->
    <div class="card shadow-sm mb-3 encabezado-operativo tarjeta-<?= e(strtolower($estado_ficha)) ?>">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-2">
                <h2 class="h5 mb-0 me-auto"><?= e($operativo['unidad_nombre']) ?></h2>
                <?= mostrar_estado($estado_ficha, pendiente_nota($operativo)) ?>
            </div>
            <div class="small text-body-secondary">
                <?= e(obtener_etiqueta_turno($operativo['turno'])) ?> ·
                <?= e(formatear_hora($operativo['inicio_programado'])) ?> – <?= e(formatear_hora($operativo['fin_programado'])) ?>
                <?php if ($otra_fecha): ?>
                    · <?= e(validar_fecha($operativo['fecha_servicio'])->format('d/m')) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Acciones: botones cuadrados en una fila -->
    <div class="acciones-ficha mb-3">
        <?php if ($destino !== null): ?>
            <a class="btn btn-institucional boton-cuadrado" target="_blank" rel="noopener"
               href="<?= e(armar_enlace_como_llegar($destino[0], $destino[1])) ?>">
                <i class="bi bi-sign-turn-right"></i> <span>Cómo llegar</span>
            </a>
        <?php endif; ?>
        <a class="btn btn-outline-institucional boton-cuadrado" href="tel:<?= e($operativo['jefe_celular']) ?>">
            <i class="bi bi-telephone"></i> <span>Llamar</span>
        </a>
        <?php if ($puede_controlar): ?>
            <a class="btn btn-outline-institucional boton-cuadrado" href="<?= e(armar_url('supervisor/control', ['id' => $operativo['id']])) ?>">
                <i class="bi bi-clipboard-check"></i> <span>Control</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="alert alert-light border py-2 small">
        <?php if ($ultima !== null): ?>
            <div class="text-body-secondary">Última ubicación registrada</div>
            <?= mostrar_ubicacion($ultima, true, false) ?>
        <?php else: ?>
            <div class="text-body-secondary">Lugar programado (todavía sin ubicación)</div>
            <?= e($operativo['lugar_programado']) ?>
            <?php if ($operativo['lugar_latitud'] !== null): ?>
                <a href="<?= e(armar_enlace_google_maps((float) $operativo['lugar_latitud'], (float) $operativo['lugar_longitud'])) ?>"
                   target="_blank" rel="noopener" class="text-nowrap"><i class="bi bi-geo-alt"></i> Ver en Google Maps</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <dl class="row small mb-2">
        <dt class="col-4">Tipo(s)</dt>
        <dd class="col-8"><?= e(implode(', ', array_column($tipos, 'nombre'))) ?></dd>
        <dt class="col-4">Jefe</dt>
        <dd class="col-8"><?= e($operativo['jefe_grado'] . ' ' . $operativo['jefe_nombres']) ?></dd>
        <dt class="col-4">Fuerzas</dt>
        <dd class="col-8">
            <?= e($operativo['fuerzas_propias']) ?> propias · <?= e($operativo['fuerzas_apoyo']) ?> de apoyo ·
            <?= $moviles ?> <?= $moviles === 1 ? 'móvil' : 'móviles' ?>
        </dd>
        <dt class="col-4">Lugar</dt>
        <dd class="col-8"><?= e($operativo['lugar_programado']) ?></dd>
        <?php if ($operativo['nota_informativa'] !== null && $operativo['nota_informativa'] !== ''): ?>
            <dt class="col-4">N.I</dt>
            <dd class="col-8"><?= e(formatear_nota_informativa($operativo['nota_informativa'])) ?></dd>
        <?php endif; ?>
    </dl>

    <?php if ($ubicaciones !== [] && $en_pagina): ?>
        <!-- En la página no hay mapa: el recorrido se lista, el último punto primero. -->
        <h3 class="h6 titulo-tarjeta mt-3"><i class="bi bi-signpost-split"></i> Recorrido · <?= count($ubicaciones) ?></h3>
        <ul class="list-group list-group-flush small">
            <?php foreach (array_reverse($ubicaciones, true) as $indice => $ubicacion): ?>
                <li class="list-group-item px-0 d-flex gap-2">
                    <span class="badge rounded-pill text-bg-light border align-self-start"><?= $indice + 1 ?></span>
                    <span><?= mostrar_ubicacion($ubicacion, true, false) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif ($ubicaciones !== []): ?>
        <!-- En el panel del mapa, el botón muestra el recorrido sobre el mapa (mapa.js);
             si el mapa no está visible (vista Lista), despliega la lista de puntos aquí. -->
        <div x-data="{ lista: false }" class="mt-3">
            <button type="button" class="btn btn-outline-institucional w-100 boton-accion"
                    @click="(window.verRecorridoEnMapa && window.verRecorridoEnMapa()) || (lista = !lista)">
                <i class="bi bi-signpost-split"></i> Ver recorrido (<?= count($ubicaciones) ?> <?= count($ubicaciones) === 1 ? 'punto' : 'puntos' ?>)
            </button>
            <ul class="list-group list-group-flush small mt-2" x-show="lista" x-cloak>
                <?php foreach (array_reverse($ubicaciones, true) as $indice => $ubicacion): ?>
                    <li class="list-group-item px-0 d-flex gap-2">
                        <span class="badge rounded-pill text-bg-light border align-self-start"><?= $indice + 1 ?></span>
                        <span><?= mostrar_ubicacion($ubicacion, true, false) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
