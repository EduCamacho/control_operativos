<?php
/**
 * Lógica de negocio pura: sin base de datos, sesión ni configuración.
 * Cada función tiene sus tests en tests/DominioTest.php.
 * Las reglas RN-xx están en docs/01-reglas-de-negocio.md.
 */
declare(strict_types=1);

const ZONA_HORARIA = 'America/Lima';
const FORMATO_FECHA_HORA = 'Y-m-d H:i:s';

const ESTADOS = ['PROGRAMADO', 'EN_CURSO', 'FINALIZADO'];
const TURNOS = ['MANANA', 'TARDE', 'NOCHE', 'MADRUGADA'];
const ETIQUETAS_TURNO = [
    'MANANA'    => 'MAÑANA',
    'TARDE'     => 'TARDE',
    'NOCHE'     => 'NOCHE',
    'MADRUGADA' => 'MADRUGADA',
];

// El servicio diario va de las 06:00 a las 05:59 del día siguiente.
const HORA_INICIO_SERVICIO_DIARIO = 6;

const GRADOS = [
    'ALFZ', 'TNTE', 'CAP', 'MAY', 'CMDTE', 'CRNL', 'GRAL',
    'S3', 'S2', 'S1', 'ST3', 'ST2', 'ST1', 'SB', 'SS',
];

// Rectángulo de La Libertad (RN-08).
const LATITUD_MIN = -9.00;
const LATITUD_MAX = -6.90;
const LONGITUD_MIN = -79.80;
const LONGITUD_MAX = -76.80;

const PRECISION_ADVERTENCIA_M = 100;
const MAX_INTENTOS_FALLIDOS = 5;
const MINUTOS_BLOQUEO = 15;
const MAX_FUERZAS = 9999;
const MAX_CANTIDAD_RESULTADO = 9999;
const MAX_DIGITOS_NOTA = 20;

// Estados visibles: etiqueta y clase de color (docs/04). SIN_CIERRE usa el naranja propio.
const ETIQUETAS_ESTADO = [
    'SIN_PROGRAMAR' => 'Sin programar',
    'PROGRAMADO'    => 'Programado',
    'EN_CURSO'      => 'En curso',
    'NO_INICIADO'   => 'No iniciado',
    'SIN_CIERRE'    => 'Sin cierre',
    'FINALIZADO'    => 'Finalizado',
];
const CLASES_ESTADO = [
    'SIN_PROGRAMAR' => 'text-bg-warning',
    'PROGRAMADO'    => 'text-bg-secondary',
    'EN_CURSO'      => 'text-bg-success',
    'NO_INICIADO'   => 'text-bg-danger',
    'SIN_CIERRE'    => 'bg-naranja',
    'FINALIZADO'    => 'text-bg-primary',
];

// Rango de la hora de inicio de cada turno, en minutos desde la medianoche (RN-02).
const RANGOS_TURNO = [
    'MANANA'    => [6 * 60, 12 * 60 - 1],
    'TARDE'     => [12 * 60, 18 * 60 - 1],
    'NOCHE'     => [18 * 60, 24 * 60 - 1],
    'MADRUGADA' => [0, 6 * 60 - 1],
];
const ORIGENES_UBICACION = ['GPS', 'MANUAL'];

// Resultado del control inopinado (RN-15).
const RESULTADOS_CONTROL = [
    'CONFORME'          => 'Conforme',
    'CON_OBSERVACIONES' => 'Con observaciones',
    'NO_ENCONTRADO'     => 'No encontrado',
];
const MAX_PRECISION_GUARDADA_M = 65535;

const ROLES = ['ADMIN', 'SUPERVISOR', 'UNIDAD'];
const DIVISIONES = [
    'DIVOPUS'  => 'DIVOPUS Trujillo',
    'DUE'      => 'DUE',
    'DIVINCRI' => 'DIVINCRI La Libertad',
];
const LARGO_MINIMO_CLAVE = 10;
const LARGO_CLAVE_TEMPORAL = 12;
// Sin caracteres que se confunden al dictarlos o leerlos en el celular (0/o, 1/l/i).
const ALFABETO_CLAVE_TEMPORAL = 'abcdefghjkmnpqrstuvwxyz23456789';

const CAMPOS_HORARIO = ['fecha_servicio', 'hora_inicio', 'hora_fin'];
const CAMPOS_DATOS = [
    'tipos', 'lugar_programado', 'lugar_coordenadas',
    'jefe_grado', 'jefe_nombres', 'jefe_celular',
    'fuerzas_propias', 'fuerzas_apoyo', 'unidades_moviles',
    'observaciones',
];
const CAMPOS_EN_CURSO = [
    'jefe_grado', 'jefe_nombres', 'jefe_celular',
    'fuerzas_propias', 'fuerzas_apoyo', 'unidades_moviles',
    'observaciones',
];

// ---------------------------------------------------------------------------
// Fechas, horas y turnos
// ---------------------------------------------------------------------------

function obtener_zona_horaria(): DateTimeZone
{
    static $zona = null;
    return $zona ??= new DateTimeZone(ZONA_HORARIA);
}

/** Convierte un DATETIME de la base de datos ('Y-m-d H:i:s') o un objeto fecha a la hora de Lima. */
function convertir_a_fecha_hora(string|DateTimeInterface $valor): DateTimeImmutable
{
    if ($valor instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($valor)->setTimezone(obtener_zona_horaria());
    }
    $fecha = DateTimeImmutable::createFromFormat('!' . FORMATO_FECHA_HORA, $valor, obtener_zona_horaria());
    if ($fecha === false || $fecha->format(FORMATO_FECHA_HORA) !== $valor) {
        throw new InvalidArgumentException("Fecha y hora inválida: $valor");
    }
    return $fecha;
}

