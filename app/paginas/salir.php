<?php
declare(strict_types=1);

if (!es_post()) {
    redirigir('');
}
verificar_csrf();
cerrar_sesion();
redirigir('login');
