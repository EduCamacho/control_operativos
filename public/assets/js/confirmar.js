/**
 * Confirmaciones con SweetAlert2 (copia local en assets/vendor/sweetalert2).
 *
 * Cualquier formulario con data-confirmar muestra un diálogo antes de enviarse:
 *   <form method="post" data-confirmar="Texto del diálogo"
 *         data-confirmar-titulo="¿Eliminar el operativo?"
 *         data-confirmar-boton="Sí, eliminar"
 *         data-confirmar-tipo="peligro">   (peligro: botón rojo; si no, verde institucional)
 *
 * Desde JavaScript: window.Confirmar.preguntar({ titulo, texto, boton, tipo }) → Promise<boolean>.
 */
(function () {
    'use strict';

    function preguntar(opciones) {
        const peligro = opciones.tipo === 'peligro';
        if (!window.Swal) {
            // Si la librería no cargó, se usa la confirmación del navegador.
            return Promise.resolve(window.confirm((opciones.titulo || '') + '\n\n' + (opciones.texto || '')));
        }
        return window.Swal.fire({
            title: opciones.titulo || '¿Está seguro?',
            text: opciones.texto || '',
            icon: peligro ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: opciones.boton || 'Sí, continuar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: peligro,
            buttonsStyling: false,
            customClass: {
                popup: 'alerta-sistema',
                actions: 'gap-2',
                confirmButton: 'btn boton-accion px-4 ' + (peligro ? 'btn-danger' : 'btn-institucional'),
                cancelButton: 'btn boton-accion px-4 btn-outline-secondary',
            },
        }).then(function (resultado) {
            return resultado.isConfirmed;
        });
    }

    document.addEventListener('submit', function (evento) {
        const formulario = evento.target;
        if (!formulario.matches('form[data-confirmar]') || formulario.dataset.confirmado === '1') {
            return;
        }
        evento.preventDefault();
        preguntar({
            titulo: formulario.dataset.confirmarTitulo,
            texto: formulario.dataset.confirmar,
            boton: formulario.dataset.confirmarBoton,
            tipo: formulario.dataset.confirmarTipo,
        }).then(function (confirmado) {
            if (confirmado) {
                formulario.dataset.confirmado = '1';
                formulario.submit();
            }
        });
    });

    window.Confirmar = { preguntar: preguntar };
})();
