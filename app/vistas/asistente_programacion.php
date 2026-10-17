<?php
/**
 * Asistente de programación de la UNIDAD en 4 pasos (docs/04). Todos los
 * campos están en un solo formulario: los pasos solo se muestran u ocultan,
 * así que no se pierde nada al ir y volver. Va dentro de un
 * <form x-data="asistenteProgramacion(...)"> (assets/js/programacion.js).
 * Espera lo mismo que los bloques campos_*.php, más $actual, $jefe_sugerido
 * y $pasos_editables.
 */
declare(strict_types=1);

$texto_guardar = $actual === null ? 'Programar operativo' : 'Guardar cambios';
?>
<!-- Progreso -->
<ol class="pasos-progreso list-unstyled d-flex mb-3" aria-label="Pasos">
    <template x-for="p in pasos" :key="p.numero">
        <li class="flex-fill">
            <button type="button" class="paso-indicador w-100" @click="puedeIrA(p.numero) && irA(p.numero)"
                    :class="{ 'actual': paso === p.numero, 'hecho': p.numero < paso && !editando }"
                    :disabled="!puedeIrA(p.numero)" :aria-current="paso === p.numero ? 'step' : null">
                <span class="paso-numero" x-text="p.numero < paso && !editando ? '✓' : p.numero"></span>
                <span class="paso-titulo" x-text="p.titulo"></span>
            </button>
        </li>
    </template>
</ol>

<div class="alert alert-danger py-2" role="alert" x-show="error" x-cloak x-text="error"></div>

<!-- Paso 1: Cuándo -->
<section class="card card-body shadow-sm mb-3" data-paso="1" x-show="paso === 1" x-cloak>
    <h2 class="h5 titulo-paso" tabindex="-1">¿Cuándo?</h2>
    <?php require RUTA_APP . '/vistas/campos_horario.php'; ?>
    <div class="alert alert-warning py-2 small mt-2 mb-0" x-show="turnoOcupado" x-cloak>
        <i class="bi bi-exclamation-triangle"></i> Su unidad ya tiene un operativo <span x-text="turno"></span> en ese servicio diario.
    </div>
</section>

<!-- Paso 2: Qué y dónde -->
<section class="card card-body shadow-sm mb-3" data-paso="2" x-show="paso === 2" x-cloak>
    <h2 class="h5 titulo-paso" tabindex="-1">¿Qué y dónde?</h2>
    <?php require RUTA_APP . '/vistas/campos_operativo.php'; ?>
</section>

<!-- Paso 3: Jefe y fuerzas -->
<section class="card card-body shadow-sm mb-3" data-paso="3" x-show="paso === 3" x-cloak>
    <h2 class="h5 titulo-paso" tabindex="-1">Jefe y fuerzas</h2>
    <?php if ($jefe_sugerido): ?>
        <div class="alert alert-light border py-2 small">
            <i class="bi bi-info-circle"></i> Se completó con el jefe del último operativo. Cámbielo si corresponde.
        </div>
    <?php endif; ?>
    <h3 class="h6 text-body-secondary">Jefe del operativo</h3>
    <?php require RUTA_APP . '/vistas/campos_jefe.php'; ?>
    <h3 class="h6 text-body-secondary mt-4">Fuerzas</h3>
    <?php require RUTA_APP . '/vistas/campos_fuerzas.php'; ?>
</section>

<!-- Paso 4: Revisar -->
<section class="mb-3" data-paso="4" x-show="paso === 4" x-cloak>
    <h2 class="h5 titulo-paso" tabindex="-1">Revise antes de <?= $actual === null ? 'programar' : 'guardar' ?></h2>

    <?php
    $bloques = [
        1 => 'Cuándo',
        2 => 'Qué y dónde',
        3 => 'Jefe y fuerzas',
    ];
    ?>
    <?php foreach ($bloques as $numero => $nombre): ?>
        <div class="card shadow-sm mb-2">
            <div class="card-body py-2">
                <div class="d-flex align-items-center mb-1">
                    <h3 class="h6 mb-0 me-auto"><?= e($nombre) ?></h3>
                    <?php if (in_array($numero, $pasos_editables, true)): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="irA(<?= $numero ?>)">
                            <i class="bi bi-pencil"></i> Cambiar
                        </button>
                    <?php else: ?>
                        <span class="small text-body-secondary"><i class="bi bi-lock"></i> No se puede cambiar</span>
                    <?php endif; ?>
                </div>
                <?php if ($numero === 1): ?>
                    <div><span class="badge text-bg-dark" x-text="turno"></span> <span class="small" x-text="resumen"></span></div>
                <?php elseif ($numero === 2): ?>
                    <dl class="row small mb-0">
                        <dt class="col-4">Tipo(s)</dt><dd class="col-8 mb-1" x-text="revision.tipos"></dd>
                        <dt class="col-4">Lugar</dt><dd class="col-8 mb-1" x-text="revision.lugar"></dd>
                        <dt class="col-4">Coordenadas</dt>
                        <dd class="col-8 mb-1">
                            <span x-show="revision.coordenadas" x-text="revision.coordenadas"></span>
                            <span x-show="!revision.coordenadas" class="text-body-secondary">Sin coordenadas</span>
                        </dd>
                    </dl>
                <?php else: ?>
                    <dl class="row small mb-0">
                        <dt class="col-4">Jefe</dt><dd class="col-8 mb-1" x-text="revision.jefe"></dd>
                        <dt class="col-4">Celular</dt><dd class="col-8 mb-1" x-text="revision.celular"></dd>
                        <dt class="col-4">Fuerzas</dt><dd class="col-8 mb-1" x-text="revision.fuerzas"></dd>
                        <dt class="col-4" x-show="revision.observaciones">Observaciones</dt>
                        <dd class="col-8 mb-0 text-break" x-show="revision.observaciones" x-text="revision.observaciones"></dd>
                    </dl>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<!-- Botones fijos al pie, al alcance del pulgar -->
<div class="barra-pasos d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary boton-accion flex-fill" x-show="paso > 1 && !editando" @click="atras()">
        <i class="bi bi-arrow-left"></i> Atrás
    </button>
    <button type="button" class="btn btn-primary boton-accion flex-fill" x-show="paso < 4" @click="siguiente()">
        <span x-text="editando ? 'Listo' : (paso === 3 ? 'Revisar' : 'Siguiente')"></span> <i class="bi bi-arrow-right"></i>
    </button>
    <button type="submit" class="btn btn-success boton-accion flex-fill" x-show="paso === 4" x-cloak>
        <i class="bi bi-check-lg"></i> <?= e($texto_guardar) ?>
    </button>
</div>
