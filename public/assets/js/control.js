/**
 * Componente Alpine "registroControl": control inopinado del SUPERVISOR (RN-15).
 * No usa el GPS ni calcula distancias: a veces el General controla con un dron,
 * sin ir al lugar. Se envía con reintento (envio.js) y el control guardado se
 * agrega arriba de "Controles anteriores" sin recargar la página.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    window.Alpine.data('registroControl', function (config) {
        return {
            config: config,
            fase: 'formulario', // formulario | enviando | sin_conexion | hecho
            resultado: '',
            observacion: '',
            errores: {},
            controles: config.controles || [],   // "Controles anteriores", el más reciente primero
            ultimoId: null,                      // el recién registrado se resalta en la lista
            idEnvio: window.Envio.crearIdEnvio(),
            envio: null,

            /** La observación solo se pide con "Con observaciones" o "No encontrado" (RN-15). */
            get pideObservacion() {
                return this.resultado === 'CON_OBSERVACIONES' || this.resultado === 'NO_ENCONTRADO';
            },

            init() {
                // Al elegir un resultado que pide observación, el cursor va al campo.
                this.$watch('resultado', () => {
                    this.errores = {};
                    if (this.pideObservacion) {
                        this.$nextTick(() => this.$refs.observacion && this.$refs.observacion.focus());
                    }
                });
            },

            claseResultado(resultado) {
                if (resultado === 'CONFORME') return 'text-bg-success';
                if (resultado === 'NO_ENCONTRADO') return 'text-bg-danger';
                return 'text-bg-warning';
            },

            enviar() {
                this.errores = {};
                if (!this.resultado) {
                    this.errores.resultado = 'Elija el resultado del control.';
                    return;
                }
                if (this.pideObservacion && this.observacion.trim() === '') {
                    this.errores.observacion = 'Describa lo observado.';
                    return;
                }
                const datos = {
                    operativo_id: config.operativoId,
                    resultado: this.resultado,
                    observacion: this.pideObservacion ? this.observacion : '',
                    id_envio: this.idEnvio,
                };
                this.fase = 'enviando';
                this.envio = window.Envio.enviarConReintento(config.urlEnvio, datos, {
                    alSinConexion: () => { this.fase = 'sin_conexion'; },
                    alTerminar: (resultado) => {
                        if (resultado.ok) {
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
