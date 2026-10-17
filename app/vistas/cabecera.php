<?php
/**
 * Inicio del layout. Variables opcionales de la página:
 *   $titulo    Título de la pestaña.
 *   $sin_menu  true para ocultar el menú (ingreso).
 */
declare(strict_types=1);

$titulo ??= 'Control de Operativos';
$sin_menu ??= false;
$usuario = $sin_menu ? null : usuario_actual();
$ruta_actual = obtener_ruta_actual();

// Menú por rol. Cada fase agrega sus opciones.
$menu = match ($usuario['rol'] ?? null) {
    'ADMIN'      => ['' => 'Inicio'],
    'SUPERVISOR' => ['' => 'Inicio'],
    'UNIDAD'     => ['' => 'Mis operativos'],
    default      => [],
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b3d2e">
    <title><?= e($titulo) ?> · Control de Operativos</title>
    <link rel="stylesheet" href="<?= e(armar_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(armar_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(armar_url('assets/css/app.css')) ?>">
    <script defer src="<?= e(armar_url('assets/vendor/alpinejs/alpine.min.js')) ?>"></script>
</head>
<body>
<?php if ($usuario !== null): ?>
<nav class="navbar navbar-expand-md navbar-dark barra-principal">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= e(armar_url()) ?>">
            <i class="bi bi-shield-check"></i> Operativos
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                aria-controls="menu-principal" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu-principal">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menu as $ruta => $etiqueta): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $ruta === $ruta_actual ? ' active' : '' ?>"
                           <?= $ruta === $ruta_actual ? 'aria-current="page"' : '' ?>
                           href="<?= e(armar_url($ruta)) ?>"><?= e($etiqueta) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 py-2 py-md-0">
                <span class="navbar-text small text-truncate">
                    <i class="bi bi-person-circle"></i>
                    <?= e($usuario['unidad_nombre'] ?? $usuario['nombre']) ?>
                </span>
                <form method="post" action="<?= e(armar_url('salir')) ?>" class="ms-auto">
                    <?= campo_csrf() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container py-3">
<?php foreach (obtener_mensajes() as $mensaje): ?>
    <div class="alert alert-<?= e($mensaje['tipo']) ?> alert-dismissible fade show" role="alert">
        <?= e($mensaje['texto']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endforeach; ?>
