<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$buscar = get('buscar');

$sql = "SELECT oc.*, p.razon_social
        FROM ordenes_compra oc
        INNER JOIN proveedores p ON p.id = oc.id_proveedor
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        oc.numero_oc LIKE :buscar
        OR p.razon_social LIKE :buscar
        OR p.rut LIKE :buscar
        OR oc.unidad_solicitante LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY oc.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll();

$pageTitle = 'Órdenes de Compra';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Órdenes de Compra</h1>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; margin:0;">
        <input
            type="text"
            name="buscar"
            value="<?php echo h($buscar); ?>"
            placeholder="Buscar por OC, proveedor o unidad"
            style="padding:10px 12px; min-width:300px; border:1px solid #d1d5db; border-radius:10px;"
        >
        <button type="submit" class="btn">Buscar</button>
        <?php if ($buscar !== ''): ?>
            <a href="index.php" class="btn btn--secondary">Limpiar</a>
        <?php endif; ?>
    </form>

    <a href="crear.php" class="btn">+ Nueva OC</a>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:1100px;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">ID</th>
                <th style="padding:12px 10px;">Número OC</th>
                <th style="padding:12px 10px;">Fecha</th>
                <th style="padding:12px 10px;">Proveedor</th>
                <th style="padding:12px 10px;">Unidad</th>
                <th style="padding:12px 10px;">Neto</th>
                <th style="padding:12px 10px;">IVA</th>
                <th style="padding:12px 10px;">Total</th>
                <th style="padding:12px 10px;">Estado</th>
                <th style="padding:12px 10px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$ordenes): ?>
            <tr>
                <td colspan="10" style="padding:18px 10px; color:#6b7280;">No se encontraron órdenes de compra.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($ordenes as $oc): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:12px 10px;"><?php echo (int)$oc['id']; ?></td>
                    <td style="padding:12px 10px;"><?php echo h($oc['numero_oc']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($oc['fecha_oc']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($oc['razon_social']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($oc['unidad_solicitante']); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$oc['monto_neto'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$oc['monto_iva'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$oc['monto_total'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($oc['estado']); ?></td>
                    <td style="padding:12px 10px;">
                        <a href="ver.php?id=<?php echo (int)$oc['id']; ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>