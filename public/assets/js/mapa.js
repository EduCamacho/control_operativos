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
        let puntosRecorrido = [];   // [{lat, lng, hora, referencia}] del operativo abierto
        let puntosNumerados = [];   // marcadores 1, 2, 3… de "Ver recorrido"
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
            viendoRecorrido: false,

            iniciar() {
                this.panel = new window.bootstrap.Offcanvas(this.$refs.panelFicha);
                // Al cerrar la ficha se borra el recorrido, salvo cuando se cierra para "Ver recorrido".
                this.$refs.panelFicha.addEventListener('hidden.bs.offcanvas', () => {
                    if (!this.viendoRecorrido) {
                        this.quitarRecorrido();
                    }
                });
                window.verRecorridoEnMapa = () => this.verRecorridoEnMapa();
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

            /**
             * Dónde va el marcador: la última ubicación registrada o, si todavía no hay
             * puntos (programado o no iniciado), el lugar programado.
             */
            posicionDe(op) {
                if (op.ubicacion) {
                    return { lat: op.ubicacion.latitud, lng: op.ubicacion.longitud, programado: false };
                }
                if (op.lugar) {
                    return { lat: op.lugar.latitud, lng: op.lugar.longitud, programado: true };
                }
                return null;
            },

            crearContenidoMarcador(op) {
                const pin = document.createElement('div');
                pin.className = 'marcador-operativo' + (this.posicionDe(op).programado ? ' marcador-programado' : '');
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
                // En el lugar programado: círculo blanco con borde del color del estado.
                const programado = this.posicionDe(op).programado;
                return {
                    path: window.google.maps.SymbolPath.CIRCLE,
                    scale: 9,
                    fillColor: programado ? '#ffffff' : config.colores[op.estado],
                    fillOpacity: 1,
                    strokeColor: programado ? config.colores[op.estado] : '#ffffff',
                    strokeWeight: programado ? 3 : 2,
                };
            },

            tituloMarcador(op) {
                const base = op.unidad + ' · ' + op.estado_etiqueta + ' · ';
                return op.ubicacion
                    ? base + op.ubicacion.referencia
                    : base + 'lugar programado: ' + op.lugar_programado;
            },

            actualizarMarcadores() {
                if (!mapa) {
                    return;
                }
                const conUbicacion = this.visibles.filter((op) => this.posicionDe(op) !== null);
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
                    const { lat, lng } = this.posicionDe(op);
                    const posicion = { lat, lng };
                    const titulo = this.tituloMarcador(op);
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
                    conUbicacion.forEach((op) => {
                        const { lat, lng } = this.posicionDe(op);
                        limites.extend({ lat, lng });
                    });
                    mapa.fitBounds(limites, 60);
                    if (conUbicacion.length === 1) {
                        mapa.setZoom(15);
                    }
                    primerEncuadre = false;
                }
            },

            dibujarRecorrido(puntos) {
                this.quitarRecorrido();
                puntosRecorrido = puntos;
                if (!mapa || puntos.length < 2) {
                    return;
                }
                recorrido = new window.google.maps.Polyline({
                    map: mapa,
                    path: puntos.map((p) => ({ lat: p.lat, lng: p.lng })),
                    strokeColor: '#1f6f50',
                    strokeOpacity: 0.85,
                    strokeWeight: 4,
                    icons: [{ icon: { path: window.google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 2.5 }, offset: '100%', repeat: '120px' }],
                });
            },

            quitarRecorrido() {
                this.quitarPuntosNumerados();
                if (recorrido) {
                    recorrido.setMap(null);
                    recorrido = null;
                }
            },

            // --- "Ver recorrido": la ficha se oculta y el mapa muestra los puntos numerados ---

            /** Lo llama el botón de la ficha. Devuelve false si no hay mapa visible (la ficha muestra la lista). */
            verRecorridoEnMapa() {
                if (!mapa || this.vista !== 'mapa' || puntosRecorrido.length === 0) {
                    return false;
                }
                this.viendoRecorrido = true;
                this.panel.hide();
                this.dibujarPuntosNumerados();
                const limites = new window.google.maps.LatLngBounds();
                puntosRecorrido.forEach((p) => limites.extend({ lat: p.lat, lng: p.lng }));
                mapa.fitBounds(limites, 70);
                if (puntosRecorrido.length === 1) {
                    mapa.setZoom(16);
                }
                return true;
            },

            volverAFicha() {
                this.quitarPuntosNumerados();
                this.viendoRecorrido = false;
                this.panel.show();
            },

            dibujarPuntosNumerados() {
                this.quitarPuntosNumerados();
                const usarAvanzados = Boolean(config.mapId && window.google.maps.marker && window.google.maps.marker.AdvancedMarkerElement);
                const ventana = new window.google.maps.InfoWindow();
                puntosRecorrido.forEach((punto, indice) => {
                    const numero = String(indice + 1);
                    const posicion = { lat: punto.lat, lng: punto.lng };
                    let marcador;
                    if (usarAvanzados) {
                        const circulo = document.createElement('div');
                        circulo.className = 'punto-recorrido' + (indice === 0 ? ' inicio' : '') + (indice === puntosRecorrido.length - 1 ? ' ultimo' : '');
                        circulo.textContent = numero;
                        marcador = new window.google.maps.marker.AdvancedMarkerElement({ map: mapa, position: posicion, content: circulo, zIndex: 1000 + indice });
                    } else {
                        marcador = new window.google.maps.Marker({
                            map: mapa,
                            position: posicion,
                            zIndex: 1000 + indice,
                            label: { text: numero, color: '#ffffff', fontSize: '11px', fontWeight: '700' },
                            icon: { path: window.google.maps.SymbolPath.CIRCLE, scale: 11, fillColor: '#1f6f50', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 },
                        });
                    }
                    // Texto con textContent: la referencia la escribe la unidad.
                    marcador.addListener('click', () => {
                        const contenido = document.createElement('div');
                        const titulo = document.createElement('strong');
                        titulo.textContent = numero + ' · ' + punto.hora;
                        const referencia = document.createElement('div');
                        referencia.textContent = punto.referencia;
                        contenido.append(titulo, referencia);
                        ventana.setContent(contenido);
                        ventana.open({ map: mapa, anchor: marcador });
                    });
                    puntosNumerados.push(marcador);
                });
                puntosNumerados.ventana = ventana;
            },

            quitarPuntosNumerados() {
                if (puntosNumerados.ventana) {
                    puntosNumerados.ventana.close();
                }
                puntosNumerados.forEach((marcador) => {
                    marcador.map = null;
                    if (marcador.setMap) marcador.setMap(null);
                });
                puntosNumerados = [];
            },

            // --- Ficha -------------------------------------------------------

            cerrarRecorrido() {
                this.viendoRecorrido = false;
                this.quitarRecorrido();
            },

            async abrirFicha(op) {
                this.viendoRecorrido = false;
                this.quitarPuntosNumerados();
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
                    const posicion = this.posicionDe(op);
                    if (mapa && posicion) {
                        mapa.panTo({ lat: posicion.lat, lng: posicion.lng });
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
