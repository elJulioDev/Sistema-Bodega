<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Sistema de Bodega';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
$flash = get_flash();

// Lógica para detectar la página actual
$current_script = $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> | Intranet</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Estilos base */
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f8fafc;
            height: 100vh;      /* Ocupa exactamente el 100% de la pantalla */
            overflow: hidden;   /* Evita el scroll en toda la página */
        }
        
        /* Layout principal */
        .layout-wrapper {
            height: 100vh;
            background-color: #f6f8fa; /* Fondo general más limpio */
        }

        /* Sidebar Minimalista / Estilo Dark */
        .sidebar {
            width: 260px;
            background-color: #0d1117; /* Tono muy oscuro y profesional */
            border-right: 1px solid #30363d;
            color: #c9d1d9;
        }
        
        .sidebar .nav-link {
            color: #8b949e;
            font-weight: 500;
            margin-bottom: 0.25rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.65rem 1rem;
            transition: all 0.2s ease-in-out;
        }
        
        .sidebar .nav-link:hover {
            color: #c9d1d9;
            background-color: rgba(177, 186, 196, 0.12);
        }
        
        .sidebar .nav-link.active {
            color: #ffffff;
            background-color: #161b22;
            border-left: 3px solid #dc3545; /* Acento rojo/corporativo */
            box-shadow: none;
            border-radius: 0 0.375rem 0.375rem 0;
        }

        .sidebar hr.border-secondary {
            border-color: #30363d !important;
        }

        /* Topbar Minimalista */
        .topbar {
            background-color: #1e293b;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            z-index: 10;
        }

        /* Área scrolleable: Aquí es donde ocurre la magia del scroll vertical */
        .content-scrollable {
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1.5rem;
            height: calc(100vh - 64px); /* Resta el alto del topbar */
        }

        /* Tablas Responsive: Fija el encabezado y permite scroll horizontal limpio */
        .table-responsive {
            max-height: calc(100vh - 220px);
            overflow-y: auto;
        }
        .table-responsive thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 1;
            box-shadow: inset 0 -1px 0 #dee2e6;
        }

        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; }
            .main-content { width: 100%; height: calc(100vh - 140px); }
            .layout-wrapper { flex-direction: column; }
            .content-scrollable { height: 100%; }
        }
    </style>
</head>
<body>

<div class="d-flex layout-wrapper">
    <?php if ($user): ?>
    <aside class="sidebar d-flex flex-column p-3 flex-shrink-0">
        <div class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="bi bi-box-seam fs-4 me-2 text-primary"></i>
            <span class="fs-5 fw-bold">Sistema Bodega</span>
        </div>
        <hr class="border-secondary">
        
        <ul class="nav nav-pills flex-column mb-auto overflow-auto">
            <li class="nav-item">
                <a href="/Bodega/index.php" class="nav-link <?php echo (strpos($current_script, 'index.php') !== false && strpos($current_script, 'Bodega/index.php') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <li>
                <a href="/Bodega/modulos/stock/stock_lista.php" class="nav-link <?php echo (strpos($current_script, '/stock/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-inboxes"></i> Stock
                </a>
            </li>

            <?php if (has_role(array('admin', 'bodega'))): ?>
            <li>
                <a href="/Bodega/modulos/proveedores/proveedores_lista.php" class="nav-link <?php echo (strpos($current_script, '/proveedores/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-truck"></i> Proveedores
                </a>
            </li>
            <li>
                <a href="/Bodega/modulos/productos/productos_lista.php" class="nav-link <?php echo (strpos($current_script, '/productos/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-boxes"></i> Productos
                </a>
            </li>
            <li>
                <a href="/Bodega/modulos/ordenes_compra/oc_lista.php" class="nav-link <?php echo (strpos($current_script, '/ordenes_compra/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-cart3"></i> Órdenes de compra
                </a>
            </li>
            <li>
                <a href="/Bodega/modulos/facturas/facturas_lista.php" class="nav-link <?php echo (strpos($current_script, '/facturas/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i> Facturas
                </a>
            </li>
            <li>
                <a href="/Bodega/modulos/movimientos/movimientos_lista.php" class="nav-link <?php echo (strpos($current_script, '/movimientos/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-left-right"></i> Movimientos
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_role('admin')): ?>
            <li class="mt-2 mb-1">
                <span class="text-secondary small fw-bold px-3 text-uppercase" style="letter-spacing: 1px; font-size: 0.7rem;">Configuración</span>
            </li>
            <li>
                <a href="/Bodega/modulos/bodegas/bodegas_lista.php" class="nav-link <?php echo (strpos($current_script, '/bodegas/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-buildings"></i> Bodegas
                </a>
            </li>
            <li>
                <a href="/Bodega/modulos/usuarios/usuarios_lista.php" class="nav-link <?php echo (strpos($current_script, '/usuarios/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Usuarios
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </aside>
    <?php endif; ?>

    <div class="main-content d-flex flex-column flex-grow-1 overflow-hidden">
        
        <header class="topbar px-4 py-2 d-flex justify-content-between align-items-center text-white" style="height: 64px;">
            <div class="fs-5 fw-bold text-truncate">
                <?php echo h($pageTitle); ?>
            </div>
            
            <?php if ($user): ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2 bg-dark bg-opacity-25 px-3 py-1 rounded-pill">
                        <i class="bi bi-person-circle text-primary fs-5"></i>
                        <span class="fw-medium small"><?php echo h($user['nombre']); ?></span>
                        <span class="badge bg-primary ms-1" style="font-size: 0.7rem;"><?php echo strtoupper(h($user['rol'])); ?></span>
                    </div>
                    <a href="/Bodega/logout.php" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1">
                        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Salir</span>
                    </a>
                </div>
            <?php endif; ?>
        </header>

        <main class="content-scrollable">
            <?php if ($flash): ?>
                <?php $alertClass = ($flash['type'] === 'error') ? 'alert-danger' : 'alert-success'; ?>
                <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi <?php echo ($flash['type'] === 'error') ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'; ?> me-2"></i>
                    <?php echo h($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>