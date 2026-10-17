<?php
/**
 * Ayudas para pintar formularios Bootstrap con sus errores de validación.
 */
declare(strict_types=1);

/** ' is-invalid' si el campo tiene error. */
function clase_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}

function mostrar_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<div class="invalid-feedback">' . e($errores[$campo]) . '</div>' : '';
}

/** Atributo checked o selected según la condición. */
function marcar(bool $condicion, string $atributo = 'checked'): string
{
    return $condicion ? " $atributo" : '';
}

/** Entero positivo de la URL (por ejemplo ?id=5), o null. */
function obtener_id_get(string $parametro = 'id'): ?int
{
    return validar_entero($_GET[$parametro] ?? null, 1, PHP_INT_MAX);
}

function obtener_id_post(string $parametro = 'id'): ?int
{
    return validar_entero($_POST[$parametro] ?? null, 1, PHP_INT_MAX);
}

/** 'hace 5 min', 'hace 3 h' o la fecha, para la última actividad. */
function formatear_hace(?string $fecha_hora, DateTimeImmutable $ahora): string
{
    if ($fecha_hora === null) {
        return 'Nunca';
    }
    $momento = convertir_a_fecha_hora($fecha_hora);
    $segundos = $ahora->getTimestamp() - $momento->getTimestamp();
    return match (true) {
        $segundos < 60        => 'hace un momento',
        $segundos < 3600      => 'hace ' . intdiv($segundos, 60) . ' min',
        $segundos < 86400     => 'hace ' . intdiv($segundos, 3600) . ' h',
        default               => $momento->format('d/m/Y H:i'),
    };
}
