<?php
/**
 * Genera el INSERT del primer usuario ADMIN, con la clave ya cifrada (docs/03).
 * Se ejecuta en local y el resultado se pega en phpMyAdmin. No se sube al hosting.
 *
 * Uso: php herramientas/crear_admin.php <usuario> <clave> "<nombre completo>"
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo se ejecuta desde la línea de comandos.');
}

[, $usuario, $clave, $nombre] = $argv + [null, '', '', ''];

$errores = [];
if (!preg_match('/^[a-z0-9._-]{3,50}$/i', $usuario)) {
    $errores[] = 'El usuario debe tener de 3 a 50 caracteres: letras sin tilde, números, punto, guion o guion bajo.';
}
if (mb_strlen($clave) < 10) {
    $errores[] = 'La clave debe tener al menos 10 caracteres.';
}
if (trim($nombre) === '' || mb_strlen($nombre) > 150) {
    $errores[] = 'Indique el nombre completo (hasta 150 caracteres).';
}
if ($errores !== []) {
    fwrite(STDERR, "Uso: php herramientas/crear_admin.php <usuario> <clave> \"<nombre completo>\"\n\n");
    fwrite(STDERR, implode("\n", $errores) . "\n");
    exit(1);
}

$escapar = static fn (string $texto): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $texto) . "'";

echo "-- Ejecutar en phpMyAdmin, en la base de datos del sistema:\n";
printf(
    "INSERT INTO usuarios (usuario, password_hash, nombre, rol) VALUES (%s, %s, %s, 'ADMIN');\n",
    $escapar($usuario),
    $escapar(password_hash($clave, PASSWORD_DEFAULT)),
    $escapar(trim($nombre)),
);
fwrite(STDERR, "\nRecuerde borrar este comando del historial de la terminal: contiene la clave.\n");
