/**
 * Componente Alpine "capturaUbicacion": obtiene la ubicación para iniciar el
 * operativo o registrar un nuevo punto (RN-07 a RN-10, docs/04).
 *
 * 1. En el celular intenta el GPS en alta precisión. En la PC va directo al
 *    ingreso manual: su "GPS" daría la ubicación de la comisaría (RN-09).
 * 2. La precisión no se muestra ni se advierte (se guarda igual, RN-08); siempre
 *    se puede pasar al ingreso manual.
 * 3. Si el GPS falla (permiso, sin señal), explica cómo activarlo y ofrece el
 *    ingreso manual: se pega la ubicación (WhatsApp o Google Maps) y el servidor
 *    la separa en latitud y longitud.
 * 4. El botón "Ubicación correcta" es la confirmación, también de las coordenadas
 *    manuales (sin paso intermedio); avisa si el inicio queda a más de 500 m del
 *    lugar programado (RN-09a).
 * 5. Envía con reintento si no hay conexión (envio.js).
 *
 * Requiere envio.js cargado antes.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    const OPCIONES_GPS = { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 };

    window.Alpine.data('capturaUbicacion', function (config) {
        return {
            // inactiva | obteniendo | lista | fuera | error | manual | enviando | sin_conexion | hecho
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
            camposVisibles: false, // latitud y longitud por separado (ocultos: se llenan al pegar)
            otrasOpciones: false, // "¿No es mi ubicación?" desplegado
            errores: {},
            idEnvio: null,
            envio: null,
            segundosReintento: window.Envio.SEGUNDOS_ENTRE_REINTENTOS,

            modal: null,

            init() {
                // El modal es estático: solo se cierra con la ✕ o Cancelar (ver cancelar()).
                this.modal = window.bootstrap.Modal.getOrCreateInstance(this.$refs.modal);
                this.$refs.modal.addEventListener('shown.bs.modal', () => {
                    // Solo si es obligatoria: en el celular, el teclado taparía el botón.
                    if (this.referenciaObligatoria && this.$refs.referencia) this.$refs.referencia.focus();
                    else if (this.fase === 'manual' && this.$refs.textoPegado) this.$refs.textoPegado.focus();
                });
                this.$refs.modal.addEventListener('hidden.bs.modal', () => {
                    if (!this.bloqueado && this.fase !== 'hecho') {
                        this.fase = 'inactiva';
                    }
                });
            },

            /** Mientras se envía o espera la señal, el modal no se puede cerrar (se perdería el punto). */
            get bloqueado() {
                return this.fase === 'enviando' || this.fase === 'sin_conexion';
            },

            abrir() {
                this.idEnvio = window.Envio.crearIdEnvio();
                this.errores = {};
                this.referencia = '';
                this.textoPegado = '';
                this.latitudManual = '';
                this.longitudManual = '';
                this.camposVisibles = false;
                this.otrasOpciones = false;
                this.modal.show();
                // Desde la PC se pegan las coordenadas que envía el jefe del operativo (RN-09).
                if (this.desdePc) {
                    this.pasarAManual();
                } else {
                    this.obtenerGps();
                }
            },

            cancelar() {
                if (this.bloqueado) {
                    return;
                }
                this.modal.hide();
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
                this.otrasOpciones = false;
                this.fase = this.dentroDeLaLibertad(this.latitud, this.longitud) ? 'lista' : 'fuera';
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

            pasarAManual() {
                this.errorManual = '';
                this.fase = 'manual';
                this.$nextTick(() => this.$refs.textoPegado && this.$refs.textoPegado.focus());
            },

            async analizarTexto() {
                this.errorManual = '';
                // Con los campos ocultos, no debe quedar el punto de un pegado anterior.
                this.latitudManual = '';
                this.longitudManual = '';
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
                    this.camposVisibles = true;
                } else {
                    this.errorManual = resultado.datos.error || 'No se reconocen las coordenadas.';
                }
            },

            /** Las coordenadas de los campos manuales si son válidas y están en La Libertad, o null. */
            get puntoManual() {
                const latitud = parseFloat(String(this.latitudManual).replace(',', '.'));
                const longitud = parseFloat(String(this.longitudManual).replace(',', '.'));
                if (!isFinite(latitud) || !isFinite(longitud) || !this.dentroDeLaLibertad(latitud, longitud)) {
                    return null;
                }
                return { latitud, longitud };
            },

            /** "-8.111600, -79.028800", como formatear_coordenadas() en app/dominio.php. */
            get textoPuntoManual() {
                const punto = this.puntoManual;
                return punto === null ? '' : punto.latitud.toFixed(6) + ', ' + punto.longitud.toFixed(6);
            },

            /** Pasa las coordenadas manuales a la ubicación que se enviará. */
            confirmarManual() {
                const punto = this.puntoManual;
                if (punto === null) {
                    this.errorManual = 'Revise la latitud y la longitud: deben estar dentro de La Libertad.';
                    return false;
                }
                this.latitud = punto.latitud;
                this.longitud = punto.longitud;
                this.precision = null;
                this.origen = 'MANUAL';
                this.otrasOpciones = false;
                this.fase = 'lista';
                return true;
            },

            /** El botón "Ubicación correcta" también confirma las coordenadas manuales, sin paso intermedio. */
            get puedeEnviar() {
                return this.fase === 'lista' || (this.fase === 'manual' && this.puntoManual !== null);
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

            /**
             * Enlace del ingreso manual: las coordenadas ingresadas (con pin) para revisarlas o, si aún no hay,
             * Google Maps centrado en la zona del operativo y sin pin, para que no se copien por error
             * las coordenadas del lugar programado en vez de las del lugar real.
             */
            get enlaceManual() {
                const punto = this.puntoManual;
                if (punto !== null) {
                    return this.enlaceMaps(punto.latitud, punto.longitud);
                }
                const centro = config.centroMapa;
                return centro
                    ? 'https://www.google.com/maps/@' + Number(centro.latitud).toFixed(7) + ',' + Number(centro.longitud).toFixed(7) + ',16z'
                    : 'https://www.google.com/maps';
            },

            /** Metros hasta el lugar programado (solo al iniciar), o null. Haversine, como distancia_m() en app/dominio.php. */
            get distanciaLugar() {
                const lugar = config.lugarProgramado;
                // En el ingreso manual se mide lo que está en los campos, antes de confirmar.
                const punto = this.fase === 'manual'
                    ? this.puntoManual
                    : (this.latitud === null ? null : { latitud: this.latitud, longitud: this.longitud });
                if (!lugar || punto === null) {
                    return null;
                }
                const radianes = (grados) => grados * Math.PI / 180;
                const dLat = radianes(punto.latitud - lugar.latitud);
                const dLng = radianes(punto.longitud - lugar.longitud);
                const a = Math.sin(dLat / 2) ** 2
                    + Math.cos(radianes(lugar.latitud)) * Math.cos(radianes(punto.latitud)) * Math.sin(dLng / 2) ** 2;
                return Math.round(2 * 6371000 * Math.asin(Math.min(1, Math.sqrt(a))));
            },

            get lejosDelLugar() {
                return this.distanciaLugar !== null && this.distanciaLugar > config.distanciaLejos;
            },

            /** "350 m" o "3,2 km", como formatear_distancia() en app/dominio.php. */
            get textoDistancia() {
                const metros = this.distanciaLugar;
                if (metros === null) {
                    return '';
                }
                return metros < 1000 ? metros + ' m' : (metros / 1000).toFixed(1).replace('.', ',') + ' km';
            },

            /** Lo decide el servidor con clasificar_dispositivo(), igual que lo que se guarda. */
            get desdePc() {
                return config.desdePc === true;
            },

            /** La referencia es obligatoria al iniciar y opcional en el nuevo punto (RN-10). */
            get referenciaObligatoria() {
                return config.accion === 'INICIAR';
            },

            /** "Ubicación correcta · Iniciar": tocarlo es confirmar la ubicación (RN-09a). */
            textoBoton(accion) {
                if (this.fase === 'obteniendo') return 'Obteniendo ubicación…';
                if (this.fase === 'enviando') return 'Enviando…';
                return 'Ubicación correcta · ' + accion;
            },

            enviar() {
                this.errores = {};
                if (this.referenciaObligatoria && this.referencia.trim() === '') {
                    this.errores = { referencia: 'Indique la referencia: calle, avenida o sector.' };
                    this.$refs.referencia && this.$refs.referencia.focus();
                    return;
                }
                if (this.fase === 'manual' && !this.confirmarManual()) {
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
