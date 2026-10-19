<?php
/**
 * ADMIN: crear o editar una cuenta, resetear su clave y desbloquearla (RN-19, RN-20).
 * Cada unidad tiene hasta MAX_CUENTAS_POR_UNIDAD cuentas activas, personales.
 * Al resetear la clave o desactivar la cuenta se cierran sus sesiones (version_sesion).
 */
declare(strict_types=1);

$admin = requiere_rol('ADMIN');
$ahora = obtener_ahora();

$id = obtener_id_get();
$actual = null;
if ($id !== null) {
    $actual = consultar(
        'SELECT id, usuario, nombre, grado, cip, celular, rol, unidad_id, activo, intentos_fallidos, bloqueado_hasta, ultimo_acceso
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
$valores = $actual ?? [
    'usuario' => '', 'nombre' => '', 'grado' => '', 'cip' => '', 'celular' => '',
    'rol' => 'UNIDAD', 'unidad_id' => null, 'activo' => 1,
];
// "Crear cuenta" desde la lista de unidades con cuentas por crear llega con la unidad ya elegida.
if ($actual === null && ($unidad_elegida = obtener_id_get('unidad_id')) !== null) {
    $valores['unidad_id'] = $unidad_elegida;
}

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

        if ($errores === [] && $datos['unidad_id'] !== null) {
            $otras_activas = (int) consultar(
                'SELECT COUNT(*) FROM usuarios WHERE unidad_id = :unidad_id AND activo = 1 AND id <> :id',
                ['unidad_id' => $datos['unidad_id'], 'id' => $id ?? 0],
            )->fetchColumn();
            $error_cupo = validar_cupo_unidad($otras_activas, (bool) $datos['activo']);
            if ($error_cupo !== null) {
                $errores['unidad_id'] = $error_cupo;
            }
        }

        if ($errores === []) {
            try {
                if ($actual === null) {
                    $clave_elegida = $datos['clave'] ?? null;
                    $clave = $clave_elegida ?? generar_clave_temporal();
                    unset($datos['clave']);
                    consultar(
                        'INSERT INTO usuarios (usuario, password_hash, nombre, grado, cip, celular, rol, unidad_id, activo)
                         VALUES (:usuario, :hash, :nombre, :grado, :cip, :celular, :rol, :unidad_id, :activo)',
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
                             SET usuario = :usuario, nombre = :nombre, grado = :grado, cip = :cip, celular = :celular,
                                 rol = :rol, unidad_id = :unidad_id, activo = :activo'
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
                $duplicado_cip = $datos['cip'] !== null && (bool) consultar(
                    'SELECT 1 FROM usuarios WHERE cip = :cip AND id <> :id',
                    ['cip' => $datos['cip'], 'id' => $id ?? 0],
                )->fetchColumn();
                if ($duplicado_cip) {
                    $errores['cip'] = 'Ya existe otra cuenta con ese CIP.';
                } else {
                    $errores['usuario'] = 'Ya existe otra cuenta con ese usuario.';
                }
            }
        }
    }
}

// Unidades para elegir, marcando las que ya completaron su cupo con otras cuentas activas.
$unidades = consultar(
    "SELECT un.id, un.division, un.nombre, un.nombre_corto, un.activo,
            COUNT(us.id) AS otras, GROUP_CONCAT(us.usuario ORDER BY us.usuario SEPARATOR ', ') AS usuarios
     FROM unidades un
     LEFT JOIN usuarios us ON us.unidad_id = un.id AND us.activo = 1 AND us.id <> :id
     GROUP BY un.id, un.division, un.nombre, un.nombre_corto, un.activo, un.orden
     ORDER BY un.orden, un.nombre_corto",
    ['id' => $actual['id'] ?? 0],
)->fetchAll();

$bloqueada = $actual !== null && cuenta_bloqueada($actual['bloqueado_hasta'], $ahora);
// Al crear una cuenta de unidad, elegir la unidad sugiere el usuario (usuario_formulario.js).
$configuracion = [
    'rol'      => (string) $valores['rol'],
    'unidadId' => (string) ($valores['unidad_id'] ?? ''),
    'usuario'  => (string) $valores['usuario'],
    'sugerir'  => $actual === null,
    'unidades' => array_column(array_map(static fn (array $u): array => [
        'id' => (string) $u['id'], 'corto' => $u['nombre_corto'],
    ], $unidades), null, 'id'),
];

$titulo = $actual === null ? 'Nuevo usuario' : $actual['usuario'];
$scripts = ['assets/js/usuario_formulario.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="formulario-angosto mx-auto">
    <a href="<?= e(armar_url('admin/usuarios')) ?>" class="d-inline-block small text-body-secondary text-decoration-none mb-2">
        <i class="bi bi-arrow-left"></i> Usuarios
    </a>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-auto"><?= e($titulo) ?></h1>
        <?php if ($actual !== null): ?>
            <span class="badge text-bg-light border fw-normal"><?= e(obtener_etiqueta_rol($actual['rol'])) ?></span>
            <?php if (!$actual['activo']): ?><span class="badge text-bg-secondary">Inactiva</span><?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($clave_entregada !== null): ?>
        <!-- Clave generada: se muestra una sola vez -->
        <div class="alert alert-success" role="alert" x-data="{ copiada: false }">
            <div class="fw-semibold mb-1"><i class="bi bi-key"></i> Clave de <?= e($actual['usuario']) ?></div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fs-4 font-monospace user-select-all me-auto" x-ref="clave"><?= e($clave_entregada) ?></span>
                <button type="button" class="btn btn-sm btn-outline-success"
                        @click="navigator.clipboard && navigator.clipboard.writeText($refs.clave.textContent.trim()).then(() => copiada = true)">
                    <i class="bi" :class="copiada ? 'bi-check-lg' : 'bi-clipboard'"></i> <span x-text="copiada ? 'Copiada' : 'Copiar'">Copiar</span>
                </button>
            </div>
            <div class="small">Entréguela al responsable de la cuenta. No se volverá a mostrar.
                Si la cuenta tenía sesiones abiertas, se cerraron.</div>
        </div>
    <?php endif; ?>

    <?php if ($actual !== null): ?>
        <!-- Acceso: lo que más se busca al abrir una cuenta (bloqueo y clave olvidada) -->
        <div class="card shadow-sm mb-3<?= $bloqueada ? ' border-danger' : '' ?>">
            <div class="card-body py-2">
                <?php if ($bloqueada): ?>
                    <div class="text-danger fw-semibold mb-1">
                        <i class="bi bi-lock-fill"></i> Bloqueada hasta las <?= e(convertir_a_fecha_hora($actual['bloqueado_hasta'])->format('H:i')) ?>
                    </div>
                <?php endif; ?>
                <div class="small text-body-secondary mb-2">
                    Último acceso: <span class="<?= $actual['ultimo_acceso'] === null ? 'acceso-nunca' : '' ?>"><?= e($actual['ultimo_acceso'] === null ? 'Nunca ingresó' : formatear_hace($actual['ultimo_acceso'], $ahora)) ?></span>
                    <?php if ($actual['intentos_fallidos'] > 0): ?>· Intentos fallidos: <?= e($actual['intentos_fallidos']) ?><?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($bloqueada || $actual['intentos_fallidos'] > 0): ?>
                        <form method="post">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="desbloquear">
                            <button type="submit" class="btn btn-sm <?= $bloqueada ? 'btn-danger' : 'btn-outline-institucional' ?>">
                                <i class="bi bi-unlock"></i> Desbloquear
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="post" data-confirmar-titulo="¿Resetear la clave de <?= e($actual['usuario']) ?>?"
                          data-confirmar="Se generará una clave nueva y se cerrarán las sesiones abiertas de la cuenta."
                          data-confirmar-boton="Sí, resetear" data-confirmar-tipo="peligro">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="resetear_clave">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-key"></i> Resetear clave</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" class="card card-body shadow-sm mb-3" novalidate x-data="formularioUsuario(<?= e(json_encode($configuracion)) ?>)">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar">

        <!-- Rol primero: decide si hay que elegir unidad -->
        <fieldset class="mb-3">
            <legend class="form-label fs-6 mb-1">Rol</legend>
            <div class="selector-rol" role="group">
                <?php foreach (['UNIDAD', 'SUPERVISOR', 'ADMIN'] as $opcion): ?>
                    <input type="radio" class="btn-check" name="rol" id="rol-<?= e(strtolower($opcion)) ?>" value="<?= e($opcion) ?>"
                           x-model="rol"<?= $es_propia ? ' disabled' : '' ?>>
                    <label class="btn btn-outline-institucional" for="rol-<?= e(strtolower($opcion)) ?>"><?= e(obtener_etiqueta_rol($opcion)) ?></label>
                <?php endforeach; ?>
            </div>
            <?php if ($es_propia): ?>
                <input type="hidden" name="rol" value="ADMIN">
                <div class="form-text">No puede cambiar el rol de su propia cuenta.</div>
            <?php endif; ?>
            <?php if (isset($errores['rol'])): ?><div class="text-danger small mt-1"><?= e($errores['rol']) ?></div><?php endif; ?>
        </fieldset>

        <div class="mb-3" x-show="rol === 'UNIDAD'" x-cloak>
            <label for="unidad_id" class="form-label">Unidad</label>
            <select id="unidad_id" name="unidad_id" class="form-select<?= clase_error($errores, 'unidad_id') ?>" :required="rol === 'UNIDAD'"
                    x-model="unidadId" @change="sugerir()">
                <option value="">Elija la unidad…</option>
                <?php foreach (DIVISIONES as $division => $etiqueta): ?>
                    <optgroup label="<?= e($etiqueta) ?>">
                        <?php foreach ($unidades as $unidad): ?>
                            <?php if ($unidad['division'] !== $division) continue; ?>
                            <?php $completa = $unidad['otras'] >= MAX_CUENTAS_POR_UNIDAD; ?>
                            <option value="<?= e($unidad['id']) ?>"<?= $completa ? ' disabled' : '' ?>>
                                <?= e($unidad['nombre_corto']) ?><?= $unidad['usuarios'] !== null ? ' — ' . ($completa ? 'completa' : 'ya tiene') . ' (' . e($unidad['usuarios']) . ')' : '' ?><?= $unidad['activo'] ? '' : ' — inactiva' ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Cada unidad tiene hasta <?= MAX_CUENTAS_POR_UNIDAD ?> cuentas activas, una por persona (RN-17, RN-20).</div>
            <?= mostrar_error($errores, 'unidad_id') ?>
        </div>

        <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" id="usuario" name="usuario" maxlength="50" required autocapitalize="none" spellcheck="false"
                   class="form-control<?= clase_error($errores, 'usuario') ?>" x-model="usuario"
                   placeholder="com.ayacucho.jefe" aria-describedby="ayuda-usuario">
            <div id="ayuda-usuario" class="form-text">Letras sin tilde, números, punto, guion o guion bajo.</div>
            <?= mostrar_error($errores, 'usuario') ?>
        </div>
        <!-- Datos de la persona: obligatorios en las cuentas de unidad (RN-20) -->
        <div class="row g-2 mb-3">
            <div class="col-5 col-sm-4">
                <label for="grado" class="form-label">Grado <span class="text-body-secondary" x-show="rol !== 'UNIDAD'">(opcional)</span></label>
                <select id="grado" name="grado" class="form-select<?= clase_error($errores, 'grado') ?>" :required="rol === 'UNIDAD'">
                    <option value="">Elija…</option>
                    <?php foreach (GRADOS as $grado): ?>
                        <option value="<?= e($grado) ?>"<?= marcar($grado === (string) ($valores['grado'] ?? ''), 'selected') ?>><?= e($grado) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= mostrar_error($errores, 'grado') ?>
            </div>
            <div class="col-7 col-sm-8">
                <label for="nombre" class="form-label">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" maxlength="150" required autocomplete="off"
                       class="form-control<?= clase_error($errores, 'nombre') ?>" value="<?= e($valores['nombre']) ?>"
                       placeholder="Juan Pérez García">
                <?= mostrar_error($errores, 'nombre') ?>
            </div>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-6">
                <label for="cip" class="form-label">CIP <span class="text-body-secondary" x-show="rol !== 'UNIDAD'">(opcional)</span></label>
                <input type="text" id="cip" name="cip" maxlength="8" inputmode="numeric" autocomplete="off"
                       class="form-control<?= clase_error($errores, 'cip') ?>" value="<?= e($valores['cip'] ?? '') ?>"
                       placeholder="31234567" :required="rol === 'UNIDAD'">
                <?= mostrar_error($errores, 'cip') ?>
            </div>
            <div class="col-6">
                <label for="celular" class="form-label">Celular <span class="text-body-secondary" x-show="rol !== 'UNIDAD'">(opcional)</span></label>
                <input type="tel" id="celular" name="celular" maxlength="15" inputmode="tel" autocomplete="off"
                       class="form-control<?= clase_error($errores, 'celular') ?>" value="<?= e($valores['celular'] ?? '') ?>"
                       placeholder="987 654 321" :required="rol === 'UNIDAD'">
                <?= mostrar_error($errores, 'celular') ?>
            </div>
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
                    Vacía: el sistema genera una de <?= LARGO_CLAVE_TEMPORAL ?> caracteres y la muestra una sola vez.
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
            <button type="submit" class="btn btn-institucional boton-accion px-4 flex-grow-1 flex-sm-grow-0">
                <i class="bi bi-check-lg"></i> <?= $actual === null ? 'Crear cuenta' : 'Guardar' ?>
            </button>
            <a href="<?= e(armar_url('admin/usuarios')) ?>" class="btn btn-outline-secondary boton-accion px-4">Cancelar</a>
        </div>
    </form>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
