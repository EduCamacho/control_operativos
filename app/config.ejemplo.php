<?php
/**
 * Copiar como app/config.php y completar. app/config.php no se versiona
 * y en el servidor se crea una sola vez (docs/03).
 */
declare(strict_types=1);

// 'produccion' o 'local'. En local se muestran los errores y se permite HTTP.
const ENTORNO = 'produccion';

// Ruta base de la aplicación en la URL, sin barra final.
// '' si el subdominio apunta a public/ (producción); por ejemplo '/control_operativos/public' en Laragon.
const URL_BASE = '';

// Base de datos.
const BD_HOST = 'localhost';
const BD_NOMBRE = 'control_operativos';
const BD_USUARIO = '';
const BD_CLAVE = '';

// Google Maps: solo se usa en las pantallas del SUPERVISOR (RN-21).
const GOOGLE_MAPS_API_KEY = '';
const GOOGLE_MAPS_MAP_ID = '';

// Ayuda de las unidades (/ayuda): a quién llamar y el videotutorial.
// El celular con 9 dígitos (por ejemplo '987654321'). Vacío: la página indica que se consulte al administrador.
const SOPORTE_NOMBRE = '';
const SOPORTE_CELULAR = '';
const SOPORTE_WHATSAPP = true;   // true si ese celular atiende por WhatsApp.
// Enlace de YouTube, subido como "No listado". Vacío: no se muestra el botón.
const VIDEO_TUTORIAL_UNIDAD_URL = '';

// Cloudflare Turnstile en el ingreso (RN-19a). Vacías: no se usa (por ejemplo, en local).
// Para probar en local, las claves de prueba de Cloudflare que siempre aprueban:
// '1x00000000000000000000AA' y '1x0000000000000000000000000000000AA'.
const TURNSTILE_SITE_KEY = '';
const TURNSTILE_SECRET_KEY = '';

// Política de contenido (CSP): false solo informa en la consola del navegador lo que
// bloquearía (Report-Only); true la aplica. Activarla después de revisar que nada se rompe.
const CSP_APLICAR = false;

// Tolerancias y ventana de inicio, en minutos (RN-07, RN-14).
const TOLERANCIA_NO_INICIADO_MIN = 15;
const TOLERANCIA_SIN_CIERRE_MIN = 30;
const VENTANA_INICIO_MIN = 60;

// Cuentas activas que puede tener cada unidad, una por persona (RN-20). Las inactivas no cuentan.
const MAX_CUENTAS_POR_UNIDAD = 2;

// Duración de la sesión por rol, en segundos (RN-18). "Inactividad": sin usar el sistema;
// "máxima": desde el ingreso, aunque se use todo el tiempo.
const SESION_INACTIVIDAD_S = ['ADMIN' => 1800, 'SUPERVISOR' => 14400, 'UNIDAD' => 43200];   // 30 min, 4 h, 12 h
const SESION_MAXIMA_S = ['ADMIN' => 28800, 'SUPERVISOR' => 57600, 'UNIDAD' => 86400];       // 8 h, 16 h, 24 h
// Vida de la cookie y de los archivos de sesión: al menos la mayor inactividad de arriba.
const DURACION_SESION_S = 43200;
