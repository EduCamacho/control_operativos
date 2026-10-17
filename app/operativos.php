<?php
/**
 * Consultas de operativos compartidas por las páginas y la API.
 * Las de una cuenta UNIDAD reciben siempre el unidad_id de unidad_de_sesion() (RN-16).
 */
declare(strict_types=1);

const COLUMNAS_OPERATIVO = 'o.id, o.unidad_id, o.fecha_servicio, o.turno, o.nombre_plan, o.lugar_programado,
    o.inicio_programado, o.fin_programado, o.jefe_grado, o.jefe_nombres, o.jefe_celular,
    o.fuerzas_propias, o.fuerzas_apoyo, o.unidades_moviles, o.estado, o.inicio_real, o.fin_real,
    o.nota_informativa, o.observaciones, o.creado_en, o.actualizado_en,
    un.nombre_corto AS unidad_nombre';

/**
 * Operativo de la unidad, o null si no existe o es de otra unidad.
 * Con FOR UPDATE bloquea la fila hasta el fin de la transacción.
 */
function obtener_operativo_de_unidad(int $id, int $unidad_id, bool $para_actualizar = false): ?array
{
    $operativo = consultar(
        'SELECT ' . COLUMNAS_OPERATIVO . '
         FROM operativos o JOIN unidades un ON un.id = o.unidad_id
         WHERE o.id = :id AND o.unidad_id = :unidad_id'
        . ($para_actualizar ? ' FOR UPDATE' : ''),
        ['id' => $id, 'unidad_id' => $unidad_id],
    )->fetch();
    return $operativo ?: null;
}

/** @return list<array> Operativos de la unidad entre dos fechas de servicio, ordenados por inicio. */
function listar_operativos_de_unidad(int $unidad_id, string $desde, string $hasta, int $limite = 200): array
{
    return consultar(
        'SELECT ' . COLUMNAS_OPERATIVO . '
         FROM operativos o JOIN unidades un ON un.id = o.unidad_id
         WHERE o.unidad_id = :unidad_id AND o.fecha_servicio BETWEEN :desde AND :hasta
         ORDER BY o.inicio_programado
         LIMIT ' . $limite,
        ['unidad_id' => $unidad_id, 'desde' => $desde, 'hasta' => $hasta],
    )->fetchAll();
}

/** @return list<array{id: int, nombre: string}> */
function obtener_tipos_de_operativo(int $operativo_id): array
{
    return consultar(
        'SELECT t.id, t.nombre
         FROM operativo_tipos ot JOIN tipos_operativo t ON t.id = ot.tipo_operativo_id
         WHERE ot.operativo_id = :id
         ORDER BY t.orden, t.nombre',
        ['id' => $operativo_id],
    )->fetchAll();
}

/** @return list<array> Recorrido del operativo, del primer al último punto. */
function obtener_ubicaciones(int $operativo_id): array
{
    return consultar(
        'SELECT id, latitud, longitud, precision_m, origen, referencia, registrado_en
         FROM operativo_ubicaciones
         WHERE operativo_id = :id
         ORDER BY registrado_en, id',
        ['id' => $operativo_id],
    )->fetchAll();
}

/** @return list<array{nombre: string, descripcion: string|null, cantidad: int}> */
function obtener_resultados(int $operativo_id): array
{
    return consultar(
        'SELECT c.nombre, c.descripcion, r.cantidad
         FROM operativo_resultados r JOIN conceptos_resultado c ON c.id = r.concepto_resultado_id
         WHERE r.operativo_id = :id
         ORDER BY c.orden, c.nombre',
        ['id' => $operativo_id],
    )->fetchAll();
}

/**
 * Tipos para el formulario: los activos más los ya marcados en el operativo,
 * aunque después se hayan desactivado.
 *
 * @param list<int> $marcados
 * @return list<array{id: int, nombre: string, activo: int}>
 */
function obtener_tipos_para_formulario(array $marcados = []): array
{
    $tipos = consultar('SELECT id, nombre, activo FROM tipos_operativo ORDER BY orden, nombre')->fetchAll();
    return array_values(array_filter(
        $tipos,
        static fn (array $tipo): bool => $tipo['activo'] || in_array((int) $tipo['id'], $marcados, true),
    ));
}

/** @return list<array{id: int, nombre: string, descripcion: string|null}> */
function obtener_conceptos_activos(): array
{
    return consultar('SELECT id, nombre, descripcion FROM conceptos_resultado WHERE activo = 1 ORDER BY orden, nombre')->fetchAll();
}

/** Estado visible con las tolerancias de app/config.php (RN-14). */
function calcular_estado_visible(array $operativo, DateTimeImmutable $ahora): string
{
    return estado_visible($operativo, $ahora, TOLERANCIA_NO_INICIADO_MIN, TOLERANCIA_SIN_CIERRE_MIN);
}

/** Badge del estado visible, con el distintivo "Sin nota" sobre FINALIZADO (docs/04). */
function mostrar_estado(string $estado_visible, bool $pendiente_nota = false): string
{
    $html = '<span class="badge ' . e(obtener_clase_estado($estado_visible)) . '">' . e(obtener_etiqueta_estado($estado_visible)) . '</span>';
    if ($pendiente_nota) {
        $html .= ' <span class="badge text-bg-light border border-primary text-primary"><i class="bi bi-file-earmark-x"></i> Sin nota</span>';
    }
    return $html;
}

/** "Av. España · 16:52 · precisión 8 m" con enlace a Google Maps (RN-21). */
function mostrar_ubicacion(array $ubicacion, bool $con_referencia = true): string
{
    $partes = [];
    if ($con_referencia) {
        $partes[] = e($ubicacion['referencia']);
    }
    $partes[] = e(formatear_hora($ubicacion['registrado_en']));
    $partes[] = $ubicacion['origen'] === 'MANUAL'
        ? '<span class="badge text-bg-warning">Manual</span>'
        : 'precisión ' . e($ubicacion['precision_m'] ?? '?') . ' m';
    $enlace = armar_enlace_google_maps((float) $ubicacion['latitud'], (float) $ubicacion['longitud']);
    return implode(' · ', $partes)
        . ' <a href="' . e($enlace) . '" target="_blank" rel="noopener" class="text-nowrap">'
        . '<i class="bi bi-geo-alt"></i> Ver en Google Maps</a>';
}