/** Valida una fecha 'Y-m-d' (incluye días que no existen, como el 30 de febrero). */
function validar_fecha(string $fecha): DateTimeImmutable
{
    $resultado = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, obtener_zona_horaria());
    if ($resultado === false || $resultado->format('Y-m-d') !== $fecha) {
        throw new InvalidArgumentException("Fecha inválida: $fecha");
    }
    return $resultado;
}

/** 'HH:MM' en formato de 24 horas → minutos desde la medianoche. */
function convertir_hora_a_minutos(string $hora): int
{
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora, $partes)) {
        throw new InvalidArgumentException("Hora inválida (use HH:MM de 24 horas): $hora");
    }
    return (int) $partes[1] * 60 + (int) $partes[2];
}

/** Turno según la hora de inicio programada (RN-02). */
function clasificar_turno(string $hora_inicio): string
{
    $minutos = convertir_hora_a_minutos($hora_inicio);
    return match (true) {
        $minutos < 6 * 60  => 'MADRUGADA',
        $minutos < 12 * 60 => 'MANANA',
        $minutos < 18 * 60 => 'TARDE',
        default            => 'NOCHE',
    };
}

function obtener_etiqueta_turno(string $turno): string
{
    return ETIQUETAS_TURNO[$turno] ?? throw new InvalidArgumentException("Turno desconocido: $turno");
}

/**
 * Arma el turno y las fechas y horas completas de un operativo (RN-02).
 * La MADRUGADA empieza el día siguiente a la fecha de servicio, y el fin cae
 * al día siguiente del inicio si su hora es menor que la de inicio.
 *
 * @return array{turno: string, inicio: DateTimeImmutable, fin: DateTimeImmutable}
 */
function calcular_horario_turno(string $fecha_servicio, string $hora_inicio, string $hora_fin): array
{
    $dia = validar_fecha($fecha_servicio);
    $minutos_inicio = convertir_hora_a_minutos($hora_inicio);
    $minutos_fin = convertir_hora_a_minutos($hora_fin);
    if ($minutos_inicio === $minutos_fin) {
        throw new InvalidArgumentException('La hora de fin debe ser distinta de la hora de inicio.');
    }

    $turno = clasificar_turno($hora_inicio);
    $dia_inicio = $turno === 'MADRUGADA' ? $dia->modify('+1 day') : $dia;
    $inicio = $dia_inicio->setTime(intdiv($minutos_inicio, 60), $minutos_inicio % 60);
    $fin = $dia_inicio->setTime(intdiv($minutos_fin, 60), $minutos_fin % 60);
    if ($fin <= $inicio) {
        $fin = $fin->modify('+1 day');
    }

    return ['turno' => $turno, 'inicio' => $inicio, 'fin' => $fin];
}

/**
 * Primera y última hora de inicio posibles de un turno dentro de un servicio diario.
 * Sirve para saber si todavía se puede programar ese turno (RN-03) y para sugerir la hora.
 *
 * @return array{desde: DateTimeImmutable, hasta: DateTimeImmutable}
 */
function calcular_rango_turno(string $fecha_servicio, string $turno): array
{
    if (!isset(RANGOS_TURNO[$turno])) {
        throw new InvalidArgumentException("Turno desconocido: $turno");
    }
    $dia = validar_fecha($fecha_servicio);
    if ($turno === 'MADRUGADA') {
        $dia = $dia->modify('+1 day');
    }
    [$desde, $hasta] = RANGOS_TURNO[$turno];
    return [
        'desde' => $dia->setTime(intdiv($desde, 60), $desde % 60),
        'hasta' => $dia->setTime(intdiv($hasta, 60), $hasta % 60),
    ];
}

/** 'HH:MM' de una fecha y hora. */
function formatear_hora(string|DateTimeInterface $fecha_hora): string
{
    return convertir_a_fecha_hora($fecha_hora)->format('H:i');
}

/** Servicio diario vigente: hoy desde las 06:00, ayer entre las 00:00 y las 05:59. */
function fecha_servicio_vigente(DateTimeImmutable $ahora): string
{
    $local = $ahora->setTimezone(obtener_zona_horaria());
    if ((int) $local->format('G') < HORA_INICIO_SERVICIO_DIARIO) {
        $local = $local->modify('-1 day');
    }
    return $local->format('Y-m-d');
}

/** 'sábado 17 de octubre de 2026'. Sin la extensión intl, que no siempre está en el hosting. */
function formatear_fecha_larga(DateTimeInterface $fecha): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return sprintf(
        '%s %d de %s de %s',
        $dias[(int) $fecha->format('w')],
        (int) $fecha->format('j'),
        $meses[(int) $fecha->format('n') - 1],
        $fecha->format('Y'),
    );
}

/** La unidad solo programa con la hora de inicio en el futuro; ADMIN puede hacerlo con la hora pasada (RN-03). */
function programacion_permitida(DateTimeImmutable $inicio, DateTimeImmutable $ahora, bool $es_admin): bool
{
    return $es_admin || $inicio > $ahora;
}

// ---------------------------------------------------------------------------
// Ubicaciones
// ---------------------------------------------------------------------------

/** RN-08: el punto debe caer dentro del rectángulo de La Libertad (bordes incluidos). */
function dentro_de_la_libertad(float $latitud, float $longitud): bool
{
    if (!is_finite($latitud) || !is_finite($longitud)) {
        return false;
    }
    return $latitud >= LATITUD_MIN && $latitud <= LATITUD_MAX
        && $longitud >= LONGITUD_MIN && $longitud <= LONGITUD_MAX;
}

