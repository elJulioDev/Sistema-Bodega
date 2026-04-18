<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');

if ($id <= 0) {
    die('ID de traspaso inválido.');
}

// --- Cabecera del traspaso ---
$sql = "
    SELECT
        t.*,
        bo.codigo AS bodega_origen_codigo,
        bo.nombre AS bodega_origen_nombre,
        bd.codigo AS bodega_destino_codigo,
        bd.nombre AS bodega_destino_nombre
    FROM traspasos_bodega t
    INNER JOIN bodegas bo ON bo.id = t.id_bodega_origen
    INNER JOIN bodegas bd ON bd.id = t.id_bodega_destino
    WHERE t.id = ?
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($id));
$traspaso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$traspaso) {
    die('Traspaso no encontrado.');
}

// --- Usuario creador ---
$creadoPor = 'No registrado';
if (!empty($traspaso['created_by'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ? LIMIT 1");
        $stmtUser->execute(array((int)$traspaso['created_by']));
        $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($u && !empty($u['nombre'])) {
            $creadoPor = $u['nombre'];
        } else {
            $creadoPor = 'Usuario ID ' . (int)$traspaso['created_by'];
        }
    } catch (Exception $e) {
        $creadoPor = 'Usuario ID ' . (int)$traspaso['created_by'];
    }
}

// --- Detalle de productos traspasados ---
$sqlDetalle = "
    SELECT
        td.*,
        p.codigo AS producto_codigo,
        p.nombre AS producto_nombre,
        um.nombre AS unidad_nombre
    FROM traspasos_bodega_detalle td
    LEFT JOIN productos p ON p.id = td.id_producto
    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    WHERE td.id_traspaso = ?
    ORDER BY td.id ASC
";
$stmtDetalle = $pdo->prepare($sqlDetalle);
$stmtDetalle->execute(array($id));
$detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

// --- Movimientos generados por este traspaso ---
$sqlMov = "
    SELECT
        mb.*,
        p.codigo AS producto_codigo,
        p.nombre AS producto_nombre,
        b.codigo AS bodega_codigo,
        b.nombre AS bodega_nombre
    FROM movimientos_bodega mb
    LEFT JOIN productos p ON p.id = mb.id_producto
    LEFT JOIN bodegas  b ON b.id = mb.id_bodega
    WHERE mb.referencia_tipo = 'traslado' AND mb.referencia_id = ?
    ORDER BY mb.id ASC
";
$stmtMov = $pdo->prepare($sqlMov);
$stmtMov->execute(array($id));
$movimientos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

// --- Stock actual en bodega origen y destino para los productos del traspaso ---
$stockActual = array();
if ($detalle) {
    $ids = array();
    foreach ($detalle as $d) { $ids[] = (int)$d['id_producto']; }
    $ids = array_unique($ids);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmtStock = $pdo->prepare("
            SELECT id_bodega, id_producto, stock_actual
            FROM stock_bodega
            WHERE id_producto IN ($ph)
              AND id_bodega IN (?, ?)
        ");
        $args = array_merge($ids, array((int)$traspaso['id_bodega_origen'], (int)$traspaso['id_bodega_destino']));
        $stmtStock->execute($args);
        foreach ($stmtStock->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $stockActual[$r['id_bodega'] . '_' . $r['id_producto']] = (float)$r['stock_actual'];
        }
    }
}

// --- Totales ---
$totalTraspaso = 0;
$totalItems    = 0;
$totalCantidad = 0;
foreach ($detalle as $d) {
    $totalTraspaso += (float)$d['subtotal'];
    $totalCantidad += (float)$d['cantidad'];
    $totalItems++;
}

$pageTitle = 'Ver Traspaso #' . (int)$traspaso['id'];
require_once __DIR__ . '/../../inc/header.php';

function mov_tipo_label($tipo) {
    switch ((string)$tipo) {
        case 'traslado_salida':
            return '<span class="badge bg-danger"><i class="bi bi-arrow-up-right me-1"></i>Salida</span>';
        case 'traslado_entrada':
            return '<span class="badge bg-success"><i class="bi bi-arrow-down-left me-1"></i>Entrada</span>';
        default:
            return '<span class="badge bg-secondary">' . h($tipo) . '</span>';
    }
}

function estado_badge($estado) {
    $map = array(
        'completado' => 'bg-success',
        'pendiente'  => 'bg-warning text-dark',
        'anulado'    => 'bg-danger',
    );
    $cls = isset($map[$estado]) ? $map[$estado] : 'bg-secondary';
    return '<span class="badge ' . $cls . ' text-uppercase">' . h($estado) . '</span>';
}
?>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="text-muted small mb-1">
            <a href="movimientos_lista.php" class="text-decoration-none text-muted">
                <i class="bi bi-chevron-left"></i> Movimientos
            </a>
            <span class="mx-1">/</span>
            <span>Traslado</span>
        </div>
        <h1 class="h3 mb-1 text-gray-800">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>Traslado #<?php echo (int)$traspaso['id']; ?>
            <?php echo estado_badge($traspaso['estado']); ?>
        </h1>
        <div class="text-muted small">
            <i class="bi bi-calendar3 me-1"></i>
            Fecha: <?php echo h(date('d/m/Y', strtotime($traspaso['fecha']))); ?>
        </div>
    </div>

    <div class="d-flex gap-2 d-print-none">
        <a href="movimientos_lista.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
        <a href="movimientos_lista.php" class="btn btn-outline-primary">
            <i class="bi bi-list-ul me-1"></i> Listado Traslados
        </a>
        <button type="button" class="btn btn-outline-primary" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<!-- Visualización origen -> destino -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center text-center g-3">
            <div class="col-md-5">
                <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size:.7rem;letter-spacing:1px;">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Bodega Origen
                </div>
                <div class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25">
                    <div class="fs-5 fw-bold text-danger"><?php echo h($traspaso['bodega_origen_nombre']); ?></div>
                    <div class="text-muted small">Código: <?php echo h($traspaso['bodega_origen_codigo']); ?></div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="d-flex flex-column align-items-center text-primary">
                    <i class="bi bi-arrow-right-circle-fill" style="font-size: 2.5rem;"></i>
                    <div class="small fw-bold mt-2"><?php echo $totalItems; ?> ítem<?php echo $totalItems === 1 ? '' : 's'; ?></div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size:.7rem;letter-spacing:1px;">
                    <i class="bi bi-box-arrow-in-down-left me-1"></i>Bodega Destino
                </div>
                <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                    <div class="fs-5 fw-bold text-success"><?php echo h($traspaso['bodega_destino_nombre']); ?></div>
                    <div class="text-muted small">Código: <?php echo h($traspaso['bodega_destino_codigo']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resumen en 4 KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Ítems</div>
                <div class="h4 mb-0 fw-bold"><?php echo number_format($totalItems, 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Cantidad Total</div>
                <div class="h4 mb-0 fw-bold text-primary"><?php echo number_format($totalCantidad, 2, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Valor Total</div>
                <div class="h4 mb-0 fw-bold text-success">$<?php echo number_format($totalTraspaso, 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Movimientos</div>
                <div class="h4 mb-0 fw-bold text-info"><?php echo count($movimientos); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Info traslado + observación -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="mb-0 fw-bold text-secondary text-uppercase" style="font-size:.8rem;letter-spacing:.5px;">
                    <i class="bi bi-info-circle me-1"></i>Información
                </h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">ID Traslado:</dt>
                    <dd class="col-7 fw-bold">#<?php echo (int)$traspaso['id']; ?></dd>

                    <dt class="col-5 text-muted fw-normal">Estado:</dt>
                    <dd class="col-7"><?php echo estado_badge($traspaso['estado']); ?></dd>

                    <dt class="col-5 text-muted fw-normal">Creado por:</dt>
                    <dd class="col-7 fw-bold"><i class="bi bi-person-circle me-1 text-muted"></i><?php echo h($creadoPor); ?></dd>

                    <dt class="col-5 text-muted fw-normal">Creado el:</dt>
                    <dd class="col-7"><?php echo h(date('d/m/Y H:i', strtotime($traspaso['created_at']))); ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="mb-0 fw-bold text-secondary text-uppercase" style="font-size:.8rem;letter-spacing:.5px;">
                    <i class="bi bi-chat-left-text me-1"></i>Observación
                </h6>
            </div>
            <div class="card-body">
                <?php if (trim((string)$traspaso['observacion']) !== ''): ?>
                    <div class="small"><?php echo nl2br(h($traspaso['observacion'])); ?></div>
                <?php else: ?>
                    <div class="text-muted fst-italic small">Sin observación registrada.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detalle de productos -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Productos Trasladados</h5>
        <span class="badge bg-light text-dark border"><?php echo $totalItems; ?> registros</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th class="px-3" style="width: 5%;">#</th>
                        <th style="width: 14%;">Código</th>
                        <th>Producto</th>
                        <th style="width: 10%;" class="text-end">Cantidad</th>
                        <th style="width: 12%;" class="text-end">Costo Unit.</th>
                        <th style="width: 12%;" class="text-end">Subtotal</th>
                        <th style="width: 11%;" class="text-end">Stock Origen</th>
                        <th style="width: 11%;" class="text-end">Stock Destino</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$detalle): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay detalle registrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php $n = 1; foreach ($detalle as $d):
                            $kO = (int)$traspaso['id_bodega_origen'] . '_' . (int)$d['id_producto'];
                            $kD = (int)$traspaso['id_bodega_destino'] . '_' . (int)$d['id_producto'];
                            $stOrigen  = isset($stockActual[$kO]) ? $stockActual[$kO] : 0;
                            $stDestino = isset($stockActual[$kD]) ? $stockActual[$kD] : 0;
                        ?>
                            <tr>
                                <td class="px-3 text-muted"><?php echo $n; ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo h($d['producto_codigo'] ? $d['producto_codigo'] : '—'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?php echo h($d['producto_nombre'] ? $d['producto_nombre'] : ($d['descripcion_item'] ? $d['descripcion_item'] : 'Producto no disponible')); ?>
                                    </div>
                                    <?php if ($d['unidad_nombre']): ?>
                                        <div class="text-muted small"><i class="bi bi-ruler me-1"></i><?php echo h($d['unidad_nombre']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold"><?php echo number_format((float)$d['cantidad'], 2, ',', '.'); ?></td>
                                <td class="text-end">$ <?php echo number_format((float)$d['costo_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-end fw-medium text-success">$ <?php echo number_format((float)$d['subtotal'], 2, ',', '.'); ?></td>
                                <td class="text-end small <?php echo $stOrigen <= 0 ? 'text-danger' : 'text-muted'; ?>">
                                    <?php echo number_format($stOrigen, 2, ',', '.'); ?>
                                </td>
                                <td class="text-end small text-success">
                                    <?php echo number_format($stDestino, 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php $n++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if ($detalle): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Total</th>
                            <th class="text-end text-success fw-bold">$ <?php echo number_format($totalTraspaso, 2, ',', '.'); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <div class="px-3 py-2 small text-muted bg-light border-top">
            <i class="bi bi-info-circle me-1"></i>
            Stock mostrado es el <strong>actual</strong> (posterior al traslado).
        </div>
    </div>
</div>

<!-- Movimientos generados -->
<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Movimientos Generados</h5>
        <span class="badge bg-light text-dark border"><?php echo count($movimientos); ?> movimientos</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th class="px-3" style="width: 8%;">ID</th>
                        <th style="width: 14%;">Tipo</th>
                        <th style="width: 20%;">Bodega</th>
                        <th>Producto</th>
                        <th style="width: 10%;" class="text-end">Cantidad</th>
                        <th style="width: 12%;" class="text-end">Precio</th>
                        <th style="width: 12%;" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$movimientos): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                No hay movimientos asociados a este traslado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td class="px-3 text-muted">#<?php echo (int)$m['id']; ?></td>
                                <td><?php echo mov_tipo_label($m['tipo_movimiento']); ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border-0">
                                        <i class="bi bi-geo-alt-fill me-1"></i>
                                        <?php echo h($m['bodega_nombre'] ? $m['bodega_nombre'] : '—'); ?>
                                    </span>
                                    <?php if ($m['bodega_codigo']): ?>
                                        <div class="text-muted small mt-1">Cód: <?php echo h($m['bodega_codigo']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo h($m['producto_nombre'] ? $m['producto_nombre'] : 'Producto'); ?></div>
                                    <?php if ($m['producto_codigo']): ?>
                                        <div class="text-muted small"><span class="badge bg-light text-dark border"><?php echo h($m['producto_codigo']); ?></span></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold <?php echo (strpos($m['tipo_movimiento'], 'entrada') !== false) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo (strpos($m['tipo_movimiento'], 'entrada') !== false ? '+' : '-'); ?><?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?>
                                </td>
                                <td class="text-end">$ <?php echo number_format((float)$m['precio_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-end fw-medium">$ <?php echo number_format((float)$m['total'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .d-print-none { display: none !important; }
    .main-content { width: 100% !important; }
    .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>