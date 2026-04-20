<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

// 1. Añadir 'solicitante' a los roles permitidos
require_role(array('admin', 'bodega', 'solicitante'));

$buscar        = trim((string)get('buscar'));
$id_bodega     = get('id_bodega');
$filtro_alerta = get('alerta', '');

// ============================================================
// BLOQUEO POR ROL (ENCARGADO O SOLICITANTE)
// ============================================================
// Creamos una variable para saber si el usuario tiene vista restringida
$is_restricted = is_encargado() || is_solicitante();

if ($is_restricted) {
    if (is_solicitante()) {
        // Obtener la bodega ligada a la unidad del solicitante
        $stmtUnidad = $pdo->prepare("SELECT id FROM bodegas WHERE id_unidad = ? AND estado = 1 LIMIT 1");
        $stmtUnidad->execute(array(user_unidad_id()));
        $b_id = $stmtUnidad->fetchColumn();
        $id_bodega = $b_id ? (string)$b_id : '0';
    } else {
        // El encargado ve su propia bodega asignada
        $id_bodega = (string)user_bodega_id();
    }

    if ((int)$id_bodega <= 0) {
        set_flash('error', 'Tu usuario o unidad no tiene una bodega asignada. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
}

// Buscar bodega central (default para admin)
$stmtBC = $pdo->prepare("SELECT id, nombre FROM bodegas WHERE estado=1 AND codigo='CENTRAL' LIMIT 1");
$stmtBC->execute();
$bodegaCentral   = $stmtBC->fetch();
$idBodegaCentral = $bodegaCentral ? (int)$bodegaCentral['id'] : 0;

// Para admin: primera carga = bodega central
if (is_admin()) {
    if (!isset($_GET['id_bodega'])) {
        $id_bodega = $idBodegaCentral > 0 ? (string)$idBodegaCentral : '';
    } else {
        $id_bodega = trim((string)$_GET['id_bodega']);
    }
}

// Bodegas disponibles en el selector
if ($is_restricted) {
    // Solo su bodega asignada/ligada
    $stmtB = $pdo->prepare("SELECT id, codigo, nombre FROM bodegas WHERE id = ? AND estado = 1 LIMIT 1");
    $stmtB->execute(array($id_bodega));
    $bodegas = $stmtB->fetchAll();
    $miBodega = !empty($bodegas[0]) ? $bodegas[0] : null;
} else {
    $bodegas = $pdo->query("
        SELECT id, codigo, nombre FROM bodegas
        WHERE estado = 1
        ORDER BY (codigo='CENTRAL') DESC, nombre ASC
    ")->fetchAll();
    $miBodega = null;
}

// Estadísticas
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

$pageTitle = $is_restricted ? 'Stock de mi Bodega' : 'Control de Stock';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold">
            <i class="bi bi-inboxes text-primary me-2"></i>
            <?php echo $is_restricted ? 'Stock de mi Bodega' : 'Control de Stock'; ?>
        </h1>
        <?php if ($is_restricted && $miBodega): ?>
            <small class="text-muted">
                <i class="bi bi-geo-alt-fill me-1"></i>
                <?php echo h($miBodega['nombre'] . ' (' . $miBodega['codigo'] . ')'); ?>
            </small>
        <?php else: ?>
            <small class="text-muted">Stock actual por bodega y producto</small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (is_admin()): ?>
            <a href="facturas/facturas_crear.php" class="btn btn-sm btn-success">
                <i class="bi bi-receipt me-1"></i> Ingresar Factura
            </a>
        <?php endif; ?>
        <?php if ($canOperate): ?>
            <a href="movimientos/movimientos_crear.php" class="btn btn-sm btn-primary">
                <i class="bi bi-arrow-left-right me-1"></i>
                <?php echo is_encargado() ? 'Nuevo Traslado' : 'Registrar Movimiento'; ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ESTADISTICAS -->
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
                <div class="h4 mb-0 fw-bold text-warning"><?php echo (int)$stats['stock_bajo']; ?></div>
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
            <div class="col-md-<?php echo $is_restricted ? '8' : '4'; ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por código o nombre...">
                </div>
            </div>

            <?php if (!$is_restricted): ?>
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
            <?php else: ?>
                <input type="hidden" name="id_bodega" value="<?php echo (int)$id_bodega; ?>">
            <?php endif; ?>

            <div class="col-md-3">
                <select name="alerta" class="form-select form-select-sm">
                    <option value="">Sin filtro de alerta</option>
                    <option value="bajo" <?php echo $filtro_alerta === 'bajo' ? 'selected' : ''; ?>>⚠ Stock bajo mínimo</option>
                    <option value="sin"  <?php echo $filtro_alerta === 'sin'  ? 'selected' : ''; ?>>✗ Sin stock</option>
                </select>
            </div>
            <div class="col-md-<?php echo $is_restricted ? '1' : '2'; ?> d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                <?php if ($buscar !== '' || (is_admin() && isset($_GET['id_bodega'])) || $filtro_alerta !== ''): ?>
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
                        <?php if (!$is_restricted): ?>
                            <th class="px-3 py-2">BODEGA</th>
                        <?php endif; ?>
                        <th class="py-2 <?php echo $is_restricted ? 'px-3' : ''; ?>">PRODUCTO</th>
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
                    <?php $colspan = $is_restricted ? 7 : 8; if ($canOperate) $colspan++; ?>
                    <tr>
                        <td colspan="<?php echo $colspan; ?>" class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No hay stock registrado con los filtros aplicados.</p>
                            <?php if (is_admin()): ?>
                                <small class="text-muted">Para ingresar stock, registra una factura de compra.</small>
                            <?php else: ?>
                                <small class="text-muted">Solicita reposición desde la bodega central u otras bodegas.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stocks as $s): 
                        $stock     = (float)$s['stock_actual'];
                        $minimo    = (float)$s['stock_minimo'];
                        $sinStock  = ($stock <= 0);
                        $bajoMin   = ($minimo > 0 && $stock > 0 && $stock <= $minimo);
                        $valor     = $stock * (float)$s['costo_promedio'];
                        $esCentral = ($s['bodega_codigo'] === 'CENTRAL');
                        
                        $rowClass = '';
                        if ($sinStock)     $rowClass = 'table-danger';
                        elseif ($bajoMin)  $rowClass = 'table-warning';
                    ?>
                        <tr<?php echo $rowClass ? ' class="' . $rowClass . '"' : ''; ?>>
                            <?php if (!$is_restricted): ?>
                                <td class="px-3">
                                    <div class="fw-semibold small"><?php echo h($s['bodega_nombre']); ?></div>
                                    <small class="text-muted">
                                        <?php echo h($s['bodega_codigo']); ?>
                                        <?php if ($esCentral): ?> <span class="text-warning">★</span><?php endif; ?>
                                    </small>
                                </td>
                            <?php endif; ?>
                            <td class="<?php echo $is_restricted ? 'px-3' : ''; ?>">
                                <div class="fw-medium small"><?php echo h($s['producto_nombre']); ?></div>
                                <small class="text-muted"><?php echo h($s['producto_codigo']); ?></small>
                            </td>
                            <td class="text-center text-secondary small">
                                <?php echo h($s['unidad_codigo'] ? $s['unidad_codigo'] : '—'); ?>
                            </td>
                            <td class="text-end fw-bold <?php echo $sinStock ? 'text-danger' : ($bajoMin ? 'text-warning' : ''); ?>">
                                <?php echo number_format($stock, 2, ',', '.'); ?>
                            </td>
                            <td class="text-end text-secondary small">
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
                                        <?php if (is_admin()): ?>
                                            <a href="movimientos/movimientos_crear.php?tipo=salida_consumo&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                               class="btn btn-outline-danger" title="Salida por consumo">
                                                <i class="bi bi-box-arrow-right"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="movimientos/movimientos_crear.php?tipo=traslado&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                           class="btn btn-outline-primary" title="Trasladar a otra bodega">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </a>
                                        <?php if (is_admin()): ?>
                                            <a href="movimientos/movimientos_crear.php?tipo=ajuste_entrada&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                               class="btn btn-outline-success" title="Ajuste de entrada">
                                                <i class="bi bi-plus-circle"></i>
                                            </a>
                                        <?php endif; ?>
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
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>