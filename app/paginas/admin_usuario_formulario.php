<?php
/**
 * ADMIN: crear o editar una cuenta, resetear su clave y desbloquearla (RN-19, RN-20).
 * Al resetear la clave o desactivar la cuenta se cierran sus sesiones (version_sesion).
 */
declare(strict_types=1);

$admin = requiere_rol('ADMIN');
$ahora = obtener_ahora();

$id = obtener_id_get();
$actual = null;
if ($id !== null) {
    $actual = consultar(
        'SELECT id, usuario, nombre, rol, unidad_id, activo, intentos_fallidos, bloqueado_hasta, ultimo_acceso
         FROM usuarios WHERE id = :id',
        ['id' => $id],
    )->fetch();
    if (!$actual) {
        guardar_mensaje('danger', 'El usuario no existe.');
        redirigir('admin/usuarios');
    }
}
$es_propia = $actual !== null && (int) $actual['id'] === $admin['id'];

/** Otro ADMIN activo además del indicado: evita quedarse sin administradores. */
$hay_otro_admin = static fn (int $excepto): bool => (bool) consultar(
    "SELECT 1 FROM usuarios WHERE rol = 'ADMIN' AND activo = 1 AND id <> :id LIMIT 1",
    ['id' => $excepto],
)->fetchColumn();

$errores = [];
$valores = $actual ?? ['usuario' => '', 'nombre' => '', 'rol' => 'UNIDAD', 'unidad_id' => null, 'activo' => 1];

// La clave generada se muestra una sola vez, después de redirigir (así recargar no la vuelve a generar).
$clave_entregada = null;
if ($actual !== null && ($_SESSION['clave_entregada']['id'] ?? null) === (int) $actual['id']) {
    $clave_entregada = $_SESSION['clave_entregada']['clave'];
}
unset($_SESSION['clave_entregada']);

$entregar_clave = static function (int $usuario_id, string $clave): never {
    $_SESSION['clave_entregada'] = ['id' => $usuario_id, 'clave' => $clave];
    redirigir('admin/usuarios/editar', ['id' => $usuario_id]);
};

if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? 'guardar');

    if ($actual !== null && $accion === 'resetear_clave') {
        $clave_nueva = generar_clave_temporal();
        consultar(
            'UPDATE usuarios
             SET password_hash = :hash, intentos_fallidos = 0, bloqueado_hasta = NULL, version_sesion = version_sesion + 1
             WHERE id = :id',
            ['hash' => password_hash($clave_nueva, PASSWORD_DEFAULT), 'id' => $id],
        );
        registrar_bitacora('RESETEAR_CLAVE', 'usuarios', $id, ['usuario' => $actual['usuario']]);
        if ($es_propia) {
            // La propia sesión también se cerró: hay que volver a ingresar con la clave nueva.
            guardar_mensaje('warning', "Su nueva clave es: $clave_nueva — anótela y vuelva a ingresar.");
            redirigir('login');
        }
        $entregar_clave((int) $id, $clave_nueva);
    } elseif ($actual !== null && $accion === 'desbloquear') {
        consultar('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id', ['id' => $id]);
        registrar_bitacora('DESBLOQUEAR_USUARIO', 'usuarios', $id, ['usuario' => $actual['usuario']]);
        guardar_mensaje('success', "Se desbloqueó la cuenta {$actual['usuario']}.");
        redirigir('admin/usuarios/editar', ['id' => $id]);
    } else {
        $ids_unidades = array_map('intval', consultar('SELECT id FROM unidades')->fetchAll(PDO::FETCH_COLUMN));
        ['datos' => $datos, 'errores' => $errores] = validar_usuario($_POST, $ids_unidades, $actual === null);
        $valores = array_merge($valores, $_POST, ['activo' => $datos['activo']]);

        if ($es_propia && (($datos['rol'] ?? 'ADMIN') !== 'ADMIN' || !$datos['activo'])) {
            $errores['rol'] = 'No puede quitarse el rol ADMIN ni desactivar su propia cuenta.';
        } elseif (
            $actual !== null && $actual['rol'] === 'ADMIN' && $actual['activo']
            && (($datos['rol'] ?? 'ADMIN') !== 'ADMIN' || !$datos['activo'])
            && !$hay_otro_admin((int) $id)
        ) {
            $errores['rol'] = 'Debe quedar al menos un ADMIN activo.';
        }

        if ($errores === []) {
            try {
                if ($actual === null) {
                    $clave_elegida = $datos['clave'] ?? null;
                    $clave = $clave_elegida ?? generar_clave_temporal();
                    unset($datos['clave']);
                    consultar(
                        'INSERT INTO usuarios (usuario, password_hash, nombre, rol, unidad_id, activo)
                         VALUES (:usuario, :hash, :nombre, :rol, :unidad_id, :activo)',
                        $datos + ['hash' => password_hash($clave, PASSWORD_DEFAULT)],
                    );
                    $id = (int) db()->lastInsertId();
                    registrar_bitacora('CREAR_USUARIO', 'usuarios', $id, $datos);
                    guardar_mensaje('success', "Se creó la cuenta {$datos['usuario']}.");
                    if ($clave_elegida === null) {
                        $entregar_clave($id, $clave);
                    }
                    redirigir('admin/usuarios');
                } else {
                    $cambios = calcular_cambios($actual, $datos);
                    if ($cambios !== []) {
                        $cierra_sesiones = $actual['activo'] && !$datos['activo'];
                        consultar(
                            'UPDATE usuarios
                             SET usuario = :usuario, nombre = :nombre, rol = :rol, unidad_id = :unidad_id, activo = :activo'
                             . ($cierra_sesiones ? ', version_sesion = version_sesion + 1' : '') . '
                             WHERE id = :id',
                            $datos + ['id' => $id],
                        );
                        registrar_bitacora($cierra_sesiones ? 'DESACTIVAR_USUARIO' : 'EDITAR_USUARIO', 'usuarios', $id, $cambios);
                    }
                    guardar_mensaje('success', "Se guardó la cuenta {$datos['usuario']}.");
                    redirigir('admin/usuarios');
                }
            } catch (PDOException $error) {
                if (!es_error_duplicado($error)) {
                    throw $error;
                }
                $duplicada_unidad = $datos['unidad_id'] !== null && (bool) consultar(
                    'SELECT 1 FROM usuarios WHERE unidad_id = :unidad_id AND id <> :id',
                    ['unidad_id' => $datos['unidad_id'], 'id' => $id ?? 0],
                )->fetchColumn();
                if ($duplicada_unidad) {
                    $errores['unidad_id'] = 'Esa unidad ya tiene una cuenta (RN-20).';
                } else {
                    $errores['usuario'] = 'Ya existe otra cuenta con ese usuario.';
                }
            }
        }
    }
}

