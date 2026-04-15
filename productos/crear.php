<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$error = '';

$stmt = $pdo->query("SELECT id, nombre FROM tipos_producto WHERE estado = 1 ORDER BY nombre ASC");
$tipos = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nombre, codigo FROM unidades_medida WHERE estado = 1 ORDER BY nombre ASC");
$unidades = $stmt->fetchAll();

$codigo = '';
$nombre = '';
$descripcion = '';
$id_tipo_producto = '';
$id_unidad_medida = '';
$marca = '';
$modelo = '';
$stock_minimo = '0.00';
$controla_stock = '1';
$activo_fijo = '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = post('codigo');
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    $id_tipo_producto = post('id_tipo_producto');
    $id_unidad_medida = post('id_unidad_medida');
    $marca = post('marca');
    $modelo = post('modelo');
    $stock_minimo = post('stock_minimo', '0.00');
    $controla_stock = post('controla_stock', '1');
    $activo_fijo = post('activo_fijo', '0');

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
                    codigo, nombre, descripcion, id_tipo_producto, id_unidad_medida,
                    marca, modelo, stock_minimo, controla_stock, activo_fijo, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute(array(
                $codigo,
                $nombre,
                ($descripcion !== '' ? $descripcion : null),
                ($id_tipo_producto !== '' ? (int)$id_tipo_producto : null),
                ($id_unidad_medida !== '' ? (int)$id_unidad_medida : null),
                $marca,
                $modelo,
                ($stock_minimo !== '' ? $stock_minimo : 0),
                (int)$controla_stock,
                (int)$activo_fijo
            ));

            set_flash('success', 'Producto creado correctamente.');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Nuevo Producto';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-box-seam text-primary me-2"></i>Nuevo Producto</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>
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

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Categoría / Tipo</label>
                <select name="id_tipo_producto" class="form-select">
                    <option value="">Seleccione</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo ($id_tipo_producto == $t['id']) ? 'selected' : ''; ?>><?php echo h($t['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Unidad de Medida</label>
                <select name="id_unidad_medida" class="form-select">
                    <option value="">Seleccione</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($id_unidad_medida == $u['id']) ? 'selected' : ''; ?>><?php echo h($u['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Marca</label>
                <input type="text" name="marca" value="<?php echo h($marca); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Modelo</label>
                <input type="text" name="modelo" value="<?php echo h($modelo); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Stock Mínimo Alerta</label>
                <input type="number" step="0.01" name="stock_minimo" value="<?php echo h($stock_minimo); ?>" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">¿Controla Stock?</label>
                <select name="controla_stock" class="form-select">
                    <option value="1" <?php echo ($controla_stock == '1') ? 'selected' : ''; ?>>Sí, llevar inventario</option>
                    <option value="0" <?php echo ($controla_stock == '0') ? 'selected' : ''; ?>>No, es servicio/intangible</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">¿Es Activo Fijo?</label>
                <select name="activo_fijo" class="form-select">
                    <option value="0" <?php echo ($activo_fijo == '0') ? 'selected' : ''; ?>>No, es producto de consumo</option>
                    <option value="1" <?php echo ($activo_fijo == '1') ? 'selected' : ''; ?>>Sí, activo fijo de la empresa</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Descripción General</label>
                <textarea name="descripcion" class="form-control" rows="3"><?php echo h($descripcion); ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="index.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>