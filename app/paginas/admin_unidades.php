<?php
/**
 * ADMIN: lista de unidades por división (docs/04). La búsqueda y los chips de
 * división filtran en el navegador, sin recargar. Cada fila abre el formulario
 * (donde también se activa o desactiva); las unidades sin cuenta tienen
 * "Crear cuenta", que abre el formulario de usuario con la unidad ya elegida.
 */
declare(strict_types=1);

requiere_rol('ADMIN');

$unidades = consultar(
    'SELECT un.id, un.division, un.nombre, un.nombre_corto, un.orden, un.activo, us.usuario
     FROM unidades un
     LEFT JOIN usuarios us ON us.unidad_id = un.id
     ORDER BY un.orden, un.nombre_corto',
)->fetchAll();

// Una sección por división, en el orden de DIVISIONES (Alpine las pinta con x-text, que escapa el texto).
$secciones = [];
foreach (DIVISIONES as $clave => $nombre) {
    $secciones[$clave] = ['clave' => $clave, 'nombre' => $nombre, 'unidades' => []];
}
foreach ($unidades as $unidad) {
    $secciones[$unidad['division']]['unidades'][] = [
        'id'         => (int) $unidad['id'],
        'url'        => armar_url('admin/unidades/editar', ['id' => $unidad['id']]),
        'orden'      => (int) $unidad['orden'],
        'corto'      => $unidad['nombre_corto'],
        'nombre'     => $unidad['nombre'],
        'usuario'    => $unidad['usuario'],
        'url_cuenta' => $unidad['usuario'] === null ? armar_url('admin/usuarios/editar', ['unidad_id' => $unidad['id']]) : null,
        'activo'     => (bool) $unidad['activo'],
        'buscar'     => $unidad['nombre_corto'] . ' ' . $unidad['nombre'] . ' ' . ($unidad['usuario'] ?? ''),
    ];
}
$secciones = array_values($secciones);

$titulo = 'Unidades';
$scripts = ['assets/js/unidades.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div x-data="listaUnidades(<?= e(json_encode(['secciones' => $secciones])) ?>)">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Unidades</h1>
        <a href="<?= e(armar_url('admin/unidades/editar')) ?>" class="btn btn-institucional boton-accion">
            <i class="bi bi-plus-lg"></i> Nueva unidad
        </a>
    </div>

    <!-- Búsqueda y chips de división (filtran sin recargar) -->
    <div class="input-group mb-2">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control" placeholder="Buscar unidad o usuario" x-model="busqueda" aria-label="Buscar unidad o usuario">
    </div>
    <div class="chips mb-3" role="group" aria-label="Filtrar por división">
        <button type="button" class="chip" :class="division === '' && 'activo'" @click="division = ''">Todas <?= count($unidades) ?></button>
        <?php foreach ($secciones as $seccion): ?>
            <button type="button" class="chip" :class="division === <?= e(json_encode($seccion['clave'])) ?> && 'activo'"
                    @click="division = <?= e(json_encode($seccion['clave'])) ?>">
                <?= e($seccion['clave']) ?> <?= count($seccion['unidades']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <template x-for="s in visibles" :key="s.clave">
        <section class="mb-3">
            <h2 class="titulo-seccion" x-text="s.nombre + ' · ' + s.unidades.length"></h2>
            <div class="list-group">
                <template x-for="u in s.unidades" :key="u.id">
                    <div class="list-group-item fila-flex align-items-center gap-2" :class="!u.activo && 'fila-inactiva'">
                        <span class="orden-unidad" title="Orden" x-text="u.orden"></span>
                        <a :href="u.url" class="enlace-fila me-auto">
                            <div class="fila-flex flex-wrap align-items-center gap-2">
                                <span class="fw-semibold" x-text="u.corto"></span>
                                <span class="badge text-bg-secondary" x-show="!u.activo">Inactiva</span>
                            </div>
                            <div class="small text-body-secondary">
                                <span x-text="u.nombre"></span> ·
                                <span x-show="u.usuario"><i class="bi bi-person"></i> <span x-text="u.usuario"></span></span>
                                <span x-show="!u.usuario" class="acceso-nunca"><i class="bi bi-person-x"></i> Sin cuenta</span>
                            </div>
                        </a>
                        <a :href="u.url_cuenta" class="btn btn-sm btn-institucional text-nowrap" x-show="u.url_cuenta">Crear cuenta</a>
                        <a :href="u.url" class="text-body-secondary" aria-hidden="true" tabindex="-1"><i class="bi bi-chevron-right"></i></a>
                    </div>
                </template>
            </div>
        </section>
    </template>
    <p class="text-body-secondary" x-show="!visibles.length" x-cloak>Sin resultados.</p>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
