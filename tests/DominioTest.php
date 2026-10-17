<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DominioTest extends TestCase
{
    private const TOLERANCIA_NO_INICIADO = 15;
    private const TOLERANCIA_SIN_CIERRE = 30;
    private const VENTANA_INICIO = 60;

    private static function lima(string $fecha_hora): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha_hora, new DateTimeZone('America/Lima'));
    }

    private static function operativo(string $estado, string $inicio, string $fin, ?string $nota = null): array
    {
        return [
            'estado'            => $estado,
            'inicio_programado' => $inicio,
            'fin_programado'    => $fin,
            'nota_informativa'  => $nota,
        ];
    }

    // --- clasificar_turno ---------------------------------------------------

    public static function limites_de_turno(): array
    {
        return [
            ['00:00', 'MADRUGADA'],
            ['05:59', 'MADRUGADA'],
            ['06:00', 'MANANA'],
            ['11:59', 'MANANA'],
            ['12:00', 'TARDE'],
            ['17:59', 'TARDE'],
            ['18:00', 'NOCHE'],
            ['23:59', 'NOCHE'],
        ];
    }

    #[DataProvider('limites_de_turno')]
    public function test_clasifica_el_turno_en_los_limites(string $hora, string $turno): void
    {
        $this->assertSame($turno, clasificar_turno($hora));
    }

    public static function horas_invalidas(): array
    {
        return [['24:00'], ['7:00'], ['07:60'], ['07:00:00'], ['7 pm'], [''], ['ab:cd']];
    }

    #[DataProvider('horas_invalidas')]
    public function test_rechaza_horas_que_no_son_hh_mm_de_24_horas(string $hora): void
    {
        $this->expectException(InvalidArgumentException::class);
        clasificar_turno($hora);
    }

    public function test_etiqueta_del_turno_lleva_tilde(): void
    {
        $this->assertSame('MAÑANA', obtener_etiqueta_turno('MANANA'));
        $this->expectException(InvalidArgumentException::class);
        obtener_etiqueta_turno('MANANITA');
    }

    // --- calcular_horario_turno ---------------------------------------------

    public function test_turno_en_el_mismo_dia(): void
    {
        $h = calcular_horario_turno('2026-10-17', '16:00', '18:00');
        $this->assertSame('TARDE', $h['turno']);
        $this->assertSame('2026-10-17 16:00:00', $h['inicio']->format(FORMATO_FECHA_HORA));
        $this->assertSame('2026-10-17 18:00:00', $h['fin']->format(FORMATO_FECHA_HORA));
    }

    public function test_noche_que_cruza_la_medianoche_sigue_siendo_noche(): void
    {
        $h = calcular_horario_turno('2026-10-17', '20:00', '02:00');
        $this->assertSame('NOCHE', $h['turno']);
        $this->assertSame('2026-10-17 20:00:00', $h['inicio']->format(FORMATO_FECHA_HORA));
        $this->assertSame('2026-10-18 02:00:00', $h['fin']->format(FORMATO_FECHA_HORA));
    }

    public function test_fin_a_la_medianoche_cae_al_dia_siguiente(): void
    {
        $h = calcular_horario_turno('2026-10-17', '18:00', '00:00');
        $this->assertSame('2026-10-18 00:00:00', $h['fin']->format(FORMATO_FECHA_HORA));
    }

    public function test_madrugada_empieza_el_dia_siguiente_a_la_fecha_de_servicio(): void
    {
        $h = calcular_horario_turno('2026-10-17', '02:00', '05:00');
        $this->assertSame('MADRUGADA', $h['turno']);
        $this->assertSame('2026-10-18 02:00:00', $h['inicio']->format(FORMATO_FECHA_HORA));
        $this->assertSame('2026-10-18 05:00:00', $h['fin']->format(FORMATO_FECHA_HORA));
    }

    public function test_madrugada_que_termina_en_la_manana(): void
    {
        $h = calcular_horario_turno('2026-10-17', '04:00', '08:00');
        $this->assertSame('MADRUGADA', $h['turno']);
        $this->assertSame('2026-10-18 08:00:00', $h['fin']->format(FORMATO_FECHA_HORA));
    }

    public function test_sin_duracion_maxima_el_fin_puede_caer_casi_24_horas_despues(): void
    {
        $h = calcular_horario_turno('2026-10-17', '08:00', '07:59');
        $this->assertSame('MANANA', $h['turno']);
        $this->assertSame('2026-10-18 07:59:00', $h['fin']->format(FORMATO_FECHA_HORA));
    }

    public function test_cruce_de_fin_de_ano(): void
    {
        $h = calcular_horario_turno('2026-12-31', '22:00', '03:00');
        $this->assertSame('2027-01-01 03:00:00', $h['fin']->format(FORMATO_FECHA_HORA));

        $m = calcular_horario_turno('2026-12-31', '01:00', '04:00');
        $this->assertSame('2027-01-01 01:00:00', $m['inicio']->format(FORMATO_FECHA_HORA));
    }

    public function test_cruce_de_febrero_en_ano_bisiesto_y_no_bisiesto(): void
    {
        $this->assertSame(
            '2028-02-29 01:00:00',
            calcular_horario_turno('2028-02-28', '01:00', '03:00')['inicio']->format(FORMATO_FECHA_HORA),
        );
        $this->assertSame(
            '2027-03-01 01:00:00',
            calcular_horario_turno('2027-02-28', '01:00', '03:00')['inicio']->format(FORMATO_FECHA_HORA),
        );
    }

    public function test_rechaza_inicio_igual_al_fin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        calcular_horario_turno('2026-10-17', '16:00', '16:00');
    }

    public function test_rechaza_fechas_que_no_existen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        calcular_horario_turno('2026-02-30', '16:00', '18:00');
    }

    // --- fecha_servicio_vigente ---------------------------------------------

    public static function momentos_y_servicio_diario(): array
    {
        return [
            ['2026-10-18 00:00:00', '2026-10-17'],
            ['2026-10-18 05:59:59', '2026-10-17'],
            ['2026-10-18 06:00:00', '2026-10-18'],
            ['2026-10-18 23:59:59', '2026-10-18'],
            ['2027-01-01 03:00:00', '2026-12-31'],
        ];
    }

    #[DataProvider('momentos_y_servicio_diario')]
    public function test_servicio_diario_vigente_cambia_a_las_6(string $ahora, string $servicio_diario): void
    {
        $this->assertSame($servicio_diario, fecha_servicio_vigente(self::lima($ahora)));
    }

    public function test_servicio_diario_vigente_convierte_desde_utc(): void
    {
        // 10:00 UTC son las 05:00 en Lima: todavía es el servicio diario anterior.
        $this->assertSame('2026-10-17', fecha_servicio_vigente(new DateTimeImmutable('2026-10-18 10:00:00', new DateTimeZone('UTC'))));
    }

    // --- formatear_fecha_larga ----------------------------------------------

    public function test_formatea_la_fecha_en_espanol(): void
    {
        $this->assertSame('sábado 17 de octubre de 2026', formatear_fecha_larga(validar_fecha('2026-10-17')));
        $this->assertSame('miércoles 1 de enero de 2025', formatear_fecha_larga(validar_fecha('2025-01-01')));
        $this->assertSame('domingo 1 de febrero de 2026', formatear_fecha_larga(validar_fecha('2026-02-01')));
    }

    // --- programacion_permitida ---------------------------------------------

    public function test_la_unidad_no_programa_con_la_hora_pasada(): void
    {
        $inicio = self::lima('2026-10-17 16:00:00');
        $this->assertTrue(programacion_permitida($inicio, self::lima('2026-10-17 15:59:59'), false));
        $this->assertFalse(programacion_permitida($inicio, self::lima('2026-10-17 16:00:00'), false));
        $this->assertFalse(programacion_permitida($inicio, self::lima('2026-10-17 16:00:01'), false));
    }

    public function test_admin_programa_con_la_hora_pasada(): void
    {
        $this->assertTrue(programacion_permitida(self::lima('2026-10-10 16:00:00'), self::lima('2026-10-17 16:00:00'), true));
    }

    // --- dentro_de_la_libertad ----------------------------------------------

    public static function coordenadas_validas(): array
    {
        return [
            'Plaza de Armas de Trujillo' => [-8.1116, -79.0288],
            'Huanchaco'                  => [-8.0789, -79.1213],
            'borde sur-oeste'            => [-9.00, -79.80],
            'borde norte-este'           => [-6.90, -76.80],
        ];
    }

    #[DataProvider('coordenadas_validas')]
    public function test_acepta_coordenadas_dentro_de_la_libertad(float $lat, float $lng): void
    {
        $this->assertTrue(dentro_de_la_libertad($lat, $lng));
    }

    public static function coordenadas_invalidas(): array
    {
        return [
            'ambos signos invertidos'       => [8.1116, 79.0288],
            'latitud sin signo'             => [8.1116, -79.0288],
            'longitud sin signo'            => [-8.1116, 79.0288],
            'latitud sin punto decimal'     => [-81116000, -79.0288],
            'longitud sin punto decimal'    => [-8.1116, -790288],
            'lat y lng intercambiadas'      => [-79.0288, -8.1116],
            'cero, cero'                    => [0.0, 0.0],
            'apenas al sur del borde'       => [-9.0000001, -79.0],
            'apenas al oeste del borde'     => [-8.0, -79.8000001],
            'apenas al norte del borde'     => [-6.8999999, -78.0],
            'apenas al este del borde'      => [-8.0, -76.7999999],
            'Lima'                          => [-12.0464, -77.0428],
            'no es un número'               => [NAN, -79.0],
            'infinito'                      => [-8.0, INF],
        ];
    }

    #[DataProvider('coordenadas_invalidas')]
    public function test_rechaza_coordenadas_fuera_de_la_libertad(float $lat, float $lng): void
    {
        $this->assertFalse(dentro_de_la_libertad($lat, $lng));
    }

    // --- parsear_coordenadas ------------------------------------------------

    public static function textos_de_google_maps(): array
    {
        return [
            'clic derecho en la PC'      => ['-8.111600, -79.028700', [-8.1116, -79.0287]],
            'sin espacio'                => ['-8.1116,-79.0287', [-8.1116, -79.0287]],
            'separado por espacio'       => ['-8.1116 -79.0287', [-8.1116, -79.0287]],
            'con espacios alrededor'     => ["  -8.1116 ,  -79.0287 \n", [-8.1116, -79.0287]],
            'entre paréntesis'           => ['(-8.1116, -79.0287)', [-8.1116, -79.0287]],
            'coma decimal'               => ['-8,1116; -79,0287', [-8.1116, -79.0287]],
            'coma decimal y coma'        => ['-8,1116, -79,0287', [-8.1116, -79.0287]],
            'muchos decimales'           => ['-8.11160012345, -79.02870098765', [-8.1116001, -79.028701]],
            'URL con @'                  => ['https://www.google.com/maps/@-8.1116,-79.0287,17z', [-8.1116, -79.0287]],
            'URL de lugar con !3d!4d'    => ['https://www.google.com/maps/place/Plaza/@-8.11,-79.02,17z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d-8.1116!4d-79.0287', [-8.1116, -79.0287]],
            'URL con q='                 => ['https://maps.google.com/?q=-8.1116,-79.0287', [-8.1116, -79.0287]],
            'URL codificada'             => ['https://www.google.com/maps/search/?api=1&query=-8.1116%2C-79.0287', [-8.1116, -79.0287]],
            'URL de búsqueda con +'      => ['https://www.google.com/maps/search/-8.1116,+-79.0287', [-8.1116, -79.0287]],
            'grados, minutos y segundos' => ['8°06\'41.8"S 79°01\'43.3"W', [-8.1116111, -79.0286944]],
            'GMS con coma y oeste'       => ['8° 06\' 41,8" S, 79° 01\' 43,3" O', [-8.1116111, -79.0286944]],
            'signos invertidos se parsean igual' => ['8.1116, 79.0287', [8.1116, 79.0287]],
        ];
    }

    #[DataProvider('textos_de_google_maps')]
    public function test_separa_coordenadas_copiadas_de_google_maps(string $texto, array $esperado): void
    {
        $resultado = parsear_coordenadas($texto);
        $this->assertNotNull($resultado);
        $this->assertEqualsWithDelta($esperado[0], $resultado[0], 0.0000001);
        $this->assertEqualsWithDelta($esperado[1], $resultado[1], 0.0000001);
    }

    public static function textos_no_reconocidos(): array
    {
        return [
            'vacío'                      => [''],
            'solo espacios'              => ['   '],
            'un solo número'             => ['-8.1116'],
            'sin punto decimal'          => ['-81116000, -790287000'],
            'tres números'               => ['-8.1, -79.0, 17'],
            'texto'                      => ['Av. España con Jr. Grau'],
            'latitud mayor a 90'         => ['-98.1116, -79.0287'],
            'minutos de 60'              => ['8°60\'00.0"S 79°01\'43.3"W'],
            'GMS sin hemisferio'         => ['8°06\'41.8" 79°01\'43.3"'],
            'URL sin coordenadas'        => ['https://maps.app.goo.gl/AbCdEf123'],
        ];
    }

    #[DataProvider('textos_no_reconocidos')]
    public function test_devuelve_null_si_no_reconoce_el_texto(string $texto): void
    {
        $this->assertNull(parsear_coordenadas($texto));
    }

    // --- precision_aceptable ------------------------------------------------

    public function test_precision_hasta_100_m_es_aceptable(): void
    {
        $this->assertTrue(precision_aceptable(8.0));
        $this->assertTrue(precision_aceptable(100.0));
        $this->assertFalse(precision_aceptable(100.1));
        $this->assertFalse(precision_aceptable(2500.0));
        $this->assertFalse(precision_aceptable(null));
    }

    // --- distancia_m --------------------------------------------------------

    public function test_distancia_al_mismo_punto_es_cero(): void
    {
        $this->assertSame(0, distancia_m(-8.1116, -79.0288, -8.1116, -79.0288));
    }

    public function test_un_grado_de_latitud_mide_unos_111_km(): void
    {
        $this->assertEqualsWithDelta(111195, distancia_m(-8.0, -79.0, -9.0, -79.0), 1);
    }

    public function test_distancia_es_simetrica(): void
    {
        $this->assertSame(
            distancia_m(-8.1116, -79.0288, -8.0789, -79.1213),
            distancia_m(-8.0789, -79.1213, -8.1116, -79.0288),
        );
    }

    public function test_distancia_corta_de_unos_11_m(): void
    {
        $this->assertEqualsWithDelta(11, distancia_m(-8.1116, -79.0288, -8.1117, -79.0288), 1);
    }

    // --- enlaces de Google Maps ---------------------------------------------

    public function test_enlace_usa_punto_decimal_aunque_el_locale_use_coma(): void
    {
        $locale = setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, 'es_PE.UTF-8', 'es_ES.UTF-8', 'Spanish_Peru', 'Spanish');
        try {
            $this->assertSame(
                'https://www.google.com/maps/search/?api=1&query=-8.1116000,-79.0288000',
                armar_enlace_google_maps(-8.1116, -79.0288),
            );
            $this->assertSame(
                'https://www.google.com/maps/dir/?api=1&destination=-8.1116000,-79.0288000',
                armar_enlace_como_llegar(-8.1116, -79.0288),
            );
        } finally {
            setlocale(LC_NUMERIC, $locale);
        }
    }

    // --- estado_visible -----------------------------------------------------

    private function estado(array $operativo, string $ahora): string
    {
        return estado_visible($operativo, self::lima($ahora), self::TOLERANCIA_NO_INICIADO, self::TOLERANCIA_SIN_CIERRE);
    }

    public function test_programado_pasa_a_no_iniciado_despues_de_15_min(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertSame('PROGRAMADO', $this->estado($op, '2026-10-17 16:14:59'));
        $this->assertSame('PROGRAMADO', $this->estado($op, '2026-10-17 16:15:00'));
        $this->assertSame('NO_INICIADO', $this->estado($op, '2026-10-17 16:15:01'));
    }

    public function test_no_iniciado_sigue_despues_de_la_hora_de_fin(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertSame('NO_INICIADO', $this->estado($op, '2026-10-20 09:00:00'));
    }

    public function test_en_curso_pasa_a_sin_cierre_despues_de_30_min(): void
    {
        $op = self::operativo('EN_CURSO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertSame('EN_CURSO', $this->estado($op, '2026-10-17 18:29:59'));
        $this->assertSame('EN_CURSO', $this->estado($op, '2026-10-17 18:30:00'));
        $this->assertSame('SIN_CIERRE', $this->estado($op, '2026-10-17 18:30:01'));
    }

    public function test_sin_cierre_de_un_turno_noche_despues_de_la_medianoche(): void
    {
        $op = self::operativo('EN_CURSO', '2026-10-17 20:00:00', '2026-10-18 02:00:00');
        $this->assertSame('EN_CURSO', $this->estado($op, '2026-10-18 00:30:00'));
        $this->assertSame('EN_CURSO', $this->estado($op, '2026-10-18 02:29:59'));
        $this->assertSame('SIN_CIERRE', $this->estado($op, '2026-10-18 02:30:01'));
    }

    public function test_no_iniciado_de_una_madrugada(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-18 02:00:00', '2026-10-18 05:00:00');
        $this->assertSame('PROGRAMADO', $this->estado($op, '2026-10-17 23:00:00'));
        $this->assertSame('NO_INICIADO', $this->estado($op, '2026-10-18 02:16:00'));
    }

    public function test_finalizado_no_cambia_con_la_hora(): void
    {
        $op = self::operativo('FINALIZADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertSame('FINALIZADO', $this->estado($op, '2026-10-30 10:00:00'));
    }

    public function test_las_tolerancias_se_reciben_por_parametro(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertSame('NO_INICIADO', estado_visible($op, self::lima('2026-10-17 16:05:01'), 5, 30));
        $this->assertSame('PROGRAMADO', estado_visible($op, self::lima('2026-10-17 16:05:01'), 10, 30));
    }

    public function test_acepta_objetos_fecha_ademas_de_textos(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $op['inicio_programado'] = self::lima('2026-10-17 16:00:00');
        $this->assertSame('NO_INICIADO', $this->estado($op, '2026-10-17 16:20:00'));
    }

    public function test_estado_desconocido_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->estado(self::operativo('ANULADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00'), '2026-10-17 16:00:00');
    }

    public function test_fecha_mal_formada_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->estado(self::operativo('PROGRAMADO', '2026-10-17 16:00', '2026-10-17 18:00:00'), '2026-10-17 16:00:00');
    }

    // --- pendiente_nota y puede_agregar_nota --------------------------------

    public function test_pendiente_nota_solo_en_finalizado_sin_nota(): void
    {
        $this->assertTrue(pendiente_nota(self::operativo('FINALIZADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00')));
        $this->assertTrue(pendiente_nota(self::operativo('FINALIZADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00', '  ')));
        $this->assertFalse(pendiente_nota(self::operativo('FINALIZADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00', '202601802185')));
        $this->assertFalse(pendiente_nota(self::operativo('EN_CURSO', '2026-10-17 16:00:00', '2026-10-17 18:00:00')));
    }

    public function test_la_unidad_agrega_la_nota_solo_si_falta(): void
    {
        $this->assertTrue(puede_agregar_nota(self::operativo('FINALIZADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00')));
        $this->assertFalse(puede_agregar_nota(self::operativo('FINALIZADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00', '202601802185')));
    }

    // --- puede_iniciar ------------------------------------------------------

    public function test_se_puede_iniciar_desde_60_min_antes(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertFalse(puede_iniciar($op, self::lima('2026-10-17 14:59:59'), self::VENTANA_INICIO));
        $this->assertTrue(puede_iniciar($op, self::lima('2026-10-17 15:00:00'), self::VENTANA_INICIO));
    }

    public function test_se_puede_iniciar_tarde_hasta_antes_de_la_hora_de_fin(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertTrue(puede_iniciar($op, self::lima('2026-10-17 17:59:59'), self::VENTANA_INICIO));
        $this->assertFalse(puede_iniciar($op, self::lima('2026-10-17 18:00:00'), self::VENTANA_INICIO));
    }

    public function test_iniciar_un_turno_noche_despues_de_la_medianoche(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 20:00:00', '2026-10-18 02:00:00');
        $this->assertTrue(puede_iniciar($op, self::lima('2026-10-18 01:00:00'), self::VENTANA_INICIO));
    }

    public function test_solo_se_inicia_desde_programado(): void
    {
        $op = self::operativo('EN_CURSO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertFalse(puede_iniciar($op, self::lima('2026-10-17 16:00:00'), self::VENTANA_INICIO));
    }

    // --- puede_eliminar -----------------------------------------------------

    public function test_eliminar_justo_antes_y_justo_despues_de_la_hora_de_inicio(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertTrue(puede_eliminar($op, self::lima('2026-10-17 15:59:59'), false));
        $this->assertFalse(puede_eliminar($op, self::lima('2026-10-17 16:00:00'), false));
        $this->assertFalse(puede_eliminar($op, self::lima('2026-10-17 16:00:01'), false));
    }

    public function test_la_unidad_no_elimina_un_operativo_iniciado(): void
    {
        $op = self::operativo('EN_CURSO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertFalse(puede_eliminar($op, self::lima('2026-10-17 15:30:00'), false));
    }

    public function test_admin_elimina_en_cualquier_momento(): void
    {
        $op = self::operativo('FINALIZADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertTrue(puede_eliminar($op, self::lima('2026-10-20 10:00:00'), true));
    }

    // --- paso_hora_inicio, puede_registrar_punto, puede_finalizar -----------

    public function test_paso_hora_inicio_incluye_el_segundo_exacto(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertFalse(paso_hora_inicio($op, self::lima('2026-10-17 15:59:59')));
        $this->assertTrue(paso_hora_inicio($op, self::lima('2026-10-17 16:00:00')));
    }

    public function test_puntos_y_cierre_solo_en_curso(): void
    {
        foreach (['PROGRAMADO' => false, 'EN_CURSO' => true, 'FINALIZADO' => false] as $estado => $esperado) {
            $op = self::operativo($estado, '2026-10-17 16:00:00', '2026-10-17 18:00:00');
            $this->assertSame($esperado, puede_registrar_punto($op), $estado);
            $this->assertSame($esperado, puede_finalizar($op), $estado);
        }
    }

    // --- campos_editables ---------------------------------------------------

    public function test_programado_antes_del_inicio_permite_editar_todo(): void
    {
        $campos = campos_editables('PROGRAMADO', false);
        foreach (['fecha_servicio', 'hora_inicio', 'hora_fin', 'tipos', 'lugar_programado', 'fuerzas_propias'] as $campo) {
            $this->assertContains($campo, $campos);
        }
    }

    public function test_programado_despues_del_inicio_bloquea_fecha_y_horas(): void
    {
        $campos = campos_editables('PROGRAMADO', true);
        foreach (['fecha_servicio', 'hora_inicio', 'hora_fin'] as $campo) {
            $this->assertNotContains($campo, $campos);
        }
        $this->assertContains('lugar_programado', $campos);
    }

    public function test_en_curso_solo_fuerzas_jefe_y_observaciones(): void
    {
        $this->assertEqualsCanonicalizing(
            ['jefe_grado', 'jefe_nombres', 'jefe_celular', 'fuerzas_propias', 'fuerzas_apoyo', 'unidades_moviles', 'observaciones'],
            campos_editables('EN_CURSO', true),
        );
    }

    public function test_finalizado_no_permite_editar_nada(): void
    {
        $this->assertSame([], campos_editables('FINALIZADO', true));
    }

    public function test_campos_de_estado_desconocido_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        campos_editables('ANULADO', false);
    }

    // --- nota informativa ---------------------------------------------------

    public static function notas(): array
    {
        return [
            'solo dígitos'          => ['202601802185', '202601802185'],
            'con el texto completo' => ['NOTA INFORMATIVA N° 202601802185', '202601802185'],
            'con espacios y guiones' => [' 2026-018 02185 ', '202601802185'],
            'vacía'                 => ['', null],
            'sin dígitos'           => ['pendiente', null],
            'exactamente 20 dígitos' => [str_repeat('1', 20), str_repeat('1', 20)],
        ];
    }

    #[DataProvider('notas')]
    public function test_normaliza_la_nota_informativa(string $texto, ?string $esperado): void
    {
        $this->assertSame($esperado, normalizar_nota_informativa($texto));
    }

    public function test_rechaza_notas_de_mas_de_20_digitos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        normalizar_nota_informativa(str_repeat('1', 21));
    }

    public function test_formatea_la_nota_informativa(): void
    {
        $this->assertSame('NOTA INFORMATIVA N° 202601802185', formatear_nota_informativa('202601802185'));
    }

    // --- normalizar_celular -------------------------------------------------

    public static function celulares(): array
    {
        return [
            ['987654321', '987654321'],
            ['987 654 321', '987654321'],
            ['987-654-321', '987654321'],
            ['+51 987 654 321', '987654321'],
            ['51987654321', '987654321'],
            ['87654321', null],
            ['887654321', null],
            ['9876543210', null],
            ['044-123456', null],
            ['', null],
        ];
    }

    #[DataProvider('celulares')]
    public function test_normaliza_el_celular(string $texto, ?string $esperado): void
    {
        $this->assertSame($esperado, normalizar_celular($texto));
    }

    // --- validar_entero -----------------------------------------------------

    public function test_valida_enteros_de_formulario(): void
    {
        $this->assertSame(3, validar_entero('3', 0, 10));
        $this->assertSame(0, validar_entero(' 0 ', 0, 10));
        $this->assertNull(validar_entero('-1', 0, 10));
        $this->assertNull(validar_entero('11', 0, 10));
        $this->assertNull(validar_entero('2.5', 0, 10));
        $this->assertNull(validar_entero('', 0, 10));
        $this->assertNull(validar_entero(null, 0, 10));
        $this->assertNull(validar_entero('tres', 0, 10));
    }

    // --- validar_programacion -----------------------------------------------

    private static function entrada_valida(): array
    {
        return [
            'tipos'            => ['1', '3'],
            'nombre_plan'      => '  Saltamonte 2026 ',
            'lugar_programado' => 'Av. España con Jr. Grau',
            'jefe_grado'       => 'CAP',
            'jefe_nombres'     => 'Juan Pérez Rodríguez',
            'jefe_celular'     => '987 654 321',
            'fuerzas_propias'  => '12',
            'fuerzas_apoyo'    => '0',
            'unidades_moviles' => '2',
            'observaciones'    => '',
        ];
    }

    public function test_programacion_valida_devuelve_datos_normalizados(): void
    {
        $r = validar_programacion(self::entrada_valida(), [1, 2, 3]);
        $this->assertSame([], $r['errores']);
        $this->assertSame([1, 3], $r['datos']['tipos']);
        $this->assertSame('Saltamonte 2026', $r['datos']['nombre_plan']);
        $this->assertSame('987654321', $r['datos']['jefe_celular']);
        $this->assertSame(12, $r['datos']['fuerzas_propias']);
        $this->assertNull($r['datos']['observaciones']);
    }

    public function test_coordenadas_del_lugar_son_opcionales(): void
    {
        $r = validar_programacion(['lugar_coordenadas' => '  '] + self::entrada_valida(), [1, 2, 3]);
        $this->assertSame([], $r['errores']);
        $this->assertNull($r['datos']['lugar_latitud']);
        $this->assertNull($r['datos']['lugar_longitud']);
    }

    public function test_coordenadas_del_lugar_pegadas_de_google_maps(): void
    {
        $r = validar_programacion(['lugar_coordenadas' => 'https://www.google.com/maps/@-8.1116,-79.0288,17z'] + self::entrada_valida(), [1, 2, 3]);
        $this->assertSame([], $r['errores']);
        $this->assertSame(-8.1116, $r['datos']['lugar_latitud']);
        $this->assertSame(-79.0288, $r['datos']['lugar_longitud']);
    }

    public function test_coordenadas_del_lugar_invalidas_o_fuera_de_la_libertad(): void
    {
        $this->assertArrayHasKey('lugar_coordenadas', validar_programacion(['lugar_coordenadas' => 'Av. España'] + self::entrada_valida(), [1, 2, 3])['errores']);
        $this->assertArrayHasKey('lugar_coordenadas', validar_programacion(['lugar_coordenadas' => '8.1116, 79.0288'] + self::entrada_valida(), [1, 2, 3])['errores']);
    }

    public function test_en_curso_no_cambia_las_coordenadas_del_lugar(): void
    {
        $this->assertNotContains('lugar_coordenadas', campos_editables('EN_CURSO', true));
        $this->assertContains('lugar_coordenadas', campos_editables('PROGRAMADO', true));
    }

    public function test_formatea_coordenadas_para_mostrar_y_reeditar(): void
    {
        $this->assertSame('-8.111600, -79.028800', formatear_coordenadas('-8.1116000', '-79.0288000'));
        $this->assertSame('', formatear_coordenadas(null, null));
        $this->assertSame([-8.1116, -79.0288], parsear_coordenadas(formatear_coordenadas(-8.1116, -79.0288)));
    }

    public function test_nombre_del_plan_vacio_queda_null(): void
    {
        $entrada = ['nombre_plan' => '   '] + self::entrada_valida();
        $this->assertNull(validar_programacion($entrada, [1, 2, 3])['datos']['nombre_plan']);
    }

    public function test_programacion_con_errores(): void
    {
        $entrada = [
            'tipos'            => [],
            'lugar_programado' => '  ',
            'jefe_grado'       => 'SARGENTO',
            'jefe_nombres'     => '',
            'jefe_celular'     => '12345',
            'fuerzas_propias'  => '0',
            'fuerzas_apoyo'    => '-1',
            'unidades_moviles' => 'dos',
        ] + self::entrada_valida();
        $errores = validar_programacion($entrada, [1, 2, 3])['errores'];
        foreach (['tipos', 'lugar_programado', 'jefe_grado', 'jefe_nombres', 'jefe_celular', 'fuerzas_propias', 'fuerzas_apoyo', 'unidades_moviles'] as $campo) {
            $this->assertArrayHasKey($campo, $errores, $campo);
        }
    }

    public function test_rechaza_tipos_que_no_estan_en_el_catalogo(): void
    {
        $entrada = ['tipos' => ['1', '99']] + self::entrada_valida();
        $this->assertArrayHasKey('tipos', validar_programacion($entrada, [1, 2, 3])['errores']);
    }

    public function test_rechaza_textos_demasiado_largos(): void
    {
        $entrada = ['lugar_programado' => str_repeat('á', 256), 'nombre_plan' => str_repeat('a', 151)] + self::entrada_valida();
        $errores = validar_programacion($entrada, [1, 2, 3])['errores'];
        $this->assertArrayHasKey('lugar_programado', $errores);
        $this->assertArrayHasKey('nombre_plan', $errores);
    }

    public function test_255_caracteres_con_tilde_son_validos(): void
    {
        $entrada = ['lugar_programado' => str_repeat('á', 255)] + self::entrada_valida();
        $this->assertArrayNotHasKey('lugar_programado', validar_programacion($entrada, [1, 2, 3])['errores']);
    }

    public function test_solo_valida_los_campos_indicados(): void
    {
        $r = validar_programacion(['fuerzas_propias' => '5'], [1], ['fuerzas_propias']);
        $this->assertSame([], $r['errores']);
        $this->assertSame(['fuerzas_propias' => 5], $r['datos']);
    }

    public function test_todos_los_grados_son_validos(): void
    {
        foreach (GRADOS as $grado) {
            $entrada = ['jefe_grado' => $grado] + self::entrada_valida();
            $this->assertArrayNotHasKey('jefe_grado', validar_programacion($entrada, [1, 3])['errores'], $grado);
        }
    }

    // --- resultados ---------------------------------------------------------

    public function test_cantidades_vacias_o_ausentes_son_cero(): void
    {
        $r = validar_cantidades_resultado([1 => '2', 2 => ''], [1, 2, 3]);
        $this->assertSame([], $r['errores']);
        $this->assertSame([1 => 2, 2 => 0, 3 => 0], $r['datos']);
    }

    public function test_cantidades_negativas_o_no_enteras_son_error(): void
    {
        $r = validar_cantidades_resultado([1 => '-1', 2 => '1.5', 3 => 'x'], [1, 2, 3]);
        $this->assertSame([1, 2, 3], array_keys($r['errores']));
    }

    public function test_ignora_conceptos_que_no_estan_activos(): void
    {
        $r = validar_cantidades_resultado([1 => '2', 99 => '5'], [1]);
        $this->assertSame([1 => 2], $r['datos']);
    }

    public function test_resultado_negativo_si_todo_es_cero(): void
    {
        $this->assertTrue(es_resultado_negativo([1 => 0, 2 => 0]));
        $this->assertTrue(es_resultado_negativo([]));
        $this->assertFalse(es_resultado_negativo([1 => 0, 2 => 1]));
    }

    // --- bloqueo de cuentas -------------------------------------------------

    public function test_cuatro_intentos_fallidos_no_bloquean(): void
    {
        $r = calcular_intento_fallido(3, self::lima('2026-10-17 10:00:00'));
        $this->assertSame(4, $r['intentos']);
        $this->assertNull($r['bloqueado_hasta']);
    }

    public function test_el_quinto_intento_bloquea_15_min_y_reinicia_el_contador(): void
    {
        $r = calcular_intento_fallido(4, self::lima('2026-10-17 10:00:00'));
        $this->assertSame(0, $r['intentos']);
        $this->assertSame('2026-10-17 10:15:00', $r['bloqueado_hasta']->format(FORMATO_FECHA_HORA));
    }

    public function test_cuenta_bloqueada_hasta_la_hora_indicada(): void
    {
        $this->assertTrue(cuenta_bloqueada('2026-10-17 10:15:00', self::lima('2026-10-17 10:14:59')));
        $this->assertFalse(cuenta_bloqueada('2026-10-17 10:15:00', self::lima('2026-10-17 10:15:00')));
        $this->assertFalse(cuenta_bloqueada(null, self::lima('2026-10-17 10:00:00')));
    }

    // --- claves -------------------------------------------------------------

    public function test_valida_el_largo_de_la_clave(): void
    {
        $this->assertNotNull(validar_clave('123456789'));
        $this->assertNull(validar_clave('1234567890'));
        $this->assertNull(validar_clave('ñandú-áéíó'));
        $this->assertNotNull(validar_clave(str_repeat('a', 73)));
    }

    public function test_clave_temporal_sin_caracteres_confusos(): void
    {
        $claves = [];
        for ($i = 0; $i < 50; $i++) {
            $clave = generar_clave_temporal();
            $this->assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{12}$/', $clave);
            $this->assertNull(validar_clave($clave));
            $claves[] = $clave;
        }
        $this->assertCount(50, array_unique($claves));
    }

    // --- validar_unidad -----------------------------------------------------

    public function test_unidad_valida(): void
    {
        $r = validar_unidad(['division' => 'DUE', 'nombre' => ' HALCONES TRUJILLO ', 'nombre_corto' => 'HALCONES', 'orden' => '22', 'activo' => '1']);
        $this->assertSame([], $r['errores']);
        $this->assertSame(['division' => 'DUE', 'nombre' => 'HALCONES TRUJILLO', 'nombre_corto' => 'HALCONES', 'orden' => 22, 'activo' => 1], $r['datos']);
    }

    public function test_unidad_sin_marcar_activo_queda_inactiva(): void
    {
        $r = validar_unidad(['division' => 'DUE', 'nombre' => 'UPROVE', 'nombre_corto' => 'UPROVE', 'orden' => '0']);
        $this->assertSame(0, $r['datos']['activo']);
    }

    public function test_unidad_con_errores(): void
    {
        $r = validar_unidad(['division' => 'DIVPOL', 'nombre' => '', 'nombre_corto' => str_repeat('x', 61), 'orden' => '-1']);
        $this->assertEqualsCanonicalizing(['division', 'nombre', 'nombre_corto', 'orden'], array_keys($r['errores']));
    }

    // --- validar_usuario ----------------------------------------------------

    public function test_usuario_unidad_con_unidad_asignada(): void
    {
        $r = validar_usuario(['usuario' => ' Com.Ayacucho ', 'nombre' => 'Comisaría Ayacucho', 'rol' => 'UNIDAD', 'unidad_id' => '1', 'activo' => '1'], [1, 2], true);
        $this->assertSame([], $r['errores']);
        $this->assertSame('com.ayacucho', $r['datos']['usuario']);
        $this->assertSame(1, $r['datos']['unidad_id']);
        $this->assertArrayNotHasKey('clave', $r['datos']);
    }

    public function test_usuario_unidad_sin_unidad_es_error(): void
    {
        $this->assertArrayHasKey('unidad_id', validar_usuario(['usuario' => 'abc', 'nombre' => 'X', 'rol' => 'UNIDAD', 'unidad_id' => ''], [1], true)['errores']);
        $this->assertArrayHasKey('unidad_id', validar_usuario(['usuario' => 'abc', 'nombre' => 'X', 'rol' => 'UNIDAD', 'unidad_id' => '9'], [1], true)['errores']);
    }

    public function test_admin_y_supervisor_no_llevan_unidad(): void
    {
        foreach (['ADMIN', 'SUPERVISOR'] as $rol) {
            $r = validar_usuario(['usuario' => 'abc', 'nombre' => 'X', 'rol' => $rol, 'unidad_id' => '1'], [1], false);
            $this->assertSame([], $r['errores'], $rol);
            $this->assertNull($r['datos']['unidad_id'], $rol);
        }
    }

    public function test_usuario_con_errores(): void
    {
        $r = validar_usuario(['usuario' => 'ab', 'nombre' => '', 'rol' => 'GENERAL', 'clave' => 'corta'], [], true);
        $this->assertEqualsCanonicalizing(['usuario', 'nombre', 'rol', 'clave'], array_keys($r['errores']));
    }

    public function test_usuario_rechaza_tildes_y_espacios(): void
    {
        $this->assertArrayHasKey('usuario', validar_usuario(['usuario' => 'comisaría', 'nombre' => 'X', 'rol' => 'ADMIN'], [], false)['errores']);
        $this->assertArrayHasKey('usuario', validar_usuario(['usuario' => 'com ayacucho', 'nombre' => 'X', 'rol' => 'ADMIN'], [], false)['errores']);
    }

    public function test_la_clave_solo_se_valida_al_crear(): void
    {
        $r = validar_usuario(['usuario' => 'abc', 'nombre' => 'X', 'rol' => 'ADMIN', 'clave' => 'corta'], [], false);
        $this->assertSame([], $r['errores']);
        $this->assertArrayNotHasKey('clave', $r['datos']);

        $r = validar_usuario(['usuario' => 'abc', 'nombre' => 'X', 'rol' => 'ADMIN', 'clave' => 'ClaveSegura2026'], [], true);
        $this->assertSame('ClaveSegura2026', $r['datos']['clave']);
    }

    // --- validar_elemento_catalogo ------------------------------------------

    public function test_elemento_de_catalogo_en_mayusculas(): void
    {
        $r = validar_elemento_catalogo(['nombre' => ' control de identidad ', 'orden' => '1', 'activo' => '1'], 100, false);
        $this->assertSame([], $r['errores']);
        $this->assertSame(['nombre' => 'CONTROL DE IDENTIDAD', 'orden' => 1, 'activo' => 1], $r['datos']);
    }

    public function test_mayusculas_con_tildes_y_enie(): void
    {
        $r = validar_elemento_catalogo(['nombre' => 'zonas rígidas y señales', 'orden' => '8'], 100, false);
        $this->assertSame('ZONAS RÍGIDAS Y SEÑALES', $r['datos']['nombre']);
    }

    public function test_concepto_con_descripcion(): void
    {
        $r = validar_elemento_catalogo(['nombre' => 'Requisitoriados', 'descripcion' => ' Personas con RQ ', 'orden' => '2'], 120, true);
        $this->assertSame('Personas con RQ', $r['datos']['descripcion']);
        $r = validar_elemento_catalogo(['nombre' => 'Requisitoriados', 'descripcion' => '', 'orden' => '2'], 120, true);
        $this->assertNull($r['datos']['descripcion']);
    }

    public function test_elemento_de_catalogo_con_errores(): void
    {
        $r = validar_elemento_catalogo(['nombre' => str_repeat('a', 101), 'orden' => 'x', 'descripcion' => str_repeat('a', 256)], 100, true);
        $this->assertEqualsCanonicalizing(['nombre', 'orden', 'descripcion'], array_keys($r['errores']));
        $this->assertArrayHasKey('nombre', validar_elemento_catalogo(['nombre' => '  ', 'orden' => '1'], 100, false)['errores']);
    }

    // --- calcular_cambios ---------------------------------------------------

    public function test_solo_devuelve_los_campos_que_cambiaron(): void
    {
        $antes = ['nombre' => 'COM. MOCHE', 'orden' => '14', 'activo' => '1', 'division' => 'DIVOPUS'];
        $despues = ['nombre' => 'COM. MOCHE', 'orden' => 15, 'activo' => 1];
        $this->assertSame(['orden' => ['antes' => '14', 'despues' => 15]], calcular_cambios($antes, $despues));
    }

    public function test_distingue_null_de_texto_vacio_y_de_cero(): void
    {
        $this->assertSame(
            ['descripcion' => ['antes' => null, 'despues' => '']],
            calcular_cambios(['descripcion' => null], ['descripcion' => '']),
        );
        $this->assertSame([], calcular_cambios(['unidad_id' => null], ['unidad_id' => null]));
        $this->assertSame(
            ['unidad_id' => ['antes' => null, 'despues' => 0]],
            calcular_cambios(['unidad_id' => null], ['unidad_id' => 0]),
        );
    }

    public function test_compara_listas_sin_importar_el_orden(): void
    {
        $this->assertSame([], calcular_cambios(['tipos' => [3, 1]], ['tipos' => ['1', '3']]));
        $this->assertArrayHasKey('tipos', calcular_cambios(['tipos' => [1]], ['tipos' => [1, 2]]));
    }

    public function test_campo_nuevo_cuenta_como_cambio(): void
    {
        $this->assertSame(['activo' => ['antes' => null, 'despues' => 1]], calcular_cambios([], ['activo' => 1]));
    }

    // --- calcular_rango_turno -----------------------------------------------

    public static function rangos_de_turno(): array
    {
        return [
            ['MANANA', '2026-10-17 06:00:00', '2026-10-17 11:59:00'],
            ['TARDE', '2026-10-17 12:00:00', '2026-10-17 17:59:00'],
            ['NOCHE', '2026-10-17 18:00:00', '2026-10-17 23:59:00'],
            ['MADRUGADA', '2026-10-18 00:00:00', '2026-10-18 05:59:00'],
        ];
    }

    #[DataProvider('rangos_de_turno')]
    public function test_rango_de_inicio_de_cada_turno(string $turno, string $desde, string $hasta): void
    {
        $rango = calcular_rango_turno('2026-10-17', $turno);
        $this->assertSame($desde, $rango['desde']->format(FORMATO_FECHA_HORA));
        $this->assertSame($hasta, $rango['hasta']->format(FORMATO_FECHA_HORA));
    }

    public function test_los_limites_del_rango_coinciden_con_clasificar_turno(): void
    {
        foreach (TURNOS as $turno) {
            $rango = calcular_rango_turno('2026-10-17', $turno);
            $this->assertSame($turno, clasificar_turno($rango['desde']->format('H:i')));
            $this->assertSame($turno, clasificar_turno($rango['hasta']->format('H:i')));
            $this->assertEquals($rango['desde'], calcular_horario_turno('2026-10-17', $rango['desde']->format('H:i'), '23:00')['inicio']);
        }
    }

    public function test_rango_de_turno_desconocido_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        calcular_rango_turno('2026-10-17', 'MEDIODIA');
    }

    public function test_formatea_la_hora(): void
    {
        $this->assertSame('02:05', formatear_hora('2026-10-18 02:05:59'));
    }

    // --- etiquetas y colores de estado --------------------------------------

    public function test_cada_estado_visible_tiene_etiqueta_y_color(): void
    {
        foreach (['SIN_PROGRAMAR', 'PROGRAMADO', 'EN_CURSO', 'NO_INICIADO', 'SIN_CIERRE', 'FINALIZADO'] as $estado) {
            $this->assertNotSame('', obtener_etiqueta_estado($estado));
            $this->assertNotSame('', obtener_clase_estado($estado));
        }
        $this->assertSame('bg-naranja', obtener_clase_estado('SIN_CIERRE'));
        $this->assertSame('text-bg-warning', obtener_clase_estado('SIN_PROGRAMAR'));
        $this->expectException(InvalidArgumentException::class);
        obtener_clase_estado('ANULADO');
    }

    // --- validar_ubicacion --------------------------------------------------

    private static function ubicacion(array $cambios = []): array
    {
        return $cambios + [
            'latitud'     => -8.1116,
            'longitud'    => -79.0288,
            'precision_m' => 8.4,
            'origen'      => 'GPS',
            'referencia'  => ' Av. España con Jr. Grau ',
            'id_envio'    => '3f2b8c1e-7a4d-4e1f-9b0a-2c5d6e7f8a9b',
        ];
    }

    public function test_ubicacion_gps_valida(): void
    {
        $r = validar_ubicacion(self::ubicacion(), true, true);
        $this->assertSame([], $r['errores']);
        $this->assertSame(8, $r['datos']['precision_m']);
        $this->assertSame('Av. España con Jr. Grau', $r['datos']['referencia']);
    }

    public function test_coordenadas_como_texto_del_formulario(): void
    {
        $r = validar_ubicacion(self::ubicacion(['latitud' => '-8.1116', 'longitud' => '-79.0288']), true, true);
        $this->assertSame([], $r['errores']);
        $this->assertSame(-8.1116, $r['datos']['latitud']);
    }

    public function test_ubicacion_manual_no_guarda_precision(): void
    {
        $r = validar_ubicacion(self::ubicacion(['origen' => 'MANUAL', 'precision_m' => 25]), true, true);
        $this->assertSame([], $r['errores']);
        $this->assertNull($r['datos']['precision_m']);
    }

    public function test_precision_muy_grande_se_recorta(): void
    {
        $r = validar_ubicacion(self::ubicacion(['precision_m' => 120000]), true, true);
        $this->assertSame(MAX_PRECISION_GUARDADA_M, $r['datos']['precision_m']);
    }

    public function test_rechaza_ubicacion_fuera_de_la_libertad_para_un_operativo(): void
    {
        $r = validar_ubicacion(self::ubicacion(['latitud' => 8.1116, 'longitud' => 79.0288]), true, true);
        $this->assertArrayHasKey('ubicacion', $r['errores']);
    }

    public function test_el_supervisor_puede_estar_fuera_del_rectangulo_y_sin_referencia(): void
    {
        $r = validar_ubicacion(self::ubicacion(['latitud' => -12.0464, 'longitud' => -77.0428, 'referencia' => '']), false, false);
        $this->assertSame([], $r['errores']);
        $this->assertArrayNotHasKey('referencia', $r['datos']);
    }

    public function test_ubicacion_con_errores(): void
    {
        $r = validar_ubicacion(['latitud' => 'abc', 'origen' => 'MAPA', 'precision_m' => -5, 'referencia' => '  '], true, true);
        $this->assertEqualsCanonicalizing(['ubicacion', 'origen', 'precision_m', 'referencia', 'id_envio'], array_keys($r['errores']));
    }

    public function test_latitud_imposible_es_error_aunque_no_se_exija_la_libertad(): void
    {
        $this->assertArrayHasKey('ubicacion', validar_ubicacion(self::ubicacion(['latitud' => -95]), false, false)['errores']);
    }

    public function test_id_de_envio_con_caracteres_raros_es_error(): void
    {
        $this->assertArrayHasKey('id_envio', validar_ubicacion(self::ubicacion(['id_envio' => "x'; DROP--"]), true, true)['errores']);
    }

    // --- calcular_estado_celda ----------------------------------------------

    private function celda(?array $operativo, string $turno, string $ahora): ?string
    {
        return calcular_estado_celda($operativo, '2026-10-17', $turno, self::lima($ahora), self::TOLERANCIA_NO_INICIADO, self::TOLERANCIA_SIN_CIERRE);
    }

    public function test_celda_vacia_es_sin_programar_cuando_ya_no_se_puede_programar(): void
    {
        $this->assertNull($this->celda(null, 'MANANA', '2026-10-17 11:58:59'));
        $this->assertNull($this->celda(null, 'MANANA', '2026-10-17 11:59:00'));
        $this->assertSame('SIN_PROGRAMAR', $this->celda(null, 'MANANA', '2026-10-17 11:59:01'));
    }

    public function test_celda_vacia_de_madrugada_vence_al_dia_siguiente(): void
    {
        $this->assertNull($this->celda(null, 'MADRUGADA', '2026-10-17 23:00:00'));
        $this->assertSame('SIN_PROGRAMAR', $this->celda(null, 'MADRUGADA', '2026-10-18 06:00:00'));
    }

    public function test_celda_con_operativo_usa_el_estado_visible(): void
    {
        $op = self::operativo('PROGRAMADO', '2026-10-17 16:00:00', '2026-10-17 18:00:00');
        $this->assertSame('PROGRAMADO', $this->celda($op, 'TARDE', '2026-10-17 15:00:00'));
        $this->assertSame('NO_INICIADO', $this->celda($op, 'TARDE', '2026-10-17 16:16:00'));
    }

    // --- operativo_controlable ----------------------------------------------

    public function test_se_controlan_operativos_del_servicio_vigente(): void
    {
        $op = ['fecha_servicio' => '2026-10-17', 'estado' => 'FINALIZADO'];
        $this->assertTrue(operativo_controlable($op, '2026-10-17'));
        $this->assertFalse(operativo_controlable($op, '2026-10-18'));
    }

    public function test_se_controla_uno_anterior_que_sigue_en_curso(): void
    {
        $this->assertTrue(operativo_controlable(['fecha_servicio' => '2026-10-16', 'estado' => 'EN_CURSO'], '2026-10-17'));
        $this->assertFalse(operativo_controlable(['fecha_servicio' => '2026-10-18', 'estado' => 'EN_CURSO'], '2026-10-17'));
    }

    // --- validar_control ----------------------------------------------------

    public function test_control_conforme_sin_observacion(): void
    {
        $r = validar_control(['resultado' => 'CONFORME', 'observacion' => '  ', 'id_envio' => 'abcdef012345']);
        $this->assertSame([], $r['errores']);
        $this->assertNull($r['datos']['observacion']);
    }

    public function test_control_con_observaciones_exige_observacion(): void
    {
        foreach (['CON_OBSERVACIONES', 'NO_ENCONTRADO'] as $resultado) {
            $this->assertArrayHasKey('observacion', validar_control(['resultado' => $resultado, 'observacion' => '', 'id_envio' => 'abcdef012345'])['errores'], $resultado);
        }
        $r = validar_control(['resultado' => 'NO_ENCONTRADO', 'observacion' => ' No estaban en el punto ', 'id_envio' => 'abcdef012345']);
        $this->assertSame([], $r['errores']);
        $this->assertSame('No estaban en el punto', $r['datos']['observacion']);
    }

    public function test_control_con_errores(): void
    {
        $r = validar_control(['resultado' => 'EXCELENTE', 'observacion' => str_repeat('a', 5001)]);
        $this->assertEqualsCanonicalizing(['resultado', 'observacion', 'id_envio'], array_keys($r['errores']));
    }

    // --- convertir_fecha_hora_local -----------------------------------------

    public function test_convierte_el_valor_de_datetime_local(): void
    {
        $this->assertSame('2026-10-17 12:05:00', convertir_fecha_hora_local('2026-10-17T12:05')->format(FORMATO_FECHA_HORA));
        $this->assertSame('2026-10-17 12:05:30', convertir_fecha_hora_local('2026-10-17T12:05:30')->format(FORMATO_FECHA_HORA));
        $this->assertNull(convertir_fecha_hora_local('2026-10-17 12:05'));
        $this->assertNull(convertir_fecha_hora_local('2026-02-30T12:05'));
        $this->assertNull(convertir_fecha_hora_local(''));
    }

    // --- validar_ejecucion --------------------------------------------------

    private function ejecucion(array $entrada, bool $con_puntos = true): array
    {
        return validar_ejecucion($entrada, $con_puntos, self::lima('2026-10-17 13:00:00'));
    }

    public function test_finalizado_valido(): void
    {
        $r = $this->ejecucion(['estado' => 'FINALIZADO', 'inicio_real' => '2026-10-17T08:00', 'fin_real' => '2026-10-17T11:00']);
        $this->assertSame([], $r['errores']);
        $this->assertSame(['estado' => 'FINALIZADO', 'inicio_real' => '2026-10-17 08:00:00', 'fin_real' => '2026-10-17 11:00:00'], $r['datos']);
    }

    public function test_reabrir_quita_el_fin_real(): void
    {
        $r = $this->ejecucion(['estado' => 'EN_CURSO', 'inicio_real' => '2026-10-17T08:00', 'fin_real' => '2026-10-17T11:00']);
        $this->assertSame([], $r['errores']);
        $this->assertNull($r['datos']['fin_real']);
    }

    public function test_programado_sin_puntos_borra_las_horas_reales(): void
    {
        $r = $this->ejecucion(['estado' => 'PROGRAMADO', 'inicio_real' => '2026-10-17T08:00'], false);
        $this->assertSame([], $r['errores']);
        $this->assertNull($r['datos']['inicio_real']);
    }

    public function test_no_vuelve_a_programado_si_tiene_puntos(): void
    {
        $this->assertArrayHasKey('estado', $this->ejecucion(['estado' => 'PROGRAMADO'], true)['errores']);
    }

    public function test_ejecucion_exige_horas_coherentes(): void
    {
        $this->assertArrayHasKey('inicio_real', $this->ejecucion(['estado' => 'EN_CURSO'])['errores']);
        $this->assertArrayHasKey('fin_real', $this->ejecucion(['estado' => 'FINALIZADO', 'inicio_real' => '2026-10-17T08:00'])['errores']);
        $this->assertArrayHasKey('fin_real', $this->ejecucion(['estado' => 'FINALIZADO', 'inicio_real' => '2026-10-17T08:00', 'fin_real' => '2026-10-17T07:59'])['errores']);
        $this->assertArrayHasKey('inicio_real', $this->ejecucion(['estado' => 'EN_CURSO', 'inicio_real' => '2026-10-17T13:01'])['errores']);
        $this->assertArrayHasKey('inicio_real', $this->ejecucion(['estado' => 'EN_CURSO', 'inicio_real' => 'ayer'])['errores']);
        $this->assertArrayHasKey('estado', $this->ejecucion(['estado' => 'ANULADO'])['errores']);
    }

    public function test_fin_igual_al_inicio_es_valido(): void
    {
        $r = $this->ejecucion(['estado' => 'FINALIZADO', 'inicio_real' => '2026-10-17T08:00', 'fin_real' => '2026-10-17T08:00']);
        $this->assertSame([], $r['errores']);
    }

    // --- validar_motivo -----------------------------------------------------

    public function test_el_motivo_es_obligatorio(): void
    {
        $this->assertNotNull(validar_motivo('   '));
        $this->assertNotNull(validar_motivo('abcd'));
        $this->assertNull(validar_motivo('Pedido del jefe de la DIVOPUS'));
        $this->assertNotNull(validar_motivo(str_repeat('a', 501)));
    }
}
