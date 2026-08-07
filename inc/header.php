<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Sistema de Bodega';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user  = current_user();
$flash = get_flash();

$current_script = $_SERVER['PHP_SELF'];

if (!function_exists('nav_active')) {
    function nav_active($needle) {
        global $current_script;
        return (strpos($current_script, $needle) !== false) ? 'active' : '';
    }
}

// Etiqueta legible del rol
$rolLabels = array(
    'admin'       => 'Administrador',
    'bodega'      => 'Encargado',
    'solicitante' => 'Solicitante'
);
$rolLabel = ($user && isset($rolLabels[$user['rol']])) ? $rolLabels[$user['rol']] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="theme-color" content="#0d1117">
    <title><?php echo h($pageTitle); ?> | Intranet</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/static/css/app.css">
</head>
<body>

<div class="layout-wrapper">

    <?php if ($user): ?>

    <!-- Overlay oscuro -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-header">
            <div class="sidebar-header-row">
                <a href="<?php echo BASE_URL; ?>/index.php" class="sidebar-brand">
                    <i class="bi bi-box-seam"></i>
                    <span>Sistema Bodega</span>
                </a>
                <button class="btn-sidebar-close" id="btnSidebarClose" type="button" aria-label="Cerrar menú">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <hr>
        </div>

        <div class="sidebar-nav">
            <ul class="nav nav-pills flex-column">

                <!-- Inicio: todos los roles -->
                <li>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="nav-link <?php echo (strpos($current_script, BASE_URL . '/index.php') !== false) ? 'active' : ''; ?>">
                        <i class="bi bi-house-door"></i> Inicio
                    </a>
                </li>

                <?php /* ==================================================
                        SOLICITANTE: consultas y solicitudes
                        ================================================== */ ?>
                <?php if (is_solicitante()): ?>

                    <li class="nav-section">Consultas</li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/stock_lista.php" class="nav-link <?php echo nav_active('stock_lista'); ?>">
                            <i class="bi bi-inboxes"></i> Stock de mi Unidad
                        </a>
                    </li>

                    <li class="nav-section">Mis Solicitudes</li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/solicitudes_crear.php" class="nav-link <?php echo nav_active('solicitudes_crear'); ?>">
                            <i class="bi bi-plus-circle"></i> Nueva Solicitud
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/solicitudes_lista.php" class="nav-link <?php echo nav_active('solicitudes_lista'); ?>">
                            <i class="bi bi-clipboard-check"></i> Historial
                        </a>
                    </li>

                <?php endif; ?>

                <?php /* ==================================================
                        ENCARGADO DE BODEGA
                        ================================================== */ ?>
                <?php if (is_encargado()): ?>

                    <li class="nav-section">Operaciones</li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/stock_lista.php" class="nav-link <?php echo nav_active('stock_lista'); ?>">
                            <i class="bi bi-inboxes"></i> Stock de mi Bodega
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/movimientos_lista.php" class="nav-link <?php echo nav_active('movimientos_lista'); ?>">
                            <i class="bi bi-arrow-left-right"></i> Movimientos
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/movimientos_crear.php" class="nav-link <?php echo nav_active('movimientos_crear'); ?>">
                            <i class="bi bi-box-arrow-right"></i> Nuevo Traslado
                        </a>
                    </li>

                    <li class="nav-section">Solicitudes</li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/solicitudes_crear.php" class="nav-link <?php echo nav_active('solicitudes_crear'); ?>">
                            <i class="bi bi-plus-circle"></i> Solicitar Reposición
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/solicitudes_lista.php" class="nav-link <?php echo nav_active('solicitudes_lista'); ?>">
                            <i class="bi bi-clipboard-check"></i> Bandeja Solicitudes
                        </a>
                    </li>

                <?php endif; ?>

                <?php /* ==================================================
                        ADMIN: acceso total
                        ================================================== */ ?>
                <?php if (is_admin()): ?>

                    <li class="nav-section">Operaciones</li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/stock_lista.php" class="nav-link <?php echo nav_active('stock_lista'); ?>">
                            <i class="bi bi-inboxes"></i> Stock
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/movimientos_lista.php" class="nav-link <?php echo nav_active('movimientos_'); ?>">
                            <i class="bi bi-arrow-left-right"></i> Movimientos
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/movimientos/solicitudes_lista.php" class="nav-link <?php echo nav_active('solicitudes'); ?>">
                            <i class="bi bi-clipboard-check"></i> Solicitudes
                        </a>
                    </li>

                    <li class="nav-section">Maestros</li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/bodegas/bodegas_lista.php" class="nav-link <?php echo nav_active('/bodegas/'); ?>">
                            <i class="bi bi-buildings"></i> Bodegas
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/productos/productos_lista.php" class="nav-link <?php echo nav_active('/productos/'); ?>">
                            <i class="bi bi-boxes"></i> Productos
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/proveedores/proveedores_lista.php" class="nav-link <?php echo nav_active('/proveedores/'); ?>">
                            <i class="bi bi-truck"></i> Proveedores
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/facturas/facturas_lista.php" class="nav-link <?php echo nav_active('/facturas/'); ?>">
                            <i class="bi bi-receipt"></i> Facturas
                        </a>
                    </li>

                    <li class="nav-section">Administración</li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/funcionarios/funcionarios_lista.php" class="nav-link <?php echo nav_active('/funcionarios/'); ?>">
                            <i class="bi bi-person-badge"></i> Funcionarios
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/modulos/unidades/unidades_lista.php" class="nav-link <?php echo nav_active('/unidades/'); ?>">
                            <i class="bi bi-diagram-3"></i> Unidades
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo h($user['nombre']); ?></div>
                    <div class="user-role"><?php echo h($rolLabel ? $rolLabel : $user['rol']); ?></div>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i>
                Cerrar sesión
            </a>
        </div>

    </aside>

    <?php endif; ?>

    <!-- ============ MAIN ============ -->
    <div class="main-content" id="mainContent">

        <?php if ($user): ?>
        <div class="mobile-topbar">
            <button class="btn-toggle" id="btnSidebarToggle" type="button" aria-label="Abrir menú">
                <i class="bi bi-list"></i>
            </button>
            <a href="<?php echo BASE_URL; ?>/index.php" class="mobile-brand">
                <i class="bi bi-box-seam"></i>
                <span>Sistema Bodega</span>
            </a>
        </div>
        <?php endif; ?>

        <main class="content-scrollable">
            <?php if ($flash): ?>
                <?php $alertClass = ($flash['type'] === 'error') ? 'alert-danger' : 'alert-success'; ?>
                <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi <?php echo ($flash['type'] === 'error') ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'; ?> me-2"></i>
                    <?php echo h($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

<script>
(function(){
    var btnOpen    = document.getElementById('btnSidebarToggle');
    var btnClose   = document.getElementById('btnSidebarClose');
    var sidebar    = document.getElementById('sidebar');
    var overlay    = document.getElementById('sidebarOverlay');

    if (!sidebar) return;

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }

    if (btnOpen)  btnOpen.addEventListener('click',  function(e){ e.stopPropagation(); openSidebar(); });
    if (btnClose) btnClose.addEventListener('click', closeSidebar);
    if (overlay)  overlay.addEventListener('click',  closeSidebar);

    sidebar.querySelectorAll('.nav-link').forEach(function(a){
        a.addEventListener('click', function(){
            if (window.innerWidth < 992) closeSidebar();
        });
    });

    window.addEventListener('resize', function(){
        if (window.innerWidth >= 992) closeSidebar();
    });
})();
</script>