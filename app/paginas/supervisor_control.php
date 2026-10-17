<?php
/**
 * SUPERVISOR: registrar un control inopinado (RN-15, docs/04).
 * Pantalla con poco texto: encabezado del operativo, tres botones de resultado,
 * observación y Guardar fijo al pie. No usa el GPS ni calcula distancias: a
 * veces el General controla con un dron, sin ir al lugar.
 */
declare(strict_types=1);

$usuario = requiere_rol('SUPERVISOR');
$ahora = obtener_ahora();

$id = obtener_id_get();
$operativo = $id === null ? null : obtener_operativo($id);
if ($operativo === null) {
    guardar_mensaje('danger', 'El operativo no existe.');
    redirigir('');
}
if (!operativo_controlable($operativo, fecha_servicio_vigente($ahora))) {
    guardar_mensaje('warning', 'Solo se registran controles sobre operativos del servicio diario vigente.');
    redirigir('supervisor/operativo', ['id' => $id]);
}

$estado = calcular_estado_visible($operativo, $ahora);
$controles = obtener_controles($id);
$configuracion = [
    'operativoId'    => (int) $id,
    'urlEnvio'       => armar_url('api/controles'),
    'urlFicha'       => armar_url('supervisor/operativo', ['id' => $id]),
    'urlIngreso'     => armar_url('login'),
    'controles'      => array_map('presentar_control', $controles),
    'yo'             => $usuario['nombre'],
];

// Resultado: ícono y color de cada botón.
$botones_resultado = [
    'CONFORME'          => ['check-circle-fill', 'success', 'Conforme'],
    'CON_OBSERVACIONES' => ['exclamation-triangle-fill', 'warning', 'Con observaciones'],
    'NO_ENCONTRADO'     => ['x-circle-fill', 'danger', 'No encontrado'],
];

$titulo = 'Registrar control';
$scripts = ['assets/js/envio.js', 'assets/js/control.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="formulario-angosto mx-auto" x-data="registroControl(<?= e(json_encode($configuracion)) ?>)">
    <a href="<?= e(armar_url()) ?>" class="d-inline-block small text-body-secondary text-decoration-none mb-2">
        <i class="bi bi-arrow-left"></i> Mapa
    </a>

    <!-- Encabezado del operativo, con el borde del color del estado -->
    <div class="card shadow-sm mb-3 encabezado-operativo tarjeta-<?= e(strtolower($estado)) ?>">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-2">
                <h1 class="h5 mb-0 me-auto"><?= e($operativo['unidad_nombre']) ?></h1>
                <?= mostrar_estado($estado) ?>
            </div>
            <div class="small text-body-secondary">
                <?= e(obtener_etiqueta_turno($operativo['turno'])) ?> ·
                <?= e(formatear_hora($operativo['inicio_programado'])) ?> – <?= e(formatear_hora($operativo['fin_programado'])) ?>
            </div>
        </div>
    </div>

    <!-- Control registrado -->
    <div x-show="fase === 'hecho'" x-cloak class="card shadow-sm mb-3 control-registrado">
        <div class="card-body text-center py-4">
            <i class="bi bi-check-circle-fill display-4 texto-institucional"></i>
            <h2 class="h5 mt-2 mb-3">Control registrado</h2>
            <a href="<?= e(armar_url()) ?>" class="btn btn-institucional boton-accion px-4"><i class="bi bi-map"></i> Volver al mapa</a>
        </div>
    </div>

    <form x-show="fase !== 'hecho'" @submit.prevent="enviar()" novalidate>

        <!-- Resultado: tres botones grandes en una fila -->
        <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6 mb-2">Resultado</legend>
            <div class="botones-resultado">
                <?php foreach ($botones_resultado as $clave => [$icono, $color, $etiqueta]): ?>
                    <input type="radio" class="btn-check" name="resultado" id="resultado-<?= e(strtolower($clave)) ?>" value="<?= e($clave) ?>" x-model="resultado">
                    <label class="btn btn-outline-<?= e($color) ?> boton-resultado" for="resultado-<?= e(strtolower($clave)) ?>">
                        <i class="bi bi-<?= e($icono) ?>"></i>
                        <span><?= e($etiqueta) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="text-danger small mt-1" x-show="errores.resultado" x-text="errores.resultado"></div>
        </fieldset>

        <div class="mb-3">
            <label for="observacion" class="form-label fw-semibold">
                Observación <span class="fw-normal text-body-secondary small" x-show="resultado === 'CONFORME'">(opcional)</span>
            </label>
            <textarea id="observacion" rows="3" class="form-control" x-model="observacion" :class="errores.observacion && 'is-invalid'"></textarea>
            <div class="invalid-feedback" x-text="errores.observacion"></div>
        </div>

        <div x-show="fase === 'sin_conexion'" x-cloak class="alert alert-warning py-2 small">
            <i class="bi bi-wifi-off"></i> Sin conexión: se enviará solo al volver la señal.
            <button type="button" class="btn btn-link btn-sm p-0 ms-1" @click="reintentar()">Reintentar ahora</button>
        </div>
        <div x-show="errores.general" x-cloak class="alert alert-danger py-2 small" x-text="errores.general"></div>

        <!-- Guardar, fijo al pie (como en el asistente de programación) -->
        <div class="barra-pasos mb-3">
            <button type="submit" class="btn btn-institucional boton-accion w-100" :disabled="fase === 'enviando' || fase === 'sin_conexion'">
                <span x-show="fase === 'enviando'" class="spinner-border spinner-border-sm" role="status"></span>
                <i class="bi bi-check-lg" x-show="fase !== 'enviando'"></i> Guardar control
            </button>
        </div>
    </form>

    <!-- Controles anteriores (el recién guardado se agrega arriba, resaltado) -->
    <div class="card card-body shadow-sm mb-4" x-show="controles.length" x-cloak>
        <h2 class="h6 titulo-tarjeta"><i class="bi bi-clipboard-check"></i> Controles anteriores · <span x-text="controles.length"></span></h2>
        <ul class="list-group list-group-flush small">
            <template x-for="c in controles" :key="c.id">
                <li class="list-group-item px-0" :class="c.id === ultimoId && 'control-nuevo'">
                    <span class="badge" :class="claseResultado(c.resultado)" x-text="c.etiqueta"></span>
                    <span x-text="c.hora + (c.supervisor !== config.yo ? ' · ' + c.supervisor : '')"></span>
                    <div class="text-body-secondary" x-show="c.observacion" x-text="c.observacion"></div>
                </li>
            </template>
        </ul>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
