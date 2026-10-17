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
        'nombre_plan' => '', 'lugar_programado' => '', 'lugar_coordenadas' => '', 'jefe_grado' => '', 'jefe_nombres' => '', 'jefe_celular' => '',
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

    ['datos' => $ejecucion, 'errores' => $errores_ejecucion] = validar_ejecucion($_POST, $puntos > 0, $ahora);
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
        } catch (PDOException $error) {
            $pdo->rollBack();
            if (!es_error_duplicado($error)) {
                throw $error;
            }
            $errores['hora_inicio'] = 'La unidad ya tiene un operativo en el turno ' . obtener_etiqueta_turno($datos['turno']) . ' de ese servicio diario (RN-01).';
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
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url('admin/operativos')) ?>">Operativos</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($actual === null ? 'Programar' : $actual['unidad_nombre']) ?></li>
    </ol>
</nav>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0"><?= e($titulo) ?></h1>
    <?php if ($actual !== null): ?>
        <?= mostrar_estado(calcular_estado_visible($actual, $ahora), pendiente_nota($actual)) ?>
        <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(armar_url('supervisor/operativo', ['id' => $id])) ?>"><i class="bi bi-eye"></i> Ver ficha</a>
    <?php endif; ?>
</div>
<div class="alert alert-warning py-2 formulario-angosto small">
    <i class="bi bi-shield-exclamation"></i>
    Corrección de administrador: todos los campos se pueden cambiar, también con la hora ya pasada.
    El motivo y cada cambio (antes → después) quedan en la bitácora.
</div>

<form method="post" class="formulario-angosto" novalidate
      x-data="formularioProgramacion(<?= e(json_encode([
          'fecha'      => (string) $valores['fecha_servicio'],
          'horaInicio' => (string) $valores['hora_inicio'],
          'horaFin'    => (string) $valores['hora_fin'],
      ])) ?>)">
    <?= campo_csrf() ?>
    <input type="hidden" name="accion" value="guardar">

    <div class="card card-body shadow-sm mb-3">
        <h2 class="h6">Unidad</h2>
        <?php if ($actual === null): ?>
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
        <?php else: ?>
            <div class="fw-semibold"><?= e($actual['unidad_nombre']) ?></div>
            <div class="small text-body-secondary">La unidad no se cambia: para otra unidad, elimine y programe de nuevo.</div>
        <?php endif; ?>
    </div>

    <?php require RUTA_APP . '/vistas/formulario_programacion.php'; ?>

    <div x-data="{ estado: <?= e(json_encode((string) $valores['estado'])) ?>, original: <?= e(json_encode($actual['estado'] ?? 'PROGRAMADO')) ?> }">
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
                <?php if ($puntos > 0): ?>
                    <div class="form-text"><?= $puntos ?> punto(s) registrado(s): no puede volver a PROGRAMADO.</div>
                <?php endif; ?>
            </div>
            <div class="row g-2" x-show="estado !== 'PROGRAMADO'" x-cloak>
                <div class="col-sm-6">
                    <label for="inicio_real" class="form-label">Inicio real</label>
                    <input type="datetime-local" id="inicio_real" name="inicio_real" step="60" max="<?= e($ahora->format('Y-m-d\TH:i')) ?>"
                           class="form-control<?= clase_error($errores, 'inicio_real') ?>" value="<?= e($valores['inicio_real']) ?>">
                    <?= mostrar_error($errores, 'inicio_real') ?>
                </div>
                <div class="col-sm-6" x-show="estado === 'FINALIZADO'">
                    <label for="fin_real" class="form-label">Fin real</label>
                    <input type="datetime-local" id="fin_real" name="fin_real" step="60" max="<?= e($ahora->format('Y-m-d\TH:i')) ?>"
                           class="form-control<?= clase_error($errores, 'fin_real') ?>" value="<?= e($valores['fin_real']) ?>">
                    <?= mostrar_error($errores, 'fin_real') ?>
                </div>
            </div>
        </div>

        <div class="card card-body shadow-sm mb-3" x-show="estado === 'FINALIZADO'" x-cloak>
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

    <div class="card card-body shadow-sm mb-3 border-warning">
        <label for="motivo" class="form-label fw-semibold">Motivo de la corrección</label>
        <textarea id="motivo" name="motivo" rows="2" maxlength="500" required
                  class="form-control<?= clase_error($errores, 'motivo') ?>"
                  placeholder="Por ejemplo: la unidad informó por oficio que el operativo terminó a las 18:30"><?= e($valores['motivo']) ?></textarea>
        <?= mostrar_error($errores, 'motivo') ?>
    </div>

    <?php if ($errores !== []): ?>
        <div class="alert alert-danger py-2">Revise los campos marcados.</div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <button type="submit" class="btn btn-success boton-accion px-4"><i class="bi bi-check-lg"></i> Guardar</button>
        <a href="<?= e(armar_url('admin/operativos')) ?>" class="btn btn-outline-secondary boton-accion px-4">Volver</a>
    </div>
</form>

<?php if ($actual !== null): ?>
    <div class="card card-body shadow-sm formulario-angosto border-danger mb-4">
        <h2 class="h6 text-danger">Eliminar operativo</h2>
        <?php if ($controles > 0): ?>
            <p class="small mb-0">No se puede eliminar: tiene <?= $controles ?> control(es) inopinado(s), que son evidencia de supervisión.</p>
        <?php else: ?>
            <form method="post" onsubmit="return confirm('¿Eliminar definitivamente este operativo con sus puntos y resultados?')">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="eliminar">
                <label for="motivo_eliminar" class="form-label small">Motivo</label>
                <div class="input-group">
                    <input type="text" id="motivo_eliminar" name="motivo_eliminar" maxlength="500" required class="form-control"
                           placeholder="Por ejemplo: programado por error en otra unidad">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
                </div>
                <div class="form-text">Se borran también sus <?= $puntos ?> punto(s) y sus resultados. La copia queda en la bitácora.</div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
