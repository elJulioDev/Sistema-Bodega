<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$proveedor = $stmt->fetch();

if (!$proveedor) {
    die('Proveedor no encontrado.');
}

$error = '';

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
        $stmt = $pdo->prepare("SELECT id FROM proveedores WHERE rut = ? AND id <> ? LIMIT 1");
        $stmt->execute([$rut, $id]);
        $existe = $stmt->fetch();

        if ($existe) {
            $error = 'Ya existe otro proveedor registrado con ese RUT.';
        } else {
            $sql = "UPDATE proveedores SET
                        rut = :rut,
                        razon_social = :razon_social,
                        nombre_fantasia = :nombre_fantasia,
                        giro = :giro,
                        direccion = :direccion,
                        comuna = :comuna,
                        ciudad = :ciudad,
                        telefono = :telefono,
                        email = :email,
                        contacto = :contacto
                    WHERE id = :id";

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
                ':contacto' => $contacto,
                ':id' => $id
            ]);

            set_flash('success', 'Proveedor actualizado correctamente.');
            redirect('index.php');
        }
    }

    // refresca valores en pantalla si hubo error
    $proveedor = array_merge($proveedor, [
        'rut' => $rut,
        'razon_social' => $razon_social,
        'nombre_fantasia' => $nombre_fantasia,
        'giro' => $giro,
        'direccion' => $direccion,
        'comuna' => $comuna,
        'ciudad' => $ciudad,
        'telefono' => $telefono,
        'email' => $email,
        'contacto' => $contacto
    ]);
}

$pageTitle = 'Editar Proveedor';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Editar Proveedor</h1>

<div class="card">
    <?php if ($error !== ''): ?>
        <div class="flash flash--error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px;">
            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">RUT *</label>
                <input type="text" name="rut" value="<?php echo h($proveedor['rut']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Raz¨®n social *</label>
                <input type="text" name="razon_social" value="<?php echo h($proveedor['razon_social']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Nombre fantas¨ªa</label>
                <input type="text" name="nombre_fantasia" value="<?php echo h($proveedor['nombre_fantasia']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Giro</label>
                <input type="text" name="giro" value="<?php echo h($proveedor['giro']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div style="grid-column:1 / -1;">
                <label style="display:block; margin-bottom:6px; font-weight:700;">Direcci¨®n</label>
                <input type="text" name="direccion" value="<?php echo h($proveedor['direccion']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Comuna</label>
                <input type="text" name="comuna" value="<?php echo h($proveedor['comuna']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Ciudad</label>
                <input type="text" name="ciudad" value="<?php echo h($proveedor['ciudad']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Tel¨¦fono</label>
                <input type="text" name="telefono" value="<?php echo h($proveedor['telefono']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Email</label>
                <input type="email" name="email" value="<?php echo h($proveedor['email']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Contacto</label>
                <input type="text" name="contacto" value="<?php echo h($proveedor['contacto']); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>
        </div>

        <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn">Guardar cambios</button>
            <a href="index.php" class="btn btn--secondary">Volver</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>