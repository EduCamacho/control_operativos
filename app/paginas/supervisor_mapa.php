<?php
/**
 * SUPERVISOR: mapa de los operativos del servicio diario vigente (docs/04).
 * Es la única pantalla que carga la API de Google Maps (RN-21).
 * Se actualiza consultando /api/mapa cada 30 s. Tiene una vista de lista que
 * funciona aunque falte la clave de Google Maps o no cargue el mapa.
 */
declare(strict_types=1);

requiere_rol('SUPERVISOR');

$configuracion = [
    'urlDatos'    => armar_url('api/mapa'),
    'urlFicha'    => armar_url('supervisor/operativo'),
    'urlIngreso'  => armar_url('login'),
    'segundos'    => 30,
    'hayClave'    => GOOGLE_MAPS_API_KEY !== '',
    'mapId'       => GOOGLE_MAPS_MAP_ID,
    'centro'      => ['lat' => -8.1116, 'lng' => -79.0288],
    'colores'     => [
        'PROGRAMADO' => '#6c757d', 'EN_CURSO' => '#198754', 'NO_INICIADO' => '#dc3545',
        'SIN_CIERRE' => '#fd7e14', 'FINALIZADO' => '#0d6efd',
    ],
    'clases'      => CLASES_ESTADO,
    'turnos'      => ETIQUETAS_TURNO,
    'divisiones'  => DIVISIONES,
];

