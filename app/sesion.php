<?php
/**
 * Sesión, ingreso y CSRF (RN-17 a RN-19, docs/03).
 */
declare(strict_types=1);

// Hash de una clave cualquiera, para que un usuario inexistente tarde lo mismo en responder.
const HASH_FICTICIO = '$2y$10$76MP7vk03aUu5bZS4SB8y.ypUFXmpIvDezKy6vh6k3l/I.QM8dw..';

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Carpeta propia: la limpieza de sesiones de otros sitios del hosting no borra las nuestras.
    ini_set('session.save_path', RUTA_APP . '/almacen/sesiones');
    ini_set('session.gc_maxlifetime', (string) DURACION_SESION_S);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('operativos');
    session_set_cookie_params([
        'lifetime' => DURACION_SESION_S,
        'path'     => URL_BASE === '' ? '/' : URL_BASE . '/',
        'secure'   => ENTORNO === 'produccion',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    renovar_sesion();
}

/**
 * La sesión dura DURACION_SESION_S desde la última actividad (RN-18).
 * La cookie se renueva como máximo cada 10 minutos.
 */
function renovar_sesion(): void
{
    $ahora = time();
    if (isset($_SESSION['ultima_actividad']) && $ahora - $_SESSION['ultima_actividad'] > DURACION_SESION_S) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['ultima_actividad'] = $ahora;

    if ($ahora - ($_SESSION['cookie_renovada'] ?? 0) > 600) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => $ahora + DURACION_SESION_S,
            'path'     => $parametros['path'],
            'secure'   => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'],
        ]);
        $_SESSION['cookie_renovada'] = $ahora;
    }
}

/**
 * Usuario de la sesión, verificado contra la base de datos en cada petición:
 * si la cuenta se desactivó o se reseteó su clave, la sesión se cierra (RN-19).
 *
 * @return array{id: int, usuario: string, nombre: string, rol: string, unidad_id: int|null, unidad_nombre: string|null}|null
 */
function usuario_actual(): ?array
{
    static $usuario = false;
    if ($usuario !== false) {
        return $usuario;
    }
    $usuario = null;

    $sesion = $_SESSION['usuario'] ?? null;
    if ($sesion === null) {
        return null;
    }

    $fila = consultar(
        'SELECT u.id, u.usuario, u.nombre, u.rol, u.unidad_id, u.activo, u.version_sesion,
                un.nombre_corto AS unidad_nombre
         FROM usuarios u
         LEFT JOIN unidades un ON un.id = u.unidad_id
         WHERE u.id = :id',
        ['id' => $sesion['id']],
    )->fetch();

    if (!$fila || !$fila['activo'] || (int) $fila['version_sesion'] !== $sesion['version_sesion']) {
        unset($_SESSION['usuario']);
        return null;
    }

    $usuario = [
        'id'            => (int) $fila['id'],
        'usuario'       => $fila['usuario'],
        'nombre'        => $fila['nombre'],
        'rol'           => $fila['rol'],
        'unidad_id'     => $fila['unidad_id'] === null ? null : (int) $fila['unidad_id'],
        'unidad_nombre' => $fila['unidad_nombre'],
    ];
    return $usuario;
}

/**
 * Intenta el ingreso. El bloqueo es por cuenta: 5 intentos fallidos la
 * bloquean 15 minutos, sin importar la IP (RN-19).
 *
 * @return string|null Mensaje de error, o null si ingresó.
 */
function intentar_ingreso(string $nombre_usuario, string $clave): ?string
{
    $mensaje_generico = 'Usuario o clave incorrectos.';
    $ahora = obtener_ahora();

    $fila = consultar(
        'SELECT id, password_hash, activo, intentos_fallidos, bloqueado_hasta, version_sesion
         FROM usuarios WHERE usuario = :usuario',
        ['usuario' => $nombre_usuario],
    )->fetch();

    if (!$fila) {
        password_verify($clave, HASH_FICTICIO);
        registrar_bitacora('LOGIN_FALLIDO', null, null, ['usuario' => mb_substr($nombre_usuario, 0, 50)]);
        return $mensaje_generico;
    }

    $id = (int) $fila['id'];
    if (cuenta_bloqueada($fila['bloqueado_hasta'], $ahora)) {
        registrar_bitacora('LOGIN_BLOQUEADO', 'usuarios', $id, [], $id);
        $hasta = convertir_a_fecha_hora($fila['bloqueado_hasta'])->format('H:i');
        return "La cuenta está bloqueada por intentos fallidos hasta las $hasta.";
    }

    if (!password_verify($clave, $fila['password_hash']) || !$fila['activo']) {
        $resultado = calcular_intento_fallido((int) $fila['intentos_fallidos'], $ahora);
        consultar(
            'UPDATE usuarios SET intentos_fallidos = :intentos, bloqueado_hasta = :bloqueado_hasta WHERE id = :id',
            [
                'intentos'        => $resultado['intentos'],
                'bloqueado_hasta' => $resultado['bloqueado_hasta']?->format(FORMATO_FECHA_HORA),
                'id'              => $id,
            ],
        );
        if ($resultado['bloqueado_hasta'] !== null) {
            registrar_bitacora('BLOQUEO', 'usuarios', $id, ['minutos' => MINUTOS_BLOQUEO], $id);
            return 'Demasiados intentos fallidos. La cuenta queda bloqueada ' . MINUTOS_BLOQUEO . ' minutos.';
        }
        registrar_bitacora('LOGIN_FALLIDO', 'usuarios', $id, [], $id);
        return $mensaje_generico;
    }

    $nuevo_hash = password_needs_rehash($fila['password_hash'], PASSWORD_DEFAULT)
        ? password_hash($clave, PASSWORD_DEFAULT)
        : $fila['password_hash'];
    consultar(
        'UPDATE usuarios
         SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW(), password_hash = :hash
         WHERE id = :id',
        ['hash' => $nuevo_hash, 'id' => $id],
    );

    session_regenerate_id(true);
    $_SESSION = [
        'usuario'          => ['id' => $id, 'version_sesion' => (int) $fila['version_sesion']],
        'ultima_actividad' => time(),
    ];
    registrar_bitacora('LOGIN', 'usuarios', $id, [], $id);
    return null;
}

function cerrar_sesion(): void
{
    $usuario = usuario_actual();
    if ($usuario !== null) {
        registrar_bitacora('LOGOUT', 'usuarios', $usuario['id']);
    }
    $_SESSION = [];
    $parametros = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $parametros['path'],
        'secure'   => $parametros['secure'],
        'httponly' => $parametros['httponly'],
        'samesite' => $parametros['samesite'],
    ]);
    session_destroy();
}

// ---------------------------------------------------------------------------
// CSRF: todo POST lleva el token, en el campo "csrf" o en la cabecera X-CSRF-Token.
// ---------------------------------------------------------------------------

function obtener_token_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(obtener_token_csrf()) . '">';
}

function verificar_csrf(): void
{
    $recibido = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($recibido) || !hash_equals(obtener_token_csrf(), $recibido)) {
        if (es_peticion_api()) {
            responder_json(['error' => 'La sesión cambió. Recargue la página.'], 419);
        }
        http_response_code(419);
        exit('La página expiró. Vuelva atrás, recárguela e intente de nuevo.');
    }
}
