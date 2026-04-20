<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim((string)post('nombre'));
    $descripcion = trim((string)post('descripcion'));
    $responsable = trim((string)post('responsable'));

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        try {
            // Obtener el último ID para generar el siguiente código
            $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM bodegas");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $nextId = isset($row['max_id']) ? ((int)$row['max_id'] + 1) : 1;

            // Generar código automático
            // Ejemplos: BOD-001, BOD-002, BOD-003
            $codigo = 'BOD-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO bodegas (codigo, nombre, descripcion, responsable, estado)
                    VALUES (:codigo, :nombre, :descripcion, :responsable, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ':codigo'      => $codigo,
                ':nombre'      => $nombre,
                ':descripcion' => $descripcion,
                ':responsable' => $responsable
            ));

            set_flash('success', 'Bodega creada correctamente con código ' . $codigo . '.');
            redirect('bodegas_lista.php');
        } catch (Exception $e) {
            $error = 'No fue posible crear la bodega.';
        }
    }
}

$pageTitle = 'Nueva Bodega';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-building-add text-primary me-2"></i>Nueva Bodega
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
            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">
                    Nombre de la Bodega <span class="text-danger">*</span>
                </label>
                <input
                    type="text"
                    name="nombre"
                    class="form-control"
                    placeholder="Ej: Bodega Central de Insumos"
                    value="<?php echo h((string)post('nombre')); ?>"
                    required
                >
            </div>

            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">Responsable a Cargo</label>
                <input
                    type="text"
                    name="responsable"
                    class="form-control"
                    placeholder="Nombre de la persona encargada"
                    value="<?php echo h((string)post('responsable')); ?>"
                >
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Descripción / Ubicación</label>
                <textarea
                    name="descripcion"
                    class="form-control"
                    rows="3"
                    placeholder="Detalles o ubicación física de esta bodega..."
                ><?php echo h((string)post('descripcion')); ?></textarea>
            </div>

            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    El código de la bodega se genera automáticamente al guardar.
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="bodegas_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Guardar Bodega
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>