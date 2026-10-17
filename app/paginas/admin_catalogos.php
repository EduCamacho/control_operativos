<?php
/**
 * ADMIN: catálogos de tipos de operativo y conceptos de resultado (docs/04).
 * No se eliminan elementos porque los operativos los referencian: se desactivan.
 * La lista va primero; agregar y editar se hacen en un modal (hoja inferior en
 * el celular), sin recargar. Con ?id= o con errores al guardar, el modal se abre solo.
 */
declare(strict_types=1);

requiere_rol('ADMIN');

const CATALOGOS = [
    'tipos' => [
        'tabla'           => 'tipos_operativo',
        'titulo'          => 'Tipos de operativo',
        'singular'        => 'tipo de operativo',
        'largo_nombre'    => 100,
        'con_descripcion' => false,
        'usos'            => 'SELECT tipo_operativo_id, COUNT(*) FROM operativo_tipos GROUP BY tipo_operativo_id',
    ],
    'conceptos' => [
        'tabla'           => 'conceptos_resultado',
        'titulo'          => 'Conceptos de resultado',
        'singular'        => 'concepto de resultado',
        'largo_nombre'    => 120,
        'con_descripcion' => true,
        'usos'            => 'SELECT concepto_resultado_id, COUNT(*) FROM operativo_resultados GROUP BY concepto_resultado_id',
    ],
];

$clave_catalogo = array_key_exists($_GET['catalogo'] ?? '', CATALOGOS) ? $_GET['catalogo'] : 'tipos';
$catalogo = CATALOGOS[$clave_catalogo];
$tabla = $catalogo['tabla']; // Viene de la constante, nunca del navegador.
$columnas = $catalogo['con_descripcion'] ? 'id, nombre, descripcion, orden, activo' : 'id, nombre, orden, activo';

$id = obtener_id_get();
$actual = null;
if ($id !== null) {
    $actual = consultar("SELECT $columnas FROM $tabla WHERE id = :id", ['id' => $id])->fetch();
    if (!$actual) {
        guardar_mensaje('danger', 'El elemento no existe.');
        redirigir('admin/catalogos', ['catalogo' => $clave_catalogo]);
    }
}

$siguiente_orden = (int) consultar("SELECT COALESCE(MAX(orden), 0) + 1 FROM $tabla")->fetchColumn();
$errores = [];
$valores = $actual ?? [
    'nombre'      => '',
    'descripcion' => '',
    'orden'       => $siguiente_orden,
    'activo'      => 1,
];

if (es_post()) {
    verificar_csrf();
    ['datos' => $datos, 'errores' => $errores] = validar_elemento_catalogo($_POST, $catalogo['largo_nombre'], $catalogo['con_descripcion']);
    $valores = array_merge($valores, $_POST, ['activo' => $datos['activo']]);

    if ($errores === []) {
        $campos = array_keys($datos);
        try {
            if ($actual === null) {
                consultar(
                    "INSERT INTO $tabla (" . implode(', ', $campos) . ') VALUES (:' . implode(', :', $campos) . ')',
                    $datos,
                );
                $nuevo_id = (int) db()->lastInsertId();
                registrar_bitacora('CREAR_CATALOGO', $tabla, $nuevo_id, $datos);
                guardar_mensaje('success', "Se agregó «{$datos['nombre']}».");
            } else {
                $cambios = calcular_cambios($actual, $datos);
                if ($cambios !== []) {
                    $asignaciones = implode(', ', array_map(static fn (string $c): string => "$c = :$c", $campos));
                    consultar("UPDATE $tabla SET $asignaciones WHERE id = :id", $datos + ['id' => $id]);
                    registrar_bitacora('EDITAR_CATALOGO', $tabla, $id, $cambios);
                }
                guardar_mensaje('success', "Se guardó «{$datos['nombre']}».");
            }
            redirigir('admin/catalogos', ['catalogo' => $clave_catalogo]);
        } catch (PDOException $error) {
            if (!es_error_duplicado($error)) {
                throw $error;
            }
            $errores['nombre'] = 'Ya existe otro elemento con ese nombre.';
        }
    }
}

$elementos = consultar("SELECT $columnas FROM $tabla ORDER BY activo DESC, orden, nombre")->fetchAll();
// Cuántos operativos usan cada elemento (se muestra en la lista y al editar).
$usos = consultar($catalogo['usos'])->fetchAll(PDO::FETCH_KEY_PAIR);

// Con ?id= o con errores al guardar, el modal se abre solo con esos valores.
$configuracion = [
    'elementos' => array_map(static fn (array $el): array => [
        'id'          => (int) $el['id'],
        'nombre'      => $el['nombre'],
        'descripcion' => $el['descripcion'] ?? '',
        'orden'       => (string) $el['orden'],
        'activo'      => (bool) $el['activo'],
        'usos'        => (int) ($usos[$el['id']] ?? 0),
    ], $elementos),
    'siguienteOrden' => (string) $siguiente_orden,
    'inicial'        => $actual !== null || $errores !== [] ? [
        'id'          => $id,
        'nombre'      => (string) $valores['nombre'],
        'descripcion' => (string) ($valores['descripcion'] ?? ''),
        'orden'       => (string) $valores['orden'],
        'activo'      => (bool) $valores['activo'],
        'usos'        => $id === null ? 0 : (int) ($usos[$id] ?? 0),
    ] : null,
    'conErrores'     => $errores !== [],
    'urlAccion'      => armar_url('admin/catalogos', ['catalogo' => $clave_catalogo]),
];
// Los errores del servidor se marcan solo mientras el modal muestra lo que se intentó guardar.
$error_en = static fn (string $campo): string => isset($errores[$campo]) ? 'true' : 'false';

