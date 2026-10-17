<?php
/**
 * Pantalla de inicio provisional (fase 0). Cada fase la reemplaza por la
 * pantalla principal de su rol (docs/04).
 */
declare(strict_types=1);

$usuario = requiere_login();
if ($usuario['rol'] === 'UNIDAD') {
    require RUTA_APP . '/paginas/mis_operativos.php';
    return;
}
$ahora = obtener_ahora();
$fecha_servicio = fecha_servicio_vigente($ahora);
$fecha_texto = formatear_fecha_larga(validar_fecha($fecha_servicio));

$pantalla = match ($usuario['rol']) {
    'ADMIN'      => 'un resumen del día. Use el menú para administrar unidades, usuarios, catálogos y la bitácora',
    'SUPERVISOR' => 'el mapa y el tablero de cumplimiento (fase 3)',
};

$titulo = 'Inicio';
require RUTA_APP . '/vistas/cabecera.php';
?>
<h1 class="h4">Bienvenido, <?= e($usuario['nombre']) ?></h1>
<p class="text-body-secondary mb-4">
    Fecha de servicio vigente: <strong><?= e($fecha_texto) ?></strong>
    · Hora del servidor: <?= e($ahora->format('H:i')) ?>
</p>

<div class="alert alert-info">
    <i class="bi bi-cone-striped"></i>
    Esta pantalla todavía está en construcción. Aquí se mostrará <?= e($pantalla) ?>.
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
