<?php
declare(strict_types=1);

/**
 * Lista blanca de rutas: solo se carga un archivo que esté aquí.
 * Las de la fase 3 se agregan al crear cada pantalla.
 */
const RUTAS = [
    ''       => 'paginas/inicio.php',
    'login'  => 'paginas/login.php',
    'salir'  => 'paginas/salir.php',

    // ADMIN (fase 1)
    'admin/unidades'         => 'paginas/admin_unidades.php',
    'admin/unidades/editar'  => 'paginas/admin_unidad_formulario.php',
    'admin/usuarios'         => 'paginas/admin_usuarios.php',
    'admin/usuarios/editar'  => 'paginas/admin_usuario_formulario.php',
    'admin/catalogos'        => 'paginas/admin_catalogos.php',
    'admin/bitacora'         => 'paginas/admin_bitacora.php',

    // UNIDAD (fase 2). La pantalla principal de la unidad es '' (mis_operativos.php).
    'operativos/programar'   => 'paginas/operativo_programar.php',
    'operativos/ver'         => 'paginas/operativo_detalle.php',
    'operativos/eliminar'    => 'paginas/operativo_eliminar.php',
    'operativos/finalizar'   => 'paginas/operativo_finalizar.php',
    'operativos/nota'        => 'paginas/operativo_nota.php',
    'api/ubicaciones'        => 'api/ubicaciones.php',
    'api/finalizar'          => 'api/finalizar.php',
    'api/coordenadas'        => 'api/coordenadas.php',
];
