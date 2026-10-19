<?php
/**
 * ADMIN: corregir cualquier operativo o programarlo en nombre de una unidad (RN-05).
 * Todo es editable en cualquier estado, también con la hora ya pasada. Cada
 * cambio queda en la bitácora con el motivo y el valor anterior y el nuevo.
 *
 * - Reabrir: pasar de FINALIZADO a EN_CURSO borra los resultados (quedan en la bitácora).
 * - Eliminar: con motivo, salvo que tenga controles inopinados (son evidencia).
 */
declare(strict_types=1);

requiere_rol('ADMIN');
$ahora = obtener_ahora();

$id = obtener_id_get();
$actual = null;
if ($id !== null) {
    $actual = obtener_operativo($id);
    if ($actual === null) {
        guardar_mensaje('danger', 'El operativo no existe.');
        redirigir('admin/operativos');
    }
}
$tipos_actuales = $actual === null ? [] : array_map(static fn (array $t): int => (int) $t['id'], obtener_tipos_de_operativo($id));
$cantidades_actuales = $actual === null ? [] : obtener_cantidades($id);
$puntos = $actual === null ? 0 : count(obtener_ubicaciones($id));
$controles = $actual === null ? 0 : contar_controles($id);

$conceptos = consultar('SELECT id, nombre, descripcion, activo FROM conceptos_resultado ORDER BY orden, nombre')->fetchAll();
$conceptos = array_values(array_filter($conceptos, static fn (array $c): bool => $c['activo'] || isset($cantidades_actuales[(int) $c['id']])));
$ids_conceptos = array_map(static fn (array $c): int => (int) $c['id'], $conceptos);
$unidades = consultar('SELECT id, division, nombre_corto, activo FROM unidades ORDER BY orden, nombre_corto')->fetchAll();

$a_local = static fn (?string $fecha_hora): string => $fecha_hora === null ? '' : convertir_a_fecha_hora($fecha_hora)->format('Y-m-d\TH:i');
$valores = $actual === null
    ? [
        'unidad_id' => '', 'fecha_servicio' => fecha_servicio_vigente($ahora), 'hora_inicio' => '', 'hora_fin' => '', 'tipos' => [],
        'lugar_programado' => '', 'lugar_coordenadas' => '', 'jefe_grado' => '', 'jefe_nombres' => '', 'jefe_celular' => '',
        'fuerzas_propias' => 1, 'fuerzas_apoyo' => 0, 'unidades_moviles' => 0, 'observaciones' => '',
        'estado' => 'PROGRAMADO', 'inicio_real' => '', 'fin_real' => '', 'nota_informativa' => '', 'cantidad' => [], 'motivo' => '',
    ]
    : $actual + [
        'hora_inicio' => formatear_hora($actual['inicio_programado']),
        'hora_fin'    => formatear_hora($actual['fin_programado']),
        'tipos'       => $tipos_actuales,
        'lugar_coordenadas' => formatear_coordenadas($actual['lugar_latitud'], $actual['lugar_longitud']),
        'cantidad'    => $cantidades_actuales,
        'motivo'      => '',
    ];
if ($actual !== null) {
    $valores['inicio_real'] = $a_local($actual['inicio_real']);
    $valores['fin_real'] = $a_local($actual['fin_real']);
}

$errores = [];
$accion = es_post() ? (string) ($_POST['accion'] ?? 'guardar') : null;

if ($accion === 'eliminar' && $actual !== null) {
    verificar_csrf();
    $motivo = trim((string) ($_POST['motivo_eliminar'] ?? ''));
    $error_motivo = validar_motivo($motivo);
    if ($error_motivo !== null) {
        guardar_mensaje('danger', $error_motivo);
        redirigir('admin/operativos/editar', ['id' => $id]);
    }
    if ($controles > 0) {
        guardar_mensaje('warning', 'No se puede eliminar: el operativo tiene controles inopinados registrados.');
        redirigir('admin/operativos/editar', ['id' => $id]);
    }
    consultar('DELETE FROM operativos WHERE id = :id', ['id' => $id]);
    registrar_bitacora('ELIMINAR', 'operativos', $id, [
        'motivo'    => $motivo,
        'por_admin' => true,
        'operativo' => array_diff_key($actual, array_flip(['unidad_nombre', 'unidad_nombre_completo', 'division'])),
        'tipos'     => $tipos_actuales,
        'puntos'    => $puntos,
    ]);
    guardar_mensaje('success', "Se eliminó el operativo de {$actual['unidad_nombre']}.");
    redirigir('admin/operativos');
}

