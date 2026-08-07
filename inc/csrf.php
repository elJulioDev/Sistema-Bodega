<?php
// inc/csrf.php

// Nos aseguramos de que la sesión esté iniciada para poder guardar el token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Genera un token seguro y lo guarda en la sesión
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Campo oculto listo para pegar dentro de un <form method="post">
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Valida que el token enviado por el formulario coincida con el de la sesión.
// Solo actúa sobre peticiones POST; GET no se valida.
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
        csrf_fail();
    }
}

// Detiene la petición sin ejecutar ninguna acción y avisa al usuario.
function csrf_fail() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = array(
        'type'    => 'error',
        'message' => 'Token de seguridad inválido o expirado. Tu sesión pudo haber expirado; vuelve a intentarlo.'
    );
    $dest = defined('BASE_URL') ? BASE_URL . '/index.php' : '../index.php';
    header('Location: ' . $dest);
    exit;
}

// Protección automática: con solo incluir este archivo se valida cualquier POST.
// Se ejecuta una sola vez por petición para no duplicar validaciones.
if (empty($GLOBALS['csrf_auto_checked'])) {
    $GLOBALS['csrf_auto_checked'] = true;
    csrf_check();
}
