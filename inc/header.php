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

    <style>
        :root {
            --sidebar-width: 260px;
            --mobile-topbar-height: 54px;
            --sidebar-bg: #0d1117;
            --sidebar-border: #30363d;
            --sidebar-text: #c9d1d9;
            --sidebar-muted: #8b949e;
            --accent: #dc3545;
        }

        * { -webkit-tap-highlight-color: transparent; }

        html, body { height: 100%; overflow: hidden; }

        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f6f8fa;
        }

        /* ====== LAYOUT ====== */
        .layout-wrapper {
            height: 100vh;
            display: flex;
            background-color: #f6f8fa;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            min-width: 0;
            overflow: hidden;
        }

        /* ====== OVERLAY ====== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 1040;
            cursor: pointer;
        }
        .sidebar-overlay.show { display: block; }

        /* ====== TOPBAR MÓVIL ====== */
        .mobile-topbar {
            display: none;
            height: var(--mobile-topbar-height);
            background-color: var(--sidebar-bg);
            border-bottom: 1px solid var(--sidebar-border);
            align-items: center;
            padding: 0 1rem;
            gap: 0.75rem;
            flex-shrink: 0;
            z-index: 10;
        }

        .mobile-topbar .btn-toggle {
            background: transparent;
            border: none;
            color: #e6edf3;
            font-size: 1.5rem;
            padding: 0;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .mobile-topbar .mobile-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #fff;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .mobile-topbar .mobile-brand .bi {
            color: var(--accent);
            font-size: 1.2rem;
        }

        /* ====== SIDEBAR ====== */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 1rem 1rem 0;
            flex-shrink: 0;
        }

        .sidebar-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.75rem;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #fff;
            text-decoration: none;
        }

        .sidebar-brand .bi { font-size: 1.5rem; color: var(--accent); }
        .sidebar-brand span { font-size: 1.05rem; font-weight: 700; }

        .btn-sidebar-close {
            display: none;
            background: transparent;
            border: none;
            color: var(--sidebar-muted);
            font-size: 1.3rem;
            padding: 0.2rem;
            line-height: 1;
            cursor: pointer;
            border-radius: 0.25rem;
            transition: color 0.15s;
        }
        .btn-sidebar-close:hover { color: #fff; }

        .sidebar-header hr {
            border-color: var(--sidebar-border) !important;
            opacity: 1;
            margin: 0;
        }

        /* Navegación scrolleable */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0.75rem 1rem 1rem;
        }

        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--sidebar-border); border-radius: 4px; }

        .sidebar .nav-section {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--sidebar-muted);
            padding: 0.75rem 1rem 0.25rem;
            margin-top: 0.25rem;
        }

        .sidebar .nav-link {
            color: var(--sidebar-muted);
            font-weight: 500;
            font-size: 0.92rem;
            margin-bottom: 0.15rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.65rem 1rem;
            transition: all 0.15s ease-in-out;
        }

        .sidebar .nav-link .bi { font-size: 1.05rem; }

        .sidebar .nav-link:hover {
            color: var(--sidebar-text);
            background-color: rgba(177, 186, 196, 0.12);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background-color: #161b22;
            border-left: 3px solid var(--accent);
            border-radius: 0 0.375rem 0.375rem 0;
            padding-left: calc(1rem - 3px);
        }

        /* Footer fijo del sidebar */
        .sidebar-footer {
            flex-shrink: 0;
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--sidebar-border);
            background-color: var(--sidebar-bg);
        }

        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 0.65rem;
        }

        .sidebar-footer .user-avatar {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #161b22;
            border: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sidebar-text);
            font-size: 1.15rem;
        }

        .sidebar-footer .user-details { flex: 1; min-width: 0; }

        .sidebar-footer .user-name {
            color: #e6edf3;
            font-size: 0.88rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-footer .user-role {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--sidebar-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-footer .btn-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.45rem 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: #f85149;
            background-color: rgba(248, 81, 73, 0.08);
            border: 1px solid rgba(248, 81, 73, 0.25);
            border-radius: 0.375rem;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-footer .btn-logout:hover {
            background-color: rgba(248, 81, 73, 0.18);
            border-color: rgba(248, 81, 73, 0.5);
            color: #ff6b6b;
        }

        .sidebar-footer .btn-logout .bi { font-size: 1rem; }

        /* ====== CONTENIDO ====== */
        .content-scrollable {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1.5rem;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 1;
            box-shadow: inset 0 -1px 0 #dee2e6;
        }

        /* ====== RESPONSIVE ====== */
        @media (max-width: 991.98px) {
            .mobile-topbar { display: flex; }
            .btn-sidebar-close { display: block; }

            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.25s ease-in-out;
                z-index: 1050;
                box-shadow: 4px 0 24px rgba(0,0,0,0.45);
            }

            .sidebar.show { transform: translateX(0); }

            .content-scrollable { padding: 1.25rem 1rem; }
        }

        @media (max-width: 575.98px) {
            .content-scrollable { padding: 1rem 0.75rem; }
            .sidebar { width: 85%; max-width: 300px; }
        }
    </style>
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
                <a href="/Bodega/index.php" class="sidebar-brand">
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
                    <a href="/Bodega/index.php" class="nav-link <?php echo (strpos($current_script, '/Bodega/index.php') !== false) ? 'active' : ''; ?>">
                        <i class="bi bi-house-door"></i> Inicio
                    </a>
                </li>

                <?php /* ==================================================
                        SOLICITANTE: solo solicitudes
                        ================================================== */ ?>
                <?php if (is_solicitante()): ?>

                    <li class="nav-section">Mis Solicitudes</li>

                    <li>
                        <a href="/Bodega/modulos/movimientos/solicitudes_crear.php" class="nav-link <?php echo nav_active('solicitudes_crear'); ?>">
                            <i class="bi bi-plus-circle"></i> Nueva Solicitud
                        </a>
                    </li>

                    <li>
                        <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="nav-link <?php echo nav_active('solicitudes_lista'); ?>">
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
                        <a href="/Bodega/modulos/stock_lista.php" class="nav-link <?php echo nav_active('stock_lista'); ?>">
                            <i class="bi bi-inboxes"></i> Stock de mi Bodega
                        </a>
                    </li>

                    <li>
                        <a href="/Bodega/modulos/movimientos/movimientos_lista.php" class="nav-link <?php echo nav_active('movimientos_lista'); ?>">
                            <i class="bi bi-arrow-left-right"></i> Movimientos
                        </a>
                    </li>

                    <li>
                        <a href="/Bodega/modulos/movimientos/movimientos_crear.php" class="nav-link <?php echo nav_active('movimientos_crear'); ?>">
                            <i class="bi bi-box-arrow-right"></i> Nuevo Traslado
                        </a>
                    </li>

                    <li class="nav-section">Solicitudes</li>

                    <li>
                        <a href="/Bodega/modulos/movimientos/solicitudes_crear.php" class="nav-link <?php echo nav_active('solicitudes_crear'); ?>">
                            <i class="bi bi-plus-circle"></i> Solicitar Reposición
                        </a>
                    </li>

                    <li>
                        <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="nav-link <?php echo nav_active('solicitudes_lista'); ?>">
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
                        <a href="/Bodega/modulos/stock_lista.php" class="nav-link <?php echo nav_active('stock_lista'); ?>">
                            <i class="bi bi-inboxes"></i> Stock
                        </a>
                    </li>
                    <li>
                        <a href="/Bodega/modulos/movimientos/movimientos_lista.php" class="nav-link <?php echo nav_active('movimientos_'); ?>">
                            <i class="bi bi-arrow-left-right"></i> Movimientos
                        </a>
                    </li>
                    <li>
                        <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="nav-link <?php echo nav_active('solicitudes'); ?>">
                            <i class="bi bi-clipboard-check"></i> Solicitudes
                        </a>
                    </li>

                    <li class="nav-section">Maestros</li>

                    <li>
                        <a href="/Bodega/modulos/bodegas/bodegas_lista.php" class="nav-link <?php echo nav_active('/bodegas/'); ?>">
                            <i class="bi bi-buildings"></i> Bodegas
                        </a>
                    </li>
                    <li>
                        <a href="/Bodega/modulos/productos/productos_lista.php" class="nav-link <?php echo nav_active('/productos/'); ?>">
                            <i class="bi bi-boxes"></i> Productos
                        </a>
                    </li>
                    <li>
                        <a href="/Bodega/modulos/proveedores/proveedores_lista.php" class="nav-link <?php echo nav_active('/proveedores/'); ?>">
                            <i class="bi bi-truck"></i> Proveedores
                        </a>
                    </li>
                    <li>
                        <a href="/Bodega/modulos/facturas/facturas_lista.php" class="nav-link <?php echo nav_active('/facturas/'); ?>">
                            <i class="bi bi-receipt"></i> Facturas
                        </a>
                    </li>

                    <li class="nav-section">Administración</li>

                    <li>
                        <a href="/Bodega/modulos/funcionarios/funcionarios_lista.php" class="nav-link <?php echo nav_active('/funcionarios/'); ?>">
                            <i class="bi bi-person-badge"></i> Funcionarios
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
            <a href="/Bodega/logout.php" class="btn-logout">
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
            <a href="/Bodega/index.php" class="mobile-brand">
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