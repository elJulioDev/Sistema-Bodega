<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Sistema de Bodega';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/ui.php';

$user  = current_user();
$flash = get_flash();

$current_script = $_SERVER['PHP_SELF'];

if (!function_exists('nav_active')) {
    function nav_active($needle) {
        global $current_script;
        return (strpos($current_script, $needle) !== false) ? 'active' : '';
    }
}

// ---- Configuración del sitio (personalizable desde el panel admin) ----
$siteNombre = site_config('site_nombre', 'Sistema Bodega');
$siteIcono  = site_config('site_icono', 'bi-box-seam');
$siteColor  = site_config('site_color', '#0d6efd');
$siteColorSec = site_config('site_color_secundario', '#8b5cf6');
$temaDefecto = site_config('tema_default', 'auto');
$temaDefecto = in_array($temaDefecto, array('light', 'dark', 'auto'), true) ? $temaDefecto : 'auto';

$brandSoft = site_color_rgba($siteColor, 0.10);
$brandSoftStrong = site_color_rgba($siteColor, 0.18);
$accentSoft = site_color_rgba($siteColorSec, 0.12);

// Etiqueta legible del rol
$rolLabels = array(
    'admin'       => 'Administrador',
    'bodega'      => 'Encargado',
    'solicitante' => 'Solicitante'
);
$rolLabel = ($user && isset($rolLabels[$user['rol']])) ? $rolLabels[$user['rol']] : '';

// Inicial del usuario para el avatar
$userNombre = ($user && isset($user['nombre'])) ? trim($user['nombre']) : 'U';
$userInitial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($userNombre, 0, 1, 'UTF-8')) : strtoupper(substr($userNombre, 0, 1));
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="theme-color" content="#0d1117">
    <title><?php echo h($pageTitle); ?> | <?php echo h($siteNombre); ?></title>

    <!-- Aplica tema y estado del sidebar ANTES de renderizar (evita parpadeo) -->
    <script>
    (function(){
        var saved = null;
        try { saved = localStorage.getItem('sb_theme'); } catch (e) {}
        var t = saved || '<?php echo $temaDefecto; ?>';
        var dark = (t === 'dark') || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');

        var collapsed = false;
        try { collapsed = localStorage.getItem('sb_collapsed') === '1'; } catch (e) {}
        document.documentElement.setAttribute('data-sb-collapsed', collapsed ? '1' : '0');
    })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/static/css/app.css">

    <!-- Colores personalizados: deben ir DESPUÉS de app.css para no ser sobrescritos -->
    <style>
    :root,
    [data-bs-theme="dark"] {
        --app-brand: <?php echo $siteColor; ?>;
        --app-brand-soft: <?php echo $brandSoft; ?>;
        --app-brand-soft-strong: <?php echo $brandSoftStrong; ?>;
        --app-accent: <?php echo $siteColorSec; ?>;
        --app-accent-soft: <?php echo $accentSoft; ?>;
        --app-sidebar-active: <?php echo $brandSoft; ?>;
        --app-sidebar-active-border: <?php echo $siteColor; ?>;
    }
    </style>
</head>
<body>

