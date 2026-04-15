<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

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
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Factura N° <?php echo h($factura['numero_factura']); ?></h1>

<div class="card">
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px;">
        <div><strong>Proveedor:</strong><br><?php echo h($factura['razon_social']); ?></div>
        <div><strong>RUT:</strong><br><?php echo h($factura['rut']); ?></div>
        <div><strong>Bodega:</strong><br><?php echo h($factura['bodega_nombre']); ?></div>
        <div><strong>OC:</strong><br><?php echo h($factura['numero_oc']); ?></div>
        <div><strong>Fecha factura:</strong><br><?php echo h($factura['fecha_factura']); ?></div>
        <div><strong>Fecha recepción:</strong><br><?php echo h($factura['fecha_recepcion']); ?></div>
        <div><strong>Estado:</strong><br><?php echo h($factura['estado']); ?></div>
    </div>

    <?php if ($factura['observacion'] !== ''): ?>
        <div style="margin-top:16px;">
            <strong>Observación:</strong><br>
            <?php echo nl2br(h($factura['observacion'])); ?>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:900px;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">Producto</th>
                <th style="padding:12px 10px;">Descripción</th>
                <th style="padding:12px 10px;">Cantidad</th>
                <th style="padding:12px 10px;">Precio unitario</th>
                <th style="padding:12px 10px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($detalle as $d): ?>
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:12px 10px;">
                    <?php
                    if ($d['codigo'] || $d['producto_nombre']) {
                        echo h($d['codigo'] . ' - ' . $d['producto_nombre']);
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td style="padding:12px 10px;"><?php echo h($d['descripcion_item']); ?></td>
                <td style="padding:12px 10px;"><?php echo number_format((float)$d['cantidad'], 2, ',', '.'); ?></td>
                <td style="padding:12px 10px;"><?php echo number_format((float)$d['precio_unitario'], 0, ',', '.'); ?></td>
                <td style="padding:12px 10px;"><?php echo number_format((float)$d['subtotal'], 0, ',', '.'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="padding:12px 10px; text-align:right;"><strong>Neto</strong></td>
                <td style="padding:12px 10px;"><strong><?php echo number_format((float)$factura['monto_neto'], 0, ',', '.'); ?></strong></td>
            </tr>
            <tr>
                <td colspan="4" style="padding:12px 10px; text-align:right;"><strong>IVA</strong></td>
                <td style="padding:12px 10px;"><strong><?php echo number_format((float)$factura['monto_iva'], 0, ',', '.'); ?></strong></td>
            </tr>
            <tr>
                <td colspan="4" style="padding:12px 10px; text-align:right;"><strong>Total</strong></td>
                <td style="padding:12px 10px;"><strong><?php echo number_format((float)$factura['monto_total'], 0, ',', '.'); ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="card">
    <a href="index.php" class="btn btn--secondary">Volver</a>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>