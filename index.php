<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

$user = current_user();
$rol  = isset($user['rol']) ? $user['rol'] : '';

// ------------------------------------------------------------------
// Helper: badge visual para tipo_movimiento (reutilizado del módulo)
// ------------------------------------------------------------------
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

// ------------------------------------------------------------------
// Bodega asignada (rol bodega / solicitante)
// ------------------------------------------------------------------
$miBodegaId     = null;
$miBodegaNombre = null;
if (in_array($rol, array('bodega', 'solicitante'), true)) {
    $stmt = $pdo->prepare("
        SELECT u.id_bodega, b.nombre
        FROM usuarios u
        LEFT JOIN bodegas b ON b.id = u.id_bodega
        WHERE u.id = :id LIMIT 1
    ");
    $stmt->execute(array(':id' => $user['id']));
    $r = $stmt->fetch();
    if ($r) {
        $miBodegaId     = $r['id_bodega'] ? (int)$r['id_bodega'] : null;
        $miBodegaNombre = $r['nombre'];
    }
}

$data = array();

// ==================================================================
// CONSULTAS: ADMIN / AUDITOR / CONSULTA
// ==================================================================
if (in_array($rol, array('admin', 'auditor', 'consulta'), true)) {

    // --- KPIs maestros ---
    $data['totalProductos']   = (int)$pdo->query("SELECT COUNT(id) FROM productos   WHERE estado = 1")->fetchColumn();
    $data['totalBodegas']     = (int)$pdo->query("SELECT COUNT(id) FROM bodegas     WHERE estado = 1")->fetchColumn();
    $data['totalProveedores'] = (int)$pdo->query("SELECT COUNT(id) FROM proveedores WHERE estado = 1")->fetchColumn();
    $data['totalUsuarios']    = (int)$pdo->query("SELECT COUNT(id) FROM usuarios    WHERE estado = 1")->fetchColumn();

    // OCs pendientes (puede no existir la tabla con datos, igual es consulta segura)
    try {
        $data['ocPendientes'] = (int)$pdo->query(
            "SELECT COUNT(id) FROM ordenes_compra WHERE estado IN ('pendiente','parcial')"
        )->fetchColumn();
    } catch (Exception $e) {
        $data['ocPendientes'] = 0;
    }

    // --- Operaciones ---
    $data['movHoy']  = (int)$pdo->query("
        SELECT COUNT(*) FROM movimientos_bodega WHERE DATE(fecha_movimiento) = CURDATE()
    ")->fetchColumn();

    $data['movMes']  = (int)$pdo->query("
        SELECT COUNT(*) FROM movimientos_bodega
        WHERE YEAR(fecha_movimiento) = YEAR(CURDATE())
          AND MONTH(fecha_movimiento) = MONTH(CURDATE())
    ")->fetchColumn();

    $data['solPend'] = (int)$pdo->query("
        SELECT COUNT(id) FROM solicitudes WHERE estado = 'pendiente'
    ")->fetchColumn();

    $data['valorStock'] = (float)$pdo->query("
        SELECT COALESCE(SUM(stock_actual * costo_promedio),0) FROM stock_bodega
    ")->fetchColumn();

    // --- Stock bajo (global) ---
    $stmt = $pdo->query("
        SELECT p.codigo, p.nombre, p.stock_minimo,
               COALESCE(SUM(s.stock_actual),0) AS stock_total,
               um.nombre AS unidad
        FROM productos p
        LEFT JOIN stock_bodega   s  ON s.id_producto = p.id
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        WHERE p.estado = 1 AND p.stock_minimo > 0 AND p.controla_stock = 1
        GROUP BY p.id, p.codigo, p.nombre, p.stock_minimo, um.nombre
        HAVING COALESCE(SUM(s.stock_actual),0) < p.stock_minimo
        ORDER BY (p.stock_minimo - COALESCE(SUM(s.stock_actual),0)) DESC
        LIMIT 8
    ");
    $data['stockBajo'] = $stmt->fetchAll();

    // --- Últimos movimientos ---
    $stmt = $pdo->query("
        SELECT m.fecha_movimiento, m.tipo_movimiento, m.cantidad,
               p.codigo, p.nombre AS producto,
               b.nombre AS bodega,
               u.nombre AS usuario
        FROM movimientos_bodega m
        INNER JOIN productos p ON p.id = m.id_producto
        INNER JOIN bodegas   b ON b.id = m.id_bodega
        LEFT  JOIN usuarios  u ON u.id = m.id_usuario
        ORDER BY m.id DESC
        LIMIT 10
    ");
    $data['ultimosMov'] = $stmt->fetchAll();

    // --- Serie 7 días (gráfico) ---
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

    // --- Top productos (30 días) ---
    $stmt = $pdo->query("
        SELECT p.codigo, p.nombre, COUNT(m.id) AS movs, COALESCE(SUM(m.cantidad),0) AS total_cant
        FROM movimientos_bodega m
        INNER JOIN productos p ON p.id = m.id_producto
        WHERE m.fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY p.id, p.codigo, p.nombre
        ORDER BY movs DESC
        LIMIT 5
    ");
    $data['topProductos'] = $stmt->fetchAll();

    // --- Top usuarios (30 días) ---
    $stmt = $pdo->query("
        SELECT u.nombre, u.rol, COUNT(m.id) AS movs
        FROM movimientos_bodega m
        INNER JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY u.id, u.nombre, u.rol
        ORDER BY movs DESC
        LIMIT 5
    ");
    $data['topUsuarios'] = $stmt->fetchAll();

    // --- Distribución por bodega ---
    $stmt = $pdo->query("
        SELECT b.nombre, b.codigo, b.responsable,
               COUNT(DISTINCT CASE WHEN s.stock_actual > 0 THEN s.id_producto END) AS productos,
               COALESCE(SUM(s.stock_actual),0) AS unidades,
               COALESCE(SUM(s.stock_actual * s.costo_promedio),0) AS valor
        FROM bodegas b
        LEFT JOIN stock_bodega s ON s.id_bodega = b.id
        WHERE b.estado = 1
        GROUP BY b.id, b.nombre, b.codigo, b.responsable
        ORDER BY valor DESC
    ");
    $data['distribBodegas'] = $stmt->fetchAll();

    // --- Solicitudes recientes ---
    $stmt = $pdo->query("
        SELECT s.numero_solicitud, s.estado, s.created_at,
               bo.nombre AS origen, bd.nombre AS destino,
               u.nombre AS usuario
        FROM solicitudes s
        LEFT JOIN bodegas  bo ON bo.id = s.id_bodega_origen
        LEFT JOIN bodegas  bd ON bd.id = s.id_bodega_destino
        LEFT JOIN usuarios u  ON u.id  = s.id_usuario
        ORDER BY s.id DESC
        LIMIT 6
    ");
    $data['ultimasSolicitudes'] = $stmt->fetchAll();
}

// ==================================================================
// CONSULTAS: BODEGA (encargado)
// ==================================================================
if ($rol === 'bodega' && $miBodegaId) {

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_producto) FROM stock_bodega WHERE id_bodega = :b AND stock_actual > 0");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['productosBodega'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(stock_actual * costo_promedio),0) FROM stock_bodega WHERE id_bodega = :b");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['valorStockBodega'] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM movimientos_bodega
        WHERE id_bodega = :b
          AND fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['movMesBodega'] = (int)$stmt->fetchColumn();

    // Stock bajo en mi bodega
    $stmt = $pdo->prepare("
        SELECT p.codigo, p.nombre, p.stock_minimo, s.stock_actual,
               um.nombre AS unidad
        FROM stock_bodega s
        INNER JOIN productos p ON p.id = s.id_producto
        LEFT  JOIN unidades_medida um ON um.id = p.id_unidad_medida
        WHERE s.id_bodega = :b
          AND p.estado = 1
          AND p.stock_minimo > 0
          AND s.stock_actual < p.stock_minimo
        ORDER BY (p.stock_minimo - s.stock_actual) DESC
        LIMIT 8
    ");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['stockBajoBodega'] = $stmt->fetchAll();

    // Últimos movimientos de mi bodega
    $stmt = $pdo->prepare("
        SELECT m.fecha_movimiento, m.tipo_movimiento, m.cantidad,
               p.codigo, p.nombre AS producto
        FROM movimientos_bodega m
        INNER JOIN productos p ON p.id = m.id_producto
        WHERE m.id_bodega = :b
        ORDER BY m.id DESC
        LIMIT 10
    ");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['ultimosMovBodega'] = $stmt->fetchAll();

    // Solicitudes pendientes dirigidas a mi bodega (origen)
    $stmt = $pdo->prepare("
        SELECT s.numero_solicitud, s.estado, s.created_at,
               bd.nombre AS destino, u.nombre AS usuario
        FROM solicitudes s
        LEFT JOIN bodegas  bd ON bd.id = s.id_bodega_destino
        LEFT JOIN usuarios u  ON u.id  = s.id_usuario
        WHERE s.id_bodega_origen = :b
          AND s.estado = 'pendiente'
        ORDER BY s.id DESC
        LIMIT 8
    ");
    $stmt->execute(array(':b' => $miBodegaId));
    $data['solicitudesBodega'] = $stmt->fetchAll();
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
        SELECT s.numero_solicitud, s.estado, s.created_at, s.observacion,
               bo.nombre AS origen, bd.nombre AS destino
        FROM solicitudes s
        LEFT JOIN bodegas bo ON bo.id = s.id_bodega_origen
        LEFT JOIN bodegas bd ON bd.id = s.id_bodega_destino
        WHERE s.id_usuario = :u
        ORDER BY s.id DESC
        LIMIT 8
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

<!-- =================================================================
     ENCABEZADO COMÚN
     ================================================================= -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1 text-gray-800 fw-bold">
            <i class="bi bi-speedometer2 text-primary me-2"></i>Panel Principal
        </h1>
        <p class="text-muted mb-0 small">
            Hola, <strong><?php echo h($user['nombre']); ?></strong> ·
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">
                <?php echo h($rolTxt); ?>
            </span>
            <?php if ($miBodegaNombre): ?>
                · <i class="bi bi-geo-alt"></i> <?php echo h($miBodegaNombre); ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-none d-md-block">
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3 py-2 fs-6 fw-medium">
            <i class="bi bi-calendar3 me-2"></i> <?php echo date('d / m / Y'); ?>
        </span>
    </div>
</div>


<?php /* ================================================================
        DASHBOARD ADMIN / AUDITOR / CONSULTA
        ================================================================ */ ?>
<?php if (in_array($rol, array('admin', 'auditor', 'consulta'), true)): ?>

<!-- FILA 1: KPIs MAESTROS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3 col-xl">
        <div class="card shadow-sm border-0 border-bottom border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Productos</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($data['totalProductos'], 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-boxes text-primary fs-4"></i>
                    </div>
                </div>
            </div>
            <?php if ($rol === 'admin'): ?>
                <a href="/Bodega/modulos/productos/productos_lista.php" class="stretched-link"></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl">
        <div class="card shadow-sm border-0 border-bottom border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Bodegas</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($data['totalBodegas'], 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-buildings text-info fs-4"></i>
                    </div>
                </div>
            </div>
            <?php if ($rol === 'admin'): ?>
                <a href="/Bodega/modulos/bodegas/bodegas_lista.php" class="stretched-link"></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl">
        <div class="card shadow-sm border-0 border-bottom border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Proveedores</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($data['totalProveedores'], 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-truck text-success fs-4"></i>
                    </div>
                </div>
            </div>
            <?php if ($rol === 'admin'): ?>
                <a href="/Bodega/modulos/proveedores/proveedores_lista.php" class="stretched-link"></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl">
        <div class="card shadow-sm border-0 border-bottom border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Usuarios</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($data['totalUsuarios'], 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-people text-warning fs-4"></i>
                    </div>
                </div>
            </div>
            <?php if ($rol === 'admin'): ?>
                <a href="/Bodega/modulos/usuarios/usuarios_lista.php" class="stretched-link"></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl">
        <div class="card shadow-sm border-0 border-bottom border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">OC Pendientes</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($data['ocPendientes'], 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-receipt-cutoff text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILA 2: KPIs OPERACIONALES -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-activity text-primary fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:.65rem;letter-spacing:.5px;">Movimientos hoy</div>
                        <div class="h4 mb-0 fw-bold"><?php echo number_format($data['movHoy'], 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-calendar-month text-info fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:.65rem;letter-spacing:.5px;">Mov. del mes</div>
                        <div class="h4 mb-0 fw-bold"><?php echo number_format($data['movMes'], 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-clipboard-check text-warning fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:.65rem;letter-spacing:.5px;">Solic. pendientes</div>
                        <div class="h4 mb-0 fw-bold"><?php echo number_format($data['solPend'], 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="bi bi-cash-stack text-success fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:.65rem;letter-spacing:.5px;">Valor stock total</div>
                        <div class="h5 mb-0 fw-bold text-success">$<?php echo number_format($data['valorStock'], 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILA 3: GRÁFICO 7 DÍAS + TOP PRODUCTOS -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-graph-up text-primary me-2"></i>Movimientos últimos 7 días</h6>
                <span class="text-muted small">Entradas vs Salidas</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:280px;">
                    <canvas id="chartMov7d"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-star-fill text-warning me-2"></i>Top productos movidos</h6>
                <small class="text-muted">Últimos 30 días</small>
            </div>
            <div class="card-body">
                <?php if (!$data['topProductos']): ?>
                    <div class="text-center text-muted py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Sin movimientos</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($data['topProductos'] as $i => $p): ?>
                            <div class="list-group-item px-0 border-0 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">#<?php echo $i+1; ?></span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-medium text-dark text-truncate"><?php echo h($p['nombre']); ?></div>
                                        <div class="small text-muted"><?php echo h($p['codigo']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-primary"><?php echo (int)$p['movs']; ?></div>
                                        <div class="small text-muted">movs</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- FILA 4: STOCK BAJO + ÚLTIMOS MOVIMIENTOS -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stock bajo mínimo</h6>
                <span class="badge bg-danger bg-opacity-10 text-danger"><?php echo count($data['stockBajo']); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$data['stockBajo']): ?>
                    <div class="text-center text-muted py-4"><i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>Todo en orden</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Actual</th>
                                <th class="text-end">Mínimo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['stockBajo'] as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium text-dark small"><?php echo h($p['nombre']); ?></div>
                                    <div class="text-muted" style="font-size:.7rem;"><?php echo h($p['codigo']); ?></div>
                                </td>
                                <td class="text-end fw-bold text-danger"><?php echo number_format((float)$p['stock_total'], 2, ',', '.'); ?></td>
                                <td class="text-end text-muted"><?php echo number_format((float)$p['stock_minimo'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-arrow-left-right text-primary me-2"></i>Últimos movimientos</h6>
                <a href="/Bodega/modulos/movimientos/movimientos_lista.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$data['ultimosMov']): ?>
                    <div class="text-center text-muted py-4">Sin movimientos</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th class="text-end">Cant.</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['ultimosMov'] as $m): ?>
                            <?php $badge = tipo_badge_dash($m['tipo_movimiento']); ?>
                            <tr>
                                <td class="text-nowrap text-secondary" style="font-size:.8rem;">
                                    <?php echo date('d/m H:i', strtotime($m['fecha_movimiento'])); ?>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark small text-truncate" style="max-width:180px;" title="<?php echo h($m['producto']); ?>">
                                        <?php echo h($m['producto']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.7rem;">
                                        <i class="bi bi-geo-alt"></i> <?php echo h($m['bodega']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badge[0]; ?>" style="font-size:.65rem;">
                                        <i class="bi <?php echo $badge[1]; ?> me-1"></i><?php echo h($badge[2]); ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold small"><?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- FILA 5: TOP USUARIOS + SOLICITUDES RECIENTES -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-check text-success me-2"></i>Usuarios más activos</h6>
                <small class="text-muted">Últimos 30 días</small>
            </div>
            <div class="card-body p-0">
                <?php if (!$data['topUsuarios']): ?>
                    <div class="text-center text-muted py-4">Sin actividad</div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($data['topUsuarios'] as $i => $u): ?>
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-primary" style="width:36px;height:36px;">
                                <?php echo strtoupper(mb_substr($u['nombre'], 0, 1)); ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-medium text-dark text-truncate"><?php echo h($u['nombre']); ?></div>
                                <div class="small text-muted">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:.65rem;"><?php echo h($u['rol']); ?></span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-primary"><?php echo (int)$u['movs']; ?></div>
                                <div class="small text-muted">movs</div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clipboard-check text-warning me-2"></i>Solicitudes recientes</h6>
                <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$data['ultimasSolicitudes']): ?>
                    <div class="text-center text-muted py-4">Sin solicitudes</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>N°</th>
                                <th>Usuario</th>
                                <th>Origen → Destino</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['ultimasSolicitudes'] as $s):
                            $est = $s['estado'];
                            $estBadge = $est === 'pendiente' ? 'bg-warning text-dark' : ($est === 'procesada' ? 'bg-success' : ($est === 'rechazada' ? 'bg-danger' : 'bg-secondary'));
                        ?>
                            <tr>
                                <td class="fw-medium small"><?php echo h($s['numero_solicitud']); ?></td>
                                <td class="small"><?php echo h($s['usuario']); ?></td>
                                <td class="small text-muted">
                                    <?php echo h($s['origen']); ?>
                                    <i class="bi bi-arrow-right mx-1"></i>
                                    <?php echo h($s['destino']); ?>
                                </td>
                                <td><span class="badge <?php echo $estBadge; ?>" style="font-size:.65rem;"><?php echo strtoupper($est); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- FILA 6: DISTRIBUCIÓN POR BODEGA -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-buildings text-info me-2"></i>Distribución de inventario por bodega</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr>
                        <th>Código</th>
                        <th>Bodega</th>
                        <th>Responsable</th>
                        <th class="text-end">Productos</th>
                        <th class="text-end">Unidades</th>
                        <th class="text-end">Valor ($)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['distribBodegas'] as $b): ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?php echo h($b['codigo']); ?></span></td>
                        <td class="fw-medium text-dark"><?php echo h($b['nombre']); ?></td>
                        <td class="text-muted small"><?php echo h($b['responsable']); ?></td>
                        <td class="text-end"><?php echo (int)$b['productos']; ?></td>
                        <td class="text-end"><?php echo number_format((float)$b['unidades'], 2, ',', '.'); ?></td>
                        <td class="text-end fw-bold text-success">$<?php echo number_format((float)$b['valor'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; /* fin admin/auditor/consulta */ ?>


<?php /* ================================================================
        DASHBOARD BODEGA (ENCARGADO)
        ================================================================ */ ?>
<?php if ($rol === 'bodega'): ?>

<?php if (!$miBodegaId): ?>
    <div class="alert alert-warning shadow-sm">
        <i class="bi bi-exclamation-triangle me-2"></i>
        No tienes una bodega asignada. Contacta al administrador.
    </div>
<?php else: ?>

<!-- KPIs BODEGA -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Productos en bodega</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($data['productosBodega'], 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-boxes text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Valor del stock</p>
                        <h5 class="mb-0 fw-bold text-success">$<?php echo number_format($data['valorStockBodega'], 0, ',', '.'); ?></h5>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-cash-stack text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Bajo mínimo</p>
                        <h3 class="mb-0 fw-bold text-danger"><?php echo count($data['stockBajoBodega']); ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Mov. del mes</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($data['movMesBodega'], 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-calendar-month text-info fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ACCIONES RÁPIDAS -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-lightning-charge text-warning me-2"></i>Acciones rápidas</h6>
        <div class="d-flex flex-wrap gap-2">
            <a href="/Bodega/modulos/movimientos/movimientos_crear.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nuevo movimiento
            </a>
            <a href="/Bodega/modulos/movimientos/movimientos_lista.php" class="btn btn-outline-primary">
                <i class="bi bi-list-ul me-1"></i> Ver movimientos
            </a>
            <a href="/Bodega/modulos/stock_lista.php" class="btn btn-outline-secondary">
                <i class="bi bi-inboxes me-1"></i> Ver stock
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Stock bajo en mi bodega -->
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between">
                <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stock bajo mínimo</h6>
                <span class="badge bg-danger bg-opacity-10 text-danger"><?php echo count($data['stockBajoBodega']); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$data['stockBajoBodega']): ?>
                    <div class="text-center text-muted py-4"><i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>Todo en orden</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr><th>Producto</th><th class="text-end">Actual</th><th class="text-end">Mín.</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['stockBajoBodega'] as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium text-dark small"><?php echo h($p['nombre']); ?></div>
                                    <div class="text-muted" style="font-size:.7rem;"><?php echo h($p['codigo']); ?></div>
                                </td>
                                <td class="text-end fw-bold text-danger"><?php echo number_format((float)$p['stock_actual'], 2, ',', '.'); ?></td>
                                <td class="text-end text-muted"><?php echo number_format((float)$p['stock_minimo'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Últimos movimientos de mi bodega -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-arrow-left-right text-primary me-2"></i>Últimos movimientos</h6>
                <a href="/Bodega/modulos/movimientos/movimientos_lista.php?id_bodega=<?php echo $miBodegaId; ?>" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$data['ultimosMovBodega']): ?>
                    <div class="text-center text-muted py-4">Sin movimientos</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th class="text-end">Cant.</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['ultimosMovBodega'] as $m):
                            $badge = tipo_badge_dash($m['tipo_movimiento']);
                        ?>
                            <tr>
                                <td class="text-nowrap text-secondary" style="font-size:.8rem;"><?php echo date('d/m H:i', strtotime($m['fecha_movimiento'])); ?></td>
                                <td>
                                    <div class="fw-medium text-dark small"><?php echo h($m['producto']); ?></div>
                                    <div class="text-muted" style="font-size:.7rem;"><?php echo h($m['codigo']); ?></div>
                                </td>
                                <td><span class="badge <?php echo $badge[0]; ?>" style="font-size:.65rem;"><i class="bi <?php echo $badge[1]; ?> me-1"></i><?php echo h($badge[2]); ?></span></td>
                                <td class="text-end fw-bold small"><?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Solicitudes pendientes a mi bodega -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between">
        <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-clipboard-check me-2"></i>Solicitudes pendientes a mi bodega</h6>
        <span class="badge bg-warning bg-opacity-10 text-warning"><?php echo count($data['solicitudesBodega']); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (!$data['solicitudesBodega']): ?>
            <div class="text-center text-muted py-4"><i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>Sin solicitudes pendientes</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr><th>N°</th><th>Solicitante</th><th>Destino</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                <?php foreach ($data['solicitudesBodega'] as $s): ?>
                    <tr>
                        <td class="fw-medium small"><?php echo h($s['numero_solicitud']); ?></td>
                        <td class="small"><?php echo h($s['usuario']); ?></td>
                        <td class="small text-muted"><?php echo h($s['destino']); ?></td>
                        <td class="small text-nowrap text-secondary"><?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; /* fin bodega asignada */ ?>
<?php endif; /* fin rol bodega */ ?>


<?php /* ================================================================
        DASHBOARD SOLICITANTE
        ================================================================ */ ?>
<?php if ($rol === 'solicitante'): ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;">Total solicitudes</p>
                        <h3 class="mb-0 fw-bold text-dark"><?php echo (int)$data['misTotal']; ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-clipboard2 text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;">Pendientes</p>
                        <h3 class="mb-0 fw-bold text-warning"><?php echo (int)$data['misPend']; ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-hourglass-split text-warning fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;">Procesadas</p>
                        <h3 class="mb-0 fw-bold text-success"><?php echo (int)$data['misProc']; ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-check2-circle text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-bottom border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;">Rechazadas</p>
                        <h3 class="mb-0 fw-bold text-danger"><?php echo (int)$data['misRech']; ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-x-circle text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body text-center py-4">
        <h5 class="fw-bold mb-2"><i class="bi bi-plus-circle text-primary me-2"></i>¿Necesitas solicitar productos?</h5>
        <p class="text-muted mb-3">Crea una nueva solicitud de traslado a cualquier bodega.</p>
        <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nueva solicitud
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Mis últimas solicitudes</h6>
        <a href="/Bodega/modulos/movimientos/solicitudes_lista.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
    </div>
    <div class="card-body p-0">
        <?php if (!$data['misSolicitudes']): ?>
            <div class="text-center text-muted py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Aún no has creado solicitudes</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr><th>N°</th><th>Origen</th><th>Destino</th><th>Estado</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                <?php foreach ($data['misSolicitudes'] as $s):
                    $est = $s['estado'];
                    $estBadge = $est === 'pendiente' ? 'bg-warning text-dark' : ($est === 'procesada' ? 'bg-success' : ($est === 'rechazada' ? 'bg-danger' : 'bg-secondary'));
                ?>
                    <tr>
                        <td class="fw-medium small"><?php echo h($s['numero_solicitud']); ?></td>
                        <td class="small text-muted"><?php echo h($s['origen']); ?></td>
                        <td class="small text-muted"><?php echo h($s['destino']); ?></td>
                        <td><span class="badge <?php echo $estBadge; ?>" style="font-size:.65rem;"><?php echo strtoupper($est); ?></span></td>
                        <td class="small text-nowrap text-secondary"><?php echo date('d/m/Y', strtotime($s['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; /* fin solicitante */ ?>


<?php /* ================================================================
        FALLBACK: sin rol reconocido
        ================================================================ */ ?>
<?php if (!in_array($rol, array('admin','bodega','solicitante','consulta','auditor'), true)): ?>
    <div class="alert alert-info shadow-sm">
        <i class="bi bi-info-circle me-2"></i>
        Tu rol no tiene un panel asignado. Contacta al administrador.
    </div>
<?php endif; ?>


<?php if (in_array($rol, array('admin','auditor','consulta'), true)): ?>
<!-- ============ CHART.JS: Movimientos 7 días ============ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    var raw = <?php echo json_encode($data['chartMov']); ?>;

    // Generar últimos 7 días en orden cronológico
    var labels = [];
    var mapEntradas = {};
    var mapSalidas  = {};

    for (var i = 0; i < raw.length; i++) {
        mapEntradas[raw[i].fecha] = parseInt(raw[i].entradas, 10) || 0;
        mapSalidas[raw[i].fecha]  = parseInt(raw[i].salidas, 10)  || 0;
    }

    var entradas = [];
    var salidas  = [];
    var hoy = new Date();
    for (var d = 6; d >= 0; d--) {
        var dt = new Date(hoy);
        dt.setDate(hoy.getDate() - d);
        var y = dt.getFullYear();
        var m = ('0' + (dt.getMonth()+1)).slice(-2);
        var day = ('0' + dt.getDate()).slice(-2);
        var key = y + '-' + m + '-' + day;

        labels.push(day + '/' + m);
        entradas.push(mapEntradas[key] || 0);
        salidas.push(mapSalidas[key] || 0);
    }

    var ctx = document.getElementById('chartMov7d');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Entradas',
                    data: entradas,
                    backgroundColor: 'rgba(25,135,84,0.75)',
                    borderRadius: 6,
                    borderSkipped: false
                },
                {
                    label: 'Salidas',
                    data: salidas,
                    backgroundColor: 'rgba(220,53,69,0.75)',
                    borderRadius: 6,
                    borderSkipped: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, boxWidth: 8 }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>