<?php
/**
 * SUPERVISOR: registrar un control inopinado (RN-15, docs/04).
 * Intenta el GPS del supervisor (opcional: sin rectángulo ni advertencia de
 * precisión), pide el resultado y la observación, y al guardar muestra la
 * distancia al último punto del operativo.
 */
declare(strict_types=1);

requiere_rol('SUPERVISOR');
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

$ubicaciones = obtener_ubicaciones($id);
$ultima = $ubicaciones === [] ? null : $ubicaciones[array_key_last($ubicaciones)];
$configuracion = [
    'operativoId'    => (int) $id,
    'urlEnvio'       => armar_url('api/controles'),
    'urlCoordenadas' => armar_url('api/coordenadas'),
    'urlFicha'       => armar_url('supervisor/operativo', ['id' => $id]),
    'urlIngreso'     => armar_url('login'),
];

$titulo = 'Registrar control';
$scripts = ['assets/js/envio.js', 'assets/js/control.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url()) ?>">Mapa</a></li>
        <li class="breadcrumb-item"><a href="<?= e(armar_url('supervisor/operativo', ['id' => $id])) ?>"><?= e($operativo['unidad_nombre']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Control</li>
    </ol>
</nav>
<h1 class="h4 mb-1">Control inopinado</h1>
<p class="text-body-secondary mb-3">
    <?= e($operativo['unidad_nombre']) ?> · turno <?= e(obtener_etiqueta_turno($operativo['turno'])) ?>
    · <?= mostrar_estado(calcular_estado_visible($operativo, $ahora)) ?>
</p>

<div class="formulario-angosto" x-data="registroControl(<?= e(json_encode($configuracion)) ?>)" x-init="obtenerGps()">
    <div class="card card-body shadow-sm mb-3 small">
        <div class="text-body-secondary">Último punto del operativo</div>
        <?php if ($ultima !== null): ?>
            <div><?= mostrar_ubicacion($ultima) ?> · <?= e(formatear_hace($ultima['registrado_en'], $ahora)) ?></div>
        <?php else: ?>
            <div>Sin ubicación registrada. Lugar programado: <?= e($operativo['lugar_programado']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Resultado guardado -->
    <div x-show="fase === 'hecho'" x-cloak class="alert alert-success">
        <h2 class="h5"><i class="bi bi-check-circle-fill"></i> Control registrado</h2>
        <p class="mb-2" x-show="distancia !== null">Distancia al último punto del operativo: <strong x-text="textoDistancia"></strong>.</p>
        <p class="mb-2" x-show="distancia === null">Sin distancia: falta su ubicación o la del operativo.</p>
        <div class="d-grid gap-2 d-sm-flex">
            <a href="<?= e(armar_url()) ?>" class="btn btn-institucional boton-accion"><i class="bi bi-map"></i> Volver al mapa</a>
            <a :href="config.urlFicha" class="btn btn-outline-institucional boton-accion">Ver la ficha</a>
        </div>
    </div>

    <form x-show="fase !== 'hecho'" @submit.prevent="enviar()" novalidate>
        <!-- Ubicación del supervisor (opcional) -->
        <div class="card card-body shadow-sm mb-3" aria-live="polite">
            <h2 class="h6">Su ubicación <span class="text-body-secondary fw-normal">(opcional)</span></h2>
            <div x-show="ubicacion.estado === 'obteniendo'" class="fila-flex align-items-center gap-2 small">
                <div class="spinner-border spinner-border-sm text-success" role="status"></div> Obteniendo ubicación…
            </div>
            <div x-show="ubicacion.estado === 'lista'" class="small">
                <i class="bi bi-check-circle-fill text-success"></i> <span x-text="textoUbicacion"></span>
                <button type="button" class="btn btn-link btn-sm p-0 ms-2" @click="quitarUbicacion()">Quitar</button>
            </div>
            <div x-show="ubicacion.estado === 'error'" class="small">
                <p class="text-danger mb-2"><i class="bi bi-geo-alt"></i> <span x-text="ubicacion.mensaje"></span></p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="obtenerGps()"><i class="bi bi-arrow-repeat"></i> Reintentar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="ubicacion.estado = 'manual'">Ingresar manualmente</button>
                </div>
                <p class="text-body-secondary mt-2 mb-0">Puede guardar el control sin ubicación; solo no se calculará la distancia.</p>
            </div>
            <div x-show="ubicacion.estado === 'manual'" class="small">
                <label for="coordenadas-pegadas" class="form-label">Pegar coordenadas de Google Maps</label>
                <div class="input-group">
                    <input type="text" id="coordenadas-pegadas" class="form-control" x-model="textoPegado" placeholder="-8.111600, -79.028800">
                    <button type="button" class="btn btn-outline-institucional" @click="usarTextoPegado()">Usar</button>
                </div>
                <div class="text-danger mt-1" x-show="errorManual" x-text="errorManual"></div>
                <button type="button" class="btn btn-link btn-sm p-0 mt-1" @click="obtenerGps()">Volver a intentar con GPS</button>
            </div>
        </div>

        <div class="card card-body shadow-sm mb-3">
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Resultado</legend>
                <div class="d-grid gap-2">
                    <?php foreach (RESULTADOS_CONTROL as $clave => $etiqueta): ?>
                        <input type="radio" class="btn-check" name="resultado" id="resultado-<?= e(strtolower($clave)) ?>" value="<?= e($clave) ?>" x-model="resultado">
                        <label class="btn boton-accion btn-outline-<?= $clave === 'CONFORME' ? 'success' : ($clave === 'NO_ENCONTRADO' ? 'danger' : 'warning') ?>"
                               for="resultado-<?= e(strtolower($clave)) ?>"><?= e($etiqueta) ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="text-danger small mt-1" x-show="errores.resultado" x-text="errores.resultado"></div>
            </fieldset>
            <label for="observacion" class="form-label">
                Observación <span class="text-body-secondary" x-show="resultado === 'CONFORME'">(opcional)</span>
            </label>
            <textarea id="observacion" rows="3" class="form-control" x-model="observacion" :class="errores.observacion && 'is-invalid'"
                      placeholder="Personal, ubicación, lo verificado…"></textarea>
            <div class="invalid-feedback" x-text="errores.observacion"></div>
        </div>

        <div x-show="fase === 'sin_conexion'" x-cloak class="alert alert-warning">
            <i class="bi bi-wifi-off"></i> <strong>Sin conexión:</strong> el control se enviará solo cuando vuelva la señal. No cierre esta página.
            <button type="button" class="btn btn-warning btn-sm ms-2" @click="reintentar()">Reintentar ahora</button>
        </div>
        <div x-show="errores.general" x-cloak class="alert alert-danger" x-text="errores.general"></div>

        <button type="submit" class="btn btn-institucional btn-lg w-100 boton-accion mb-4" :disabled="fase === 'enviando' || fase === 'sin_conexion'">
            <span x-show="fase === 'enviando'" class="spinner-border spinner-border-sm" role="status"></span>
            Guardar control
        </button>
    </form>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
