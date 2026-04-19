<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';

$rut             = '';
$razon_social    = '';
$nombre_fantasia = '';
$giro            = '';
$direccion       = '';
$comuna          = '';
$ciudad          = '';
$telefono        = '';
$email           = '';
$contacto        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut             = post('rut');
    $razon_social    = post('razon_social');
    $nombre_fantasia = post('nombre_fantasia');
    $giro            = post('giro');
    $direccion       = post('direccion');
    $comuna          = post('comuna');
    $ciudad          = post('ciudad');
    $telefono        = post('telefono');
    $email           = post('email');
    $contacto        = post('contacto');

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
                ':rut'             => $rut,
                ':razon_social'    => $razon_social,
                ':nombre_fantasia' => $nombre_fantasia,
                ':giro'            => $giro,
                ':direccion'       => $direccion,
                ':comuna'          => $comuna,
                ':ciudad'          => $ciudad,
                ':telefono'        => $telefono,
                ':email'           => $email,
                ':contacto'        => $contacto
            ));

            set_flash('success', 'Proveedor creado correctamente.');
            redirect('proveedores_lista.php');
        }
    }
}

$pageTitle = 'Nuevo Proveedor';
require_once __DIR__ . '/../../inc/header.php';
?>

<style>
/* ── Sección del formulario ── */
.form-section { padding: 1.4rem 1.5rem; }
.form-section + .form-section { border-top: 1px solid #f0f2f5; }
.section-title {
    font-size: .78rem; font-weight: 700; letter-spacing: .7px;
    text-transform: uppercase; color: #9ca3af;
    display: flex; align-items: center; gap: .5rem; margin-bottom: 1.1rem;
}
.section-title::after { content: ''; flex: 1; height: 1px; background: #f0f2f5; }

/* ── Campos con icono ── */
.field-icon-wrap { position: relative; }
.field-icon-wrap .field-icon {
    position: absolute; left: .85rem; top: 50%;
    transform: translateY(-50%); color: #9ca3af;
    font-size: .95rem; pointer-events: none; z-index: 5;
}
.field-icon-wrap .form-control { padding-left: 2.5rem; }
.field-icon-wrap .form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 .2rem rgba(13,110,253,.15);
}

/* ── Labels ── */
.form-label { font-size: .83rem; font-weight: 600; color: #374151; margin-bottom: .35rem; }
.req { color: #ef4444; }
.required-note { font-size: .75rem; color: #9ca3af; margin-bottom: 1.25rem; }

/* ── Responsive ── */
@media (max-width: 575.98px) {
    .form-section { padding: 1.1rem 1rem; }
}
</style>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-truck text-primary me-2"></i>Nuevo Proveedor
        </h1>
        <small class="text-muted">Registra una nueva empresa proveedora en el sistema</small>
    </div>
    <a href="proveedores_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<?php if ($error !== ''): ?>
<div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div><?php echo h($error); ?></div>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="post" novalidate>

<div class="card shadow-sm border-0 mb-0">

    <!-- ── Sección 1: Datos Fiscales ── -->
    <div class="form-section">
        <p class="section-title"><i class="bi bi-file-earmark-text text-muted"></i> Datos Fiscales</p>
        <p class="required-note"><span class="req">*</span> Campos obligatorios</p>

        <div class="row g-3">
            <div class="col-12 col-sm-4 col-lg-3">
                <label class="form-label">RUT <span class="req">*</span></label>
                <div class="field-icon-wrap">
                    <i class="bi bi-person-vcard field-icon"></i>
                    <input type="text" name="rut" value="<?php echo h($rut); ?>"
                           class="form-control" placeholder="12.345.678-9" required autocomplete="off">
                </div>
            </div>

            <div class="col-12 col-sm-8 col-lg-5">
                <label class="form-label">Razón Social <span class="req">*</span></label>
                <div class="field-icon-wrap">
                    <i class="bi bi-building field-icon"></i>
                    <input type="text" name="razon_social" value="<?php echo h($razon_social); ?>"
                           class="form-control" placeholder="Nombre legal de la empresa" required>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-4">
                <label class="form-label">Nombre de Fantasía</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-tag field-icon"></i>
                    <input type="text" name="nombre_fantasia" value="<?php echo h($nombre_fantasia); ?>"
                           class="form-control" placeholder="Marca comercial (opcional)">
                </div>
            </div>

            <div class="col-12 col-sm-6">
                <label class="form-label">Giro Comercial</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-briefcase field-icon"></i>
                    <input type="text" name="giro" value="<?php echo h($giro); ?>"
                           class="form-control" placeholder="Ej: Venta al por mayor de insumos de oficina">
                </div>
            </div>
        </div>
    </div>

    <!-- ── Sección 2: Ubicación ── -->
    <div class="form-section">
        <p class="section-title"><i class="bi bi-geo-alt text-muted"></i> Ubicación</p>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Dirección</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-map field-icon"></i>
                    <input type="text" name="direccion" value="<?php echo h($direccion); ?>"
                           class="form-control" placeholder="Calle, número, oficina…">
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label">Comuna</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-pin-map field-icon"></i>
                    <input type="text" name="comuna" value="<?php echo h($comuna); ?>"
                           class="form-control" placeholder="Ej: Rancagua">
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label">Ciudad</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-buildings field-icon"></i>
                    <input type="text" name="ciudad" value="<?php echo h($ciudad); ?>"
                           class="form-control" placeholder="Ej: Rancagua">
                </div>
            </div>
        </div>
    </div>

    <!-- ── Sección 3: Contacto ── -->
    <div class="form-section">
        <p class="section-title"><i class="bi bi-person-lines-fill text-muted"></i> Contacto</p>

        <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label">Teléfono</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-telephone field-icon"></i>
                    <input type="text" name="telefono" value="<?php echo h($telefono); ?>"
                           class="form-control" placeholder="+56 9 1234 5678">
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label">Correo Electrónico</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-envelope field-icon"></i>
                    <input type="email" name="email" value="<?php echo h($email); ?>"
                           class="form-control" placeholder="contacto@empresa.cl">
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label">Persona de Contacto</label>
                <div class="field-icon-wrap">
                    <i class="bi bi-person field-icon"></i>
                    <input type="text" name="contacto" value="<?php echo h($contacto); ?>"
                           class="form-control" placeholder="Nombre del representante">
                </div>
            </div>
        </div>
    </div>

    <!-- ── Footer ── -->
    <div class="card-footer bg-light border-top d-flex justify-content-end gap-2 py-3 px-4">
        <a href="proveedores_lista.php" class="btn btn-light border">
            <i class="bi bi-x-lg me-1"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-floppy me-1"></i> Guardar Proveedor
        </button>
    </div>

</div>
</form>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>