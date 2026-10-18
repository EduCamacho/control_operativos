<?php
/**
 * Tarjeta "Recorrido": línea de tiempo vertical, el punto más reciente arriba.
 * Muestra los 5 más recientes y despliega el resto con un botón, para que un
 * operativo largo no empuje el resto de la ficha. Espera $ubicaciones.
 */
declare(strict_types=1);

$puntos_visibles = 5; // Variable y no constante: el parcial se incluye dos veces (PC y celular).

$total_puntos = count($ubicaciones);
$ocultos = max(0, $total_puntos - $puntos_visibles);
?>
<div class="card shadow-sm mb-3" x-data="{ todos: false }">
    <div class="card-body">
        <h2 class="h6 titulo-tarjeta"><i class="bi bi-signpost-split"></i> Recorrido · <?= $total_puntos ?> punto(s)</h2>
        <ol class="recorrido list-unstyled mb-0">
            <?php $posicion = 0; ?>
            <?php foreach (array_reverse($ubicaciones, true) as $indice => $ubicacion): ?>
                <?php $posicion++; ?>
                <li class="<?= $posicion === 1 ? 'reciente' : '' ?>"
                    <?php if ($posicion > $puntos_visibles): ?>x-show="todos" x-cloak<?php endif; ?>
                    <?php if ($posicion === $puntos_visibles && $ocultos > 0): ?>:class="{ 'sin-linea': !todos }"<?php endif; ?>>
                    <div class="d-flex flex-wrap align-items-baseline gap-2">
                        <span class="fw-semibold"><?= e(formatear_hora($ubicacion['registrado_en'])) ?></span>
                        <span><?= e($ubicacion['referencia']) ?></span>
                        <?php if ($indice === 0): ?><span class="badge text-bg-success">Inicio</span><?php endif; ?>
                    </div>
                    <div class="small text-body-secondary">
                        <?= $ubicacion['origen'] === 'MANUAL'
                            ? '<span class="badge text-bg-warning">Manual</span>'
                            : 'precisión ' . e($ubicacion['precision_m'] ?? '?') . ' m' ?>
                        <?= mostrar_avisos_ubicacion($ubicacion) ?>
                        · <a href="<?= e(armar_enlace_google_maps((float) $ubicacion['latitud'], (float) $ubicacion['longitud'])) ?>"
                             target="_blank" rel="noopener" class="enlace-institucional">Ver en Google Maps</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($ocultos > 0): ?>
            <button type="button" class="btn btn-sm btn-outline-institucional w-100 mt-3" @click="todos = !todos" :aria-expanded="todos">
                <span x-show="!todos"><i class="bi bi-chevron-down"></i> Ver <?= $ocultos === 1 ? 'el punto anterior' : "los $ocultos puntos anteriores" ?></span>
                <span x-show="todos" x-cloak><i class="bi bi-chevron-up"></i> Ver solo los <?= $puntos_visibles ?> más recientes</span>
            </button>
        <?php endif; ?>
    </div>
</div>
