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
                        $numero_oc,
                        (int)$id_proveedor,
                        $fecha_oc,
                        $unidad_solicitante,
                        $centro_costo,
                        $descripcion,
                        $monto_neto,
                        $monto_iva,
                        $monto_total,
                        $estado,
                        $observacion,
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
                            $id_oc,
                            $item['id_producto'],
                            $item['descripcion_item'],
                            $item['cantidad'],
                            $item['precio_unitario'],
                            $item['subtotal']
                        ));
                    }

                    $pdo->commit();

                    set_flash('success', 'Orden de compra creada correctamente.');
                    redirect('ver.php?id=' . $id_oc);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error al guardar la orden de compra: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Nueva Orden de Compra';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Nueva Orden de Compra</h1>

<div class="card">
    <?php if ($error !== ''): ?>
        <div class="flash flash--error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post" id="formOC">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-bottom:18px;">
            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Número OC *</label>
                <input type="text" name="numero_oc" value="<?php echo h(post('numero_oc')); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Proveedor *</label>
                <select name="id_proveedor" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
                    <option value="">Seleccione</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo (post('id_proveedor') == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo h($p['razon_social'] . ' - ' . $p['rut']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Fecha OC *</label>
                <input type="date" name="fecha_oc" value="<?php echo h(post('fecha_oc')); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Unidad solicitante</label>
                <input type="text" name="unidad_solicitante" value="<?php echo h(post('unidad_solicitante')); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Centro de costo</label>
                <input type="text" name="centro_costo" value="<?php echo h(post('centro_costo')); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Estado</label>
                <select name="estado" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
                    <option value="pendiente">pendiente</option>
                    <option value="parcial">parcial</option>
                    <option value="cerrada">cerrada</option>
                    <option value="anulada">anulada</option>
                </select>
            </div>

            <div style="grid-column:1 / -1;">
                <label style="display:block; margin-bottom:6px; font-weight:700;">Descripción</label>
                <textarea name="descripcion" rows="3" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;"><?php echo h(post('descripcion')); ?></textarea>
            </div>

            <div style="grid-column:1 / -1;">
                <label style="display:block; margin-bottom:6px; font-weight:700;">Observación</label>
                <textarea name="observacion" rows="3" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;"><?php echo h(post('observacion')); ?></textarea>
            </div>
        </div>

        <div class="card" style="padding:0; overflow:auto;">
            <div style="padding:16px 16px 0;">
                <h3 style="margin:0 0 12px;">Detalle</h3>
            </div>

            <table id="tablaDetalle" style="width:100%; border-collapse:collapse; min-width:900px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                        <th style="padding:12px 10px;">Producto</th>
                        <th style="padding:12px 10px;">Descripción</th>
                        <th style="padding:12px 10px;">Cantidad</th>
                        <th style="padding:12px 10px;">Precio unitario</th>
                        <th style="padding:12px 10px;">Subtotal</th>
                        <th style="padding:12px 10px;">Acción</th>
                    </tr>
                </thead>
                <tbody id="detalleBody">
                    <tr>
                        <td style="padding:10px;">
                            <select name="item_id_producto[]" class="item-producto" style="width:100%; padding:8px;">
                                <option value="">Seleccione</option>
                                <?php foreach ($productos as $pr): ?>
                                    <option value="<?php echo (int)$pr['id']; ?>" data-nombre="<?php echo h($pr['nombre']); ?>">
                                        <?php echo h($pr['codigo'] . ' - ' . $pr['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="padding:10px;">
                            <input type="text" name="item_descripcion[]" class="item-descripcion" style="width:100%; padding:8px;">
                        </td>
                        <td style="padding:10px;">
                            <input type="number" step="0.01" min="0" name="item_cantidad[]" class="item-cantidad" value="1" style="width:100%; padding:8px;">
                        </td>
                        <td style="padding:10px;">
                            <input type="number" step="0.01" min="0" name="item_precio[]" class="item-precio" value="0" style="width:100%; padding:8px;">
                        </td>
                        <td style="padding:10px;">
                            <input type="text" class="item-subtotal" value="0" readonly style="width:100%; padding:8px; background:#f9fafb;">
                        </td>
                        <td style="padding:10px;">
                            <button type="button" class="btn btn--secondary btn-eliminar-fila">Quitar</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="padding:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn" id="btnAgregarFila">+ Agregar ítem</button>
            </div>
        </div>

        <div class="card">
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:700;">Neto</label>
                    <input type="text" id="resumenNeto" readonly value="0" style="width:100%; padding:10px; background:#f9fafb; border:1px solid #d1d5db; border-radius:10px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:700;">IVA</label>
                    <input type="text" id="resumenIva" readonly value="0" style="width:100%; padding:10px; background:#f9fafb; border:1px solid #d1d5db; border-radius:10px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:700;">Total</label>
                    <input type="text" id="resumenTotal" readonly value="0" style="width:100%; padding:10px; background:#f9fafb; border:1px solid #d1d5db; border-radius:10px;">
                </div>
            </div>

            <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn">Guardar orden de compra</button>
                <a href="index.php" class="btn btn--secondary">Volver</a>
            </div>
        </div>
    </form>
</div>

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
            var sub = parseFloat(filas[i].querySelector('.item-subtotal').value || 0);
            neto += sub;
        }

        var iva = neto * 0.19;
        var total = neto + iva;

        document.getElementById('resumenNeto').value = neto.toFixed(2);
        document.getElementById('resumenIva').value = iva.toFixed(2);
        document.getElementById('resumenTotal').value = total.toFixed(2);
    }

    function enlazarFila(tr) {
        var producto = tr.querySelector('.item-producto');
        var descripcion = tr.querySelector('.item-descripcion');
        var cantidad = tr.querySelector('.item-cantidad');
        var precio = tr.querySelector('.item-precio');
        var quitar = tr.querySelector('.btn-eliminar-fila');

        producto.onchange = function() {
            var txt = this.options[this.selectedIndex].getAttribute('data-nombre');
            if (descripcion.value === '' && txt) {
                descripcion.value = txt;
            }
        };

        cantidad.oninput = function() { recalcularFila(tr); };
        precio.oninput = function() { recalcularFila(tr); };

        quitar.onclick = function() {
            var totalFilas = detalleBody.querySelectorAll('tr').length;
            if (totalFilas > 1) {
                tr.parentNode.removeChild(tr);
                recalcularResumen();
            }
        };
    }

    btnAgregarFila.onclick = function() {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td style="padding:10px;">' +
                '<select name="item_id_producto[]" class="item-producto" style="width:100%; padding:8px;">' +
                    document.querySelector('.item-producto').innerHTML +
                '</select>' +
            '</td>' +
            '<td style="padding:10px;"><input type="text" name="item_descripcion[]" class="item-descripcion" style="width:100%; padding:8px;"></td>' +
            '<td style="padding:10px;"><input type="number" step="0.01" min="0" name="item_cantidad[]" class="item-cantidad" value="1" style="width:100%; padding:8px;"></td>' +
            '<td style="padding:10px;"><input type="number" step="0.01" min="0" name="item_precio[]" class="item-precio" value="0" style="width:100%; padding:8px;"></td>' +
            '<td style="padding:10px;"><input type="text" class="item-subtotal" value="0" readonly style="width:100%; padding:8px; background:#f9fafb;"></td>' +
            '<td style="padding:10px;"><button type="button" class="btn btn--secondary btn-eliminar-fila">Quitar</button></td>';

        detalleBody.appendChild(tr);
        enlazarFila(tr);
        recalcularResumen();
    };

    var filasIniciales = detalleBody.querySelectorAll('tr');
    for (var i = 0; i < filasIniciales.length; i++) {
        enlazarFila(filasIniciales[i]);
        recalcularFila(filasIniciales[i]);
    }
})();
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>