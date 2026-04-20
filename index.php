<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

$user = current_user();
$rol  = isset($user['rol']) ? $user['rol'] : '';

if (!function_exists('tipo_badge_dash')) {
    function tipo_badge_dash($tipo) {
        $t = (string)$tipo;
        $map = array(
            'entrada_compra'    => array('bg-success',   'bi-box-arrow-in-down', 'Entrada Compra'),
            'salida_consumo'    => array('bg-danger',    'bi-box-arrow-up',      'Salida Consumo'),
            'ajuste_entrada'    => array('bg-info',      'bi-plus-circle',       'Ajuste Entrada'),
            'ajuste_salida'     => array('bg-warning',   'bi-dash-circle',       'Ajuste Salida'),
            'traslado_entrada'  => array('bg-primary',   'bi-arrow-down-left',   'Traslado Entrada'),
            'traslado_salida'   => array('bg-secondary', 'bi-arrow-up-right',    'Traslado Salida'),
        );
        return isset($map[$t]) ? $map[$t] : array('bg-light text-dark', 'bi-dash', '—');
    }
}

// Bodega asignada (rol bodega / solicitante)
$miBodegaId     = null;
$miBodegaNombre = null;
if (in_array($rol, array('bodega', 'solicitante'), true)) {
    $stmt = $pdo->prepare("SELECT u.id_bodega, b.nombre FROM usuarios u LEFT JOIN bodegas b ON b.id = u.id_bodega WHERE u.id = :id LIMIT 1");
    $stmt->execute(array(':id' => $user['id']));
    $r = $stmt->fetch();
    if ($r) {
        $miBodegaId     = $r['id_bodega'] ? (int)$r['id_bodega'] : null;
        $miBodegaNombre = $r['nombre'];
    }
}

$data = array();

