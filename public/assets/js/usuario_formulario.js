/**
 * Componente Alpine "formularioUsuario": crear o editar una cuenta (ADMIN, docs/04).
 * El rol decide si se elige unidad. Al crear una cuenta de unidad, elegir la
 * unidad sugiere el usuario ("COM. AYACUCHO" → "com.ayacucho") y el nombre
 * completo de la unidad, salvo que ya se hayan escrito a mano.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    /** "COM. EL ALAMBRE" → "com.el.alambre" (lo que acepta el usuario: a-z, 0-9, punto, guion). */
    function sugerirUsuario(texto) {
        return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
            .replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '').slice(0, 50);
    }

    window.Alpine.data('formularioUsuario', function (config) {
        return {
            rol: config.rol,
            unidadId: config.unidadId,
            usuario: config.usuario,
            nombre: config.nombre,
            ultimaSugerencia: { usuario: '', nombre: '' },

            init() {
                // Llegó con la unidad elegida ("Crear cuenta" desde la lista).
                if (config.sugerir && this.unidadId && this.usuario === '' && this.nombre === '') {
                    this.sugerir();
                }
            },

            sugerir() {
                const unidad = config.unidades[this.unidadId];
                if (!config.sugerir || !unidad) return;
                // Solo reemplaza lo vacío o lo que había sugerido antes (no lo escrito a mano).
                if (this.usuario === '' || this.usuario === this.ultimaSugerencia.usuario) {
                    this.usuario = sugerirUsuario(unidad.corto);
                }
                if (this.nombre === '' || this.nombre === this.ultimaSugerencia.nombre) {
                    this.nombre = unidad.nombre;
                }
                this.ultimaSugerencia = { usuario: sugerirUsuario(unidad.corto), nombre: unidad.nombre };
            },
        };
    });
});
