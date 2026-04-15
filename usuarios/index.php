<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
require_role('admin');

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    // Evitar que el usuario se desactive a sí mismo
    if ($id === (int)$_SESSION['user_id']) {
        set_flash('error', 'No puedes desactivar tu propia cuenta.');
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET estado = IF(estado=1,0,1) WHERE id = ?");
        $stmt->execute(array($id));
        set_flash('success', 'Estado del usuario actualizado.');
    }
    redirect('index.php');
}

$stmt = $pdo->query("SELECT id, nombre, usuario, rol, estado FROM usuarios ORDER BY id DESC");
$usuarios = $stmt->fetchAll();

$pageTitle = 'Usuarios';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-people text-primary me-2"></i>Gestión de Usuarios</h1>
    <a href="crear.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Nuevo Usuario</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">NOMBRE</th>
                        <th class="py-3">USUARIO</th>
                        <th class="py-3 text-center">ROL</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$usuarios): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No hay usuarios registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="px-4 fw-medium text-muted"><?php echo (int)$u['id']; ?></td>
                            <td class="fw-bold text-dark"><?php echo h($u['nombre']); ?></td>
                            <td><span class="text-muted"><i class="bi bi-person-circle me-1"></i><?php echo h($u['usuario']); ?></span></td>
                            <td class="text-center">
                                <?php if (strtolower($u['rol']) === 'admin'): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border-0 px-2 py-1">Administrador</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border-0 px-2 py-1 text-uppercase"><?php echo h($u['rol']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$u['estado'] === 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 border-0">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 border-0">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 text-end">
                                <div class="btn-group" role="group">
                                    <a href="editar.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <a href="index.php?toggle=<?php echo (int)$u['id']; ?>" 
                                       class="btn btn-sm btn-outline-<?php echo $u['estado'] ? 'danger' : 'success'; ?>" 
                                       onclick="return confirm('¿Deseas cambiar el estado de este usuario?');"
                                       title="<?php echo $u['estado'] ? 'Desactivar' : 'Activar'; ?>">
                                       <i class="bi bi-<?php echo $u['estado'] ? 'power' : 'check-circle'; ?>"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled title="No puedes desactivarte a ti mismo"><i class="bi bi-power"></i></button>
                                    <?php endif; ?>
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