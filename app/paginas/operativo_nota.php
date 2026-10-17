<?php
/**
 * UNIDAD: agregar la nota informativa a un operativo FINALIZADO que no la tiene (RN-13).
 * Una vez guardada, solo ADMIN puede cambiarla.
 */
declare(strict_types=1);

$unidad_id = unidad_de_sesion();
if (!es_post()) {
    redirigir('');
}
verificar_csrf();

$id = obtener_id_post();
$operativo = $id === null ? null : obtener_operativo_de_unidad($id, $unidad_id);
if ($operativo === null) {
    guardar_mensaje('danger', 'El operativo no existe.');
    redirigir('');
}
if (!puede_agregar_nota($operativo)) {
    guardar_mensaje('warning', 'Este operativo ya tiene nota informativa o todavía no está finalizado.');
    redirigir('operativos/ver', ['id' => $id]);
}

try {
    $nota = normalizar_nota_informativa((string) ($_POST['nota_informativa'] ?? ''));
} catch (InvalidArgumentException $error) {
    guardar_mensaje('danger', $error->getMessage());
    redirigir('operativos/ver', ['id' => $id]);
}
if ($nota === null) {
    guardar_mensaje('danger', 'Escriba el número de la nota informativa.');
    redirigir('operativos/ver', ['id' => $id]);
}

$sentencia = consultar(
    "UPDATE operativos SET nota_informativa = :nota
     WHERE id = :id AND unidad_id = :unidad_id AND estado = 'FINALIZADO' AND (nota_informativa IS NULL OR nota_informativa = '')",
    ['nota' => $nota, 'id' => $id, 'unidad_id' => $unidad_id],
);
if ($sentencia->rowCount() === 1) {
    registrar_bitacora('AGREGAR_NOTA', 'operativos', $id, ['nota_informativa' => $nota]);
    guardar_mensaje('success', 'Se guardó la ' . formatear_nota_informativa($nota) . '.');
}
redirigir('operativos/ver', ['id' => $id]);
