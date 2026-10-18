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
 * Las sesiones con usuario vencen según su rol (RN-18) en usuario_actual(), que
 * conoce el rol, avisa y deja constancia en la bitácora; por eso la última
 * actividad se actualiza allí. Aquí solo se limpian las sesiones sin usuario
 * (por ejemplo, la del formulario de ingreso). La cookie se renueva como
 * máximo cada 10 minutos.
 */
function renovar_sesion(): void
{
    $ahora = time();
    if (!isset($_SESSION['usuario']) && isset($_SESSION['ultima_actividad'])
        && $ahora - $_SESSION['ultima_actividad'] > DURACION_SESION_S) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

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

    // RN-18: duración por rol, por inactividad y como máximo desde el ingreso.
    $ahora = time();
    $_SESSION['ingreso'] ??= $ahora; // sesiones abiertas antes de esta regla
    $inactividad_s = SESION_INACTIVIDAD_S[$fila['rol']] ?? DURACION_SESION_S;
    $maxima_s = SESION_MAXIMA_S[$fila['rol']] ?? DURACION_SESION_S;
    $ultima = $_SESSION['ultima_actividad'] ?? $ahora;
    if (sesion_vencida($ahora, $_SESSION['ingreso'], $ultima, $inactividad_s, $maxima_s)) {
        $por_inactividad = $ahora - $ultima > $inactividad_s;
        registrar_bitacora('SESION_VENCIDA', 'usuarios', (int) $fila['id'], [
            'motivo' => $por_inactividad ? 'inactividad' : 'tiempo_maximo',
        ], (int) $fila['id']);
        unset($_SESSION['usuario'], $_SESSION['ingreso'], $_SESSION['ultima_actividad']);
        if (!es_peticion_api()) {
            guardar_mensaje('info', $por_inactividad
                ? 'Su sesión se cerró por inactividad. Vuelva a ingresar.'
                : 'Por seguridad, su sesión se cerró. Vuelva a ingresar.');
        }
        return null;
    }
    $_SESSION['ultima_actividad'] = $ahora;

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
 * Procesa el formulario de ingreso con sus defensas, en este orden (RN-19a):
 * límite por IP, campo trampa (honeypot), Cloudflare Turnstile y, al final,
 * usuario y clave. Cada fallo cuenta para el límite de la IP.
 *
 * @return string|null Mensaje de error, o null si ingresó.
 */
function procesar_ingreso(string $nombre_usuario, string $clave, string $trampa, string $token_turnstile): ?string
{
    $ip = obtener_ip_cliente($_SERVER);
    $ahora = obtener_ahora();
    $desde = $ahora->modify('-' . VENTANA_FALLIDOS_IP_MIN . ' minutes')->format(FORMATO_FECHA_HORA);
    $fallidos = (int) consultar(
        'SELECT COUNT(*) FROM intentos_ingreso WHERE ip = :ip AND registrado_en >= :desde',
        ['ip' => $ip, 'desde' => $desde],
    )->fetchColumn();
    if (ip_excede_intentos($fallidos)) {
        registrar_bitacora('LOGIN_IP_FRENADA', null, null, ['usuario' => mb_substr($nombre_usuario, 0, 50)]);
        return 'Demasiados intentos desde esta conexión. Espere ' . VENTANA_FALLIDOS_IP_MIN . ' minutos e intente nuevamente.';
    }

    // Un campo invisible que solo llenan los bots. No se les dice por qué falló.
    if ($trampa !== '') {
        registrar_fallido_ip($ip, $nombre_usuario, 'TRAMPA', $ahora);
        registrar_bitacora('LOGIN_BOT', null, null, ['usuario' => mb_substr($nombre_usuario, 0, 50)]);
        return 'Usuario o clave incorrectos.';
    }

    if (TURNSTILE_SECRET_KEY !== '' && !verificar_turnstile($token_turnstile, $ip)) {
        registrar_fallido_ip($ip, $nombre_usuario, 'VERIFICACION', $ahora);
        return 'No se pudo verificar que es una persona. Espere unos segundos e intente nuevamente.';
    }

    if ($nombre_usuario === '' || $clave === '') {
        return 'Ingrese su usuario y su clave.';
    }

    $error = intentar_ingreso($nombre_usuario, $clave);
    if ($error !== null) {
        registrar_fallido_ip($ip, $nombre_usuario, 'CLAVE', $ahora);
    }
    return $error;
}

/** Guarda un fallo de la IP y, de paso, borra los de más de un día (no hay cron). */
function registrar_fallido_ip(string $ip, string $nombre_usuario, string $motivo, DateTimeImmutable $ahora): void
{
    consultar('DELETE FROM intentos_ingreso WHERE registrado_en < :limite', [
        'limite' => $ahora->modify('-1 day')->format(FORMATO_FECHA_HORA),
    ]);
    consultar(
        'INSERT INTO intentos_ingreso (ip, usuario, motivo, registrado_en) VALUES (:ip, :usuario, :motivo, :ahora)',
        [
            'ip'      => mb_substr($ip, 0, 45),
            'usuario' => $nombre_usuario === '' ? null : mb_substr($nombre_usuario, 0, 50),
            'motivo'  => $motivo,
            'ahora'   => $ahora->format(FORMATO_FECHA_HORA),
        ],
    );
}

/**
 * Valida la respuesta del widget de Cloudflare Turnstile con su API. Si no se
 * puede consultar (sin red), se rechaza: el ingreso no queda sin esa defensa.
 */
function verificar_turnstile(string $token, string $ip): bool
{
    if ($token === '' || strlen($token) > 2048) {
        return false;
    }
    $datos = http_build_query(['secret' => TURNSTILE_SECRET_KEY, 'response' => $token, 'remoteip' => $ip]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    if (function_exists('curl_init')) {
        $conexion = curl_init($url);
        curl_setopt_array($conexion, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $datos,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $respuesta = curl_exec($conexion);
        curl_close($conexion);
    } else {
        $respuesta = @file_get_contents($url, false, stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $datos,
            'timeout' => 8,
        ]]));
    }
    if (!is_string($respuesta)) {
        error_log('Turnstile: no se pudo consultar siteverify.');
        return false;
    }
    $resultado = json_decode($respuesta, true);
    return is_array($resultado) && ($resultado['success'] ?? false) === true;
}

