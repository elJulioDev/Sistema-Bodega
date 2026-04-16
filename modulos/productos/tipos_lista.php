<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE tipos_producto SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado actualizado.');
    redirect('tipos.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id', 0);
    $nombre = post('nombre');
    $descripcion = post('descripcion');

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT id FROM tipos_producto WHERE nombre = ? AND id <> ? LIMIT 1");
            $stmt->execute(array($nombre, $id));
            $existe = $stmt->fetch();

            if ($existe) {
                $error = 'Ya existe otro tipo con ese nombre.';
            } else {
                $stmt = $pdo->prepare("UPDATE tipos_producto SET nombre = ?, descripcion = ? WHERE id = ?");
                $stmt->execute(array($nombre, $descripcion, $id));
                set_flash('success', 'Tipo actualizado correctamente.');
                redirect('tipos.php');
            }
        } else {
            $stmt = $pdo->prepare("SELECT id FROM tipos_producto WHERE nombre = ? LIMIT 1");
            $stmt->execute(array($nombre));
            $existe = $stmt->fetch();

            if ($existe) {
                $error = 'Ya existe un tipo con ese nombre.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tipos_producto (nombre, descripcion, estado) VALUES (?, ?, 1)");
                $stmt->execute(array($nombre, $descripcion));
                set_flash('success', 'Tipo creado correctamente.');
                redirect('tipos.php');
            }
        }
    }
}

$editando = null;
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM tipos_producto WHERE id = ? LIMIT 1");
    $stmt->execute(array($idEditar));
    $editando = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM tipos_producto ORDER BY nombre ASC");
$tipos = $stmt->fetchAll();

$pageTitle = 'Tipos de Producto';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-tags text-primary me-2"></i>Tipos de Producto</h1>
    <a href="productos_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver a Productos</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white pt-3 pb-2 border-0">
        <h5 class="mb-0 fw-bold"><?php echo $editando ? 'Editar Tipo' : 'Nuevo Tipo'; ?></h5>
    </div>
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="id" value="<?php echo $editando ? (int)$editando['id'] : 0; ?>">

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" value="<?php echo h(isset($editando['nombre']) ? $editando['nombre'] : ''); ?>" class="form-control" placeholder="Ej: Herramientas" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Descripción</label>
                <input type="text" name="descripcion" value="<?php echo h(isset($editando['descripcion']) ? $editando['descripcion'] : ''); ?>" class="form-control" placeholder="Opcional">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-floppy me-1"></i> <?php echo $editando ? 'Guardar' : 'Agregar'; ?>
                </button>
                <?php if ($editando): ?>
                    <a href="tipos.php" class="btn btn-light border" title="Cancelar"><i class="bi bi-x-lg"></i></a>
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
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">NOMBRE</th>
                        <th class="py-3">DESCRIPCIÓN</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tipos as $t): ?>
                    <tr>
                        <td class="px-4 fw-medium text-muted"><?php echo (int)$t['id']; ?></td>
                        <td class="fw-bold"><?php echo h($t['nombre']); ?></td>
                        <td><span class="text-muted"><?php echo h($t['descripcion']) ?: '-'; ?></span></td>
                        <td class="text-center">
                            <?php if ((int)$t['estado'] === 1): ?>
                                <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 border-0">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 border-0">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 text-end">
                            <div class="btn-group" role="group">
                                <a href="tipos.php?editar=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                <a href="tipos.php?toggle=<?php echo (int)$t['id']; ?>" 
                                   class="btn btn-sm btn-outline-<?php echo $t['estado'] ? 'danger' : 'success'; ?>" 
                                   onclick="return confirm('¿Deseas cambiar el estado?');" title="<?php echo $t['estado'] ? 'Desactivar' : 'Activar'; ?>">
                                   <i class="bi bi-<?php echo $t['estado'] ? 'power' : 'check-circle'; ?>"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';