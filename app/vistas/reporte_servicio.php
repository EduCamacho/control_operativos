<?php
/**
 * Cuerpo del reporte del servicio diario. Lo usan la pantalla /reportes y el
 * PDF, con los mismos estilos (public/assets/css/reporte.css). Solo tablas y
 * CSS sencillo, porque Dompdf no entiende flexbox ni grid. Espera $reporte
 * (armar_reporte_servicio()), $generado_por y $con_estado: "Estado de los
 * operativos" solo lo ve el ADMIN (al General no le sirve, decisión de Eduardo).
 */
declare(strict_types=1);

$matriz = $reporte['matriz'];
$division_texto = $reporte['division'] === '' ? 'Todas las divisiones' : DIVISIONES[$reporte['division']];
$abreviado = ['MANANA' => 'MAÑANA', 'TARDE' => 'TARDE', 'NOCHE' => 'NOCHE', 'MADRUGADA' => 'MADRUG.'];
$orden_resumen = ['FINALIZADO', 'EN_CURSO', 'PROGRAMADO', 'NO_INICIADO', 'SIN_CIERRE', 'SIN_PROGRAMAR'];
?>
<div class="reporte">
    <table class="reporte-encabezado">
        <tr>
            <?php $logo = armar_logo_reporte(); // public/assets/img/logo.png; sin archivo, no se muestra ?>
            <?php if ($logo !== null): ?>
                <td class="reporte-logo"><img src="<?= e($logo) ?>" alt="Logo"></td>
            <?php endif; ?>
            <td>
                <div class="reporte-institucion">POLICÍA NACIONAL DEL PERÚ · REGPOL LA LIBERTAD</div>
                <div class="reporte-titulo">Reporte del servicio diario</div>
                <div class="reporte-subtitulo">
                    Servicio del día <?= e(formatear_servicio_diario(validar_fecha($reporte['fecha']))) ?> · <?= e($division_texto) ?>
                </div>
            </td>
            <td class="reporte-generado">
                Generado el <?= e($reporte['generado']->format('d/m/Y H:i')) ?><br>
                por <?= e($generado_por) ?>
            </td>
        </tr>
    </table>
    <p class="reporte-nota">Los estados corresponden a la hora en que se generó el reporte.</p>

    <!-- Operativos realizados (finalizados, en curso y sin cierre), en total y por división;
         cada división con su color, el mismo de sus filas en "Cumplimiento por unidad". -->
    <?php $cifras = $reporte['cifras']; ?>
    <table class="reporte-tarjetas">
        <tr>
            <td class="tarjeta-principal">
                <span class="tarjeta-cifra"><?= $cifras['realizados'] ?></span>
                <span class="tarjeta-titulo">Operativos realizados</span>
                <span class="tarjeta-detalle">finalizados, en curso y sin cierre</span>
            </td>
            <?php foreach ($cifras['divisiones'] as $clave => $realizados_division): ?>
                <td class="division-<?= e(strtolower($clave)) ?>">
                    <span class="tarjeta-cifra"><?= $realizados_division ?></span>
                    <span class="tarjeta-titulo"><?= e($clave) ?></span>
                    <span class="tarjeta-detalle">realizado<?= $realizados_division === 1 ? '' : 's' ?></span>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>

    <?php if ($con_estado): ?>
    <h2 class="reporte-seccion">Estado de los operativos</h2>
    <table class="reporte-resumen">
        <tr>
            <td><span class="reporte-cifra"><?= $matriz['operativos'] ?></span>operativos</td>
            <td><span class="reporte-cifra"><?= $reporte['unidades'] ?></span>unidades</td>
            <?php foreach ($orden_resumen as $estado): ?>
                <td class="estado-<?= e(strtolower($estado)) ?>">
                    <span class="reporte-cifra"><?= $matriz['totales'][$estado] ?></span><?= e(obtener_etiqueta_estado($estado)) ?><?= $estado === 'SIN_PROGRAMAR' ? ' (turnos)' : '' ?>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>
    <p class="reporte-linea">
        Finalizados con resultado <strong>positivo: <?= $reporte['positivos'] ?></strong> · negativo: <?= $reporte['negativos'] ?>
        · sin nota informativa: <?= $matriz['sin_nota'] ?>
    </p>
    <?php endif; ?>

    <h2 class="reporte-seccion">Resultados</h2>
    <?php $con_cantidad = array_filter($reporte['resultados'], static fn (array $r): bool => (int) $r['cantidad'] > 0); ?>
    <?php if ($con_cantidad === []): ?>
        <p class="reporte-vacio">Ningún concepto con resultados en este servicio diario.</p>
    <?php endif; ?>
    <!-- Una casilla por concepto, 3 por fila; los que quedaron en 0 van en gris. -->
    <table class="reporte-resultados">
        <?php foreach (array_chunk($reporte['resultados'], 3) as $fila_resultados): ?>
            <tr>
                <?php foreach ($fila_resultados as $resultado): ?>
                    <?php $cantidad = (int) $resultado['cantidad']; ?>
                    <td class="resultado<?= $cantidad > 0 ? ' con-cantidad' : '' ?>">
                        <img class="resultado-icono" alt=""
                             src="<?= e(armar_icono_resultado($resultado['icono'], $cantidad > 0 ? '#1f6f50' : '#aab2ba')) ?>">
                        <span class="resultado-cifra"><?= $cantidad ?></span>
                        <span class="resultado-nombre"><?= e($resultado['nombre']) ?></span>
                        <?php if ($resultado['descripcion'] !== null && $resultado['descripcion'] !== ''): ?>
                            <span class="resultado-ayuda"><?= e($resultado['descripcion']) ?></span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <?php for ($vacia = count($fila_resultados); $vacia < 3; $vacia++): ?>
                    <td class="resultado-relleno"></td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- Personal y recursos de los operativos realizados (los mismos de las casillas de arriba).
         Se suma lo que declaró cada operativo: las mismas personas o vehículos en dos operativos cuentan
         dos veces; por eso dice "suma por operativo" (decisión de Eduardo, 18/10/2026).
         Diseño propio, sin franja de color: no se confunde con las divisiones. -->
    <?php
    $fuerzas = $reporte['fuerzas'];
    $casillas_fuerzas = [
        ['users', $fuerzas['efectivos'], 'Efectivos empleados', 'propios y de apoyo · suma por operativo'],
        ['user-shield', $fuerzas['propias'], 'Propios', 'de las unidades'],
        ['users-plus', $fuerzas['apoyo'], 'De apoyo', 'de otras unidades'],
        ['car-suv', $fuerzas['moviles'], 'Unidades móviles', 'suma por operativo'],
    ];
    ?>
    <h2 class="reporte-seccion">Personal y recursos empleados</h2>
    <table class="reporte-fuerzas">
        <tr>
            <?php foreach ($casillas_fuerzas as $indice_fuerza => [$icono_fuerza, $cifra_fuerza, $titulo_fuerza, $detalle_fuerza]): ?>
                <?php $es_total = $indice_fuerza === 0; // la primera es el total: se distingue de las demás ?>
                <td class="fuerza<?= $es_total ? ' fuerza-total' : '' ?>">
                    <table class="fuerza-contenido">
                        <tr>
                            <td class="fuerza-icono"><img alt="" src="<?= e(armar_icono($icono_fuerza, $es_total ? '#ffffff' : '#1f6f50')) ?>"></td>
                            <td>
                                <span class="fuerza-cifra"><?= $cifra_fuerza ?></span>
                                <span class="fuerza-titulo"><?= e($titulo_fuerza) ?></span>
                                <span class="fuerza-detalle"><?= e($detalle_fuerza) ?></span>
                            </td>
                        </tr>
                    </table>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>
    <p class="reporte-linea reporte-fuerzas-division">
        <?php $partes = []; ?>
        <?php foreach ($fuerzas['divisiones'] as $clave => $de_division): ?>
            <?php $partes[] = '<span class="marca-division division-' . e(strtolower($clave)) . '"></span><strong>' . e($clave) . '</strong>: '
                . $de_division['efectivos'] . ' efectivo' . ($de_division['efectivos'] === 1 ? '' : 's')
                . ' y ' . $de_division['moviles'] . ' móvil' . ($de_division['moviles'] === 1 ? '' : 'es'); ?>
        <?php endforeach; ?>
        <?= implode(' · ', $partes) ?>
    </p>

    <h2 class="reporte-seccion">Cumplimiento por unidad</h2>
    <div class="reporte-desplazable">
        <table class="reporte-tabla reporte-matriz">
            <thead>
                <tr>
                    <th>Unidad</th>
                    <?php foreach (TURNOS as $turno): ?><th><?= e($abreviado[$turno]) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php // Agrupa por división siguiendo el orden de las unidades: si el ADMIN mezcla divisiones en el orden,
                      // el título se repite (lo avisa detectar_divisiones_partidas() en la lista de unidades).
                      $division_actual = null; ?>
                <?php foreach ($matriz['filas'] as $fila): ?>
                    <?php if ($reporte['division'] === '' && $fila['unidad']['division'] !== $division_actual): ?>
                        <?php $division_actual = $fila['unidad']['division']; ?>
                        <tr class="fila-division division-<?= e(strtolower($division_actual)) ?>"><td colspan="5"><?= e(DIVISIONES[$division_actual] ?? $division_actual) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td class="unidad"><?= e($fila['unidad']['nombre_corto']) ?></td>
                        <?php foreach (TURNOS as $turno): ?>
                            <?php $celda = $fila['turnos'][$turno]; ?>
                            <td>
                                <?php foreach ($celda['operativos'] as ['operativo' => $operativo, 'estado' => $estado]): ?>
                                    <div class="chip estado-<?= e(strtolower($estado)) ?>">
                                        <?= e(formatear_hora($operativo['inicio_programado'])) ?> <?= e(obtener_etiqueta_estado($estado)) ?><?= pendiente_nota($operativo) ? ' · sin N.I' : '' ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($celda['estado_vacio'] !== null): ?>
                                    <div class="chip estado-sin_programar">Sin programar</div>
                                <?php elseif ($celda['operativos'] === []): ?>
                                    <span class="vacio">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
