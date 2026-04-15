<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute(array($id));
$usuario_db = $stmt->fetch();

if (!$usuario_db) {
    die('Usuario no encontrado.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = post('nombre');
    $usuario = post('usuario');
    $clave = post('clave'); // Si está vacía, no se cambia
    $rol = post('rol');

    if ($nombre === '' || $usuario === '') {
        $error = 'El nombre y el usuario son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($usuario, $id));
        
        if ($stmt->fetch()) {
            $error = 'El nombre de usuario ya está en uso por otra persona.';
        } else {
            if ($clave !== '') {
                // Actualiza con nueva clave
                $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, clave_hash=?, rol=? WHERE id=?");
                $stmt->execute(array($nombre, $usuario, $clave_hash, $rol, $id));
            } else {
                // Actualiza sin tocar la clave
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol=? WHERE id=?");
                $stmt->execute(array($nombre, $usuario, $rol, $id));
            }
            
            set_flash('success', 'Usuario actualizado correctamente.');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Editar Usuario';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Usuario</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
</div>

<div class="row">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
                <?php endif; ?>

                <form method="post" class="row g-4">
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" value="<?php echo h($usuario_db['nombre']); ?>" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Nombre de Usuario <span class="text-danger">*</span></label>
                        <input type="text" name="usuario" value="<?php echo h($usuario_db['usuario']); ?>" class="form-control" required autocomplete="off">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Nueva Contraseña</label>
                        <input type="password" name="clave" class="form-control" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Rol del Sistema</label>
                        <select name="rol" class="form-select">
                            <option value="admin" <?php echo ($usuario_db['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="bodega" <?php echo ($usuario_db['rol'] === 'bodega') ? 'selected' : ''; ?>>Encargado de Bodega</option>
                            <option value="consulta" <?php echo ($usuario_db['rol'] === 'consulta') ? 'selected' : ''; ?>>Solo Consulta</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="index.php" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>