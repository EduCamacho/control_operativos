/**
 * Componente Alpine "capturaUbicacion": obtiene la ubicación para iniciar el
 * operativo o registrar un nuevo punto (RN-07 a RN-10, docs/04).
 *
 * 1. Intenta el GPS en alta precisión.
 * 2. Si la precisión es peor que 100 m, ofrece reintentar, usarla o ingresar a mano.
 * 3. Si el GPS falla (permiso, sin señal, PC sin GPS), explica cómo activarlo y
 *    ofrece el ingreso manual: se pegan las coordenadas copiadas de Google Maps
 *    y el servidor las separa en latitud y longitud.
 * 4. Envía con reintento si no hay conexión (envio.js).
 *
 * Requiere envio.js cargado antes.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    const OPCIONES_GPS = { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 };

    window.Alpine.data('capturaUbicacion', function (config) {
        return {
            // inactiva | obteniendo | lista | imprecisa | fuera | error | manual | enviando | sin_conexion | hecho
            fase: 'inactiva',
            mensajeError: '',
            permisoDenegado: false,
            latitud: null,
            longitud: null,
            precision: null,
            origen: null,
            referencia: '',
            textoPegado: '',
            latitudManual: '',
            longitudManual: '',
            errorManual: '',
            analizando: false,
            errores: {},
            idEnvio: null,
            envio: null,
            segundosReintento: window.Envio.SEGUNDOS_ENTRE_REINTENTOS,

            abrir() {
                this.idEnvio = window.Envio.crearIdEnvio();
                this.errores = {};
                this.obtenerGps();
                this.$nextTick(() => this.$refs.referencia && this.$refs.referencia.focus());
            },

            cancelar() {
                if (this.fase === 'enviando' || this.fase === 'sin_conexion') {
                    return;
                }
                this.fase = 'inactiva';
            },

            obtenerGps() {
                this.mensajeError = '';
                this.permisoDenegado = false;
                if (!('geolocation' in navigator)) {
                    this.fallar('Este navegador no puede obtener la ubicación.');
                    return;
                }
                if (!window.isSecureContext) {
                    this.fallar('El navegador solo entrega el GPS en una conexión segura (https).');
                    return;
                }
                this.fase = 'obteniendo';
                navigator.geolocation.getCurrentPosition(
                    (posicion) => this.alObtener(posicion),
                    (error) => this.alFallarGps(error),
                    OPCIONES_GPS,
                );
            },

            alObtener(posicion) {
                this.latitud = posicion.coords.latitude;
                this.longitud = posicion.coords.longitude;
                this.precision = Math.round(posicion.coords.accuracy);
                this.origen = 'GPS';
                if (!this.dentroDeLaLibertad(this.latitud, this.longitud)) {
                    this.fase = 'fuera';
                } else if (this.precision > config.precisionMaxima) {
                    this.fase = 'imprecisa';
                } else {
                    this.fase = 'lista';
                }
            },

            alFallarGps(error) {
                const mensajes = {
                    1: 'No hay permiso para usar la ubicación.',
                    2: 'El celular no pudo obtener la ubicación. Verifique que el GPS esté activado.',
                    3: 'El GPS tardó demasiado en responder.',
                };
                this.fallar(mensajes[error.code] || 'No se pudo obtener la ubicación.');
                this.permisoDenegado = error.code === 1;
            },

            fallar(mensaje) {
                this.mensajeError = mensaje;
                this.fase = 'error';
            },

            usarDeTodosModos() {
                this.fase = 'lista';
            },

            pasarAManual() {
                this.errorManual = '';
                this.fase = 'manual';
                this.$nextTick(() => this.$refs.textoPegado && this.$refs.textoPegado.focus());
            },

            async analizarTexto() {
                this.errorManual = '';
                if (this.textoPegado.trim() === '') {
                    return;
                }
                this.analizando = true;
                const resultado = await window.Envio.enviarJson(config.urlCoordenadas, { texto: this.textoPegado });
                this.analizando = false;
                if (resultado.ok) {
                    this.latitudManual = String(resultado.datos.latitud);
                    this.longitudManual = String(resultado.datos.longitud);
                    if (!resultado.datos.dentro_de_la_libertad) {
                        this.errorManual = 'Esas coordenadas están fuera de La Libertad. Revise que las copió bien.';
                    }
                } else if (resultado.sinConexion) {
                    this.errorManual = 'Sin conexión. Escriba la latitud y la longitud en los campos de abajo.';
                } else {
                    this.errorManual = resultado.datos.error || 'No se reconocen las coordenadas.';
                }
            },

            confirmarManual() {
                const latitud = parseFloat(String(this.latitudManual).replace(',', '.'));
                const longitud = parseFloat(String(this.longitudManual).replace(',', '.'));
                if (!isFinite(latitud) || !isFinite(longitud)) {
                    this.errorManual = 'Complete la latitud y la longitud.';
                    return;
                }
                if (!this.dentroDeLaLibertad(latitud, longitud)) {
                    this.errorManual = 'Esas coordenadas están fuera de La Libertad. Revise que las copió bien.';
                    return;
                }
                this.latitud = latitud;
                this.longitud = longitud;
                this.precision = null;
                this.origen = 'MANUAL';
                this.fase = 'lista';
            },

            dentroDeLaLibertad(latitud, longitud) {
                const l = config.limites;
                return latitud >= l.latitudMin && latitud <= l.latitudMax
                    && longitud >= l.longitudMin && longitud <= l.longitudMax;
            },

            enlaceMaps(latitud, longitud) {
                if (latitud === null || latitud === '' || longitud === null || longitud === '') {
                    return '#';
                }
                return 'https://www.google.com/maps/search/?api=1&query=' + Number(latitud).toFixed(7) + ',' + Number(longitud).toFixed(7);
            },

            get textoUbicacion() {
                if (this.latitud === null) {
                    return '';
                }
                const coordenadas = this.latitud.toFixed(6) + ', ' + this.longitud.toFixed(6);
                return this.origen === 'MANUAL' ? coordenadas + ' · ingresada a mano' : coordenadas + ' · precisión ' + this.precision + ' m';
            },

            enviar() {
                this.errores = {};
                if (this.referencia.trim() === '') {
                    this.errores = { referencia: 'Indique la referencia: calle, avenida o sector.' };
                    this.$refs.referencia && this.$refs.referencia.focus();
                    return;
                }
                this.fase = 'enviando';
                const datos = {
                    operativo_id: config.operativoId,
                    accion: config.accion,
                    latitud: this.latitud,
                    longitud: this.longitud,
                    precision_m: this.precision,
                    origen: this.origen,
                    referencia: this.referencia.trim(),
                    id_envio: this.idEnvio,
                };
                this.envio = window.Envio.enviarConReintento(config.urlEnvio, datos, {
                    alSinConexion: () => { this.fase = 'sin_conexion'; },
                    alTerminar: (resultado) => this.alTerminarEnvio(resultado),
                });
            },

            reintentarEnvio() {
                if (this.envio) {
                    this.fase = 'enviando';
                    this.envio.reintentar();
                }
            },

            alTerminarEnvio(resultado) {
                if (resultado.ok) {
                    this.fase = 'hecho';
                    window.location.href = config.urlDespues;
                    return;
                }
                if (resultado.estado === 401) {
                    window.location.href = config.urlIngreso;
                    return;
                }
                this.errores = resultado.datos.errores || {};
                this.errores.general = resultado.datos.error || 'No se pudo guardar. Intente nuevamente.';
                // Un error de negocio (por ejemplo, ya iniciado) necesita una nueva captura.
                this.idEnvio = window.Envio.crearIdEnvio();
                this.fase = 'lista';
            },
        };
    });
});
