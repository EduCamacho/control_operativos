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

// Tolerancias y ventana de inicio, en minutos (RN-07, RN-14).
const TOLERANCIA_NO_INICIADO_MIN = 15;
const TOLERANCIA_SIN_CIERRE_MIN = 30;
const VENTANA_INICIO_MIN = 60;

// Duración de la sesión sin actividad, en segundos (RN-18: al menos 12 horas).
const DURACION_SESION_S = 43200;
