<?php
/**
 * ADMIN: lista de cuentas con su rol, unidad, estado y último acceso (docs/04).
 */
declare(strict_types=1);

requiere_rol('ADMIN');

$ahora = obtener_ahora();
$rol = in_array($_GET['rol'] ?? '', ROLES, true) ? $_GET['rol'] : '';

$usuarios = consultar(
    "SELECT u.id, u.usuario, u.nombre, u.rol, u.activo, u.bloqueado_hasta, u.ultimo_acceso,
            un.nombre_corto AS unidad
     FROM usuarios u
     LEFT JOIN unidades un ON un.id = u.unidad_id
     WHERE :rol = '' OR u.rol = :rol_filtro
     ORDER BY FIELD(u.rol, 'ADMIN', 'SUPERVISOR', 'UNIDAD'), un.orden, u.usuario",
    ['rol' => $rol, 'rol_filtro' => $rol],
)->fetchAll();

$sin_cuenta = (int) consultar(
    'SELECT COUNT(*) FROM unidades un LEFT JOIN usuarios u ON u.unidad_id = un.id WHERE un.activo = 1 AND u.id IS NULL',
)->fetchColumn();

$titulo = 'Usuarios';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Usuarios <span class="badge text-bg-light"><?= count($usuarios) ?></span></h1>
    <a href="<?= e(armar_url('admin/usuarios/editar')) ?>" class="btn btn-success boton-accion">
        <i class="bi bi-person-plus"></i> Nuevo usuario
    </a>
</div>

<?php if ($sin_cuenta > 0): ?>
    <div class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle"></i>
        <?= $sin_cuenta === 1 ? 'Hay 1 unidad activa sin cuenta.' : "Hay $sin_cuenta unidades activas sin cuenta." ?>
    </div>
<?php endif; ?>

<div class="btn-group btn-group-sm flex-wrap mb-3" role="group" aria-label="Filtrar por rol">
    <a href="<?= e(armar_url('admin/usuarios')) ?>" class="btn btn-outline-secondary<?= $rol === '' ? ' active' : '' ?>">Todos</a>
    <?php foreach (ROLES as $opcion): ?>
        <a href="<?= e(armar_url('admin/usuarios', ['rol' => $opcion])) ?>"
           class="btn btn-outline-secondary<?= $rol === $opcion ? ' active' : '' ?>"><?= e($opcion) ?></a>
    <?php endforeach; ?>
</div>

<div class="list-group">
    <?php foreach ($usuarios as $fila): ?>
        <?php $bloqueado = cuenta_bloqueada($fila['bloqueado_hasta'], $ahora); ?>
        <a href="<?= e(armar_url('admin/usuarios/editar', ['id' => $fila['id']])) ?>"
           class="list-group-item list-group-item-action d-flex flex-wrap align-items-center gap-2<?= $fila['activo'] ? '' : ' list-group-item-light' ?>">
            <div class="me-auto">
                <div class="fw-semibold">
                    <?= e($fila['usuario']) ?>
                    <span class="badge text-bg-<?= $fila['rol'] === 'ADMIN' ? 'dark' : ($fila['rol'] === 'SUPERVISOR' ? 'primary' : 'light') ?>"><?= e($fila['rol']) ?></span>
                    <?php if (!$fila['activo']): ?><span class="badge text-bg-secondary">Inactivo</span><?php endif; ?>
                    <?php if ($bloqueado): ?>
                        <span class="badge text-bg-danger"><i class="bi bi-lock"></i> Bloqueado hasta <?= e(convertir_a_fecha_hora($fila['bloqueado_hasta'])->format('H:i')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="small text-body-secondary">
                    <?= e($fila['unidad'] ?? $fila['nombre']) ?> · Último acceso: <?= e(formatear_hace($fila['ultimo_acceso'], $ahora)) ?>
                </div>
            </div>
            <i class="bi bi-chevron-right text-body-secondary"></i>
        </a>
    <?php endforeach; ?>
    <?php if ($usuarios === []): ?>
        <div class="list-group-item text-body-secondary">No hay usuarios con ese rol.</div>
    <?php endif; ?>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
