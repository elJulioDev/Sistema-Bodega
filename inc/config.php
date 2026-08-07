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