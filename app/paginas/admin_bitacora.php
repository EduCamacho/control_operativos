<?php
/**
 * ADMIN: consulta de la bitácora filtrada por usuario, acción y fecha (docs/04).
 */
declare(strict_types=1);

requiere_rol('ADMIN');

const FILAS_POR_PAGINA = 50;

$hoy = obtener_ahora()->format('Y-m-d');
$fecha_valida = static function (mixed $valor): ?string {
    try {
        return is_string($valor) && $valor !== '' ? validar_fecha($valor)->format('Y-m-d') : null;
    } catch (InvalidArgumentException) {
        return null;
    }
};

$filtros = [
    'usuario_id' => obtener_id_get('usuario_id'),
    'accion'     => preg_match('/^[A-Z_]{1,50}$/', (string) ($_GET['accion'] ?? '')) ? $_GET['accion'] : null,
    'desde'      => $fecha_valida($_GET['desde'] ?? null) ?? obtener_ahora()->modify('-7 days')->format('Y-m-d'),
    'hasta'      => $fecha_valida($_GET['hasta'] ?? null) ?? $hoy,
];
$pagina = validar_entero($_GET['pagina'] ?? 1, 1, PHP_INT_MAX) ?? 1;

$condiciones = ['b.registrado_en >= :desde', 'b.registrado_en < :hasta + INTERVAL 1 DAY'];
$parametros = ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta']];
if ($filtros['usuario_id'] !== null) {
    $condiciones[] = 'b.usuario_id = :usuario_id';
    $parametros['usuario_id'] = $filtros['usuario_id'];
}
if ($filtros['accion'] !== null) {
    $condiciones[] = 'b.accion = :accion';
    $parametros['accion'] = $filtros['accion'];
}
$donde = implode(' AND ', $condiciones);

$total = (int) consultar("SELECT COUNT(*) FROM bitacora b WHERE $donde", $parametros)->fetchColumn();
$paginas = max(1, (int) ceil($total / FILAS_POR_PAGINA));
$pagina = min($pagina, $paginas);
$desplazamiento = ($pagina - 1) * FILAS_POR_PAGINA;

$registros = consultar(
    "SELECT b.id, b.accion, b.entidad, b.entidad_id, b.detalle, b.ip, b.user_agent, b.registrado_en,
            u.usuario, u.rol
     FROM bitacora b
     LEFT JOIN usuarios u ON u.id = b.usuario_id
     WHERE $donde
     ORDER BY b.id DESC
     LIMIT " . FILAS_POR_PAGINA . " OFFSET $desplazamiento",
    $parametros,
)->fetchAll();

$usuarios = consultar('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();
$acciones = consultar('SELECT DISTINCT accion FROM bitacora ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN);

/** Detalle JSON en texto legible: "campo: antes → después". */
$describir_detalle = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '';
    }
    $detalle = json_decode($json, true);
    if (!is_array($detalle)) {
        return $json;
    }
    $partes = [];
    foreach ($detalle as $campo => $valor) {
        if (is_array($valor) && array_key_exists('antes', $valor) && array_key_exists('despues', $valor)) {
            $antes = json_encode($valor['antes'], JSON_UNESCAPED_UNICODE);
            $despues = json_encode($valor['despues'], JSON_UNESCAPED_UNICODE);
            $partes[] = "$campo: $antes → $despues";
        } else {
            $partes[] = "$campo: " . (is_scalar($valor) || $valor === null ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE));
        }
    }
    return implode(' · ', $partes);
};

$url_pagina = static fn (int $numero): string => armar_url('admin/bitacora', array_filter($filtros + ['pagina' => $numero]));

$titulo = 'Bitácora';
require RUTA_APP . '/vistas/cabecera.php';
?>
<h1 class="h4 mb-3">Bitácora</h1>

<form method="get" action="<?= e(armar_url('admin/bitacora')) ?>" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label for="usuario_id" class="form-label small">Usuario</label>
            <select id="usuario_id" name="usuario_id" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($usuarios as $opcion): ?>
                    <option value="<?= e($opcion['id']) ?>"<?= marcar($filtros['usuario_id'] === (int) $opcion['id'], 'selected') ?>><?= e($opcion['usuario']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="accion" class="form-label small">Acción</label>
            <select id="accion" name="accion" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($acciones as $opcion): ?>
                    <option value="<?= e($opcion) ?>"<?= marcar($filtros['accion'] === $opcion, 'selected') ?>><?= e($opcion) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="desde" class="form-label small">Desde</label>
            <input type="date" id="desde" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>" max="<?= e($hoy) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="hasta" class="form-label small">Hasta</label>
            <input type="date" id="hasta" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>" max="<?= e($hoy) ?>">
        </div>
        <div class="col-12 col-md-2 d-grid">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filtrar</button>
        </div>
    </div>
</form>

<p class="small text-body-secondary">
    <?= $total === 1 ? '1 registro' : number_format($total, 0, ',', ' ') . ' registros' ?>
    <?php if ($paginas > 1): ?>· página <?= $pagina ?> de <?= $paginas ?><?php endif; ?>
</p>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle small">
        <thead class="table-light">
            <tr>
                <th scope="col">Fecha y hora</th>
                <th scope="col">Usuario</th>
                <th scope="col">Acción</th>
                <th scope="col">Sobre</th>
                <th scope="col">Detalle</th>
                <th scope="col">IP y dispositivo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $registro): ?>
                <tr>
                    <td class="text-nowrap"><?= e(convertir_a_fecha_hora($registro['registrado_en'])->format('d/m/Y H:i:s')) ?></td>
                    <td class="text-nowrap"><?= e($registro['usuario'] ?? '—') ?></td>
                    <td><span class="badge text-bg-light font-monospace"><?= e($registro['accion']) ?></span></td>
                    <td class="text-nowrap"><?= e($registro['entidad'] === null ? '' : $registro['entidad'] . ' #' . $registro['entidad_id']) ?></td>
                    <td class="text-break"><?= e($describir_detalle($registro['detalle'])) ?></td>
                    <td class="text-body-secondary">
                        <?= e($registro['ip'] ?? '') ?>
                        <div class="text-truncate" style="max-width: 16rem" title="<?= e($registro['user_agent'] ?? '') ?>"><?= e($registro['user_agent'] ?? '') ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($registros === []): ?>
                <tr><td colspan="6" class="text-center text-body-secondary py-4">No hay registros con estos filtros.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($paginas > 1): ?>
    <nav aria-label="Páginas de la bitácora">
        <ul class="pagination pagination-sm flex-wrap">
            <li class="page-item<?= $pagina === 1 ? ' disabled' : '' ?>">
                <a class="page-link" href="<?= e($url_pagina($pagina - 1)) ?>">Anterior</a>
            </li>
            <li class="page-item active" aria-current="page"><span class="page-link"><?= $pagina ?></span></li>
            <li class="page-item<?= $pagina === $paginas ? ' disabled' : '' ?>">
                <a class="page-link" href="<?= e($url_pagina($pagina + 1)) ?>">Siguiente</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
