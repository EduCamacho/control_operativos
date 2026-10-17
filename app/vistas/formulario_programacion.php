<?php
/**
 * Campos del formulario de programación, compartidos por la UNIDAD
 * (operativo_programar.php) y ADMIN (admin_operativo_formulario.php).
 * Va dentro del <form>. Espera:
 *   $valores           Valores actuales de los campos.
 *   $errores           Errores de validación por campo.
 *   $deshabilitado     fn(string $campo): string → ' disabled' si el campo no es editable.
 *   $tipos_formulario  Tipos de operativo para las casillas.
 *   $fecha_minima      Fecha mínima del selector, o null (ADMIN programa en el pasado).
 * Registra el componente Alpine formularioProgramacion (turno calculado al escribir la hora).
 */
declare(strict_types=1);
?>
    <div class="card card-body shadow-sm mb-3">
        <h2 class="h6">Fecha y horario</h2>
        <div class="row g-2">
            <div class="col-12">
                <label for="fecha_servicio" class="form-label">Fecha de servicio</label>
                <input type="date" id="fecha_servicio" name="fecha_servicio" x-model="fecha" required
                       <?= $fecha_minima === null ? "" : "min=\"" . e($fecha_minima) . "\"" ?> class="form-control<?= clase_error($errores, 'fecha_servicio') ?>"
                       value="<?= e($valores['fecha_servicio']) ?>"<?= $deshabilitado('fecha_servicio') ?>>
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
                <div class="alert alert-light border mb-0 py-2" x-show="turno" x-cloak aria-live="polite">
                    <span class="badge text-bg-dark" x-text="turno"></span>
                    <span x-text="resumen"></span>
                </div>
                <div class="form-text">Formato de 24 horas. El turno se calcula según la hora de inicio.</div>
            </div>
        </div>
    </div>

    <div class="card card-body shadow-sm mb-3">
        <h2 class="h6">Operativo</h2>
        <fieldset class="mb-3"<?= $deshabilitado('tipos') ?>>
            <legend class="form-label fs-6">Tipo(s) de operativo</legend>
            <div class="row row-cols-1 row-cols-sm-2 g-1">
                <?php foreach ($tipos_formulario as $tipo): ?>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input<?= clase_error($errores, 'tipos') ?>" type="checkbox" name="tipos[]"
                                   id="tipo-<?= e($tipo['id']) ?>" value="<?= e($tipo['id']) ?>"
                                   <?= marcar(in_array((int) $tipo['id'], array_map('intval', (array) $valores['tipos']), true)) ?>>
                            <label class="form-check-label" for="tipo-<?= e($tipo['id']) ?>">
                                <?= e($tipo['nombre']) ?><?= $tipo['activo'] ? '' : ' (inactivo)' ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errores['tipos'])): ?><div class="text-danger small mt-1"><?= e($errores['tipos']) ?></div><?php endif; ?>
        </fieldset>
        <div class="mb-3">
            <label for="nombre_plan" class="form-label">Nombre del plan <span class="text-body-secondary">(opcional)</span></label>
            <input type="text" id="nombre_plan" name="nombre_plan" maxlength="150"
                   class="form-control<?= clase_error($errores, 'nombre_plan') ?>" value="<?= e($valores['nombre_plan'] ?? '') ?>"
                   placeholder="Saltamonte 2026"<?= $deshabilitado('nombre_plan') ?>>
            <?= mostrar_error($errores, 'nombre_plan') ?>
        </div>
        <div class="mb-1">
            <label for="lugar_programado" class="form-label">Lugar programado</label>
            <input type="text" id="lugar_programado" name="lugar_programado" maxlength="255" required
                   class="form-control<?= clase_error($errores, 'lugar_programado') ?>" value="<?= e($valores['lugar_programado']) ?>"
                   placeholder="Av. España con Jr. Grau, sector Centro"<?= $deshabilitado('lugar_programado') ?>>
            <?= mostrar_error($errores, 'lugar_programado') ?>
        </div>
    </div>

    <div class="card card-body shadow-sm mb-3">
        <h2 class="h6">Jefe del operativo</h2>
        <div class="row g-2">
            <div class="col-4">
                <label for="jefe_grado" class="form-label">Grado</label>
                <select id="jefe_grado" name="jefe_grado" required class="form-select<?= clase_error($errores, 'jefe_grado') ?>"<?= $deshabilitado('jefe_grado') ?>>
                    <option value="">—</option>
                    <optgroup label="Oficiales">
                        <?php foreach (array_slice(GRADOS, 0, 7) as $grado): ?>
                            <option<?= marcar($valores['jefe_grado'] === $grado, 'selected') ?>><?= e($grado) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Suboficiales">
                        <?php foreach (array_slice(GRADOS, 7) as $grado): ?>
                            <option<?= marcar($valores['jefe_grado'] === $grado, 'selected') ?>><?= e($grado) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <?= mostrar_error($errores, 'jefe_grado') ?>
            </div>
            <div class="col-8">
                <label for="jefe_nombres" class="form-label">Nombres y apellidos</label>
                <input type="text" id="jefe_nombres" name="jefe_nombres" maxlength="150" required autocomplete="off"
                       class="form-control<?= clase_error($errores, 'jefe_nombres') ?>" value="<?= e($valores['jefe_nombres']) ?>"<?= $deshabilitado('jefe_nombres') ?>>
                <?= mostrar_error($errores, 'jefe_nombres') ?>
            </div>
            <div class="col-12">
                <label for="jefe_celular" class="form-label">Celular</label>
                <input type="tel" id="jefe_celular" name="jefe_celular" maxlength="15" required inputmode="tel" autocomplete="off"
                       class="form-control<?= clase_error($errores, 'jefe_celular') ?>" value="<?= e($valores['jefe_celular']) ?>"
                       placeholder="987654321"<?= $deshabilitado('jefe_celular') ?>>
                <?= mostrar_error($errores, 'jefe_celular') ?>
            </div>
        </div>
    </div>

    <div class="card card-body shadow-sm mb-3">
        <h2 class="h6">Fuerzas</h2>
        <?php foreach (['fuerzas_propias' => ['Fuerzas propias', 1], 'fuerzas_apoyo' => ['Fuerzas de apoyo', 0], 'unidades_moviles' => ['Unidades móviles', 0]] as $campo => [$etiqueta, $minimo]): ?>
            <?php $parametros = ['nombre' => $campo, 'etiqueta' => $etiqueta, 'valor' => $valores[$campo], 'minimo' => $minimo, 'maximo' => MAX_FUERZAS, 'deshabilitado' => $deshabilitado($campo) !== '', 'errores' => $errores]; ?>
            <?php require RUTA_APP . '/vistas/campo_contador.php'; ?>
        <?php endforeach; ?>
    </div>

    <div class="card card-body shadow-sm mb-3">
        <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
        <textarea id="observaciones" name="observaciones" rows="3" class="form-control"<?= $deshabilitado('observaciones') ?>><?= e($valores['observaciones'] ?? '') ?></textarea>
    </div>