if ($accion === 'guardar') {
    verificar_csrf();
    $tipos_formulario = obtener_tipos_para_formulario($tipos_actuales);
    $ids_tipos = array_map(static fn (array $t): int => (int) $t['id'], $tipos_formulario);
    ['datos' => $datos, 'errores' => $errores] = validar_programacion($_POST, $ids_tipos);

    // Unidad: se elige solo al programar en su nombre.
    $unidad_id = $actual === null ? validar_entero($_POST['unidad_id'] ?? null, 1, PHP_INT_MAX) : (int) $actual['unidad_id'];
    if ($actual === null && !in_array($unidad_id, array_map(static fn (array $u): int => (int) $u['id'], $unidades), true)) {
        $errores['unidad_id'] = 'Elija la unidad.';
    }

    // Horario: ADMIN puede programar con la hora ya pasada (RN-03).
    $horario = null;
    try {
        $horario = calcular_horario_turno((string) ($_POST['fecha_servicio'] ?? ''), (string) ($_POST['hora_inicio'] ?? ''), (string) ($_POST['hora_fin'] ?? ''));
    } catch (InvalidArgumentException $error) {
        $errores['hora_inicio'] = $error->getMessage();
    }

    // Las horas reales que ADMIN no cambió conservan sus segundos (el campo solo tiene minutos).
    $entrada_ejecucion = $_POST;
    foreach (['inicio_real', 'fin_real'] as $campo) {
        $entrada_ejecucion[$campo] = restaurar_segundos((string) ($_POST[$campo] ?? ''), $actual[$campo] ?? null);
    }
    ['datos' => $ejecucion, 'errores' => $errores_ejecucion] = validar_ejecucion($entrada_ejecucion, $puntos > 0, $ahora);
    $errores += $errores_ejecucion;
    $finalizado = ($ejecucion['estado'] ?? null) === 'FINALIZADO';

    $nota = null;
    $cantidades = [];
    if ($finalizado) {
        try {
            $nota = normalizar_nota_informativa((string) ($_POST['nota_informativa'] ?? ''));
        } catch (InvalidArgumentException $error) {
            $errores['nota_informativa'] = $error->getMessage();
        }
        ['datos' => $cantidades, 'errores' => $errores_cantidades] = validar_cantidades_resultado((array) ($_POST['cantidad'] ?? []), $ids_conceptos);
        foreach ($errores_cantidades as $concepto_id => $mensaje) {
            $errores["cantidad[$concepto_id]"] = $mensaje;
        }
    }

    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $error_motivo = validar_motivo($motivo);
    if ($error_motivo !== null) {
        $errores['motivo'] = $error_motivo;
    }

    $valores = array_merge($valores, $_POST);
    $valores['tipos'] = array_map('intval', (array) ($_POST['tipos'] ?? []));
    $valores['cantidad'] = array_map('intval', (array) ($_POST['cantidad'] ?? []));

    if ($errores === []) {
        $tipos = $datos['tipos'];
        unset($datos['tipos']);
        $datos += [
            'fecha_servicio'    => $_POST['fecha_servicio'],
            'turno'             => $horario['turno'],
            'inicio_programado' => $horario['inicio']->format(FORMATO_FECHA_HORA),
            'fin_programado'    => $horario['fin']->format(FORMATO_FECHA_HORA),
            'nota_informativa'  => $nota,
        ] + $ejecucion;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($actual === null) {
                $columnas = array_keys($datos);
                consultar(
                    'INSERT INTO operativos (unidad_id, creado_por, ' . implode(', ', $columnas) . ')
                     VALUES (:unidad_id, :creado_por, :' . implode(', :', $columnas) . ')',
                    $datos + ['unidad_id' => $unidad_id, 'creado_por' => usuario_actual()['id']],
                );
                $id = (int) $pdo->lastInsertId();
            } else {
                $asignaciones = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($datos)));
                consultar("UPDATE operativos SET $asignaciones WHERE id = :id", $datos + ['id' => $id]);
            }

            consultar('DELETE FROM operativo_tipos WHERE operativo_id = :id', ['id' => $id]);
            $insertar = $pdo->prepare('INSERT INTO operativo_tipos (operativo_id, tipo_operativo_id) VALUES (:operativo_id, :tipo_id)');
            foreach ($tipos as $tipo_id) {
                $insertar->execute(['operativo_id' => $id, 'tipo_id' => $tipo_id]);
            }

            // Fuera de FINALIZADO no hay resultados: reabrir los borra (quedan en la bitácora).
            consultar('DELETE FROM operativo_resultados WHERE operativo_id = :id', ['id' => $id]);
            $insertar = $pdo->prepare('INSERT INTO operativo_resultados (operativo_id, concepto_resultado_id, cantidad) VALUES (:operativo_id, :concepto_id, :cantidad)');
            foreach ($cantidades as $concepto_id => $cantidad) {
                $insertar->execute(['operativo_id' => $id, 'concepto_id' => $concepto_id, 'cantidad' => $cantidad]);
            }

            $como_lista = static function (array $mapa): array {
                $lista = [];
                foreach ($mapa as $clave => $valor) {
                    $lista[] = "$clave:$valor";
                }
                return $lista;
            };
            if ($actual === null) {
                registrar_bitacora('PROGRAMAR', 'operativos', $id, ['motivo' => $motivo, 'por_admin' => true, 'unidad_id' => $unidad_id] + $datos + ['tipos' => $tipos]);
            } else {
                $cambios = calcular_cambios(
                    $actual + ['tipos' => $tipos_actuales, 'resultados' => $como_lista(array_filter($cantidades_actuales))],
                    $datos + ['tipos' => $tipos, 'resultados' => $como_lista(array_filter($cantidades))],
                );
                if ($cambios !== []) {
                    registrar_bitacora('CORREGIR', 'operativos', $id, ['motivo' => $motivo, 'cambios' => $cambios]);
                }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        if ($errores === []) {
            $reabierto = $actual !== null && $actual['estado'] === 'FINALIZADO' && $datos['estado'] !== 'FINALIZADO';
            guardar_mensaje('success', ($actual === null ? 'Operativo programado en nombre de la unidad.' : 'Corrección guardada.')
                . ($reabierto ? ' Se reabrió el operativo y se borraron sus resultados.' : ''));
            redirigir('admin/operativos/editar', ['id' => $id]);
        }
    }
}