/** Con precisión peor que 100 m se advierte y se ofrece el ingreso manual (RN-08, RN-09). */
function precision_aceptable(?float $precision_m): bool
{
    return $precision_m !== null && $precision_m >= 0 && $precision_m <= PRECISION_ADVERTENCIA_M;
}

/**
 * Separa en [latitud, longitud] las coordenadas copiadas de Google Maps (RN-09).
 * Acepta decimal ("-8.1116, -79.0287", también con coma decimal), URLs de Google Maps
 * y grados, minutos y segundos (8°06'41.8"S 79°01'43.3"W).
 * No corrige signos: eso lo rechaza después dentro_de_la_libertad().
 *
 * @return array{0: float, 1: float}|null  null si no reconoce el texto.
 */
function parsear_coordenadas(string $texto): ?array
{
    $texto = trim(rawurldecode($texto));
    if ($texto === '') {
        return null;
    }

    // URL de un lugar: !3d<lat>!4d<lng> es la posición exacta del lugar (más precisa que la @ del encuadre).
    if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $texto, $m)) {
        return armar_coordenadas((float) $m[1], (float) $m[2]);
    }

    // URL con @lat,lng,zoom, ?q=lat,lng, query=, destination=, ll= o /search/lat,lng.
    if (preg_match('#(?:@|=|/)(-?\d{1,3}\.\d+),[\s+]*(-?\d{1,3}\.\d+)#', $texto, $m)) {
        return armar_coordenadas((float) $m[1], (float) $m[2]);
    }

    // Grados, minutos y segundos con hemisferio.
    $patron_dms = '(\d{1,3})\s*°\s*(\d{1,2})\s*[\'′’]\s*(\d{1,2}(?:[.,]\d+)?)\s*(?:"|″|”|\'\')?\s*';
    if (preg_match('/^' . $patron_dms . '([NS])[\s,;]*' . $patron_dms . '([EWO])$/iu', $texto, $m)) {
        $latitud = convertir_dms_a_decimal($m[1], $m[2], $m[3], $m[4]);
        $longitud = convertir_dms_a_decimal($m[5], $m[6], $m[7], $m[8]);
        if ($latitud === null || $longitud === null) {
            return null;
        }
        return armar_coordenadas($latitud, $longitud);
    }

    // Par decimal, con punto o coma decimal, separado por coma, punto y coma o espacio.
    $numero = '(-?\d{1,3}(?:[.,]\d+)?)';
    if (preg_match('/^\(?\s*' . $numero . '\s*(?:[,;]\s*|\s+)' . $numero . '\s*\)?$/', $texto, $m)) {
        return armar_coordenadas(
            (float) str_replace(',', '.', $m[1]),
            (float) str_replace(',', '.', $m[2]),
        );
    }

    return null;
}

function convertir_dms_a_decimal(string $grados, string $minutos, string $segundos, string $hemisferio): ?float
{
    $min = (int) $minutos;
    $seg = (float) str_replace(',', '.', $segundos);
    if ($min >= 60 || $seg >= 60) {
        return null;
    }
    $decimal = (int) $grados + $min / 60 + $seg / 3600;
    return in_array(strtoupper($hemisferio), ['S', 'W', 'O'], true) ? -$decimal : $decimal;
}

/** @return array{0: float, 1: float}|null */
function armar_coordenadas(float $latitud, float $longitud): ?array
{
    if (abs($latitud) > 90 || abs($longitud) > 180) {
        return null;
    }
    return [round($latitud, 7), round($longitud, 7)];
}

/**
 * Valida una ubicación que llega del navegador (RN-08 a RN-10).
 * Solo se aceptan latitud, longitud, precisión, origen, referencia e id_envio:
 * la hora la pone el servidor (RN-11).
 *
 * @param bool $exigir_la_libertad false para la ubicación del supervisor (RN-15).
 * @return array{datos: array<string, mixed>, errores: array<string, string>}
 */
