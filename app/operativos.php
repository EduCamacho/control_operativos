<?php
/**
 * Consultas de operativos compartidas por las páginas y la API.
 * Las de una cuenta UNIDAD reciben siempre el unidad_id de unidad_de_sesion() (RN-16).
 */
declare(strict_types=1);

const COLUMNAS_OPERATIVO = 'o.id, o.unidad_id, o.fecha_servicio, o.turno, o.nombre_plan, o.lugar_programado, o.lugar_latitud, o.lugar_longitud,
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

// ---------------------------------------------------------------------------
// Supervisión (SUPERVISOR y ADMIN ven todas las unidades, RN-16)
// ---------------------------------------------------------------------------

/** Operativo de cualquier unidad, para SUPERVISOR y ADMIN. */
function obtener_operativo(int $id): ?array
{
    $operativo = consultar(
        'SELECT ' . COLUMNAS_OPERATIVO . ', un.nombre AS unidad_nombre_completo, un.division
         FROM operativos o JOIN unidades un ON un.id = o.unidad_id
         WHERE o.id = :id',
        ['id' => $id],
    )->fetch();
    return $operativo ?: null;
}

/**
 * Operativos del mapa: los del servicio diario indicado y los de servicios
 * anteriores que siguen en curso (no hay duración máxima), con su última ubicación.
 *
 * @return list<array>
 */
