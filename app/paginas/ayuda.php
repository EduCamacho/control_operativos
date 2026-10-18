<?php
/**
 * UNIDAD: Ayuda (docs/04). A quién llamar, el videotutorial y preguntas
 * frecuentes. Los datos de contacto y el video están en app/config.php.
 * El video es un enlace (abre la app de YouTube), no se incrusta: la página
 * queda liviana con poca señal.
 */
declare(strict_types=1);

requiere_rol('UNIDAD');

$celular = trim(SOPORTE_CELULAR);
$enlace_whatsapp = SOPORTE_WHATSAPP ? armar_enlace_whatsapp($celular) : null;
$video = str_starts_with(VIDEO_TUTORIAL_UNIDAD_URL, 'https://') ? VIDEO_TUTORIAL_UNIDAD_URL : '';

// [pregunta, respuesta (null: los pasos para activar la ubicación), video opcional [enlace, etiqueta]].
// Los videos son de terceros (elegidos por Eduardo, 18/10/2026): si los borran, reemplazarlos por uno propio.
$preguntas = [
    ['¿Cómo activo la ubicación del celular?', null, ['https://www.youtube.com/watch?v=7jE82O5tRe8', 'Ver video (Android)']],
    ['Me aparece "¡Detectamos que está en una PC!"',
        'Lo ideal es iniciar y registrar los puntos desde el celular, en el lugar del operativo. Si está en la comisaría, toque <strong>¿No es mi ubicación?</strong> → <strong>Ingresar manualmente</strong> y pegue las coordenadas que le envíe el jefe del operativo.'],
    ['¿Cómo copio las coordenadas de Google Maps?',
        'En Google Maps, mantenga presionado el lugar (o haga clic derecho en la PC) y toque las coordenadas para copiarlas, por ejemplo <em>-8.111600, -79.028800</em>. También puede pegar el enlace del lugar.',
        ['https://www.youtube.com/watch?v=MQRf4R9haVs', 'Ver video']],
    ['Me equivoqué en un dato del operativo',
        'Desde la ficha del operativo, toque <strong>Editar</strong>. Antes de la hora de inicio puede cambiar todo; después ya no se cambian la fecha ni las horas, y en curso solo el jefe, las fuerzas y las observaciones. Para lo demás, pida la corrección al administrador.'],
    ['Finalicé sin la nota informativa',
        'Abra el operativo finalizado y escriba el número en <strong>Nota informativa</strong>. Una vez guardada, solo el administrador puede cambiarla.'],
    ['Olvidé la clave',
        'Pida al administrador que la resetee. Para cambiarla usted mismo, toque su nombre al pie del menú (<strong>Mi cuenta</strong>).'],
];

$titulo = 'Ayuda';
require RUTA_APP . '/vistas/cabecera.php';
?>
<div class="formulario-angosto">
    <h1 class="h4 mb-3">Ayuda</h1>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 titulo-tarjeta"><i class="bi bi-headset"></i> ¿Necesita ayuda?</h2>
            <?php if ($celular === ''): ?>
                <p class="mb-0 text-body-secondary">Consulte al administrador del sistema.</p>
            <?php else: ?>
                <p class="mb-2">
                    Comuníquese con <?= SOPORTE_NOMBRE !== '' ? '<strong>' . e(SOPORTE_NOMBRE) . '</strong>' : 'soporte' ?>
                    <span class="text-body-secondary">· <?= e($celular) ?></span>
                </p>
                <div class="d-grid gap-2 d-sm-flex">
                    <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $celular)) ?>" class="btn btn-institucional boton-accion px-4">
                        <i class="bi bi-telephone"></i> Llamar
                    </a>
                    <?php if ($enlace_whatsapp !== null): ?>
                        <a href="<?= e($enlace_whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-outline-institucional boton-accion px-4">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($video !== ''): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 titulo-tarjeta"><i class="bi bi-play-btn"></i> Videotutorial</h2>
                <p class="small text-body-secondary">Cómo programar, iniciar, registrar puntos y finalizar un operativo.</p>
                <a href="<?= e($video) ?>" target="_blank" rel="noopener" class="btn btn-outline-danger boton-accion px-4">
                    <i class="bi bi-youtube"></i> Ver el videotutorial
                </a>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="titulo-seccion mt-4"><i class="bi bi-question-circle"></i> Preguntas frecuentes</h2>
    <div class="list-group shadow-sm mb-4 preguntas-frecuentes">
        <?php foreach ($preguntas as $entrada): ?>
            <?php [$pregunta, $respuesta] = $entrada; $video_pregunta = $entrada[2] ?? null; ?>
            <details class="list-group-item">
                <summary class="fw-semibold"><?= e($pregunta) ?></summary>
                <div class="small mt-2">
                    <?php if ($respuesta === null): ?>
                        <?php require RUTA_APP . '/vistas/pasos_activar_ubicacion.php'; ?>
                    <?php else: ?>
                        <?= $respuesta // Texto fijo de esta página, con etiquetas de formato. ?>
                    <?php endif; ?>
                    <?php if ($video_pregunta !== null): ?>
                        <div>
                            <a href="<?= e($video_pregunta[0]) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger mt-2">
                                <i class="bi bi-youtube"></i> <?= e($video_pregunta[1]) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</div>
<?php require RUTA_APP . '/vistas/pie.php'; ?>