function validar_ubicacion(array $entrada, bool $exigir_la_libertad, bool $con_referencia): array
{
    $datos = [];
    $errores = [];

    $latitud = filter_var($entrada['latitud'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitud = filter_var($entrada['longitud'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($latitud === false || $longitud === false || abs($latitud) > 90 || abs($longitud) > 180) {
        $errores['ubicacion'] = 'La ubicación no es válida.';
    } elseif ($exigir_la_libertad && !dentro_de_la_libertad($latitud, $longitud)) {
        $errores['ubicacion'] = 'La ubicación está fuera de La Libertad. Revise las coordenadas.';
    } else {
        $datos['latitud'] = round($latitud, 7);
        $datos['longitud'] = round($longitud, 7);
    }

    $origen = $entrada['origen'] ?? null;
    if (!in_array($origen, ORIGENES_UBICACION, true)) {
        $errores['origen'] = 'Falta el origen de la ubicación.';
    } else {
        $datos['origen'] = $origen;
    }

    // Las ubicaciones manuales no tienen precisión (RN-09).
    $precision = $entrada['precision_m'] ?? null;
    if ($origen === 'MANUAL' || $precision === null || $precision === '') {
        $datos['precision_m'] = null;
    } else {
        $precision = filter_var($precision, FILTER_VALIDATE_FLOAT);
        if ($precision === false || $precision < 0) {
            $errores['precision_m'] = 'La precisión no es válida.';
        } else {
            $datos['precision_m'] = (int) min(MAX_PRECISION_GUARDADA_M, round($precision));
        }
    }

    if ($con_referencia) {
        $referencia = trim((string) ($entrada['referencia'] ?? ''));
        if ($referencia === '') {
            $errores['referencia'] = 'Indique la referencia: calle, avenida o sector.';
        } elseif (mb_strlen($referencia) > 255) {
            $errores['referencia'] = 'La referencia admite hasta 255 caracteres.';
        } else {
            $datos['referencia'] = $referencia;
        }
    }

    $id_envio = $entrada['id_envio'] ?? null;
    if (!is_string($id_envio) || !preg_match('/^[A-Za-z0-9-]{8,36}$/', $id_envio)) {
        $errores['id_envio'] = 'Falta el identificador del envío.';
    } else {
        $datos['id_envio'] = $id_envio;
    }

    return ['datos' => $datos, 'errores' => $errores];
}

/** Distancia en metros con la fórmula de Haversine (RN-15). */
function distancia_m(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $radio_tierra_m = 6371000;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    $a = sin($d_lat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($d_lng / 2) ** 2;
    return (int) round(2 * $radio_tierra_m * asin(min(1.0, sqrt($a))));
}

/** "-8.111600, -79.028800" para mostrar o volver a editar; '' si no hay coordenadas. */
function formatear_coordenadas(string|float|null $latitud, string|float|null $longitud): string
{
    if ($latitud === null || $longitud === null || $latitud === '' || $longitud === '') {
        return '';
    }
    return sprintf('%.6F, %.6F', (float) $latitud, (float) $longitud);
}

/** Enlace que no consume la API de Google Maps (RN-21). Siempre con punto decimal. */
function armar_enlace_google_maps(float $latitud, float $longitud): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . sprintf('%.7F,%.7F', $latitud, $longitud);
}

function armar_enlace_como_llegar(float $latitud, float $longitud): string
{
    return 'https://www.google.com/maps/dir/?api=1&destination=' . sprintf('%.7F,%.7F', $latitud, $longitud);
}

// ---------------------------------------------------------------------------
// Estados y acciones permitidas
// ---------------------------------------------------------------------------

function validar_estado(string $estado): void
{
    if (!in_array($estado, ESTADOS, true)) {
        throw new InvalidArgumentException("Estado desconocido: $estado");
    }
}

/**
 * Estado que se muestra (RN-14). PENDIENTE_NOTA no se devuelve aquí porque es
 * un distintivo sobre FINALIZADO: ver pendiente_nota().
 *
 * @param array{estado: string, inicio_programado: string|DateTimeInterface, fin_programado: string|DateTimeInterface} $operativo
 */
function estado_visible(
    array $operativo,
    DateTimeImmutable $ahora,
    int $tolerancia_no_iniciado_min,
    int $tolerancia_sin_cierre_min,
): string {
    $estado = $operativo['estado'];
    validar_estado($estado);

    if ($estado === 'PROGRAMADO') {
        $limite = convertir_a_fecha_hora($operativo['inicio_programado'])->modify("+{$tolerancia_no_iniciado_min} minutes");
        if ($ahora > $limite) {
            return 'NO_INICIADO';
        }
    }
    if ($estado === 'EN_CURSO') {
        $limite = convertir_a_fecha_hora($operativo['fin_programado'])->modify("+{$tolerancia_sin_cierre_min} minutes");
        if ($ahora > $limite) {
            return 'SIN_CIERRE';
        }
    }
    return $estado;
}

function obtener_etiqueta_estado(string $estado): string
{
    return ETIQUETAS_ESTADO[$estado] ?? throw new InvalidArgumentException("Estado desconocido: $estado");
}

function obtener_clase_estado(string $estado): string
{
    return CLASES_ESTADO[$estado] ?? throw new InvalidArgumentException("Estado desconocido: $estado");
}

/** FINALIZADO sin número de nota informativa (RN-13). */
function pendiente_nota(array $operativo): bool
{
    return $operativo['estado'] === 'FINALIZADO'
        && trim((string) ($operativo['nota_informativa'] ?? '')) === '';
}

function paso_hora_inicio(array $operativo, DateTimeImmutable $ahora): bool
{
    return $ahora >= convertir_a_fecha_hora($operativo['inicio_programado']);
}

/** RN-07: desde la ventana previa al inicio y hasta antes de la hora de fin. */
function puede_iniciar(array $operativo, DateTimeImmutable $ahora, int $ventana_inicio_min): bool
{
    if ($operativo['estado'] !== 'PROGRAMADO') {
        return false;
    }
    $desde = convertir_a_fecha_hora($operativo['inicio_programado'])->modify("-{$ventana_inicio_min} minutes");
    $hasta = convertir_a_fecha_hora($operativo['fin_programado']);
    return $ahora >= $desde && $ahora < $hasta;
}

/** RN-06: la unidad elimina solo un PROGRAMADO antes de su hora de inicio; ADMIN siempre (RN-05). */
function puede_eliminar(array $operativo, DateTimeImmutable $ahora, bool $es_admin): bool
{
    if ($es_admin) {
        return true;
    }
    return $operativo['estado'] === 'PROGRAMADO' && !paso_hora_inicio($operativo, $ahora);
}

function puede_registrar_punto(array $operativo): bool
{
    return $operativo['estado'] === 'EN_CURSO';
}

function puede_finalizar(array $operativo): bool
{
    return $operativo['estado'] === 'EN_CURSO';
}

/**
 * Estado de una celda del tablero (unidad × turno). Sin operativo, la celda es
 * SIN_PROGRAMAR solo cuando ya pasó la última hora de inicio posible del turno;
 * antes devuelve null porque todavía se puede programar.
 */
function calcular_estado_celda(
    ?array $operativo,
    string $fecha_servicio,
    string $turno,
    DateTimeImmutable $ahora,
    int $tolerancia_no_iniciado_min,
    int $tolerancia_sin_cierre_min,
): ?string {
    if ($operativo !== null) {
        return estado_visible($operativo, $ahora, $tolerancia_no_iniciado_min, $tolerancia_sin_cierre_min);
    }
    return calcular_rango_turno($fecha_servicio, $turno)['hasta'] < $ahora ? 'SIN_PROGRAMAR' : null;
}

/**
 * RN-15: el control se registra sobre operativos del servicio diario vigente.
 * También sobre uno de un servicio anterior que sigue en curso, porque sigue en el mapa.
 */
function operativo_controlable(array $operativo, string $servicio_vigente): bool
{
    return $operativo['fecha_servicio'] === $servicio_vigente
        || ($operativo['estado'] === 'EN_CURSO' && $operativo['fecha_servicio'] < $servicio_vigente);
}

/**
 * Resultado y observación del control (RN-15). La observación es obligatoria
 * salvo cuando el resultado es CONFORME.
 *
 * @return array{datos: array<string, mixed>, errores: array<string, string>}
 */
function validar_control(array $entrada): array
{
    $datos = [];
    $errores = [];

    $resultado = $entrada['resultado'] ?? null;
    if (!is_string($resultado) || !array_key_exists($resultado, RESULTADOS_CONTROL)) {
        $errores['resultado'] = 'Elija el resultado del control.';
    } else {
        $datos['resultado'] = $resultado;
    }

    $observacion = trim((string) ($entrada['observacion'] ?? ''));
    if (mb_strlen($observacion) > 5000) {
        $errores['observacion'] = 'La observación admite hasta 5000 caracteres.';
    } elseif ($observacion === '' && $resultado !== null && $resultado !== 'CONFORME' && isset($datos['resultado'])) {
        $errores['observacion'] = 'Describa lo observado.';
    } else {
        $datos['observacion'] = $observacion === '' ? null : $observacion;
    }

    $id_envio = $entrada['id_envio'] ?? null;
    if (!is_string($id_envio) || !preg_match('/^[A-Za-z0-9-]{8,36}$/', $id_envio)) {
        $errores['id_envio'] = 'Falta el identificador del envío.';
    } else {
        $datos['id_envio'] = $id_envio;
    }

    return ['datos' => $datos, 'errores' => $errores];
}

/** La unidad solo puede agregar la nota si falta; una vez guardada la cambia solo ADMIN (RN-13). */
function puede_agregar_nota(array $operativo): bool
{
    return pendiente_nota($operativo);
}

/**
 * Campos que la UNIDAD puede editar (RN-05). ADMIN puede editar todos.
 *
 * @return list<string>
 */
function campos_editables(string $estado, bool $paso_hora_inicio): array
{
    validar_estado($estado);
    return match ($estado) {
        'PROGRAMADO' => $paso_hora_inicio ? CAMPOS_DATOS : array_merge(CAMPOS_HORARIO, CAMPOS_DATOS),
        'EN_CURSO'   => CAMPOS_EN_CURSO,
        'FINALIZADO' => [],
    };
}

// ---------------------------------------------------------------------------
// Validación de datos
// ---------------------------------------------------------------------------

/** Deja solo los dígitos (RN-13). null si no hay ninguno. */
function normalizar_nota_informativa(string $texto): ?string
{
    $digitos = preg_replace('/\D+/', '', $texto);
    if ($digitos === '') {
        return null;
    }
    if (strlen($digitos) > MAX_DIGITOS_NOTA) {
        throw new InvalidArgumentException('La nota informativa no puede tener más de ' . MAX_DIGITOS_NOTA . ' dígitos.');
    }
    return $digitos;
}

function formatear_nota_informativa(string $numero): string
{
    return 'NOTA INFORMATIVA N° ' . $numero;
}

/** Celular peruano: 9 dígitos que empiezan en 9. Acepta espacios, guiones y el prefijo +51. */
function normalizar_celular(string $texto): ?string
{
    $digitos = preg_replace('/[\s\-().]+/', '', $texto);
    $digitos = preg_replace('/^(\+?51)(?=9\d{8}$)/', '', $digitos);
    return preg_match('/^9\d{8}$/', $digitos) ? $digitos : null;
}

/** Entero dentro de un rango; acepta enteros y textos de un formulario. */
function validar_entero(mixed $valor, int $minimo, int $maximo): ?int
{
    if (is_string($valor)) {
        $valor = trim($valor);
    }
    $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]);
    return $entero === false ? null : $entero;
}

/**
 * Valida los datos de programación (RN-04). Solo revisa los campos indicados,
 * para poder validar la edición de un operativo EN_CURSO (RN-05).
 * La fecha y las horas se validan con calcular_horario_turno().
 *
 * @param list<int> $ids_tipos_activos
 * @param list<string>|null $campos
 * @return array{datos: array<string, mixed>, errores: array<string, string>}
 */
function validar_programacion(array $entrada, array $ids_tipos_activos, ?array $campos = null): array
{
    $campos ??= CAMPOS_DATOS;
    $datos = [];
    $errores = [];
    $texto = static fn (string $campo): string => trim((string) ($entrada[$campo] ?? ''));

    if (in_array('tipos', $campos, true)) {
        $tipos = array_values(array_unique(array_map('intval', (array) ($entrada['tipos'] ?? []))));
        if ($tipos === []) {
            $errores['tipos'] = 'Marque al menos un tipo de operativo.';
        } elseif (array_diff($tipos, $ids_tipos_activos) !== []) {
            $errores['tipos'] = 'Hay un tipo de operativo que no es válido.';
        } else {
            $datos['tipos'] = $tipos;
        }
    }

    if (in_array('lugar_programado', $campos, true)) {
        $lugar = $texto('lugar_programado');
        if ($lugar === '') {
            $errores['lugar_programado'] = 'Indique el lugar programado.';
        } elseif (mb_strlen($lugar) > 255) {
            $errores['lugar_programado'] = 'El lugar programado admite hasta 255 caracteres.';
        } else {
            $datos['lugar_programado'] = $lugar;
        }
    }

    // Coordenadas del lugar programado: obligatorias (ubican el operativo en el mapa
    // antes de iniciarse), pegadas de Google Maps.
    if (in_array('lugar_coordenadas', $campos, true)) {
        $texto_coordenadas = $texto('lugar_coordenadas');
        $coordenadas = $texto_coordenadas === '' ? null : parsear_coordenadas($texto_coordenadas);
        if ($texto_coordenadas === '') {
            $errores['lugar_coordenadas'] = 'Pegue las coordenadas del lugar (toque Buscar para obtenerlas en Google Maps).';
        } elseif ($coordenadas === null) {
            $errores['lugar_coordenadas'] = 'No se reconocen las coordenadas. Cópielas de Google Maps (por ejemplo: -8.1116, -79.0288).';
        } elseif (!dentro_de_la_libertad($coordenadas[0], $coordenadas[1])) {
            $errores['lugar_coordenadas'] = 'Esas coordenadas están fuera de La Libertad. Revise que las copió bien.';
        } else {
            [$datos['lugar_latitud'], $datos['lugar_longitud']] = $coordenadas;
        }
    }

    if (in_array('jefe_grado', $campos, true)) {
        $grado = $texto('jefe_grado');
        if (!in_array($grado, GRADOS, true)) {
            $errores['jefe_grado'] = 'Elija el grado del jefe del operativo.';
        } else {
            $datos['jefe_grado'] = $grado;
        }
    }

    if (in_array('jefe_nombres', $campos, true)) {
        $nombres = $texto('jefe_nombres');
        if ($nombres === '') {
            $errores['jefe_nombres'] = 'Indique los nombres del jefe del operativo.';
        } elseif (mb_strlen($nombres) > 150) {
            $errores['jefe_nombres'] = 'Los nombres admiten hasta 150 caracteres.';
        } else {
            $datos['jefe_nombres'] = $nombres;
        }
    }

    if (in_array('jefe_celular', $campos, true)) {
        $celular = normalizar_celular($texto('jefe_celular'));
        if ($celular === null) {
            $errores['jefe_celular'] = 'El celular debe tener 9 dígitos y empezar con 9.';
        } else {
            $datos['jefe_celular'] = $celular;
        }
    }

    $enteros = [
        'fuerzas_propias'  => [1, 'Las fuerzas propias deben ser un número entero de 1 a ' . MAX_FUERZAS . '.'],
        'fuerzas_apoyo'    => [0, 'Las fuerzas de apoyo deben ser un número entero de 0 a ' . MAX_FUERZAS . '.'],
        'unidades_moviles' => [0, 'Las unidades móviles deben ser un número entero de 0 a ' . MAX_FUERZAS . '.'],
    ];
    foreach ($enteros as $campo => [$minimo, $mensaje]) {
        if (!in_array($campo, $campos, true)) {
            continue;
        }
        $valor = validar_entero($entrada[$campo] ?? null, $minimo, MAX_FUERZAS);
        if ($valor === null) {
            $errores[$campo] = $mensaje;
        } else {
            $datos[$campo] = $valor;
        }
    }

    if (in_array('observaciones', $campos, true)) {
        $observaciones = $texto('observaciones');
        $datos['observaciones'] = $observaciones === '' ? null : $observaciones;
    }

    return ['datos' => $datos, 'errores' => $errores];
}

/**
 * Cantidades del cierre (RN-12): un entero ≥ 0 por concepto, 0 si no viene.
 *
 * @param list<int> $ids_conceptos_activos
 * @return array{datos: array<int, int>, errores: array<int, string>}
 */
function validar_cantidades_resultado(array $entrada, array $ids_conceptos_activos): array
{
    $datos = [];
    $errores = [];
    foreach ($ids_conceptos_activos as $id) {
        $valor = $entrada[$id] ?? 0;
        if (is_string($valor) && trim($valor) === '') {
            $valor = 0;
        }
        $cantidad = validar_entero($valor, 0, MAX_CANTIDAD_RESULTADO);
        if ($cantidad === null) {
            $errores[$id] = 'Debe ser un número entero de 0 a ' . MAX_CANTIDAD_RESULTADO . '.';
        } else {
            $datos[$id] = $cantidad;
        }
    }
    return ['datos' => $datos, 'errores' => $errores];
}

/** Negativo si todas las cantidades son 0 (RN-12). */
function es_resultado_negativo(array $cantidades): bool
{
    foreach ($cantidades as $cantidad) {
        if ((int) $cantidad !== 0) {
            return false;
        }
    }
    return true;
}

// ---------------------------------------------------------------------------
// Cuentas (RN-19)
// ---------------------------------------------------------------------------

/**
 * Nuevo estado de la cuenta tras un intento fallido: al llegar a 5 se bloquea
 * 15 minutos y el contador vuelve a 0.
 *
 * @return array{intentos: int, bloqueado_hasta: DateTimeImmutable|null}
 */
function calcular_intento_fallido(int $intentos_previos, DateTimeImmutable $ahora): array
{
    $intentos = $intentos_previos + 1;
    if ($intentos >= MAX_INTENTOS_FALLIDOS) {
        return ['intentos' => 0, 'bloqueado_hasta' => $ahora->modify('+' . MINUTOS_BLOQUEO . ' minutes')];
    }
    return ['intentos' => $intentos, 'bloqueado_hasta' => null];
}

function cuenta_bloqueada(string|DateTimeInterface|null $bloqueado_hasta, DateTimeImmutable $ahora): bool
{
    return $bloqueado_hasta !== null && $ahora < convertir_a_fecha_hora($bloqueado_hasta);
}

/** @return string|null Mensaje de error, o null si la clave es válida. */
function validar_clave(string $clave): ?string
{
    if (mb_strlen($clave) < LARGO_MINIMO_CLAVE) {
        return 'La clave debe tener al menos ' . LARGO_MINIMO_CLAVE . ' caracteres.';
    }
    if (mb_strlen($clave) > 72) {
        return 'La clave admite hasta 72 caracteres.';
    }
    return null;
}

/** Clave que ADMIN entrega al crear una cuenta o resetear la clave (RN-19). */
function generar_clave_temporal(): string
{
    $clave = '';
    $ultimo = strlen(ALFABETO_CLAVE_TEMPORAL) - 1;
    for ($i = 0; $i < LARGO_CLAVE_TEMPORAL; $i++) {
        $clave .= ALFABETO_CLAVE_TEMPORAL[random_int(0, $ultimo)];
    }
    return $clave;
}

// ---------------------------------------------------------------------------
// Administración: unidades, usuarios y catálogos
// ---------------------------------------------------------------------------

/**
 * @return array{datos: array<string, mixed>, errores: array<string, string>}
 */
function validar_unidad(array $entrada): array
{
    $datos = [];
    $errores = [];
    $texto = static fn (string $campo): string => trim((string) ($entrada[$campo] ?? ''));

    $division = $texto('division');
    if (!array_key_exists($division, DIVISIONES)) {
        $errores['division'] = 'Elija la división.';
    } else {
        $datos['division'] = $division;
    }

    foreach (['nombre' => [150, 'el nombre completo'], 'nombre_corto' => [60, 'el nombre corto']] as $campo => [$largo, $etiqueta]) {
        $valor = $texto($campo);
        if ($valor === '') {
            $errores[$campo] = "Indique $etiqueta.";
        } elseif (mb_strlen($valor) > $largo) {
            $errores[$campo] = ucfirst($etiqueta) . " admite hasta $largo caracteres.";
        } else {
            $datos[$campo] = $valor;
        }
    }

    $orden = validar_entero($entrada['orden'] ?? null, 0, 65535);
    if ($orden === null) {
        $errores['orden'] = 'El orden debe ser un número entero de 0 a 65535.';
    } else {
        $datos['orden'] = $orden;
    }

    $datos['activo'] = !empty($entrada['activo']) ? 1 : 0;
    return ['datos' => $datos, 'errores' => $errores];
}

/**
 * Datos de una cuenta (RN-20): UNIDAD lleva unidad; ADMIN y SUPERVISOR no.
 * La clave es opcional: si viene vacía, la página genera una temporal.
 * La unicidad del usuario y de la unidad la garantiza la base de datos.
 *
 * @param list<int> $ids_unidades Unidades que existen.
 * @return array{datos: array<string, mixed>, errores: array<string, string>}
 */
function validar_usuario(array $entrada, array $ids_unidades, bool $es_nuevo): array
{
    $datos = [];
    $errores = [];
    $texto = static fn (string $campo): string => trim((string) ($entrada[$campo] ?? ''));

    $usuario = mb_strtolower($texto('usuario'));
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $usuario)) {
        $errores['usuario'] = 'El usuario debe tener de 3 a 50 caracteres: letras sin tilde, números, punto, guion o guion bajo.';
    } else {
        $datos['usuario'] = $usuario;
    }

    $nombre = $texto('nombre');
    if ($nombre === '') {
        $errores['nombre'] = 'Indique el nombre.';
    } elseif (mb_strlen($nombre) > 150) {
        $errores['nombre'] = 'El nombre admite hasta 150 caracteres.';
    } else {
        $datos['nombre'] = $nombre;
    }

    $rol = $texto('rol');
    if (!in_array($rol, ROLES, true)) {
        $errores['rol'] = 'Elija el rol.';
    } else {
        $datos['rol'] = $rol;
        if ($rol === 'UNIDAD') {
            $unidad_id = validar_entero($entrada['unidad_id'] ?? null, 1, PHP_INT_MAX);
            if ($unidad_id === null || !in_array($unidad_id, $ids_unidades, true)) {
                $errores['unidad_id'] = 'Una cuenta UNIDAD debe tener una unidad asignada.';
            } else {
                $datos['unidad_id'] = $unidad_id;
            }
        } else {
            $datos['unidad_id'] = null;
        }
    }

    if ($es_nuevo) {
        $clave = (string) ($entrada['clave'] ?? '');
        if ($clave !== '') {
            $error = validar_clave($clave);
            if ($error !== null) {
                $errores['clave'] = $error;
            } else {
                $datos['clave'] = $clave;
            }
        }
    }

    $datos['activo'] = !empty($entrada['activo']) ? 1 : 0;
    return ['datos' => $datos, 'errores' => $errores];
}

