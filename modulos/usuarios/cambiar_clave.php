<?php
// modulos/usuarios/cambiar_clave.php
// Cambio de contraseña del propio usuario. Es la única página permitida
// cuando la cuenta tiene la bandera debe_cambiar_clave activa (primera vez,
// o clave restablecida por el administrador).
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claveActual   = (string)post('clave_actual');
    $claveNueva    = (string)post('clave_nueva');
    $claveConfirm  = (string)post('clave_confirm');

    // Clave actual
    $stmt = $pdo->prepare("SELECT clave_hash FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute(array((int)$_SESSION['user_id']));
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila || !password_verify($claveActual, $fila['clave_hash'])) {
        $error = 'La contraseña actual no es correcta.';
    } elseif (!validar_clave_politica($claveNueva, $error)) {
        // $error ya contiene el mensaje de la política de contraseñas
    } elseif ($claveNueva !== $claveConfirm) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } elseif (password_verify($claveNueva, $fila['clave_hash'])) {
        $error = 'La nueva contraseña no puede ser igual a la actual.';
    } else {
        $clave_hash = password_hash($claveNueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET clave_hash = ?, debe_cambiar_clave = 0 WHERE id = ?");
        $stmt->execute(array($clave_hash, (int)$_SESSION['user_id']));

        $_SESSION['debe_cambiar_clave'] = 0;

        set_flash('success', 'Contraseña actualizada correctamente.');
        redirect(BASE_URL . '/index.php');
    }
}

$pageTitle = 'Cambiar Contraseña';
require_once __DIR__ . '/../../inc/header.php';
?>

<?php ui_page_header(
    'bi-key',
    'Cambiar Contraseña',
    'Debes actualizar tu contraseña para continuar usando el sistema.',
    ''
); ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="row g-3">
                    <?php echo csrf_field(); ?>

                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Contraseña actual <span class="text-danger">*</span></label>
                        <input type="password" name="clave_actual" class="form-control" required autocomplete="current-password">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="clave_nueva" class="form-control" minlength="8" required autocomplete="new-password">
                        <div class="form-text">Mínimo 8 caracteres, con letras y números.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="clave_confirm" class="form-control" minlength="8" required autocomplete="new-password">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-lock me-1"></i> Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>
