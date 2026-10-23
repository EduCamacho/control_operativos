<?php
declare(strict_types=1);

/**
 * Lista blanca de rutas: solo se carga un archivo que esté aquí.
 * Cada pantalla nueva se agrega aquí.
 */
const RUTAS = [
    ''       => 'paginas/inicio.php',
    'login'  => 'paginas/login.php',
    'salir'  => 'paginas/salir.php',
    'cuenta'       => 'paginas/cuenta.php',
    'cuenta/clave' => 'paginas/cuenta_clave.php', // redirige a 'cuenta'

    // ADMIN (fase 1)
    'admin/unidades'         => 'paginas/admin_unidades.php',
    'admin/unidades/editar'  => 'paginas/admin_unidad_formulario.php',
    'admin/usuarios'         => 'paginas/admin_usuarios.php',
    'admin/usuarios/editar'  => 'paginas/admin_usuario_formulario.php',
    'admin/catalogos'        => 'paginas/admin_catalogos.php',
    'admin/bitacora'         => 'paginas/admin_bitacora.php',
    'admin/operativos'        => 'paginas/admin_operativos.php',
    'admin/operativos/editar' => 'paginas/admin_operativo_formulario.php',

    // UNIDAD (fase 2). La pantalla principal de la unidad es '' (mis_operativos.php).
    'ayuda'                  => 'paginas/ayuda.php',
    'operativos/programar'   => 'paginas/operativo_programar.php',
    'operativos/ver'         => 'paginas/operativo_detalle.php',
    'operativos/eliminar'    => 'paginas/operativo_eliminar.php',
    'operativos/finalizar'   => 'paginas/operativo_finalizar.php',
    'operativos/nota'        => 'paginas/operativo_nota.php',
    'resultados'             => 'paginas/mis_resultados.php',
    'resultados/pdf'         => 'paginas/mis_resultados_pdf.php',
    'api/ubicaciones'        => 'api/ubicaciones.php',
    'api/finalizar'          => 'api/finalizar.php',
    'api/coordenadas'        => 'api/coordenadas.php',

    // SUPERVISOR (fase 3). Su pantalla principal es '' (supervisor_mapa.php).
    'supervisor/tablero'     => 'paginas/supervisor_tablero.php',
    'supervisor/operativo'   => 'paginas/supervisor_operativo.php',
    'supervisor/control'     => 'paginas/supervisor_control.php',
    'reportes'               => 'paginas/reportes.php',
    'reportes/pdf'           => 'paginas/reporte_pdf.php',
    'reportes/unidad'        => 'paginas/reportes_unidad.php',
    'reportes/unidad/pdf'    => 'paginas/reportes_unidad_pdf.php',
    'api/mapa'               => 'api/mapa.php',
    'api/controles'          => 'api/controles.php',
];
