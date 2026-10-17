<?php
/**
 * UNIDAD: programar un operativo o editar uno de su unidad (RN-01 a RN-05).
 * El turno no se elige: se clasifica por la hora de inicio (RN-02).
 * Al editar, solo se pueden cambiar los campos que permite el estado (RN-05).
 */
declare(strict_types=1);

$unidad_id = unidad_de_sesion();
$ahora = obtener_ahora();
$vigente = fecha_servicio_vigente($ahora);

$id = obtener_id_get();
$actual = null;
$tipos_actuales = [];
if ($id !== null) {
    $actual = obtener_operativo_de_unidad($id, $unidad_id);
    if ($actual === null) {
        guardar_mensaje('danger', 'El operativo no existe.');
        redirigir('');
    }
    $tipos_actuales = array_map(static fn (array $t): int => (int) $t['id'], obtener_tipos_de_operativo($id));
}

$editables = $actual === null
    ? array_merge(CAMPOS_HORARIO, CAMPOS_DATOS)
    : campos_editables($actual['estado'], paso_hora_inicio($actual, $ahora));
if ($actual !== null && $editables === []) {
    guardar_mensaje('info', 'Un operativo finalizado ya no se puede modificar.');
    redirigir('operativos/ver', ['id' => $id]);
}
$editable = static fn (string $campo): bool => in_array($campo, $editables, true);

// Valores iniciales: los del operativo, o una sugerencia a partir del turno elegido en la tarjeta.
if ($actual !== null) {
    $valores = $actual + [
        'hora_inicio' => formatear_hora($actual['inicio_programado']),
        'hora_fin'    => formatear_hora($actual['fin_programado']),
        'tipos'       => $tipos_actuales,
    ];
} else {
    $fecha = (string) ($_GET['fecha'] ?? $vigente);
    try {
        $fecha = max($vigente, validar_fecha($fecha)->format('Y-m-d'));
    } catch (InvalidArgumentException) {
        $fecha = $vigente;
    }
    $hora_inicio = '';
    $turno_sugerido = $_GET['turno'] ?? null;
    if (in_array($turno_sugerido, TURNOS, true)) {
        $rango = calcular_rango_turno($fecha, $turno_sugerido);
        // Primera hora en punto del turno que todavía no pasó.
        $sugerida = $rango['desde'];
        while ($sugerida <= $ahora && $sugerida < $rango['hasta']) {
            $sugerida = $sugerida->modify('+1 hour');
        }
        if ($sugerida > $ahora && $sugerida <= $rango['hasta']) {
            $hora_inicio = formatear_hora($sugerida);
        }
    }
    $valores = [
        'fecha_servicio' => $fecha, 'hora_inicio' => $hora_inicio, 'hora_fin' => '', 'tipos' => [],
        'nombre_plan' => '', 'lugar_programado' => '', 'jefe_grado' => '', 'jefe_nombres' => '', 'jefe_celular' => '',
        'fuerzas_propias' => 1, 'fuerzas_apoyo' => 0, 'unidades_moviles' => 0, 'observaciones' => '',
    ];
}

