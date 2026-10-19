/**
 * Componente Alpine "formularioUsuario": crear o editar una cuenta (ADMIN, docs/04).
 * El rol decide si se elige unidad. Al crear una cuenta de unidad, elegir la
 * unidad sugiere el usuario ("COM. AYACUCHO" → "com.ayacucho"), salvo que ya se
 * haya escrito a mano. El nombre es el de la persona: no se sugiere.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    /** "COM. EL ALAMBRE" → "com.el.alambre" (lo que acepta el usuario: a-z, 0-9, punto, guion). */
    function sugerirUsuario(texto) {
        return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
            .replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '').slice(0, 50);
    }

    window.Alpine.data('formularioUsuario', function (config) {
        return {
            rol: config.rol,
            unidadId: config.unidadId,
            usuario: config.usuario,
            ultimaSugerencia: '',

            init() {
                // Llegó con la unidad elegida ("Crear cuenta" desde la lista).
                if (config.sugerir && this.unidadId && this.usuario === '') {
                    this.sugerir();
                }
            },

            sugerir() {
                const unidad = config.unidades[this.unidadId];
                if (!config.sugerir || !unidad) return;
                // Solo reemplaza lo vacío o lo que había sugerido antes (no lo escrito a mano).
                if (this.usuario === '' || this.usuario === this.ultimaSugerencia) {
                    this.usuario = sugerirUsuario(unidad.corto);
                }
                this.ultimaSugerencia = sugerirUsuario(unidad.corto);
            },
        };
    });
});
