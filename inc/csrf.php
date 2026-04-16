<?php
// C:\xampp\htdocs\IntranetColtauco\inc\csrf.php

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

// Valida que el token enviado por el formulario coincida con el de la sesión
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
            die('Error de seguridad: Token CSRF inválido.');
        }
    }
}
?>