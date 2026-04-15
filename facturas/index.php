<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$buscar = get('buscar');

$sql = "SELECT f.*, b.nombre AS bodega_nombre, p.razon_social, oc.numero_oc
        FROM facturas f
        INNER JOIN bodegas b ON b.id = f.id_bodega
        INNER JOIN proveedores p ON p.id = f.id_proveedor
        LEFT JOIN ordenes_compra oc ON oc.id = f.id_orden_compra
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        f.numero_factura LIKE :buscar
        OR p.razon_social LIKE :buscar
        OR p.rut LIKE :buscar
        OR b.nombre LIKE :buscar
        OR oc.numero_oc LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY f.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$facturas = $stmt->fetchAll();

$pageTitle = 'Facturas';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Facturas</h1>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; margin:0;">
        <input
            type="text"
            name="buscar"
            value="<?php echo h($buscar); ?>"
            placeholder="Buscar por factura, proveedor, bodega u OC"
            style="padding:10px 12px; min-width:320px; border:1px solid #d1d5db; border-radius:10px;"
        >
        <button type="submit" class="btn">Buscar</button>
        <?php if ($buscar !== ''): ?>
            <a href="index.php" class="btn btn--secondary">Limpiar</a>
        <?php endif; ?>
    </form>

    <a href="crear.php" class="btn">+ Nueva factura</a>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:1200px;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">ID</th>
                <th style="padding:12px 10px;">Factura</th>
                <th style="padding:12px 10px;">Fecha</th>
                <th style="padding:12px 10px;">Proveedor</th>
                <th style="padding:12px 10px;">Bodega</th>
                <th style="padding:12px 10px;">OC</th>
                <th style="padding:12px 10px;">Neto</th>
                <th style="padding:12px 10px;">IVA</th>
                <th style="padding:12px 10px;">Total</th>
                <th style="padding:12px 10px;">Estado</th>
                <th style="padding:12px 10px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$facturas): ?>
            <tr>
                <td colspan="11" style="padding:18px 10px; color:#6b7280;">No se encontraron facturas.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($facturas as $f): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:12px 10px;"><?php echo (int)$f['id']; ?></td>
                    <td style="padding:12px 10px;"><?php echo h($f['numero_factura']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($f['fecha_factura']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($f['razon_social']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($f['bodega_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($f['numero_oc']); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$f['monto_neto'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$f['monto_iva'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$f['monto_total'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($f['estado']); ?></td>
                    <td style="padding:12px 10px;">
                        <a href="ver.php?id=<?php echo (int)$f['id']; ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>