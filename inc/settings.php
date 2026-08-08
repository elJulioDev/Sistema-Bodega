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
 * Genera el favicon del sitio como SVG inline (data URI).
 * Usa el ícono por defecto del sistema (bi-box-seam) y los colores de la marca,
 * por lo que se mantiene igual en todas las pestañas y sigue la personalización.
 */
function site_favicon()
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $brand  = site_config('site_color', '#0d6efd');
    $accent = site_config('site_color_secundario', '#8b5cf6');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . $brand . '"/>'
        . '<stop offset="1" stop-color="' . $accent . '"/>'
        . '</linearGradient></defs>'
        . '<rect width="64" height="64" rx="14" fill="url(#g)"/>'
        . '<g fill="#ffffff" transform="translate(8 8) scale(3)">'
        . '<path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2l-2.218-.887zm3.564 1.426L5.596 5 8 5.961 14.154 3.5l-2.404-.961zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923l6.5 2.6zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464L7.443.184z"/>'
        . '</g></svg>';

    $cached = 'data:image/svg+xml,' . rawurlencode($svg);
    return $cached;
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
