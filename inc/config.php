<?php
// inc/config.php
require_once __DIR__ . '/env.php';

load_env(__DIR__ . '/../.env');

define('DB_HOST',    env('DB_HOST', '127.0.0.1'));
define('DB_PORT',    env('DB_PORT', '3306'));
define('DB_NAME',    env('DB_NAME', 'sistema_bodega'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Sin barra final. Ej: '' si vive en raíz del dominio, '/Bodega' si vive en subcarpeta.
define('BASE_URL', rtrim(env('BASE_URL', ''), '/'));

define('APP_ENV', env('APP_ENV', 'production'));

if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ─────────────────────────────────────────────────────────────
// Sesiones seguras
// Aplica a todos los session_start() del sistema (auth, csrf, functions, logout).
// ─────────────────────────────────────────────────────────────
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

$es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $es_https,
    'httponly' => true,
    'samesite' => 'Lax',
));

// ─────────────────────────────────────────────────────────────
// Cabeceras de seguridad
// CSP moderada: permite estilos/scripts inline (tema, json en <script>)
// y solo los CDN usados (Bootstrap Icons, Google Fonts, Chart.js).
// ─────────────────────────────────────────────────────────────
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        . "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'"
    );
}