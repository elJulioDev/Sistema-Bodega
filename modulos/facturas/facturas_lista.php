<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$buscar = trim((string)get('buscar'));
$filtro_estado = get('estado', '');
$filtro_proveedor = get('proveedor', '');

// --- Estadísticas ---
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN estado = 'ingresada' THEN 1 ELSE 0 END) AS ingresadas,
        SUM(CASE WHEN estado = 'anulada' THEN 1 ELSE 0 END) AS anuladas,
        SUM(CASE WHEN estado = 'ingresada' THEN monto_total ELSE 0 END) AS monto_ingresadas
    FROM facturas
")->fetch();

// Facturas del mes actual
$stmtMes = $pdo->query("SELECT COALESCE(SUM(monto_total),0) AS total_mes FROM facturas WHERE estado='ingresada' AND YEAR(fecha_factura)=YEAR(CURDATE()) AND MONTH(fecha_factura)=MONTH(CURDATE())");
$mesActual = $stmtMes->fetch();

// Proveedores para filtro
$proveedores = $pdo->query("SELECT id, razon_social FROM proveedores WHERE estado=1 ORDER BY razon_social")->fetchAll();

$sql = "SELECT f.*, b.nombre AS bodega_nombre, p.razon_social, oc.numero_oc,
        (SELECT COUNT(*) FROM facturas_detalle WHERE id_factura = f.id) AS total_items
        FROM facturas f
        INNER JOIN bodegas b ON b.id = f.id_bodega
        INNER JOIN proveedores p ON p.id = f.id_proveedor
        LEFT JOIN ordenes_compra oc ON oc.id = f.id_orden_compra
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (f.numero_factura LIKE :buscar OR p.razon_social LIKE :buscar OR p.rut LIKE :buscar OR f.numero_oc LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
}
if ($filtro_estado !== '') {
    $sql .= " AND f.estado = :estado";
    $params[':estado'] = $filtro_estado;
}
if ($filtro_proveedor !== '') {
    $sql .= " AND f.id_proveedor = :prov";
    $params[':prov'] = (int)$filtro_proveedor;
}

$sql .= " ORDER BY f.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$facturas = $stmt->fetchAll();

$pageTitle = 'Facturas';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold"><i class="bi bi-receipt text-primary me-2"></i>Facturas de Compra</h1>
        <small class="text-muted">Registro de ingresos a bodega central</small>
    </div>
    <a href="facturas_crear.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i> Ingresar Factura</a>
</div>

<!-- ESTADÍSTICAS -->
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Total Facturas</div>
                <div class="h4 mb-0 fw-bold"><?php echo (int)$stats['total']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Ingresadas</div>
                <div class="h4 mb-0 fw-bold text-success"><?php echo (int)$stats['ingresadas']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-danger">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Anuladas</div>
                <div class="h4 mb-0 fw-bold text-danger"><?php echo (int)$stats['anuladas']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Monto del Mes</div>
                <div class="h4 mb-0 fw-bold text-info">$<?php echo number_format((float)$mesActual['total_mes'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2 px-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="N° Factura, Proveedor, RUT u OC...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="proveedor" class="form-select form-select-sm">
                    <option value="">Todos los proveedores</option>
                    <?php foreach ($proveedores as $pr): ?>
                        <option value="<?php echo (int)$pr['id']; ?>" <?php echo ($filtro_proveedor == $pr['id']) ? 'selected' : ''; ?>>
                            <?php echo h($pr['razon_social']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <option value="ingresada" <?php echo $filtro_estado === 'ingresada' ? 'selected' : ''; ?>>Ingresadas</option>
                    <option value="anulada" <?php echo $filtro_estado === 'anulada' ? 'selected' : ''; ?>>Anuladas</option>
                    <option value="borrador" <?php echo $filtro_estado === 'borrador' ? 'selected' : ''; ?>>Borrador</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                <?php if ($buscar !== '' || $filtro_estado !== '' || $filtro_proveedor !== ''): ?>
                    <a href="facturas_lista.php" class="btn btn-sm btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABLA -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="table-light text-secondary" style="font-size: 0.75rem;">
                    <tr>
                        <th class="px-3 py-2">N° FACTURA</th>
                        <th class="py-2">FECHA</th>
                        <th class="py-2">PROVEEDOR</th>
                        <th class="py-2 text-center">ÍTEMS</th>
                        <th class="py-2">BODEGA</th>
                        <th class="py-2 text-end">TOTAL</th>
                        <th class="py-2 text-center">ESTADO</th>
                        <th class="px-3 py-2 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$facturas): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No se encontraron facturas.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($facturas as $f): 
                        $est = strtolower($f['estado']);
                        $badge = 'bg-secondary bg-opacity-10 text-secondary';
                        if ($est === 'ingresada') $badge = 'bg-success bg-opacity-10 text-success';
                        if ($est === 'anulada') $badge = 'bg-danger bg-opacity-10 text-danger';
                        if ($est === 'borrador') $badge = 'bg-warning bg-opacity-10 text-warning';
                        $anulada = ($est === 'anulada');
                    ?>
                        <tr<?php echo $anulada ? ' class="text-decoration-line-through opacity-75"' : ''; ?>>
                            <td class="px-3">
                                <i class="bi bi-file-earmark-text text-primary me-1"></i>
                                <strong><?php echo h($f['numero_factura']); ?></strong>
                                <?php if (!empty($f['numero_oc'])): ?>
                                    <br><small class="text-muted">OC: <?php echo h($f['numero_oc']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small"><?php echo date('d/m/Y', strtotime($f['fecha_factura'])); ?></div>
                                <?php if ($f['fecha_recepcion']): ?>
                                    <small class="text-muted">Rec: <?php echo date('d/m/Y', strtotime($f['fecha_recepcion'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width:220px;" title="<?php echo h($f['razon_social']); ?>">
                                    <?php echo h($f['razon_social']); ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?php echo (int)$f['total_items']; ?></span>
                            </td>
                            <td>
                                <span class="small text-primary"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h($f['bodega_nombre']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                $<?php echo number_format((float)$f['monto_total'], 0, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $badge; ?> border-0 text-uppercase"><?php echo h($f['estado']); ?></span>
                            </td>
                            <td class="px-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="facturas_ver.php?id=<?php echo (int)$f['id']; ?>" class="btn btn-outline-primary" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (!$anulada): ?>
                                        <a href="facturas_editar.php?id=<?php echo (int)$f['id']; ?>" class="btn btn-outline-secondary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="facturas_anular.php?id=<?php echo (int)$f['id']; ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('¿Anular esta factura?\n\nSe revertirá el stock ingresado a bodega. Esta acción puede deshacerse reactivando manualmente.');"
                                           title="Anular">
                                            <i class="bi bi-x-circle"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="facturas_anular.php?id=<?php echo (int)$f['id']; ?>&reactivar=1" 
                                           class="btn btn-outline-success" 
                                           onclick="return confirm('¿Reactivar esta factura?\n\nSe volverá a ingresar el stock a bodega.');"
                                           title="Reactivar">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($facturas): ?>
            <div class="card-footer bg-white py-2 px-3 border-top">
                <small class="text-muted">
                    Mostrando <strong><?php echo count($facturas); ?></strong> factura(s).
                    Las facturas anuladas aparecen tachadas y no afectan el stock.
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>