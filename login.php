<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/csrf.php';
require_once __DIR__ . '/inc/settings.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
$year  = date('Y');

$siteNombre    = site_config('site_nombre', 'Sistema de Bodega');
$siteDescrip   = site_config('site_descripcion', 'Panel de Administración y Control');
$siteIcono     = site_config('site_icono', 'bi-box-seam');
$orgNombre     = site_config('org_nombre', '');
$orgDominio    = site_config('org_email_dominio', '');
$siteColor     = site_config('site_color', '#0d6efd');
$siteColorSec  = site_config('site_color_secundario', '#8b5cf6');
$brandDeep     = site_color_darken($siteColor, 0.18);
$brandSoft     = site_color_rgba($siteColor, 0.12);
$temaDefault   = site_config('tema_default', 'auto');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $usuario = post('usuario');
    $clave   = post('clave');

    // Throttling simple contra fuerza bruta (contador en sesión)
    $intentosFallidos = isset($_SESSION['login_intentos']) ? (int)$_SESSION['login_intentos'] : 0;
    if ($intentosFallidos >= 5) {
        sleep(2);
        $error = 'Demasiados intentos fallidos. Espera unos segundos y vuelve a intentar.';
    } elseif ($usuario === '' || $clave === '') {
        $error = 'Debes ingresar tu usuario (RUT) y contraseña.';
    } else {
        $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(':usuario' => $usuario));
        $row = $stmt->fetch();

        if ($row && password_verify($clave, $row['clave_hash'])) {
            // Limpiar contador y renovar el ID de sesión (evita fijación de sesión)
            unset($_SESSION['login_intentos']);
            session_regenerate_id(true);

            // Sesion principal usada por auth.php y modulos
            $_SESSION['user_id']             = (int)$row['id'];
            $_SESSION['user_nombre']         = $row['nombre'];
            $_SESSION['user_usuario']        = $row['usuario'];
            $_SESSION['user_rol']            = $row['rol'];
            $_SESSION['user_id_bodega']      = $row['id_bodega']      !== null ? (int)$row['id_bodega']      : 0;
            $_SESSION['user_id_unidad']      = $row['id_unidad']      !== null ? (int)$row['id_unidad']      : 0;
            $_SESSION['user_id_funcionario'] = $row['id_funcionario'] !== null ? (int)$row['id_funcionario'] : 0;

            // Alias legacy para index.php / header.php antiguo
            $_SESSION['usuario_id']     = (int)$row['id'];
            $_SESSION['usuario_nombre'] = $row['nombre'];
            $_SESSION['usuario_rol']    = $row['rol'];

            set_flash('success', 'Bienvenido al sistema.');
            redirect(BASE_URL . '/index.php');
        } else {
            $_SESSION['login_intentos'] = $intentosFallidos + 1;
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!doctype html>
<html lang="es" data-bs-theme="<?php echo $temaDefault === 'auto' ? 'light' : h($temaDefault); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión | <?php echo h($siteNombre); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo site_favicon(); ?>">
    <script>
        (function () {
            var s = '<?php echo $temaDefault === 'dark' ? 'dark' : 'light'; ?>';
            try { s = localStorage.getItem('sb_theme') || s; } catch (e) {}
            if (s === 'auto') s = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', s);
        })();
    </script>
    <style>
        :root {
            --app-brand: <?php echo h($siteColor); ?>;
            --app-brand-deep: <?php echo h($brandDeep); ?>;
            --app-brand-soft: <?php echo h($brandSoft); ?>;
            --app-accent: <?php echo h($siteColorSec); ?>;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="static/css/login.css">
</head>
<body>

<button type="button" class="theme-toggle" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
    <i class="bi bi-moon-stars" id="themeToggleIcon"></i>
</button>

<div class="login-container">

    <?php if ($orgNombre !== ''): ?>
    <div class="institution-badge">
        <i class="bi bi-building"></i>
        <?php echo h($orgNombre); ?>
    </div>
    <?php endif; ?>

    <div class="login-card">

        <div class="card-header">
            <div class="logo-wrap"><i class="<?php echo h($siteIcono); ?>"></i></div>
            <h1><?php echo h($siteNombre); ?></h1>
            <p><?php echo h($siteDescrip); ?></p>
        </div>

        <div class="card-body">

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i>
                <?php echo h($error); ?>
            </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="usuario">RUT Funcionario</label>
                        <div class="input-wrap">
                            <span class="input-icon"><i class="bi bi-person"></i></span>
                            <input id="usuario" class="form-control" type="text" name="usuario"
                                   placeholder="Ej: admin o 12345678-9" required autofocus
                                   value="<?php echo $error ? h(isset($_POST['usuario']) ? $_POST['usuario'] : '') : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="clave">Contraseña</label>
                        <div class="input-wrap">
                            <span class="input-icon"><i class="bi bi-lock"></i></span>
                            <input id="clave" class="form-control" type="password" name="clave"
                                   placeholder="••••••••" required>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn-submit">
                    Ingresar al Sistema
                    <i class="bi bi-box-arrow-in-right"></i>
                </button>
            </form>
        </div>

        <div class="card-footer">
            &copy; <?php echo $year; ?>
            <?php if ($orgNombre !== ''): ?><strong><?php echo h($orgNombre); ?></strong><?php endif; ?>
            — <?php echo h($siteNombre); ?>
        </div>

    </div>
</div>

<script>
    (function () {
        var btn = document.getElementById('themeToggle');
        var icon = document.getElementById('themeToggleIcon');
        function current() {
            var s = document.documentElement.getAttribute('data-bs-theme');
            return s === 'dark' ? 'dark' : 'light';
        }
        function sync() {
            icon.className = current() === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
        if (btn) {
            btn.addEventListener('click', function () {
                var next = current() === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', next);
                try { localStorage.setItem('sb_theme', next); } catch (e) {}
                sync();
            });
            sync();
        }
    })();
</script>

</body>
</html>
