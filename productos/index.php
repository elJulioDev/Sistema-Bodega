<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE productos SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado del producto actualizado.');
    redirect('index.php');
}

$buscar = get('buscar');

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
        OR p.marca LIKE :buscar
        OR p.modelo LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$pageTitle = 'Productos';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-boxes text-primary me-2"></i>Catálogo de Productos</h1>
    
    <div class="d-flex gap-2">
        <a href="tipos.php" class="btn btn-outline-secondary"><i class="bi bi-tags"></i> Tipos</a>
        <a href="unidades.php" class="btn btn-outline-secondary"><i class="bi bi-rulers"></i> Unidades</a>
        <a href="crear.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Producto</a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por código, nombre, marca o modelo...">
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
                <thead class="table-light text-secondary" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">CÓDIGO</th>
                        <th class="py-3">PRODUCTO</th>
                        <th class="py-3">TIPO/UNIDAD</th>
                        <th class="py-3">MARCA/MODELO</th>
                        <th class="py-3 text-center">CONTROL STOCK</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$productos): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No se encontraron productos registrados en el sistema.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productos as $p): ?>
                        <tr>
                            <td class="px-4"><span class="badge bg-light text-dark border"><?php echo h($p['codigo']); ?></span></td>
                            <td class="fw-bold text-dark"><?php echo h($p['nombre']); ?></td>
                            <td>
                                <div class="text-secondary small"><i class="bi bi-tag-fill text-muted me-1"></i><?php echo h($p['tipo_nombre'] ?: 'Sin tipo'); ?></div>
                                <div class="text-secondary small"><i class="bi bi-ruler text-muted me-1"></i><?php echo h($p['unidad_nombre'] ?: 'Sin unidad'); ?></div>
                            </td>
                            <td>
                                <div class="text-secondary small fw-medium"><?php echo h($p['marca'] ?: '-'); ?></div>
                                <div class="text-secondary small"><?php echo h($p['modelo'] ?: '-'); ?></div>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$p['controla_stock'] === 1): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border-0"><i class="bi bi-check-circle me-1"></i>Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border-0"><i class="bi bi-x-circle me-1"></i>No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$p['estado'] === 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 border-0">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 border-0">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 text-end">
                                <div class="btn-group" role="group">
                                    <a href="editar.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?toggle=<?php echo (int)$p['id']; ?>" 
                                       class="btn btn-sm btn-outline-<?php echo $p['estado'] ? 'danger' : 'success'; ?>" 
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

<?php require_once __DIR__ . '/../inc/footer.php'; ?>