$titulo = 'Catálogos';
$scripts = ['assets/js/catalogos.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div x-data="catalogoAdmin(<?= e(json_encode($configuracion)) ?>)">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Catálogos</h1>
        <button type="button" class="btn btn-institucional boton-accion" @click="abrir(null)">
            <i class="bi bi-plus-lg"></i> Agregar
        </button>
    </div>

    <ul class="nav nav-tabs mb-3">
        <?php foreach (CATALOGOS as $clave => $opcion): ?>
            <li class="nav-item">
                <a class="nav-link<?= $clave === $clave_catalogo ? ' active' : '' ?>"
                   <?= $clave === $clave_catalogo ? 'aria-current="page"' : '' ?>
                   href="<?= e(armar_url('admin/catalogos', ['catalogo' => $clave])) ?>"><?= e($opcion['titulo']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Lista: cada fila abre el modal para editarla -->
    <div class="list-group">
        <template x-for="el in elementos" :key="el.id">
            <button type="button" class="list-group-item list-group-item-action fila-flex align-items-center gap-2 text-start"
                    :class="!el.activo && 'fila-inactiva'" @click="abrir(el)">
                <span class="orden-unidad" title="Orden" x-text="el.orden"></span>
                <span class="me-auto">
                    <span x-text="el.nombre"></span>
                    <small class="d-block text-body-secondary" x-show="el.descripcion" x-text="el.descripcion"></small>
                </span>
                <span class="badge text-bg-secondary" x-show="!el.activo">Inactivo</span>
                <span class="small text-body-secondary text-nowrap" x-show="el.usos" x-text="el.usos + (el.usos === 1 ? ' operativo' : ' operativos')"></span>
                <i class="bi bi-chevron-right text-body-secondary"></i>
            </button>
        </template>
        <div class="list-group-item text-body-secondary" x-show="!elementos.length" x-cloak>Todavía no hay elementos.</div>
    </div>

    <!-- Agregar o editar: hoja inferior en el celular, centrado en la PC -->
    <div class="modal fade" tabindex="-1" x-ref="modal" aria-labelledby="titulo-catalogo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-hoja-inferior">
            <form method="post" class="modal-content" novalidate :action="urlAccion + (form.id ? '&id=' + form.id : '')">
                <div class="modal-header encabezado-modal">
                    <h2 class="modal-title h5" id="titulo-catalogo"
                        x-text="(form.id ? 'Editar ' : 'Agregar ') + <?= e(json_encode($catalogo['singular'])) ?>"></h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <?= campo_csrf() ?>
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" id="nombre" name="nombre" maxlength="<?= $catalogo['largo_nombre'] ?>" required x-ref="nombre"
                               class="form-control text-uppercase" :class="conErrores && <?= $error_en('nombre') ?> && 'is-invalid'" x-model="form.nombre">
                        <div class="invalid-feedback"><?= e($errores['nombre'] ?? '') ?></div>
                        <div class="form-text text-warning-emphasis" x-show="form.id && form.usos" x-cloak>
                            <i class="bi bi-info-circle"></i> Lo usan <span x-text="form.usos"></span> operativo(s): el cambio de nombre también se verá en ellos.
                        </div>
                    </div>
                    <?php if ($catalogo['con_descripcion']): ?>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción <span class="text-body-secondary">(opcional)</span></label>
                            <input type="text" id="descripcion" name="descripcion" maxlength="255"
                                   class="form-control" :class="conErrores && <?= $error_en('descripcion') ?> && 'is-invalid'" x-model="form.descripcion"
                                   aria-describedby="ayuda-descripcion">
                            <div id="ayuda-descripcion" class="form-text">Ayuda que se muestra bajo el campo al finalizar el operativo.</div>
                            <div class="invalid-feedback"><?= e($errores['descripcion'] ?? '') ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="orden" class="form-label">Orden</label>
                        <input type="number" id="orden" name="orden" min="0" max="65535" step="1" inputmode="numeric" required
                               class="form-control" :class="conErrores && <?= $error_en('orden') ?> && 'is-invalid'" x-model="form.orden">
                        <div class="invalid-feedback"><?= e($errores['orden'] ?? '') ?></div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" x-model="form.activo">
                        <label class="form-check-label" for="activo">Activo</label>
                        <div class="form-text">Los inactivos no aparecen en los formularios nuevos, pero se conservan en los operativos que ya los usan.</div>
                    </div>
                </div>
                <div class="modal-footer barra-modal">
                    <button type="button" class="btn btn-outline-secondary boton-accion" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-institucional boton-accion flex-grow-1"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
