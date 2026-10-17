<?php
/**
 * UNIDAD: eliminar un operativo PROGRAMADO antes de su hora de inicio (RN-06).
 * Después de esa hora queda visible como NO_INICIADO: no se pueden ocultar incumplimientos.
 */
declare(strict_types=1);

$unidad_id = unidad_de_sesion();
if (!es_post()) {
    redirigir('');
}
verificar_csrf();

$id = obtener_id_post();
$pdo = db();
$pdo->beginTransaction();
$operativo = $id === null ? null : obtener_operativo_de_unidad($id, $unidad_id, true);
if ($operativo === null) {
    $pdo->rollBack();
    guardar_mensaje('danger', 'El operativo no existe.');
    redirigir('');
}
if (!puede_eliminar($operativo, obtener_ahora(), false)) {
    $pdo->rollBack();
    guardar_mensaje('warning', 'Ya no se puede eliminar: solo se elimina un operativo programado antes de su hora de inicio.');
    redirigir('operativos/ver', ['id' => $id]);
}
// Un control inopinado es evidencia de supervisión: el operativo controlado se conserva.
if (contar_controles($id) > 0) {
    $pdo->rollBack();
    guardar_mensaje('warning', 'No se puede eliminar: el operativo ya tiene un control inopinado registrado.');
    redirigir('operativos/ver', ['id' => $id]);
}

$tipos = array_column(obtener_tipos_de_operativo($id), 'nombre');
consultar("DELETE FROM operativos WHERE id = :id AND unidad_id = :unidad_id AND estado = 'PROGRAMADO'", ['id' => $id, 'unidad_id' => $unidad_id]);
// Se guarda la copia completa, porque la fila deja de existir.
registrar_bitacora('ELIMINAR', 'operativos', $id, array_diff_key($operativo, array_flip(['unidad_nombre'])) + ['tipos' => $tipos]);
$pdo->commit();

guardar_mensaje('success', 'Operativo del turno ' . obtener_etiqueta_turno($operativo['turno']) . ' eliminado.');
redirigir('');
