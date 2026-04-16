<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("
    SELECT oc.*, p.rut, p.razon_social, p.nombre_fantasia
    FROM ordenes_compra oc
    INNER JOIN proveedores p ON p.id = oc.id_proveedor
    WHERE oc.id = ?
    LIMIT 1
");
$stmt->execute(array($id));
$oc = $stmt->fetch();

if (!$oc) {
    die('Orden de compra no encontrada.');
}

$stmt = $pdo->prepare("
    SELECT d.*, pr.codigo, pr.nombre AS producto_nombre
    FROM ordenes_compra_detalle d
    LEFT JOIN productos pr ON pr.id = d.id_producto
    WHERE d.id_orden_compra = ?
    ORDER BY d.id ASC
");
$stmt->execute(array($id));
$detalle = $stmt->fetchAll();

$pageTitle = 'Ver Orden de Compra';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-cart-check text-primary me-2"></i>Orden de Compra <span class="text-primary">#<?php echo h($oc['numero_oc']); ?></span>
    </h1>
    <a href="oc_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white pt-3 pb-2 border-0">
        <h5 class="mb-0 fw-bold">Información General</h5>
    </div>
    <div class="card-body bg-light rounded-bottom">
        <div class="row g-4">
            <div class="col-md-4 col-lg-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Proveedor</span>
                <span class="fs-6 fw-medium text-dark"><?php echo h($oc['razon_social']); ?></span>
            </div>
            <div class="col-md-4 col-lg-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">RUT</span>
                <span class="fs-6 text-dark"><?php echo h($oc['rut']); ?></span>
            </div>
            <div class="col-md-4 col-lg-2">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Fecha OC</span>
                <span class="fs-6 text-dark"><?php echo date('d/m/Y', strtotime($oc['fecha_oc'])); ?></span>
            </div>
            <div class="col-md-4 col-lg-2">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Estado</span>
                <?php 
                    $est = strtolower($oc['estado']);
                    $badge = 'bg-secondary';
                    if ($est === 'cerrada') $badge = 'bg-success';
                    if ($est === 'pendiente') $badge = 'bg-warning text-dark';
                    if ($est === 'parcial') $badge = 'bg-info text-dark';
                    if ($est === 'anulada') $badge = 'bg-danger';
                ?>
                <span class="badge <?php echo $badge; ?> px-2 py-1 border-0 text-uppercase"><?php echo h($oc['estado']); ?></span>
            </div>
            <div class="col-md-4 col-lg-4">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Unidad solicitante</span>
                <span class="fs-6 text-dark"><?php echo h($oc['unidad_solicitante']) ?: '-'; ?></span>
            </div>
            <div class="col-md-4 col-lg-4">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Centro de costo</span>
                <span class="fs-6 text-dark"><?php echo h($oc['centro_costo']) ?: '-'; ?></span>
            </div>
            <?php if ($oc['descripcion'] !== ''): ?>
            <div class="col-12 border-top pt-3 mt-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Descripción</span>
                <p class="mb-0 text-dark"><?php echo nl2br(h($oc['descripcion'])); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($oc['observacion'] !== ''): ?>
            <div class="col-12 border-top pt-3 mt-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Observación Interna</span>
                <p class="mb-0 text-dark"><?php echo nl2br(h($oc['observacion'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white pt-3 pb-2 border-0">
        <h5 class="mb-0 fw-bold">Detalle de Ítems</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="py-3">Descripción</th>
                        <th class="py-3 text-end">Cantidad</th>
                        <th class="py-3 text-end">Precio Unitario</th>
                        <th class="px-4 py-3 text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td class="px-4 fw-medium text-dark">
                            <?php echo ($d['codigo'] || $d['producto_nombre']) ? h($d['codigo'] . ' - ' . $d['producto_nombre']) : '-'; ?>
                        </td>
                        <td class="text-secondary"><?php echo h($d['descripcion_item']); ?></td>
                        <td class="text-end"><?php echo number_format((float)$d['cantidad'], 2, ',', '.'); ?></td>
                        <td class="text-end">$<?php echo number_format((float)$d['precio_unitario'], 0, ',', '.'); ?></td>
                        <td class="px-4 text-end fw-medium text-dark">$<?php echo number_format((float)$d['subtotal'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-end text-muted fw-bold py-2">Neto</td>
                        <td class="px-4 text-end fw-bold text-dark">$<?php echo number_format((float)$oc['monto_neto'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end text-muted fw-bold py-2">IVA (19%)</td>
                        <td class="px-4 text-end fw-bold text-dark">$<?php echo number_format((float)$oc['monto_iva'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end text-dark fs-5 fw-bold py-3">Total</td>
                        <td class="px-4 text-end fs-5 fw-bold text-primary py-3">$<?php echo number_format((float)$oc['monto_total'], 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';