$titulo = 'Mapa';
$pagina_completa = true;
$scripts = ['assets/js/mapa.js'];
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="mapa-supervisor" x-data="mapaSupervisor(<?= e(json_encode($configuracion)) ?>)" x-init="iniciar()">
    <!-- Barra de controles -->
    <div class="mapa-barra d-flex flex-wrap align-items-center gap-2 px-2 py-2 border-bottom bg-body">
        <div class="me-auto lh-sm">
            <div class="fw-semibold small" x-text="servicio ? 'Servicio del ' + servicio : 'Cargando…'"></div>
            <div class="small text-body-secondary">
                <span x-show="!errorCarga" x-text="textoActualizado"></span>
                <span x-show="errorCarga" class="text-danger"><i class="bi bi-wifi-off"></i> <span x-text="errorCarga"></span></span>
            </div>
        </div>
        <select class="form-select form-select-sm w-auto" x-model="filtros.turno" aria-label="Filtrar por turno">
            <option value="">Todos los turnos</option>
            <template x-for="(etiqueta, clave) in config.turnos" :key="clave">
                <option :value="clave" x-text="etiqueta"></option>
            </template>
        </select>
        <select class="form-select form-select-sm w-auto" x-model="filtros.division" aria-label="Filtrar por división">
            <option value="">Todas las divisiones</option>
            <template x-for="(etiqueta, clave) in config.divisiones" :key="clave">
                <option :value="clave" x-text="etiqueta"></option>
            </template>
        </select>
        <div class="form-check form-switch small mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="ver-finalizados" x-model="filtros.finalizados">
            <label class="form-check-label" for="ver-finalizados">Finalizados</label>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Vista">
            <button type="button" class="btn" :class="vista === 'mapa' ? 'btn-dark' : 'btn-outline-dark'" @click="cambiarVista('mapa')">
                <i class="bi bi-map"></i> Mapa
            </button>
            <button type="button" class="btn" :class="vista === 'lista' ? 'btn-dark' : 'btn-outline-dark'" @click="cambiarVista('lista')">
                <i class="bi bi-list-ul"></i> Lista
            </button>
        </div>
    </div>

    <!-- Barra de alertas (RN-14) -->
    <button type="button" class="mapa-alertas btn w-100 rounded-0 text-start d-flex align-items-center gap-2"
            :class="alertas.length ? 'btn-danger' : 'btn-success'" @click="alertasAbiertas = !alertasAbiertas"
            :aria-expanded="alertasAbiertas">
        <i class="bi" :class="alertas.length ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'"></i>
        <span class="me-auto" x-show="alertas.length">
            <strong x-text="contar('NO_INICIADO')"></strong> no iniciado(s) ·
            <strong x-text="contar('SIN_CIERRE')"></strong> sin cierre
        </span>
        <span class="me-auto" x-show="!alertas.length">Sin alertas</span>
        <span x-show="alertas.length"><i class="bi" :class="alertasAbiertas ? 'bi-chevron-up' : 'bi-chevron-down'"></i></span>
    </button>
    <div class="mapa-lista-alertas list-group list-group-flush border-bottom" x-show="alertasAbiertas && alertas.length" x-cloak>
        <template x-for="op in alertas" :key="op.id">
            <button type="button" class="list-group-item list-group-item-action small" @click="abrirFicha(op)">
                <span class="badge" :class="config.clases[op.estado]" x-text="op.estado_etiqueta"></span>
                <strong x-text="op.unidad"></strong> · <span x-text="op.turno_etiqueta"></span>
                <span x-text="op.inicio_programado + '–' + op.fin_programado"></span>
                <div class="text-body-secondary" x-text="op.ubicacion ? op.ubicacion.referencia + ' (' + op.ubicacion.hace + ')' : 'Lugar programado: ' + op.lugar_programado"></div>
            </button>
        </template>
    </div>

    <!-- Mapa -->
    <div class="mapa-contenedor position-relative" x-show="vista === 'mapa'">
        <div x-ref="mapa" class="mapa-google"></div>
        <div class="mapa-aviso position-absolute top-50 start-50 translate-middle text-center p-3" x-show="avisoMapa" x-cloak>
            <i class="bi bi-map display-5 text-body-secondary"></i>
            <p class="mt-2 mb-2" x-text="avisoMapa"></p>
            <button type="button" class="btn btn-dark" @click="cambiarVista('lista')"><i class="bi bi-list-ul"></i> Ver la lista</button>
        </div>
        <div class="mapa-leyenda position-absolute bottom-0 start-0 m-2 p-2 bg-body rounded shadow-sm small">
            <template x-for="(color, estado) in config.colores" :key="estado">
                <div><span class="mapa-punto" :style="'background:' + color"></span> <span x-text="etiquetaEstado(estado)"></span></div>
            </template>
            <div class="border-top mt-1 pt-1"><span class="mapa-punto mapa-punto-programado"></span> Lugar programado (sin iniciar)</div>
        </div>
    </div>

    <!-- Lista -->
    <div class="mapa-lista p-2" x-show="vista === 'lista'" x-cloak>
        <p class="small text-body-secondary px-1" x-show="!visibles.length">No hay operativos con estos filtros.</p>
        <div class="list-group">
            <template x-for="op in visibles" :key="op.id">
                <button type="button" class="list-group-item list-group-item-action" @click="abrirFicha(op)">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong class="me-auto" x-text="op.unidad"></strong>
                        <span class="badge text-bg-light border" x-text="op.turno_etiqueta + (op.servicio_anterior ? ' (servicio anterior)' : '')"></span>
                        <span class="badge" :class="config.clases[op.estado]" x-text="op.estado_etiqueta"></span>
                        <span class="badge text-bg-light border border-primary text-primary" x-show="op.pendiente_nota">Sin N.I</span>
                    </div>
                    <div class="small text-body-secondary">
                        <span x-text="op.inicio_programado + '–' + op.fin_programado"></span> ·
                        <span x-text="op.ubicacion ? op.ubicacion.referencia + ' · ' + op.ubicacion.hace : 'Lugar programado: ' + op.lugar_programado"></span>
                        <span class="badge text-bg-warning" x-show="op.ubicacion && op.ubicacion.origen === 'MANUAL'">Manual</span>
                    </div>
                </button>
            </template>
        </div>
    </div>

    <!-- Ficha del operativo: panel inferior -->
    <div class="offcanvas offcanvas-bottom mapa-ficha" tabindex="-1" x-ref="panelFicha" aria-label="Ficha del operativo">
        <div class="offcanvas-header py-2">
            <span class="fw-semibold">Operativo</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <div x-show="cargandoFicha" class="d-flex align-items-center gap-2 text-body-secondary">
                <div class="spinner-border spinner-border-sm" role="status"></div> Cargando…
            </div>
            <div x-ref="contenidoFicha"></div>
        </div>
    </div>
</div>

<?php if (GOOGLE_MAPS_API_KEY !== ''): ?>
    <script>
        // La API llama a esta función al terminar de cargar; el componente la espera con el evento.
        window.alCargarGoogleMaps = function () {
            window.googleMapsListo = true;
            document.dispatchEvent(new CustomEvent('google-maps-listo'));
        };
    </script>
    <script async src="https://maps.googleapis.com/maps/api/js?key=<?= e(rawurlencode(GOOGLE_MAPS_API_KEY)) ?>&loading=async&libraries=marker&callback=alCargarGoogleMaps&language=es&region=PE"></script>
<?php endif; ?>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
