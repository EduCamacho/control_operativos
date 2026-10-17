<?php
declare(strict_types=1);

/**
 * Lista blanca de rutas: solo se carga un archivo que esté aquí.
 * Las de las fases 1 a 3 se agregan al crear cada pantalla.
 */
const RUTAS = [
    ''       => 'paginas/inicio.php',
    'login'  => 'paginas/login.php',
    'salir'  => 'paginas/salir.php',
];
