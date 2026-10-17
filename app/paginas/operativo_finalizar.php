<?php
/**
 * UNIDAD: finalizar el operativo con sus resultados (RN-12, RN-13, docs/04).
 * Un campo por concepto de resultado (0 por defecto), observaciones y nota
 * informativa opcionales, y confirmación. Se envía a /api/finalizar con reintento.
 */
declare(strict_types=1);

$unidad_id = unidad_de_sesion();
$id = obtener_id_get();
$operativo = $id === null ? null : obtener_operativo_de_unidad($id, $unidad_id);
if ($operativo === null) {
    guardar_mensaje('danger', 'El operativo no existe.');
    redirigir('');
}
if (!puede_finalizar($operativo)) {
    guardar_mensaje('info', 'Solo se puede finalizar un operativo en curso.');
    redirigir('operativos/ver', ['id' => $id]);
}

$conceptos = obtener_conceptos_activos();
$configuracion = [
    'operativoId' => (int) $id,
    'urlEnvio'    => armar_url('api/finalizar'),
    'urlDespues'  => armar_url('operativos/ver', ['id' => $id]),
    'urlIngreso'  => armar_url('login'),
];

$titulo = 'Finalizar operativo';
$scripts = ['assets/js/envio.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url()) ?>">Mis operativos</a></li>
        <li class="breadcrumb-item"><a href="<?= e(armar_url('operativos/ver', ['id' => $id])) ?>">Operativo</a></li>
        <li class="breadcrumb-item active" aria-current="page">Finalizar</li>
    </ol>
</nav>
<h1 class="h4 mb-1">Finalizar operativo</h1>
<p class="text-body-secondary">Turno <?= e(obtener_etiqueta_turno($operativo['turno'])) ?> · iniciado a las <?= e(formatear_hora($operativo['inicio_real'])) ?></p>

<form class="formulario-angosto" novalidate x-data="formularioCierre(<?= e(json_encode($configuracion)) ?>)" @submit.prevent="confirmar()">
    <div class="card card-body shadow-sm mb-3">
        <h2 class="h6">Resultados</h2>
        <p class="small text-body-secondary">Deje en 0 lo que no corresponda. Si todo queda en 0, el resultado es <strong>negativo</strong>.</p>
        <?php foreach ($conceptos as $concepto): ?>
            <?php $parametros = [
                'nombre'        => 'cantidad[' . $concepto['id'] . ']',
                'id'            => 'concepto-' . $concepto['id'],
                'etiqueta'      => mb_strtoupper(mb_substr($concepto['nombre'], 0, 1)) . mb_strtolower(mb_substr($concepto['nombre'], 1)),
                'ayuda'         => $concepto['descripcion'] ?? null,
                'valor'         => 0,
                'minimo'        => 0,
                'maximo'        => MAX_CANTIDAD_RESULTADO,
                'deshabilitado' => false,
                'errores'       => [],
            ]; ?>
            <?php if ($parametros['ayuda'] === null) unset($parametros['ayuda']); ?>
            <?php require RUTA_APP . '/vistas/campo_contador.php'; ?>
        <?php endforeach; ?>
    </div>

    <div class="card card-body shadow-sm mb-3">
        <div class="mb-3">
            <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
            <textarea id="observaciones" name="observaciones" rows="3" class="form-control"><?= e($operativo['observaciones'] ?? '') ?></textarea>
        </div>
        <div>
            <label for="nota_informativa" class="form-label">N° de nota informativa SICPIP <span class="text-body-secondary">(opcional)</span></label>
            <input type="text" id="nota_informativa" name="nota_informativa" inputmode="numeric" maxlength="40"
                   class="form-control" :class="errores.nota_informativa && 'is-invalid'" placeholder="202601802185">
            <div class="invalid-feedback" x-text="errores.nota_informativa"></div>
            <div class="form-text">Si todavía no la tiene, podrá agregarla después.</div>
        </div>
    </div>

    <div x-show="fase === 'sin_conexion'" x-cloak class="alert alert-warning">
        <p class="mb-2"><i class="bi bi-wifi-off"></i> <strong>Sin conexión:</strong> el cierre quedó guardado en esta pantalla y se enviará solo
            cuando vuelva la señal. No cierre esta página.</p>
        <button type="button" class="btn btn-warning boton-accion" @click="reintentar()"><i class="bi bi-arrow-repeat"></i> Reintentar ahora</button>
    </div>
    <div x-show="error" x-cloak class="alert alert-danger" x-text="error"></div>

    <div class="fila-flex flex-wrap gap-2 mb-4" x-show="fase === 'formulario' || fase === 'enviando'">
        <button type="submit" class="btn btn-danger btn-lg boton-accion px-4" :disabled="fase === 'enviando'">
            <span x-show="fase === 'enviando'" class="spinner-border spinner-border-sm" role="status"></span>
            <i class="bi bi-stop-circle" x-show="fase !== 'enviando'"></i> Finalizar operativo
        </button>
        <a href="<?= e(armar_url('operativos/ver', ['id' => $id])) ?>" class="btn btn-outline-secondary btn-lg boton-accion px-4">Cancelar</a>
    </div>
</form>

<script>
    document.addEventListener('alpine:init', function () {
        window.Alpine.data('formularioCierre', function (config) {
            return {
                fase: 'formulario', // formulario | enviando | sin_conexion | hecho
                error: '',
                errores: {},
                envio: null,
                leerDatos() {
                    const formulario = this.$root;
                    const cantidades = {};
                    formulario.querySelectorAll('input[name^="cantidad["]').forEach(function (campo) {
                        cantidades[campo.name.slice(9, -1)] = parseInt(campo.value, 10) || 0;
                    });
                    return {
                        operativo_id: config.operativoId,
                        cantidades: cantidades,
                        observaciones: formulario.querySelector('#observaciones').value,
                        nota_informativa: formulario.querySelector('#nota_informativa').value,
                    };
                },
                async confirmar() {
                    const datos = this.leerDatos();
                    const total = Object.values(datos.cantidades).reduce((suma, n) => suma + n, 0);
                    const resumen = total === 0 ? 'Resultado NEGATIVO: todas las cantidades en 0.' : 'Resultado positivo: ' + total + ' en total.';
                    const confirmado = await window.Confirmar.preguntar({
                        titulo: '¿Finalizar el operativo?',
                        texto: resumen + ' Después ya no podrá modificar los resultados.',
                        boton: 'Sí, finalizar',
                        tipo: 'peligro',
                    });
                    if (!confirmado) {
                        return;
                    }
                    this.error = '';
                    this.errores = {};
                    this.fase = 'enviando';
                    this.envio = window.Envio.enviarConReintento(config.urlEnvio, datos, {
                        alSinConexion: () => { this.fase = 'sin_conexion'; },
                        alTerminar: (resultado) => {
                            if (resultado.ok) {
                                this.fase = 'hecho';
                                window.location.href = config.urlDespues;
                            } else if (resultado.estado === 401) {
                                window.location.href = config.urlIngreso;
                            } else {
                                this.errores = resultado.datos.errores || {};
                                this.error = resultado.datos.error || 'No se pudo finalizar. Intente nuevamente.';
                                this.fase = 'formulario';
                            }
                        },
                    });
                },
                reintentar() {
                    if (this.envio) {
                        this.fase = 'enviando';
                        this.envio.reintentar();
                    }
                },
            };
        });
    });
</script>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