/**
 * Tipos de operativo y conceptos de resultado. Los conceptos llevan además una descripción.
 * Los nombres se guardan en mayúsculas, como en el catálogo inicial.
 *
 * @return array{datos: array<string, mixed>, errores: array<string, string>}
 */
function validar_elemento_catalogo(array $entrada, int $largo_nombre, bool $con_descripcion): array
{
    $datos = [];
    $errores = [];

    $nombre = mb_strtoupper(trim((string) ($entrada['nombre'] ?? '')));
    if ($nombre === '') {
        $errores['nombre'] = 'Indique el nombre.';
    } elseif (mb_strlen($nombre) > $largo_nombre) {
        $errores['nombre'] = "El nombre admite hasta $largo_nombre caracteres.";
    } else {
        $datos['nombre'] = $nombre;
    }

    if ($con_descripcion) {
        $descripcion = trim((string) ($entrada['descripcion'] ?? ''));
        if (mb_strlen($descripcion) > 255) {
            $errores['descripcion'] = 'La descripción admite hasta 255 caracteres.';
        } else {
            $datos['descripcion'] = $descripcion === '' ? null : $descripcion;
        }
    }

    $orden = validar_entero($entrada['orden'] ?? null, 0, 65535);
    if ($orden === null) {
        $errores['orden'] = 'El orden debe ser un número entero de 0 a 65535.';
    } else {
        $datos['orden'] = $orden;
    }

    $datos['activo'] = !empty($entrada['activo']) ? 1 : 0;
    return ['datos' => $datos, 'errores' => $errores];
}

