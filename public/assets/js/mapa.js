/**
 * Componente Alpine "mapaSupervisor": mapa del General (docs/04, RN-14, RN-21).
 *
 * - Consulta /api/mapa cada 30 s y muestra "Actualizado hace X s".
 * - Marcadores coloreados por estado con el nombre corto de la unidad.
 * - Filtros por turno y división; los finalizados se pueden ocultar.
 * - Barra de alertas con los NO_INICIADO y SIN_CIERRE.
 * - Al tocar un marcador abre la ficha (HTML que arma el servidor) y dibuja el recorrido.
 * - Vista de lista para cuando no hay clave de Google Maps o el mapa no carga.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    const ETIQUETAS = {
        PROGRAMADO: 'Programado', EN_CURSO: 'En curso', NO_INICIADO: 'No iniciado',
        SIN_CIERRE: 'Sin cierre', FINALIZADO: 'Finalizado',
    };

    window.Alpine.data('mapaSupervisor', function (config) {
        // Objetos de Google Maps fuera del estado reactivo de Alpine.
        let mapa = null;
        let recorrido = null;
        const marcadores = new Map();
        let primerEncuadre = true;

        return {
            config: config,
            operativos: [],
            servicio: '',
            ultimaCarga: null,
            ahora: Date.now(),
            errorCarga: '',
            filtros: { turno: '', division: '', finalizados: true },
            vista: config.hayClave ? 'mapa' : 'lista',
            avisoMapa: config.hayClave ? '' : 'Falta configurar la clave de Google Maps en app/config.php.',
            alertasAbiertas: false,
            cargandoFicha: false,
            panel: null,

            iniciar() {
                this.panel = new window.bootstrap.Offcanvas(this.$refs.panelFicha);
                this.$refs.panelFicha.addEventListener('hidden.bs.offcanvas', () => this.quitarRecorrido());
                this.cargar();
                setInterval(() => this.cargar(), config.segundos * 1000);
                setInterval(() => { this.ahora = Date.now(); }, 1000);
                this.$watch('filtros', () => this.actualizarMarcadores(), { deep: true });
                if (config.hayClave) {
                    if (window.googleMapsListo) {
                        this.crearMapa();
                    } else {
                        document.addEventListener('google-maps-listo', () => this.crearMapa(), { once: true });
                        setTimeout(() => {
                            if (!mapa) {
                                this.avisoMapa = 'No se pudo cargar Google Maps. Revise la conexión o la clave.';
                            }
                        }, 15000);
                    }
                }
            },

            async cargar() {
                try {
                    const respuesta = await fetch(config.urlDatos, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    if (respuesta.status === 401) {
                        window.location.href = config.urlIngreso;
                        return;
                    }
                    if (!respuesta.ok) {
                        throw new Error('HTTP ' + respuesta.status);
                    }
                    const datos = await respuesta.json();
                    this.operativos = datos.operativos;
                    this.servicio = datos.servicio_etiqueta;
                    this.ultimaCarga = Date.now();
                    this.errorCarga = '';
                    this.actualizarMarcadores();
                } catch (error) {
                    this.errorCarga = 'Sin conexión: se reintentará en ' + config.segundos + ' s';
                }
            },

            get textoActualizado() {
                if (!this.ultimaCarga) {
                    return 'Cargando…';
                }
                const segundos = Math.max(0, Math.round((this.ahora - this.ultimaCarga) / 1000));
                return 'Actualizado hace ' + segundos + ' s';
            },

            get visibles() {
                return this.operativos.filter((op) =>
                    (this.filtros.turno === '' || op.turno === this.filtros.turno)
                    && (this.filtros.division === '' || op.division === this.filtros.division)
                    && (this.filtros.finalizados || op.estado !== 'FINALIZADO'));
            },

            get alertas() {
                return this.operativos.filter((op) => op.estado === 'NO_INICIADO' || op.estado === 'SIN_CIERRE');
            },

            contar(estado) {
                return this.operativos.filter((op) => op.estado === estado).length;
            },

            etiquetaEstado(estado) {
                return ETIQUETAS[estado] || estado;
            },

            cambiarVista(vista) {
                this.vista = vista;
                if (vista === 'mapa' && mapa) {
                    this.$nextTick(() => window.google.maps.event.trigger(mapa, 'resize'));
                }
            },

            // --- Google Maps -------------------------------------------------

            crearMapa() {
                const opciones = {
                    center: config.centro,
                    zoom: 12,
                    gestureHandling: 'greedy',
                    streetViewControl: false,
                    mapTypeControl: false,
                    fullscreenControl: false,
                    clickableIcons: false,
                };
                if (config.mapId) {
                    opciones.mapId = config.mapId;
                }
                mapa = new window.google.maps.Map(this.$refs.mapa, opciones);
                this.avisoMapa = '';
                this.actualizarMarcadores();
            },

            crearContenidoMarcador(op) {
                const pin = document.createElement('div');
                pin.className = 'marcador-operativo';
                pin.style.setProperty('--color-marcador', config.colores[op.estado]);
                pin.textContent = op.unidad;
                if (op.ubicacion && op.ubicacion.origen === 'MANUAL') {
                    const manual = document.createElement('span');
                    manual.className = 'marcador-manual';
                    manual.textContent = 'Manual';
                    pin.appendChild(manual);
                }
                return pin;
            },

            iconoClasico(op) {
                return {
                    path: window.google.maps.SymbolPath.CIRCLE,
                    scale: 9,
                    fillColor: config.colores[op.estado],
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                };
            },

            actualizarMarcadores() {
                if (!mapa) {
                    return;
                }
                const conUbicacion = this.visibles.filter((op) => op.ubicacion);
                const vigentes = new Set(conUbicacion.map((op) => op.id));

                marcadores.forEach((marcador, id) => {
                    if (!vigentes.has(id)) {
                        marcador.map = null;
                        if (marcador.setMap) marcador.setMap(null);
                        marcadores.delete(id);
                    }
                });

                const usarAvanzados = Boolean(config.mapId && window.google.maps.marker && window.google.maps.marker.AdvancedMarkerElement);
                conUbicacion.forEach((op) => {
                    const posicion = { lat: op.ubicacion.latitud, lng: op.ubicacion.longitud };
                    const titulo = op.unidad + ' · ' + op.estado_etiqueta + ' · ' + op.ubicacion.referencia + ' (' + op.ubicacion.hace + ')';
                    let marcador = marcadores.get(op.id);
                    if (usarAvanzados) {
                        if (!marcador) {
                            marcador = new window.google.maps.marker.AdvancedMarkerElement({ map: mapa, position: posicion, title: titulo, content: this.crearContenidoMarcador(op) });
                            marcador.addListener('click', () => this.abrirFicha(this.operativos.find((o) => o.id === op.id) || op));
                            marcadores.set(op.id, marcador);
                        } else {
                            marcador.position = posicion;
                            marcador.title = titulo;
                            marcador.content = this.crearContenidoMarcador(op);
                        }
                    } else {
                        if (!marcador) {
                            marcador = new window.google.maps.Marker({ map: mapa, position: posicion, title: titulo, icon: this.iconoClasico(op), label: { text: op.unidad, fontSize: '11px', fontWeight: '600', className: 'etiqueta-marcador' } });
                            marcador.addListener('click', () => this.abrirFicha(this.operativos.find((o) => o.id === op.id) || op));
                            marcadores.set(op.id, marcador);
                        } else {
                            marcador.setPosition(posicion);
                            marcador.setTitle(titulo);
                            marcador.setIcon(this.iconoClasico(op));
                        }
                    }
                });

                if (primerEncuadre && conUbicacion.length) {
                    const limites = new window.google.maps.LatLngBounds();
                    conUbicacion.forEach((op) => limites.extend({ lat: op.ubicacion.latitud, lng: op.ubicacion.longitud }));
                    mapa.fitBounds(limites, 60);
                    if (conUbicacion.length === 1) {
                        mapa.setZoom(15);
                    }
                    primerEncuadre = false;
                }
            },

            dibujarRecorrido(puntos) {
                this.quitarRecorrido();
                if (!mapa || puntos.length < 2) {
                    return;
                }
                recorrido = new window.google.maps.Polyline({
                    map: mapa,
                    path: puntos.map((p) => ({ lat: p[0], lng: p[1] })),
                    strokeColor: '#1f6f50',
                    strokeOpacity: 0.85,
                    strokeWeight: 4,
                    icons: [{ icon: { path: window.google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 2.5 }, offset: '100%', repeat: '120px' }],
                });
            },

            quitarRecorrido() {
                if (recorrido) {
                    recorrido.setMap(null);
                    recorrido = null;
                }
            },

            // --- Ficha -------------------------------------------------------

            async abrirFicha(op) {
                this.alertasAbiertas = false;
                this.cargandoFicha = true;
                this.$refs.contenidoFicha.innerHTML = '';
                this.panel.show();
                try {
                    const respuesta = await fetch(config.urlFicha + '?id=' + encodeURIComponent(op.id) + '&fragmento=1', { credentials: 'same-origin' });
                    if (respuesta.status === 401 || respuesta.redirected) {
                        window.location.href = config.urlIngreso;
                        return;
                    }
                    // La ficha la arma el servidor con todo el texto escapado.
                    this.$refs.contenidoFicha.innerHTML = await respuesta.text();
                    const ficha = this.$refs.contenidoFicha.querySelector('[data-recorrido]');
                    if (ficha) {
                        this.dibujarRecorrido(JSON.parse(ficha.dataset.recorrido || '[]'));
                    }
                    if (mapa && op.ubicacion) {
                        mapa.panTo({ lat: op.ubicacion.latitud, lng: op.ubicacion.longitud });
                    }
                } catch (error) {
                    this.$refs.contenidoFicha.innerHTML = '<div class="alert alert-warning">Sin conexión. Intente de nuevo.</div>';
                } finally {
                    this.cargandoFicha = false;
                }
            },
        };
    });
});