/**
 * Intenta el ingreso. La espera es por cuenta y crece con cada fallo desde el
 * 5.º (1, 2, 4, 8 y hasta 15 minutos), sin importar la IP (RN-19).
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
        return "Demasiados intentos fallidos. Podrá intentar nuevamente a las $hasta.";
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
            $minutos = minutos_de_espera($resultado['intentos']);
            registrar_bitacora('BLOQUEO', 'usuarios', $id, ['minutos' => $minutos, 'intentos' => $resultado['intentos']], $id);
            return 'Usuario o clave incorrectos. Por seguridad, espere ' . $minutos . ($minutos === 1 ? ' minuto' : ' minutos') . ' antes de volver a intentar.';
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
        'ingreso'          => time(),
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

// ---------------------------------------------------------------------------
// Formularios que crean algo: un identificador de un solo uso evita duplicados
// si el envío llega dos veces (doble toque, reintento con mala señal). La sesión
// atiende las peticiones de a una, así que el segundo envío ve el primero ya hecho.
// ---------------------------------------------------------------------------

function crear_id_formulario(): string
{
    $id = bin2hex(random_bytes(16));
    $_SESSION['formularios'][$id] = null;
    // Solo se guardan los 20 más recientes (pestañas abiertas a la vez).
    $_SESSION['formularios'] = array_slice($_SESSION['formularios'], -20, null, true);
    return $id;
}

/** El identificador enviado si es uno vigente de esta sesión; si no, uno nuevo. */
function reusar_id_formulario(string $enviado): string
{
    return preg_match('/^[0-9a-f]{32}$/', $enviado) && array_key_exists($enviado, $_SESSION['formularios'] ?? [])
        ? $enviado
        : crear_id_formulario();
}

/** Id de lo que ya se creó con ese formulario, o null si todavía no se usó. */
function obtener_creado_con_formulario(string $id): ?int
{
    return $_SESSION['formularios'][$id] ?? null;
}

function marcar_formulario_usado(string $id, int $creado): void
{
    if ($id !== '') {
        $_SESSION['formularios'][$id] = $creado;
    }
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