/** Valor de un <input type="datetime-local"> ('2026-10-17T12:05') a fecha y hora de Lima. null si no es válido. */
function convertir_fecha_hora_local(string $valor): ?DateTimeImmutable
{
    $valor = trim($valor);
    foreach (['!Y-m-d\TH:i', '!Y-m-d\TH:i:s'] as $formato) {
        $fecha = DateTimeImmutable::createFromFormat($formato, $valor, obtener_zona_horaria());
        if ($fecha !== false && $fecha->format(substr($formato, 1)) === $valor) {
            return $fecha;
        }
    }
    return null;
}

/**
 * Estado y horas reales que fija ADMIN al corregir un operativo (RN-05).
 * Mantiene la coherencia del ciclo de vida: PROGRAMADO sin horas reales ni
 * puntos; EN_CURSO con inicio y sin fin; FINALIZADO con inicio y fin.
 * Las horas reales no pueden estar en el futuro.
 *
 * @return array{datos: array{estado?: string, inicio_real?: string|null, fin_real?: string|null}, errores: array<string, string>}
 */
function validar_ejecucion(array $entrada, bool $tiene_ubicaciones, DateTimeImmutable $ahora): array
{
    $datos = [];
    $errores = [];

    $estado = $entrada['estado'] ?? null;
    if (!in_array($estado, ESTADOS, true)) {
        return ['datos' => [], 'errores' => ['estado' => 'Elija el estado.']];
    }
    $datos['estado'] = $estado;

    $leer = static function (string $campo) use ($entrada, $ahora, &$errores): ?DateTimeImmutable {
        $texto = trim((string) ($entrada[$campo] ?? ''));
        if ($texto === '') {
            return null;
        }
        $fecha = convertir_fecha_hora_local($texto);
        if ($fecha === null) {
            $errores[$campo] = 'La fecha y hora no es válida.';
        } elseif ($fecha > $ahora) {
            $errores[$campo] = 'No puede estar en el futuro.';
        }
        return $fecha;
    };
    $inicio = $leer('inicio_real');
    $fin = $leer('fin_real');

    if ($estado === 'PROGRAMADO') {
        if ($tiene_ubicaciones) {
            $errores['estado'] = 'Tiene puntos registrados: no puede volver a PROGRAMADO.';
        }
        $inicio = $fin = null;
    } else {
        if ($inicio === null && !isset($errores['inicio_real'])) {
            $errores['inicio_real'] = 'Indique la hora real de inicio.';
        }
        if ($estado === 'EN_CURSO') {
            $fin = null;
        } elseif ($fin === null && !isset($errores['fin_real'])) {
            $errores['fin_real'] = 'Indique la hora real de fin.';
        } elseif ($inicio !== null && $fin !== null && $fin < $inicio) {
            $errores['fin_real'] = 'El fin real no puede ser anterior al inicio real.';
        }
    }

    $datos['inicio_real'] = $inicio?->format(FORMATO_FECHA_HORA);
    $datos['fin_real'] = $fin?->format(FORMATO_FECHA_HORA);
    return ['datos' => $datos, 'errores' => $errores];
}

