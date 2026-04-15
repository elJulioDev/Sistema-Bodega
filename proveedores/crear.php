<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$error = '';

$rut = '';
$razon_social = '';
$nombre_fantasia = '';
$giro = '';
$direccion = '';
$comuna = '';
$ciudad = '';
$telefono = '';
$email = '';
$contacto = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = post('rut');
    $razon_social = post('razon_social');
    $nombre_fantasia = post('nombre_fantasia');
    $giro = post('giro');
    $direccion = post('direccion');
    $comuna = post('comuna');
    $ciudad = post('ciudad');
    $telefono = post('telefono');
    $email = post('email');
    $contacto = post('contacto');

    if ($rut === '' || $razon_social === '') {
        $error = 'El RUT y la raz¨®n social son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM proveedores WHERE rut = ? LIMIT 1");
        $stmt->execute([$rut]);
        $existe = $stmt->fetch();

        if ($existe) {
            $error = 'Ya existe un proveedor registrado con ese RUT.';
        } else {
            $sql = "INSERT INTO proveedores (
                        rut, razon_social, nombre_fantasia, giro, direccion,
                        comuna, ciudad, telefono, email, contacto, estado
                    ) VALUES (
                        :rut, :razon_social, :nombre_fantasia, :giro, :direccion,
                        :comuna, :ciudad, :telefono, :email, :contacto, 1
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':rut' => $rut,
                ':razon_social' => $razon_social,
                ':nombre_fantasia' => $nombre_fantasia,
                ':giro' => $giro,
                ':direccion' => $direccion,
                ':comuna' => $comuna,
                ':ciudad' => $ciudad,
                ':telefono' => $telefono,
                ':email' => $email,
                ':contacto' => $contacto
            ]);

            set_flash('success', 'Proveedor creado correctamente.');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Nuevo Proveedor';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Nuevo Proveedor</h1>

<div class="card">
    <?php if ($error !== ''): ?>
        <div class="flash flash--error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px;">
            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">RUT *</label>
                <input type="text" name="rut" value="<?php echo h($rut); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Raz¨®n social *</label>
                <input type="text" name="razon_social" value="<?php echo h($razon_social); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Nombre fantas¨ªa</label>
                <input type="text" name="nombre_fantasia" value="<?php echo h($nombre_fantasia); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Giro</label>
                <input type="text" name="giro" value="<?php echo h($giro); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div style="grid-column:1 / -1;">
                <label style="display:block; margin-bottom:6px; font-weight:700;">Direcci¨®n</label>
                <input type="text" name="direccion" value="<?php echo h($direccion); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Comuna</label>
                <input type="text" name="comuna" value="<?php echo h($comuna); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Ciudad</label>
                <input type="text" name="ciudad" value="<?php echo h($ciudad); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Tel¨¦fono</label>
                <input type="text" name="telefono" value="<?php echo h($telefono); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Email</label>
                <input type="email" name="email" value="<?php echo h($email); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Contacto</label>
                <input type="text" name="contacto" value="<?php echo h($contacto); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>
        </div>

        <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn">Guardar</button>
            <a href="index.php" class="btn btn--secondary">Volver</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>