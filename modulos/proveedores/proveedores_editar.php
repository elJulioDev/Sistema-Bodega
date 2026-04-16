<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ? LIMIT 1");
$stmt->execute(array($id));
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
        $error = 'El RUT y la razón social son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM proveedores WHERE rut = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($rut, $id));
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
                ':contacto' => $contacto,
                ':id' => $id
            ));

            set_flash('success', 'Proveedor actualizado correctamente.');
            redirect('proveedores_lista.php');
        }
    }

    // Refresca valores en pantalla si hubo error
    $proveedor = array_merge($proveedor, array(
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
    ));
}

$pageTitle = 'Editar Proveedor';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Proveedor</h1>
    <a href="proveedores_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" class="row g-4">
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">RUT <span class="text-danger">*</span></label>
                <input type="text" name="rut" value="<?php echo h($proveedor['rut']); ?>" class="form-control" required>
            </div>

            <div class="col-md-8">
                <label class="form-label fw-bold text-secondary">Razón Social <span class="text-danger">*</span></label>
                <input type="text" name="razon_social" value="<?php echo h($proveedor['razon_social']); ?>" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Nombre de Fantasía</label>
                <input type="text" name="nombre_fantasia" value="<?php echo h($proveedor['nombre_fantasia']); ?>" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Giro</label>
                <input type="text" name="giro" value="<?php echo h($proveedor['giro']); ?>" class="form-control">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Dirección</label>
                <input type="text" name="direccion" value="<?php echo h($proveedor['direccion']); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Comuna</label>
                <input type="text" name="comuna" value="<?php echo h($proveedor['comuna']); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Ciudad</label>
                <input type="text" name="ciudad" value="<?php echo h($proveedor['ciudad']); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Teléfono</label>
                <input type="text" name="telefono" value="<?php echo h($proveedor['telefono']); ?>" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Email</label>
                <input type="email" name="email" value="<?php echo h($proveedor['email']); ?>" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Contacto</label>
                <input type="text" name="contacto" value="<?php echo h($proveedor['contacto']); ?>" class="form-control">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="proveedores_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';