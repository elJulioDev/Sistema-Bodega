<?php
// inc/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------
// Helpers base
// -------------------------------------------------------------

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
        'id'             => (int)$_SESSION['user_id'],
        'nombre'         => isset($_SESSION['user_nombre'])         ? $_SESSION['user_nombre']             : '',
        'usuario'        => isset($_SESSION['user_usuario'])        ? $_SESSION['user_usuario']            : '',
        'rol'            => isset($_SESSION['user_rol'])            ? $_SESSION['user_rol']                : '',
        'id_bodega'      => isset($_SESSION['user_id_bodega'])      ? (int)$_SESSION['user_id_bodega']     : 0,
        'id_unidad'      => isset($_SESSION['user_id_unidad'])      ? (int)$_SESSION['user_id_unidad']     : 0,
        'id_funcionario' => isset($_SESSION['user_id_funcionario']) ? (int)$_SESSION['user_id_funcionario']: 0
    );
}

// -------------------------------------------------------------
// Chequeos de rol
// -------------------------------------------------------------

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
            redirect('/Bodega/index.php');
        } else {
            header('Location: /Bodega/index.php');
        }
        exit;
    }
}

// -------------------------------------------------------------
// Helpers rapidos por rol
// -------------------------------------------------------------

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