<div class="layout-wrapper">

    <?php if ($user): ?>

    <!-- Overlay oscuro (móvil) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar" id="sidebar" aria-label="Menú principal">

        <div class="sidebar-header">
            <div class="sidebar-header-row">
                <a href="<?php echo BASE_URL; ?>/index.php" class="sidebar-brand" title="<?php echo h($siteNombre); ?>">
                    <span class="sidebar-brand-icon"><i class="bi <?php echo h($siteIcono); ?>"></i></span>
                    <span class="sidebar-brand-name"><?php echo h($siteNombre); ?></span>
                </a>
                <button class="btn-sidebar-close" id="btnSidebarClose" type="button" aria-label="Cerrar menú">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <hr>
        </div>

        <?php
        // ---- Menú por rol (misma estructura que siempre; solo presentación) ----
        $nav = array();

        $nav[] = array(
            'label' => 'Inicio',
            'url'   => BASE_URL . '/index.php',
            'icon'  => 'bi-house-door',
            'active'=> (strpos($current_script, BASE_URL . '/index.php') !== false) ? 'active' : '',
            'section' => null,
        );

        if (is_solicitante()) {
            $nav[] = array('section' => 'Consultas');
            $nav[] = array('label' => 'Stock de mi Unidad', 'url' => BASE_URL . '/modulos/stock_lista.php', 'icon' => 'bi-inboxes', 'active' => nav_active('stock_lista'));
            $nav[] = array('section' => 'Mis Solicitudes');
            $nav[] = array('label' => 'Nueva Solicitud', 'url' => BASE_URL . '/modulos/movimientos/solicitudes_crear.php', 'icon' => 'bi-plus-circle', 'active' => nav_active('solicitudes_crear'));
            $nav[] = array('label' => 'Historial', 'url' => BASE_URL . '/modulos/movimientos/solicitudes_lista.php', 'icon' => 'bi-clipboard-check', 'active' => nav_active('solicitudes_lista'));
        }

        if (is_encargado()) {
            $nav[] = array('section' => 'Operaciones');
            $nav[] = array('label' => 'Stock de mi Bodega', 'url' => BASE_URL . '/modulos/stock_lista.php', 'icon' => 'bi-inboxes', 'active' => nav_active('stock_lista'));
            $nav[] = array('label' => 'Movimientos', 'url' => BASE_URL . '/modulos/movimientos/movimientos_lista.php', 'icon' => 'bi-arrow-left-right', 'active' => nav_active('movimientos_lista'));
            $nav[] = array('label' => 'Nuevo Traslado', 'url' => BASE_URL . '/modulos/movimientos/movimientos_crear.php', 'icon' => 'bi-box-arrow-right', 'active' => nav_active('movimientos_crear'));
            $nav[] = array('section' => 'Solicitudes');
            $nav[] = array('label' => 'Solicitar Reposición', 'url' => BASE_URL . '/modulos/movimientos/solicitudes_crear.php', 'icon' => 'bi-plus-circle', 'active' => nav_active('solicitudes_crear'));
            $nav[] = array('label' => 'Bandeja Solicitudes', 'url' => BASE_URL . '/modulos/movimientos/solicitudes_lista.php', 'icon' => 'bi-clipboard-check', 'active' => nav_active('solicitudes_lista'));
        }

        if (is_admin()) {
            $nav[] = array('section' => 'Operaciones');
            $nav[] = array('label' => 'Stock', 'url' => BASE_URL . '/modulos/stock_lista.php', 'icon' => 'bi-inboxes', 'active' => nav_active('stock_lista'));
            $nav[] = array('label' => 'Movimientos', 'url' => BASE_URL . '/modulos/movimientos/movimientos_lista.php', 'icon' => 'bi-arrow-left-right', 'active' => nav_active('movimientos_lista'));
            $nav[] = array('label' => 'Solicitudes', 'url' => BASE_URL . '/modulos/movimientos/solicitudes_lista.php', 'icon' => 'bi-clipboard-check', 'active' => nav_active('solicitudes'));
            $nav[] = array('section' => 'Maestros');
            $nav[] = array('label' => 'Bodegas', 'url' => BASE_URL . '/modulos/bodegas/bodegas_lista.php', 'icon' => 'bi-buildings', 'active' => nav_active('/bodegas/'));
            $nav[] = array('label' => 'Productos', 'url' => BASE_URL . '/modulos/productos/productos_lista.php', 'icon' => 'bi-boxes', 'active' => nav_active('/productos/'));
            $nav[] = array('label' => 'Proveedores', 'url' => BASE_URL . '/modulos/proveedores/proveedores_lista.php', 'icon' => 'bi-truck', 'active' => nav_active('/proveedores/'));
            $nav[] = array('label' => 'Facturas', 'url' => BASE_URL . '/modulos/facturas/facturas_lista.php', 'icon' => 'bi-receipt', 'active' => nav_active('/facturas/'));
            $nav[] = array('section' => 'Administración');
            $nav[] = array('label' => 'Funcionarios', 'url' => BASE_URL . '/modulos/funcionarios/funcionarios_lista.php', 'icon' => 'bi-person-badge', 'active' => nav_active('/funcionarios/'));
            $nav[] = array('label' => 'Unidades', 'url' => BASE_URL . '/modulos/unidades/unidades_lista.php', 'icon' => 'bi-diagram-3', 'active' => nav_active('/unidades/'));
            $nav[] = array('label' => 'Personalización', 'url' => BASE_URL . '/modulos/configuraciones/editar.php', 'icon' => 'bi-sliders', 'active' => nav_active('/configuraciones/'));
        }
        ?>

        <div class="sidebar-nav" id="sidebarNav">
            <ul class="nav nav-pills flex-column">
                <?php foreach ($nav as $item): ?>
                    <?php if (isset($item['section']) && $item['section']): ?>
                        <li class="nav-section"><?php echo h($item['section']); ?></li>
                    <?php else: ?>
                        <li>
                            <a href="<?php echo h($item['url']); ?>" class="nav-link <?php echo $item['active']; ?>" title="<?php echo h($item['label']); ?>">
                                <i class="bi <?php echo h($item['icon']); ?>"></i>
                                <span class="nav-label"><?php echo h($item['label']); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

    </aside>

    <!-- Restaura el scroll del sidebar durante el render (sin parpadeo al navegar) -->
    <script>
    (function(){
        var nav = document.getElementById('sidebarNav');
        if (!nav) return;
        try {
            var top = parseInt(localStorage.getItem('sb_scroll'), 10) || 0;
            if (top > 0) nav.scrollTop = top;
        } catch (e) {}
    })();
    </script>

    <?php endif; ?>

    <!-- ============ MAIN ============ -->
    <div class="main-content" id="mainContent">

        <?php if ($user): ?>
        <!-- ============ TOPBAR ============ -->
        <header class="topbar">
            <button class="btn-toggle" id="btnSidebarCollapse" type="button" aria-label="Abrir o cerrar menú">
                <i class="bi bi-list d-inline-flex d-lg-none"></i>
                <i class="bi bi-layout-sidebar d-none d-lg-inline-flex"></i>
            </button>

            <a href="<?php echo BASE_URL; ?>/index.php" class="topbar-brand" title="<?php echo h($siteNombre); ?>">
                <span class="topbar-brand-icon"><i class="bi <?php echo h($siteIcono); ?>"></i></span>
                <span class="d-none d-sm-inline"><?php echo h($siteNombre); ?></span>
            </a>

            <nav class="topbar-breadcrumb" aria-label="Miga de pan">
                <?php $crumbs = ui_breadcrumb_items($pageTitle); foreach ($crumbs as $i => $c): ?>
                    <?php if ($i > 0): ?><span class="crumb-sep bi bi-chevron-right"></span><?php endif; ?>
                    <?php if ($c['current']): ?>
                        <span class="crumb-current" title="<?php echo h($c['label']); ?>"><?php echo h($c['label']); ?></span>
                    <?php else: ?>
                        <a href="<?php echo h($c['url']); ?>" class="text-decoration-none crumb-link"><?php echo h($c['label']); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <span class="topbar-spacer"></span>

            <div class="topbar-right">
                <!-- Selector de tema -->
                <div class="dropdown">
                    <button class="btn-icon dropdown-toggle" data-bs-toggle="dropdown" aria-label="Cambiar tema" aria-expanded="false">
                        <i class="bi bi-circle-half" id="themeIcon"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header"><i class="bi bi-palette me-1"></i>Tema de la interfaz</h6></li>
                        <li><button type="button" class="dropdown-item" data-theme-option="light" data-theme-label="Claro"><i class="bi bi-sun me-2"></i>Claro</button></li>
                        <li><button type="button" class="dropdown-item" data-theme-option="dark" data-theme-label="Oscuro"><i class="bi bi-moon me-2"></i>Oscuro</button></li>
                        <li><button type="button" class="dropdown-item" data-theme-option="auto" data-theme-label="Auto"><i class="bi bi-circle-half me-2"></i>Auto (sistema)</button></li>
                    </ul>
                </div>

                <!-- Menú de usuario -->
                <div class="dropdown">
                    <button class="topbar-user-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de usuario">
                        <span class="user-avatar"><?php echo h($userInitial); ?></span>
                        <span class="user-meta">
                            <span class="u-name"><?php echo h($user['nombre']); ?></span>
                            <span class="u-role"><?php echo h($rolLabel ? $rolLabel : $user['rol']); ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><span class="dropdown-item-text fw-semibold"><?php echo h($user['nombre']); ?></span></li>
                        <li><span class="dropdown-item-text small text-muted"><?php echo h($rolLabel ? $rolLabel : $user['rol']); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (is_admin()): ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/modulos/configuraciones/editar.php">
                                <i class="bi bi-sliders me-2"></i>Personalización
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <?php endif; ?>

        <main class="content-scrollable">
            <?php if ($flash): ?>
                <?php
                // Mensajes del sistema: se muestran como modal centrado (ver inc/header.php)
                $flashTitle = 'Operación exitosa';
                $flashIcon  = 'bi-check-circle-fill';
                $flashColor = 'success';
                if ($flash['type'] === 'error') {
                    $flashTitle = 'Error';
                    $flashIcon  = 'bi-x-circle-fill';
                    $flashColor = 'danger';
                } elseif ($flash['type'] === 'danger') {
                    $flashTitle = 'Atención';
                    $flashIcon  = 'bi-exclamation-triangle-fill';
                    $flashColor = 'danger';
                }
                ?>
                <div class="modal fade" id="flashModal" tabindex="-1" aria-hidden="true"
                     aria-labelledby="flashModalTitle" role="dialog">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content app-modal">
                            <div class="app-modal-body">
                                <div class="app-modal-icon app-modal-icon-<?php echo $flashColor; ?>">
                                    <i class="bi <?php echo $flashIcon; ?>"></i>
                                </div>
                                <h5 class="app-modal-title" id="flashModalTitle"><?php echo h($flashTitle); ?></h5>
                                <p class="app-modal-msg"><?php echo h($flash['message']); ?></p>
                            </div>
                            <div class="app-modal-footer">
                                <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">
                                    <i class="bi bi-check2 me-1"></i> Entendido
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                (function(){
                    var m = document.getElementById('flashModal');
                    if (!m) return;
                    // Bootstrap se carga al final del body (footer.php); reintentar hasta que exista
                    var showModal = function(){
                        if (window.bootstrap) {
                            bootstrap.Modal.getOrCreateInstance(m).show();
                        } else {
                            setTimeout(showModal, 50);
                        }
                    };
                    showModal();
                })();
                </script>
            <?php endif; ?>

