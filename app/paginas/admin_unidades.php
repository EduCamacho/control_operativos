<?php
/**
 * ADMIN: lista de unidades por división, con activar y desactivar (docs/04).
 */
declare(strict_types=1);

requiere_rol('ADMIN');

if (es_post()) {
    verificar_csrf();
    $id = obtener_id_post();
    $unidad = $id === null ? false : consultar('SELECT id, nombre_corto, activo FROM unidades WHERE id = :id', ['id' => $id])->fetch();
    if (!$unidad) {
        guardar_mensaje('danger', 'La unidad no existe.');
        redirigir('admin/unidades');
    }
    $activo = $unidad['activo'] ? 0 : 1;
    consultar('UPDATE unidades SET activo = :activo WHERE id = :id', ['activo' => $activo, 'id' => $id]);
    registrar_bitacora($activo ? 'ACTIVAR_UNIDAD' : 'DESACTIVAR_UNIDAD', 'unidades', $id, ['nombre_corto' => $unidad['nombre_corto']]);
    guardar_mensaje('success', $unidad['nombre_corto'] . ($activo ? ' quedó activa.' : ' quedó inactiva.'));
    redirigir('admin/unidades');
}

$unidades = consultar(
    'SELECT un.id, un.division, un.nombre, un.nombre_corto, un.orden, un.activo, us.usuario
     FROM unidades un
     LEFT JOIN usuarios us ON us.unidad_id = un.id
     ORDER BY un.orden, un.nombre_corto',
)->fetchAll();

$por_division = array_fill_keys(array_keys(DIVISIONES), []);
foreach ($unidades as $unidad) {
    $por_division[$unidad['division']][] = $unidad;
}

$titulo = 'Unidades';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Unidades <span class="badge text-bg-light"><?= count($unidades) ?></span></h1>
    <a href="<?= e(armar_url('admin/unidades/editar')) ?>" class="btn btn-success boton-accion">
        <i class="bi bi-plus-lg"></i> Nueva unidad
    </a>
</div>

<?php foreach ($por_division as $division => $lista): ?>
    <h2 class="h6 text-uppercase text-body-secondary mt-4"><?= e(DIVISIONES[$division]) ?> · <?= count($lista) ?></h2>
    <?php if ($lista === []): ?>
        <p class="text-body-secondary small">Sin unidades.</p>
    <?php else: ?>
    <div class="list-group">
        <?php foreach ($lista as $unidad): ?>
            <div class="list-group-item d-flex flex-wrap align-items-center gap-2<?= $unidad['activo'] ? '' : ' list-group-item-light' ?>">
                <span class="badge text-bg-light font-monospace" title="Orden"><?= e($unidad['orden']) ?></span>
                <div class="me-auto">
                    <div class="fw-semibold">
                        <?= e($unidad['nombre_corto']) ?>
                        <?php if (!$unidad['activo']): ?><span class="badge text-bg-secondary">Inactiva</span><?php endif; ?>
                    </div>
                    <div class="small text-body-secondary">
                        <?= e($unidad['nombre']) ?> ·
                        <?php if ($unidad['usuario'] !== null): ?>
                            <i class="bi bi-person"></i> <?= e($unidad['usuario']) ?>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-person-x"></i> Sin cuenta</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?= e(armar_url('admin/unidades/editar', ['id' => $unidad['id']])) ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <form method="post" action="<?= e(armar_url('admin/unidades')) ?>">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="id" value="<?= e($unidad['id']) ?>">
                    <button type="submit" class="btn btn-outline-<?= $unidad['activo'] ? 'secondary' : 'success' ?> btn-sm">
                        <?= $unidad['activo'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
