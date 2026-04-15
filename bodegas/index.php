<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

// 1. PRIMERO PROCESAMOS LA LÓGICA DE REDIRECCIÓN
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->exec("UPDATE bodegas SET estado = IF(estado=1,0,1) WHERE id = {$id}");
    set_flash('success', 'Estado actualizado');
    redirect('index.php'); // Ahora la redirección funcionará sin error
}

// 2. LUEGO CARGAMOS LA VISTA Y EL HEADER
$pageTitle = 'Bodegas';
require_once __DIR__ . '/../inc/header.php';

$stmt = $pdo->query("SELECT * FROM bodegas ORDER BY id DESC");
$bodegas = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-buildings text-primary me-2"></i>Gestión de Bodegas</h1>
    <a href="crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nueva Bodega</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">CÓDIGO</th>
                        <th class="py-3">NOMBRE</th>
                        <th class="py-3">RESPONSABLE</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$bodegas): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No hay bodegas registradas en el sistema.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bodegas as $b): ?>
                        <tr>
                            <td class="px-4 fw-medium text-muted"><?php echo $b['id']; ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo h($b['codigo']); ?></span></td>
                            <td class="fw-bold text-dark"><?php echo h($b['nombre']); ?></td>
                            <td>
                                <?php if($b['responsable']): ?>
                                    <i class="bi bi-person me-1 text-muted"></i> <?php echo h($b['responsable']); ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($b['estado']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 border-0">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 border-0">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 text-end">
                                <div class="btn-group" role="group">
                                    <a href="editar.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?toggle=<?php echo $b['id']; ?>" 
                                       class="btn btn-sm btn-outline-<?php echo $b['estado'] ? 'danger' : 'success'; ?>" 
                                       title="<?php echo $b['estado'] ? 'Desactivar' : 'Activar'; ?>"
                                       onclick="return confirm('¿Deseas cambiar el estado de esta bodega?');">
                                        <i class="bi bi-<?php echo $b['estado'] ? 'power' : 'check-circle'; ?>"></i>
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