/**
 * Componente Alpine "catalogoAdmin": catálogos del ADMIN (docs/04).
 * La lista va primero; tocar una fila (o "Agregar") llena el formulario del modal
 * y lo abre, sin recargar. Con ?id= o con errores al guardar, el modal se abre
 * solo con lo que se intentó guardar y marca los campos con error.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    window.Alpine.data('catalogoAdmin', function (config) {
        return {
            elementos: config.elementos,
            urlAccion: config.urlAccion,
            form: { id: null, nombre: '', descripcion: '', orden: config.siguienteOrden, activo: true, usos: 0 },
            conErrores: false,
            modal: null,

            init() {
                this.modal = window.bootstrap.Modal.getOrCreateInstance(this.$refs.modal);
                this.$refs.modal.addEventListener('shown.bs.modal', () => this.$refs.nombre.focus());
                if (config.inicial) {
                    this.form = Object.assign({}, config.inicial);
                    this.conErrores = config.conErrores;
                    this.modal.show();
                }
            },

            /** Abre el modal para editar un elemento, o para agregar uno nuevo (null). */
            abrir(elemento) {
                this.conErrores = false; // los errores del servidor eran de otro intento
                this.form = elemento
                    ? Object.assign({}, elemento)
                    : { id: null, nombre: '', descripcion: '', orden: config.siguienteOrden, activo: true, usos: 0 };
                this.modal.show();
            },
        };
    });
});
