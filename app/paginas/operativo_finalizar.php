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
$ahora = obtener_ahora();
$configuracion = [
    'operativoId' => (int) $id,
    'conceptos'   => array_map(static fn (array $c): int => (int) $c['id'], $conceptos),
    'maximo'      => MAX_CANTIDAD_RESULTADO,
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
<div class="formulario-angosto">
    <h1 class="h4 mb-1">Finalizar operativo</h1>
    <p class="text-body-secondary mb-3">
        <?= e($operativo['unidad_nombre']) ?> · turno <?= e(obtener_etiqueta_turno($operativo['turno'])) ?><br>
        Iniciado a las <?= e(formatear_hora($operativo['inicio_real'])) ?> · <?= e(formatear_hace($operativo['inicio_real'], $ahora)) ?>
    </p>
</div>

<form class="formulario-angosto" novalidate x-data="formularioCierre(<?= e(json_encode($configuracion)) ?>)" @submit.prevent="confirmar()">
    <div class="card shadow-sm mb-3">
        <!-- El resultado se ve mientras se completa: Negativo o Positivo con el total (RN-12). -->
        <div class="card-header bg-transparent d-flex align-items-center gap-2">
            <h2 class="h6 titulo-tarjeta mb-0 me-auto"><i class="bi bi-clipboard-data"></i> Resultados</h2>
            <span x-show="total === 0" class="badge text-bg-secondary">Negativo</span>
            <span x-show="total > 0" x-cloak class="badge text-bg-success" x-text="'Positivo · ' + total"></span>
        </div>
        <p class="small text-body-secondary px-3 pt-2 mb-0">Marque solo lo que obtuvo. Si todo queda en 0, el resultado es <strong>negativo</strong>.</p>
        <ul class="list-group list-group-flush lista-resultados">
            <?php foreach ($conceptos as $concepto): ?>
                <?php
                $concepto_id = (int) $concepto['id'];
                $etiqueta = mb_strtoupper(mb_substr($concepto['nombre'], 0, 1)) . mb_strtolower(mb_substr($concepto['nombre'], 1));
                ?>
                <li class="list-group-item fila-resultado" :class="cantidades[<?= $concepto_id ?>] > 0 && 'con-cantidad'">
                    <label for="concepto-<?= $concepto_id ?>" class="fila-resultado-nombre">
                        <?= e($etiqueta) ?>
                        <?php if ($concepto['descripcion'] !== null): ?>
                            <span class="d-block small text-body-secondary fw-normal"><?= e($concepto['descripcion']) ?></span>
                        <?php endif; ?>
                    </label>
                    <div class="input-group campo-contador contador-compacto">
                        <button type="button" class="btn btn-outline-secondary" aria-label="Restar uno a <?= e($etiqueta) ?>"
                                @click="cambiar(<?= $concepto_id ?>, -1)" :disabled="cantidades[<?= $concepto_id ?>] === 0">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                        <input type="number" id="concepto-<?= $concepto_id ?>" name="cantidad[<?= $concepto_id ?>]"
                               x-model.number="cantidades[<?= $concepto_id ?>]" @change="normalizar(<?= $concepto_id ?>)" @focus="$event.target.select()"
                               min="0" max="<?= MAX_CANTIDAD_RESULTADO ?>" step="1" inputmode="numeric" value="0" class="form-control text-center">
                        <button type="button" class="btn btn-outline-secondary" aria-label="Sumar uno a <?= e($etiqueta) ?>"
                                @click="cambiar(<?= $concepto_id ?>, 1)">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card card-body shadow-sm mb-3">
        <div class="mb-3">
            <label for="nota_informativa" class="form-label">Nota informativa SICPIP <span class="text-body-secondary">(opcional)</span></label>
            <div class="input-group has-validation">
                <span class="input-group-text">N°</span>
                <input type="text" id="nota_informativa" name="nota_informativa" inputmode="numeric" maxlength="40"
                       class="form-control" :class="errores.nota_informativa && 'is-invalid'" placeholder="202601802185">
                <div class="invalid-feedback" x-text="errores.nota_informativa"></div>
            </div>
            <div class="form-text">Escriba solo el número. Si todavía no la tiene, podrá agregarla después.</div>
        </div>
        <div>
            <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
            <textarea id="observaciones" name="observaciones" rows="3" class="form-control"><?= e($operativo['observaciones'] ?? '') ?></textarea>
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
                cantidades: Object.fromEntries(config.conceptos.map((id) => [id, 0])),
                get total() {
                    return Object.values(this.cantidades).reduce((suma, n) => suma + (parseInt(n, 10) || 0), 0);
                },
                cambiar(id, paso) {
                    this.cantidades[id] = (parseInt(this.cantidades[id], 10) || 0) + paso;
                    this.normalizar(id);
                },
                /** Entero entre 0 y el máximo; vacío o inválido queda en 0. */
                normalizar(id) {
                    const valor = parseInt(this.cantidades[id], 10);
                    this.cantidades[id] = isFinite(valor) ? Math.min(config.maximo, Math.max(0, valor)) : 0;
                },
                leerDatos() {
                    const formulario = this.$root;
                    config.conceptos.forEach((id) => this.normalizar(id));
                    return {
                        operativo_id: config.operativoId,
                        cantidades: { ...this.cantidades },
                        observaciones: formulario.querySelector('#observaciones').value,
                        nota_informativa: formulario.querySelector('#nota_informativa').value,
                    };
                },
                async confirmar() {
                    const datos = this.leerDatos();
                    const total = this.total;
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
