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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-receipt text-primary me-2"></i>Facturas de Compra</h1>
    <a href="crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Ingresar Factura</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por N° Factura, Proveedor, Bodega u OC...">
                </div>
            </div>
            <div class="col-md-4 col-lg-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Buscar</button>
                <?php if ($buscar !== ''): ?>
                    <a href="index.php" class="btn btn-light border">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary text-nowrap" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">FACTURA</th>
                        <th class="py-3">FECHA</th>
                        <th class="py-3">PROVEEDOR</th>
                        <th class="py-3">DESTINO (BODEGA)</th>
                        <th class="py-3">OC REF.</th>
                        <th class="py-3 text-end">TOTAL</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-center">ACCIÓN</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$facturas): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No se encontraron facturas registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($facturas as $f): ?>
                        <tr>
                            <td class="px-4 fw-bold text-dark"><i class="bi bi-file-earmark-text text-muted me-1"></i><?php echo h($f['numero_factura']); ?></td>
                            <td class="text-muted small"><?php echo date('d/m/Y', strtotime($f['fecha_factura'])); ?></td>
                            <td>
                                <div class="text-dark fw-medium text-truncate" style="max-width: 200px;" title="<?php echo h($f['razon_social']); ?>">
                                    <?php echo h($f['razon_social']); ?>
                                </div>
                            </td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary border-0"><?php echo h($f['bodega_nombre']); ?></span></td>
                            <td><span class="text-muted small"><?php echo h($f['numero_oc']) ?: '-'; ?></span></td>
                            <td class="text-end fw-bold text-success">$<?php echo number_format((float)$f['monto_total'], 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <?php 
                                    $est = strtolower($f['estado']);
                                    $badge = 'bg-secondary';
                                    if ($est === 'ingresada') $badge = 'bg-success';
                                    if ($est === 'anulada') $badge = 'bg-danger';
                                    if ($est === 'borrador') $badge = 'bg-warning text-dark';
                                ?>
                                <span class="badge <?php echo $badge; ?> border-0 text-uppercase"><?php echo h($f['estado']); ?></span>
                            </td>
                            <td class="px-4 text-center">
                                <a href="ver.php?id=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>