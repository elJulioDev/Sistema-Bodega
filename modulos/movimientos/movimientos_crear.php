<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$error = '';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS traspasos_bodega (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_bodega_origen INT UNSIGNED NOT NULL,
        id_bodega_destino INT UNSIGNED NOT NULL,
        fecha DATE NOT NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'completado',
        observacion TEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_origen (id_bodega_origen),
        INDEX idx_destino (id_bodega_destino)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS traspasos_bodega_detalle (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_traspaso INT UNSIGNED NOT NULL,
        id_producto INT UNSIGNED NOT NULL,
        descripcion_item VARCHAR(255) NULL,
        cantidad DECIMAL(12,2) NOT NULL DEFAULT 0,
        costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
        subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_traspaso (id_traspaso),
        INDEX idx_producto (id_producto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$stmt = $pdo->query("SELECT id, codigo, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre ASC");
$bodegas = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_bodega_origen  = (int)post('id_bodega_origen');
    $id_bodega_destino = (int)post('id_bodega_destino');
    $fecha             = trim(post('fecha'));
    $observacion       = trim(post('observacion'));

    $items_producto = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_cantidad = isset($_POST['item_cantidad']) ? $_POST['item_cantidad'] : array();

    if ($id_bodega_origen <= 0 || $id_bodega_destino <= 0 || $fecha === '') {
        $error = 'Bodega origen, bodega destino y fecha son obligatorios.';
    } elseif ($id_bodega_origen === $id_bodega_destino) {
        $error = 'La bodega origen y destino deben ser distintas.';
    } else {
        $detalleLimpio = array();
        $totalItems = count($items_producto);

        for ($i = 0; $i < $totalItems; $i++) {
            $id_producto = isset($items_producto[$i]) ? (int)$items_producto[$i] : 0;
            $cantidad    = isset($items_cantidad[$i]) ? (float)$items_cantidad[$i] : 0;

            if ($id_producto > 0 && $cantidad > 0) {
                $detalleLimpio[] = array(
                    'id_producto' => $id_producto,
                    'cantidad'    => $cantidad
                );
            }
        }

        if (!$detalleLimpio) {
            $error = 'Debes ingresar al menos un producto válido para traspasar.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO traspasos_bodega (
                        id_bodega_origen,
                        id_bodega_destino,
                        fecha,
                        estado,
                        observacion,
                        created_by
                    ) VALUES (?, ?, ?, 'completado', ?, ?)
                ");

                $stmt->execute(array(
                    $id_bodega_origen,
                    $id_bodega_destino,
                    $fecha,
                    ($observacion !== '' ? $observacion : null),
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                ));

                $id_traspaso = (int)$pdo->lastInsertId();

                $stmtDetalle = $pdo->prepare("
                    INSERT INTO traspasos_bodega_detalle (
                        id_traspaso,
                        id_producto,
                        descripcion_item,
                        cantidad,
                        costo_unitario,
                        subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmtMov = $pdo->prepare("
                    INSERT INTO movimientos_bodega (
                        id_bodega,
                        id_producto,
                        tipo_movimiento,
                        cantidad,
                        precio_unitario,
                        total,
                        referencia_tipo,
                        referencia_id,
                        fecha_movimiento,
                        observacion,
                        id_usuario
                    ) VALUES (?, ?, ?, ?, ?, ?, 'traslado', ?, NOW(), ?, ?)
                ");

                $stmtStockOrigen = $pdo->prepare("
                    SELECT 
                        sb.id,
                        sb.stock_actual,
                        sb.costo_promedio,
                        p.nombre
                    FROM stock_bodega sb
                    INNER JOIN productos p ON p.id = sb.id_producto
                    WHERE sb.id_bodega = ? AND sb.id_producto = ?
                    LIMIT 1
                ");

                $stmtStockDestino = $pdo->prepare("
                    SELECT id, stock_actual, costo_promedio
                    FROM stock_bodega
                    WHERE id_bodega = ? AND id_producto = ?
                    LIMIT 1
                ");

                $stmtUpdateStock = $pdo->prepare("
                    UPDATE stock_bodega
                    SET stock_actual = ?, costo_promedio = ?
                    WHERE id = ?
                ");

                $stmtInsertStock = $pdo->prepare("
                    INSERT INTO stock_bodega (
                        id_bodega,
                        id_producto,
                        stock_actual,
                        costo_promedio
                    ) VALUES (?, ?, ?, ?)
                ");

                foreach ($detalleLimpio as $item) {
                    $id_producto = (int)$item['id_producto'];
                    $cantidad    = (float)$item['cantidad'];

                    $stmtStockOrigen->execute(array($id_bodega_origen, $id_producto));
                    $stockOrigen = $stmtStockOrigen->fetch();

                    if (!$stockOrigen) {
                        throw new Exception('El producto ID ' . $id_producto . ' no existe en la bodega de origen.');
                    }

                    if ((float)$stockOrigen['stock_actual'] < $cantidad) {
                        throw new Exception('Stock insuficiente para el producto ' . $stockOrigen['nombre'] . ' en la bodega de origen.');
                    }

                    $costoUnitario = (float)$stockOrigen['costo_promedio'];
                    $subtotal = $cantidad * $costoUnitario;

                    $stmtDetalle->execute(array(
                        $id_traspaso,
                        $id_producto,
                        $stockOrigen['nombre'],
                        $cantidad,
                        $costoUnitario,
                        $subtotal
                    ));

                    $nuevoStockOrigen = (float)$stockOrigen['stock_actual'] - $cantidad;

                    $stmtUpdateStock->execute(array(
                        $nuevoStockOrigen,
                        $costoUnitario,
                        (int)$stockOrigen['id']
                    ));

                    $stmtStockDestino->execute(array($id_bodega_destino, $id_producto));
                    $stockDestino = $stmtStockDestino->fetch();

                    if ($stockDestino) {
                        $nuevoStockDestino = (float)$stockDestino['stock_actual'] + $cantidad;

                        $stmtUpdateStock->execute(array(
                            $nuevoStockDestino,
                            $costoUnitario,
                            (int)$stockDestino['id']
                        ));
                    } else {
                        $stmtInsertStock->execute(array(
                            $id_bodega_destino,
                            $id_producto,
                            $cantidad,
                            $costoUnitario
                        ));
                    }

                    $stmtMov->execute(array(
                        $id_bodega_origen,
                        $id_producto,
                        'traslado_salida',
                        $cantidad,
                        $costoUnitario,
                        $subtotal,
                        $id_traspaso,
                        'Salida por traslado a bodega ID ' . $id_bodega_destino,
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                    ));

                    $stmtMov->execute(array(
                        $id_bodega_destino,
                        $id_producto,
                        'traslado_entrada',
                        $cantidad,
                        $costoUnitario,
                        $subtotal,
                        $id_traspaso,
                        'Entrada por traslado desde bodega ID ' . $id_bodega_origen,
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                    ));
                }

                $pdo->commit();
                set_flash('success', 'Traspaso registrado correctamente.');
                redirect('traspasos_ver.php?id=' . $id_traspaso);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Nuevo Traspaso entre Bodegas';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-arrow-left-right text-primary me-2"></i>Nuevo Traspaso entre Bodegas
    </h1>
    <a href="traspasos_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
    </div>
<?php endif; ?>

<form method="post" id="formTraspaso">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0">
            <h5 class="mb-0 fw-bold">Datos del Traspaso</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary">Bodega Origen <span class="text-danger">*</span></label>
                    <select name="id_bodega_origen" id="id_bodega_origen" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>" <?php echo ((int)post('id_bodega_origen') === (int)$b['id']) ? 'selected' : ''; ?>>
                                <?php echo h($b['nombre'] . ' (' . $b['codigo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary">Bodega Destino <span class="text-danger">*</span></label>
                    <select name="id_bodega_destino" id="id_bodega_destino" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>" <?php echo ((int)post('id_bodega_destino') === (int)$b['id']) ? 'selected' : ''; ?>>
                                <?php echo h($b['nombre'] . ' (' . $b['codigo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" value="<?php echo h(post('fecha', date('Y-m-d'))); ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold text-secondary">Observación</label>
                    <textarea name="observacion" class="form-control" rows="3"><?php echo h(post('observacion')); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Detalle del Traspaso</h5>
            <button type="button" class="btn btn-sm btn-success" id="btnAgregarFila">
                <i class="bi bi-plus-lg me-1"></i> Agregar Producto
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaDetalle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th style="width: 34%;">Producto</th>
                            <th style="width: 14%;">Stock Origen</th>
                            <th style="width: 14%;">Cantidad</th>
                            <th style="width: 14%;">Costo Origen</th>
                            <th style="width: 14%;">Subtotal</th>
                            <th style="width: 10%;">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="detalleBody">
                        <tr>
                            <td>
                                <select name="item_id_producto[]" class="form-select item-producto">
                                    <option value="">Seleccione bodega origen</option>
                                </select>
                            </td>
                            <td><input type="text" class="form-control item-stock bg-light" value="-" readonly></td>
                            <td><input type="number" step="0.01" min="0.01" name="item_cantidad[]" class="form-control item-cantidad" value="1"></td>
                            <td><input type="text" class="form-control item-costo bg-light" value="0.00" readonly></td>
                            <td><input type="text" class="form-control item-subtotal bg-light" value="0.00" readonly></td>
                            <td>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="small item-estado text-muted">Seleccione</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila">&times;</button>
                                </div>
                            </td>
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
                    <div class="d-flex justify-content-between fs-5">
                        <span class="fw-bold text-dark">Total referencial:</span>
                        <span class="fw-bold text-primary">
                            $ <input type="text" id="resumenTotal" readonly value="0.00" class="border-0 bg-transparent text-end text-primary fw-bold" style="width: 130px; outline:none;">
                        </span>
                    </div>
                    <div class="form-text mt-2">
                        Solo se muestran productos con stock en la bodega origen.
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4 py-2">
                        <i class="bi bi-floppy me-2"></i>Registrar Traspaso
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function() {
    var detalleBody = document.getElementById('detalleBody');
    var btnAgregarFila = document.getElementById('btnAgregarFila');
    var bodegaOrigen = document.getElementById('id_bodega_origen');

    function recalcularResumen() {
        var filas = detalleBody.querySelectorAll('tr');
        var total = 0;

        for (var i = 0; i < filas.length; i++) {
            total += parseFloat(filas[i].querySelector('.item-subtotal').value || 0);
        }

        document.getElementById('resumenTotal').value = total.toFixed(2);
    }

    function limpiarFilaInfo(tr) {
        tr.querySelector('.item-stock').value = '-';
        tr.querySelector('.item-costo').value = '0.00';
        tr.querySelector('.item-subtotal').value = '0.00';
        tr.querySelector('.item-estado').className = 'small item-estado text-muted';
        tr.querySelector('.item-estado').textContent = 'Seleccione';
        recalcularResumen();
    }

    function recalcularFila(tr) {
        var cantidad = parseFloat(tr.querySelector('.item-cantidad').value || 0);
        var costo = parseFloat(tr.querySelector('.item-costo').value || 0);
        var stock = parseFloat(tr.querySelector('.item-stock').value || 0);
        var estado = tr.querySelector('.item-estado');

        if (isNaN(cantidad)) cantidad = 0;
        if (isNaN(costo)) costo = 0;
        if (isNaN(stock)) stock = 0;

        tr.querySelector('.item-subtotal').value = (cantidad * costo).toFixed(2);

        if (!tr.querySelector('.item-producto').value) {
            estado.className = 'small item-estado text-muted';
            estado.textContent = 'Seleccione';
        } else if (cantidad <= 0) {
            estado.className = 'small item-estado text-muted';
            estado.textContent = 'Cantidad inválida';
        } else if (cantidad > stock) {
            estado.className = 'small item-estado text-danger';
            estado.textContent = 'Sin stock';
        } else {
            estado.className = 'small item-estado text-success';
            estado.textContent = 'OK';
        }

        recalcularResumen();
    }

    function cargarInfoProducto(tr) {
        var idBodega = bodegaOrigen.value;
        var idProducto = tr.querySelector('.item-producto').value;
        var estado = tr.querySelector('.item-estado');

        if (!idBodega || !idProducto) {
            limpiarFilaInfo(tr);
            return;
        }

        estado.className = 'small item-estado text-muted';
        estado.textContent = 'Consultando...';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'ajax_producto_bodega.php?id_bodega=' + encodeURIComponent(idBodega) + '&id_producto=' + encodeURIComponent(idProducto), true);

        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;

            if (xhr.status !== 200) {
                limpiarFilaInfo(tr);
                estado.className = 'small item-estado text-danger';
                estado.textContent = 'Error';
                return;
            }

            var resp;
            try {
                resp = JSON.parse(xhr.responseText);
            } catch (e) {
                limpiarFilaInfo(tr);
                estado.className = 'small item-estado text-danger';
                estado.textContent = 'Error';
                return;
            }

            if (!resp.ok) {
                limpiarFilaInfo(tr);
                estado.className = 'small item-estado text-danger';
                estado.textContent = resp.error || 'No disponible';
                return;
            }

            tr.querySelector('.item-stock').value = parseFloat(resp.stock_actual || 0).toFixed(2);
            tr.querySelector('.item-costo').value = parseFloat(resp.costo_promedio || 0).toFixed(2);
            recalcularFila(tr);
        };

        xhr.send();
    }

    function cargarProductosFila(tr, selectedValue) {
        var idBodega = bodegaOrigen.value;
        var select = tr.querySelector('.item-producto');

        select.innerHTML = '<option value="">Cargando...</option>';
        limpiarFilaInfo(tr);

        if (!idBodega) {
            select.innerHTML = '<option value="">Seleccione bodega origen</option>';
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'ajax_productos_bodega.php?id_bodega=' + encodeURIComponent(idBodega), true);

        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;

            if (xhr.status !== 200) {
                select.innerHTML = '<option value="">Error al cargar</option>';
                return;
            }

            var resp;
            try {
                resp = JSON.parse(xhr.responseText);
            } catch (e) {
                select.innerHTML = '<option value="">Error al cargar</option>';
                return;
            }

            if (!resp.ok || !resp.productos || !resp.productos.length) {
                select.innerHTML = '<option value="">Sin productos con stock</option>';
                return;
            }

            var html = '<option value="">Seleccione</option>';
            for (var i = 0; i < resp.productos.length; i++) {
                var p = resp.productos[i];
                var sel = (selectedValue && String(selectedValue) === String(p.id)) ? ' selected' : '';
                html += '<option value="' + p.id + '"' + sel + '>';
                html += escaparHtml(p.codigo + ' - ' + p.nombre);
                html += '</option>';
            }

            select.innerHTML = html;

            if (selectedValue) {
                cargarInfoProducto(tr);
            }
        };

        xhr.send();
    }

    function escaparHtml(txt) {
        txt = txt == null ? '' : String(txt);
        return txt
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function enlazarFila(tr) {
        tr.querySelector('.item-producto').onchange = function() {
            cargarInfoProducto(tr);
        };

        tr.querySelector('.item-cantidad').oninput = function() {
            recalcularFila(tr);
        };

        tr.querySelector('.btn-eliminar-fila').onclick = function() {
            if (detalleBody.querySelectorAll('tr').length > 1) {
                tr.remove();
                recalcularResumen();
            }
        };
    }

    btnAgregarFila.onclick = function() {
        var tr = document.createElement('tr');
        tr.innerHTML = ''
            + '<td><select name="item_id_producto[]" class="form-select item-producto"><option value="">Seleccione bodega origen</option></select></td>'
            + '<td><input type="text" class="form-control item-stock bg-light" value="-" readonly></td>'
            + '<td><input type="number" step="0.01" min="0.01" name="item_cantidad[]" class="form-control item-cantidad" value="1"></td>'
            + '<td><input type="text" class="form-control item-costo bg-light" value="0.00" readonly></td>'
            + '<td><input type="text" class="form-control item-subtotal bg-light" value="0.00" readonly></td>'
            + '<td><div class="d-flex gap-2 align-items-center"><span class="small item-estado text-muted">Seleccione</span><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila">&times;</button></div></td>';

        detalleBody.appendChild(tr);
        enlazarFila(tr);
        cargarProductosFila(tr, '');
    };

    bodegaOrigen.onchange = function() {
        var filas = detalleBody.querySelectorAll('tr');
        for (var i = 0; i < filas.length; i++) {
            cargarProductosFila(filas[i], '');
        }
    };

    var filas = detalleBody.querySelectorAll('tr');
    for (var i = 0; i < filas.length; i++) {
        enlazarFila(filas[i]);
        cargarProductosFila(filas[i], filas[i].querySelector('.item-producto').value);
    }

    recalcularResumen();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>