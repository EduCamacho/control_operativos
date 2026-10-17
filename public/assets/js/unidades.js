/**
 * Componente Alpine "listaUnidades": lista de unidades del ADMIN por división (docs/04).
 * Filtra por división (chips) y por texto (nombre corto, nombre o usuario) sin
 * recargar, sin distinguir mayúsculas ni tildes. Oculta las divisiones sin resultados.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    function normalizar(texto) {
        return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    window.Alpine.data('listaUnidades', function (config) {
        const secciones = config.secciones.map(function (s) {
            return Object.assign({}, s, {
                unidades: s.unidades.map(function (u) { return Object.assign({}, u, { clave: normalizar(u.buscar) }); }),
            });
        });
        return {
            secciones: secciones,
            division: '',
            busqueda: '',

            get visibles() {
                const q = normalizar(this.busqueda.trim());
                return this.secciones
                    .filter((s) => this.division === '' || s.clave === this.division)
                    .map((s) => Object.assign({}, s, { unidades: s.unidades.filter((u) => q === '' || u.clave.includes(q)) }))
                    .filter((s) => s.unidades.length);
            },
        };
    });
});
