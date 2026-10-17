<?php
/**
 * UNIDAD: ficha del operativo con la acción que toca según su estado (docs/04):
 *   PROGRAMADO → Iniciar (captura la primera ubicación), Editar, Eliminar.
 *   EN_CURSO   → Registrar nuevo punto y Finalizar operativo; ubicación actual y recorrido.
 *   FINALIZADO → resultados y, si falta, Agregar nota informativa.
 * No carga Google Maps: las ubicaciones son enlaces (RN-21).
 */
declare(strict_types=1);

$unidad_id = unidad_de_sesion();
$ahora = obtener_ahora();

$id = obtener_id_get();
$operativo = $id === null ? null : obtener_operativo_de_unidad($id, $unidad_id);
if ($operativo === null) {
    guardar_mensaje('danger', 'El operativo no existe.');
    redirigir('');
}

$estado = calcular_estado_visible($operativo, $ahora);
$tipos = obtener_tipos_de_operativo($id);
$ubicaciones = obtener_ubicaciones($id);
$actual = $ubicaciones === [] ? null : $ubicaciones[array_key_last($ubicaciones)];
$resultados = $operativo['estado'] === 'FINALIZADO' ? obtener_resultados($id) : [];

$puede_iniciar = puede_iniciar($operativo, $ahora, VENTANA_INICIO_MIN);
$puede_eliminar = puede_eliminar($operativo, $ahora, false) && contar_controles($id) === 0;
$puede_editar = campos_editables($operativo['estado'], paso_hora_inicio($operativo, $ahora)) !== [];
$desde_inicio = convertir_a_fecha_hora($operativo['inicio_programado'])->modify('-' . VENTANA_INICIO_MIN . ' minutes');

$configuracion_captura = static fn (string $accion): string => e(json_encode([
    'accion'          => $accion,
    'operativoId'     => (int) $id,
    'urlEnvio'        => armar_url('api/ubicaciones'),
    'urlCoordenadas'  => armar_url('api/coordenadas'),
    'urlDespues'      => armar_url('operativos/ver', ['id' => $id]),
    'urlIngreso'      => armar_url('login'),
    'precisionMaxima' => PRECISION_ADVERTENCIA_M,
    'limites'         => ['latitudMin' => LATITUD_MIN, 'latitudMax' => LATITUD_MAX, 'longitudMin' => LONGITUD_MIN, 'longitudMax' => LONGITUD_MAX],
]));