// ==================================================================
// CONSULTAS: ADMIN
// ==================================================================
if (in_array($rol, array('admin', 'auditor', 'consulta'), true)) {

    $data['totalProductos']   = (int)$pdo->query("SELECT COUNT(id) FROM productos WHERE estado = 1")->fetchColumn();
    $data['totalBodegas']     = (int)$pdo->query("SELECT COUNT(id) FROM bodegas WHERE estado = 1")->fetchColumn();
    $data['totalProveedores'] = (int)$pdo->query("SELECT COUNT(id) FROM proveedores WHERE estado = 1")->fetchColumn();
    $data['totalUsuarios']    = (int)$pdo->query("SELECT COUNT(id) FROM usuarios WHERE estado = 1")->fetchColumn();

    $data['movHoy']  = (int)$pdo->query("SELECT COUNT(*) FROM movimientos_bodega WHERE DATE(fecha_movimiento) = CURDATE()")->fetchColumn();
    $data['movMes']  = (int)$pdo->query("SELECT COUNT(*) FROM movimientos_bodega WHERE YEAR(fecha_movimiento)=YEAR(CURDATE()) AND MONTH(fecha_movimiento)=MONTH(CURDATE())")->fetchColumn();
    $data['solPend'] = (int)$pdo->query("SELECT COUNT(id) FROM solicitudes WHERE estado = 'pendiente'")->fetchColumn();
    $data['valorStock'] = (float)$pdo->query("SELECT COALESCE(SUM(stock_actual * costo_promedio),0) FROM stock_bodega")->fetchColumn();

    // Stock bajo mínimo (top 6)
    $stmt = $pdo->query("
        SELECT p.codigo, p.nombre, p.stock_minimo,
               COALESCE(SUM(s.stock_actual),0) AS stock_total,
               um.nombre AS unidad
        FROM productos p
        LEFT JOIN stock_bodega s ON s.id_producto = p.id
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        WHERE p.estado = 1 AND p.stock_minimo > 0 AND p.controla_stock = 1
        GROUP BY p.id, p.codigo, p.nombre, p.stock_minimo, um.nombre
        HAVING COALESCE(SUM(s.stock_actual),0) < p.stock_minimo
        ORDER BY (p.stock_minimo - COALESCE(SUM(s.stock_actual),0)) DESC
        LIMIT 6
    ");
    $data['stockBajo'] = $stmt->fetchAll();

    // Últimos movimientos (top 7)
    $stmt = $pdo->query("
        SELECT m.fecha_movimiento, m.tipo_movimiento, m.cantidad,
               p.nombre AS producto, b.nombre AS bodega
        FROM movimientos_bodega m
        INNER JOIN productos p ON p.id = m.id_producto
        INNER JOIN bodegas b ON b.id = m.id_bodega
        ORDER BY m.id DESC LIMIT 7
    ");
    $data['ultimosMov'] = $stmt->fetchAll();

    // Gráfico 7 días
    $stmt = $pdo->query("
        SELECT DATE(fecha_movimiento) AS fecha,
               SUM(CASE WHEN tipo_movimiento IN ('entrada_compra','ajuste_entrada','traslado_entrada') THEN 1 ELSE 0 END) AS entradas,
               SUM(CASE WHEN tipo_movimiento IN ('salida_consumo','ajuste_salida','traslado_salida') THEN 1 ELSE 0 END) AS salidas
        FROM movimientos_bodega
        WHERE fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(fecha_movimiento)
        ORDER BY fecha ASC
    ");
    $data['chartMov'] = $stmt->fetchAll();

    // Solicitudes recientes (top 5)
    $stmt = $pdo->query("
        SELECT s.numero_solicitud, s.estado, s.created_at,
               bo.nombre AS origen, bd.nombre AS destino, u.nombre AS usuario
        FROM solicitudes s
        LEFT JOIN bodegas bo ON bo.id = s.id_bodega_origen
        LEFT JOIN bodegas bd ON bd.id = s.id_bodega_destino
        LEFT JOIN usuarios u ON u.id = s.id_usuario
        ORDER BY s.id DESC LIMIT 5
    ");
    $data['ultimasSolicitudes'] = $stmt->fetchAll();
}

// ==================================================================
// CONSULTAS: BODEGA
// ==================================================================
if ($rol === 'bodega' && $miBodegaId) {

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_producto) FROM stock_bodega WHERE id_bodega = :b AND stock_actual > 0");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['productosBodega'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(stock_actual * costo_promedio),0) FROM stock_bodega WHERE id_bodega = :b");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['valorStockBodega'] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM movimientos_bodega WHERE id_bodega = :b AND fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['movMesBodega'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT p.codigo, p.nombre, p.stock_minimo, s.stock_actual
        FROM stock_bodega s INNER JOIN productos p ON p.id = s.id_producto
        WHERE s.id_bodega = :b AND p.estado = 1 AND p.stock_minimo > 0 AND s.stock_actual < p.stock_minimo
        ORDER BY (p.stock_minimo - s.stock_actual) DESC LIMIT 6
    ");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['stockBajoBodega'] = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT m.fecha_movimiento, m.tipo_movimiento, m.cantidad,
               p.codigo, p.nombre AS producto
        FROM movimientos_bodega m INNER JOIN productos p ON p.id = m.id_producto
        WHERE m.id_bodega = :b ORDER BY m.id DESC LIMIT 7
    ");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['ultimosMovBodega'] = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM solicitudes WHERE id_bodega_origen = :b AND estado = 'pendiente'
    ");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['solPendBodega'] = (int)$stmt->fetchColumn();
}

// ==================================================================
// CONSULTAS: SOLICITANTE
// ==================================================================
if ($rol === 'solicitante') {

    $stmt = $pdo->prepare("SELECT COUNT(id) FROM solicitudes WHERE id_usuario = :u");
    $stmt->execute(array(':u' => $user['id']));
    $data['misTotal'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(id) FROM solicitudes WHERE id_usuario = :u AND estado = 'pendiente'");
    $stmt->execute(array(':u' => $user['id']));
    $data['misPend'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(id) FROM solicitudes WHERE id_usuario = :u AND estado = 'procesada'");
    $stmt->execute(array(':u' => $user['id']));
    $data['misProc'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(id) FROM solicitudes WHERE id_usuario = :u AND estado = 'rechazada'");
    $stmt->execute(array(':u' => $user['id']));
    $data['misRech'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.numero_solicitud, s.estado, s.created_at,
               bo.nombre AS origen, bd.nombre AS destino
        FROM solicitudes s
        LEFT JOIN bodegas bo ON bo.id = s.id_bodega_origen
        LEFT JOIN bodegas bd ON bd.id = s.id_bodega_destino
        WHERE s.id_usuario = :u ORDER BY s.id DESC LIMIT 6
    ");
    $stmt->execute(array(':u' => $user['id']));
    $data['misSolicitudes'] = $stmt->fetchAll();
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/inc/header.php';

$rolLabel = array(
    'admin'       => 'Administrador',
    'bodega'      => 'Encargado de Bodega',
    'solicitante' => 'Solicitante',
    'consulta'    => 'Solo Consulta',
    'auditor'     => 'Auditor'
);
$rolTxt = isset($rolLabel[$rol]) ? $rolLabel[$rol] : ucfirst($rol);
?>

<style>
/* ── Compacidad global del dashboard ── */
.dash-kpi .card-body       { padding: .85rem 1rem; }
.dash-kpi .kpi-label       { font-size: .68rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #8b949e; margin-bottom: .2rem; }
.dash-kpi .kpi-value       { font-size: 1.55rem; font-weight: 700; line-height: 1; color: #1a1f36; }
.dash-kpi .kpi-icon        { width: 38px; height: 38px; flex-shrink: 0; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; }

.dash-card                 { border: 0; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,.07); }
.dash-card .card-header    { background: #fff; border-bottom: 1px solid #f0f2f5; padding: .7rem 1rem; border-radius: 10px 10px 0 0; }
.dash-card .card-header h6 { font-size: .82rem; font-weight: 700; margin: 0; }
.dash-table                { font-size: .82rem; }
.dash-table th             { font-size: .67rem; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: #8b949e; padding: .55rem .75rem; border-bottom: 1px solid #f0f2f5; background: #fafbfc; }
.dash-table td             { padding: .5rem .75rem; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }
.dash-table tr:last-child td { border-bottom: 0; }

.badge-tipo                { font-size: .63rem; padding: .25em .55em; }
.section-label             { font-size: .68rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #8b949e; margin-bottom: .6rem; }

@media (max-width: 575.98px) {
    .dash-kpi .kpi-icon { display: none; }
    .chart-wrapper      { height: 180px !important; }
}
</style>

<!-- ── Encabezado ── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h5 mb-0 fw-bold text-dark">
            Panel principal
        </h1>
        <p class="text-muted mb-0 small">
            Hola, <strong><?php echo h($user['nombre']); ?></strong> ·
            <span class="text-secondary"><?php echo h($rolTxt); ?></span>
            <?php if ($miBodegaNombre): ?> · <i class="bi bi-geo-alt"></i> <?php echo h($miBodegaNombre); ?><?php endif; ?>
        </p>
    </div>
    <span class="badge bg-light text-secondary border d-none d-md-inline-flex align-items-center gap-1 py-2 px-3">
        <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y'); ?>
    </span>
</div>


<?php /* ================================================================
        ADMIN / AUDITOR / CONSULTA
        ================================================================ */ ?>
<?php if (in_array($rol, array('admin', 'auditor', 'consulta'), true)): ?>

<!-- KPIs maestros (fila 1) -->
<div class="row g-2 mb-2 dash-kpi">
    <?php
    $kpis1 = array(
        array('Productos',   $data['totalProductos'],   'bi-boxes',      'bg-primary bg-opacity-10 text-primary',  '/Bodega/modulos/productos/productos_lista.php'),
        array('Bodegas',     $data['totalBodegas'],     'bi-buildings',  'bg-info bg-opacity-10 text-info',        '/Bodega/modulos/bodegas/bodegas_lista.php'),
        array('Proveedores', $data['totalProveedores'], 'bi-truck',      'bg-success bg-opacity-10 text-success',  '/Bodega/modulos/proveedores/proveedores_lista.php'),
        array('Usuarios',    $data['totalUsuarios'],    'bi-people',     'bg-warning bg-opacity-10 text-warning',  $rol === 'admin' ? '/Bodega/modulos/usuarios/usuarios_lista.php' : null),
    );
    foreach ($kpis1 as $k): ?>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100 <?php echo $k[4] ? 'position-relative' : ''; ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon <?php echo $k[3]; ?>"><i class="bi <?php echo $k[2]; ?>"></i></div>
                <div>
                    <div class="kpi-label"><?php echo $k[0]; ?></div>
                    <div class="kpi-value"><?php echo number_format($k[1], 0, ',', '.'); ?></div>
                </div>
            </div>
            <?php if ($k[4] && $rol === 'admin'): ?>
                <a href="<?php echo $k[4]; ?>" class="stretched-link"></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- KPIs operacionales (fila 2) -->
<div class="row g-2 mb-3 dash-kpi">
    <?php
    $kpis2 = array(
        array('Mov. hoy',         $data['movHoy'],    'bi-activity',         'bg-primary bg-opacity-10 text-primary'),
        array('Mov. del mes',     $data['movMes'],    'bi-calendar-month',   'bg-info bg-opacity-10 text-info'),
        array('Sol. pendientes',  $data['solPend'],   'bi-clipboard-check',  'bg-warning bg-opacity-10 text-warning'),
        array('Valor stock',      null,               'bi-cash-stack',       'bg-success bg-opacity-10 text-success'),
    );
    foreach ($kpis2 as $i => $k): ?>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon <?php echo $k[3]; ?>"><i class="bi <?php echo $k[2]; ?>"></i></div>
                <div>
                    <div class="kpi-label"><?php echo $k[0]; ?></div>
                    <?php if ($i < 3): ?>
                        <div class="kpi-value"><?php echo number_format($k[1], 0, ',', '.'); ?></div>
                    <?php else: ?>
                        <div class="kpi-value text-success" style="font-size:1.15rem;">
                            $<?php echo number_format($data['valorStock'], 0, ',', '.'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Gráfico + Stock bajo mínimo -->
<div class="row g-3 mb-3">

    <!-- Gráfico 7 días -->
    <div class="col-12 col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-graph-up text-primary me-2"></i>Movimientos — últimos 7 días</h6>
                <span class="text-muted small d-none d-sm-inline">Entradas vs Salidas</span>
            </div>
            <div class="card-body p-3 d-flex flex-column">
                <div class="chart-wrapper flex-grow-1" style="position:relative; min-height: 200px;">
                    <canvas id="chartMov7d"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock bajo mínimo -->
    <div class="col-12 col-lg-5">
        <div class="card dash-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stock bajo mínimo</h6>
                <span class="badge bg-danger bg-opacity-10 text-danger border-0"><?php echo count($data['stockBajo']); ?></span>
            </div>
            <?php if (!$data['stockBajo']): ?>
                <div class="card-body text-center text-muted py-4 small">
                    <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>Todo en orden
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table table table-sm mb-0">
                    <thead><tr><th>Producto</th><th class="text-end">Actual</th><th class="text-end">Mín.</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['stockBajo'] as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-medium text-dark text-truncate" style="max-width:160px"><?php echo h($p['nombre']); ?></div>
                            <div class="text-muted" style="font-size:.7rem"><?php echo h($p['codigo']); ?></div>
                        </td>
                        <td class="text-end fw-bold text-danger"><?php echo number_format((float)$p['stock_total'],2,',','.'); ?></td>
                        <td class="text-end text-muted"><?php echo number_format((float)$p['stock_minimo'],2,',','.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Últimos movimientos + Solicitudes recientes -->
<div class="row g-3 mb-4">

    <!-- Últimos movimientos -->
    <div class="col-12 col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-arrow-left-right text-primary me-2"></i>Últimos movimientos</h6>
                <a href="/Bodega/modulos/movimientos/movimientos_lista.php" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem">Ver todos</a>
            </div>
            <?php if (!$data['ultimosMov']): ?>
                <div class="card-body text-center text-muted py-4 small">Sin movimientos</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table table table-sm mb-0">
                    <thead><tr>
                        <th class="d-none d-sm-table-cell">Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th class="text-end">Cant.</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($data['ultimosMov'] as $m):
                        $badge = tipo_badge_dash($m['tipo_movimiento']);
                    ?>
                    <tr>
                        <td class="text-muted d-none d-sm-table-cell" style="font-size:.75rem;white-space:nowrap"><?php echo date('d/m H:i', strtotime($m['fecha_movimiento'])); ?></td>
                        <td>
                            <div class="fw-medium text-dark text-truncate" style="max-width:150px"><?php echo h($m['producto']); ?></div>
                            <div class="text-muted" style="font-size:.7rem"><i class="bi bi-geo-alt"></i> <?php echo h($m['bodega']); ?></div>
                        </td>
                        <td>
                            <span class="badge <?php echo $badge[0]; ?> badge-tipo">
                                <i class="bi <?php echo $badge[1]; ?> me-1"></i><span class="d-none d-md-inline"><?php echo h($badge[2]); ?></span>
                            </span>
                        </td>
                        <td class="text-end fw-bold"><?php echo number_format((float)$m['cantidad'],2,',','.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Solicitudes recientes -->
    <div class="col-12 col-lg-5">
        <div class="card dash-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-clipboard-check text-warning me-2"></i>Solicitudes recientes</h6>
                <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem">Ver todas</a>
            </div>
            <?php if (!$data['ultimasSolicitudes']): ?>
                <div class="card-body text-center text-muted py-4 small">Sin solicitudes</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table table table-sm mb-0">
                    <thead><tr><th>N°</th><th class="d-none d-sm-table-cell">Usuario</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['ultimasSolicitudes'] as $s):
                        $est = $s['estado'];
                        $ec  = $est === 'pendiente' ? 'bg-warning text-dark' : ($est === 'procesada' ? 'bg-success' : ($est === 'rechazada' ? 'bg-danger' : 'bg-secondary'));
                    ?>
                    <tr>
                        <td>
                            <div class="fw-medium small"><?php echo h($s['numero_solicitud']); ?></div>
                            <div class="text-muted d-sm-none" style="font-size:.7rem"><?php echo h($s['usuario']); ?></div>
                        </td>
                        <td class="small text-muted d-none d-sm-table-cell"><?php echo h($s['usuario']); ?></td>
                        <td><span class="badge <?php echo $ec; ?> badge-tipo text-uppercase"><?php echo $est; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php endif; /* fin admin */ ?>


<?php /* ================================================================
        BODEGA (ENCARGADO)
        ================================================================ */ ?>
<?php if ($rol === 'bodega'): ?>

<?php if (!$miBodegaId): ?>
    <div class="alert alert-warning shadow-sm">
        <i class="bi bi-exclamation-triangle me-2"></i>No tienes una bodega asignada. Contacta al administrador.
    </div>
<?php else: ?>

<!-- KPIs bodega -->
<div class="row g-2 mb-3 dash-kpi">
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-boxes"></i></div>
                <div><div class="kpi-label">Productos</div><div class="kpi-value"><?php echo number_format($data['productosBodega'],0,',','.'); ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
                <div><div class="kpi-label">Valor stock</div><div class="kpi-value text-success" style="font-size:1.1rem">$<?php echo number_format($data['valorStockBodega'],0,',','.'); ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                <div><div class="kpi-label">Bajo mínimo</div><div class="kpi-value text-danger"><?php echo count($data['stockBajoBodega']); ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-month"></i></div>
                <div><div class="kpi-label">Mov. del mes</div><div class="kpi-value"><?php echo number_format($data['movMesBodega'],0,',','.'); ?></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones rápidas -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="/Bodega/modulos/movimientos/movimientos_crear.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo traslado</a>
    <a href="/Bodega/modulos/movimientos/movimientos_lista.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i>Movimientos</a>
    <a href="/Bodega/modulos/stock_lista.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-inboxes me-1"></i>Ver stock</a>
    <?php if ($data['solPendBodega'] > 0): ?>
        <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="btn btn-sm btn-warning">
            <i class="bi bi-clipboard-check me-1"></i>Solicitudes pendientes
            <span class="badge bg-dark ms-1"><?php echo $data['solPendBodega']; ?></span>
        </a>
    <?php endif; ?>
</div>

<!-- Stock bajo + Últimos movimientos -->
<div class="row g-3 mb-4">

    <div class="col-12 col-lg-5">
        <div class="card dash-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stock bajo mínimo</h6>
                <span class="badge bg-danger bg-opacity-10 text-danger border-0"><?php echo count($data['stockBajoBodega']); ?></span>
            </div>
            <?php if (!$data['stockBajoBodega']): ?>
                <div class="card-body text-center text-muted py-4 small">
                    <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>Todo en orden
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table table table-sm mb-0">
                    <thead><tr><th>Producto</th><th class="text-end">Actual</th><th class="text-end">Mín.</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['stockBajoBodega'] as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-medium text-dark text-truncate" style="max-width:150px"><?php echo h($p['nombre']); ?></div>
                            <div class="text-muted" style="font-size:.7rem"><?php echo h($p['codigo']); ?></div>
                        </td>
                        <td class="text-end fw-bold text-danger"><?php echo number_format((float)$p['stock_actual'],2,',','.'); ?></td>
                        <td class="text-end text-muted"><?php echo number_format((float)$p['stock_minimo'],2,',','.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-arrow-left-right text-primary me-2"></i>Últimos movimientos</h6>
                <a href="/Bodega/modulos/movimientos/movimientos_lista.php?id_bodega=<?php echo $miBodegaId; ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem">Ver todos</a>
            </div>
            <?php if (!$data['ultimosMovBodega']): ?>
                <div class="card-body text-center text-muted py-4 small">Sin movimientos</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table table table-sm mb-0">
                    <thead><tr>
                        <th class="d-none d-sm-table-cell">Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th class="text-end">Cant.</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($data['ultimosMovBodega'] as $m):
                        $badge = tipo_badge_dash($m['tipo_movimiento']);
                    ?>
                    <tr>
                        <td class="text-muted d-none d-sm-table-cell" style="font-size:.75rem;white-space:nowrap"><?php echo date('d/m H:i', strtotime($m['fecha_movimiento'])); ?></td>
                        <td>
                            <div class="fw-medium text-dark text-truncate" style="max-width:150px"><?php echo h($m['producto']); ?></div>
                            <div class="text-muted" style="font-size:.7rem"><?php echo h($m['codigo']); ?></div>
                        </td>
                        <td>
                            <span class="badge <?php echo $badge[0]; ?> badge-tipo">
                                <i class="bi <?php echo $badge[1]; ?> me-1"></i><span class="d-none d-md-inline"><?php echo h($badge[2]); ?></span>
                            </span>
                        </td>
                        <td class="text-end fw-bold"><?php echo number_format((float)$m['cantidad'],2,',','.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>
<?php endif; /* fin bodega */ ?>


<?php /* ================================================================
        SOLICITANTE
        ================================================================ */ ?>
<?php if ($rol === 'solicitante'): ?>

<!-- KPIs solicitante -->
<div class="row g-2 mb-3 dash-kpi">
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-clipboard2"></i></div>
                <div><div class="kpi-label">Total</div><div class="kpi-value"><?php echo (int)$data['misTotal']; ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="kpi-label">Pendientes</div><div class="kpi-value text-warning"><?php echo (int)$data['misPend']; ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-circle"></i></div>
                <div><div class="kpi-label">Procesadas</div><div class="kpi-value text-success"><?php echo (int)$data['misProc']; ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card dash-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
                <div><div class="kpi-label">Rechazadas</div><div class="kpi-value text-danger"><?php echo (int)$data['misRech']; ?></div></div>
            </div>
        </div>
    </div>
</div>

<!-- CTA + listado -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4 col-lg-3">
    <div class="card dash-card h-100">
        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center py-4">
            <div class="mb-3">
                <i class="bi bi-box-seam text-primary" style="font-size: 2.2rem;"></i>
                <p class="fw-semibold text-dark mb-0 mt-2">¿Necesitas productos?</p>
                <span class="text-muted" style="font-size: 0.75rem;">Crea una solicitud de insumos</span>
            </div>
            
            <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="btn btn-primary px-4 py-1 mt-auto" style="font-size: 0.8rem; border-radius: 6px; box-shadow: 0 2px 4px rgba(13,110,253,0.15);">
                <i class="bi bi-plus-lg me-1"></i> Nueva solicitud
            </a>
        </div>
    </div>
    </div>
    <div class="col-12 col-md-8 col-lg-9">
        <div class="card dash-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-clock-history text-primary me-2"></i>Mis últimas solicitudes</h6>
                <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem">Ver todas</a>
            </div>
            <?php if (!$data['misSolicitudes']): ?>
                <div class="card-body text-center text-muted py-4 small">
                    <i class="bi bi-inbox fs-4 d-block mb-1"></i>Aún no has creado solicitudes
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table table table-sm mb-0">
                    <thead><tr>
                        <th>N°</th>
                        <th class="d-none d-sm-table-cell">Origen → Destino</th>
                        <th>Estado</th>
                        <th class="d-none d-md-table-cell">Fecha</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($data['misSolicitudes'] as $s):
                        $est = $s['estado'];
                        $ec  = $est === 'pendiente' ? 'bg-warning text-dark' : ($est === 'procesada' ? 'bg-success' : ($est === 'rechazada' ? 'bg-danger' : 'bg-secondary'));
                    ?>
                    <tr>
                        <td class="fw-medium small"><?php echo h($s['numero_solicitud']); ?></td>
                        <td class="small text-muted d-none d-sm-table-cell">
                            <?php echo h($s['origen']); ?> <i class="bi bi-arrow-right mx-1"></i> <?php echo h($s['destino']); ?>
                        </td>
                        <td><span class="badge <?php echo $ec; ?> badge-tipo text-uppercase"><?php echo $est; ?></span></td>
                        <td class="small text-muted d-none d-md-table-cell"><?php echo date('d/m/Y', strtotime($s['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; /* fin solicitante */ ?>


<?php if (!in_array($rol, array('admin','bodega','solicitante','consulta','auditor'), true)): ?>
    <div class="alert alert-info shadow-sm">
        <i class="bi bi-info-circle me-2"></i>Tu rol no tiene un panel asignado. Contacta al administrador.
    </div>
<?php endif; ?>


<?php if (in_array($rol, array('admin','auditor','consulta'), true)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    var raw = <?php echo json_encode($data['chartMov']); ?>;
    var mapE = {}, mapS = {};
    for (var i = 0; i < raw.length; i++) {
        mapE[raw[i].fecha] = parseInt(raw[i].entradas, 10) || 0;
        mapS[raw[i].fecha] = parseInt(raw[i].salidas,  10) || 0;
    }
    var labels = [], entradas = [], salidas = [];
    var hoy = new Date();
    for (var d = 6; d >= 0; d--) {
        var dt = new Date(hoy);
        dt.setDate(hoy.getDate() - d);
        var y = dt.getFullYear(), m = ('0'+(dt.getMonth()+1)).slice(-2), day = ('0'+dt.getDate()).slice(-2);
        var key = y+'-'+m+'-'+day;
        labels.push(day+'/'+m);
        entradas.push(mapE[key] || 0);
        salidas.push(mapS[key]  || 0);
    }
    var ctx = document.getElementById('chartMov7d');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Entradas', data: entradas, backgroundColor: 'rgba(25,135,84,.75)', borderRadius: 5, borderSkipped: false },
                { label: 'Salidas',  data: salidas,  backgroundColor: 'rgba(220,53,69,.75)',  borderRadius: 5, borderSkipped: false }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: 'rgba(0,0,0,.04)' } }
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>