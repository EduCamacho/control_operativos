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

$turno = obtener_etiqueta_turno($operativo['turno']);
$hora_inicio = convertir_a_fecha_hora($operativo['inicio_programado']);
$hora_fin = convertir_a_fecha_hora($operativo['fin_programado']);
$con_cantidad = array_values(array_filter($resultados, static fn (array $r): bool => (int) $r['cantidad'] > 0));
$tiene_nota = $operativo['nota_informativa'] !== null && $operativo['nota_informativa'] !== '';

$titulo = 'Operativo ' . $turno;
$scripts = ['assets/js/envio.js', 'assets/js/ubicacion.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<a href="<?= e(armar_url()) ?>" class="d-inline-block small text-body-secondary text-decoration-none mb-2">
    <i class="bi bi-arrow-left"></i> Mis operativos
</a>

<!-- Encabezado: unidad, turno y estado, con el borde del color del estado -->
<div class="card shadow-sm mb-3 encabezado-operativo tarjeta-<?= e(strtolower($estado)) ?>">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h1 class="h5 mb-0 me-auto"><?= e($operativo['unidad_nombre']) ?></h1>
            <?= mostrar_estado($estado, pendiente_nota($operativo)) ?>
        </div>
        <div class="text-body-secondary">
            Turno <strong class="text-body"><?= e($turno) ?></strong> · <?= e(formatear_hora($hora_inicio)) ?> – <?= e(formatear_hora($hora_fin)) ?>
            <?php if ($hora_fin->format('Y-m-d') !== $hora_inicio->format('Y-m-d')): ?><span class="small">(día siguiente)</span><?php endif; ?>
        </div>
        <div class="small text-body-secondary">
            <?php if ($operativo['inicio_real'] !== null): ?>
                Iniciado <?= e(formatear_hora($operativo['inicio_real'])) ?>
                <?php if ($operativo['fin_real'] !== null): ?>
                    · finalizado <?= e(formatear_hora($operativo['fin_real'])) ?>
                <?php else: ?>
                    · <?= e(formatear_hace($operativo['inicio_real'], $ahora)) ?>
                <?php endif; ?>
            <?php else: ?>
                Servicio diario del <?= e(formatear_fecha_larga(validar_fecha($operativo['fecha_servicio']))) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <?php if ($operativo['estado'] === 'PROGRAMADO'): ?>
            <div x-data="capturaUbicacion(<?= $configuracion_captura('INICIAR') ?>)">
                <?php if ($puede_iniciar): ?>
                    <button type="button" class="btn btn-institucional btn-lg w-100 boton-accion mb-3" x-show="fase === 'inactiva'" @click="abrir()">
                        <i class="bi bi-play-fill"></i> Iniciar operativo
                    </button>
                    <?php $captura = ['titulo' => 'Iniciar operativo', 'boton' => 'Iniciar operativo']; ?>
                    <?php require RUTA_APP . '/vistas/captura_ubicacion.php'; ?>
                <?php elseif ($ahora < $desde_inicio): ?>
                    <div class="aviso-estado mb-3">
                        <i class="bi bi-clock"></i> Podrá iniciarlo desde las <strong><?= e(formatear_hora($desde_inicio)) ?></strong>.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger mb-3">
                        <i class="bi bi-x-octagon"></i> Pasó la hora de fin programada sin iniciarse: ya no se puede iniciar.
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($operativo['estado'] === 'EN_CURSO'): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 titulo-tarjeta"><i class="bi bi-geo-alt-fill"></i> Posición actual</h2>
                    <?php if ($actual === null): ?>
                        <p class="text-body-secondary mb-0">Todavía no hay puntos registrados.</p>
                    <?php else: ?>
                        <div class="fs-5 fw-semibold lh-sm"><?= e($actual['referencia']) ?></div>
                        <div class="text-body-secondary small mt-1">
                            <?= e(formatear_hora($actual['registrado_en'])) ?> · <?= e(formatear_hace($actual['registrado_en'], $ahora)) ?> ·
                            <?= $actual['origen'] === 'MANUAL'
                                ? '<span class="badge text-bg-warning">Manual</span>'
                                : 'precisión ' . e($actual['precision_m'] ?? '?') . ' m' ?>
                        </div>
                        <a href="<?= e(armar_enlace_google_maps((float) $actual['latitud'], (float) $actual['longitud'])) ?>"
                           target="_blank" rel="noopener" class="d-inline-block small mt-1 enlace-institucional">
                            <i class="bi bi-box-arrow-up-right"></i> Ver en Google Maps
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div x-data="capturaUbicacion(<?= $configuracion_captura('PUNTO') ?>)">
                <div class="d-grid gap-2 mb-3" x-show="fase === 'inactiva'">
                    <button type="button" class="btn btn-institucional btn-lg boton-accion" @click="abrir()">
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
                    <div class="d-flex align-items-center mb-2">
                        <h2 class="h6 titulo-tarjeta mb-0 me-auto"><i class="bi bi-clipboard-data"></i> Resultados</h2>
                        <?php if ($con_cantidad === []): ?>
                            <span class="badge text-bg-secondary">Negativo</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Positivo</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($con_cantidad === []): ?>
                        <p class="text-body-secondary mb-0">Sin resultados: todas las cantidades quedaron en 0.</p>
                    <?php else: ?>
                        <div class="cifras">
                            <?php foreach ($con_cantidad as $resultado): ?>
                                <div class="cifra">
                                    <div class="cifra-numero"><?= e($resultado['cantidad']) ?></div>
                                    <div class="cifra-etiqueta"><?= e(mb_strtolower($resultado['nombre'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 titulo-tarjeta"><i class="bi bi-file-earmark-text"></i> Nota informativa (SICPIP)</h2>
                    <?php if ($tiene_nota): ?>
                        <div class="fw-semibold"><?= e(formatear_nota_informativa($operativo['nota_informativa'])) ?></div>
                    <?php else: ?>
                        <form method="post" action="<?= e(armar_url('operativos/nota')) ?>">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <label for="nota_informativa" class="form-label small text-body-secondary">Todavía no tiene nota. Escriba solo el número.</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">N°</span>
                                <input type="text" id="nota_informativa" name="nota_informativa" inputmode="numeric" maxlength="40" required
                                       class="form-control" placeholder="202601802185">
                                <button type="submit" class="btn btn-institucional">Guardar</button>
                            </div>
                            <div class="form-text">Una vez guardada, solo el administrador puede cambiarla.</div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ubicaciones !== []): ?>
            <!-- En la PC el recorrido va aquí, bajo la acción; en el celular, al final (abajo). -->
            <div class="d-none d-lg-block">
                <?php require RUTA_APP . '/vistas/recorrido_operativo.php'; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex align-items-center">
                <h2 class="h6 titulo-tarjeta mb-0 me-auto"><i class="bi bi-card-checklist"></i> Datos del operativo</h2>
                <?php if ($puede_editar): ?>
                    <a href="<?= e(armar_url('operativos/programar', ['id' => $id])) ?>" class="btn btn-sm btn-outline-institucional">
                        <i class="bi bi-pencil"></i> Editar
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="dato">
                    <i class="bi bi-tags dato-icono"></i>
                    <div><div class="dato-etiqueta">Tipo(s)</div><?= e(implode(', ', array_column($tipos, 'nombre'))) ?></div>
                </div>
                <div class="dato">
                    <i class="bi bi-geo-alt dato-icono"></i>
                    <div>
                        <div class="dato-etiqueta">Lugar programado</div>
                        <?= e($operativo['lugar_programado']) ?>
                        <?php if ($operativo['lugar_latitud'] !== null): ?>
                            <a href="<?= e(armar_enlace_google_maps((float) $operativo['lugar_latitud'], (float) $operativo['lugar_longitud'])) ?>"
                               target="_blank" rel="noopener" class="d-block small enlace-institucional">Ver en Google Maps</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dato">
                    <i class="bi bi-person-badge dato-icono"></i>
                    <div class="flex-fill">
                        <div class="dato-etiqueta">Jefe del operativo</div>
                        <?= e($operativo['jefe_grado'] . ' ' . $operativo['jefe_nombres']) ?>
                        <div>
                            <a href="tel:<?= e($operativo['jefe_celular']) ?>" class="btn btn-sm btn-outline-institucional mt-1">
                                <i class="bi bi-telephone"></i> Llamar · <?= e($operativo['jefe_celular']) ?>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="dato">
                    <i class="bi bi-people dato-icono"></i>
                    <div class="flex-fill">
                        <div class="dato-etiqueta">Fuerzas</div>
                        <div class="cifras cifras-compactas">
                            <div class="cifra"><div class="cifra-numero"><?= e($operativo['fuerzas_propias']) ?></div><div class="cifra-etiqueta">propias</div></div>
                            <div class="cifra"><div class="cifra-numero"><?= e($operativo['fuerzas_apoyo']) ?></div><div class="cifra-etiqueta">de apoyo</div></div>
                            <div class="cifra"><div class="cifra-numero"><?= e($operativo['unidades_moviles']) ?></div><div class="cifra-etiqueta">móviles</div></div>
                        </div>
                    </div>
                </div>
                <?php if ($operativo['observaciones'] !== null): ?>
                    <div class="dato">
                        <i class="bi bi-chat-left-text dato-icono"></i>
                        <div><div class="dato-etiqueta">Observaciones</div><div class="text-break" style="white-space: pre-line"><?= e($operativo['observaciones']) ?></div></div>
                    </div>
                <?php endif; ?>
                <?php if ($puede_eliminar): ?>
                    <form method="post" action="<?= e(armar_url('operativos/eliminar')) ?>" class="mt-3 pt-3 border-top text-end"
                          onsubmit="return confirm('¿Eliminar este operativo? Solo se puede eliminar antes de su hora de inicio.')">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none"><i class="bi bi-trash"></i> Eliminar operativo</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php if ($ubicaciones !== []): ?>
    <!-- En el celular, el recorrido es la última tarjeta: puede crecer mucho y no debe desplazar los datos. -->
    <div class="d-lg-none">
        <?php require RUTA_APP . '/vistas/recorrido_operativo.php'; ?>
    </div>
<?php endif; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
