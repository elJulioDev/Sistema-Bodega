<?php
// inc/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helpers base
function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

// Tiempo máximo de inactividad antes de invalidar la sesión (30 minutos).
if (!defined('SESION_INACTIVIDAD_SEGUNDOS')) {
    define('SESION_INACTIVIDAD_SEGUNDOS', 1800);
}

/**
 * Rechaza la sesión y devuelve al login si el usuario dejó de estar
 * activo en BD, su rol cambió o su sesión caducó por inactividad.
 */
function invalidar_sesion()
{
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    if (function_exists('set_flash')) {
        set_flash('info', 'Tu sesión finalizó. Inicia sesión nuevamente.');
    }
    if (function_exists('redirect')) {
        redirect(BASE_URL . '/login.php');
    }
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    // Expiración por inactividad
    $ultimaActividad = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : 0;
    if ($ultimaActividad > 0 && (time() - $ultimaActividad) > SESION_INACTIVIDAD_SEGUNDOS) {
        invalidar_sesion();
    }
    $_SESSION['last_activity'] = time();

    // Re-validar en cada request: si el usuario fue desactivado o su rol
    // rebajado mientras tenía una sesión activa, dejar de confiar en ella.
    $pdo = $GLOBALS['pdo'];
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, rol, estado, debe_cambiar_clave
                FROM   usuarios
                WHERE  id = ? AND estado = 1
                LIMIT  1
            ");
            $stmt->execute(array((int)$_SESSION['user_id']));
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $fila = false;
        }

        if (!$fila) {
            invalidar_sesion();
        }

        // Sincronizar rol y bandera de cambio de clave si cambiaron en BD
        if ($fila['rol'] !== $_SESSION['user_rol']) {
            $_SESSION['user_rol'] = $fila['rol'];
            $_SESSION['usuario_rol'] = $fila['rol'];
        }
        $debeCambiar = (int)$fila['debe_cambiar_clave'];
        if ((int)($_SESSION['debe_cambiar_clave'] ?? 0) !== $debeCambiar) {
            $_SESSION['debe_cambiar_clave'] = $debeCambiar;
        }

        // Forzar cambio de contraseña en el primer acceso
        if ($debeCambiar === 1) {
            $paginaActual = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
            if ($paginaActual !== 'cambiar_clave.php' && $paginaActual !== 'logout.php') {
                if (function_exists('set_flash')) {
                    set_flash('info', 'Debes cambiar tu contraseña antes de continuar.');
                }
                redirect(BASE_URL . '/modulos/usuarios/cambiar_clave.php');
            }
        }
    }
}

function current_user()
{
    if (!is_logged_in()) {
        return null;
    }

    return array(
        'id'             => (int)$_SESSION['user_id'],
        'nombre'         => isset($_SESSION['user_nombre'])         ? $_SESSION['user_nombre']             : '',
        'usuario'        => isset($_SESSION['user_usuario'])        ? $_SESSION['user_usuario']            : '',
        'rol'            => isset($_SESSION['user_rol'])            ? $_SESSION['user_rol']                : '',
        'id_bodega'      => isset($_SESSION['user_id_bodega'])      ? (int)$_SESSION['user_id_bodega']     : 0,
        'id_unidad'      => isset($_SESSION['user_id_unidad'])      ? (int)$_SESSION['user_id_unidad']     : 0,
        'id_funcionario' => isset($_SESSION['user_id_funcionario']) ? (int)$_SESSION['user_id_funcionario']: 0
    );
}

// Chequeos de rol
function has_role($roles)
{
    if (!is_logged_in()) {
        return false;
    }

    $userRole = isset($_SESSION['user_rol']) ? $_SESSION['user_rol'] : '';

    if (!is_array($roles)) {
        $roles = array($roles);
    }

    return in_array($userRole, $roles, true);
}

/**
 * Exige que el usuario tenga alguno de los roles dados.
 * Si no esta logueado redirige a login.
 * Si esta logueado pero sin permisos, flash de error y redirige al dashboard.
 */
function require_role($roles)
{
    require_login();

    if (!has_role($roles)) {
        if (function_exists('set_flash')) {
            set_flash('error', 'Acceso denegado. No tienes permisos para esta sección.');
        }
        if (function_exists('redirect')) {
            redirect(BASE_URL . '/index.php');
        } else {
            header('Location: ' . BASE_URL . '/index.php');
        }
        exit;
    }
}

// Helpers rapidos por rol
function is_admin()       { return has_role('admin'); }
function is_encargado()   { return has_role('bodega'); }
function is_solicitante() { return has_role('solicitante'); }

function user_bodega_id()
{
    return isset($_SESSION['user_id_bodega']) ? (int)$_SESSION['user_id_bodega'] : 0;
}

function user_unidad_id()
{
    return isset($_SESSION['user_id_unidad']) ? (int)$_SESSION['user_id_unidad'] : 0;
}

function user_funcionario_id()
{
    return isset($_SESSION['user_id_funcionario']) ? (int)$_SESSION['user_id_funcionario'] : 0;
}