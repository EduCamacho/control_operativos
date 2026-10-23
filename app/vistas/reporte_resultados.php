<?php
/**
 * Cuerpo de "Mis resultados" de la UNIDAD (RN-22). Lo usan la pantalla /resultados y
 * el PDF, con los estilos de public/assets/css/reporte.css (solo tablas: Dompdf no
 * entiende flexbox ni grid). Espera $reporte (armar_resultados_unidad()),
 * $unidad_nombre, $generado_por y $en_pantalla (en el PDF no hay enlaces a la ficha).
 */
declare(strict_types=1);

$cifras = $reporte['cifras'];
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
                <div class="reporte-titulo">Resultados de operativos</div>
                <div class="reporte-subtitulo">
                    <?= e($unidad_nombre) ?> · Servicios del <?= e(formatear_periodo($reporte['desde'], $reporte['hasta'])) ?>
                </div>
            </td>
            <td class="reporte-generado">
                Generado el <?= e($reporte['generado']->format('d/m/Y H:i')) ?><br>
                por <?= e($generado_por) ?>
            </td>
        </tr>
    </table>
    <p class="reporte-nota">Solo operativos finalizados: los resultados se registran al finalizar.</p>

    <table class="reporte-tarjetas">
        <tr>
            <td class="tarjeta-principal">
                <span class="tarjeta-cifra"><?= $cifras['realizados'] ?></span>
                <span class="tarjeta-titulo">Operativos realizados</span>
                <span class="tarjeta-detalle">finalizados en el período</span>
            </td>
            <td>
                <span class="tarjeta-cifra"><?= $cifras['positivos'] ?></span>
                <span class="tarjeta-titulo">Positivo<?= $cifras['positivos'] === 1 ? '' : 's' ?></span>
                <span class="tarjeta-detalle">con algún resultado</span>
            </td>
            <td>
                <span class="tarjeta-cifra"><?= $cifras['negativos'] ?></span>
                <span class="tarjeta-titulo">Negativo<?= $cifras['negativos'] === 1 ? '' : 's' ?></span>
                <span class="tarjeta-detalle">todo en 0</span>
            </td>
            <td class="<?= $cifras['sin_nota'] > 0 ? 'tarjeta-alerta' : '' ?>">
                <span class="tarjeta-cifra"><?= $cifras['sin_nota'] ?></span>
                <span class="tarjeta-titulo">Sin N.I</span>
                <span class="tarjeta-detalle">falta la nota informativa</span>
            </td>
        </tr>
    </table>

    <h2 class="reporte-seccion">Resultados</h2>
    <?php
    $resultados = $reporte['resultados'];
    $texto_vacio = 'Ningún concepto con resultados en el período.';
    require RUTA_APP . '/vistas/reporte_casillas_resultados.php';
    ?>

    <h2 class="reporte-seccion">Operativos finalizados</h2>
    <?php if ($reporte['operativos'] === []): ?>
        <p class="reporte-vacio">No hay operativos finalizados en el período.</p>
    <?php else: ?>
        <div class="reporte-desplazable">
            <table class="reporte-tabla reporte-operativos">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Lugar</th>
                        <th>Resultados</th>
                        <th>Nota informativa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte['operativos'] as $operativo): ?>
                        <tr>
                            <td class="fecha">
                                <?php $fecha = validar_fecha($operativo['fecha_servicio'])->format('d/m/Y'); ?>
                                <?php if ($en_pantalla): ?>
                                    <a href="<?= e(armar_url('operativos/ver', ['id' => $operativo['id']])) ?>"><?= e($fecha) ?></a>
                                <?php else: ?>
                                    <?= e($fecha) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(obtener_etiqueta_turno($operativo['turno'])) ?><br>
                                <span class="secundario"><?= e(formatear_hora($operativo['inicio_programado'])) ?>–<?= e(formatear_hora($operativo['fin_programado'])) ?></span>
                            </td>
                            <td><?= e($operativo['lugar_programado']) ?></td>
                            <td>
                                <?php if ($operativo['detalle'] === []): ?>
                                    <span class="secundario">Negativo</span>
                                <?php else: ?>
                                    <?php foreach ($operativo['detalle'] as $logro): ?>
                                        <div><strong><?= $logro['cantidad'] ?></strong> <?= e($logro['nombre']) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (pendiente_nota($operativo)): ?>
                                    <span class="falta-nota">Sin N.I</span>
                                <?php else: ?>
                                    <?= e($operativo['nota_informativa']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
