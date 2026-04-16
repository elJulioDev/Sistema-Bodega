<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';

$bodegas = $pdo->query("SELECT id, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

$productos = $pdo->query("
    SELECT p.id, p.codigo, p.nombre, um.nombre AS unidad_nombre
    FROM productos p
    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    WHERE p.estado = 1 AND p.controla_stock = 1
    ORDER BY p.nombre ASC
")->fetchAll();

// Stock map para mostrar en JS sin llamadas AJAX
$stockRows = $pdo->query("SELECT id_bodega, id_producto, stock_actual FROM stock_bodega")->fetchAll();
$stockMap  = array();
foreach ($stockRows as $sr) {
    $stockMap[$sr['id_bodega'] . '_' . $sr['id_producto']] = (float)$sr['stock_actual'];
}

// Valores prefill desde GET (accesos rápidos de stock_lista)
$pre_tipo     = get('tipo', 'salida_consumo');
$pre_bodega   = get('id_bodega');
$pre_producto = get('id_producto');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo              = post('tipo_movimiento');
    $id_bodega         = post('id_bodega');
    $id_bodega_destino = post('id_bodega_destino');
    $id_producto       = post('id_producto');
    $cantidad          = (float) str_replace(',', '.', post('cantidad'));
    $precio_unitario   = (float) str_replace(',', '.', post('precio_unitario', '0'));
    $observacion       = post('observacion');

    $esTraslado   = ($tipo === 'traslado');
    $tiposValidos = array('salida_consumo', 'ajuste_entrada', 'ajuste_salida', 'traslado');

    if (!in_array($tipo, $tiposValidos) || $id_bodega === '' || $id_producto === '' || $cantidad <= 0) {
        $error = 'Tipo, bodega, producto y cantidad son obligatorios. La cantidad debe ser mayor a 0.';
    } elseif ($esTraslado && ($id_bodega_destino === '' || (int)$id_bodega_destino === (int)$id_bodega)) {
        $error = 'Para traslados debes seleccionar una bodega de destino diferente a la de origen.';
    } else {
        try {
            $pdo->beginTransaction();

            $total      = $cantidad * $precio_unitario;
            $id_usuario = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

            if ($esTraslado) {

                $stmtS = $pdo->prepare("SELECT id, stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
                $stmtS->execute(array((int)$id_bodega, (int)$id_producto));
                $stockOrigen = $stmtS->fetch();

                if (!$stockOrigen || (float)$stockOrigen['stock_actual'] < $cantidad) {
                    $disp = $stockOrigen ? number_format((float)$stockOrigen['stock_actual'], 2, ',', '.') : '0';
                    throw new Exception('Stock insuficiente en bodega origen. Disponible: ' . $disp);
                }

                // salida en origen
                $s = $pdo->prepare("INSERT INTO movimientos_bodega
                    (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario)
                    VALUES (?, ?, 'traslado_salida', ?, ?, ?, 'traslado', 0, ?, ?)");
                $s->execute(array((int)$id_bodega, (int)$id_producto, $cantidad, $precio_unitario, $total, $observacion, $id_usuario));
                $id_mov = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE movimientos_bodega SET referencia_id = ? WHERE id = ?")->execute(array($id_mov, $id_mov));

                $pdo->prepare("UPDATE stock_bodega SET stock_actual = stock_actual - ? WHERE id = ?")
                    ->execute(array($cantidad, (int)$stockOrigen['id']));

                // entrada en destino
                $e = $pdo->prepare("INSERT INTO movimientos_bodega
                    (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario)
                    VALUES (?, ?, 'traslado_entrada', ?, ?, ?, 'traslado', ?, ?, ?)");
                $e->execute(array((int)$id_bodega_destino, (int)$id_producto, $cantidad, $precio_unitario, $total, $id_mov, $observacion, $id_usuario));

                $stmtD = $pdo->prepare("SELECT id, stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
                $stmtD->execute(array((int)$id_bodega_destino, (int)$id_producto));
                $stockDest = $stmtD->fetch();

                if ($stockDest) {
                    $pdo->prepare("UPDATE stock_bodega SET stock_actual = stock_actual + ? WHERE id = ?")
                        ->execute(array($cantidad, (int)$stockDest['id']));
                } else {
                    $pdo->prepare("INSERT INTO stock_bodega (id_bodega, id_producto, stock_actual, costo_promedio) VALUES (?, ?, ?, ?)")
                        ->execute(array((int)$id_bodega_destino, (int)$id_producto, $cantidad, $precio_unitario));
                }

            } else {

                $esEntrada = ($tipo === 'ajuste_entrada');
                $esSalida  = ($tipo === 'salida_consumo' || $tipo === 'ajuste_salida');

                $stmtS = $pdo->prepare("SELECT id, stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
                $stmtS->execute(array((int)$id_bodega, (int)$id_producto));
                $stockActual = $stmtS->fetch();

                if ($esSalida) {
                    $disp = $stockActual ? (float)$stockActual['stock_actual'] : 0;
                    if ($disp < $cantidad) {
                        throw new Exception('Stock insuficiente. Disponible: ' . number_format($disp, 2, ',', '.'));
                    }
                }

                $m = $pdo->prepare("INSERT INTO movimientos_bodega
                    (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario)
                    VALUES (?, ?, ?, ?, ?, ?, 'manual', 0, ?, ?)");
                $m->execute(array((int)$id_bodega, (int)$id_producto, $tipo, $cantidad, $precio_unitario, $total, $observacion, $id_usuario));
                $id_mov = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE movimientos_bodega SET referencia_id = ? WHERE id = ?")->execute(array($id_mov, $id_mov));

                if ($stockActual) {
                    $delta = $esEntrada ? $cantidad : -$cantidad;
                    $pdo->prepare("UPDATE stock_bodega SET stock_actual = stock_actual + ? WHERE id = ?")
                        ->execute(array($delta, (int)$stockActual['id']));
                } elseif ($esEntrada) {
                    $pdo->prepare("INSERT INTO stock_bodega (id_bodega, id_producto, stock_actual, costo_promedio) VALUES (?, ?, ?, ?)")
                        ->execute(array((int)$id_bodega, (int)$id_producto, $cantidad, $precio_unitario));
                }
            }

            $pdo->commit();
            set_flash('success', 'Movimiento registrado y stock actualizado correctamente.');
            redirect('movimientos_lista.php');

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    // restaurar prefill si hay error
    $pre_tipo     = $tipo;
    $pre_bodega   = $id_bodega;
    $pre_producto = $id_producto;
}

$pageTitle = 'Nuevo Movimiento';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-arrow-left-right text-primary me-2"></i>Nuevo Movimiento de Bodega
    </h1>
    <a href="movimientos_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al historial
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post" id="formMovimiento">

    <!-- 1. Tipo -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0">
            <h5 class="mb-0 fw-bold">¿Qué tipo de movimiento es?</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php
                $tipos_mov = array(
                    'salida_consumo' => array('icon' => 'bi-box-arrow-right', 'color' => 'danger',  'label' => 'Salida por Consumo',       'desc' => 'Entrega de material para uso interno'),
                    'traslado'       => array('icon' => 'bi-arrow-repeat',    'color' => 'primary', 'label' => 'Traslado entre Bodegas',    'desc' => 'Mover stock de una bodega a otra'),
                    'ajuste_entrada' => array('icon' => 'bi-plus-circle',     'color' => 'success', 'label' => 'Ajuste Entrada',            'desc' => 'Corrección positiva de inventario'),
                    'ajuste_salida'  => array('icon' => 'bi-dash-circle',     'color' => 'warning', 'label' => 'Ajuste Salida',             'desc' => 'Corrección negativa de inventario'),
                );
                foreach ($tipos_mov as $val => $t): ?>
                <div class="col-6 col-md-3">
                    <input type="radio" class="btn-check" name="tipo_movimiento"
                           id="tipo_<?php echo $val; ?>" value="<?php echo $val; ?>"
                           autocomplete="off" required
                           <?php echo ($pre_tipo === $val) ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-<?php echo $t['color']; ?> w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 gap-2 text-center"
                           for="tipo_<?php echo $val; ?>"
                           style="min-height:115px; border-width:2px; cursor:pointer; transition: all .15s;">
                        <i class="bi <?php echo $t['icon']; ?> fs-2"></i>
                        <span class="fw-bold" style="font-size:.82rem;"><?php echo $t['label']; ?></span>
                        <span class="text-muted" style="font-size:.7rem;"><?php echo $t['desc']; ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 2. Detalle -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0">
            <h5 class="mb-0 fw-bold">Detalle del Movimiento</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">

                <!-- Bodega origen -->
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">
                        Bodega <span id="lbl-origen-suffix">de Origen</span> <span class="text-danger">*</span>
                    </label>
                    <select name="id_bodega" id="id_bodega" class="form-select" required>
                        <option value="">Seleccione una bodega</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>"
                                <?php echo ((string)$pre_bodega === (string)$b['id']) ? 'selected' : ''; ?>>
                                <?php echo h($b['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Bodega destino (traslado) -->
                <div class="col-md-6" id="wrap-destino" style="display:none;">
                    <label class="form-label fw-bold text-secondary">
                        Bodega de Destino <span class="text-danger">*</span>
                    </label>
                    <select name="id_bodega_destino" id="id_bodega_destino" class="form-select">
                        <option value="">Seleccione una bodega</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>"><?php echo h($b['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Producto -->
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">Producto <span class="text-danger">*</span></label>
                    <select name="id_producto" id="id_producto" class="form-select" required>
                        <option value="">Seleccione un producto</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"
                                data-unidad="<?php echo h($p['unidad_nombre']); ?>"
                                <?php echo ((string)$pre_producto === (string)$p['id']) ? 'selected' : ''; ?>>
                                <?php echo h($p['codigo'] . ' — ' . $p['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Stock actual dinámico -->
                    <div id="stock-display-wrap" class="mt-2 d-flex align-items-center gap-2" style="display:none!important;">
                        <span id="stock-display" class="badge fs-6 px-3 py-2"></span>
                        <span id="stock-unidad" class="text-muted small"></span>
                    </div>
                </div>

                <!-- Cantidad -->
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary">Cantidad <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01"
                           name="cantidad" id="cantidad" class="form-control fs-5 fw-bold"
                           placeholder="0.00"
                           value="<?php echo isset($_POST['cantidad']) ? h(post('cantidad')) : ''; ?>" required>
                </div>

                <!-- Precio unitario -->
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary">Precio Unitario</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" min="0"
                               name="precio_unitario" class="form-control"
                               placeholder="0"
                               value="<?php echo isset($_POST['precio_unitario']) ? h(post('precio_unitario')) : '0'; ?>">
                    </div>
                    <div class="form-text">Solo para registro de costo.</div>
                </div>

                <!-- Observación -->
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary">Observación / Motivo</label>
                    <textarea name="observacion" class="form-control" rows="2"
                        placeholder="Ej: Entrega a Dir. Obras para proyecto X, corrección inventario diciembre..."><?php echo isset($_POST['observacion']) ? h(post('observacion')) : ''; ?></textarea>
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="movimientos_lista.php" class="btn btn-light border px-4">Cancelar</a>
        <button type="submit" class="btn btn-primary px-5 py-2" id="btnSubmit">
            <i class="bi bi-floppy me-2"></i>Registrar Movimiento
        </button>
    </div>

</form>

<script>
(function () {
    var stockMap    = <?php echo json_encode($stockMap); ?>;
    var tipoRadios  = document.querySelectorAll('input[name="tipo_movimiento"]');
    var wrapDestino = document.getElementById('wrap-destino');
    var selDestino  = document.getElementById('id_bodega_destino');
    var selBodega   = document.getElementById('id_bodega');
    var selProducto = document.getElementById('id_producto');
    var stockWrap   = document.getElementById('stock-display-wrap');
    var stockEl     = document.getElementById('stock-display');
    var stockUnidad = document.getElementById('stock-unidad');
    var lblSuffix   = document.getElementById('lbl-origen-suffix');
    var btnSubmit   = document.getElementById('btnSubmit');

    var btnLabels = {
        'salida_consumo': 'Registrar Salida',
        'traslado':       'Registrar Traslado',
        'ajuste_entrada': 'Registrar Ajuste de Entrada',
        'ajuste_salida':  'Registrar Ajuste de Salida'
    };

    function getTipo() {
        var c = document.querySelector('input[name="tipo_movimiento"]:checked');
        return c ? c.value : '';
    }

    function updateTipo() {
        var tipo = getTipo();
        var esTraslado = tipo === 'traslado';

        wrapDestino.style.display = esTraslado ? '' : 'none';
        selDestino.required = esTraslado;
        lblSuffix.textContent = esTraslado ? 'de Origen' : '';
        btnSubmit.innerHTML = '<i class="bi bi-floppy me-2"></i>' + (btnLabels[tipo] || 'Registrar Movimiento');

        updateStock();
    }

    function updateStock() {
        var bodega   = selBodega.value;
        var producto = selProducto.value;

        if (!bodega || !producto) {
            stockWrap.style.display = 'none';
            return;
        }

        var key   = bodega + '_' + producto;
        var stock = stockMap.hasOwnProperty(key) ? parseFloat(stockMap[key]) : 0;
        var opt   = selProducto.options[selProducto.selectedIndex];
        var unidad = opt ? (opt.getAttribute('data-unidad') || '') : '';

        stockWrap.style.removeProperty('display');
        stockEl.textContent  = 'Stock actual: ' + stock.toFixed(2);
        stockEl.className    = 'badge fs-6 px-3 py-2 ' + (stock > 0 ? 'bg-success' : 'bg-danger');
        stockUnidad.textContent = unidad;
    }

    tipoRadios.forEach(function (r) { r.addEventListener('change', updateTipo); });
    selBodega.addEventListener('change', updateStock);
    selProducto.addEventListener('change', updateStock);

    updateTipo();
    updateStock();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>