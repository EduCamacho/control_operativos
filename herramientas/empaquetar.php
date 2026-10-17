<?php
/**
 * Arma el .zip que se sube al hosting (docs/03, "Despliegue").
 * Se ejecuta en local y no se sube al hosting.
 *
 * Uso: php herramientas/empaquetar.php [--sin-tests]
 *
 * - Incluye solo los archivos versionados en git de app/ y public/: nunca
 *   app/config.php, sesiones ni registros. Tampoco docs/, sql/, tests/,
 *   herramientas/ ni vendor/.
 * - Antes corre los tests de dominio (salvo --sin-tests) y avisa si hay
 *   cambios sin commit, porque esos cambios no entran en el paquete.
 * - Agrega app/version.txt con el commit y la fecha, que se muestran al pie del menú.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo se ejecuta desde la línea de comandos.');
}

$raiz = dirname(__DIR__);
chdir($raiz);
$sin_tests = in_array('--sin-tests', $argv, true);

$ejecutar = static function (string $comando): array {
    exec($comando . ' 2>&1', $salida, $codigo);
    return [$codigo, $salida];
};
$detener = static function (string $mensaje): never {
    fwrite(STDERR, "\n✗ $mensaje\n");
    exit(1);
};

if (!class_exists(ZipArchive::class)) {
    $detener('Falta la extensión zip de PHP (actívela en php.ini con extension=zip).');
}

[$codigo, $salida] = $ejecutar('git rev-parse --short HEAD');
if ($codigo !== 0) {
    $detener('No es un repositorio git o git no está en el PATH.');
}
$commit = trim($salida[0]);

[, $cambios] = $ejecutar('git status --porcelain -- app public');
if ($cambios !== []) {
    echo "⚠ Hay cambios sin commit en app/ o public/. El paquete usa la versión del último commit ($commit):\n";
    foreach (array_slice($cambios, 0, 10) as $linea) {
        echo "    $linea\n";
    }
    echo "\n";
}

if (!$sin_tests) {
    if (!is_file('vendor/bin/phpunit')) {
        $detener('Faltan los tests: ejecute "composer install" o use --sin-tests.');
    }
    echo "Corriendo los tests…\n";
    [$codigo, $salida] = $ejecutar(escapeshellarg(PHP_BINARY) . ' vendor/bin/phpunit --no-progress');
    echo '    ' . (end($salida) ?: '') . "\n";
    if ($codigo !== 0) {
        echo implode("\n", $salida) . "\n";
        $detener('Los tests fallaron: no se armó el paquete.');
    }
}

// Archivos de la versión del último commit (no los de la carpeta de trabajo).
[$codigo, $archivos] = $ejecutar('git ls-tree -r --name-only HEAD -- app public');
if ($codigo !== 0 || $archivos === []) {
    $detener('No se pudo listar los archivos de app/ y public/.');
}
$prohibidos = ['app/config.php'];
foreach ($archivos as $archivo) {
    if (in_array($archivo, $prohibidos, true)) {
        $detener("$archivo está versionado en git: sáquelo del repositorio antes de empaquetar.");
    }
}

$ahora = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
$fecha = $ahora->format('Y-m-d H:i');
$destino = $raiz . '/dist/control_operativos_' . $ahora->format('Ymd_Hi') . "_$commit.zip";
@mkdir(dirname($destino), 0775, true);

// git archive arma el zip con el contenido exacto del commit (finales de línea LF, binarios intactos).
[$codigo, $salida] = $ejecutar('git archive --format=zip -o ' . escapeshellarg($destino) . ' HEAD app public');
if ($codigo !== 0) {
    $detener("git archive falló:\n" . implode("\n", $salida));
}
$zip = new ZipArchive();
if ($zip->open($destino) !== true) {
    $detener("No se pudo abrir $destino para agregar la versión.");
}
$zip->addFromString('app/version.txt', "$commit · $fecha\n");
$zip->close();

$tamano = round(filesize($destino) / 1024);
echo "\n✓ Paquete listo: dist/" . basename($destino) . " ({$tamano} KB, " . (count($archivos) + 1) . " archivos)\n";
echo "  Versión: $commit · $fecha\n\n";
echo "Siguientes pasos (docs/03, \"Despliegue\"):\n";
echo "  1. Aplique en phpMyAdmin los sql/NNN_*.sql nuevos, en orden, ANTES de subir el paquete.\n";
echo "  2. Suba el .zip a la carpeta del sistema en el hosting y extráigalo encima (no toca app/config.php).\n";
echo "  3. Revise que la versión al pie del menú sea $commit.\n";

$sql = glob($raiz . '/sql/*.sql') ?: [];
echo "\nArchivos SQL del repositorio: " . implode(', ', array_map('basename', $sql)) . "\n";
