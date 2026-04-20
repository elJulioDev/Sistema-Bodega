<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute(array($id));
$producto = $stmt->fetch();

if (!$producto) { 
    set_flash('error', 'Producto no encontrado.');
    redirect('productos_lista.php');
}

$unidades = $pdo->query("SELECT id, nombre, codigo FROM unidades_medida WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim(post('codigo'));
    $nombre = trim(post('nombre'));
    $id_unidad = post('id_unidad_medida');
    $stock_min = post('stock_minimo', 0);
    $activo_fijo = post('activo_fijo', 0);
    $descripcion = trim(post('descripcion'));

    if ($codigo === '' || $nombre === '') {
        $error = 'Código y nombre son obligatorios.';
    } elseif ($id_unidad === '') {
        $error = 'Debes seleccionar una unidad de medida.';
    } else {
        // Verificar código duplicado
        $stmtC = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? AND id <> ? LIMIT 1");
        $stmtC->execute(array($codigo, $id));
        if ($stmtC->fetch()) {
            $error = 'Ya existe otro producto con ese código.';
        } else {
            $sql = "UPDATE productos SET 
                    codigo = ?, nombre = ?, id_unidad_medida = ?, 
                    stock_minimo = ?, activo_fijo = ?, descripcion = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                $codigo, 
                $nombre, 
                (int)$id_unidad, 
                $stock_min, 
                (int)$activo_fijo, 
                ($descripcion !== '' ? $descripcion : null), 
                $id
            ));
            
            set_flash('success', 'Producto actualizado correctamente.');
            redirect('productos_lista.php');
        }
    }
    // Refrescar datos en memoria con los recién posteados
    $producto['codigo'] = $codigo;
    $producto['nombre'] = $nombre;
    $producto['id_unidad_medida'] = $id_unidad;
    $producto['stock_minimo'] = $stock_min;
    $producto['activo_fijo'] = $activo_fijo;
    $producto['descripcion'] = $descripcion;
}

$pageTitle = 'Editar Producto';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Producto</h1>
        <small class="text-muted">ID #<?php echo (int)$producto['id']; ?> &middot; Modifica los datos del producto</small>
    </div>
    <a href="productos_lista.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" value="<?php echo h($producto['codigo']); ?>" class="form-control" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Nombre del Producto <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" value="<?php echo h($producto['nombre']); ?>" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Unidad de Medida <span class="text-danger">*</span></label>
                        <select name="id_unidad_medida" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($producto['id_unidad_medida'] == $u['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($u['nombre']); ?> (<?php echo h($u['codigo']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Stock Mínimo (alerta)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-bell"></i></span>
                            <input type="number" step="0.01" min="0" name="stock_minimo" value="<?php echo h($producto['stock_minimo']); ?>" class="form-control">
                        </div>
                        <small class="text-muted">Se alerta cuando el stock llegue a este valor.</small>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Tipo de producto</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="activo_fijo" id="af_no" value="0" <?php echo ($producto['activo_fijo'] == 0) ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary w-100 text-start" for="af_no">
                                    <i class="bi bi-basket me-1"></i> <strong>Consumible</strong>
                                    <div class="small text-muted">Producto que se consume</div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="activo_fijo" id="af_si" value="1" <?php echo ($producto['activo_fijo'] == 1) ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-info w-100 text-start" for="af_si">
                                    <i class="bi bi-building me-1"></i> <strong>Activo Fijo</strong>
                                    <div class="small text-muted">Bien de la empresa</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"><?php echo h($producto['descripcion']); ?></textarea>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                        <a href="productos_lista.php" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body p-3">
                <h6 class="fw-bold text-primary"><i class="bi bi-info-circle me-1"></i> Información</h6>
                <dl class="small mb-0">
                    <dt class="text-secondary">Creado</dt>
                    <dd class="mb-2"><?php echo h(date('d/m/Y H:i', strtotime($producto['created_at']))); ?></dd>
                    <dt class="text-secondary">Última actualización</dt>
                    <dd class="mb-2"><?php echo h(date('d/m/Y H:i', strtotime($producto['updated_at']))); ?></dd>
                    <dt class="text-secondary">Estado</dt>
                    <dd class="mb-0">
                        <?php if ((int)$producto['estado'] === 1): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border-0">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border-0">Inactivo</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>