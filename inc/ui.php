<?php
/**
 * inc/ui.php
 * Helpers de UI reutilizables para mantener markup consistente
 * en todas las páginas (cabeceras, badges, estados vacíos, alertas, breadcrumbs).
 * Solo emiten HTML: no dependen de sesión/auth.
 */

/**
 * Cabecera de página: icono + título + subtítulo + acciones (botones HTML).
 * Uso: ui_page_header('bi-boxes', 'Catálogo de Productos', 'Descripción', '<a ...>Nuevo</a>');
 */
function ui_page_header($icon, $title, $subtitle = '', $actions = '')
{
    ?>
    <div class="page-header">
        <div class="page-header-title">
            <span class="page-header-icon"><i class="bi <?php echo h($icon); ?>"></i></span>
            <div>
                <h1><?php echo h($title); ?></h1>
                <?php if ($subtitle !== ''): ?>
                    <p class="page-header-sub"><?php echo h($subtitle); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($actions !== ''): ?>
            <div class="page-header-actions"><?php echo $actions; ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Enlace con estilo de botón para usar dentro de ui_page_header().
 * ui_btn_link('Nuevo', 'crear.php', 'primary', 'bi-plus-lg')
 */
function ui_btn_link($label, $url, $variant = 'primary', $icon = '', $size = 'sm', $attrs = '')
{
    $cls = 'btn btn-' . $size . ' btn-' . $variant;
    $ico = ($icon !== '') ? '<i class="bi ' . h($icon) . ' me-1"></i>' : '';
    echo '<a href="' . h($url) . '" class="' . $cls . '" ' . $attrs . '>' . $ico . h($label) . '</a>';
}

/**
 * Botón de submit con estilo.
 * ui_btn_submit('Guardar', 'primary', 'bi-check-lg')
 */
function ui_btn_submit($label, $variant = 'primary', $icon = '', $attrs = '')
{
    $cls = 'btn btn-' . $variant;
    $ico = ($icon !== '') ? '<i class="bi ' . h($icon) . ' me-1"></i>' : '';
    echo '<button type="submit" class="' . $cls . '" ' . $attrs . '>' . $ico . h($label) . '</button>';
}

/**
 * Badge soft (pastilla) con color semántico.
 * ui_badge('Activo', 'success')
 */
function ui_badge($text, $variant = 'secondary')
{
    $variants = array('primary', 'success', 'danger', 'warning', 'info', 'secondary', 'dark');
    if (!in_array($variant, $variants, true)) {
        $variant = 'secondary';
    }
    echo '<span class="badge badge-soft badge-soft-' . $variant . '">' . h($text) . '</span>';
}

/**
 * Estado vacío reutilizable para listas/resultados sin datos.
 * ui_empty_state('bi-inbox', 'Sin resultados', 'Texto opcional', '<a ...>Crear</a>');
 */
function ui_empty_state($icon, $title, $text = '', $actions = '')
{
    ?>
    <div class="empty-state">
        <i class="bi <?php echo h($icon); ?> empty-icon"></i>
        <h5><?php echo h($title); ?></h5>
        <?php if ($text !== ''): ?>
            <p><?php echo h($text); ?></p>
        <?php endif; ?>
        <?php if ($actions !== ''): ?>
            <div class="mt-3"><?php echo $actions; ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Alerta tipo flash reutilizable.
 * ui_alert('success', 'Cambios guardados.') — acepta success|error|warning|info.
 */
function ui_alert($type, $message)
{
    $map = array(
        'success' => array('alert-success', 'bi-check-circle-fill'),
        'error'   => array('alert-danger', 'bi-exclamation-triangle-fill'),
        'danger'  => array('alert-danger', 'bi-exclamation-triangle-fill'),
        'warning' => array('alert-warning', 'bi-exclamation-circle-fill'),
        'info'    => array('alert-info', 'bi-info-circle-fill'),
    );
    if (!isset($map[$type])) {
        $type = 'info';
    }
    list($cls, $icon) = $map[$type];
    echo '<div class="alert ' . $cls . ' alert-dismissible fade show shadow-sm" role="alert">'
        . '<i class="bi ' . $icon . ' me-2"></i>' . h($message)
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>'
        . '</div>';
}

/**
 * Breadcrumb derivado del path actual + $pageTitle.
 * Devuelve array de items: ['label'=>..., 'url'=>...|null, 'current'=>bool]
 */
function ui_breadcrumb_items($pageTitle)
{
    global $current_script;

    $items = array(array('label' => 'Inicio', 'url' => BASE_URL . '/index.php', 'current' => false));

    $modLabels = array(
        'bodegas'        => 'Bodegas',
        'productos'      => 'Productos',
        'proveedores'    => 'Proveedores',
        'facturas'       => 'Facturas',
        'ordenes_compra' => 'Órdenes de Compra',
        'funcionarios'   => 'Funcionarios',
        'unidades'       => 'Unidades',
        'usuarios'       => 'Usuarios',
        'movimientos'    => 'Movimientos',
        'configuraciones'=> 'Personalización',
    );

    if (isset($current_script) && preg_match('#/modulos/([^/]+)/#', $current_script, $m) && isset($modLabels[$m[1]])) {
        $items[] = array('label' => $modLabels[$m[1]], 'url' => null, 'current' => false);
    } elseif (isset($current_script) && strpos($current_script, '/modulos/stock_lista.php') !== false) {
        $items[] = array('label' => 'Stock', 'url' => null, 'current' => false);
    }

    $items[] = array('label' => $pageTitle, 'url' => null, 'current' => true);
    return $items;
}
