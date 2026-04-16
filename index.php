<?php
/**
 * index.php — Dashboard principal del sistema de bodega
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Incluir configuración DB
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

// Nombres de meses en español (compatible PHP 5.6 sin setlocale)
$_MESES = array(
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
);

// ── Consultas de resumen ──────────────────────────────────────
$stats = array(
    'bodegas'        => 0,
    'productos'      => 0,
    'proveedores'    => 0,
    'stock_bajo'     => 0,
    'oc_pendientes'  => 0,
    'facturas_mes'   => 0,
    'salidas_mes'    => 0,
    'traslados_mes'  => 0,
);

if (isset($conn)) {
    // Total bodegas activas
    $r = $conn->query("SELECT COUNT(*) AS c FROM bodegas WHERE estado=1");
    if ($r) $stats['bodegas'] = $r->fetch_assoc()['c'];

    // Total productos activos
    $r = $conn->query("SELECT COUNT(*) AS c FROM productos WHERE estado=1");
    if ($r) $stats['productos'] = $r->fetch_assoc()['c'];

    // Total proveedores activos
    $r = $conn->query("SELECT COUNT(*) AS c FROM proveedores WHERE estado=1");
    if ($r) $stats['proveedores'] = $r->fetch_assoc()['c'];

    // Productos con stock bajo el mínimo
    $r = $conn->query("
        SELECT COUNT(DISTINCT p.id) AS c
        FROM productos p
        JOIN stock_bodega sb ON sb.id_producto = p.id
        WHERE p.controla_stock = 1 AND p.estado = 1
          AND sb.stock_actual < p.stock_minimo AND p.stock_minimo > 0
    ");
    if ($r) $stats['stock_bajo'] = $r->fetch_assoc()['c'];

    // OC pendientes
    $r = $conn->query("SELECT COUNT(*) AS c FROM ordenes_compra WHERE estado IN('pendiente','parcial')");
    if ($r) $stats['oc_pendientes'] = $r->fetch_assoc()['c'];

    // Facturas del mes actual
    $r = $conn->query("SELECT COUNT(*) AS c FROM facturas WHERE MONTH(fecha_factura)=MONTH(NOW()) AND YEAR(fecha_factura)=YEAR(NOW())");
    if ($r) $stats['facturas_mes'] = $r->fetch_assoc()['c'];

    // Salidas del mes
    $r = $conn->query("SELECT COUNT(*) AS c FROM salidas_bodega WHERE MONTH(fecha_salida)=MONTH(NOW()) AND YEAR(fecha_salida)=YEAR(NOW())");
    if ($r) $stats['salidas_mes'] = $r->fetch_assoc()['c'];

    // Traslados del mes
    $r = $conn->query("SELECT COUNT(*) AS c FROM traslados_bodega WHERE MONTH(fecha_traslado)=MONTH(NOW()) AND YEAR(fecha_traslado)=YEAR(NOW())");
    if ($r) $stats['traslados_mes'] = $r->fetch_assoc()['c'];

    // Últimos movimientos
    $ultimosMovimientos = array();
    $r = $conn->query("
        SELECT m.tipo_movimiento, m.cantidad, m.fecha_movimiento,
               p.nombre AS producto, b.nombre AS bodega
        FROM movimientos_bodega m
        JOIN productos p ON p.id = m.id_producto
        JOIN bodegas   b ON b.id = m.id_bodega
        ORDER BY m.fecha_movimiento DESC
        LIMIT 8
    ");
    if ($r) while ($row = $r->fetch_assoc()) $ultimosMovimientos[] = $row;

    // Alertas stock bajo
    $alertasStock = array();
    $r = $conn->query("
        SELECT p.codigo, p.nombre, p.stock_minimo,
               SUM(sb.stock_actual) AS stock_total
        FROM productos p
        JOIN stock_bodega sb ON sb.id_producto = p.id
        WHERE p.controla_stock=1 AND p.estado=1 AND p.stock_minimo > 0
        GROUP BY p.id, p.codigo, p.nombre, p.stock_minimo
        HAVING stock_total < p.stock_minimo
        ORDER BY (stock_total / p.stock_minimo) ASC
        LIMIT 6
    ");
    if ($r) while ($row = $r->fetch_assoc()) $alertasStock[] = $row;
}

// Variables para el header
$pageTitle   = 'Dashboard';
$pageSection = 'dashboard';

require_once __DIR__ . '/inc/header.php';

// Helpers
function tipoMovBadge($tipo) {
    $map = array(
        'entrada_compra'   => array('success', 'Entrada Compra'),
        'salida_consumo'   => array('warning', 'Salida Consumo'),
        'ajuste_entrada'   => array('primary', 'Ajuste Entrada'),
        'ajuste_salida'    => array('danger',  'Ajuste Salida'),
        'traslado_entrada' => array('info',    'Traslado Entrada'),
        'traslado_salida'  => array('secondary','Traslado Salida'),
    );
    $d = isset($map[$tipo]) ? $map[$tipo] : array('secondary', htmlspecialchars($tipo));
    return '<span class="badge badge-' . $d[0] . '">' . $d[1] . '</span>';
}

// Fecha larga en español sin strftime
$fechaHoy  = date('d') . ' de ' . $_MESES[(int)date('n')] . ' de ' . date('Y');
$mesActual = $_MESES[(int)date('n')] . ' ' . date('Y');
?>

<!-- ── PAGE HEADER ── -->
<div class="page-header">
  <div class="page-header-title">
    <h1>Dashboard</h1>
    <p>Resumen general del sistema — <?php echo $fechaHoy; ?></p>
  </div>
  <?php if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin'): ?>
  <div class="page-header-actions">
    <a href="reportes.php" class="btn btn-secondary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
      </svg>
      Reportes
    </a>
    <a href="usuarios.php" class="btn btn-primary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
      </svg>
      Usuarios
    </a>
  </div>
  <?php endif; ?>
</div>


<!-- ── ALERTA STOCK BAJO ── -->
<?php if ($stats['stock_bajo'] > 0): ?>
<div class="alert alert-warning mb-24">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
  </svg>
  <div>
    <strong><?php echo (int)$stats['stock_bajo']; ?> producto(s) con stock crítico</strong> —
    El inventario está por debajo del mínimo definido.
    <a href="stock.php?filtro=bajo" style="color:inherit; font-weight:600; text-decoration:underline;">Ver productos →</a>
  </div>
</div>
<?php endif; ?>


<!-- ── KPI CARDS ── -->
<div class="grid grid-4 mb-24">

  <div class="kpi-card">
    <div class="kpi-card-header">
      <div class="kpi-icon primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </div>
    </div>
    <div class="kpi-value"><?php echo (int)$stats['bodegas']; ?></div>
    <div class="kpi-label">Bodegas activas</div>
    <div class="kpi-footer"><a href="bodegas.php">Ver bodegas →</a></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-card-header">
      <div class="kpi-icon success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
        </svg>
      </div>
      <?php if ($stats['stock_bajo'] > 0): ?>
      <span class="kpi-badge down">
        <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
          <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
        </svg>
        <?php echo (int)$stats['stock_bajo']; ?> críticos
      </span>
      <?php endif; ?>
    </div>
    <div class="kpi-value"><?php echo (int)$stats['productos']; ?></div>
    <div class="kpi-label">Productos registrados</div>
    <div class="kpi-footer"><a href="productos.php">Ver catálogo →</a></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-card-header">
      <div class="kpi-icon warning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
      </div>
      <?php if ($stats['oc_pendientes'] > 0): ?>
      <span class="kpi-badge down"><?php echo (int)$stats['oc_pendientes']; ?> pendientes</span>
      <?php endif; ?>
    </div>
    <div class="kpi-value"><?php echo (int)$stats['oc_pendientes']; ?></div>
    <div class="kpi-label">OC pendientes / parciales</div>
    <div class="kpi-footer"><a href="ordenes_compra.php">Ver órdenes →</a></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-card-header">
      <div class="kpi-icon info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>
    </div>
    <div class="kpi-value"><?php echo (int)$stats['proveedores']; ?></div>
    <div class="kpi-label">Proveedores activos</div>
    <div class="kpi-footer"><a href="proveedores.php">Ver proveedores →</a></div>
  </div>

</div><!-- /grid kpi -->


<!-- ── FILA: actividad del mes + accesos rápidos ── -->
<div class="grid grid-3 mb-24">

  <!-- Stats mes (span 2) -->
  <div style="grid-column: span 2;">
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Actividad del Mes</div>
          <div class="card-subtitle"><?php echo $mesActual; ?></div>
        </div>
      </div>
      <div class="card-body">
        <div class="grid grid-3" style="gap:16px;">

          <div style="text-align:center; padding:16px; background:var(--color-body-bg); border-radius:var(--radius-md); border:1px solid var(--color-border);">
            <div style="font-size:32px; font-weight:700; color:var(--color-primary); line-height:1; margin-bottom:6px;">
              <?php echo (int)$stats['facturas_mes']; ?>
            </div>
            <div style="font-size:12px; color:var(--color-text-secondary); font-weight:500;">Facturas ingresadas</div>
            <div style="margin-top:10px;">
              <a href="facturas.php" class="btn btn-sm btn-outline-primary w-100">Ver →</a>
            </div>
          </div>

          <div style="text-align:center; padding:16px; background:var(--color-body-bg); border-radius:var(--radius-md); border:1px solid var(--color-border);">
            <div style="font-size:32px; font-weight:700; color:var(--color-warning); line-height:1; margin-bottom:6px;">
              <?php echo (int)$stats['salidas_mes']; ?>
            </div>
            <div style="font-size:12px; color:var(--color-text-secondary); font-weight:500;">Salidas de bodega</div>
            <div style="margin-top:10px;">
              <a href="salidas.php" class="btn btn-sm btn-secondary w-100" style="border-color:var(--color-warning); color:var(--color-warning);">Ver →</a>
            </div>
          </div>

          <div style="text-align:center; padding:16px; background:var(--color-body-bg); border-radius:var(--radius-md); border:1px solid var(--color-border);">
            <div style="font-size:32px; font-weight:700; color:var(--color-info); line-height:1; margin-bottom:6px;">
              <?php echo (int)$stats['traslados_mes']; ?>
            </div>
            <div style="font-size:12px; color:var(--color-text-secondary); font-weight:500;">Traslados realizados</div>
            <div style="margin-top:10px;">
              <a href="traslados.php" class="btn btn-sm btn-secondary w-100" style="border-color:var(--color-info); color:var(--color-info);">Ver →</a>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Accesos rápidos -->
  <div>
    <div class="card h-100">
      <div class="card-header">
        <div class="card-title">Accesos Rápidos</div>
      </div>
      <div class="card-body" style="display:flex; flex-direction:column; gap:8px; padding:14px;">

        <a href="ordenes_compra.php?accion=nueva" class="quick-access-card">
          <div class="quick-access-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
          </div>
          <div class="quick-access-text">
            <div class="quick-access-title">Nueva OC</div>
            <div class="quick-access-sub">Orden de compra</div>
          </div>
          <div class="quick-access-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </div>
        </a>

        <a href="salidas.php?accion=nueva" class="quick-access-card">
          <div class="quick-access-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
              <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </div>
          <div class="quick-access-text">
            <div class="quick-access-title">Nueva Salida</div>
            <div class="quick-access-sub">Salida de bodega</div>
          </div>
          <div class="quick-access-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </div>
        </a>

        <a href="traslados.php?accion=nuevo" class="quick-access-card">
          <div class="quick-access-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="3" width="15" height="13"/>
              <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
              <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
          </div>
          <div class="quick-access-text">
            <div class="quick-access-title">Nuevo Traslado</div>
            <div class="quick-access-sub">Entre bodegas</div>
          </div>
          <div class="quick-access-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </div>
        </a>

        <a href="ajustes.php?accion=nuevo" class="quick-access-card">
          <div class="quick-access-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"/>
              <path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2"/>
            </svg>
          </div>
          <div class="quick-access-text">
            <div class="quick-access-title">Ajuste de Stock</div>
            <div class="quick-access-sub">Corrección de inventario</div>
          </div>
          <div class="quick-access-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </div>
        </a>

      </div>
    </div>
  </div>

</div><!-- /grid actividad -->


<!-- ── FILA: últimos movimientos + alertas stock ── -->
<div class="grid grid-2">

  <!-- Últimos movimientos -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Últimos Movimientos</div>
        <div class="card-subtitle">Los 8 movimientos más recientes</div>
      </div>
      <a href="movimientos.php" class="btn btn-sm btn-secondary">Ver todos</a>
    </div>
    <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Tipo</th>
            <th class="text-right">Cantidad</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($ultimosMovimientos)): ?>
            <?php foreach ($ultimosMovimientos as $m): ?>
            <tr>
              <td>
                <div style="font-weight:500; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px;" title="<?php echo htmlspecialchars($m['producto']); ?>">
                  <?php echo htmlspecialchars($m['producto']); ?>
                </div>
                <div style="font-size:11px; color:var(--color-text-muted);"><?php echo htmlspecialchars($m['bodega']); ?></div>
              </td>
              <td><?php echo tipoMovBadge($m['tipo_movimiento']); ?></td>
              <td class="text-right amount"><?php echo number_format($m['cantidad'], 2); ?></td>
              <td style="font-size:11px; color:var(--color-text-muted); white-space:nowrap;">
                <?php echo date('d/m H:i', strtotime($m['fecha_movimiento'])); ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-center text-muted" style="padding:32px;">Sin movimientos registrados</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Alertas stock bajo -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Alertas de Stock</div>
        <div class="card-subtitle">Productos bajo el mínimo</div>
      </div>
      <?php if (!empty($alertasStock)): ?>
      <span class="badge badge-danger"><?php echo count($alertasStock); ?> alertas</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($alertasStock)): ?>
    <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Producto</th>
            <th class="text-right">Stock</th>
            <th class="text-right">Mínimo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($alertasStock as $a): ?>
          <?php $pct = ($a['stock_minimo'] > 0) ? round(($a['stock_total'] / $a['stock_minimo']) * 100) : 0; ?>
          <tr>
            <td>
              <div style="font-weight:500; font-size:12px;"><?php echo htmlspecialchars($a['nombre']); ?></div>
              <div class="code-text" style="font-size:10px;"><?php echo htmlspecialchars($a['codigo']); ?></div>
            </td>
            <td class="text-right">
              <span class="amount" style="color:var(--color-danger); font-weight:600;">
                <?php echo number_format($a['stock_total'], 2); ?>
              </span>
            </td>
            <td class="text-right amount text-muted"><?php echo number_format($a['stock_minimo'], 2); ?></td>
            <td>
              <div style="background:#fee2e2; height:6px; border-radius:3px; min-width:50px; overflow:hidden;">
                <div style="background:var(--color-danger); height:100%; width:<?php echo min($pct, 100); ?>%; border-radius:3px;"></div>
              </div>
              <div style="font-size:10px; color:var(--color-danger); text-align:center; margin-top:2px;"><?php echo $pct; ?>%</div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <h5>Todo en orden</h5>
      <p>No hay productos con stock crítico en este momento.</p>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /grid movimientos+alertas -->

<?php require_once __DIR__ . '/inc/footer.php';