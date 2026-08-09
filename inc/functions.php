<?php
// inc/functions.php
// Protección CSRF global: al cargar functions.php en cualquier página
// se valida automáticamente todo POST (ver inc/csrf.php).
require_once __DIR__ . '/csrf.php';

function h($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/**
 * Codifica datos a JSON seguro para incrustar en un <script> inline.
 * Escapa <, >, & y comillas para impedir salirse del bloque (XSS).
 */
function js_json($data)
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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

/**
 * IP del cliente (limitada a 45 chars, IPv6). Se usa para el
 * throttle de fuerza bruta persistente del login.
 */
function client_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    return substr($ip, 0, 45);
}

/**
 * Indica si el usuario+IP está bloqueado por intentos fallidos.
 * Retorna false si no está bloqueado, o los segundos restantes de bloqueo.
 *
 * La comparación se hace en SQL (UNIX_TIMESTAMP) para no depender de la
 * zona horaria de PHP vs. la del motor de BD.
 *
 * @param PDO    $pdo
 * @param string $usuario
 * @param string $ip
 * @return int|false
 */
function login_bloqueado($pdo, $usuario, $ip)
{
    $stmt = $pdo->prepare("
        SELECT UNIX_TIMESTAMP(bloqueado_hasta) - UNIX_TIMESTAMP(NOW()) AS restante
        FROM   login_intentos
        WHERE  usuario = ? AND ip = ?
        LIMIT  1
    ");
    $stmt->execute(array($usuario, $ip));
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        return false;
    }
    $restante = (int)$fila['restante'];
    if ($restante <= 0) {
        return false;
    }
    return $restante;
}

/**
 * Registra un intento fallido de login. A partir de 5 fallos
 * consecutivos (usuario+IP) activa un bloqueo de 15 minutos.
 *
 * @param PDO    $pdo
 * @param string $usuario
 * @param string $ip
 */
function registrar_intento_fallido($pdo, $usuario, $ip)
{
    $stmt = $pdo->prepare("
        SELECT id, intentos
        FROM   login_intentos
        WHERE  usuario = ? AND ip = ?
        LIMIT  1
    ");
    $stmt->execute(array($usuario, $ip));
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila) {
        $nuevos = (int)$fila['intentos'] + 1;
        if ($nuevos >= 5) {
            $stmt = $pdo->prepare("
                UPDATE login_intentos
                SET    intentos = ?, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE), ultimo_intento = NOW()
                WHERE  id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE login_intentos
                SET    intentos = ?, ultimo_intento = NOW()
                WHERE  id = ?
            ");
        }
        $stmt->execute(array($nuevos, (int)$fila['id']));
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO login_intentos (usuario, ip, intentos, ultimo_intento)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute(array($usuario, $ip));
    }
}

/**
 * Limpia el contador de intentos tras un login exitoso.
 *
 * @param PDO    $pdo
 * @param string $usuario
 * @param string $ip
 */
function limpiar_intentos_login($pdo, $usuario, $ip)
{
    $stmt = $pdo->prepare("DELETE FROM login_intentos WHERE usuario = ? AND ip = ?");
    $stmt->execute(array($usuario, $ip));
}

/**
 * Valida que una contraseña cumpla la política del sistema
 * (mínimo 8 caracteres, con letras y números).
 *
 * @param string $clave
 * @param string $mensaje  Recibe el mensaje de error si no valida.
 * @return bool
 */
function validar_clave_politica($clave, &$mensaje)
{
    if (strlen($clave) < 8) {
        $mensaje = 'La contraseña debe tener al menos 8 caracteres.';
        return false;
    }
    if (!preg_match('/[A-Za-z]/', $clave) || !preg_match('/[0-9]/', $clave)) {
        $mensaje = 'La contraseña debe incluir letras y números.';
        return false;
    }
    return true;
}

/**
 * Genera una contraseña temporal aleatoria y fuerte (CSPRNG),
 * sin caracteres ambiguos (0/O/1/l). El primer login obligará a cambiarla.
 *
 * @param int $longitud
 * @return string
 */
function generar_clave_temporal($longitud = 10)
{
    $caracteres = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($caracteres) - 1;
    $clave = '';
    for ($i = 0; $i < $longitud; $i++) {
        $clave .= $caracteres[random_int(0, $max)];
    }
    return $clave;
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