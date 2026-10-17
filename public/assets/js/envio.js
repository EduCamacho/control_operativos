/**
 * Envío de datos al servidor con reintento cuando no hay conexión (RN-11a).
 *
 * - Cada envío lleva el token CSRF en la cabecera X-CSRF-Token.
 * - Sin conexión (o si el servidor falla con 5xx) se reintenta solo cada 15 s
 *   y en cuanto el navegador avisa que volvió la señal.
 * - Un error 4xx (validación o regla de negocio) no se reintenta: se devuelve.
 * - Mientras haya un envío pendiente, el navegador avisa antes de cerrar la página.
 */
(function () {
    'use strict';

    const SEGUNDOS_ENTRE_REINTENTOS = 15;

    function crearIdEnvio() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function obtenerTokenCsrf() {
        const meta = document.querySelector('meta[name="csrf"]');
        return meta ? meta.content : '';
    }

    /**
     * POST con cuerpo JSON.
     * @returns {Promise<{ok: boolean, estado: number, datos: object, sinConexion: boolean}>}
     */
    async function enviarJson(url, datos) {
        try {
            const respuesta = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': obtenerTokenCsrf(),
                },
                body: JSON.stringify(datos),
            });
            let cuerpo = {};
            try {
                cuerpo = await respuesta.json();
            } catch (error) {
                cuerpo = {};
            }
            return {
                ok: respuesta.ok,
                estado: respuesta.status,
                datos: cuerpo,
                sinConexion: respuesta.status >= 500,
            };
        } catch (error) {
            return { ok: false, estado: 0, datos: {}, sinConexion: true };
        }
    }

    function avisarAntesDeSalir(evento) {
        evento.preventDefault();
        evento.returnValue = '';
    }

    /**
     * Envía y reintenta hasta obtener una respuesta del servidor.
     * @param {{alSinConexion?: Function, alTerminar: Function}} avisos
     * @returns {{reintentar: Function}}
     */
    function enviarConReintento(url, datos, avisos) {
        let temporizador = null;
        let enCurso = false;
        let terminado = false;

        function limpiar() {
            clearTimeout(temporizador);
            window.removeEventListener('online', intentar);
            window.removeEventListener('beforeunload', avisarAntesDeSalir);
        }

        async function intentar() {
            if (terminado || enCurso) {
                return;
            }
            enCurso = true;
            clearTimeout(temporizador);
            const resultado = await enviarJson(url, datos);
            enCurso = false;
            if (resultado.sinConexion) {
                if (avisos.alSinConexion) {
                    avisos.alSinConexion(resultado);
                }
                temporizador = setTimeout(intentar, SEGUNDOS_ENTRE_REINTENTOS * 1000);
                return;
            }
            terminado = true;
            limpiar();
            avisos.alTerminar(resultado);
        }

        window.addEventListener('online', intentar);
        window.addEventListener('beforeunload', avisarAntesDeSalir);
        intentar();
        return { reintentar: intentar };
    }

    window.Envio = { crearIdEnvio, enviarJson, enviarConReintento, SEGUNDOS_ENTRE_REINTENTOS };
})();
