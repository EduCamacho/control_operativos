<?php
declare(strict_types=1);

/** @return array Usuario de la sesión; si no hay, redirige al ingreso (o responde 401 en la API). */
function requiere_login(): array
{
    $usuario = usuario_actual();
    if ($usuario === null) {
        if (es_peticion_api()) {
            responder_json(['error' => 'La sesión terminó. Vuelva a ingresar.'], 401);
        }
        redirigir('login');
    }
    return $usuario;
}

function requiere_rol(string ...$roles): array
{
    $usuario = requiere_login();
    if (!in_array($usuario['rol'], $roles, true)) {
        if (es_peticion_api()) {
            responder_json(['error' => 'No tiene permiso para esta acción.'], 403);
        }
        http_response_code(403);
        require RUTA_APP . '/paginas/prohibido.php';
        exit;
    }
    return $usuario;
}

/**
 * Unidad de la cuenta UNIDAD en sesión. Es el único origen válido del
 * unidad_id en las consultas de una unidad (RN-16): nunca se toma del navegador.
 */
function unidad_de_sesion(): int
{
    $usuario = requiere_rol('UNIDAD');
    if ($usuario['unidad_id'] === null) {
        throw new LogicException("La cuenta UNIDAD {$usuario['id']} no tiene unidad asignada (RN-20).");
    }
    return $usuario['unidad_id'];
}

function es_admin(): bool
{
    return (usuario_actual()['rol'] ?? null) === 'ADMIN';
}
