<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';
$nombre = '';
$usuario = '';
$rol = 'bodega';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = post('nombre');
    $usuario = post('usuario');
    $clave = post('clave');
    $rol = post('rol');

    if ($nombre === '' || $usuario === '' || $clave === '') {
        $error = 'Nombre, usuario y contraseña son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
        $stmt->execute(array($usuario));
        
        if ($stmt->fetch()) {
            $error = 'El nombre de usuario ya está en uso.';
        } else {
            // Encriptar contraseña
            $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, clave_hash, rol, estado) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute(array($nombre, $usuario, $clave_hash, $rol));
            
            set_flash('success', 'Usuario creado correctamente.');
            redirect('usuarios_lista.php');
        }
    }
}

$pageTitle = 'Nuevo Usuario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-person-plus text-primary me-2"></i>Nuevo Usuario</h1>
    <a href="usuarios_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
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
                        <input type="text" name="nombre" value="<?php echo h($nombre); ?>" class="form-control" placeholder="Ej: Juan Pérez" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Nombre de Usuario <span class="text-danger">*</span></label>
                        <input type="text" name="usuario" value="<?php echo h($usuario); ?>" class="form-control" placeholder="Para iniciar sesión" required autocomplete="new-password">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="clave" class="form-control" placeholder="Mínimo 6 caracteres" required autocomplete="new-password">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Rol del Sistema</label>
                        <select name="rol" class="form-select">
                            <option value="admin" <?php echo ($rol === 'admin') ? 'selected' : ''; ?>>Administrador (Acceso total)</option>
                            <option value="bodega" <?php echo ($rol === 'bodega') ? 'selected' : ''; ?>>Encargado de Bodega</option>
                            <option value="consulta" <?php echo ($rol === 'consulta') ? 'selected' : ''; ?>>Solo Consulta</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="usuarios_lista.php" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Guardar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';