<script>
(function(){
    var sidebar    = document.getElementById('sidebar');
    var overlay    = document.getElementById('sidebarOverlay');
    var btnToggle  = document.getElementById('btnSidebarCollapse');
    var btnClose   = document.getElementById('btnSidebarClose');

    if (!sidebar) return;

    /* ---------- Tema (claro / oscuro / auto) ---------- */
    var THEME_KEY = 'sb_theme';
    var themeBtn = document.getElementById('themeIcon');
    var themeOptions = document.querySelectorAll('[data-theme-option]');

    function currentStoredTheme() {
        try { return localStorage.getItem(THEME_KEY) || '<?php echo $temaDefecto; ?>'; }
        catch (e) { return '<?php echo $temaDefecto; ?>'; }
    }
    function resolveTheme(t) {
        if (t === 'dark') return 'dark';
        if (t === 'light') return 'light';
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    function applyTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', resolveTheme(t));
    }
    function themeIconName(t) {
        return t === 'dark' ? 'bi-moon-stars-fill' : (t === 'light' ? 'bi-sun-fill' : 'bi-circle-half');
    }
    function renderThemeUI(t) {
        if (themeBtn) themeBtn.className = themeIconName(t);
        themeOptions.forEach(function(opt){
            var check = opt.querySelector('.theme-check');
            if (check) check.remove();
            if (opt.getAttribute('data-theme-option') === t) {
                var i = document.createElement('i');
                i.className = 'bi bi-check2 theme-check float-end text-success';
                opt.appendChild(i);
            }
        });
    }
    function setTheme(t, persist) {
        if (persist) { try { localStorage.setItem(THEME_KEY, t); } catch (e) {} }
        applyTheme(t);
        renderThemeUI(t);
    }

    themeOptions.forEach(function(opt){
        opt.addEventListener('click', function(){
            setTheme(opt.getAttribute('data-theme-option'), true);
            var dd = opt.closest('.dropdown');
            var btn = dd.querySelector('[data-bs-toggle="dropdown"]');
            if (btn && window.bootstrap) bootstrap.Dropdown.getOrCreateInstance(btn).hide();
        });
    });

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(){
            if (currentStoredTheme() === 'auto') applyTheme('auto');
        });
    }

    renderThemeUI(currentStoredTheme());

    /* ---------- Sidebar: drawer (móvil) / colapsable (escritorio) ---------- */
    var COLLAPSE_KEY = 'sb_collapsed';

    function isMobile() { return window.innerWidth < 992; }

    function openDrawer() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
    }
    function closeDrawer() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }

    function isCollapsed() {
        return document.documentElement.getAttribute('data-sb-collapsed') === '1';
    }

    function applyCollapsed(collapsed) {
        document.documentElement.setAttribute('data-sb-collapsed', collapsed ? '1' : '0');
        try { localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0'); } catch (e) {}
    }

    // El estado ya fue restaurado de forma síncrona en el <head>; aquí solo falta el toggle
    if (btnToggle) {
        btnToggle.addEventListener('click', function(){
            if (isMobile()) {
                openDrawer();
            } else {
                applyCollapsed(!isCollapsed());
            }
        });
    }
    if (btnClose) btnClose.addEventListener('click', closeDrawer);
    if (overlay)  overlay.addEventListener('click',  closeDrawer);

    sidebar.querySelectorAll('.nav-link').forEach(function(a){
        a.addEventListener('click', function(){
            if (isMobile()) closeDrawer();
        });
    });

    /* ---------- Persistir el scroll del sidebar entre páginas ---------- */
    var sidebarNav = document.getElementById('sidebarNav');
    if (sidebarNav) {
        var scrollTimer = null;
        sidebarNav.addEventListener('scroll', function(){
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function(){
                try { localStorage.setItem('sb_scroll', String(sidebarNav.scrollTop)); } catch (e) {}
            }, 150);
        });
    }

    window.addEventListener('resize', function(){
        if (window.innerWidth >= 992) closeDrawer();
    });
})();
</script>