$titulo = 'Operativo ' . obtener_etiqueta_turno($operativo['turno']);
$scripts = ['assets/js/envio.js', 'assets/js/ubicacion.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url()) ?>">Mis operativos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Operativo</li>
    </ol>
</nav>

<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0">Turno <?= e(obtener_etiqueta_turno($operativo['turno'])) ?></h1>
    <?= mostrar_estado($estado, pendiente_nota($operativo)) ?>
</div>
<p class="text-body-secondary mb-3">
    Servicio diario del <?= e(formatear_fecha_larga(validar_fecha($operativo['fecha_servicio']))) ?>
</p>

<div class="row g-3">
    <div class="col-lg-7">
        <?php if ($operativo['estado'] === 'PROGRAMADO'): ?>
            <div x-data="capturaUbicacion(<?= $configuracion_captura('INICIAR') ?>)">
                <?php if ($puede_iniciar): ?>
                    <button type="button" class="btn btn-success btn-lg w-100 boton-accion mb-3" x-show="fase === 'inactiva'" @click="abrir()">
                        <i class="bi bi-play-fill"></i> Iniciar operativo
                    </button>
                    <?php $captura = ['titulo' => 'Iniciar operativo', 'boton' => 'Iniciar operativo']; ?>
                    <?php require RUTA_APP . '/vistas/captura_ubicacion.php'; ?>
                <?php elseif ($ahora < $desde_inicio): ?>
                    <div class="alert alert-secondary"><i class="bi bi-clock"></i> Podrá iniciarlo desde las <strong><?= e(formatear_hora($desde_inicio)) ?></strong>.</div>
                <?php else: ?>
                    <div class="alert alert-danger"><i class="bi bi-x-octagon"></i> Pasó la hora de fin programada sin iniciarse: ya no se puede iniciar.</div>
                <?php endif; ?>
            </div>
        <?php elseif ($operativo['estado'] === 'EN_CURSO'): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="small text-body-secondary">Ubicación actual</div>
                    <div class="fs-6"><?= $actual === null ? 'Sin ubicación' : mostrar_ubicacion($actual) ?></div>
                    <?php if ($actual !== null): ?>
                        <div class="small text-body-secondary">Actualizada <?= e(formatear_hace($actual['registrado_en'], $ahora)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div x-data="capturaUbicacion(<?= $configuracion_captura('PUNTO') ?>)">
                <div class="d-grid gap-2 mb-3" x-show="fase === 'inactiva'">
                    <button type="button" class="btn btn-primary btn-lg boton-accion" @click="abrir()">
                        <i class="bi bi-geo-alt-fill"></i> Registrar nuevo punto
                    </button>
                    <a href="<?= e(armar_url('operativos/finalizar', ['id' => $id])) ?>" class="btn btn-outline-danger btn-lg boton-accion">
                        <i class="bi bi-stop-circle"></i> Finalizar operativo
                    </a>
                </div>
                <?php $captura = ['titulo' => 'Nuevo punto', 'boton' => 'Registrar punto']; ?>
                <?php require RUTA_APP . '/vistas/captura_ubicacion.php'; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6">Resultados
                        <?php if (es_resultado_negativo(array_column($resultados, 'cantidad'))): ?>
                            <span class="badge text-bg-secondary">Negativo</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Positivo</span>
                        <?php endif; ?>
                    </h2>
                    <?php $con_cantidad = array_filter($resultados, static fn (array $r): bool => (int) $r['cantidad'] > 0); ?>
                    <?php if ($con_cantidad === []): ?>
                        <p class="text-body-secondary mb-0">Sin resultados (todas las cantidades en 0).</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($con_cantidad as $resultado): ?>
                                <li><strong><?= e($resultado['cantidad']) ?></strong> <?= e(mb_strtolower($resultado['nombre'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <?php if ($operativo['nota_informativa'] !== null && $operativo['nota_informativa'] !== ''): ?>
                        <div class="fw-semibold"><?= e(formatear_nota_informativa($operativo['nota_informativa'])) ?></div>
                    <?php else: ?>
                        <form method="post" action="<?= e(armar_url('operativos/nota')) ?>">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <label for="nota_informativa" class="form-label fw-semibold">Agregar nota informativa (SICPIP)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">N°</span>
                                <input type="text" id="nota_informativa" name="nota_informativa" inputmode="numeric" maxlength="40" required
                                       class="form-control" placeholder="202601802185">
                                <button type="submit" class="btn btn-primary">Guardar</button>
                            </div>
                            <div class="form-text">Solo el número. Una vez guardada, solo el administrador puede cambiarla.</div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ubicaciones !== []): ?>
            <h2 class="h6 text-uppercase text-body-secondary">Recorrido · <?= count($ubicaciones) ?> punto(s)</h2>
            <ul class="list-group mb-3">
                <?php foreach (array_reverse($ubicaciones, true) as $indice => $ubicacion): ?>
                    <li class="list-group-item small d-flex gap-2">
                        <span class="badge rounded-pill text-bg-light border align-self-start"><?= $indice + 1 ?></span>
                        <span><?= mostrar_ubicacion($ubicacion) ?><?= $indice === 0 ? ' <span class="badge text-bg-success">Inicio</span>' : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Programado</dt>
                    <dd class="col-7">
                        <?= e(convertir_a_fecha_hora($operativo['inicio_programado'])->format('d/m H:i')) ?> –
                        <?= e(convertir_a_fecha_hora($operativo['fin_programado'])->format('d/m H:i')) ?>
                    </dd>
                    <?php if ($operativo['inicio_real'] !== null): ?>
                        <dt class="col-5">Real</dt>
                        <dd class="col-7">
                            <?= e(convertir_a_fecha_hora($operativo['inicio_real'])->format('d/m H:i')) ?> –
                            <?= $operativo['fin_real'] === null ? 'en curso' : e(convertir_a_fecha_hora($operativo['fin_real'])->format('d/m H:i')) ?>
                        </dd>
                    <?php endif; ?>
                    <dt class="col-5">Tipo(s)</dt>
                    <dd class="col-7"><?= e(implode(', ', array_column($tipos, 'nombre'))) ?></dd>
                    <?php if ($operativo['nombre_plan'] !== null): ?>
                        <dt class="col-5">Plan</dt>
                        <dd class="col-7"><?= e($operativo['nombre_plan']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-5">Lugar programado</dt>
                    <dd class="col-7"><?= e($operativo['lugar_programado']) ?></dd>
                    <dt class="col-5">Jefe</dt>
                    <dd class="col-7">
                        <?= e($operativo['jefe_grado'] . ' ' . $operativo['jefe_nombres']) ?><br>
                        <a href="tel:<?= e($operativo['jefe_celular']) ?>"><i class="bi bi-telephone"></i> <?= e($operativo['jefe_celular']) ?></a>
                    </dd>
                    <dt class="col-5">Fuerzas</dt>
                    <dd class="col-7">
                        <?= e($operativo['fuerzas_propias']) ?> propias · <?= e($operativo['fuerzas_apoyo']) ?> de apoyo ·
                        <?= e($operativo['unidades_moviles']) ?> unidad(es) móvil(es)
                    </dd>
                    <?php if ($operativo['observaciones'] !== null): ?>
                        <dt class="col-5">Observaciones</dt>
                        <dd class="col-7 text-break" style="white-space: pre-line"><?= e($operativo['observaciones']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($puede_editar): ?>
                <a href="<?= e(armar_url('operativos/programar', ['id' => $id])) ?>" class="btn btn-outline-primary boton-accion">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            <?php endif; ?>
            <?php if ($puede_eliminar): ?>
                <form method="post" action="<?= e(armar_url('operativos/eliminar')) ?>"
                      onsubmit="return confirm('¿Eliminar este operativo? Solo se puede eliminar antes de su hora de inicio.')">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <button type="submit" class="btn btn-outline-danger boton-accion"><i class="bi bi-trash"></i> Eliminar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
