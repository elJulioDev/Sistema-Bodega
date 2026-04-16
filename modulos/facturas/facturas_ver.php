<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("
    SELECT f.*, b.nombre AS bodega_nombre, p.rut, p.razon_social, oc.numero_oc
    FROM facturas f
    INNER JOIN bodegas b ON b.id = f.id_bodega
    INNER JOIN proveedores p ON p.id = f.id_proveedor
    LEFT JOIN ordenes_compra oc ON oc.id = f.id_orden_compra
    WHERE f.id = ?
    LIMIT 1
");
$stmt->execute(array($id));
$factura = $stmt->fetch();

if (!$factura) {
    die('Factura no encontrada.');
}

$stmt = $pdo->prepare("
    SELECT d.*, pr.codigo, pr.nombre AS producto_nombre
    FROM facturas_detalle d
    LEFT JOIN productos pr ON pr.id = d.id_producto
    WHERE d.id_factura = ?
    ORDER BY d.id ASC
");
$stmt->execute(array($id));
$detalle = $stmt->fetchAll();

$pageTitle = 'Ver Factura';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-file-earmark-text text-primary me-2"></i>Factura <span class="text-primary">N° <?php echo h($factura['numero_factura']); ?></span>
    </h1>
    <a href="facturas_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white pt-3 pb-2 border-0">
        <h5 class="mb-0 fw-bold">Información de Recepción</h5>
    </div>
    <div class="card-body bg-light rounded-bottom">
        <div class="row g-4">
            <div class="col-md-4 col-lg-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Proveedor</span>
                <span class="fs-6 fw-medium text-dark"><?php echo h($factura['razon_social']); ?></span>
            </div>
            <div class="col-md-4 col-lg-2">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">RUT</span>
                <span class="fs-6 text-dark"><?php echo h($factura['rut']); ?></span>
            </div>
            <div class="col-md-4 col-lg-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Destino (Bodega)</span>
                <span class="fs-6 text-dark"><i class="bi bi-geo-alt-fill text-primary me-1"></i><?php echo h($factura['bodega_nombre']); ?></span>
            </div>
            <div class="col-md-4 col-lg-2">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">OC Referencia</span>
                <span class="fs-6 text-dark"><?php echo $factura['numero_oc'] ? h($factura['numero_oc']) : '-'; ?></span>
            </div>
            <div class="col-md-4 col-lg-2">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Fecha Emisión</span>
                <span class="fs-6 text-dark"><?php echo date('d/m/Y', strtotime($factura['fecha_factura'])); ?></span>
            </div>
            <div class="col-md-4 col-lg-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Fecha Recepción</span>
                <span class="fs-6 text-dark"><?php echo $factura['fecha_recepcion'] ? date('d/m/Y', strtotime($factura['fecha_recepcion'])) : '-'; ?></span>
            </div>
            <div class="col-md-4 col-lg-2">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Estado Interno</span>
                <?php 
                    $est = strtolower($factura['estado']);
                    $badge = 'bg-secondary';
                    if ($est === 'ingresada') $badge = 'bg-success';
                    if ($est === 'anulada') $badge = 'bg-danger';
                    if ($est === 'borrador') $badge = 'bg-warning text-dark';
                ?>
                <span class="badge <?php echo $badge; ?> px-2 py-1 border-0 text-uppercase"><?php echo h($factura['estado']); ?></span>
            </div>
            <?php if ($factura['observacion'] !== ''): ?>
            <div class="col-12 border-top pt-3 mt-3">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Observación Interna</span>
                <p class="mb-0 text-dark"><?php echo nl2br(h($factura['observacion'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white pt-3 pb-2 border-0">
        <h5 class="mb-0 fw-bold">Detalle Inventariado</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="py-3">Descripción / Glosa</th>
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
                        <td class="px-4 text-end fw-bold text-dark">$<?php echo number_format((float)$factura['monto_neto'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end text-muted fw-bold py-2">IVA (19%)</td>
                        <td class="px-4 text-end fw-bold text-dark">$<?php echo number_format((float)$factura['monto_iva'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end text-dark fs-5 fw-bold py-3">Total</td>
                        <td class="px-4 text-end fs-5 fw-bold text-success py-3">$<?php echo number_format((float)$factura['monto_total'], 0, ',', '.'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';