<?php
/**
 * inc/settings.php
 * Configuraciones personalizables del sitio, almacenadas en la tabla `configuraciones`.
 * Reemplazan los textos/logos hardcodeados para permitir personalización sin tocar código.
 *
 * Uso:
 *   site_config('site_nombre', 'Sistema Bodega')
 *   site_config('site_color', '#0d6efd')
 */

function site_config($clave, $default = '')
{
    static $cache = null;

    if ($cache === null) {
        $cache = array();
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                foreach ($pdo->query("SELECT clave, valor FROM configuraciones")->fetchAll() as $r) {
                    $cache[$r['clave']] = $r['valor'];
                }
            } catch (PDOException $e) {
                $cache = array();
            }
        }
    }

    if (isset($cache[$clave]) && $cache[$clave] !== null && $cache[$clave] !== '') {
        return $cache[$clave];
    }
    return $default;
}

/**
 * Convierte un color hex (#0d6efd) a rgba() para generar variantes de la marca.
 * Ej: site_color_rgba('#0d6efd', 0.12) => rgba(13,110,253,0.12)
 */
function site_color_rgba($hex, $alpha = 1)
{
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return 'rgba(13,110,253,' . $alpha . ')';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
}

/**
 * Oscurece un color hex (#0d6efd) en un porcentaje (0..1) para gradientes/hover.
 */
function site_color_darken($hex, $amount = 0.14)
{
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return '#0a58ca';
    }
    $f = max(0, min(1, 1 - (float)$amount));
    $r = (int)round(hexdec(substr($hex, 0, 2)) * $f);
    $g = (int)round(hexdec(substr($hex, 2, 2)) * $f);
    $b = (int)round(hexdec(substr($hex, 4, 2)) * $f);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Guarda (upsert) una clave de configuración en la tabla `configuraciones`.
 */
function site_save_config($clave, $valor)
{
    global $pdo;
    $valor = trim((string)$valor);
    $stmt = $pdo->prepare("INSERT INTO configuraciones (clave, valor) VALUES (:clave, :valor)
                           ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->execute(array(':clave' => $clave, ':valor' => $valor));
}
