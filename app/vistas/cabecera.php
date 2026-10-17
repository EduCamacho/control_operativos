<?php
/**
 * Inicio del layout. Variables opcionales de la página:
 *   $titulo    Título de la pestaña.
 *   $sin_menu  true para ocultar el menú (ingreso).
 *   $scripts   Rutas de JS propios de la página (se cargan antes de Alpine).
 *   $pagina_completa  true para ocupar todo el espacio sin márgenes (mapa).
 *
 * Menú en barra lateral izquierda: fija desde pantallas grandes (lg) y,
 * en el celular, panel deslizable que se abre con el botón ☰ de la barra superior.
 */
declare(strict_types=1);

$titulo ??= 'Control de Operativos';
$sin_menu ??= false;
$usuario = $sin_menu ? null : usuario_actual();
$ruta_actual = obtener_ruta_actual();
$layout_con_menu = $usuario !== null; // Lo usa pie.php para cerrar el contenedor.

// Menú por rol: ruta => [etiqueta, icono]. Cada fase agrega sus opciones.
$menu = match ($usuario['rol'] ?? null) {
    'ADMIN'      => [
        ''                => ['Inicio', 'house'],
        'admin/unidades'  => ['Unidades', 'building'],
        'admin/usuarios'  => ['Usuarios', 'people'],
        'admin/operativos' => ['Operativos', 'clipboard-data'],
        'admin/catalogos' => ['Catálogos', 'list-check'],
        'admin/bitacora'  => ['Bitácora', 'journal-text'],
        'supervisor/tablero' => ['Tablero', 'grid-3x3'],
    ],
    'SUPERVISOR' => [
        ''                   => ['Mapa', 'map'],
        'supervisor/tablero' => ['Tablero', 'grid-3x3'],
    ],
    'UNIDAD'     => [
        ''                     => ['Mis operativos', 'clipboard-check'],
        'operativos/programar' => ['Programar', 'calendar-plus'],
    ],
    default      => [],
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f6f50">
    <title><?= e($titulo) ?> · Control de Operativos</title>
    <link rel="stylesheet" href="<?= e(armar_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(armar_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(armar_url('assets/css/app.css')) ?>">
    <?php if ($usuario !== null): ?>
        <meta name="csrf" content="<?= e(obtener_token_csrf()) ?>">
    <?php endif; ?>
    <?php foreach ($scripts ?? [] as $script): // Antes de Alpine, para que registren sus componentes. ?>
        <script defer src="<?= e(armar_url($script)) ?>"></script>
    <?php endforeach; ?>
    <script defer src="<?= e(armar_url('assets/vendor/alpinejs/alpine.min.js')) ?>"></script>
</head>
<body>
<?php if ($usuario !== null): ?>
<div class="d-lg-flex">
    <aside class="offcanvas-lg offcanvas-start barra-lateral" tabindex="-1" id="menu-lateral" aria-labelledby="titulo-menu-lateral">
        <div class="offcanvas-header barra-lateral-marca">
            <a class="text-white text-decoration-none fw-semibold fs-5" id="titulo-menu-lateral" href="<?= e(armar_url()) ?>">
                <i class="bi bi-shield-check"></i> Operativos
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                    data-bs-target="#menu-lateral" aria-label="Cerrar menú"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <nav class="nav nav-pills flex-column gap-1 p-2" aria-label="Menú principal">
                <?php foreach ($menu as $ruta => [$etiqueta, $icono]): ?>
                    <?php $activa = $ruta === $ruta_actual || ($ruta !== '' && str_starts_with($ruta_actual, $ruta . '/')); ?>
                    <a class="nav-link<?= $activa ? ' active' : '' ?>"<?= $activa ? ' aria-current="page"' : '' ?>
                       href="<?= e(armar_url($ruta)) ?>">
                        <i class="bi bi-<?= e($icono) ?>" aria-hidden="true"></i> <?= e($etiqueta) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto p-3 border-top border-light border-opacity-25">
                <div class="small mb-2 text-truncate" title="<?= e($usuario['nombre']) ?>">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <?= e($usuario['unidad_nombre'] ?? $usuario['nombre']) ?>
                    <span class="d-block opacity-75"><?= e($usuario['usuario']) ?> · <?= e($usuario['rol']) ?></span>
                </div>
                <form method="post" action="<?= e(armar_url('salir')) ?>">
                    <?= campo_csrf() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm w-100">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Salir
                    </button>
                </form>
                <div class="small opacity-50 mt-2 text-center">versión <?= e(obtener_version() ?? 'de desarrollo') ?></div>
            </div>
        </div>
    </aside>

    <div class="flex-grow-1 contenido-principal">
        <header class="navbar barra-principal d-lg-none sticky-top">
            <div class="container-fluid">
                <button class="btn text-white px-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-lateral"
                        aria-controls="menu-lateral" aria-label="Abrir menú">
                    <i class="bi bi-list fs-3" aria-hidden="true"></i>
                </button>
                <span class="navbar-brand text-white fw-semibold me-auto ms-1"><?= e($titulo) ?></span>
            </div>
        </header>
<?php endif; ?>
<main class="<?= empty($pagina_completa) ? 'container-fluid contenedor-pagina py-3' : 'pagina-completa' ?>">
<?php foreach (obtener_mensajes() as $mensaje): ?>
    <div class="alert alert-<?= e($mensaje['tipo']) ?> alert-dismissible fade show" role="alert">
        <?= e($mensaje['texto']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endforeach; ?>
