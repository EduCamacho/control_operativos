<?php
declare(strict_types=1);

/**
 * Registra una acción en la bitácora (RN-17). Las cuentas de unidad son
 * compartidas, por eso se guardan siempre la IP y el dispositivo.
 * Si no se indica el usuario, se usa el de la sesión.
 */
function registrar_bitacora(
    string $accion,
    ?string $entidad = null,
    ?int $entidad_id = null,
    array $detalle = [],
    ?int $usuario_id = null,
): void {
    $usuario_id ??= usuario_actual()['id'] ?? null;
    consultar(
        'INSERT INTO bitacora (usuario_id, accion, entidad, entidad_id, detalle, ip, user_agent)
         VALUES (:usuario_id, :accion, :entidad, :entidad_id, :detalle, :ip, :user_agent)',
        [
            'usuario_id' => $usuario_id,
            'accion'     => $accion,
            'entidad'    => $entidad,
            'entidad_id' => $entidad_id,
            'detalle'    => $detalle === [] ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE),
            'ip'         => obtener_ip_cliente($_SERVER) ?: null, // la real, también detrás de Cloudflare
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ],
    );
}
