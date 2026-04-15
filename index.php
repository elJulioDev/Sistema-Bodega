<?php
require_once __DIR__ . '/inc/db.php'; // Agregamos la conexión a BD para el Dashboard
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

// ---------------------------------------------------------
// CONSULTAS PARA LOS KPIS DEL DASHBOARD
// ---------------------------------------------------------

// 1. Total de Productos Activos
$stmt = $pdo->query("SELECT COUNT(id) FROM productos WHERE estado = 1");
$totalProductos = $stmt->fetchColumn();

// 2. Órdenes de Compra Pendientes o Parciales
$stmt = $pdo->query("SELECT COUNT(id) FROM ordenes_compra WHERE estado IN ('pendiente', 'parcial')");
$totalOcPendientes = $stmt->fetchColumn();

// 3. Total de Proveedores
$stmt = $pdo->query("SELECT COUNT(id) FROM proveedores WHERE estado = 1");
$totalProveedores = $stmt->fetchColumn();

// 4. Total de Bodegas
$stmt = $pdo->query("SELECT COUNT(id) FROM bodegas WHERE estado = 1");
$totalBodegas = $stmt->fetchColumn();

// 5. Últimos 5 movimientos de bodega
$stmt = $pdo->query("
    SELECT m.fecha_movimiento, p.codigo, p.nombre as producto, m.tipo_movimiento, m.cantidad, b.nombre as bodega
    FROM movimientos_bodega m
    INNER JOIN productos p ON m.id_producto = p.id
    INNER JOIN bodegas b ON m.id_bodega = b.id
    ORDER BY m.id DESC
    LIMIT 5
");
$ultimosMovimientos = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800 fw-bold">Panel Principal</h1>
        <p class="text-muted mb-0">Resumen general del estado de la bodega e inventario.</p>
    </div>
    <div class="d-none d-md-block">
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3 py-2 fs-6 fw-medium">
            <i class="bi bi-calendar3 me-2"></i> <?php echo date('d / m / Y'); ?>
        </span>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-bottom border-primary border-4 h-100" style="transition: transform 0.2s;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;">Total Productos</p>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo number_format($totalProductos, 0, ',', '.'); ?></h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="bi bi-boxes text-primary fs-3"></i>
                    </div>
                </div>
            </div>
            <a href="/Bodega/productos/index.php" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-bottom border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;">OC Pendientes</p>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo number_format($totalOcPendientes, 0, ',', '.'); ?></h2>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="bi bi-cart-dash text-warning fs-3"></i>
                    </div>
                </div>
            </div>
            <a href="/Bodega/ordenes_compra/index.php" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-bottom border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;">Proveedores</p>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo number_format($totalProveedores, 0, ',', '.'); ?></h2>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="bi bi-truck text-success fs-3"></i>
                    </div>
                </div>
            </div>
            <a href="/Bodega/proveedores/index.php" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 border-bottom border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;">Bodegas</p>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo number_format($totalBodegas, 0, ',', '.'); ?></h2>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                        <i class="bi bi-buildings text-info fs-3"></i>
                    </div>
                </div>
            </div>
            <a href="/Bodega/bodegas/index.php" class="stretched-link"></a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-arrow-left-right text-primary me-2"></i>Últimos Movimientos</h5>
                <a href="/Bodega/movimientos/index.php" class="btn btn-sm btn-outline-primary px-3">Ver todos</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted" style="font-size: 0.85rem;">
                            <tr>
                                <th>FECHA</th>
                                <th>CÓDIGO</th>
                                <th>PRODUCTO</th>
                                <th>TIPO</th>
                                <th class="text-end">CANT.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$ultimosMovimientos): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No hay movimientos recientes registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ultimosMovimientos as $mov): ?>
                                    <tr>
                                        <td class="text-nowrap text-secondary" style="font-size: 0.9rem;">
                                            <?php echo date('d/m/Y', strtotime($mov['fecha_movimiento'])); ?><br>
                                            <small><?php echo date('H:i', strtotime($mov['fecha_movimiento'])); ?></small>
                                        </td>
                                        <td class="fw-medium text-dark"><?php echo h($mov['codigo']); ?></td>
                                        <td>
                                            <div class="text-truncate fw-medium text-dark" style="max-width: 200px;" title="<?php echo h($mov['producto']); ?>">
                                                <?php echo h($mov['producto']); ?>
                                            </div>
                                            <div class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h($mov['bodega']); ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                                $tipo = h($mov['tipo_movimiento']);
                                                // Definir colores según si es entrada o salida
                                                $badgeClass = (strpos($tipo, 'entrada') !== false) ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> border-0">
                                                <?php echo strtoupper(str_replace('_', ' ', $tipo)); ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold fs-6">
                                            <?php echo number_format((float)$mov['cantidad'], 2, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Accesos Rápidos</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    
                    <a href="/Bodega/ordenes_compra/crear.php" class="btn btn-outline-primary text-start d-flex align-items-center p-3 rounded-3" style="border-width: 2px;">
                        <i class="bi bi-cart-plus fs-3 me-3"></i>
                        <div>
                            <div class="fw-bold">Nueva OC</div>
                            <div class="small text-muted border-0">Generar solicitud a proveedor</div>
                        </div>
                    </a>

                    <a href="/Bodega/facturas/crear.php" class="btn btn-outline-success text-start d-flex align-items-center p-3 rounded-3" style="border-width: 2px;">
                        <i class="bi bi-receipt fs-3 me-3"></i>
                        <div>
                            <div class="fw-bold">Ingresar Factura</div>
                            <div class="small text-muted border-0">Recepcionar stock al inventario</div>
                        </div>
                    </a>

                    <a href="/Bodega/productos/crear.php" class="btn btn-outline-secondary text-start d-flex align-items-center p-3 rounded-3" style="border-width: 2px;">
                        <i class="bi bi-box-seam fs-3 me-3"></i>
                        <div>
                            <div class="fw-bold">Nuevo Producto</div>
                            <div class="small text-muted border-0">Añadir al catálogo maestro</div>
                        </div>
                    </a>

                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>