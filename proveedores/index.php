<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

// activar / desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    $stmt = $pdo->prepare("UPDATE proveedores SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));

    set_flash('success', 'Estado del proveedor actualizado.');
    redirect('index.php');
}

$buscar = get('buscar');

$sql = "SELECT * FROM proveedores WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        rut LIKE :buscar
        OR razon_social LIKE :buscar
        OR nombre_fantasia LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proveedores = $stmt->fetchAll();

$pageTitle = 'Proveedores';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-truck text-primary me-2"></i>Directorio de Proveedores</h1>
    <a href="crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo Proveedor</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por RUT, Razón Social o Fantasía...">
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
                        <th class="px-4 py-3">RUT</th>
                        <th class="py-3">RAZÓN SOCIAL</th>
                        <th class="py-3">FANTASÍA</th>
                        <th class="py-3">COMUNA</th>
                        <th class="py-3">CONTACTO</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$proveedores): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No se encontraron proveedores.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proveedores as $p): ?>
                        <tr>
                            <td class="px-4"><span class="badge bg-light text-dark border"><?php echo h($p['rut']); ?></span></td>
                            <td class="fw-bold text-dark"><?php echo h($p['razon_social']); ?></td>
                            <td><?php echo h($p['nombre_fantasia']) ?: '-'; ?></td>
                            <td><?php echo h($p['comuna']) ?: '-'; ?></td>
                            <td>
                                <div class="text-secondary small"><i class="bi bi-telephone-fill me-1"></i><?php echo h($p['telefono']) ?: 'S/N'; ?></div>
                                <div class="text-secondary small"><i class="bi bi-envelope-fill me-1"></i><?php echo h($p['email']) ?: 'S/E'; ?></div>
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
                                    <a href="?toggle=<?php echo (int)$p['id']; ?>" 
                                       class="btn btn-sm btn-outline-<?php echo $p['estado'] ? 'danger' : 'success'; ?>" 
                                       onclick="return confirm('¿Deseas cambiar el estado de este proveedor?');"
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