<?php
/**
 * header.php — Cabecera del sistema de bodega
 * Incluye: topbar, sidebar, apertura del layout
 *
 * Variables esperadas (opcional, definir antes de incluir):
 *   $pageTitle      — Título de la página actual
 *   $pageSection    — Sección activa del sidebar (para marcar active)
 */

// Seguridad: requiere sesión iniciada
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Datos del usuario logueado
$usuarioNombre  = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario';
$usuarioRol     = isset($_SESSION['usuario_rol'])    ? $_SESSION['usuario_rol']    : 'consulta';
$usuarioInicial = strtoupper(substr($usuarioNombre, 0, 1));

// Página activa
$paginaActual = isset($pageSection) ? $pageSection : '';
$tituloPagina = isset($pageTitle)   ? $pageTitle   : 'Bodega Virtual';

// Helper: clase 'active' para el nav — evita redeclaración si header se incluye varias veces
if (!function_exists('navActive')) {
    function navActive($section) {
        global $paginaActual;
        return ($paginaActual === $section) ? 'active' : '';
    }
}

// Helper: ¿el usuario tiene permiso para ver sección?
if (!function_exists('puedeVer')) {
    function puedeVer($rolesPermitidos) {
        global $usuarioRol;
        return in_array($usuarioRol, $rolesPermitidos);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?php echo htmlspecialchars($tituloPagina); ?> — Bodega Virtual</title>

  <!-- Bootstrap 4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <!-- Sistema propio -->
  <link rel="stylesheet" href="static/css/style.css">
</head>
<body>

<div id="wrapper">

  <!-- ═══════════════════════════════════════════════
       OVERLAY MOBILE
  ════════════════════════════════════════════════ -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- ═══════════════════════════════════════════════
       SIDEBAR
  ════════════════════════════════════════════════ -->
  <nav id="sidebar">

    <!-- Marca -->
    <a href="index.php" class="sidebar-brand">
      <div class="sidebar-brand-icon">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
      </div>
      <div class="sidebar-brand-text">
        <span class="sidebar-brand-name">Bodega Virtual</span>
        <span class="sidebar-brand-sub">Sistema de Gestión</span>
      </div>
    </a>

    <!-- Navegación -->
    <div class="sidebar-nav">

      <!-- Dashboard -->
      <div class="nav-group">
        <a href="index.php" class="nav-link <?php echo navActive('dashboard'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
          Dashboard
        </a>
      </div>

      <div class="nav-divider"></div>

      <!-- Inventario -->
      <div class="nav-group">
        <span class="nav-group-label">Inventario</span>

        <a href="bodegas.php" class="nav-link <?php echo navActive('bodegas'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          Bodegas
        </a>

        <a href="stock.php" class="nav-link <?php echo navActive('stock'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
          Stock
        </a>

        <a href="movimientos.php" class="nav-link <?php echo navActive('movimientos'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/>
            <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/>
          </svg>
          Movimientos
        </a>
      </div>

      <div class="nav-divider"></div>

      <!-- Compras -->
      <div class="nav-group">
        <span class="nav-group-label">Compras</span>

        <a href="ordenes_compra.php" class="nav-link <?php echo navActive('ordenes_compra'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
          </svg>
          Órdenes de Compra
        </a>

        <a href="facturas.php" class="nav-link <?php echo navActive('facturas'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
            <line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
          </svg>
          Facturas
        </a>
      </div>

      <div class="nav-divider"></div>

      <!-- Operaciones -->
      <div class="nav-group">
        <span class="nav-group-label">Operaciones</span>

        <a href="salidas.php" class="nav-link <?php echo navActive('salidas'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Salidas de Bodega
        </a>

        <a href="traslados.php" class="nav-link <?php echo navActive('traslados'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
          Traslados
        </a>

        <a href="ajustes.php" class="nav-link <?php echo navActive('ajustes'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41M12 2v2M12 20v2M2 12h2M20 12h2"/>
          </svg>
          Ajustes de Bodega
        </a>
      </div>

      <div class="nav-divider"></div>

      <!-- Catálogos -->
      <div class="nav-group">
        <span class="nav-group-label">Catálogos</span>

        <a href="productos.php" class="nav-link <?php echo navActive('productos'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
          </svg>
          Productos
        </a>

        <a href="proveedores.php" class="nav-link <?php echo navActive('proveedores'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
          </svg>
          Proveedores
        </a>

        <a href="tipos_producto.php" class="nav-link <?php echo navActive('tipos_producto'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
            <line x1="7" y1="7" x2="7.01" y2="7"/>
          </svg>
          Tipos de Producto
        </a>

        <a href="unidades_medida.php" class="nav-link <?php echo navActive('unidades'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
          </svg>
          Unidades de Medida
        </a>
      </div>

      <!-- Admin -->
      <?php if (puedeVer(array('admin'))): ?>
      <div class="nav-divider"></div>
      <div class="nav-group">
        <span class="nav-group-label">Administración</span>

        <a href="usuarios.php" class="nav-link <?php echo navActive('usuarios'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Usuarios
        </a>

        <a href="reportes.php" class="nav-link <?php echo navActive('reportes'); ?>">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
          </svg>
          Reportes
        </a>
      </div>
      <?php endif; ?>

    </div><!-- /sidebar-nav -->

    <!-- Pie del sidebar: usuario -->
    <div class="sidebar-footer">
      <div class="sidebar-footer-user">
        <div class="sidebar-avatar"><?php echo $usuarioInicial; ?></div>
        <div class="sidebar-user-info">
          <span class="sidebar-user-name"><?php echo htmlspecialchars($usuarioNombre); ?></span>
          <span class="sidebar-user-role"><?php echo htmlspecialchars($usuarioRol); ?></span>
        </div>
      </div>
    </div>

  </nav><!-- /sidebar -->


  <!-- ═══════════════════════════════════════════════
       TOPBAR
  ════════════════════════════════════════════════ -->
  <header id="topbar">

    <!-- Toggle mobile -->
    <button class="topbar-toggle" id="sidebarToggle" onclick="toggleSidebar()" title="Menú">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6"  x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <!-- Título de la página -->
    <div class="topbar-title"><?php echo htmlspecialchars($tituloPagina); ?></div>

    <div class="topbar-spacer"></div>

    <!-- Acciones -->
    <div class="topbar-actions">

      <!-- Hora/Fecha -->
      <span id="topbar-clock" class="topbar-clock"></span>

      <!-- Notificaciones (placeholder) -->
      <button class="topbar-btn" title="Notificaciones">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
      </button>

      <!-- Usuario dropdown -->
      <div class="topbar-dropdown">
        <div class="topbar-user">
          <div class="topbar-user-avatar"><?php echo $usuarioInicial; ?></div>
          <div class="topbar-user-info">
            <span class="topbar-user-name"><?php echo htmlspecialchars($usuarioNombre); ?></span>
            <span class="topbar-user-role"><?php echo htmlspecialchars($usuarioRol); ?></span>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-text-muted); margin-left:2px;">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </div>
        <div class="topbar-dropdown-menu">
          <div class="dropdown-item-header">
            <span>Sesión iniciada como</span>
            <strong><?php echo htmlspecialchars($usuarioNombre); ?></strong>
          </div>
          <div class="dropdown-divider"></div>
          <a href="perfil.php" class="dropdown-item-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            Mi Perfil
          </a>
          <a href="cambiar_clave.php" class="dropdown-item-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
            Cambiar Clave
          </a>
          <div class="dropdown-divider"></div>
          <a href="logout.php" class="dropdown-item-link text-danger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Cerrar Sesión
          </a>
        </div>
      </div>

    </div><!-- /topbar-actions -->

  </header><!-- /topbar -->


  <!-- Scripts de layout (no dependen de jQuery) -->
  <script>
  /* Sidebar toggle mobile */
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
  }
  function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
  }

  /* Reloj topbar */
  function updateClock() {
    var d = new Date();
    var days   = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    var months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    var h   = d.getHours().toString().padStart(2, '0');
    var m   = d.getMinutes().toString().padStart(2, '0');
    var txt = days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()] + ' — ' + h + ':' + m;
    var el  = document.getElementById('topbar-clock');
    if (el) el.textContent = txt;
  }
  updateClock();
  setInterval(updateClock, 30000);
  </script>


  <!-- ═══════════════════════════════════════════════
       CONTENIDO PRINCIPAL
  ════════════════════════════════════════════════ -->
  <main id="page-content">

<?php /* ← El contenido de cada página va aquí */ ?>
<?php /* Cerrar con footer.php que cierra: </main> </div> </body> </html> */ ?>