<?php
/**
 * Modal de captura de ubicación para iniciar el operativo o registrar un nuevo
 * punto (docs/04). Hoja inferior en el celular (sale desde abajo) y centrado en la PC.
 * Es estático (no se cierra al tocar fuera ni con Escape) y no se puede cerrar
 * mientras hay un envío pendiente, para no perder la ubicación capturada.
 * Va dentro de un elemento con x-data="capturaUbicacion(...)". Espera:
 *   $captura = ['titulo' => ..., 'boton' => ...]
 */
declare(strict_types=1);
?>
<div class="modal fade modal-captura" tabindex="-1" x-ref="modal" data-bs-backdrop="static" data-bs-keyboard="false"
     x-id="['captura']" :aria-labelledby="$id('captura') + '-titulo'" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-hoja-inferior">
        <div class="modal-content">
            <div class="modal-header encabezado-modal">
                <h2 class="modal-title h5" :id="$id('captura') + '-titulo'"><i class="bi bi-geo-alt-fill"></i> <?= e($captura['titulo']) ?></h2>
                <button type="button" class="btn-close btn-close-white" aria-label="Cerrar" @click="cancelar()" :disabled="bloqueado"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" :for="'referencia-' + $id('captura')">Referencia</label>
                    <input type="text" :id="'referencia-' + $id('captura')" x-ref="referencia" x-model="referencia" maxlength="255"
                           class="form-control form-control-lg" :class="errores.referencia && 'is-invalid'"
                           placeholder="Calle, avenida o sector" :disabled="bloqueado" @keydown.enter.prevent="fase === 'lista' && enviar()">
                    <div class="invalid-feedback" x-text="errores.referencia"></div>
                </div>

        <div aria-live="polite">
            <!-- Obteniendo el GPS -->
            <div x-show="fase === 'obteniendo'" class="fila-flex align-items-center gap-2 py-2">
                <div class="spinner-border spinner-border-sm texto-institucional" role="status"></div>
                <span>Obteniendo ubicación…</span>
            </div>

            <!-- Ubicación lista para enviar -->
            <div x-show="fase === 'lista' || fase === 'enviando' || fase === 'sin_conexion'" class="alert alert-success py-2 mb-3">
                <i class="bi bi-check-circle-fill"></i>
                <span x-text="textoUbicacion"></span>
                <a :href="enlaceMaps(latitud, longitud)" target="_blank" rel="noopener" class="ms-1 text-nowrap">
                    <i class="bi bi-geo-alt"></i> Ver en Google Maps
                </a>
            </div>

            <!-- Precisión peor que 100 m -->
            <div x-show="fase === 'imprecisa'" class="alert alert-warning mb-3">
                <p class="mb-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    La precisión es de <strong x-text="precision + ' m'"></strong> (lo recomendable es 100 m o menos).
                    Al aire libre el GPS suele mejorar en unos segundos.
                </p>
                <div class="d-grid gap-2 d-sm-flex">
                    <button type="button" class="btn btn-warning boton-accion" @click="obtenerGps()"><i class="bi bi-arrow-repeat"></i> Reintentar</button>
                    <button type="button" class="btn btn-outline-dark boton-accion" @click="usarDeTodosModos()">Usar de todos modos</button>
                    <button type="button" class="btn btn-outline-dark boton-accion" @click="pasarAManual()">Ingresar manualmente</button>
                </div>
            </div>

            <!-- GPS fuera de La Libertad -->
            <div x-show="fase === 'fuera'" class="alert alert-danger mb-3">
                <p class="mb-2"><i class="bi bi-x-octagon-fill"></i> El GPS indica una ubicación fuera de La Libertad.</p>
                <div class="d-grid gap-2 d-sm-flex">
                    <button type="button" class="btn btn-danger boton-accion" @click="obtenerGps()"><i class="bi bi-arrow-repeat"></i> Reintentar</button>
                    <button type="button" class="btn btn-outline-dark boton-accion" @click="pasarAManual()">Ingresar manualmente</button>
                </div>
            </div>

            <!-- El GPS falló -->
            <div x-show="fase === 'error'" class="alert alert-danger mb-3">
                <p class="fw-semibold mb-2"><i class="bi bi-geo-alt"></i> <span x-text="mensajeError"></span></p>
                <div x-show="permisoDenegado" class="small mb-2">
                    <p class="mb-1">Para activar la ubicación:</p>
                    <ul class="mb-1 ps-3">
                        <li><strong>Android (Chrome):</strong> toque el candado <i class="bi bi-lock"></i> junto a la dirección → <em>Permisos</em> → <em>Ubicación</em> → <em>Permitir</em>.</li>
                        <li><strong>iPhone (Safari):</strong> <em>Ajustes</em> → <em>Privacidad y seguridad</em> → <em>Localización</em> → <em>Safari</em> → <em>Al usar la app</em>.</li>
                        <li>Verifique también que la ubicación (GPS) del celular esté encendida.</li>
                    </ul>
                </div>
                <div class="d-grid gap-2 d-sm-flex">
                    <button type="button" class="btn btn-danger boton-accion" @click="obtenerGps()"><i class="bi bi-arrow-repeat"></i> Reintentar</button>
                    <button type="button" class="btn btn-outline-dark boton-accion" @click="pasarAManual()">Ingresar manualmente</button>
                </div>
            </div>

            <!-- Ingreso manual: coordenadas pegadas de Google Maps (RN-09) -->
            <div x-show="fase === 'manual'" class="border rounded p-3 mb-3 bg-body-tertiary">
                <label class="form-label fw-semibold" :for="'pegado-' + $id('captura')">Pegar coordenadas de Google Maps</label>
                <textarea :id="'pegado-' + $id('captura')" x-ref="textoPegado" x-model="textoPegado" rows="2" class="form-control"
                          placeholder="-8.111600, -79.028800" @paste="$nextTick(() => analizarTexto())" @change="analizarTexto()"></textarea>
                <div class="form-text mb-2">
                    En Google Maps, mantenga presionado el lugar (o haga clic derecho en la PC) y copie las coordenadas.
                    También puede pegar el enlace del lugar.
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small mb-0" :for="'latitud-' + $id('captura')">Latitud</label>
                        <input type="text" inputmode="decimal" :id="'latitud-' + $id('captura')" x-model="latitudManual" class="form-control" placeholder="-8.1116">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-0" :for="'longitud-' + $id('captura')">Longitud</label>
                        <input type="text" inputmode="decimal" :id="'longitud-' + $id('captura')" x-model="longitudManual" class="form-control" placeholder="-79.0288">
                    </div>
                </div>
                <div x-show="analizando" class="small text-body-secondary mb-2">Analizando…</div>
                <div x-show="errorManual" class="text-danger small mb-2" x-text="errorManual"></div>
                <div class="d-grid gap-2 d-sm-flex">
                    <button type="button" class="btn btn-institucional boton-accion" @click="confirmarManual()"><i class="bi bi-check-lg"></i> Usar estas coordenadas</button>
                    <a :href="enlaceMaps(latitudManual, longitudManual)" target="_blank" rel="noopener" class="btn btn-outline-secondary boton-accion"
                       :class="(latitudManual === '' || longitudManual === '') && 'disabled'"><i class="bi bi-geo-alt"></i> Ver en Google Maps</a>
                    <button type="button" class="btn btn-outline-secondary boton-accion" @click="obtenerGps()"><i class="bi bi-crosshair"></i> Volver a intentar con GPS</button>
                </div>
            </div>

            <!-- Sin conexión: se reintenta solo -->
            <div x-show="fase === 'sin_conexion'" class="alert alert-warning mb-3">
                <p class="mb-2">
                    <i class="bi bi-wifi-off"></i> <strong>Sin conexión:</strong> la ubicación quedó guardada en esta pantalla y
                    se enviará sola cuando vuelva la señal (se reintenta cada <span x-text="segundosReintento"></span> s).
                    No cierre esta página.
                </p>
                <button type="button" class="btn btn-warning boton-accion" @click="reintentarEnvio()"><i class="bi bi-arrow-repeat"></i> Reintentar ahora</button>
            </div>

            <div x-show="errores.general" class="alert alert-danger py-2 mb-3" x-text="errores.general"></div>
        </div>
            </div>
            <div class="modal-footer barra-modal">
                <button type="button" class="btn btn-outline-secondary boton-accion flex-fill" @click="cancelar()" :disabled="bloqueado">Cancelar</button>
                <button type="button" class="btn btn-institucional boton-accion flex-fill" :disabled="fase !== 'lista'" @click="enviar()">
                    <span x-show="fase === 'enviando'" class="spinner-border spinner-border-sm" role="status"></span>
                    <span x-text="fase === 'enviando' ? 'Enviando…' : <?= e(json_encode($captura['boton'])) ?>"></span>
                </button>
            </div>
        </div>
    </div>
</div>
