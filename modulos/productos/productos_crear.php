<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';

$codigo = '';
$nombre = '';
$unidad_medida = 'unidad';
$activo_fijo = '0';
$stock_minimo = '0.00';
$descripcion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = post('codigo');
    $nombre = post('nombre');
    $unidad_medida = post('unidad_medida');
    $activo_fijo = post('activo_fijo', '0');
    $stock_minimo = post('stock_minimo', '0.00');
    $descripcion = post('descripcion');

    if ($codigo === '' || $nombre === '') {
        $error = 'El código y el nombre son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? LIMIT 1");
        $stmt->execute(array($codigo));
        $existe = $stmt->fetch();

        if ($existe) {
            $error = 'Ya existe un producto con ese código.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO productos (
                    codigo, nombre, unidad_medida, activo_fijo, stock_minimo, descripcion, estado
                ) VALUES (?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute(array(
                $codigo,
                $nombre,
                $unidad_medida,
                (int)$activo_fijo,
                ($stock_minimo !== '' ? $stock_minimo : 0),
                ($descripcion !== '' ? $descripcion : null)
            ));

            set_flash('success', 'Producto creado correctamente.');
            redirect('productos_lista.php');
        }
    }
}

$pageTitle = 'Nuevo Producto';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-box-seam text-primary me-2"></i>Nuevo Producto</h1>
    <a href="productos_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" class="row g-4">
            <div class="col-md-3">
                <label class="form-label fw-bold text-secondary">Código <span class="text-danger">*</span></label>
                <input type="text" name="codigo" value="<?php echo h($codigo); ?>" class="form-control" required>
            </div>

            <div class="col-md-9">
                <label class="form-label fw-bold text-secondary">Nombre de producto <span class="text-danger">*</span></label>
                <input type="text" name="nombre" value="<?php echo h($nombre); ?>" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Unidad de Medida</label>
                <select name="unidad_medida" class="form-select">
                    <option value="unidad" <?php echo ($unidad_medida == 'unidad') ? 'selected' : ''; ?>>Unidad</option>
                    <option value="caja" <?php echo ($unidad_medida == 'caja') ? 'selected' : ''; ?>>Caja</option>
                    <option value="paquete" <?php echo ($unidad_medida == 'paquete') ? 'selected' : ''; ?>>Paquete</option>
                    <option value="kilogramo" <?php echo ($unidad_medida == 'kilogramo') ? 'selected' : ''; ?>>Kilogramo</option>
                    <option value="litro" <?php echo ($unidad_medida == 'litro') ? 'selected' : ''; ?>>Litro</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Stock Mínimo Alerta</label>
                <input type="number" step="0.01" name="stock_minimo" value="<?php echo h($stock_minimo); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">¿Es Activo Fijo?</label>
                <select name="activo_fijo" class="form-select">
                    <option value="0" <?php echo ($activo_fijo == '0') ? 'selected' : ''; ?>>No, es de consumo (fungible)</option>
                    <option value="1" <?php echo ($activo_fijo == '1') ? 'selected' : ''; ?>>Sí, es activo de la empresa</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Descripción General</label>
                <textarea name="descripcion" class="form-control" rows="3" placeholder="Ingresa detalles, características, etc."><?php echo h($descripcion); ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="productos_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php';