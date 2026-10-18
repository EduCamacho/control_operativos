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
    // Defensas del ingreso: límite por IP, campo trampa y Turnstile (RN-19a).
    $error = procesar_ingreso(
        $nombre_usuario,
        (string) ($_POST['clave'] ?? ''),
        trim((string) ($_POST['sitio_web'] ?? '')),
        (string) ($_POST['cf-turnstile-response'] ?? ''),
    );
    if ($error === null) {
        redirigir('');
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
            <!-- Trampa para bots (honeypot): invisible para las personas, que no la llenan (RN-19a). -->
            <div class="campo-trampa" aria-hidden="true">
                <label for="sitio_web">Sitio web</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off" value="">
            </div>
            <?php if (TURNSTILE_SITE_KEY !== ''): ?>
                <!-- Cloudflare Turnstile: casi siempre invisible; solo pide un clic si duda. -->
                <div class="cf-turnstile mb-3" data-sitekey="<?= e(TURNSTILE_SITE_KEY) ?>" data-language="es"
                     data-appearance="interaction-only" data-action="login"
                     data-error-callback="alFallarVerificacion" data-unsupported-callback="alFallarVerificacion"></div>
                <!-- Si la verificación no carga, se avisa: si no, el ingreso falla sin explicación (RN-19a). -->
                <div id="aviso-verificacion" class="alert alert-warning small py-2 mb-3" role="alert" hidden>
                    <i class="bi bi-shield-exclamation"></i>
                    No se pudo cargar la verificación de seguridad. Recargue la página o pruebe con otra red
                    (datos móviles o wifi).<span id="codigo-verificacion" class="d-block text-body-secondary mt-1"></span>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-success btn-lg w-100 boton-accion">Ingresar</button>
        </form>
        <?php if (TURNSTILE_SITE_KEY !== ''): ?>
            <script>
                // Turnstile llama a esta función si falla (dominio no autorizado, navegador antiguo, sin red);
                // también se usa si su script no llega a cargar (bloqueador o sin conexión).
                function alFallarVerificacion(codigo) {
                    document.getElementById('aviso-verificacion').hidden = false;
                    if (codigo) {
                        document.getElementById('codigo-verificacion').textContent = 'Código para soporte: ' + codigo;
                    }
                    return true; // Turnstile no lanza el error en la consola: ya se avisó.
                }
            </script>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer
                    onerror="alFallarVerificacion('sin-conexion')"></script>
        <?php endif; ?>
        <p class="small text-body-secondary text-center mt-4">Creado por la UNITIC - REGPOL LA LIBERTAD</p>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
