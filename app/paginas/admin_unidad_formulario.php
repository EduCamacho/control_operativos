<?php
/**
 * ADMIN: crear o editar una unidad.
 */
declare(strict_types=1);

requiere_rol('ADMIN');

$id = obtener_id_get();
$actual = null;
if ($id !== null) {
    $actual = consultar('SELECT id, division, nombre, nombre_corto, orden, activo FROM unidades WHERE id = :id', ['id' => $id])->fetch();
    if (!$actual) {
        guardar_mensaje('danger', 'La unidad no existe.');
        redirigir('admin/unidades');
    }
}

$errores = [];
$valores = $actual ?? [
    'division'     => 'DIVOPUS',
    'nombre'       => '',
    'nombre_corto' => '',
    'orden'        => (int) consultar('SELECT COALESCE(MAX(orden), 0) + 1 FROM unidades')->fetchColumn(),
    'activo'       => 1,
];

if (es_post()) {
    verificar_csrf();
    ['datos' => $datos, 'errores' => $errores] = validar_unidad($_POST);
    $valores = array_merge($valores, $_POST, ['activo' => $datos['activo']]);

    if ($errores === []) {
        try {
            if ($actual === null) {
                consultar(
                    'INSERT INTO unidades (division, nombre, nombre_corto, orden, activo)
                     VALUES (:division, :nombre, :nombre_corto, :orden, :activo)',
                    $datos,
                );
                $id = (int) db()->lastInsertId();
                registrar_bitacora('CREAR_UNIDAD', 'unidades', $id, $datos);
                guardar_mensaje('success', "Se creó la unidad {$datos['nombre_corto']}.");
            } else {
                $cambios = calcular_cambios($actual, $datos);
                if ($cambios !== []) {
                    consultar(
                        'UPDATE unidades
                         SET division = :division, nombre = :nombre, nombre_corto = :nombre_corto, orden = :orden, activo = :activo
                         WHERE id = :id',
                        $datos + ['id' => $id],
                    );
                    registrar_bitacora('EDITAR_UNIDAD', 'unidades', $id, $cambios);
                }
                guardar_mensaje('success', "Se guardó la unidad {$datos['nombre_corto']}.");
            }
            redirigir('admin/unidades');
        } catch (PDOException $error) {
            if (!es_error_duplicado($error)) {
                throw $error;
            }
            $errores['nombre_corto'] = 'Ya existe otra unidad con ese nombre corto.';
        }
    }
}

$titulo = $actual === null ? 'Nueva unidad' : 'Editar unidad';
require RUTA_APP . '/vistas/cabecera.php';
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url('admin/unidades')) ?>">Unidades</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($titulo) ?></li>
    </ol>
</nav>
<h1 class="h4 mb-3"><?= e($titulo) ?></h1>

<form method="post" class="card card-body shadow-sm formulario-angosto" novalidate>
    <?= campo_csrf() ?>
    <div class="mb-3">
        <label for="division" class="form-label">División</label>
        <select id="division" name="division" class="form-select<?= clase_error($errores, 'division') ?>" required>
            <?php foreach (DIVISIONES as $clave => $etiqueta): ?>
                <option value="<?= e($clave) ?>"<?= marcar($valores['division'] === $clave, 'selected') ?>><?= e($etiqueta) ?></option>
            <?php endforeach; ?>
        </select>
        <?= mostrar_error($errores, 'division') ?>
    </div>
    <div class="mb-3">
        <label for="nombre" class="form-label">Nombre completo</label>
        <input type="text" id="nombre" name="nombre" maxlength="150" required
               class="form-control<?= clase_error($errores, 'nombre') ?>" value="<?= e($valores['nombre']) ?>"
               placeholder="COMISARÍA PNP AYACUCHO">
        <?= mostrar_error($errores, 'nombre') ?>
    </div>
    <div class="mb-3">
        <label for="nombre_corto" class="form-label">Nombre corto</label>
        <input type="text" id="nombre_corto" name="nombre_corto" maxlength="60" required
               class="form-control<?= clase_error($errores, 'nombre_corto') ?>" value="<?= e($valores['nombre_corto']) ?>"
               placeholder="COM. AYACUCHO" aria-describedby="ayuda-nombre-corto">
        <div id="ayuda-nombre-corto" class="form-text">Es el que se muestra en el mapa y en el tablero. No puede repetirse.</div>
        <?= mostrar_error($errores, 'nombre_corto') ?>
    </div>
    <div class="mb-3">
        <label for="orden" class="form-label">Orden</label>
        <input type="number" id="orden" name="orden" min="0" max="65535" step="1" inputmode="numeric" required
               class="form-control<?= clase_error($errores, 'orden') ?>" value="<?= e($valores['orden']) ?>"
               aria-describedby="ayuda-orden">
        <div id="ayuda-orden" class="form-text">Posición en las listas y en el tablero (de menor a mayor).</div>
        <?= mostrar_error($errores, 'orden') ?>
    </div>
    <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1"<?= marcar((bool) $valores['activo']) ?>>
        <label class="form-check-label" for="activo">Activa</label>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-success boton-accion px-4"><i class="bi bi-check-lg"></i> Guardar</button>
        <a href="<?= e(armar_url('admin/unidades')) ?>" class="btn btn-outline-secondary boton-accion px-4">Cancelar</a>
    </div>
</form>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
