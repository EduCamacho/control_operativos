<?php
/**
 * ADMIN: lista de cuentas con su rol, unidad, estado y último acceso (docs/04).
 * La búsqueda y los chips de rol filtran en el navegador, sin recargar.
 * Arriba, las unidades activas con cuentas por crear (hasta MAX_CUENTAS_POR_UNIDAD
 * activas, RN-20), cada una con "Crear cuenta".
 */
declare(strict_types=1);

requiere_rol('ADMIN');

$ahora = obtener_ahora();
$rol_inicial = in_array($_GET['rol'] ?? '', ROLES, true) ? $_GET['rol'] : '';

$usuarios = consultar(
    "SELECT u.id, u.usuario, u.nombre, u.grado, u.cip, u.rol, u.activo, u.bloqueado_hasta, u.ultimo_acceso,
            un.nombre_corto AS unidad
     FROM usuarios u
     LEFT JOIN unidades un ON un.id = u.unidad_id
     ORDER BY FIELD(u.rol, 'ADMIN', 'SUPERVISOR', 'UNIDAD'), un.orden, u.usuario",
)->fetchAll();

$sin_cuenta = consultar(
    'SELECT un.id, un.nombre_corto, COUNT(u.id) AS cuentas
     FROM unidades un LEFT JOIN usuarios u ON u.unidad_id = un.id AND u.activo = 1
     WHERE un.activo = 1
     GROUP BY un.id, un.nombre_corto, un.orden
     HAVING COUNT(u.id) < :maximo
     ORDER BY un.orden',
    ['maximo' => MAX_CUENTAS_POR_UNIDAD],
)->fetchAll();

// Cantidad por rol, para los chips.
$por_rol = array_fill_keys(ROLES, 0);
foreach ($usuarios as $fila) {
    $por_rol[$fila['rol']]++;
}

// Lo que la lista muestra (Alpine lo pinta con x-text, que escapa el texto).
$filas = array_map(static function (array $fila) use ($ahora): array {
    $bloqueado = cuenta_bloqueada($fila['bloqueado_hasta'], $ahora);
    return [
        'id'        => (int) $fila['id'],
        'url'       => armar_url('admin/usuarios/editar', ['id' => $fila['id']]),
        'usuario'   => $fila['usuario'],
        'rol'       => $fila['rol'],
        'etiqueta'  => obtener_etiqueta_rol($fila['rol']),
        'detalle'   => implode(' · ', array_filter([$fila['unidad'], trim(($fila['grado'] ?? '') . ' ' . $fila['nombre'])])),
        'buscar'    => $fila['usuario'] . ' ' . $fila['nombre'] . ' ' . ($fila['cip'] ?? '') . ' ' . ($fila['unidad'] ?? ''),
        'activo'    => (bool) $fila['activo'],
        'bloqueado' => $bloqueado ? 'Bloqueado hasta ' . convertir_a_fecha_hora($fila['bloqueado_hasta'])->format('H:i') : null,
        'acceso'    => formatear_hace($fila['ultimo_acceso'], $ahora),
        'nunca'     => $fila['ultimo_acceso'] === null,
    ];
}, $usuarios);

$titulo = 'Usuarios';
$scripts = ['assets/js/usuarios.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div x-data="listaUsuarios(<?= e(json_encode(['filas' => $filas, 'rol' => $rol_inicial])) ?>)">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Usuarios</h1>
        <a href="<?= e(armar_url('admin/usuarios/editar')) ?>" class="btn btn-institucional boton-accion">
            <i class="bi bi-person-plus"></i> Nuevo usuario
        </a>
    </div>

    <!-- Búsqueda y chips de rol (filtran sin recargar) -->
    <div class="input-group mb-2">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control" placeholder="Buscar usuario, nombre, CIP o unidad" x-model="busqueda" aria-label="Buscar usuario, nombre, CIP o unidad">
    </div>
    <div class="chips mb-3" role="group" aria-label="Filtrar por rol">
        <button type="button" class="chip" :class="rol === '' && 'activo'" @click="rol = ''">Todos <?= count($usuarios) ?></button>
        <?php foreach (['UNIDAD', 'SUPERVISOR', 'ADMIN'] as $opcion): ?>
            <button type="button" class="chip" :class="rol === <?= e(json_encode($opcion)) ?> && 'activo'" @click="rol = <?= e(json_encode($opcion)) ?>">
                <?= e(obtener_etiqueta_rol($opcion)) ?> <?= $por_rol[$opcion] ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if ($sin_cuenta !== []): ?>
        <!-- Unidades activas con cuentas por crear: se despliega la lista, cada una con su botón -->
        <details class="alert alert-warning py-2 unidades-sin-cuenta">
            <summary class="fw-semibold">
                <i class="bi bi-exclamation-triangle"></i>
                <?= count($sin_cuenta) === 1 ? '1 unidad con cuentas por crear' : count($sin_cuenta) . ' unidades con cuentas por crear' ?>
            </summary>
            <ul class="list-unstyled mb-0 mt-2">
                <?php foreach ($sin_cuenta as $unidad): ?>
                    <li class="d-flex align-items-center gap-2 py-1">
                        <span class="me-auto"><?= e($unidad['nombre_corto']) ?>
                            <span class="small text-body-secondary"><?= e($unidad['cuentas']) ?> de <?= MAX_CUENTAS_POR_UNIDAD ?></span></span>
                        <a class="btn btn-sm btn-institucional" href="<?= e(armar_url('admin/usuarios/editar', ['unidad_id' => $unidad['id']])) ?>">
                            Crear cuenta
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <div class="list-group">
        <template x-for="f in visibles" :key="f.id">
            <a :href="f.url" class="list-group-item list-group-item-action fila-flex align-items-center gap-2" :class="!f.activo && 'fila-inactiva'">
                <div class="me-auto">
                    <div class="fila-flex flex-wrap align-items-center gap-2">
                        <span class="fw-semibold" x-text="f.usuario"></span>
                        <span class="badge text-bg-light border fw-normal" x-text="f.etiqueta"></span>
                        <span class="badge text-bg-secondary" x-show="!f.activo">Inactivo</span>
                        <span class="badge text-bg-danger" x-show="f.bloqueado"><i class="bi bi-lock"></i> <span x-text="f.bloqueado"></span></span>
                    </div>
                    <div class="small text-body-secondary">
                        <span x-text="f.detalle"></span> ·
                        <span :class="f.nunca && 'acceso-nunca'" x-text="f.nunca ? 'Nunca ingresó' : f.acceso"></span>
                    </div>
                </div>
                <i class="bi bi-chevron-right text-body-secondary"></i>
            </a>
        </template>
        <div class="list-group-item text-body-secondary" x-show="!visibles.length" x-cloak>Sin resultados.</div>
    </div>
</div>

<?php require RUTA_APP . '/vistas/pie.php'; ?>
