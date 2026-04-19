<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM bodegas WHERE id = ?");
$stmt->execute(array($id));
$bodega = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bodega) {
    die('Bodega no encontrada');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim((string)post('nombre'));
    $descripcion = trim((string)post('descripcion'));
    $responsable = trim((string)post('responsable'));

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $sql = "UPDATE bodegas SET
                nombre = :nombre,
                descripcion = :descripcion,
                responsable = :responsable
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion,
            ':responsable' => $responsable,
            ':id'          => $id
        ));

        set_flash('success', 'Bodega actualizada correctamente.');
        redirect('bodegas_lista.php');
    }
}

$pageTitle = 'Editar Bodega';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Bodega
    </h1>
    <a href="bodegas_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-4">
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Codigo Interno</label>
                <input
                    type="text"
                    value="<?php echo h($bodega['codigo']); ?>"
                    class="form-control bg-light"
                    readonly
                >
                <div class="form-text">El codigo de la bodega es generado por el sistema y no se puede modificar.</div>
            </div>

            <div class="col-md-8">
                <label class="form-label fw-bold text-secondary">Nombre de la Bodega <span class="text-danger">*</span></label>
                <input
                    type="text"
                    name="nombre"
                    value="<?php echo h($bodega['nombre']); ?>"
                    class="form-control"
                    required
                >
            </div>

            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">Responsable a Cargo</label>
                <input
                    type="text"
                    name="responsable"
                    value="<?php echo h($bodega['responsable']); ?>"
                    class="form-control"
                >
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Descripcion / Ubicacion</label>
                <textarea name="descripcion" class="form-control" rows="3"><?php echo h($bodega['descripcion']); ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="bodegas_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>