$errores = [];
if (es_post()) {
    verificar_csrf();
    $tipos_formulario = obtener_tipos_para_formulario($tipos_actuales);
    $ids_tipos = array_map(static fn (array $t): int => (int) $t['id'], $tipos_formulario);
    $campos_datos = array_values(array_intersect(CAMPOS_DATOS, $editables));
    ['datos' => $datos, 'errores' => $errores] = validar_programacion($_POST, $ids_tipos, $campos_datos);

    // Fecha y horas: solo si son editables (antes de la hora de inicio).
    $horario = null;
    if ($editable('hora_inicio')) {
        try {
            $horario = calcular_horario_turno(
                (string) ($_POST['fecha_servicio'] ?? ''),
                (string) ($_POST['hora_inicio'] ?? ''),
                (string) ($_POST['hora_fin'] ?? ''),
            );
            if (!programacion_permitida($horario['inicio'], $ahora, false)) {
                $errores['hora_inicio'] = 'La hora de inicio ya pasó. Elija una hora futura.';
            }
        } catch (InvalidArgumentException $error) {
            $errores['hora_inicio'] = $error->getMessage();
        }
    }

    $valores = array_merge($valores, array_intersect_key($_POST, array_flip(array_merge($editables, ['hora_inicio', 'hora_fin']))));
    $valores['tipos'] = $datos['tipos'] ?? array_map('intval', (array) ($_POST['tipos'] ?? []));

    if ($errores === []) {
        $tipos = $datos['tipos'] ?? null;
        unset($datos['tipos']);
        if ($horario !== null) {
            $datos += [
                'fecha_servicio'    => $_POST['fecha_servicio'],
                'turno'             => $horario['turno'],
                'inicio_programado' => $horario['inicio']->format(FORMATO_FECHA_HORA),
                'fin_programado'    => $horario['fin']->format(FORMATO_FECHA_HORA),
            ];
        }

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
                $accion_bitacora = 'PROGRAMAR';
                $detalle = $datos + ['tipos' => $tipos];
            } else {
                // Se vuelve a leer con bloqueo: el estado pudo cambiar desde otro dispositivo.
                $bloqueado = obtener_operativo_de_unidad($id, $unidad_id, true);
                if ($bloqueado === null || $bloqueado['estado'] !== $actual['estado']
                    || ($horario !== null && paso_hora_inicio($bloqueado, $ahora))) {
                    throw new RuntimeException('El operativo cambió mientras lo editaba.');
                }
                $detalle = calcular_cambios($actual + ['tipos' => $tipos_actuales], $datos + ($tipos === null ? [] : ['tipos' => $tipos]));
                if ($datos !== []) {
                    $asignaciones = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($datos)));
                    consultar(
                        "UPDATE operativos SET $asignaciones WHERE id = :id AND unidad_id = :unidad_id",
                        $datos + ['id' => $id, 'unidad_id' => $unidad_id],
                    );
                }
                $accion_bitacora = 'EDITAR';
            }

            if ($tipos !== null) {
                consultar('DELETE FROM operativo_tipos WHERE operativo_id = :id', ['id' => $id]);
                $insertar = $pdo->prepare('INSERT INTO operativo_tipos (operativo_id, tipo_operativo_id) VALUES (:operativo_id, :tipo_id)');
                foreach ($tipos as $tipo_id) {
                    $insertar->execute(['operativo_id' => $id, 'tipo_id' => $tipo_id]);
                }
            }

            if ($accion_bitacora === 'PROGRAMAR' || $detalle !== []) {
                registrar_bitacora($accion_bitacora, 'operativos', $id, $detalle);
            }
            $pdo->commit();

            guardar_mensaje('success', $accion_bitacora === 'PROGRAMAR'
                ? 'Operativo programado: turno ' . obtener_etiqueta_turno($datos['turno']) . '.'
                : 'Cambios guardados.');
            redirigir('operativos/ver', ['id' => $id]);
        } catch (PDOException $error) {
            $pdo->rollBack();
            if (!es_error_duplicado($error)) {
                throw $error;
            }
            $errores['hora_inicio'] = 'La unidad ya tiene un operativo en el turno '
                . obtener_etiqueta_turno($datos['turno']) . ' de ese servicio diario (RN-01).';
        } catch (RuntimeException $error) {
            $pdo->rollBack();
            guardar_mensaje('warning', 'El operativo cambió mientras lo editaba (por ejemplo, se inició desde otro dispositivo). Revise los datos.');
            redirigir('operativos/ver', ['id' => $id]);
        }
    }
}

$tipos_formulario = obtener_tipos_para_formulario(array_map('intval', (array) $valores['tipos']));
$titulo = $actual === null ? 'Programar operativo' : 'Editar operativo';
$deshabilitado = static fn (string $campo): string => $editable($campo) ? '' : ' disabled';
require RUTA_APP . '/vistas/cabecera.php';
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url()) ?>">Mis operativos</a></li>
        <?php if ($actual !== null): ?>
            <li class="breadcrumb-item"><a href="<?= e(armar_url('operativos/ver', ['id' => $id])) ?>">Operativo</a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?= e($actual === null ? 'Programar' : 'Editar') ?></li>
    </ol>
</nav>
<h1 class="h4 mb-3"><?= e($titulo) ?></h1>

<?php if ($actual !== null && !$editable('hora_inicio')): ?>
    <div class="alert alert-info py-2 formulario-angosto">
        <i class="bi bi-lock"></i>
        <?= $actual['estado'] === 'EN_CURSO'
            ? 'El operativo está en curso: solo se pueden cambiar el jefe, las fuerzas, las unidades móviles y las observaciones.'
            : 'Ya pasó la hora de inicio: la fecha y las horas no se pueden cambiar.' ?>
    </div>
<?php endif; ?>

<form method="post" class="formulario-angosto" novalidate
      x-data="formularioProgramacion(<?= e(json_encode([
          'fecha'      => (string) $valores['fecha_servicio'],
          'horaInicio' => (string) $valores['hora_inicio'],
          'horaFin'    => (string) $valores['hora_fin'],
      ])) ?>)">
    <?= campo_csrf() ?>

    <?php $fecha_minima = $vigente; ?>
    <?php require RUTA_APP . '/vistas/formulario_programacion.php'; ?>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <button type="submit" class="btn btn-success boton-accion px-4">
            <i class="bi bi-check-lg"></i> <?= $actual === null ? 'Programar' : 'Guardar cambios' ?>
        </button>
        <a href="<?= e($actual === null ? armar_url() : armar_url('operativos/ver', ['id' => $id])) ?>" class="btn btn-outline-secondary boton-accion px-4">Cancelar</a>
    </div>
</form>

<?php require RUTA_APP . '/vistas/pie.php'; ?>
