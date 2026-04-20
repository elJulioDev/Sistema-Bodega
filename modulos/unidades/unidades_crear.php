<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error  = '';
$codigo = '';
$nombre = '';

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
        $stmt = $pdo->prepare("SELECT id FROM unidades_organizacionales WHERE codigo = ? LIMIT 1");
        $stmt->execute(array($codigo));
        if ($stmt->fetch()) {
            $error = 'Ya existe una unidad con ese código.';
        } else {
            $pdo->prepare("
                INSERT INTO unidades_organizacionales (codigo, nombre, estado, created_at)
                VALUES (?, ?, 1, NOW())
            ")->execute(array($codigo, $nombre));

            set_flash('success', 'Unidad "' . $nombre . '" creada correctamente.');
            redirect('unidades_lista.php');
        }
    }
}

$pageTitle = 'Nueva Unidad Organizacional';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb" class="small mb-1">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="unidades_lista.php" class="text-decoration-none">Unidades</a>
                </li>
                <li class="breadcrumb-item active">Nueva</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-diagram-3 text-primary me-2"></i>Nueva Unidad Organizacional
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
                               placeholder="Ej: SECPLA"
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
                               placeholder="Ej: Dirección de Secretaría de Planificación Comunal"
                               required>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-info-circle me-2"></i>¿Qué es una unidad?
                </h6>
                <p class="small text-muted mb-2">
                    Las unidades organizacionales representan los departamentos y direcciones
                    de la municipalidad (ej: <code>DIDECO</code>, <code>DAF</code>, <code>DOM</code>).
                </p>
                <p class="small text-muted mb-0">
                    Una vez creadas, pueden asignarse a <strong>funcionarios</strong> y a
                    usuarios con rol <strong>Solicitante</strong> para enrutar sus solicitudes
                    a la bodega correspondiente.
                </p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i> Guardar unidad
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