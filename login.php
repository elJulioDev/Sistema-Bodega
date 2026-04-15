<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

if (is_logged_in()) {
    redirect('/Bodega/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = post('usuario');
    $clave   = post('clave');

    if ($usuario === '' || $clave === '') {
        $error = 'Debes ingresar usuario y contraseña.';
    } else {
        $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(':usuario' => $usuario));
        $row = $stmt->fetch();

        if ($row && password_verify($clave, $row['clave_hash'])) {
            $_SESSION['user_id'] = (int)$row['id'];
            $_SESSION['user_nombre'] = $row['nombre'];
            $_SESSION['user_usuario'] = $row['usuario'];
            $_SESSION['user_rol'] = $row['rol'];

            set_flash('success', 'Bienvenido al sistema.');
            redirect('/Bodega/index.php');
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistema de Bodega</title>
    <link rel="stylesheet" href="static/css/login.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="card-header">
            <h1>Sistema de Bodega</h1>
            <p>Panel de Administración y Control</p>
        </div>

        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?php echo h($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="usuario">Nombre de Usuario</label>
                    <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Ej: admin" required autofocus>
                </div>

                <div class="form-group">
                    <label for="clave">Contraseña</label>
                    <input type="password" name="clave" id="clave" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-block">Ingresar al Sistema</button>
            </form>
        </div>
    </div>
</body>
</html>