$tipos_formulario = obtener_tipos_para_formulario(array_map('intval', (array) $valores['tipos']));
$deshabilitado = static fn (string $campo): string => '';
$fecha_minima = null;
$titulo = $actual === null ? 'Programar en nombre de una unidad' : 'Corregir operativo';
$scripts = ['assets/js/programacion.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="formulario-amplio">
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url('admin/operativos')) ?>">Operativos</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($actual === null ? 'Programar' : $actual['unidad_nombre']) ?></li>
    </ol>
</nav>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto"><?= e($titulo) ?></h1>
    <?php if ($actual !== null): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(armar_url('supervisor/operativo', ['id' => $id])) ?>"><i class="bi bi-eye"></i> Ver ficha</a>
    <?php endif; ?>
</div>

<?php if ($actual !== null): ?>
    <!-- Resumen del operativo tal como está guardado, con el borde del color del estado (como la ficha). -->
    <?php $estado_actual = calcular_estado_visible($actual, $ahora); ?>
    <div class="card shadow-sm mb-3 encabezado-operativo tarjeta-<?= e(strtolower($estado_actual)) ?>">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h2 class="h5 mb-0"><?= e($actual['unidad_nombre']) ?></h2>
                <?= mostrar_estado($estado_actual, pendiente_nota($actual)) ?>
            </div>
            <ul class="resumen-operativo list-unstyled small text-body-secondary mb-0 mt-1">
                <li><i class="bi bi-clock"></i> <?= e(obtener_etiqueta_turno($actual['turno'])) ?> ·
                    <?= e(validar_fecha($actual['fecha_servicio'])->format('d/m/Y')) ?> ·
                    <?= e(formatear_hora($actual['inicio_programado'])) ?>–<?= e(formatear_hora($actual['fin_programado'])) ?></li>
                <li><i class="bi bi-person-badge"></i> <?= e($actual['jefe_grado'] . ' ' . $actual['jefe_nombres']) ?> · <?= e($actual['jefe_celular']) ?></li>
                <li><i class="bi bi-people"></i> <?= e($actual['fuerzas_propias']) ?> propios + <?= e($actual['fuerzas_apoyo']) ?> de apoyo ·
                    <i class="bi bi-car-front"></i> <?= e($actual['unidades_moviles']) ?> móvil<?= (int) $actual['unidades_moviles'] === 1 ? '' : 'es' ?></li>
                <li><i class="bi bi-geo-alt"></i> <?= $puntos ?> punto<?= $puntos === 1 ? '' : 's' ?> ·
                    <i class="bi bi-clipboard-check"></i> <?= $controles ?> control<?= $controles === 1 ? '' : 'es' ?></li>
            </ul>
        </div>
    </div>
