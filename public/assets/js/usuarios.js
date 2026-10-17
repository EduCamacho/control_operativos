/**
 * Componente Alpine "listaUsuarios": lista de cuentas del ADMIN (docs/04).
 * Filtra por rol (chips) y por texto (usuario, nombre o unidad) sin recargar,
 * sin distinguir mayúsculas ni tildes.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    function normalizar(texto) {
        return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    window.Alpine.data('listaUsuarios', function (config) {
        return {
            filas: config.filas.map(function (f) { return Object.assign({}, f, { clave: normalizar(f.buscar) }); }),
            rol: config.rol,
            busqueda: '',

            get visibles() {
                const q = normalizar(this.busqueda.trim());
                return this.filas.filter((f) => (this.rol === '' || f.rol === this.rol) && (q === '' || f.clave.includes(q)));
            },
        };
    });
});
