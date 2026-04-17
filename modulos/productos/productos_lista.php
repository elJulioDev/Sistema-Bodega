<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

// --- LÓGICA DE ESTADO (DESACTIVAR/ACTIVAR) ---
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE productos SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado del producto actualizado correctamente.');
    redirect('productos_lista.php');
}

// Nota: Se ha removido la lógica de eliminación física para proteger la integridad de los datos.

$buscar = get('buscar');

// Consulta con JOIN para traer nombres de categorías y unidades
$sql = "SELECT p.*, tp.nombre AS tipo_nombre, um.nombre AS unidad_nombre
        FROM productos p
        LEFT JOIN tipos_producto tp ON tp.id = p.id_tipo_producto
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        p.codigo LIKE :buscar
        OR p.nombre LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$pageTitle = 'Catálogo de Productos';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-boxes text-primary me-2"></i>Catálogo de Productos
    </h1>
    
    <div class="d-flex gap-2">
        <a href="unidades_lista.php" class="btn btn-outline-primary">
            <i class="bi bi-ruler me-1"></i> Unidades
        </a>
        <div class="vr mx-2"></div> <a href="productos_importar.php" class="btn btn-success">
            <i class="bi bi-file-earmark-arrow-up me-1"></i> Importar CSV
        </a>
        <a href="productos_crear.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Producto
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por código o nombre...">
                </div>
            </div>
            <div class="col-md-4 col-lg-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Buscar</button>
                <?php if ($buscar !== ''): ?>
                    <a href="productos_lista.php" class="btn btn-light border">Limpiar</a>
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
                        <th class="px-4 py-3">CÓDIGO</th>
                        <th class="py-3">PRODUCTO</th>
                        <th class="py-3">CATEGORÍA</th>
                        <th class="py-3">UNIDAD</th>
                        <th class="py-3 text-center">ACTIVO FIJO</th>
                        <th class="py-3 text-center">STOCK MIN.</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$productos): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">No hay productos registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productos as $p): ?>
                        <tr>
                            <td class="px-4">
                                <span class="badge bg-light text-dark border"><?php echo h($p['codigo']); ?></span>
                            </td>
                            <td class="fw-bold text-dark"><?php echo h($p['nombre']); ?></td>
                            <td>
                                <span class="text-secondary small"><?php echo h($p['tipo_nombre'] ?: 'Sin categoría'); ?></span>
                            </td>
                            <td>
                                <span class="text-secondary small text-uppercase"><?php echo h($p['unidad_nombre'] ?: 'N/A'); ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$p['activo_fijo'] === 1): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border-0">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border-0">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-muted fw-bold">
                                <?php echo number_format((float)$p['stock_minimo'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$p['estado'] === 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border-0 px-2 py-1">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border-0 px-2 py-1">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 text-end">
                                <div class="btn-group">
                                    <a href="productos_editar.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="productos_lista.php?toggle=<?php echo (int)$p['id']; ?>" 
                                       class="btn btn-sm btn-outline-<?php echo $p['estado'] ? 'warning' : 'success'; ?>" 
                                       onclick="return confirm('¿Deseas cambiar el estado de este producto?');"
                                       title="<?php echo $p['estado'] ? 'Desactivar' : 'Activar'; ?>">
                                       <i class="bi bi-<?php echo $p['estado'] ? 'power' : 'check-circle'; ?>"></i>
                                    </a>
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

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>