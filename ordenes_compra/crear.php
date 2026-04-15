<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$error = '';

$stmt = $pdo->query("SELECT id, rut, razon_social FROM proveedores WHERE estado = 1 ORDER BY razon_social ASC");
$proveedores = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, codigo, nombre FROM productos WHERE estado = 1 ORDER BY nombre ASC");
$productos = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_oc = post('numero_oc');
    $id_proveedor = post('id_proveedor');
    $fecha_oc = post('fecha_oc');
    $unidad_solicitante = post('unidad_solicitante');
    $centro_costo = post('centro_costo');
    $descripcion = post('descripcion');
    $observacion = post('observacion');
    $estado = post('estado', 'pendiente');

    $items_producto = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_descripcion = isset($_POST['item_descripcion']) ? $_POST['item_descripcion'] : array();
    $items_cantidad = isset($_POST['item_cantidad']) ? $_POST['item_cantidad'] : array();
    $items_precio = isset($_POST['item_precio']) ? $_POST['item_precio'] : array();

    if ($numero_oc === '' || $id_proveedor === '' || $fecha_oc === '') {
        $error = 'Número OC, proveedor y fecha son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM ordenes_compra WHERE numero_oc = ? LIMIT 1");
        $stmt->execute(array($numero_oc));
        $existe = $stmt->fetch();

        if ($existe) {
            $error = 'Ya existe una orden de compra con ese número.';
        } else {
            $detalleLimpio = array();
            $monto_neto = 0;
            $totalItems = count($items_descripcion);

            for ($i = 0; $i < $totalItems; $i++) {
                $id_producto = isset($items_producto[$i]) ? trim($items_producto[$i]) : '';
                $desc = isset($items_descripcion[$i]) ? trim($items_descripcion[$i]) : '';
                $cant = isset($items_cantidad[$i]) ? (float)$items_cantidad[$i] : 0;
                $precio = isset($items_precio[$i]) ? (float)$items_precio[$i] : 0;

                if ($desc !== '' && $cant > 0) {
                    $subtotal = $cant * $precio;
                    $monto_neto += $subtotal;

                    $detalleLimpio[] = array(
                        'id_producto' => ($id_producto !== '' ? (int)$id_producto : null),
                        'descripcion_item' => $desc,
                        'cantidad' => $cant,
                        'precio_unitario' => $precio,
                        'subtotal' => $subtotal
                    );
                }
            }

            if (!$detalleLimpio) {
                $error = 'Debes ingresar al menos un ítem válido en el detalle.';
            } else {
                $monto_iva = round($monto_neto * 0.19, 2);
                $monto_total = $monto_neto + $monto_iva;

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO ordenes_compra (
                            numero_oc, id_proveedor, fecha_oc, unidad_solicitante, centro_costo,
                            descripcion, monto_neto, monto_iva, monto_total, estado, observacion, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute(array(
                        $numero_oc, (int)$id_proveedor, $fecha_oc, $unidad_solicitante, $centro_costo,
                        $descripcion, $monto_neto, $monto_iva, $monto_total, $estado, $observacion,
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                    ));

                    $id_oc = $pdo->lastInsertId();

                    $stmtDetalle = $pdo->prepare("
                        INSERT INTO ordenes_compra_detalle (
                            id_orden_compra, id_producto, descripcion_item, cantidad, precio_unitario, subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    foreach ($detalleLimpio as $item) {
                        $stmtDetalle->execute(array(
                            $id_oc, $item['id_producto'], $item['descripcion_item'],
                            $item['cantidad'], $item['precio_unitario'], $item['subtotal']
                        ));
                    }

                    $pdo->commit();
                    set_flash('success', 'Orden de compra creada correctamente.');
                    redirect('ver.php?id=' . $id_oc);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error al guardar: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Nueva Orden de Compra';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-cart-plus text-primary me-2"></i>Nueva Orden de Compra</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post" id="formOC">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0">
            <h5 class="mb-0 fw-bold">Datos Generales</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary">Número OC <span class="text-danger">*</span></label>
                    <input type="text" name="numero_oc" value="<?php echo h(post('numero_oc')); ?>" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">Proveedor <span class="text-danger">*</span></label>
                    <select name="id_proveedor" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo (post('id_proveedor') == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo h($p['razon_social'] . ' - ' . $p['rut']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary">Fecha OC <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_oc" value="<?php echo h(post('fecha_oc')); ?>" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary">Unidad solicitante</label>
                    <input type="text" name="unidad_solicitante" value="<?php echo h(post('unidad_solicitante')); ?>" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary">Centro de costo</label>
                    <input type="text" name="centro_costo" value="<?php echo h(post('centro_costo')); ?>" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary">Estado Inicial</label>
                    <select name="estado" class="form-select">
                        <option value="pendiente">Pendiente</option>
                        <option value="parcial">Parcial</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">Descripción</label>
                    <textarea name="descripcion" rows="2" class="form-control"><?php echo h(post('descripcion')); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">Observación Interna</label>
                    <textarea name="observacion" rows="2" class="form-control"><?php echo h(post('observacion')); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Detalle de Ítems</h5>
            <button type="button" class="btn btn-sm btn-success" id="btnAgregarFila"><i class="bi bi-plus-lg me-1"></i> Agregar Ítem</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaDetalle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th style="width: 25%;">Producto</th>
                            <th style="width: 30%;">Descripción</th>
                            <th style="width: 12%;">Cantidad</th>
                            <th style="width: 15%;">Precio Unit.</th>
                            <th style="width: 13%;">Subtotal</th>
                            <th style="width: 5%;" class="text-center"><i class="bi bi-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="detalleBody">
                        <tr>
                            <td>
                                <select name="item_id_producto[]" class="form-select item-producto">
                                    <option value="">Seleccione o deje en blanco</option>
                                    <?php foreach ($productos as $pr): ?>
                                        <option value="<?php echo (int)$pr['id']; ?>" data-nombre="<?php echo h($pr['nombre']); ?>">
                                            <?php echo h($pr['codigo'] . ' - ' . $pr['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="item_descripcion[]" class="form-control item-descripcion"></td>
                            <td><input type="number" step="0.01" min="0" name="item_cantidad[]" class="form-control item-cantidad" value="1"></td>
                            <td><input type="number" step="0.01" min="0" name="item_precio[]" class="form-control item-precio" value="0"></td>
                            <td><input type="text" class="form-control item-subtotal bg-light" value="0" readonly></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila"><i class="bi bi-x-lg"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-4 offset-md-8">
            <div class="card shadow-sm border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold text-secondary">Neto:</span>
                        <span>$ <input type="text" id="resumenNeto" readonly value="0" class="border-0 bg-transparent text-end fw-medium" style="width: 100px; outline:none;"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold text-secondary">IVA (19%):</span>
                        <span>$ <input type="text" id="resumenIva" readonly value="0" class="border-0 bg-transparent text-end fw-medium" style="width: 100px; outline:none;"></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5">
                        <span class="fw-bold text-dark">Total:</span>
                        <span class="fw-bold text-primary">$ <input type="text" id="resumenTotal" readonly value="0" class="border-0 bg-transparent text-end text-primary fw-bold" style="width: 120px; outline:none;"></span>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4 py-2"><i class="bi bi-floppy me-2"></i>Guardar Orden de Compra</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function() {
    var detalleBody = document.getElementById('detalleBody');
    var btnAgregarFila = document.getElementById('btnAgregarFila');

    function recalcularFila(tr) {
        var cantidad = parseFloat(tr.querySelector('.item-cantidad').value || 0);
        var precio = parseFloat(tr.querySelector('.item-precio').value || 0);
        var subtotal = cantidad * precio;
        tr.querySelector('.item-subtotal').value = subtotal.toFixed(2);
        recalcularResumen();
    }

    function recalcularResumen() {
        var filas = detalleBody.querySelectorAll('tr');
        var neto = 0;
        for (var i = 0; i < filas.length; i++) {
            neto += parseFloat(filas[i].querySelector('.item-subtotal').value || 0);
        }
        var iva = neto * 0.19;
        document.getElementById('resumenNeto').value = neto.toFixed(2);
        document.getElementById('resumenIva').value = iva.toFixed(2);
        document.getElementById('resumenTotal').value = (neto + iva).toFixed(2);
    }

    function enlazarFila(tr) {
        tr.querySelector('.item-producto').onchange = function() {
            var txt = this.options[this.selectedIndex].getAttribute('data-nombre');
            if (tr.querySelector('.item-descripcion').value === '' && txt) {
                tr.querySelector('.item-descripcion').value = txt;
            }
        };
        tr.querySelector('.item-cantidad').oninput = function() { recalcularFila(tr); };
        tr.querySelector('.item-precio').oninput = function() { recalcularFila(tr); };
        tr.querySelector('.btn-eliminar-fila').onclick = function() {
            if (detalleBody.querySelectorAll('tr').length > 1) {
                tr.remove();
                recalcularResumen();
            }
        };
    }

    btnAgregarFila.onclick = function() {
        var tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="item_id_producto[]" class="form-select item-producto">${document.querySelector('.item-producto').innerHTML}</select></td>
            <td><input type="text" name="item_descripcion[]" class="form-control item-descripcion"></td>
            <td><input type="number" step="0.01" min="0" name="item_cantidad[]" class="form-control item-cantidad" value="1"></td>
            <td><input type="number" step="0.01" min="0" name="item_precio[]" class="form-control item-precio" value="0"></td>
            <td><input type="text" class="form-control item-subtotal bg-light" value="0" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila"><i class="bi bi-x-lg"></i></button></td>
        `;
        detalleBody.appendChild(tr);
        enlazarFila(tr);
    };

    document.querySelectorAll('#detalleBody tr').forEach(enlazarFila);
})();
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>