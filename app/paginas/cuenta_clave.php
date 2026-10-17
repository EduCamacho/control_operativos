<?php
/**
 * Cambiar la propia clave (todas las cuentas). La cuenta UNIDAD es compartida
 * (RN-17): por eso la sesión actual se mantiene y las de los demás dispositivos
 * solo se cierran si el usuario lo elige (version_sesion, RN-19).
 */
declare(strict_types=1);

$usuario = requiere_login();
$errores = [];
$cerrar_otras = false;

if (es_post()) {
    verificar_csrf();
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $confirmacion = (string) ($_POST['confirmacion'] ?? '');
    $cerrar_otras = !empty($_POST['cerrar_otras']);

    $hash = (string) consultar('SELECT password_hash FROM usuarios WHERE id = :id', ['id' => $usuario['id']])->fetchColumn();
    if ($actual === '' || !password_verify($actual, $hash)) {
        $errores['actual'] = 'La clave actual no es correcta.';
        registrar_bitacora('CAMBIAR_CLAVE_FALLIDO', 'usuarios', $usuario['id']);
    } else {
        $errores = validar_cambio_clave($actual, $nueva, $confirmacion);
    }

    if ($errores === []) {
        consultar(
            'UPDATE usuarios SET password_hash = :hash' . ($cerrar_otras ? ', version_sesion = version_sesion + 1' : '') . ' WHERE id = :id',
            ['hash' => password_hash($nueva, PASSWORD_DEFAULT), 'id' => $usuario['id']],
        );
        session_regenerate_id(true);
        if ($cerrar_otras) {
            // La sesión actual adopta la nueva versión; las demás quedan cerradas.
            $_SESSION['usuario']['version_sesion'] = (int) consultar(
                'SELECT version_sesion FROM usuarios WHERE id = :id',
                ['id' => $usuario['id']],
            )->fetchColumn();
        }
        registrar_bitacora('CAMBIAR_CLAVE', 'usuarios', $usuario['id'], ['cerro_otras_sesiones' => $cerrar_otras]);
        guardar_mensaje('success', $cerrar_otras
            ? 'Clave cambiada. Se cerró la sesión en los demás dispositivos.'
            : 'Clave cambiada. Los demás dispositivos siguen conectados; use la clave nueva al volver a ingresar.');
        redirigir('');
    }
}

$campo_clave = static function (string $nombre, string $etiqueta, string $autocompletar, array $errores, ?string $ayuda = null): void {
    ?>
    <div class="mb-3" x-data="{ visible: false }">
        <label for="<?= e($nombre) ?>" class="form-label"><?= e($etiqueta) ?></label>
        <div class="input-group input-group-lg has-validation">
            <input :type="visible ? 'text' : 'password'" type="password" id="<?= e($nombre) ?>" name="<?= e($nombre) ?>"
                   autocomplete="<?= e($autocompletar) ?>" required maxlength="72"
                   class="form-control<?= clase_error($errores, $nombre) ?>"
                   <?= $ayuda !== null ? 'aria-describedby="' . e($nombre) . '-ayuda"' : '' ?>>
            <button type="button" class="btn btn-outline-secondary" @click="visible = !visible"
                    :aria-label="visible ? 'Ocultar clave' : 'Mostrar clave'" aria-label="Mostrar clave">
                <i class="bi" :class="visible ? 'bi-eye-slash' : 'bi-eye'"></i>
            </button>
            <?= mostrar_error($errores, $nombre) ?>
        </div>
        <?php if ($ayuda !== null): ?><div id="<?= e($nombre) ?>-ayuda" class="form-text"><?= e($ayuda) ?></div><?php endif; ?>
    </div>
    <?php
};

$titulo = 'Cambiar clave';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="formulario-angosto mx-auto">
    <h1 class="h4 mb-1">Cambiar clave</h1>
    <p class="text-body-secondary mb-3">
        Cuenta <strong class="text-body"><?= e($usuario['usuario']) ?></strong>
        <?php if ($usuario['unidad_nombre'] !== null): ?>· <?= e($usuario['unidad_nombre']) ?><?php endif; ?>
    </p>

    <form method="post" novalidate>
        <?= campo_csrf() ?>
        <div class="card card-body shadow-sm mb-3">
            <h2 class="h6 titulo-tarjeta"><i class="bi bi-shield-lock"></i> Clave actual</h2>
            <?php $campo_clave('actual', 'Clave actual', 'current-password', $errores); ?>
        </div>

        <div class="card card-body shadow-sm mb-3">
            <h2 class="h6 titulo-tarjeta"><i class="bi bi-key"></i> Clave nueva</h2>
            <?php $campo_clave('nueva', 'Clave nueva', 'new-password', $errores, 'Al menos ' . LARGO_MINIMO_CLAVE . ' caracteres. Use una frase fácil de recordar y difícil de adivinar.'); ?>
            <?php $campo_clave('confirmacion', 'Repita la clave nueva', 'new-password', $errores); ?>
        </div>

        <?php if ($usuario['rol'] === 'UNIDAD'): ?>
            <div class="aviso-estado small mb-3">
                <i class="bi bi-people"></i>
                Esta cuenta es compartida por la unidad. Avise la clave nueva a quienes la usan (por ejemplo, al jefe del operativo en campo).
            </div>
        <?php endif; ?>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="cerrar_otras" name="cerrar_otras" value="1"<?= marcar($cerrar_otras) ?>>
            <label class="form-check-label" for="cerrar_otras">
                Cerrar la sesión en los demás dispositivos
                <span class="d-block small text-body-secondary">Márquelo si cree que alguien no autorizado conoce la clave. Esta sesión sigue abierta.</span>
            </label>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="submit" class="btn btn-institucional boton-accion px-4"><i class="bi bi-check-lg"></i> Cambiar clave</button>
            <a href="<?= e(armar_url()) ?>" class="btn btn-outline-secondary boton-accion px-4">Cancelar</a>
        </div>
    </form>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
