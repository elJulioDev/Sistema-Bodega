<?php
// inc/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: /Bodega/login.php');
        exit;
    }
}

function current_user()
{
    if (!is_logged_in()) {
        return null;
    }

    return array(
        'id'     => $_SESSION['user_id'],
        'nombre' => isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : '',
        'usuario'=> isset($_SESSION['user_usuario']) ? $_SESSION['user_usuario'] : '',
        'rol'    => isset($_SESSION['user_rol']) ? $_SESSION['user_rol'] : ''
    );
}

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

function require_role($roles)
{
    if (!has_role($roles)) {
        http_response_code(403);
        die('Acceso denegado.');
    }
}