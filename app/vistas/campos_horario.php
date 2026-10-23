<?php
/**
 * Bloque "Cuándo" del formulario de programación: fecha de servicio, horas y
 * el turno calculado (RN-02). Necesita un componente Alpine con fecha,
 * horaInicio, horaFin, turno y resumen (assets/js/programacion.js).
 * Espera $valores, $errores, $deshabilitado, $fecha_minima y $fecha_maxima (RN-03).
 */
declare(strict_types=1);
?>
<div class="row g-2">
    <div class="col-12">
        <label for="fecha_servicio" class="form-label">Fecha de servicio</label>
        <input type="date" id="fecha_servicio" name="fecha_servicio" x-model="fecha" required
               <?= $fecha_minima === null ? '' : 'min="' . e($fecha_minima) . '"' ?>
               <?= ($fecha_maxima ?? null) === null ? '' : 'max="' . e($fecha_maxima) . '"' ?>
               class="form-control form-control-lg<?= clase_error($errores, 'fecha_servicio') ?>"
               value="<?= e($valores['fecha_servicio']) ?>"<?= $deshabilitado('fecha_servicio') ?>>
        <?= mostrar_error($errores, 'fecha_servicio') ?>
    </div>
    <div class="col-6">
        <label for="hora_inicio" class="form-label">Hora de inicio</label>
        <input type="time" id="hora_inicio" name="hora_inicio" x-model="horaInicio" required step="60"
               class="form-control form-control-lg<?= clase_error($errores, 'hora_inicio') ?>"
               value="<?= e($valores['hora_inicio']) ?>"<?= $deshabilitado('hora_inicio') ?>>
    </div>
    <div class="col-6">
        <label for="hora_fin" class="form-label">Hora de fin</label>
        <input type="time" id="hora_fin" name="hora_fin" x-model="horaFin" required step="60"
               class="form-control form-control-lg<?= clase_error($errores, 'hora_inicio') ?>"
               value="<?= e($valores['hora_fin']) ?>"<?= $deshabilitado('hora_fin') ?>>
    </div>
    <div class="col-12">
        <?php if (isset($errores['hora_inicio'])): ?>
            <div class="text-danger small mb-1"><?= e($errores['hora_inicio']) ?></div>
        <?php endif; ?>
        <div class="turno-calculado border rounded p-2 mt-1" x-show="turno" x-cloak aria-live="polite">
            <span class="badge text-bg-dark fs-6" x-text="turno"></span>
            <span class="small" x-text="resumen"></span>
        </div>
        <div class="form-text">Formato de 24 horas. El turno se calcula según la hora de inicio.</div>
    </div>
</div>