function listar_operativos_para_mapa(string $servicio_diario): array
{
    return consultar(
        'SELECT ' . COLUMNAS_OPERATIVO . ', un.division, un.orden AS unidad_orden,
                ub.latitud, ub.longitud, ub.precision_m, ub.origen, ub.referencia, ub.registrado_en AS ubicacion_en,
                (SELECT COUNT(*) FROM operativo_ubicaciones c WHERE c.operativo_id = o.id) AS puntos,
                (SELECT COUNT(*) FROM controles_inopinados ci WHERE ci.operativo_id = o.id) AS controles
         FROM operativos o
         JOIN unidades un ON un.id = o.unidad_id
         LEFT JOIN operativo_ubicaciones ub ON ub.id = (
             SELECT id FROM operativo_ubicaciones
             WHERE operativo_id = o.id
             ORDER BY registrado_en DESC, id DESC LIMIT 1)
         WHERE o.fecha_servicio = :servicio
            OR (o.fecha_servicio BETWEEN :servicio_desde AND :servicio_anterior AND o.estado = \'EN_CURSO\')
         ORDER BY un.orden, o.inicio_programado',
        [
            'servicio'          => $servicio_diario,
            'servicio_desde'    => validar_fecha($servicio_diario)->modify('-7 days')->format('Y-m-d'),
            'servicio_anterior' => validar_fecha($servicio_diario)->modify('-1 day')->format('Y-m-d'),
        ],
    )->fetchAll();
}

/** @return list<array> Unidades activas en el orden del tablero. */
function listar_unidades_activas(): array
{
    return consultar('SELECT id, division, nombre, nombre_corto, orden FROM unidades WHERE activo = 1 ORDER BY orden, nombre_corto')->fetchAll();
}

/** @return list<array> Operativos de todas las unidades en un servicio diario. */
function listar_operativos_del_servicio(string $servicio_diario): array
{
    return consultar(
        'SELECT ' . COLUMNAS_OPERATIVO . '
         FROM operativos o JOIN unidades un ON un.id = o.unidad_id
         WHERE o.fecha_servicio = :servicio',
        ['servicio' => $servicio_diario],
    )->fetchAll();
}

/** @return list<array> Controles inopinados del operativo, del más reciente al más antiguo. */
function obtener_controles(int $operativo_id): array
{
    return consultar(
        'SELECT ci.id, ci.resultado, ci.observacion, ci.distancia_m, ci.latitud, ci.longitud, ci.origen, ci.registrado_en,
                u.nombre AS supervisor
         FROM controles_inopinados ci JOIN usuarios u ON u.id = ci.usuario_id
         WHERE ci.operativo_id = :id
         ORDER BY ci.registrado_en DESC, ci.id DESC',
        ['id' => $operativo_id],
    )->fetchAll();
}

function contar_controles(int $operativo_id): int
{
    return (int) consultar('SELECT COUNT(*) FROM controles_inopinados WHERE operativo_id = :id', ['id' => $operativo_id])->fetchColumn();
}

// ---------------------------------------------------------------------------
// ADMIN: búsqueda y corrección de operativos
// ---------------------------------------------------------------------------

/**
 * Condiciones SQL de la búsqueda de ADMIN. El estado visible (NO_INICIADO,
 * SIN_CIERRE, PENDIENTE_NOTA) se traduce a condiciones con las tolerancias.
 *
 * @return array{0: string, 1: array<string, mixed>}
 */
function armar_filtro_operativos(array $filtros, DateTimeImmutable $ahora): array
{
    $condiciones = ['o.fecha_servicio BETWEEN :desde AND :hasta'];
    $parametros = ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta']];
    if ($filtros['unidad_id'] !== null) {
        $condiciones[] = 'o.unidad_id = :unidad_id';
        $parametros['unidad_id'] = $filtros['unidad_id'];
    }
    if ($filtros['turno'] !== '') {
        $condiciones[] = 'o.turno = :turno';
        $parametros['turno'] = $filtros['turno'];
    }
    $limite_no_iniciado = $ahora->modify('-' . TOLERANCIA_NO_INICIADO_MIN . ' minutes')->format(FORMATO_FECHA_HORA);
    $limite_sin_cierre = $ahora->modify('-' . TOLERANCIA_SIN_CIERRE_MIN . ' minutes')->format(FORMATO_FECHA_HORA);
    switch ($filtros['estado']) {
        case 'PROGRAMADO':
            $condiciones[] = "o.estado = 'PROGRAMADO' AND o.inicio_programado >= :limite";
            $parametros['limite'] = $limite_no_iniciado;
            break;
        case 'NO_INICIADO':
            $condiciones[] = "o.estado = 'PROGRAMADO' AND o.inicio_programado < :limite";
            $parametros['limite'] = $limite_no_iniciado;
            break;
        case 'EN_CURSO':
            $condiciones[] = "o.estado = 'EN_CURSO' AND o.fin_programado >= :limite";
            $parametros['limite'] = $limite_sin_cierre;
            break;
        case 'SIN_CIERRE':
            $condiciones[] = "o.estado = 'EN_CURSO' AND o.fin_programado < :limite";
            $parametros['limite'] = $limite_sin_cierre;
            break;
        case 'FINALIZADO':
            $condiciones[] = "o.estado = 'FINALIZADO'";
            break;
        case 'PENDIENTE_NOTA':
            $condiciones[] = "o.estado = 'FINALIZADO' AND (o.nota_informativa IS NULL OR o.nota_informativa = '')";
            break;
    }
    return [implode(' AND ', $condiciones), $parametros];
}

/** @return array{total: int, filas: list<array>} */
function buscar_operativos(array $filtros, DateTimeImmutable $ahora, int $limite, int $desplazamiento): array
{
    [$donde, $parametros] = armar_filtro_operativos($filtros, $ahora);
    $total = (int) consultar("SELECT COUNT(*) FROM operativos o WHERE $donde", $parametros)->fetchColumn();
    $filas = consultar(
        'SELECT ' . COLUMNAS_OPERATIVO . "
         FROM operativos o JOIN unidades un ON un.id = o.unidad_id
         WHERE $donde
         ORDER BY o.fecha_servicio DESC, un.orden, o.inicio_programado
         LIMIT $limite OFFSET $desplazamiento",
        $parametros,
    )->fetchAll();
    return ['total' => $total, 'filas' => $filas];
}

/** @return array<int, int> Cantidades del cierre por concepto. */
function obtener_cantidades(int $operativo_id): array
{
    $cantidades = [];
    foreach (consultar('SELECT concepto_resultado_id, cantidad FROM operativo_resultados WHERE operativo_id = :id', ['id' => $operativo_id])->fetchAll() as $fila) {
        $cantidades[(int) $fila['concepto_resultado_id']] = (int) $fila['cantidad'];
    }
    return $cantidades;
}
