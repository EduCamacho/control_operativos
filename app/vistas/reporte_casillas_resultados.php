<?php
/**
 * Casillas de "Resultados", una por concepto y 3 por fila (2 en el celular); los que
 * quedaron en 0 van en gris. La usan el reporte del servicio diario y "Mis resultados",
 * en pantalla y en el PDF. Espera $resultados (nombre, descripcion, icono, cantidad)
 * y $texto_vacio (cuando ningún concepto tiene cantidad).
 */
declare(strict_types=1);

$con_cantidad = array_filter($resultados, static fn (array $r): bool => (int) $r['cantidad'] > 0);
?>
<?php if ($con_cantidad === []): ?>
    <p class="reporte-vacio"><?= e($texto_vacio) ?></p>
<?php endif; ?>
<table class="reporte-resultados">
    <?php foreach (array_chunk($resultados, 3) as $fila_resultados): ?>
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