<script>
    // Muestra el turno y el día real mientras se escriben las horas (la regla está en clasificar_turno()).
    document.addEventListener('alpine:init', function () {
        const DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        window.Alpine.data('formularioProgramacion', function (inicial) {
            return {
                fecha: inicial.fecha,
                horaInicio: inicial.horaInicio,
                horaFin: inicial.horaFin,
                minutos(hora) {
                    const partes = /^(\d{2}):(\d{2})/.exec(hora || '');
                    return partes ? Number(partes[1]) * 60 + Number(partes[2]) : null;
                },
                get turno() {
                    const m = this.minutos(this.horaInicio);
                    if (m === null) return '';
                    if (m < 360) return 'MADRUGADA';
                    if (m < 720) return 'MAÑANA';
                    if (m < 1080) return 'TARDE';
                    return 'NOCHE';
                },
                dia(desplazamiento) {
                    const d = new Date(this.fecha + 'T12:00:00');
                    d.setDate(d.getDate() + desplazamiento);
                    return DIAS[d.getDay()] + ' ' + String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
                },
                get resumen() {
                    if (!this.fecha || !this.turno) return '';
                    const inicio = this.minutos(this.horaInicio);
                    const fin = this.minutos(this.horaFin);
                    const diaInicio = this.turno === 'MADRUGADA' ? 1 : 0;
                    let texto = '· empieza el ' + this.dia(diaInicio) + ' a las ' + this.horaInicio;
                    if (fin !== null) {
                        if (fin === inicio) return '· la hora de fin debe ser distinta de la de inicio';
                        texto += ' y termina el ' + this.dia(diaInicio + (fin < inicio ? 1 : 0)) + ' a las ' + this.horaFin;
                    }
                    return texto;
                },
            };
        });
    });
</script>
