<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');
if ($id <= 0) {
    set_flash('error', 'ID inválido.');
    redirect('unidades_lista.php');
}

$stmt = $pdo->prepare("SELECT * FROM unidades_organizacionales WHERE id = ? LIMIT 1");
$stmt->execute(array($id));
$unidad = $stmt->fetch();

if (!$unidad) {
    set_flash('error', 'Unidad no encontrada.');
    redirect('unidades_lista.php');
}

$error  = '';
$codigo = $unidad['codigo'];
$nombre = $unidad['nombre'];

// Vínculos dependientes
$stmtF = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE id_unidad = ?");
$stmtF->execute(array($id));
$totalFuncionarios = (int)$stmtF->fetchColumn();

$stmtU = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_unidad = ?");
$stmtU->execute(array($id));
$totalUsuarios = (int)$stmtU->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim((string)post('codigo')));
    $nombre = trim((string)post('nombre'));

    if ($codigo === '') {
        $error = 'El código es obligatorio.';
    } elseif (strlen($codigo) > 20) {
        $error = 'El código no puede superar los 20 caracteres.';
    } elseif ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM unidades_organizacionales WHERE codigo = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($codigo, $id));
        if ($stmt->fetch()) {
            $error = 'Ya existe otra unidad con ese código.';
        } else {
            $pdo->prepare("
                UPDATE unidades_organizacionales
                SET codigo = ?, nombre = ?
                WHERE id = ?
            ")->execute(array($codigo, $nombre, $id));

            set_flash('success', 'Unidad actualizada correctamente.');
            redirect('unidades_lista.php');
        }
    }
}

$pageTitle = 'Editar Unidad';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb" class="small mb-1">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="unidades_lista.php" class="text-decoration-none">Unidades</a>
                </li>
                <li class="breadcrumb-item active"><?php echo h($unidad['nombre']); ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-pencil-square text-primary me-2"></i>Editar Unidad Organizacional
        </h1>
    </div>
    <a href="unidades_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
    </div>
<?php endif; ?>

<?php if ($totalFuncionarios > 0 || $totalUsuarios > 0): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        Esta unidad tiene
        <?php if ($totalFuncionarios > 0): ?>
            <strong><?php echo $totalFuncionarios; ?> funcionario(s)</strong>
        <?php endif; ?>
        <?php if ($totalFuncionarios > 0 && $totalUsuarios > 0): ?> y <?php endif; ?>
        <?php if ($totalUsuarios > 0): ?>
            <strong><?php echo $totalUsuarios; ?> usuario(s)</strong>
        <?php endif; ?>
        asignado(s). Los cambios se reflejarán automáticamente en todo el sistema.
    </div>
<?php endif; ?>

<form method="post" id="formUnidad">
<div class="row g-3">

    <!-- Columna principal -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-building text-primary me-2"></i>Datos de la unidad
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary">
                            Código <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="codigo"
                               id="inputCodigo"
                               value="<?php echo h($codigo); ?>"
                               class="form-control text-uppercase font-monospace"
                               maxlength="20"
                               required>
                        <div class="form-text">Máx. 20 caracteres. Único en el sistema.</div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-bold text-secondary">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nombre"
                               value="<?php echo h($nombre); ?>"
                               class="form-control"
                               maxlength="150"
                               required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Estado actual</label>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <span class="badge <?php echo ((int)$unidad['estado'] === 1) ? 'bg-success bg-opacity-10 text-success border border-success-subtle' : 'bg-secondary bg-opacity-10 text-secondary border'; ?> px-3 py-2">
                                <i class="bi bi-<?php echo ((int)$unidad['estado'] === 1) ? 'check-circle' : 'pause-circle'; ?> me-1"></i>
                                <?php echo ((int)$unidad['estado'] === 1) ? 'ACTIVA' : 'INACTIVA'; ?>
                            </span>
                            <span class="small text-muted">
                                Para cambiar el estado usa el listado.
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="col-lg-4">

        <!-- Info del registro -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-card-list text-secondary me-2"></i>Información del registro
                </h6>
            </div>
            <div class="card-body p-3">
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted">ID</dt>
                    <dd class="col-6"><?php echo (int)$unidad['id']; ?></dd>

                    <dt class="col-6 text-muted">Creada</dt>
                    <dd class="col-6"><?php echo date('d-m-Y', strtotime($unidad['created_at'])); ?></dd>

                    <dt class="col-6 text-muted">Funcionarios</dt>
                    <dd class="col-6">
                        <?php if ($totalFuncionarios > 0): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">
                                <?php echo $totalFuncionarios; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-6 text-muted">Usuarios</dt>
                    <dd class="col-6">
                        <?php if ($totalUsuarios > 0): ?>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">
                                <?php echo $totalUsuarios; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Acciones -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i> Guardar cambios
                    </button>
                    <a href="unidades_lista.php" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>
</form>

<script>
document.getElementById('inputCodigo').addEventListener('input', function() {
    var pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>