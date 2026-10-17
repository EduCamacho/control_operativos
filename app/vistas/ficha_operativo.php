<?php
/**
 * Ficha del operativo para SUPERVISOR y ADMIN (docs/04). La usan el panel del
 * mapa (como fragmento HTML), la lista del mapa y el tablero.
 * Espera: $operativo, $tipos, $ubicaciones, $controles, $ahora y $puede_controlar.
 * El recorrido va en data-recorrido para que el mapa lo dibuje.
 */
declare(strict_types=1);

$estado_ficha = calcular_estado_visible($operativo, $ahora);
$ultima = $ubicaciones === [] ? null : $ubicaciones[array_key_last($ubicaciones)];
$recorrido = array_map(static fn (array $u): array => [(float) $u['latitud'], (float) $u['longitud']], $ubicaciones);
?>
<div class="ficha-operativo" data-recorrido="<?= e(json_encode($recorrido)) ?>" data-operativo-id="<?= e($operativo['id']) ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <h2 class="h5 mb-0"><?= e($operativo['unidad_nombre']) ?></h2>
        <?= mostrar_estado($estado_ficha, pendiente_nota($operativo)) ?>
    </div>
    <p class="text-body-secondary small mb-2">
        Turno <?= e(obtener_etiqueta_turno($operativo['turno'])) ?>
        · servicio del <?= e(validar_fecha($operativo['fecha_servicio'])->format('d/m/Y')) ?>
    </p>

    <div class="d-grid gap-2 d-sm-flex mb-3">
        <?php if ($ultima !== null): ?>
            <a class="btn btn-institucional boton-accion" target="_blank" rel="noopener"
               href="<?= e(armar_enlace_como_llegar((float) $ultima['latitud'], (float) $ultima['longitud'])) ?>">
                <i class="bi bi-sign-turn-right"></i> Cómo llegar
            </a>
        <?php elseif ($operativo['lugar_latitud'] !== null): ?>
            <a class="btn btn-institucional boton-accion" target="_blank" rel="noopener"
               href="<?= e(armar_enlace_como_llegar((float) $operativo['lugar_latitud'], (float) $operativo['lugar_longitud'])) ?>">
                <i class="bi bi-sign-turn-right"></i> Cómo llegar al lugar programado
            </a>
        <?php endif; ?>
        <a class="btn btn-outline-institucional boton-accion" href="tel:<?= e($operativo['jefe_celular']) ?>">
            <i class="bi bi-telephone"></i> Llamar al jefe
        </a>
        <?php if ($puede_controlar): ?>
            <a class="btn btn-outline-institucional boton-accion" href="<?= e(armar_url('supervisor/control', ['id' => $operativo['id']])) ?>">
                <i class="bi bi-clipboard-check"></i> Registrar control
            </a>
        <?php endif; ?>
    </div>

    <?php if ($ultima !== null): ?>
        <div class="alert alert-light border py-2 small">
            <div class="text-body-secondary">Posición actual</div>
            <?= mostrar_ubicacion($ultima, true, false) ?>
        </div>
    <?php else: ?>
        <div class="alert alert-light border py-2 small">
            <div class="text-body-secondary">Lugar programado (todavía sin ubicación)</div>
            <?= e($operativo['lugar_programado']) ?>
            <?php if ($operativo['lugar_latitud'] !== null): ?>
                <a href="<?= e(armar_enlace_google_maps((float) $operativo['lugar_latitud'], (float) $operativo['lugar_longitud'])) ?>"
                   target="_blank" rel="noopener" class="text-nowrap"><i class="bi bi-geo-alt"></i> Ver en Google Maps</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <dl class="row small mb-2">
        <dt class="col-4">Tipo(s)</dt>
        <dd class="col-8"><?= e(implode(', ', array_column($tipos, 'nombre'))) ?></dd>
        <dt class="col-4">Jefe</dt>
        <dd class="col-8"><?= e($operativo['jefe_grado'] . ' ' . $operativo['jefe_nombres']) ?> · <?= e($operativo['jefe_celular']) ?></dd>
        <dt class="col-4">Fuerzas</dt>
        <dd class="col-8">
            <?= e($operativo['fuerzas_propias']) ?> propias · <?= e($operativo['fuerzas_apoyo']) ?> de apoyo ·
            <?= e($operativo['unidades_moviles']) ?> móvil(es)
        </dd>
        <dt class="col-4">Programado</dt>
        <dd class="col-8">
            <?= e(convertir_a_fecha_hora($operativo['inicio_programado'])->format('d/m H:i')) ?> –
            <?= e(convertir_a_fecha_hora($operativo['fin_programado'])->format('d/m H:i')) ?>
        </dd>
        <dt class="col-4">Real</dt>
        <dd class="col-8">
            <?php if ($operativo['inicio_real'] === null): ?>
                Sin iniciar
            <?php else: ?>
                <?= e(convertir_a_fecha_hora($operativo['inicio_real'])->format('d/m H:i')) ?> –
                <?= $operativo['fin_real'] === null ? 'en curso' : e(convertir_a_fecha_hora($operativo['fin_real'])->format('d/m H:i')) ?>
            <?php endif; ?>
        </dd>
        <dt class="col-4">Lugar</dt>
        <dd class="col-8"><?= e($operativo['lugar_programado']) ?></dd>
        <?php if ($operativo['nota_informativa'] !== null && $operativo['nota_informativa'] !== ''): ?>
            <dt class="col-4">Nota</dt>
            <dd class="col-8"><?= e(formatear_nota_informativa($operativo['nota_informativa'])) ?></dd>
        <?php endif; ?>
    </dl>

    <?php if ($controles !== []): ?>
        <h3 class="h6 text-uppercase text-body-secondary mt-3">Controles · <?= count($controles) ?></h3>
        <ul class="list-group list-group-flush small mb-2">
            <?php foreach ($controles as $control): ?>
                <li class="list-group-item px-0">
                    <span class="badge <?= $control['resultado'] === 'CONFORME' ? 'text-bg-success' : ($control['resultado'] === 'NO_ENCONTRADO' ? 'text-bg-danger' : 'text-bg-warning') ?>">
                        <?= e(RESULTADOS_CONTROL[$control['resultado']]) ?>
                    </span>
                    <?= e(convertir_a_fecha_hora($control['registrado_en'])->format('d/m H:i')) ?> · <?= e($control['supervisor']) ?>
                    <?php if ($control['distancia_m'] !== null): ?> · a <?= e($control['distancia_m']) ?> m del último punto<?php endif; ?>
                    <?php if ($control['observacion'] !== null): ?><div class="text-body-secondary"><?= e($control['observacion']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($ubicaciones !== []): ?>
        <h3 class="h6 text-uppercase text-body-secondary mt-3">Recorrido · <?= count($ubicaciones) ?> punto(s)</h3>
        <ul class="list-group list-group-flush small">
            <?php foreach (array_reverse($ubicaciones, true) as $indice => $ubicacion): ?>
                <li class="list-group-item px-0 d-flex gap-2">
                    <span class="badge rounded-pill text-bg-light border align-self-start"><?= $indice + 1 ?></span>
                    <span><?= mostrar_ubicacion($ubicacion, true, false) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
