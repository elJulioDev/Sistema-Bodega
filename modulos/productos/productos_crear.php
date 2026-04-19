<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';

// Unidades de medida activas
$stmt = $pdo->query("SELECT id, nombre, codigo FROM unidades_medida WHERE estado = 1 ORDER BY nombre ASC");
$unidades = $stmt->fetchAll();

$codigo = '';
$nombre = '';
$descripcion = '';
$id_unidad_medida = '';
$stock_minimo = '0.00';
$activo_fijo = '0';

// Sugerir código automático (PROD-###)
$stmtCod = $pdo->query("SELECT codigo FROM productos WHERE codigo LIKE 'PROD-%' ORDER BY id DESC LIMIT 1");
$ultimo = $stmtCod->fetch();
$codigoSugerido = 'PROD-100';
if ($ultimo && preg_match('/PROD-(\d+)/', $ultimo['codigo'], $m)) {
    $codigoSugerido = 'PROD-' . ((int)$m[1] + 1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = trim(post('codigo'));
    $nombre = trim(post('nombre'));
    $descripcion = trim(post('descripcion'));
    $id_unidad_medida = post('id_unidad_medida');
    $stock_minimo = post('stock_minimo', '0.00');
    $activo_fijo = post('activo_fijo', '0');

    if ($codigo === '' || $nombre === '') {
        $error = 'El código y el nombre son obligatorios.';
    } elseif ($id_unidad_medida === '') {
        $error = 'Debes seleccionar una unidad de medida.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? LIMIT 1");
        $stmt->execute(array($codigo));
        $existe = $stmt->fetch();

        if ($existe) {
            $error = 'Ya existe un producto con ese código.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO productos (
                    codigo, nombre, descripcion, id_unidad_medida,
                    stock_minimo, activo_fijo, estado
                ) VALUES (?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute(array(
                $codigo,
                $nombre,
                ($descripcion !== '' ? $descripcion : null),
                (int)$id_unidad_medida,
                ($stock_minimo !== '' ? $stock_minimo : 0),
                (int)$activo_fijo
            ));

            set_flash('success', 'Producto creado correctamente.');
            redirect('productos_lista.php');
        }
    }
} else {
    $codigo = $codigoSugerido;
}

$pageTitle = 'Nuevo Producto';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Nuevo Producto</h1>
        <small class="text-muted">Registra un nuevo artículo en el catálogo</small>
    </div>
    <a href="productos_lista.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
                <?php endif; ?>

                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" value="<?php echo h($codigo); ?>" class="form-control" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Nombre del producto <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" value="<?php echo h($nombre); ?>" class="form-control" placeholder="Ej: Resma de papel carta" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Unidad de Medida <span class="text-danger">*</span></label>
                        <select name="id_unidad_medida" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($id_unidad_medida == $u['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($u['nombre']); ?> (<?php echo h($u['codigo']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Stock Mínimo (alerta)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-bell"></i></span>
                            <input type="number" step="0.01" min="0" name="stock_minimo" value="<?php echo h($stock_minimo); ?>" class="form-control">
                        </div>
                        <small class="text-muted">Se alerta cuando el stock llegue a este valor.</small>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Tipo de producto</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="activo_fijo" id="af_no" value="0" <?php echo ($activo_fijo == '0') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary w-100 text-start" for="af_no">
                                    <i class="bi bi-basket me-1"></i> <strong>Consumible</strong>
                                    <div class="small text-muted">Producto que se consume</div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="activo_fijo" id="af_si" value="1" <?php echo ($activo_fijo == '1') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-info w-100 text-start" for="af_si">
                                    <i class="bi bi-building me-1"></i> <strong>Activo Fijo</strong>
                                    <div class="small text-muted">Bien de la empresa</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary text-uppercase">Descripción general</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles adicionales del producto..."><?php echo h($descripcion); ?></textarea>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                        <a href="productos_lista.php" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body p-3">
                <h6 class="fw-bold text-primary"><i class="bi bi-info-circle me-1"></i> Ayuda rápida</h6>
                <ul class="small text-secondary ps-3 mb-0">
                    <li class="mb-2"><strong>Código:</strong> identificador único. Puedes usar el sugerido o uno propio.</li>
                    <li class="mb-2"><strong>Unidad:</strong> cómo se mide/vende (unidad, caja, litro, etc.).</li>
                    <li class="mb-2"><strong>Stock mínimo:</strong> al llegar a este valor aparecerá una alerta en el sistema.</li>
                    <li class="mb-0"><strong>Activo fijo:</strong> márcalo solo si es un bien patrimonial (equipos, muebles).</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>