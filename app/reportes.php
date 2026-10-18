<?php
/**
 * Reportes para el SUPERVISOR y ADMIN (docs/04): los datos del reporte del
 * servicio diario y su PDF con Dompdf, incluido en app/lib/dompdf (sin
 * Composer en producción). La matriz se arma con armar_matriz_servicio().
 */
declare(strict_types=1);

/**
 * Ícono de un concepto de resultado como imagen SVG incrustada (data URI), con
 * el color ya fijado. Sirve igual en la pantalla y en el PDF (Dompdf no carga
 * fuentes de íconos). Un ícono desconocido o vacío usa el genérico.
 */
function armar_icono_resultado(?string $icono, string $color): string
{
    $icono = $icono !== null && array_key_exists($icono, ICONOS_RESULTADO) ? $icono : ICONO_RESULTADO_PREDETERMINADO;
    return armar_icono($icono, $color);
}

/**
 * Cualquier ícono de app/iconos/ (sin .svg) como data URI con el color fijado.
 * El nombre lo pone el código, nunca el navegador; igual se valida.
 */
function armar_icono(string $nombre, string $color): string
{
    static $armados = [];
    $archivo = RUTA_APP . "/iconos/$nombre.svg";
    if (!preg_match('/^[a-z0-9-]+$/', $nombre) || !is_file($archivo)) {
        throw new InvalidArgumentException("Ícono desconocido: $nombre");
    }
    $color = preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#000000';
    return $armados["$nombre$color"] ??= 'data:image/svg+xml;base64,' . base64_encode(
        str_replace('currentColor', $color, (string) file_get_contents($archivo)),
    );
}

/**
 * Filtros del reporte desde la URL: fecha de servicio (por defecto, la vigente)
 * y división ('' = todas). Un valor inválido vuelve al predeterminado.
 *
 * @return array{fecha: string, division: string}
 */
function leer_filtros_reporte(array $consulta, DateTimeImmutable $ahora): array
{
    $vigente = fecha_servicio_vigente($ahora);
    try {
        $fecha = validar_fecha((string) ($consulta['fecha'] ?? $vigente))->format('Y-m-d');
    } catch (InvalidArgumentException) {
        $fecha = $vigente;
    }
    $division = (string) ($consulta['division'] ?? '');
    return ['fecha' => $fecha, 'division' => array_key_exists($division, DIVISIONES) ? $division : ''];
}

/**
 * Todo lo del reporte de un servicio diario, filtrado por división ('' = todas).
 * Los estados se calculan con la hora actual: el reporte es una foto de ese momento.
 *
 * @return array<string, mixed>
 */
function armar_reporte_servicio(string $fecha_servicio, string $division, DateTimeImmutable $ahora): array
{
    $unidades = array_values(array_filter(
        listar_unidades_activas(),
        static fn (array $u): bool => $division === '' || $u['division'] === $division,
    ));
    $matriz = armar_matriz_servicio(
        $unidades,
        listar_operativos_del_servicio($fecha_servicio),
        $fecha_servicio,
        $ahora,
        TOLERANCIA_NO_INICIADO_MIN,
        TOLERANCIA_SIN_CIERRE_MIN,
    );

    // Solo unidades activas, como la matriz (y el tablero).
    $filtro = 'o.fecha_servicio = :fecha AND un.activo = 1' . ($division === '' ? '' : ' AND un.division = :division');
    $parametros = ['fecha' => $fecha_servicio] + ($division === '' ? [] : ['division' => $division]);

    // Todos los conceptos activos, también en 0 (se ve qué no se logró), más los
    // inactivos que tengan cantidad ese día.
    $resultados = consultar(
        "SELECT c.nombre, c.descripcion, c.icono, COALESCE(SUM(r.cantidad), 0) AS cantidad
         FROM conceptos_resultado c
         LEFT JOIN (operativo_resultados r
                    JOIN operativos o ON o.id = r.operativo_id
                    JOIN unidades un ON un.id = o.unidad_id)
             ON r.concepto_resultado_id = c.id AND $filtro
         GROUP BY c.id, c.nombre, c.descripcion, c.icono, c.orden, c.activo
         HAVING c.activo = 1 OR SUM(r.cantidad) > 0
         ORDER BY c.orden, c.nombre",
        $parametros,
    )->fetchAll();

    $total_por_operativo = [];
    foreach (consultar(
        "SELECT r.operativo_id, SUM(r.cantidad) AS total
         FROM operativo_resultados r
         JOIN operativos o ON o.id = r.operativo_id
         JOIN unidades un ON un.id = o.unidad_id
         WHERE $filtro
         GROUP BY r.operativo_id",
        $parametros,
    )->fetchAll() as $fila) {
        $total_por_operativo[(int) $fila['operativo_id']] = (int) $fila['total'];
    }

    // Finalizados con resultado positivo (alguna cantidad) o negativo (todo en 0, RN-12).
    $positivos = 0;
    $negativos = 0;
    foreach ($matriz['filas'] as $fila) {
        foreach (TURNOS as $turno) {
            foreach ($fila['turnos'][$turno]['operativos'] as ['operativo' => $operativo]) {
                if ($operativo['estado'] !== 'FINALIZADO') {
                    continue;
                }
                if (($total_por_operativo[(int) $operativo['id']] ?? 0) > 0) {
                    $positivos++;
                } else {
                    $negativos++;
                }
            }
        }
    }

    return [
        'fecha'      => $fecha_servicio,
        'division'   => $division,
        'generado'   => $ahora,
        'unidades'   => count($unidades),
        'matriz'     => $matriz,
        'cifras'     => contar_realizados_por_division($matriz),
        'fuerzas'    => sumar_fuerzas_realizadas($matriz),
        'resultados' => $resultados,
        'positivos'  => $positivos,
        'negativos'  => $negativos,
    ];
}

/**
 * Convierte el HTML en PDF A4 vertical, con "Página N de M" al pie, y lo envía
 * para descargar. Dompdf sin PHP embebido ni archivos remotos: solo lee lo que
 * está dentro de app/ (por ejemplo, el logo).
 */
function enviar_pdf(string $html, string $nombre_archivo): never
{
    require_once RUTA_APP . '/lib/dompdf/autoload.inc.php';

    $opciones = new \Dompdf\Options();
    $opciones->set('isRemoteEnabled', false);
    $opciones->set('isPhpEnabled', false);
    $opciones->set('chroot', RUTA_APP);
    $opciones->set('defaultFont', 'Helvetica');
    // Dompdf usa "screen" por defecto: así no toma los ajustes de pantalla de reporte.css.
    $opciones->set('defaultMediaType', 'print');

    $dompdf = new \Dompdf\Dompdf($opciones);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $lienzo = $dompdf->getCanvas();
    $fuente = $dompdf->getFontMetrics()->getFont('Helvetica');
    $lienzo->page_text($lienzo->get_width() - 110, $lienzo->get_height() - 28, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, [0.4, 0.4, 0.4]);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: no-store');
    echo $dompdf->output();
    exit;
}
