<?php
declare(strict_types=1);

/** Conexión PDO única por petición, con la hora de Lima (docs/03). */
function db(): PDO
{
    static $conexion = null;
    if ($conexion === null) {
        $conexion = new PDO(
            'mysql:host=' . BD_HOST . ';dbname=' . BD_NOMBRE . ';charset=utf8mb4',
            BD_USUARIO,
            BD_CLAVE,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        );
        $conexion->exec("SET time_zone = '-05:00'");
    }
    return $conexion;
}

/** Ejecuta una sentencia preparada y la devuelve para leer sus filas. */
function consultar(string $sql, array $parametros = []): PDOStatement
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($parametros);
    return $sentencia;
}

/** Error de clave única (MySQL 1062), para mostrar "ya existe" en lugar de un error del servidor. */
function es_error_duplicado(PDOException $error): bool
{
    return ($error->errorInfo[1] ?? null) === 1062;
}
