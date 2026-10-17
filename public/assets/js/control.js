/**
 * Componente Alpine "registroControl": control inopinado del SUPERVISOR (RN-15).
 * La ubicación del supervisor es opcional: no se valida el rectángulo de
 * La Libertad ni se advierte por precisión. Se envía con reintento (envio.js).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    window.Alpine.data('registroControl', function (config) {
        return {
            config: config,
            fase: 'formulario', // formulario | enviando | sin_conexion | hecho
            ubicacion: { estado: 'inactiva', latitud: null, longitud: null, precision: null, origen: null, mensaje: '' },
            textoPegado: '',
            errorManual: '',
            resultado: '',
            observacion: '',
            errores: {},
            distancia: null,
            controles: config.controles || [],   // "Controles anteriores", el más reciente primero
            ultimoId: null,                      // el recién registrado se resalta en la lista

            claseResultado(resultado) {
                if (resultado === 'CONFORME') return 'text-bg-success';
                if (resultado === 'NO_ENCONTRADO') return 'text-bg-danger';
                return 'text-bg-warning';
            },
            idEnvio: window.Envio.crearIdEnvio(),
            envio: null,

            obtenerGps() {
                if (!('geolocation' in navigator) || !window.isSecureContext) {
                    this.ubicacion = { ...this.ubicacion, estado: 'error', mensaje: 'Este navegador no entrega la ubicación aquí.' };
                    return;
                }
                this.ubicacion.estado = 'obteniendo';
                navigator.geolocation.getCurrentPosition(
                    (posicion) => {
                        this.ubicacion = {
                            estado: 'lista',
                            latitud: posicion.coords.latitude,
                            longitud: posicion.coords.longitude,
                            precision: Math.round(posicion.coords.accuracy),
                            origen: 'GPS',
                            mensaje: '',
                        };
                    },
                    (error) => {
                        const mensajes = { 1: 'No hay permiso para usar la ubicación.', 2: 'No se pudo obtener la ubicación.', 3: 'El GPS tardó demasiado.' };
                        this.ubicacion = { ...this.ubicacion, estado: 'error', mensaje: mensajes[error.code] || 'No se pudo obtener la ubicación.' };
                    },
                    { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
                );
            },

            async usarTextoPegado() {
                this.errorManual = '';
                const resultado = await window.Envio.enviarJson(config.urlCoordenadas, { texto: this.textoPegado });
                if (resultado.ok) {
                    this.ubicacion = { estado: 'lista', latitud: resultado.datos.latitud, longitud: resultado.datos.longitud, precision: null, origen: 'MANUAL', mensaje: '' };
                } else {
                    this.errorManual = resultado.sinConexion ? 'Sin conexión.' : (resultado.datos.error || 'No se reconocen las coordenadas.');
                }
            },

            quitarUbicacion() {
                this.ubicacion = { estado: 'error', latitud: null, longitud: null, precision: null, origen: null, mensaje: 'Se guardará sin su ubicación.' };
            },

            get textoUbicacion() {
                const u = this.ubicacion;
                if (u.latitud === null) return '';
                const coordenadas = Number(u.latitud).toFixed(6) + ', ' + Number(u.longitud).toFixed(6);
                return u.origen === 'MANUAL' ? coordenadas + ' · ingresada a mano' : coordenadas + ' · precisión ' + u.precision + ' m';
            },

            get textoDistancia() {
                if (this.distancia === null) return '';
                return this.distancia >= 1000 ? (this.distancia / 1000).toFixed(1).replace('.', ',') + ' km' : this.distancia + ' m';
            },

            enviar() {
                this.errores = {};
                if (!this.resultado) {
                    this.errores.resultado = 'Elija el resultado del control.';
                    return;
                }
                if (this.resultado !== 'CONFORME' && this.observacion.trim() === '') {
                    this.errores.observacion = 'Describa lo observado.';
                    return;
                }
                const datos = {
                    operativo_id: config.operativoId,
                    resultado: this.resultado,
                    observacion: this.observacion,
                    id_envio: this.idEnvio,
                };
                if (this.ubicacion.estado === 'lista') {
                    datos.latitud = this.ubicacion.latitud;
                    datos.longitud = this.ubicacion.longitud;
                    datos.precision_m = this.ubicacion.precision;
                    datos.origen = this.ubicacion.origen;
                }
                this.fase = 'enviando';
                this.envio = window.Envio.enviarConReintento(config.urlEnvio, datos, {
                    alSinConexion: () => { this.fase = 'sin_conexion'; },
                    alTerminar: (resultado) => {
                        if (resultado.ok) {
                            this.distancia = resultado.datos.distancia_m;
                            // El control recién guardado aparece arriba de la lista, sin recargar.
                            const nuevo = resultado.datos.control;
                            if (nuevo && !this.controles.some((c) => c.id === nuevo.id)) {
                                this.controles.unshift(nuevo);
                                this.ultimoId = nuevo.id;
                            }
                            this.fase = 'hecho';
                        } else if (resultado.estado === 401) {
                            window.location.href = config.urlIngreso;
                        } else {
                            this.errores = resultado.datos.errores || {};
                            this.errores.general = resultado.datos.error || 'No se pudo guardar el control.';
                            this.fase = 'formulario';
                        }
                    },
                });
            },

            reintentar() {
                if (this.envio) {
                    this.fase = 'enviando';
                    this.envio.reintentar();
                }
            },
        };
    });
});