/** Motivo de una corrección o eliminación de ADMIN, para la bitácora. null si es válido. */
function validar_motivo(string $motivo): ?string
{
    $largo = mb_strlen(trim($motivo));
    if ($largo < 5) {
        return 'Explique el motivo de la corrección (al menos 5 caracteres).';
    }
    if ($largo > 500) {
        return 'El motivo admite hasta 500 caracteres.';
    }
    return null;
}

/**
 * Campos que cambiaron, para la bitácora: ['campo' => ['antes' => x, 'despues' => y]].
 * Compara como texto, para que 5 y '5' (como vienen de la base de datos) sean iguales.
 * Solo revisa las claves de $despues.
 *
 * @return array<string, array{antes: mixed, despues: mixed}>
 */
function calcular_cambios(array $antes, array $despues): array
{
    $normalizar = static function (mixed $valor): mixed {
        if (is_array($valor)) {
            $valor = array_map('strval', $valor);
            sort($valor);
            return $valor;
        }
        if (is_bool($valor)) {
            return (string) (int) $valor;
        }
        return $valor === null ? null : (string) $valor;
    };

    $cambios = [];
    foreach ($despues as $campo => $valor_nuevo) {
        $valor_anterior = $antes[$campo] ?? null;
        if ($normalizar($valor_anterior) !== $normalizar($valor_nuevo)) {
            $cambios[$campo] = ['antes' => $valor_anterior, 'despues' => $valor_nuevo];
        }
    }
    return $cambios;
}
