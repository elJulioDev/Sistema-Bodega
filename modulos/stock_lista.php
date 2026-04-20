<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
require_role(array('admin', 'bodega'));

$buscar = trim((string)get('buscar'));
$id_bodega = get('id_bodega');
$filtro_alerta = get('alerta', ''); // 'bajo' | ''

// Buscar bodega central
$stmtBC = $pdo->prepare("SELECT id, nombre FROM bodegas WHERE estado=1 AND codigo='CENTRAL' LIMIT 1");
$stmtBC->execute();
$bodegaCentral = $stmtBC->fetch();
$idBodegaCentral = $bodegaCentral ? (int)$bodegaCentral['id'] : 0;

// Si el usuario envió el filtro explícitamente (incluso vacío = todas), respetarlo.
// Solo aplicar la central como default en la primera carga (sin parámetro en URL).
if (!isset($_GET['id_bodega'])) {
    // Primera carga: mostrar solo bodega central por defecto
    $id_bodega = $idBodegaCentral > 0 ? (string)$idBodegaCentral : '';
} else {
    // El usuario eligió explícitamente (vacío = todas las bodegas)
    $id_bodega = trim((string)$_GET['id_bodega']);
}

$bodegas = $pdo->query("SELECT id, codigo, nombre FROM bodegas WHERE estado = 1 ORDER BY (codigo='CENTRAL') DESC, nombre ASC")->fetchAll();

