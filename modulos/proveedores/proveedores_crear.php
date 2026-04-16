<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

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
        $error = 'El RUT y la razón social son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM proveedores WHERE rut = ? LIMIT 1");
        $stmt->execute(array($rut));
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
            $stmt->execute(array(
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
            ));

            set_flash('success', 'Proveedor creado correctamente.');
            redirect('proveedores_lista.php');
        }
    }
}

$pageTitle = 'Nuevo Proveedor';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-truck text-primary me-2"></i>Nuevo Proveedor</h1>
    <a href="proveedores_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?php echo h($error); ?></div>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-4">
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">RUT <span class="text-danger">*</span></label>
                <input type="text" name="rut" value="<?php echo h($rut); ?>" class="form-control" placeholder="Ej: 76.123.456-7" required>
            </div>

            <div class="col-md-8">
                <label class="form-label fw-bold text-secondary">Razón Social <span class="text-danger">*</span></label>
                <input type="text" name="razon_social" value="<?php echo h($razon_social); ?>" class="form-control" placeholder="Nombre legal de la empresa" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Nombre de Fantasía</label>
                <input type="text" name="nombre_fantasia" value="<?php echo h($nombre_fantasia); ?>" class="form-control" placeholder="Nombre comercial">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Giro</label>
                <input type="text" name="giro" value="<?php echo h($giro); ?>" class="form-control" placeholder="Actividad comercial">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Dirección</label>
                <input type="text" name="direccion" value="<?php echo h($direccion); ?>" class="form-control" placeholder="Calle y número">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Comuna</label>
                <input type="text" name="comuna" value="<?php echo h($comuna); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Ciudad</label>
                <input type="text" name="ciudad" value="<?php echo h($ciudad); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Teléfono</label>
                <input type="text" name="telefono" value="<?php echo h($telefono); ?>" class="form-control" placeholder="+56 9...">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Email</label>
                <input type="email" name="email" value="<?php echo h($email); ?>" class="form-control" placeholder="correo@empresa.com">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Contacto</label>
                <input type="text" name="contacto" value="<?php echo h($contacto); ?>" class="form-control" placeholder="Nombre del vendedor o ejecutivo">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="proveedores_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Guardar Proveedor</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';