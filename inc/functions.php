<?php
// inc/functions.php

function h($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function post($key, $default = '')
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function set_flash($type, $message)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['flash'] = array(
        'type' => $type,
        'message' => $message
    );
}

function get_flash()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Marca como 'caducada' las solicitudes pendientes/en_revision
 * cuya fecha_limite ya venció. Llamar al inicio de cada página
 * de solicitudes para mantener estados al día sin cron.
 *
 * @param PDO $pdo
 * @return int  Cantidad de solicitudes caducadas en esta llamada
 */
function caducar_solicitudes_vencidas($pdo) {
    // Marcar caducadas
    $stmt = $pdo->prepare("
        UPDATE solicitudes
        SET    estado = 'caducada',
               observacion_respuesta = CONCAT(
                   COALESCE(observacion_respuesta, ''),
                   ' [Caducada automáticamente el ', DATE_FORMAT(NOW(), '%d/%m/%Y'), ']'
               )
        WHERE  estado IN ('pendiente', 'en_revision')
          AND  fecha_limite IS NOT NULL
          AND  fecha_limite < CURDATE()
    ");
    $stmt->execute();
    $n = $stmt->rowCount();

    if ($n > 0) {
        // Log automático
        $pdo->prepare("
            INSERT INTO solicitudes_log (id_solicitud, id_usuario, accion, detalle)
            SELECT id, 0, 'caducada_auto',
                   CONCAT('Caducada automáticamente. Fecha límite: ', DATE_FORMAT(fecha_limite, '%d/%m/%Y'))
            FROM   solicitudes
            WHERE  estado = 'caducada'
              AND  updated_at >= NOW() - INTERVAL 10 SECOND
        ")->execute();
    }
    return $n;
}

/**
 * Obtiene stock reservado por producto para una bodega origen,
 * considerando solicitudes pendientes y en revisión.
 * Retorna array: [ id_producto => cantidad_reservada ]
 *
 * @param PDO $pdo
 * @param int $id_bodega_origen
 * @return array
 */
function get_stock_reservado($pdo, $id_bodega_origen) {
    $stmt = $pdo->prepare("
        SELECT sd.id_producto, SUM(sd.cantidad) AS reservado
        FROM   solicitudes_detalle sd
        INNER  JOIN solicitudes s ON s.id = sd.id_solicitud
        WHERE  s.id_bodega_origen = ?
          AND  s.estado IN ('pendiente', 'en_revision')
          AND  (sd.estado IS NULL OR sd.estado = 'pendiente')
        GROUP  BY sd.id_producto
    ");
    $stmt->execute(array((int)$id_bodega_origen));
    $result = array();
    foreach ($stmt->fetchAll() as $r) {
        $result[(int)$r['id_producto']] = (float)$r['reservado'];
    }
    return $result;
}