// Unidades para elegir, marcando las que ya tienen otra cuenta.
$unidades = consultar(
    'SELECT un.id, un.division, un.nombre_corto, un.activo, us.id AS usuario_id, us.usuario
     FROM unidades un
     LEFT JOIN usuarios us ON us.unidad_id = un.id
     ORDER BY un.orden, un.nombre_corto',
)->fetchAll();

$titulo = $actual === null ? 'Nuevo usuario' : 'Usuario ' . $actual['usuario'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<nav aria-label="Ruta de navegación">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(armar_url('admin/usuarios')) ?>">Usuarios</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($actual === null ? 'Nuevo' : $actual['usuario']) ?></li>
    </ol>
</nav>
<h1 class="h4 mb-3"><?= e($titulo) ?></h1>

<?php if ($clave_entregada !== null): ?>
    <div class="alert alert-success formulario-angosto" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-key"></i> Clave de <?= e($actual['usuario']) ?></div>
        <div class="fs-4 font-monospace user-select-all mb-1"><?= e($clave_entregada) ?></div>
        <div class="small">Entréguela al responsable de la cuenta. No se volverá a mostrar.
            Si la cuenta tenía sesiones abiertas, se cerraron.</div>
    </div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm formulario-angosto mb-3" novalidate
      x-data="{ rol: <?= e(json_encode($valores['rol'])) ?> }">
    <?= campo_csrf() ?>
    <input type="hidden" name="accion" value="guardar">

    <div class="mb-3">
        <label for="usuario" class="form-label">Usuario</label>
        <input type="text" id="usuario" name="usuario" maxlength="50" required autocapitalize="none" spellcheck="false"
               class="form-control<?= clase_error($errores, 'usuario') ?>" value="<?= e($valores['usuario']) ?>"
               placeholder="com.ayacucho" aria-describedby="ayuda-usuario">
        <div id="ayuda-usuario" class="form-text">Letras sin tilde, números, punto, guion o guion bajo.</div>
        <?= mostrar_error($errores, 'usuario') ?>
    </div>
    <div class="mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="150" required
               class="form-control<?= clase_error($errores, 'nombre') ?>" value="<?= e($valores['nombre']) ?>"
               placeholder="Comisaría PNP Ayacucho / CRNL PNP Juan Pérez">
        <?= mostrar_error($errores, 'nombre') ?>
    </div>
    <div class="mb-3">
        <label for="rol" class="form-label">Rol</label>
        <select id="rol" name="rol" x-model="rol" class="form-select<?= clase_error($errores, 'rol') ?>"
                required<?= $es_propia ? ' disabled' : '' ?>>
            <?php foreach (ROLES as $opcion): ?>
                <option value="<?= e($opcion) ?>"<?= marcar($valores['rol'] === $opcion, 'selected') ?>><?= e($opcion) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($es_propia): ?>
            <input type="hidden" name="rol" value="ADMIN">
            <div class="form-text">No puede cambiar el rol de su propia cuenta.</div>
        <?php endif; ?>
        <?= mostrar_error($errores, 'rol') ?>
    </div>
    <div class="mb-3" x-show="rol === 'UNIDAD'" x-cloak>
        <label for="unidad_id" class="form-label">Unidad</label>
        <select id="unidad_id" name="unidad_id" class="form-select<?= clase_error($errores, 'unidad_id') ?>" :required="rol === 'UNIDAD'">
            <option value="">Elija la unidad…</option>
            <?php foreach (DIVISIONES as $division => $etiqueta): ?>
                <optgroup label="<?= e($etiqueta) ?>">
                    <?php foreach ($unidades as $unidad): ?>
                        <?php if ($unidad['division'] !== $division) continue; ?>
                        <?php $ocupada = $unidad['usuario_id'] !== null && (int) $unidad['usuario_id'] !== (int) ($actual['id'] ?? 0); ?>
                        <option value="<?= e($unidad['id']) ?>"
                            <?= marcar((string) $valores['unidad_id'] === (string) $unidad['id'], 'selected') ?>
                            <?= $ocupada ? ' disabled' : '' ?>>
                            <?= e($unidad['nombre_corto']) ?><?= $ocupada ? ' — ya tiene cuenta (' . e($unidad['usuario']) . ')' : '' ?><?= $unidad['activo'] ? '' : ' — inactiva' ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Cada unidad tiene una sola cuenta, compartida (RN-17, RN-20).</div>
        <?= mostrar_error($errores, 'unidad_id') ?>
    </div>
    <?php if ($actual === null): ?>
        <div class="mb-3" x-data="{ visible: false }">
            <label for="clave" class="form-label">Clave <span class="text-body-secondary">(opcional)</span></label>
            <div class="input-group">
                <input :type="visible ? 'text' : 'password'" type="password" id="clave" name="clave" autocomplete="new-password"
                       class="form-control<?= clase_error($errores, 'clave') ?>" aria-describedby="ayuda-clave">
                <button type="button" class="btn btn-outline-secondary" @click="visible = !visible" aria-label="Mostrar u ocultar la clave">
                    <i class="bi" :class="visible ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
                <?= mostrar_error($errores, 'clave') ?>
            </div>
            <div id="ayuda-clave" class="form-text">
                Si la deja vacía, el sistema genera una de <?= LARGO_CLAVE_TEMPORAL ?> caracteres y se la muestra una sola vez.
                Mínimo <?= LARGO_MINIMO_CLAVE ?> caracteres.
            </div>
        </div>
    <?php endif; ?>
    <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1"
               <?= marcar((bool) $valores['activo']) ?><?= $es_propia ? ' disabled' : '' ?>>
        <?php if ($es_propia): ?><input type="hidden" name="activo" value="1"><?php endif; ?>
        <label class="form-check-label" for="activo">Activa</label>
        <div class="form-text">Al desactivarla se cierran sus sesiones abiertas.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-success boton-accion px-4"><i class="bi bi-check-lg"></i> Guardar</button>
        <a href="<?= e(armar_url('admin/usuarios')) ?>" class="btn btn-outline-secondary boton-accion px-4">Volver</a>
    </div>
</form>

<?php if ($actual !== null): ?>
    <div class="card card-body shadow-sm formulario-angosto">
        <h2 class="h6">Acceso</h2>
        <p class="small text-body-secondary mb-3">
            Último acceso: <?= e(formatear_hace($actual['ultimo_acceso'], $ahora)) ?>
            · Intentos fallidos: <?= e($actual['intentos_fallidos']) ?>
            <?php if (cuenta_bloqueada($actual['bloqueado_hasta'], $ahora)): ?>
                · <span class="text-danger fw-semibold">Bloqueada hasta las <?= e(convertir_a_fecha_hora($actual['bloqueado_hasta'])->format('H:i')) ?></span>
            <?php endif; ?>
        </p>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" data-confirmar-titulo="¿Resetear la clave de <?= e($actual['usuario']) ?>?"
                  data-confirmar="Se generará una clave nueva y se cerrarán las sesiones abiertas de la cuenta."
                  data-confirmar-boton="Sí, resetear" data-confirmar-tipo="peligro">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="resetear_clave">
                <button type="submit" class="btn btn-outline-danger boton-accion"><i class="bi bi-key"></i> Resetear clave</button>
            </form>
            <?php if (cuenta_bloqueada($actual['bloqueado_hasta'], $ahora) || $actual['intentos_fallidos'] > 0): ?>
                <form method="post">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="desbloquear">
                    <button type="submit" class="btn btn-outline-primary boton-accion"><i class="bi bi-unlock"></i> Desbloquear</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
