/**
 * Formulario de programación (docs/04).
 *
 * - "formularioProgramacion": muestra el turno y el día real mientras se
 *   escriben las horas. Lo usa ADMIN, con el formulario en una sola página.
 * - "asistenteProgramacion": lo mismo, repartido en 4 pasos para la UNIDAD
 *   (Cuándo · Qué y dónde · Jefe y fuerzas · Revisar). Valida cada paso antes
 *   de avanzar; el servidor vuelve a validar todo al guardar.
 *
 * La regla del turno es la de clasificar_turno() en app/dominio.php.
 */
(function () {
    'use strict';

    const DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    const CLAVES_TURNO = { 'MADRUGADA': 'MADRUGADA', 'MAÑANA': 'MANANA', 'TARDE': 'TARDE', 'NOCHE': 'NOCHE' };

    function minutos(hora) {
        const partes = /^(\d{2}):(\d{2})/.exec(hora || '');
        return partes ? Number(partes[1]) * 60 + Number(partes[2]) : null;
    }

    function calcularTurno(horaInicio) {
        const m = minutos(horaInicio);
        if (m === null) return '';
        if (m < 360) return 'MADRUGADA';
        if (m < 720) return 'MAÑANA';
        if (m < 1080) return 'TARDE';
        return 'NOCHE';
    }

    function formatearDia(fecha, desplazamiento) {
        const d = new Date(fecha + 'T12:00:00');
        d.setDate(d.getDate() + desplazamiento);
        return DIAS[d.getDay()] + ' ' + String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
    }

    function armarResumen(fecha, horaInicio, horaFin) {
        const turno = calcularTurno(horaInicio);
        if (!fecha || !turno) return '';
        const inicio = minutos(horaInicio);
        const fin = minutos(horaFin);
        const diaInicio = turno === 'MADRUGADA' ? 1 : 0;
        let texto = 'empieza el ' + formatearDia(fecha, diaInicio) + ' a las ' + horaInicio;
        if (fin !== null) {
            if (fin === inicio) return 'la hora de fin debe ser distinta de la de inicio';
            texto += ' y termina el ' + formatearDia(fecha, diaInicio + (fin < inicio ? 1 : 0)) + ' a las ' + horaFin;
        }
        return texto;
    }

    /** Fecha y hora real de inicio, en la hora del celular. */
    function inicioReal(fecha, horaInicio) {
        const m = minutos(horaInicio);
        if (!fecha || m === null) return null;
        const d = new Date(fecha + 'T00:00:00');
        if (m < 360) d.setDate(d.getDate() + 1);
        d.setMinutes(m);
        return d;
    }

    document.addEventListener('alpine:init', function () {
        /**
         * Coordenadas del lugar programado (opcionales): abre Google Maps con el lugar
         * escrito y separa lo que se pega con /api/coordenadas (parsear_coordenadas()).
         */
        window.Alpine.data('coordenadasLugar', function (config) {
            return {
                texto: config.texto,
                estado: '', // '' | analizando | ok | error
                latitud: null,
                longitud: null,
                mensaje: '',

                init() {
                    if (this.texto.trim() !== '') {
                        this.analizar();
                    }
                },

                enlaceBuscar() {
                    const formulario = this.$el.closest('form');
                    const lugar = formulario ? (formulario.querySelector('[name="lugar_programado"]') || {}).value || '' : '';
                    if (lugar.trim() === '') {
                        return 'https://www.google.com/maps/@-8.1116,-79.0288,14z';
                    }
                    return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lugar.trim() + ', La Libertad, Perú');
                },

                enlaceVer() {
                    return 'https://www.google.com/maps/search/?api=1&query=' + this.latitud + ',' + this.longitud;
                },

                async analizar() {
                    const texto = this.texto.trim();
                    if (texto === '') {
                        this.estado = '';
                        return;
                    }
                    this.estado = 'analizando';
                    try {
                        const meta = document.querySelector('meta[name="csrf"]');
                        const respuesta = await fetch(config.url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': meta ? meta.content : '' },
                            body: JSON.stringify({ texto: texto }),
                        });
                        const datos = await respuesta.json();
                        if (!respuesta.ok) {
                            this.estado = 'error';
                            this.mensaje = datos.error || 'No se reconocen las coordenadas.';
                        } else if (!datos.dentro_de_la_libertad) {
                            this.estado = 'error';
                            this.mensaje = 'Esas coordenadas están fuera de La Libertad. Revise que las copió bien.';
                        } else {
                            this.latitud = datos.latitud;
                            this.longitud = datos.longitud;
                            this.estado = 'ok';
                        }
                    } catch (error) {
                        // Sin conexión: el servidor las revisará al guardar.
                        this.estado = '';
                    }
                },
            };
        });

        window.Alpine.data('formularioProgramacion', function (inicial) {
            return {
                fecha: inicial.fecha,
                horaInicio: inicial.horaInicio,
                horaFin: inicial.horaFin,
                get turno() { return calcularTurno(this.horaInicio); },
                get resumen() { return armarResumen(this.fecha, this.horaInicio, this.horaFin); },
            };
        });

        window.Alpine.data('asistenteProgramacion', function (config) {
            return {
                fecha: config.fecha,
                horaInicio: config.horaInicio,
                horaFin: config.horaFin,
                paso: config.pasoInicial,
                editando: config.editando,
                editables: config.pasosEditables,
                ocupados: config.ocupados,
                error: '',
                enviando: false,
                revision: {},
                pasos: [
                    { numero: 1, titulo: 'Cuándo' },
                    { numero: 2, titulo: 'Qué y dónde' },
                    { numero: 3, titulo: 'Jefe y fuerzas' },
                    { numero: 4, titulo: 'Revisar' },
                ],

                init() {
                    if (this.paso === 4) {
                        this.$nextTick(() => this.armarRevision());
                    }
                    // Un solo envío: el segundo toque no vuelve a mandar el formulario.
                    this.$root.addEventListener('submit', (evento) => {
                        if (this.enviando) {
                            evento.preventDefault();
                            return;
                        }
                        this.enviando = true;
                    });
                    // Al volver con "Atrás" del navegador, la página restaurada debe poder enviarse otra vez.
                    window.addEventListener('pageshow', (evento) => {
                        if (evento.persisted) this.enviando = false;
                    });
                    // Enter en un campo pasa al paso siguiente en vez de enviar el formulario.
                    this.$root.addEventListener('keydown', (evento) => {
                        if (evento.key === 'Enter' && this.paso < 4 && evento.target.tagName !== 'TEXTAREA') {
                            evento.preventDefault();
                            this.siguiente();
                        }
                    });
                },

                get turno() { return calcularTurno(this.horaInicio); },
                get resumen() { return armarResumen(this.fecha, this.horaInicio, this.horaFin); },
                /** Cuántos operativos tiene ya la unidad en ese servicio diario y turno (solo informativo, RN-01). */
                get operativosEnTurno() {
                    if (!this.turno) return 0;
                    const clave = this.fecha + '|' + CLAVES_TURNO[this.turno];
                    return this.ocupados.filter((ocupado) => ocupado === clave).length;
                },

                campo(nombre) {
                    return this.$root.querySelector('[name="' + nombre + '"]');
                },

                /** El campo es editable si existe y no está bloqueado por el estado (RN-05). */
                activo(nombre) {
                    const elemento = this.campo(nombre);
                    return elemento && !elemento.disabled;
                },

                fallar(mensaje, nombre) {
                    this.error = mensaje;
                    const elemento = nombre ? this.campo(nombre) : null;
                    if (elemento) {
                        elemento.focus();
                    }
                    return false;
                },

                validar(paso) {
                    this.error = '';
                    if (paso === 1 && this.activo('hora_inicio')) {
                        if (!this.fecha) return this.fallar('Elija la fecha de servicio.', 'fecha_servicio');
                        // Límite de anticipación (RN-03): el "max" del campo lo pone el servidor.
                        const maxima = this.campo('fecha_servicio').max;
                        if (maxima && this.fecha > maxima) {
                            return this.fallar('Solo se puede programar hasta el servicio del ' + maxima.split('-').reverse().join('/') + '.', 'fecha_servicio');
                        }
                        if (minutos(this.horaInicio) === null) return this.fallar('Indique la hora de inicio.', 'hora_inicio');
                        if (minutos(this.horaFin) === null) return this.fallar('Indique la hora de fin.', 'hora_fin');
                        if (this.horaInicio === this.horaFin) return this.fallar('La hora de fin debe ser distinta de la de inicio.', 'hora_fin');
                        const inicio = inicioReal(this.fecha, this.horaInicio);
                        if (inicio && inicio <= new Date()) return this.fallar('La hora de inicio ya pasó. Elija una hora futura.', 'hora_inicio');
                    }
                    if (paso === 2 && this.activo('lugar_programado')) {
                        if (!this.$root.querySelector('[name="tipos[]"]:checked')) return this.fallar('Marque al menos un tipo de operativo.');
                        if (this.campo('lugar_programado').value.trim() === '') return this.fallar('Indique el lugar programado.', 'lugar_programado');
                        const coordenadas = this.campo('lugar_coordenadas');
                        if (coordenadas && !coordenadas.disabled) {
                            if (coordenadas.value.trim() === '') return this.fallar('Pegue las coordenadas del lugar. Toque Buscar para obtenerlas en Google Maps.', 'lugar_coordenadas');
                            if (coordenadas.dataset.valido === 'false') return this.fallar('Revise las coordenadas del lugar.', 'lugar_coordenadas');
                        }
                    }
                    if (paso === 3 && this.activo('jefe_grado')) {
                        if (this.campo('jefe_grado').value === '') return this.fallar('Elija el grado del jefe.', 'jefe_grado');
                        if (this.campo('jefe_nombres').value.trim() === '') return this.fallar('Indique los nombres del jefe.', 'jefe_nombres');
                        const celular = this.campo('jefe_celular').value.replace(/[\s\-().]/g, '').replace(/^\+?51(?=9\d{8}$)/, '');
                        if (!/^9\d{8}$/.test(celular)) return this.fallar('El celular debe tener 9 dígitos y empezar con 9.', 'jefe_celular');
                        if (!(parseInt(this.campo('fuerzas_propias').value, 10) >= 1)) return this.fallar('Las fuerzas propias deben ser al menos 1.', 'fuerzas_propias');
                    }
                    return true;
                },

                siguiente() {
                    if (!this.validar(this.paso)) return;
                    this.irA(this.editando || this.paso === 3 ? 4 : this.paso + 1);
                },

                atras() {
                    this.error = '';
                    this.irA(this.editando ? 4 : this.paso - 1);
                },

                /** Se puede saltar a un paso ya visto, o a cualquiera editable al editar. */
                puedeIrA(numero) {
                    if (numero === this.paso) return false;
                    if (this.editando) return numero === 4 || this.editables.includes(numero);
                    return numero < this.paso;
                },

                irA(numero) {
                    if (numero === 4) {
                        // Antes de revisar se validan todos los pasos.
                        for (const paso of [1, 2, 3]) {
                            if (!this.validar(paso)) {
                                this.paso = paso;
                                return;
                            }
                        }
                        this.armarRevision();
                    }
                    this.paso = numero;
                    this.$nextTick(() => {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        const titulo = this.$root.querySelector('[data-paso="' + numero + '"] .titulo-paso');
                        if (titulo) titulo.focus({ preventScroll: true });
                    });
                },

                armarRevision() {
                    const valor = (nombre) => (this.campo(nombre) ? this.campo(nombre).value.trim() : '');
                    const tipos = Array.from(this.$root.querySelectorAll('[name="tipos[]"]:checked'))
                        .map((casilla) => this.$root.querySelector('label[for="' + casilla.id + '"] .nombre-tipo').textContent.trim());
                    this.revision = {
                        tipos: tipos.join(', '),
                        lugar: valor('lugar_programado'),
                        coordenadas: valor('lugar_coordenadas'),
                        // Lo arma coordenadasLugar cuando reconoce las coordenadas.
                        enlaceMapa: this.campo('lugar_coordenadas') ? this.campo('lugar_coordenadas').dataset.enlace || '' : '',
                        jefe: (valor('jefe_grado') + ' ' + valor('jefe_nombres')).trim(),
                        celular: valor('jefe_celular'),
                        fuerzas: valor('fuerzas_propias') + ' propias · ' + valor('fuerzas_apoyo') + ' de apoyo · ' + valor('unidades_moviles') + ' móvil(es)',
                        observaciones: valor('observaciones'),
                    };
                },
            };
        });
    });
})();
