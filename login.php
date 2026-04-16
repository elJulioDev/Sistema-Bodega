<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/csrf.php'; // Se incluye tu nuevo archivo CSRF

if (is_logged_in()) {
    redirect('/Bodega/index.php');
}

$error = '';
$year = date('Y'); // Definimos el año actual para el footer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validamos el token CSRF
    csrf_check();

    // 2. Usamos 'usuario' igual que en tu login.php original
    $usuario = post('usuario');
    $clave   = post('clave');

    if ($usuario === '' || $clave === '') {
        $error = 'Debes ingresar tu usuario (RUT) y contraseña.';
    } else {
        // 3. Lógica idéntica a login.php
        $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(':usuario' => $usuario));
        $row = $stmt->fetch();

        if ($row && password_verify($clave, $row['clave_hash'])) {
            // Variables que usan auth.php y algunos módulos (oc_crear, usuarios_lista)
            $_SESSION['user_id'] = (int)$row['id'];
            $_SESSION['user_nombre'] = $row['nombre'];
            $_SESSION['user_usuario'] = $row['usuario'];
            $_SESSION['user_rol'] = $row['rol'];

            // Variables que exigen index.php y header.php
            $_SESSION['usuario_id'] = (int)$row['id'];
            $_SESSION['usuario_nombre'] = $row['nombre'];
            $_SESSION['usuario_rol'] = $row['rol'];

            set_flash('success', 'Bienvenido al sistema.');
            redirect('/Bodega/index.php');
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesion | Sistema de Bodega</title>
    <link rel="stylesheet" href="static/css/login.css">
</head>
<body>

<div class="login-container">

    <div class="institution-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Municipalidad de Coltauco
    </div>

    <div class="login-card">

        <div class="card-header">
            <h1>Sistema de Bodega</h1>
            <p>Panel de Administración y Control</p>
        </div>

        <div class="card-body">

            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="usuario">RUT Funcionario</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </span>
                            <input id="usuario" class="form-control" type="text" name="usuario"
                                   placeholder="Ej: admin o 12345678-9" required autofocus
                                   value="<?php echo $error ? htmlspecialchars(isset($_POST['usuario']) ? $_POST['usuario'] : '') : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="clave">Contraseña</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </span>
                            <input id="clave" class="form-control" type="password" name="clave"
                                   placeholder="••••••••" required>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn-submit">
                    Ingresar al Sistema
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path>
                        <path d="m12 5 7 7-7 7"></path>
                    </svg>
                </button>
            </form>
        </div>

        <div class="card-footer">
            &copy; <?php echo $year; ?> <strong>Depto. de Informática</strong> — Municipalidad de Coltauco
        </div>

    </div>
</div>

</body>
</html>