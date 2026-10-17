<?php
declare(strict_types=1);

if (usuario_actual() !== null) {
    redirigir('');
}

$error = null;
$nombre_usuario = '';

if (es_post()) {
    verificar_csrf();
    $nombre_usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    if ($nombre_usuario === '' || $clave === '') {
        $error = 'Ingrese su usuario y su clave.';
    } else {
        $error = intentar_ingreso($nombre_usuario, $clave);
        if ($error === null) {
            redirigir('');
        }
    }
}

$titulo = 'Ingresar';
$sin_menu = true;
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-9 col-md-6 col-lg-4">
        <div class="text-center my-4">
            <i class="bi bi-shield-check display-4 text-success"></i>
            <h1 class="h4 mt-2 mb-0">Control de Operativos</h1>
            <p class="text-body-secondary">REGPOL La Libertad</p>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(armar_url('login')) ?>" class="card card-body shadow-sm">
            <?= campo_csrf() ?>
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <input type="text" class="form-control form-control-lg" id="usuario" name="usuario"
                       value="<?= e($nombre_usuario) ?>" autocomplete="username" autocapitalize="none"
                       spellcheck="false" maxlength="50" required <?= $nombre_usuario === '' ? 'autofocus' : '' ?>>
            </div>
            <div class="mb-3" x-data="{ visible: false }">
                <label for="clave" class="form-label">Clave</label>
                <div class="input-group">
                    <input :type="visible ? 'text' : 'password'" type="password" class="form-control form-control-lg"
                           id="clave" name="clave" autocomplete="current-password" required
                           <?= $nombre_usuario !== '' ? 'autofocus' : '' ?>>
                    <button type="button" class="btn btn-outline-secondary" @click="visible = !visible"
                            :aria-label="visible ? 'Ocultar clave' : 'Mostrar clave'" aria-label="Mostrar clave">
                        <i class="bi" :class="visible ? 'bi-eye-slash' : 'bi-eye'"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-success btn-lg w-100 boton-accion">Ingresar</button>
        </form>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
