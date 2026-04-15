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
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:linear-gradient(135deg,#e5eefb 0%,#f6f8fb 100%);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .login-box{
            width:100%;
            max-width:420px;
            background:#fff;
            border-radius:18px;
            padding:28px;
            box-shadow:0 15px 40px rgba(0,0,0,.12);
        }
        .title{
            margin:0 0 8px;
            font-size:28px;
            color:#111827;
        }
        .subtitle{
            margin:0 0 22px;
            color:#6b7280;
            font-size:14px;
        }
        .form-group{
            margin-bottom:14px;
        }
        label{
            display:block;
            font-size:14px;
            margin-bottom:6px;
            font-weight:700;
            color:#374151;
        }
        input{
            width:100%;
            padding:12px 14px;
            border:1px solid #d1d5db;
            border-radius:10px;
            font-size:14px;
        }
        .btn{
            width:100%;
            padding:12px 14px;
            background:#2563eb;
            color:#fff;
            border:none;
            border-radius:10px;
            font-size:15px;
            font-weight:700;
            cursor:pointer;
            margin-top:10px;
        }
        .error{
            background:#fee2e2;
            color:#991b1b;
            padding:12px 14px;
            border-radius:10px;
            font-size:14px;
            margin-bottom:14px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1 class="title">Sistema de Bodega</h1>
        <p class="subtitle">Ingreso al panel de administración</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" name="usuario" id="usuario" required>
            </div>

            <div class="form-group">
                <label for="clave">Contraseña</label>
                <input type="password" name="clave" id="clave" required>
            </div>

            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
</body>
</html>