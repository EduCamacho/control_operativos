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

// Contacto de soporte (el mismo de la Ayuda): antes de ingresar no hay otra forma de pedir ayuda.
$celular_soporte = trim(SOPORTE_CELULAR);
$whatsapp_soporte = SOPORTE_WHATSAPP ? armar_enlace_whatsapp($celular_soporte) : null;

$titulo = 'Ingresar';
$sin_menu = true;
$pagina_completa = true;
$mensajes_propios = true; // los avisos (por ejemplo, sesión cerrada) van dentro del panel
require RUTA_APP . '/vistas/cabecera.php';
?>
<!-- Ingreso: en la PC, pantalla dividida (marca a la izquierda, formulario a la derecha);
     en el celular, una franja verde con el escudo arriba y el formulario debajo. -->
<div class="ingreso">
    <aside class="ingreso-marca">
        <div class="ingreso-marca-contenido">
            <?php if (obtener_archivo_logo() !== null): ?>
                <img class="ingreso-logo" src="<?= e(armar_url('assets/img/logo.png')) ?>" alt="Escudo de la Policía Nacional del Perú">
            <?php else: ?>
                <i class="bi bi-shield-check ingreso-logo-icono" aria-hidden="true"></i>
            <?php endif; ?>
            <div>
                <div class="ingreso-sistema">Control de Operativos</div>
                <div class="ingreso-institucion">REGPOL La Libertad</div>
            </div>
            <p class="ingreso-descripcion">Programación, seguimiento en campo y control de los operativos policiales.</p>
        </div>
        <div class="ingreso-credito">Creado por la UNITIC - REGPOL LA LIBERTAD</div>
    </aside>

    <section class="ingreso-panel">
        <div class="ingreso-formulario">
            <h1 class="h3 mb-1">Ingresar</h1>
            <p class="text-body-secondary mb-4">Use el usuario y la clave de su cuenta.</p>

            <?php foreach (obtener_mensajes() as $mensaje): ?>
                <div class="alert alert-<?= e($mensaje['tipo']) ?> py-2" role="alert"><?= e($mensaje['texto']) ?></div>
            <?php endforeach; ?>
            <?php if ($error !== null): ?>
                <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(armar_url('login')) ?>">
                <?= campo_csrf() ?>
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="usuario" name="usuario"
                               value="<?= e($nombre_usuario) ?>" autocomplete="username" autocapitalize="none"
                               spellcheck="false" maxlength="50" required <?= $nombre_usuario === '' ? 'autofocus' : '' ?>>
                    </div>
                </div>
                <div class="mb-4" x-data="{ visible: false }">
                    <label for="clave" class="form-label">Clave</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input :type="visible ? 'text' : 'password'" type="password" class="form-control"
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
                <button type="submit" class="btn btn-institucional btn-lg w-100 boton-accion">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar
                </button>
            </form>

            <div class="ingreso-ayuda">
                <i class="bi bi-headset"></i>
                <div>
                    <strong>¿Problemas para ingresar?</strong>
                    <?php if ($celular_soporte === ''): ?>
                        <span class="d-block">Consulte al administrador del sistema.</span>
                    <?php else: ?>
                        <span class="d-block">
                            Comuníquese con <?= SOPORTE_NOMBRE !== '' ? e(SOPORTE_NOMBRE) : 'soporte' ?>:
                            <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $celular_soporte)) ?>"><?= e($celular_soporte) ?></a>
                            <?php if ($whatsapp_soporte !== null): ?>
                                · <a href="<?= e($whatsapp_soporte) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>
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
<?php require RUTA_APP . '/vistas/pie.php'; ?>
