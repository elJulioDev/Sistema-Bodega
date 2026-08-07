<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("
    SELECT f.*, b.nombre AS bodega_nombre, b.codigo AS bodega_codigo, p.rut, p.razon_social, oc.numero_oc,
    u.nombre AS creado_por
    FROM facturas f
    INNER JOIN bodegas b ON b.id = f.id_bodega
    INNER JOIN proveedores p ON p.id = f.id_proveedor
    LEFT JOIN ordenes_compra oc ON oc.id = f.id_orden_compra
    LEFT JOIN usuarios u ON u.id = f.created_by
    WHERE f.id = ?
    LIMIT 1
");
$stmt->execute(array($id));
$factura = $stmt->fetch();

if (!$factura) {
    set_flash('error', 'Factura no encontrada.');
    redirect('facturas_lista.php');
}

$stmt = $pdo->prepare("
    SELECT d.*, pr.codigo, pr.nombre AS producto_nombre, um.nombre AS unidad,
    COALESCE((SELECT stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = d.id_producto), 0) AS stock_actual
    FROM facturas_detalle d
    LEFT JOIN productos pr ON pr.id = d.id_producto
    LEFT JOIN unidades_medida um ON um.id = pr.id_unidad_medida
    WHERE d.id_factura = ?
    ORDER BY d.id ASC
");
$stmt->execute(array((int)$factura['id_bodega'], $id));
$detalle = $stmt->fetchAll();

$anulada = (strtolower($factura['estado']) === 'anulada');

$pageTitle = 'Factura N° ' . $factura['numero_factura'];
require_once __DIR__ . '/../../inc/header.php';
?>

<?php
ob_start();
if ($anulada): ?>
    <span class="badge bg-danger text-uppercase align-self-center">ANULADA</span>
<?php
endif;
ui_btn_link('Volver', 'facturas_lista.php', 'outline-secondary', 'bi-arrow-left');
if (!$anulada): ?>
    <?php ui_btn_link('Editar', 'facturas_editar.php?id=' . $id, 'outline-primary', 'bi-pencil'); ?>
    <form method="post" action="facturas_anular.php" class="d-inline"
          data-confirm="¿Anular la factura N° <?php echo h($factura['numero_factura']); ?>? Se revertirá el stock ingresado.">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i> Anular</button>
    </form>
<?php else: ?>
    <form method="post" action="facturas_anular.php" class="d-inline"
          data-confirm="¿Reactivar la factura N° <?php echo h($factura['numero_factura']); ?>? Se reingresará el stock.">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <input type="hidden" name="reactivar" value="1">
        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-arrow-counterclockwise me-1"></i> Reactivar</button>
    </form>
<?php endif; ?>
<?php
ui_page_header('bi-file-earmark-text', 'Factura N° ' . $factura['numero_factura'], 'Detalle de recepción e ingreso a bodega', ob_get_clean());
?>

<?php if ($anulada): ?>
    <div class="alert alert-danger py-2 small">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Factura anulada.</strong> El stock de los productos fue revertido en bodega. 
        Puedes reactivarla si fue un error.
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <!-- INFO RECEPCIÓN -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-body py-2 border-0">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-info-circle me-1"></i> Información de Recepción</h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted text-uppercase fw-semibold">Proveedor</div>
                        <div class="fw-semibold text-body"><?php echo h($factura['razon_social']); ?></div>
                        <small class="text-muted">RUT: <?php echo h($factura['rut']); ?></small>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted text-uppercase fw-semibold">Bodega Destino</div>
                        <div class="fw-semibold text-primary">
                            <i class="bi bi-geo-alt-fill me-1"></i><?php echo h($factura['bodega_nombre']); ?>
                            <small class="text-muted">(<?php echo h($factura['bodega_codigo']); ?>)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted text-uppercase fw-semibold">Fecha Emisión</div>
                        <div class="text-body"><?php echo date('d/m/Y', strtotime($factura['fecha_factura'])); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted text-uppercase fw-semibold">Fecha Recepción</div>
                        <div class="text-body"><?php echo $factura['fecha_recepcion'] ? date('d/m/Y', strtotime($factura['fecha_recepcion'])) : '—'; ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted text-uppercase fw-semibold">OC Referencia</div>
                        <div class="text-body"><?php echo $factura['numero_oc'] ? h($factura['numero_oc']) : '—'; ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted text-uppercase fw-semibold">Estado</div>
                        <?php 
                            $badge = 'bg-secondary bg-opacity-10 text-secondary';
                            $est = strtolower($factura['estado']);
                            if ($est === 'ingresada') $badge = 'bg-success bg-opacity-10 text-success';
                            if ($est === 'anulada') $badge = 'bg-danger bg-opacity-10 text-danger';
                            if ($est === 'borrador') $badge = 'bg-warning bg-opacity-10 text-warning';
                        ?>
                        <span class="badge <?php echo $badge; ?> text-uppercase"><?php echo h($factura['estado']); ?></span>
                    </div>
                    <?php if ($factura['observacion']): ?>
                        <div class="col-12 border-top pt-2 mt-1">
                            <div class="small text-muted text-uppercase fw-semibold">Observación</div>
                            <p class="mb-0 small text-body"><?php echo nl2br(h($factura['observacion'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TOTALES -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-body h-100">
            <div class="card-body p-3">
                <h6 class="fw-bold text-body mb-3"><i class="bi bi-calculator me-1"></i> Totales</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Neto:</span>
                    <strong>$<?php echo number_format((float)$factura['monto_neto'], 0, ',', '.'); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">IVA (19%):</span>
                    <strong>$<?php echo number_format((float)$factura['monto_iva'], 0, ',', '.'); ?></strong>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fs-5">
                    <span class="fw-bold">Total:</span>
                    <span class="fw-bold text-success">$<?php echo number_format((float)$factura['monto_total'], 0, ',', '.'); ?></span>
                </div>
                <hr class="my-2">
                <div class="small text-muted">
                    <div><i class="bi bi-person me-1"></i>Creado por: <strong><?php echo h($factura['creado_por'] ?: 'Sistema'); ?></strong></div>
                    <div><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($factura['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DETALLE -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-body py-2 border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-list-ul me-1"></i> Detalle de Productos Ingresados</h6>
        <span class="badge bg-body text-body border"><?php echo count($detalle); ?> ítem(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-md" >
                <thead class="text-secondary fs-sm" >
                    <tr>
                        <th class="px-3 py-2">PRODUCTO</th>
                        <th class="py-2">DESCRIPCIÓN</th>
                        <th class="py-2 text-center">UNIDAD</th>
                        <th class="py-2 text-end">CANTIDAD</th>
                        <th class="py-2 text-end">P. UNITARIO</th>
                        <th class="py-2 text-end">SUBTOTAL</th>
                        <?php if (!$anulada): ?>
                            <th class="px-3 py-2 text-end">STOCK ACTUAL</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td class="px-3">
                            <?php if ($d['codigo']): ?>
                                <span class="badge bg-body text-body border font-monospace small"><?php echo h($d['codigo']); ?></span>
                                <div class="fw-semibold text-body small mt-1"><?php echo h($d['producto_nombre']); ?></div>
                            <?php else: ?>
                                <span class="text-muted small">Sin producto vinculado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-secondary small"><?php echo h($d['descripcion_item']); ?></td>
                        <td class="text-center small text-muted"><?php echo h($d['unidad'] ?: '—'); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format((float)$d['cantidad'], 2, ',', '.'); ?></td>
                        <td class="text-end">$<?php echo number_format((float)$d['precio_unitario'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold text-body">$<?php echo number_format((float)$d['subtotal'], 0, ',', '.'); ?></td>
                        <?php if (!$anulada): ?>
                            <td class="px-3 text-end">
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                    <?php echo number_format((float)$d['stock_actual'], 2, ',', '.'); ?>
                                </span>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="<?php echo $anulada ? 5 : 6; ?>" class="text-end text-muted fw-bold py-2">Neto</td>
                        <td class="<?php echo $anulada ? 'text-end' : 'text-end'; ?> fw-bold">$<?php echo number_format((float)$factura['monto_neto'], 0, ',', '.'); ?></td>
                        <?php if (!$anulada): ?><td></td><?php endif; ?>
                    </tr>
                    <tr>
                        <td colspan="<?php echo $anulada ? 5 : 6; ?>" class="text-end text-muted fw-bold py-2">IVA (19%)</td>
                        <td class="text-end fw-bold">$<?php echo number_format((float)$factura['monto_iva'], 0, ',', '.'); ?></td>
                        <?php if (!$anulada): ?><td></td><?php endif; ?>
                    </tr>
                    <tr>
                        <td colspan="<?php echo $anulada ? 5 : 6; ?>" class="text-end fs-6 fw-bold py-2">Total</td>
                        <td class="text-end fs-6 fw-bold text-success">$<?php echo number_format((float)$factura['monto_total'], 0, ',', '.'); ?></td>
                        <?php if (!$anulada): ?><td></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>