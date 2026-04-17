<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute(array($id));
$producto = $stmt->fetch();

if (!$producto) { die('Producto no encontrado'); }

$tipos = $pdo->query("SELECT id, nombre FROM tipos_producto WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$unidades = $pdo->query("SELECT id, nombre FROM unidades_medida WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = post('codigo');
    $nombre = post('nombre');
    $id_tipo = post('id_tipo_producto');
    $id_unidad = post('id_unidad_medida');
    $stock_min = post('stock_minimo', 0);
    $activo_fijo = post('activo_fijo', 0);
    $descripcion = post('descripcion');

    if ($codigo === '' || $nombre === '') {
        $error = 'Código y nombre son obligatorios.';
    } else {
        $sql = "UPDATE productos SET 
                codigo = ?, nombre = ?, id_tipo_producto = ?, id_unidad_medida = ?, 
                stock_minimo = ?, activo_fijo = ?, descripcion = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($codigo, $nombre, $id_tipo ?: null, $id_unidad ?: null, $stock_min, $activo_fijo, $descripcion, $id));
        
        set_flash('success', 'Producto actualizado correctamente.');
        redirect('productos_lista.php');
    }
}

$pageTitle = 'Editar Producto';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Producto</h1>
    <a href="productos_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post" class="row g-4">
            <div class="col-md-3">
                <label class="form-label fw-bold text-secondary small text-uppercase">Código</label>
                <input type="text" name="codigo" value="<?php echo h($producto['codigo']); ?>" class="form-control" required>
            </div>
            <div class="col-md-9">
                <label class="form-label fw-bold text-secondary small text-uppercase">Nombre del Producto</label>
                <input type="text" name="nombre" value="<?php echo h($producto['nombre']); ?>" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small text-uppercase">Categoría</label>
                <select name="id_tipo_producto" class="form-select">
                    <option value="">Seleccione...</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo ($producto['id_tipo_producto'] == $t['id']) ? 'selected' : ''; ?>><?php echo h($t['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small text-uppercase">Unidad de Medida</label>
                <select name="id_unidad_medida" class="form-select">
                    <option value="">Seleccione...</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($producto['id_unidad_medida'] == $u['id']) ? 'selected' : ''; ?>><?php echo h($u['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary small text-uppercase">Stock Mínimo</label>
                <input type="number" step="0.01" name="stock_minimo" value="<?php echo h($producto['stock_minimo']); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary small text-uppercase">¿Es Activo Fijo?</label>
                <select name="activo_fijo" class="form-select">
                    <option value="0" <?php echo ($producto['activo_fijo'] == 0) ? 'selected' : ''; ?>>No (Consumo)</option>
                    <option value="1" <?php echo ($producto['activo_fijo'] == 1) ? 'selected' : ''; ?>>Sí (Activo)</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary small text-uppercase">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"><?php echo h($producto['descripcion']); ?></textarea>
            </div>

            <div class="col-12 text-end pt-3 border-top">
                <a href="productos_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-floppy me-1"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../inc/footer.php'; ?>