<?php
// Iniciar la sesión para poder manipularla
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar las funciones si quieres usar el sistema de mensajes flash
require_once __DIR__ . '/inc/functions.php';

// 1. Vaciar todas las variables de sesión actuales
$_SESSION = array();

// 2. Destruir la cookie de la sesión (opcional pero recomendado por seguridad)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruir la sesión por completo
session_destroy();

// 4. Iniciar una nueva sesión limpia SOLO para poder enviar un mensaje de éxito
session_start();
set_flash('success', 'Has cerrado sesión correctamente.');

// 5. Redirigir al login
redirect('/Bodega/login.php');
exit;