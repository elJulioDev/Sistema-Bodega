<?php
// modulos/funcionarios/funcionarios_crear.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';

// Valores por defecto
$codigo = $rut = $nombre = $cargo = $programa = $email = '';
$id_unidad    = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Datos funcionario
    $codigo     = trim((string)post('codigo'));
    $rut        = trim((string)post('rut'));
    $nombre     = trim((string)post('nombre'));
    $id_unidad  = (int)post('id_unidad');
    $cargo      = trim((string)post('cargo'));
    $programa   = trim((string)post('programa'));
    $email      = trim((string)post('email'));

    if ($rut === '' || $nombre === '') {
        $error = 'RUT y nombre son obligatorios.';
    } else {
        // Validar RUT duplicado en funcionarios
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE rut = ? LIMIT 1");
        $stmt->execute(array($rut));
        if ($stmt->fetch()) {
            $error = 'Ya existe un funcionario con ese RUT.';
        } else {
            try {
                $pdo->beginTransaction();

                // INSERT funcionario
                $stmt = $pdo->prepare("
                    INSERT INTO funcionarios (codigo, rut, nombre, id_unidad, cargo, programa, email, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute(array(
                    $codigo,
                    $rut,
                    $nombre,
                    $id_unidad > 0 ? $id_unidad : null,
                    $cargo,
                    $programa,
                    $email !== '' ? $email : null
                ));
                $idFuncionario = (int)$pdo->lastInsertId();

                // Auto-crear acceso con rol solicitante si el RUT no existe aún como usuario
                $stmtChkU = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
                $stmtChkU->execute(array($rut));
                $usuarioAutoMsg = '';
                if (!$stmtChkU->fetch()) {
                    $clave_auto = ($codigo !== '') ? $codigo : $rut;
                    $clave_hash = password_hash($clave_auto, PASSWORD_BCRYPT);
                    $stmtU = $pdo->prepare("
                        INSERT INTO usuarios
                            (id_funcionario, nombre, email, usuario, clave_hash, rol, id_unidad, id_bodega, estado)
                        VALUES (?, ?, ?, ?, ?, 'solicitante', ?, NULL, 1)
                    ");
                    $stmtU->execute(array(
                        $idFuncionario,
                        $nombre,
                        $email !== '' ? $email : null,
                        $rut,
                        $clave_hash,
                        $id_unidad > 0 ? $id_unidad : null
                    ));
                    $usuarioAutoMsg = ' con acceso al sistema (rol Solicitante, contraseña: código)';
                }

                $pdo->commit();
                set_flash('success', 'Funcionario creado correctamente' . $usuarioAutoMsg . '.');
                redirect('funcionarios_lista.php');

            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Error al crear: ' . $e->getMessage();
            }
        }
    }
}

// Datos para selectores
$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$pageTitle = 'Nuevo Funcionario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-person-plus text-primary me-2"></i>Nuevo Funcionario
    </h1>
    <a href="funcionarios_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
    </div>
<?php endif; ?>

<form method="post" id="formFuncionario">

<div class="row g-3">
    <div class="col-lg-8">

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-person-vcard text-primary me-2"></i>Datos del funcionario
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary">Código RRHH</label>
                        <input type="text" name="codigo" value="<?php echo h($codigo); ?>" class="form-control" placeholder="Ej: 001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary">RUT <span class="text-danger">*</span></label>
                        <input type="text" name="rut" id="inpRut" value="<?php echo h($rut); ?>" class="form-control" placeholder="12345678-9" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary">Email</label>
                        <input type="email" name="email" value="<?php echo h($email); ?>" class="form-control" placeholder="correo@coltauco.cl">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" value="<?php echo h($nombre); ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Unidad organizacional</label>
                        <select name="id_unidad" id="selUnidadFunc" class="form-select">
                            <option value="">— Sin unidad asignada —</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad === (int)$u['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Cargo</label>
                        <input type="text" name="cargo" value="<?php echo h($cargo); ?>" class="form-control" placeholder="Ej: Administrativo">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Programa / Proyecto</label>
                        <input type="text" name="programa" value="<?php echo h($programa); ?>" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="alert alert-success mb-0 d-flex align-items-start gap-3">
                    <i class="bi bi-shield-check fs-4 text-success mt-1"></i>
                    <div>
                        <div class="fw-bold mb-1">Acceso automático habilitado</div>
                        <div class="small text-muted">
                            Al guardar, el funcionario recibirá acceso al sistema con rol
                            <strong>Solicitante</strong>. El usuario será su <strong>RUT</strong>
                            y la contraseña inicial será su <strong>código</strong>
                            (o el RUT si no tiene código asignado).
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-info-circle me-2"></i>Roles del sistema
                </h6>
                <div class="mb-3">
                    <span class="badge bg-danger bg-opacity-10 text-danger mb-1 border border-danger-subtle">ADMINISTRADOR</span>
                    <p class="small text-muted mb-0">Acceso total. No requiere bodega ni unidad.</p>
                </div>
                <div class="mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-1 border border-primary-subtle">ENCARGADO</span>
                    <p class="small text-muted mb-0">Gestiona una bodega. Puede registrar consumos y traslados.</p>
                </div>
                <div class="mb-0">
                    <span class="badge bg-info bg-opacity-10 text-info mb-1 border border-info-subtle">SOLICITANTE</span>
                    <p class="small text-muted mb-0">Solicita insumos desde cualquier bodega hacia su unidad.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
    <a href="funcionarios_lista.php" class="btn btn-light border">Cancelar</a>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-floppy me-1"></i> Guardar funcionario
    </button>
</div>

</form>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>