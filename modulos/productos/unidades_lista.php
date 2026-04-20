<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';

// --- LÓGICA PARA ACTIVAR/DESACTIVAR ---
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE unidades_medida SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado de la unidad actualizado correctamente.');
    redirect('unidades_lista.php');
}

// --- LÓGICA PARA GUARDAR (CREAR O EDITAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id', 0);
    $codigo = trim(post('codigo'));
    $nombre = trim(post('nombre'));
    $descripcion = trim(post('descripcion'));

    if ($codigo === '' || $nombre === '') {
        $error = 'El código y el nombre de la unidad son obligatorios.';
    } else {
        // Validar duplicados (mismo código o nombre en otra ID)
        $stmt = $pdo->prepare("SELECT id FROM unidades_medida WHERE (codigo = ? OR nombre = ?) AND id <> ? LIMIT 1");
        $stmt->execute(array($codigo, $nombre, $id));
        if ($stmt->fetch()) {
            $error = 'Ya existe otra unidad con ese mismo código o nombre.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE unidades_medida SET codigo = ?, nombre = ?, descripcion = ? WHERE id = ?");
                $stmt->execute(array($codigo, $nombre, $descripcion, $id));
                set_flash('success', 'Unidad de medida actualizada.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO unidades_medida (codigo, nombre, descripcion, estado) VALUES (?, ?, ?, 1)");
                $stmt->execute(array($codigo, $nombre, $descripcion));
                set_flash('success', 'Nueva unidad de medida creada.');
            }
            redirect('unidades_lista.php');
        }
    }
}

// --- LÓGICA PARA CARGAR DATOS EN EL FORMULARIO SI SE VA A EDITAR ---
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM unidades_medida WHERE id = ?");
    $stmt->execute(array((int)$_GET['editar']));
    $editando = $stmt->fetch();
}

$unidades = $pdo->query("SELECT * FROM unidades_medida ORDER BY nombre ASC")->fetchAll();

$pageTitle = 'Unidades de Medida';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-ruler text-primary me-2"></i>Unidades de Medida</h1>
    <a href="productos_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver a Productos</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 border-0">
                <h5 class="fw-bold mb-0"><?php echo $editando ? 'Editar Unidad' : 'Nueva Unidad'; ?></h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger small shadow-sm"><?php echo h($error); ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $editando ? (int)$editando['id'] : 0; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" class="form-control" value="<?php echo $editando ? h($editando['codigo']) : ''; ?>" placeholder="Ej: UN, KG, CJ" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo $editando ? h($editando['nombre']) : ''; ?>" placeholder="Ej: Unidad, Kilogramo" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"><?php echo $editando ? h($editando['descripcion']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-floppy me-1"></i> <?php echo $editando ? 'Guardar Cambios' : 'Crear Unidad'; ?>
                    </button>
                    
                    <?php if ($editando): ?>
                        <a href="unidades_lista.php" class="btn btn-light border w-100 mt-2">Cancelar Edición</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="px-4 py-3">CÓDIGO</th>
                                <th class="py-3">NOMBRE</th>
                                <th class="py-3 text-center">ESTADO</th>
                                <th class="px-4 py-3 text-end">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$unidades): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No hay unidades registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($unidades as $u): ?>
                                <tr>
                                    <td class="px-4"><span class="badge bg-light text-dark border"><?php echo h($u['codigo']); ?></span></td>
                                    <td class="fw-bold text-dark"><?php echo h($u['nombre']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $u['estado'] ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $u['estado'] ? 'text-success' : 'text-danger'; ?> border-0">
                                            <?php echo $u['estado'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 text-end">
                                        <div class="btn-group">
                                            <a href="?editar=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?toggle=<?php echo $u['id']; ?>" 
                                               class="btn btn-sm btn-outline-<?php echo $u['estado'] ? 'danger' : 'success'; ?>" 
                                               onclick="return confirm('¿Deseas cambiar el estado de esta unidad?');"
                                               title="<?php echo $u['estado'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="bi bi-<?php echo $u['estado'] ? 'power' : 'check-circle'; ?>"></i>
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
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>