// Estadísticas generales (filtradas por bodega si aplica)
$sqlStats = "
    SELECT 
        COUNT(*) AS total_registros,
        COALESCE(SUM(sb.stock_actual), 0) AS stock_total,
        COALESCE(SUM(sb.stock_actual * sb.costo_promedio), 0) AS valor_total,
        SUM(CASE WHEN sb.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
        SUM(CASE WHEN p.stock_minimo > 0 AND sb.stock_actual > 0 AND sb.stock_actual <= p.stock_minimo THEN 1 ELSE 0 END) AS stock_bajo
    FROM stock_bodega sb
    INNER JOIN productos p ON p.id = sb.id_producto
    WHERE p.estado = 1
";
$paramsStats = array();
if ($id_bodega !== '') {
    $sqlStats .= " AND sb.id_bodega = :idb";
    $paramsStats[':idb'] = (int)$id_bodega;
}
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($paramsStats);
$stats = $stmtStats->fetch();

// Consulta principal
$sql = "SELECT 
            sb.*,
            b.nombre AS bodega_nombre,
            b.codigo AS bodega_codigo,
            p.codigo AS producto_codigo,
            p.nombre AS producto_nombre,
            p.stock_minimo,
            p.activo_fijo,
            um.nombre AS unidad_nombre,
            um.codigo AS unidad_codigo
        FROM stock_bodega sb
        INNER JOIN bodegas b ON b.id = sb.id_bodega
        INNER JOIN productos p ON p.id = sb.id_producto
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        WHERE p.estado = 1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (p.codigo LIKE :buscar OR p.nombre LIKE :buscar OR b.nombre LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
}

if ($id_bodega !== '') {
    $sql .= " AND sb.id_bodega = :id_bodega";
    $params[':id_bodega'] = (int)$id_bodega;
}

if ($filtro_alerta === 'bajo') {
    $sql .= " AND p.stock_minimo > 0 AND sb.stock_actual <= p.stock_minimo";
} elseif ($filtro_alerta === 'sin') {
    $sql .= " AND sb.stock_actual <= 0";
}

$sql .= " ORDER BY (b.codigo='CENTRAL') DESC, b.nombre ASC, p.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll();

$canOperate = has_role(array('admin', 'bodega'));

$pageTitle = 'Control de Stock';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold"><i class="bi bi-inboxes text-primary me-2"></i>Control de Stock</h1>
        <small class="text-muted">Stock actual por bodega y producto</small>
    </div>
    <div class="d-flex gap-2">
        <a href="facturas/facturas_crear.php" class="btn btn-sm btn-success">
            <i class="bi bi-receipt me-1"></i> Ingresar Factura
        </a>
        <?php if ($canOperate): ?>
            <a href="movimientos/movimientos_crear.php" class="btn btn-sm btn-primary">
                <i class="bi bi-arrow-left-right me-1"></i> Registrar Movimiento
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ESTADÍSTICAS -->
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Productos en Stock</div>
                <div class="h4 mb-0 fw-bold"><?php echo (int)$stats['total_registros']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Unidades Totales</div>
                <div class="h4 mb-0 fw-bold text-success"><?php echo number_format((float)$stats['stock_total'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Stock Bajo</div>
                <div class="h4 mb-0 fw-bold text-warning">
                    <?php echo (int)$stats['stock_bajo']; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Valor stock</div>
                <div class="h4 mb-0 fw-bold text-info">$<?php echo number_format((float)$stats['valor_total'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2 px-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por código o nombre...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="id_bodega" class="form-select form-select-sm">
                    <option value="">Todas las bodegas</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>" <?php echo ((string)$id_bodega === (string)$b['id']) ? 'selected' : ''; ?>>
                            <?php echo h($b['nombre']); ?><?php echo $b['codigo'] === 'CENTRAL' ? ' ★' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="alerta" class="form-select form-select-sm">
                    <option value="">Sin filtro de alerta</option>
                    <option value="bajo" <?php echo $filtro_alerta === 'bajo' ? 'selected' : ''; ?>>⚠ Stock bajo mínimo</option>
                    <option value="sin" <?php echo $filtro_alerta === 'sin' ? 'selected' : ''; ?>>✗ Sin stock</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                <?php if ($buscar !== '' || isset($_GET['id_bodega']) || $filtro_alerta !== ''): ?>
                    <a href="stock_lista.php" class="btn btn-sm btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABLA -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="table-light text-secondary" style="font-size: 0.75rem;">
                    <tr>
                        <th class="px-3 py-2">BODEGA</th>
                        <th class="py-2">PRODUCTO</th>
                        <th class="py-2 text-center">UNIDAD</th>
                        <th class="py-2 text-end">STOCK</th>
                        <th class="py-2 text-end">MÍN.</th>
                        <th class="py-2 text-end">C. PROMEDIO</th>
                        <th class="py-2 text-end">VALOR</th>
                        <th class="py-2 text-center">ÚLTIMA ACT.</th>
                        <?php if ($canOperate): ?>
                            <th class="px-3 py-2 text-center">ACCIONES</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$stocks): ?>
                    <tr>
                        <td colspan="<?php echo $canOperate ? 9 : 8; ?>" class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No hay stock registrado con los filtros aplicados.</p>
                            <small class="text-muted">Para ingresar stock, registra una factura de compra.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stocks as $s): 
                        $stock = (float)$s['stock_actual'];
                        $minimo = (float)$s['stock_minimo'];
                        $sinStock = ($stock <= 0);
                        $bajoMin = ($minimo > 0 && $stock > 0 && $stock <= $minimo);
                        $valor = $stock * (float)$s['costo_promedio'];
                        $esCentral = ($s['bodega_codigo'] === 'CENTRAL');
                        
                        $rowClass = '';
                        if ($sinStock) $rowClass = 'table-danger';
                        elseif ($bajoMin) $rowClass = 'table-warning';
                    ?>
                        <tr<?php echo $rowClass ? ' class="' . $rowClass . '"' : ''; ?>>
                            <td class="px-3">
                                <?php if ($esCentral): ?>
                                    <span class="badge bg-primary text-white"><i class="bi bi-star-fill me-1"></i><?php echo h($s['bodega_nombre']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                        <i class="bi bi-geo-alt me-1"></i><?php echo h($s['bodega_nombre']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <span class="badge bg-light text-dark border font-monospace small me-1"><?php echo h($s['producto_codigo']); ?></span>
                                    <?php if ((int)$s['activo_fijo'] === 1): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info small"><i class="bi bi-building"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-semibold text-dark small mt-1"><?php echo h($s['producto_nombre']); ?></div>
                            </td>
                            <td class="text-center small text-muted">
                                <?php echo h($s['unidad_nombre'] ?: '—'); ?>
                            </td>
                            <td class="text-end fw-bold fs-6">
                                <?php if ($sinStock): ?>
                                    <span class="text-danger"><i class="bi bi-exclamation-octagon-fill"></i> 0,00</span>
                                <?php elseif ($bajoMin): ?>
                                    <span class="text-warning"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo number_format($stock, 2, ',', '.'); ?></span>
                                <?php else: ?>
                                    <span class="text-success"><?php echo number_format($stock, 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small text-muted">
                                <?php echo $minimo > 0 ? number_format($minimo, 2, ',', '.') : '—'; ?>
                            </td>
                            <td class="text-end text-secondary small">
                                $<?php echo number_format((float)$s['costo_promedio'], 0, ',', '.'); ?>
                            </td>
                            <td class="text-end fw-semibold small">
                                $<?php echo number_format($valor, 0, ',', '.'); ?>
                            </td>
                            <td class="text-center text-muted small">
                                <?php echo date('d/m/Y', strtotime($s['updated_at'])); ?>
                                <br><small><?php echo date('H:i', strtotime($s['updated_at'])); ?></small>
                            </td>
                            <?php if ($canOperate): ?>
                                <td class="px-3 text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="movimientos/movimientos_crear.php?tipo=salida_consumo&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                           class="btn btn-outline-danger" title="Salida por consumo">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </a>
                                        <a href="movimientos/movimientos_crear.php?tipo=traslado&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                           class="btn btn-outline-primary" title="Trasladar a otra bodega">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </a>
                                        <a href="movimientos/movimientos_crear.php?tipo=ajuste_entrada&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                           class="btn btn-outline-success" title="Ajuste de entrada">
                                            <i class="bi bi-plus-circle"></i>
                                        </a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($stocks): ?>
            <div class="card-footer bg-white py-2 px-3 border-top">
                <small class="text-muted">
                    Mostrando <strong><?php echo count($stocks); ?></strong> registro(s).
                    Filas <span class="badge bg-warning bg-opacity-25 text-warning">amarillas</span> = stock bajo mínimo.
                    Filas <span class="badge bg-danger bg-opacity-25 text-danger">rojas</span> = sin stock.
                    La bodega con ★ es la central (recepción de facturas).
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>