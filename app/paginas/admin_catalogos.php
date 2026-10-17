<?php
/**
 * ADMIN: catálogos de tipos de operativo y conceptos de resultado.
 * No se eliminan elementos porque los operativos los referencian: se desactivan.
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
        'uso'             => 'SELECT COUNT(*) FROM operativo_tipos WHERE tipo_operativo_id = :id',
    ],
    'conceptos' => [
        'tabla'           => 'conceptos_resultado',
        'titulo'          => 'Conceptos de resultado',
        'singular'        => 'concepto de resultado',
        'largo_nombre'    => 120,
        'con_descripcion' => true,
        'uso'             => 'SELECT COUNT(*) FROM operativo_resultados WHERE concepto_resultado_id = :id',
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

$errores = [];
$valores = $actual ?? [
    'nombre'      => '',
    'descripcion' => '',
    'orden'       => (int) consultar("SELECT COALESCE(MAX(orden), 0) + 1 FROM $tabla")->fetchColumn(),
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
$usos = $actual === null ? 0 : (int) consultar($catalogo['uso'], ['id' => $id])->fetchColumn();

$titulo = 'Catálogos';
require RUTA_APP . '/vistas/cabecera.php';
?>
<h1 class="h4 mb-3">Catálogos</h1>

<ul class="nav nav-tabs mb-3">
    <?php foreach (CATALOGOS as $clave => $opcion): ?>
        <li class="nav-item">
            <a class="nav-link<?= $clave === $clave_catalogo ? ' active' : '' ?>"
               <?= $clave === $clave_catalogo ? 'aria-current="page"' : '' ?>
               href="<?= e(armar_url('admin/catalogos', ['catalogo' => $clave])) ?>"><?= e($opcion['titulo']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="row g-3">
    <div class="col-lg-5 order-lg-2">
        <form method="post" class="card card-body shadow-sm" novalidate
              action="<?= e(armar_url('admin/catalogos', array_filter(['catalogo' => $clave_catalogo, 'id' => $id]))) ?>">
            <h2 class="h6"><?= $actual === null ? 'Agregar ' . e($catalogo['singular']) : 'Editar ' . e($catalogo['singular']) ?></h2>
            <?= campo_csrf() ?>
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="<?= $catalogo['largo_nombre'] ?>" required
                       class="form-control text-uppercase<?= clase_error($errores, 'nombre') ?>" value="<?= e($valores['nombre']) ?>">
                <?= mostrar_error($errores, 'nombre') ?>
                <?php if ($usos > 0): ?>
                    <div class="form-text text-warning-emphasis">
                        <i class="bi bi-info-circle"></i> Lo usan <?= $usos ?> operativo(s): el cambio de nombre también se verá en ellos.
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($catalogo['con_descripcion']): ?>
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción <span class="text-body-secondary">(opcional)</span></label>
                    <input type="text" id="descripcion" name="descripcion" maxlength="255"
                           class="form-control<?= clase_error($errores, 'descripcion') ?>" value="<?= e($valores['descripcion'] ?? '') ?>"
                           aria-describedby="ayuda-descripcion">
                    <div id="ayuda-descripcion" class="form-text">Ayuda que se muestra bajo el campo al finalizar el operativo.</div>
                    <?= mostrar_error($errores, 'descripcion') ?>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label for="orden" class="form-label">Orden</label>
                <input type="number" id="orden" name="orden" min="0" max="65535" step="1" inputmode="numeric" required
                       class="form-control<?= clase_error($errores, 'orden') ?>" value="<?= e($valores['orden']) ?>">
                <?= mostrar_error($errores, 'orden') ?>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1"<?= marcar((bool) $valores['activo']) ?>>
                <label class="form-check-label" for="activo">Activo</label>
                <div class="form-text">Los inactivos no aparecen en los formularios nuevos, pero se conservan en los operativos que ya los usan.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-success boton-accion px-4"><i class="bi bi-check-lg"></i> Guardar</button>
                <?php if ($actual !== null): ?>
                    <a href="<?= e(armar_url('admin/catalogos', ['catalogo' => $clave_catalogo])) ?>" class="btn btn-outline-secondary boton-accion px-4">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="col-lg-7 order-lg-1">
        <div class="list-group">
            <?php foreach ($elementos as $elemento): ?>
                <a href="<?= e(armar_url('admin/catalogos', ['catalogo' => $clave_catalogo, 'id' => $elemento['id']])) ?>"
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2<?= (int) $elemento['id'] === $id ? ' active' : '' ?><?= $elemento['activo'] ? '' : ' list-group-item-light' ?>">
                    <span class="badge text-bg-light font-monospace" title="Orden"><?= e($elemento['orden']) ?></span>
                    <span class="me-auto">
                        <?= e($elemento['nombre']) ?>
                        <?php if (!empty($elemento['descripcion'])): ?>
                            <small class="d-block opacity-75"><?= e($elemento['descripcion']) ?></small>
                        <?php endif; ?>
                    </span>
                    <?php if (!$elemento['activo']): ?><span class="badge text-bg-secondary">Inactivo</span><?php endif; ?>
                    <i class="bi bi-pencil"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
