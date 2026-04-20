<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

// --- LÓGICA DE ESTADO (DESACTIVAR/ACTIVAR) ---
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE productos SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado del producto actualizado correctamente.');
    redirect('productos_lista.php');
}

$buscar = trim((string)get('buscar'));
$filtro_tipo = get('tipo', '');    // activo | consumo | ''
$filtro_estado = get('estado', ''); // 1 | 0 | ''

// Estadísticas generales
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) AS inactivos,
        SUM(CASE WHEN activo_fijo = 1 THEN 1 ELSE 0 END) AS activos_fijos
    FROM productos
")->fetch();

// Consulta principal
$sql = "SELECT p.*, um.nombre AS unidad_nombre, um.codigo AS unidad_codigo,
        COALESCE((SELECT SUM(stock_actual) FROM stock_bodega WHERE id_producto = p.id), 0) AS stock_total
        FROM productos p
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (p.codigo LIKE :buscar OR p.nombre LIKE :buscar OR p.descripcion LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
}

if ($filtro_tipo === 'activo') {
    $sql .= " AND p.activo_fijo = 1";
} elseif ($filtro_tipo === 'consumo') {
    $sql .= " AND p.activo_fijo = 0";
}

if ($filtro_estado === '1' || $filtro_estado === '0') {
    $sql .= " AND p.estado = :estado";
    $params[':estado'] = (int)$filtro_estado;
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$pageTitle = 'Catálogo de Productos';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold"><i class="bi bi-boxes text-primary me-2"></i>Catálogo de Productos</h1>
        <small class="text-muted">Gestiona todos los productos del sistema</small>
    </div>
    <div class="d-flex gap-2">
        <a href="unidades_lista.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-ruler me-1"></i> Unidades
        </a>
        <a href="productos_importar.php" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-arrow-up me-1"></i> Importar CSV
        </a>
        <a href="productos_crear.php" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Producto
        </a>
    </div>
</div>

<!-- TARJETAS DE ESTADÍSTICAS -->
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Total</div>
                <div class="h4 mb-0 fw-bold"><?php echo (int)$stats['total']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Activos</div>
                <div class="h4 mb-0 fw-bold text-success"><?php echo (int)$stats['activos']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-danger">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Inactivos</div>
                <div class="h4 mb-0 fw-bold text-danger"><?php echo (int)$stats['inactivos']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Activos Fijos</div>
                <div class="h4 mb-0 fw-bold text-info"><?php echo (int)$stats['activos_fijos']; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2 px-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por código, nombre o descripción...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos los tipos</option>
                    <option value="consumo" <?php echo $filtro_tipo === 'consumo' ? 'selected' : ''; ?>>Consumibles</option>
                    <option value="activo" <?php echo $filtro_tipo === 'activo' ? 'selected' : ''; ?>>Activos Fijos</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <option value="1" <?php echo $filtro_estado === '1' ? 'selected' : ''; ?>>Activos</option>
                    <option value="0" <?php echo $filtro_estado === '0' ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                <?php if ($buscar !== '' || $filtro_tipo !== '' || $filtro_estado !== ''): ?>
                    <a href="productos_lista.php" class="btn btn-sm btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
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
                        <th class="px-3 py-2">CÓDIGO</th>
                        <th class="py-2">PRODUCTO</th>
                        <th class="py-2 text-center">UNIDAD</th>
                        <th class="py-2 text-center">TIPO</th>
                        <th class="py-2 text-end">STOCK</th>
                        <th class="py-2 text-end">STOCK MÍN.</th>
                        <th class="py-2 text-center">ESTADO</th>
                        <th class="px-3 py-2 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$productos): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No hay productos que coincidan con los filtros.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productos as $p): 
                        $stock = (float)$p['stock_total'];
                        $stockMin = (float)$p['stock_minimo'];
                        $bajoStock = ($stockMin > 0 && $stock <= $stockMin);
                    ?>
                        <tr<?php echo $bajoStock ? ' class="table-warning"' : ''; ?>>
                            <td class="px-3">
                                <span class="badge bg-light text-dark border font-monospace"><?php echo h($p['codigo']); ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo h($p['nombre']); ?></div>
                                <?php if (!empty($p['descripcion'])): ?>
                                    <small class="text-muted d-block text-truncate" style="max-width: 280px;"><?php echo h($p['descripcion']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($p['unidad_nombre']): ?>
                                    <span class="small text-dark"><?php echo h($p['unidad_nombre']); ?></span>
                                    <br><small class="text-muted">(<?php echo h($p['unidad_codigo']); ?>)</small>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$p['activo_fijo'] === 1): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-building me-1"></i>Activo Fijo</span>
                                <?php else: ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-basket me-1"></i>Consumible</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold">
                                <?php if ($bajoStock): ?>
                                    <span class="text-danger" title="Stock bajo el mínimo">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <?php echo number_format($stock, 2, ',', '.'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-dark"><?php echo number_format($stock, 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-muted">
                                <?php echo number_format($stockMin, 2, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$p['estado'] === 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border-0">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border-0">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="productos_editar.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="productos_lista.php?toggle=<?php echo (int)$p['id']; ?>" 
                                       class="btn btn-outline-<?php echo $p['estado'] ? 'warning' : 'success'; ?>" 
                                       onclick="return confirm('¿Deseas cambiar el estado de este producto?');"
                                       title="<?php echo $p['estado'] ? 'Desactivar' : 'Activar'; ?>">
                                       <i class="bi bi-<?php echo $p['estado'] ? 'pause-circle' : 'check-circle'; ?>"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($productos): ?>
            <div class="card-footer bg-white py-2 px-3 border-top">
                <small class="text-muted">
                    Mostrando <strong><?php echo count($productos); ?></strong> producto(s)
                    <?php if ($buscar !== '' || $filtro_tipo !== '' || $filtro_estado !== ''): ?>
                        con los filtros aplicados
                    <?php endif; ?>
                    &middot; Las filas en amarillo indican <strong class="text-warning">stock bajo el mínimo</strong>.
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>