<?php endif; ?>

<form method="post" novalidate
      x-data="formularioProgramacion(<?= e(json_encode([
          'fecha'      => (string) $valores['fecha_servicio'],
          'horaInicio' => (string) $valores['hora_inicio'],
          'horaFin'    => (string) $valores['hora_fin'],
      ])) ?>)">
    <?= campo_csrf() ?>
    <input type="hidden" name="accion" value="guardar">

    <!-- En la PC, dos columnas: a la izquierda los datos del operativo; a la derecha, fija al bajar,
         la ejecución, el motivo y Guardar. En el celular, una sola columna en ese orden. -->
    <div class="row g-3" x-data="{ estado: <?= e(json_encode((string) $valores['estado'])) ?>, original: <?= e(json_encode($actual['estado'] ?? 'PROGRAMADO')) ?> }">
        <div class="col-lg-7 col-xl-8">
            <?php if ($actual === null): ?>
                <div class="card card-body shadow-sm mb-3">
                    <h2 class="h6">Unidad</h2>
                    <select name="unidad_id" class="form-select<?= clase_error($errores, 'unidad_id') ?>" required aria-label="Unidad">
                        <option value="">Elija la unidad…</option>
                        <?php foreach (DIVISIONES as $division => $etiqueta): ?>
                            <optgroup label="<?= e($etiqueta) ?>">
                                <?php foreach ($unidades as $unidad): ?>
                                    <?php if ($unidad['division'] !== $division) continue; ?>
                                    <option value="<?= e($unidad['id']) ?>"<?= marcar((string) $valores['unidad_id'] === (string) $unidad['id'], 'selected') ?>>
                                        <?= e($unidad['nombre_corto']) ?><?= $unidad['activo'] ? '' : ' (inactiva)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?= mostrar_error($errores, 'unidad_id') ?>
                </div>
            <?php endif; ?>

            <?php require RUTA_APP . '/vistas/formulario_programacion.php'; ?>

            <div class="card card-body shadow-sm mb-3 rejilla-contadores" x-show="estado === 'FINALIZADO'" x-cloak>
                <h2 class="h6">Cierre</h2>
                <div class="mb-3">
                    <label for="nota_informativa" class="form-label">N° de nota informativa <span class="text-body-secondary">(opcional)</span></label>
                    <input type="text" id="nota_informativa" name="nota_informativa" inputmode="numeric" maxlength="40"
                           class="form-control<?= clase_error($errores, 'nota_informativa') ?>" value="<?= e($valores['nota_informativa'] ?? '') ?>">
                    <?= mostrar_error($errores, 'nota_informativa') ?>
                </div>
                <?php foreach ($conceptos as $concepto): ?>
                    <?php $parametros = [
                        'nombre'        => 'cantidad[' . $concepto['id'] . ']',
                        'id'            => 'concepto-' . $concepto['id'],
                        'etiqueta'      => mb_strtoupper(mb_substr($concepto['nombre'], 0, 1)) . mb_strtolower(mb_substr($concepto['nombre'], 1)) . ($concepto['activo'] ? '' : ' (inactivo)'),
                        'valor'         => (int) ($valores['cantidad'][(int) $concepto['id']] ?? 0),
                        'minimo'        => 0,
                        'maximo'        => MAX_CANTIDAD_RESULTADO,
                        'deshabilitado' => false,
                        'errores'       => $errores,
                    ]; ?>
                    <?php require RUTA_APP . '/vistas/campo_contador.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-5 col-xl-4">
            <div class="columna-acciones">
                <div class="card card-body shadow-sm mb-3">
                    <h2 class="h6">Ejecución</h2>
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select id="estado" name="estado" x-model="estado" class="form-select<?= clase_error($errores, 'estado') ?>">
                            <?php foreach (ESTADOS as $opcion): ?>
                                <option value="<?= e($opcion) ?>"<?= marcar($valores['estado'] === $opcion, 'selected') ?>><?= e(obtener_etiqueta_estado($opcion)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= mostrar_error($errores, 'estado') ?>
                        <div class="form-text" x-show="original === 'FINALIZADO' && estado === 'EN_CURSO'" x-cloak>
                            <i class="bi bi-arrow-counterclockwise"></i> <strong>Reabrir:</strong> se borran los resultados y la nota, y la unidad podrá registrar puntos y finalizarlo de nuevo.
                        </div>
                        <div class="form-text" x-show="estado === 'FINALIZADO'" x-cloak>
                            <i class="bi bi-arrow-left d-none d-lg-inline"></i><i class="bi bi-arrow-up d-lg-none"></i> La nota y los resultados van en <strong>Cierre</strong>.
                        </div>
                        <?php if ($puntos > 0): ?>
                            <div class="form-text"><?= $puntos ?> punto(s) registrado(s): no puede volver a PROGRAMADO.</div>
                        <?php endif; ?>
                    </div>
                    <div x-show="estado !== 'PROGRAMADO'" x-cloak>
                        <div class="mb-3">
                            <label for="inicio_real" class="form-label">Inicio real</label>
                            <input type="datetime-local" id="inicio_real" name="inicio_real" step="60" max="<?= e($ahora->format('Y-m-d\TH:i')) ?>"
                                   class="form-control<?= clase_error($errores, 'inicio_real') ?>" value="<?= e($valores['inicio_real']) ?>">
                            <?= mostrar_error($errores, 'inicio_real') ?>
                        </div>
                        <div class="mb-1" x-show="estado === 'FINALIZADO'">
                            <label for="fin_real" class="form-label">Fin real</label>
                            <input type="datetime-local" id="fin_real" name="fin_real" step="60" max="<?= e($ahora->format('Y-m-d\TH:i')) ?>"
                                   class="form-control<?= clase_error($errores, 'fin_real') ?>" value="<?= e($valores['fin_real']) ?>">
                            <?= mostrar_error($errores, 'fin_real') ?>
                        </div>
                    </div>
                </div>

                <div class="card card-body shadow-sm mb-3 border-warning">
                    <label for="motivo" class="form-label fw-semibold"><?= $actual === null ? 'Motivo' : 'Motivo de la corrección' ?></label>
                    <textarea id="motivo" name="motivo" rows="3" maxlength="500" required
                              class="form-control<?= clase_error($errores, 'motivo') ?>"
                              placeholder="<?= e($actual === null
                                  ? 'Por ejemplo: la unidad no tiene conexión y lo informó por teléfono'
                                  : 'Por ejemplo: la unidad informó por oficio que el operativo terminó a las 18:30') ?>"><?= e($valores['motivo']) ?></textarea>
                    <?= mostrar_error($errores, 'motivo') ?>
                    <div class="form-text">
                        <i class="bi bi-shield-exclamation"></i> Todo se puede cambiar, también con la hora ya pasada.
                        El motivo y cada cambio (antes → después) quedan en la bitácora.
                    </div>
                </div>

                <?php if ($errores !== []): ?>
                    <div class="alert alert-danger py-2">Revise los campos marcados.</div>
                <?php endif; ?>
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-success boton-accion"><i class="bi bi-check-lg"></i> Guardar</button>
                    <a href="<?= e(armar_url('admin/operativos')) ?>" class="btn btn-outline-secondary boton-accion">Volver</a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php if ($actual !== null): ?>
    <!-- Eliminar queda plegado y lejos de Guardar: es definitivo. -->
    <div class="row">
        <div class="col-lg-7 col-xl-8">
            <details class="zona-eliminar card shadow-sm border-danger mb-4">
                <summary class="card-body py-2 text-danger fw-semibold"><i class="bi bi-trash"></i> Eliminar operativo</summary>
                <div class="card-body pt-0">
                    <?php if ($controles > 0): ?>
                        <p class="small mb-0">No se puede eliminar: tiene <?= $controles ?> control(es) inopinado(s), que son evidencia de supervisión.</p>
                    <?php else: ?>
                        <form method="post" data-confirmar-titulo="¿Eliminar el operativo?"
                              data-confirmar="Se eliminará definitivamente el operativo de <?= e($actual['unidad_nombre']) ?> con sus puntos y resultados. La copia queda en la bitácora."
                              data-confirmar-boton="Sí, eliminar" data-confirmar-tipo="peligro">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <label for="motivo_eliminar" class="form-label small">Motivo</label>
                            <div class="input-group">
                                <input type="text" id="motivo_eliminar" name="motivo_eliminar" maxlength="500" required class="form-control"
                                       placeholder="Por ejemplo: programado por error en otra unidad">
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
                            </div>
                            <div class="form-text">Se borran también sus <?= $puntos ?> punto(s) y sus resultados. La copia queda en la bitácora.
                                La unidad no se cambia: para pasarlo a otra unidad, elimínelo y prográmelo de nuevo.</div>
                        </form>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </div>
<?php endif; ?>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
