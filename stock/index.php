<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$buscar = get('buscar');
$id_bodega = get('id_bodega');

$stmt = $pdo->query("SELECT id, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre ASC");
$bodegas = $stmt->fetchAll();

$sql = "SELECT 
            sb.*,
            b.nombre AS bodega_nombre,
            p.codigo AS producto_codigo,
            p.nombre AS producto_nombre,
            um.nombre AS unidad_nombre,
            tp.nombre AS tipo_nombre
        FROM stock_bodega sb
        INNER JOIN bodegas b ON b.id = sb.id_bodega
        INNER JOIN productos p ON p.id = sb.id_producto
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        LEFT JOIN tipos_producto tp ON tp.id = p.id_tipo_producto
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        p.codigo LIKE :buscar
        OR p.nombre LIKE :buscar
        OR b.nombre LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

if ($id_bodega !== '') {
    $sql .= " AND sb.id_bodega = :id_bodega";
    $params[':id_bodega'] = (int)$id_bodega;
}

$sql .= " ORDER BY b.nombre ASC, p.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll();

$pageTitle = 'Control de Stock';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-inboxes text-primary me-2"></i>Stock por Bodega</h1>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label text-secondary fw-bold small">Buscar producto o código</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Ej: Tuerca, PROD-01...">
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label text-secondary fw-bold small">Filtrar por Bodega</label>
                <select name="id_bodega" class="form-select">
                    <option value="">Todas las bodegas</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>" <?php echo ((string)$id_bodega === (string)$b['id']) ? 'selected' : ''; ?>>
                            <?php echo h($b['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                <?php if ($buscar !== '' || $id_bodega !== ''): ?>
                    <a href="index.php" class="btn btn-light border" title="Limpiar"><i class="bi bi-eraser"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">BODEGA</th>
                        <th class="py-3">CÓDIGO</th>
                        <th class="py-3">PRODUCTO</th>
                        <th class="py-3">TIPO / UNIDAD</th>
                        <th class="py-3 text-end">STOCK ACTUAL</th>
                        <th class="py-3 text-end">C. PROMEDIO</th>
                        <th class="px-4 py-3 text-center">ÚLTIMA ACT.</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$stocks): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No hay stock registrado con los filtros actuales.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stocks as $s): ?>
                        <tr>
                            <td class="px-4">
                                <span class="badge bg-primary bg-opacity-10 text-primary border-0"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h($s['bodega_nombre']); ?></span>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo h($s['producto_codigo']); ?></span></td>
                            <td class="fw-bold text-dark"><?php echo h($s['producto_nombre']); ?></td>
                            <td>
                                <div class="small text-secondary"><?php echo h($s['tipo_nombre'] ?: '-'); ?></div>
                                <div class="small text-muted"><i class="bi bi-ruler me-1"></i><?php echo h($s['unidad_nombre'] ?: '-'); ?></div>
                            </td>
                            <td class="text-end fw-bold fs-6 <?php echo ((float)$s['stock_actual'] <= 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format((float)$s['stock_actual'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end text-secondary fw-medium">
                                $<?php echo number_format((float)$s['costo_promedio'], 0, ',', '.'); ?>
                            </td>
                            <td class="px-4 text-center text-muted small">
                                <?php echo date('d/m/Y H:i', strtotime($s['updated_at'])); ?>
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