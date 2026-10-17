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

const CAMPOS_HORARIO = ['fecha_servicio', 'hora_inicio', 'hora_fin'];
const CAMPOS_DATOS = [
    'tipos', 'nombre_plan', 'lugar_programado',
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

/** Distancia en metros con la fórmula de Haversine (RN-15). */
function distancia_m(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $radio_tierra_m = 6371000;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    $a = sin($d_lat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($d_lng / 2) ** 2;
    return (int) round(2 * $radio_tierra_m * asin(min(1.0, sqrt($a))));
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

    if (in_array('nombre_plan', $campos, true)) {
        $plan = $texto('nombre_plan');
        if (mb_strlen($plan) > 150) {
            $errores['nombre_plan'] = 'El nombre del plan admite hasta 150 caracteres.';
        } else {
            $datos['nombre_plan'] = $plan === '' ? null : $plan;
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
