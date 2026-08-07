<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

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
require_once __DIR__ . '/../../inc/header.php';
?>

<?php
ob_start();
ui_btn_link('Nueva OC', 'oc_crear.php', 'primary', 'bi-plus-lg');
ui_page_header('bi-cart3', 'Órdenes de Compra', '', ob_get_clean());
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-body border-end-0"><i class="bi bi-search text-secondary"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por N° OC, proveedor o unidad...">
                </div>
            </div>
            <div class="col-md-4 col-lg-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Buscar</button>
                <?php if ($buscar !== ''): ?>
                    <a href="oc_lista.php" class="btn btn-light border">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-secondary text-nowrap fs-md" >
                    <tr>
                        <th class="px-4 py-3">OC</th>
                        <th class="py-3">FECHA</th>
                        <th class="py-3">PROVEEDOR</th>
                        <th class="py-3">UNIDAD</th>
                        <th class="py-3 text-end">NETO</th>
                        <th class="py-3 text-end">TOTAL</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-center">ACCIÓN</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$ordenes): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No se encontraron órdenes de compra.</td></tr>
                <?php else: ?>
                    <?php foreach ($ordenes as $oc): ?>
                        <tr>
                            <td class="px-4 fw-bold text-body">#<?php echo h($oc['numero_oc']); ?></td>
                            <td class="text-muted small"><?php echo date('d/m/Y', strtotime($oc['fecha_oc'])); ?></td>
                            <td>
                                <div class="text-body fw-medium text-truncate mw-250" title="<?php echo h($oc['razon_social']); ?>">
                                    <?php echo h($oc['razon_social']); ?>
                                </div>
                            </td>
                            <td><span class="text-muted small"><?php echo h($oc['unidad_solicitante']) ?: '-'; ?></span></td>
                            <td class="text-end text-muted">$<?php echo number_format((float)$oc['monto_neto'], 0, ',', '.'); ?></td>
                            <td class="text-end fw-bold text-primary">$<?php echo number_format((float)$oc['monto_total'], 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <?php 
                                    $est = strtolower($oc['estado']);
                                    $badge = 'bg-secondary bg-opacity-10 text-secondary';
                                    if ($est === 'cerrada') $badge = 'bg-success bg-opacity-10 text-success';
                                    if ($est === 'pendiente') $badge = 'bg-warning bg-opacity-10 text-warning';
                                    if ($est === 'parcial') $badge = 'bg-info bg-opacity-10 text-info';
                                    if ($est === 'anulada') $badge = 'bg-danger bg-opacity-10 text-danger';
                                ?>
                                <span class="badge <?php echo $badge; ?> border-0 text-uppercase"><?php echo h($oc['estado']); ?></span>
                            </td>
                            <td class="px-4 text-center">
                                <div class="btn-group" role="group">
                                    <a href="oc_ver.php?id=<?php echo (int)$oc['id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                    <a href="oc_editar.php?id=<?php echo (int)$oc['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';