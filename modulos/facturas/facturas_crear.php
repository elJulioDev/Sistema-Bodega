<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';

$CODIGO_BODEGA_CENTRAL = 'CENTRAL';

$stmt = $pdo->prepare("SELECT id, codigo, nombre FROM bodegas WHERE estado = 1 AND codigo = ? LIMIT 1");
$stmt->execute(array($CODIGO_BODEGA_CENTRAL));
$bodegaCentral = $stmt->fetch();

if (!$bodegaCentral) {
    $bodegaCentral = $pdo->query("SELECT id, codigo, nombre FROM bodegas WHERE estado = 1 ORDER BY id ASC LIMIT 1")->fetch();
    if (!$bodegaCentral) {
        die('No existe una bodega activa en el sistema.');
    }
}

$proveedores = $pdo->query("SELECT id, rut, razon_social FROM proveedores WHERE estado = 1 ORDER BY razon_social ASC")->fetchAll();

$idBC = (int)$bodegaCentral['id'];
$productos = $pdo->query("SELECT p.id, p.codigo, p.nombre, p.activo_fijo, um.nombre AS unidad,
                          COALESCE((SELECT costo_promedio FROM stock_bodega WHERE id_producto = p.id AND id_bodega = $idBC LIMIT 1), 0) AS ultimo_costo
                          FROM productos p 
                          LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
                          WHERE p.estado = 1 ORDER BY p.nombre ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_bodega       = $idBC;
    $id_proveedor    = post('id_proveedor');
    $numero_oc       = trim(post('numero_oc'));
    $numero_factura  = trim(post('numero_factura'));
    $fecha_factura   = post('fecha_factura');
    $fecha_recepcion = post('fecha_recepcion');
    $estado          = post('estado', 'ingresada');
    $observacion     = post('observacion');

    $items_producto    = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_descripcion = isset($_POST['item_descripcion']) ? $_POST['item_descripcion'] : array();
    $items_cantidad    = isset($_POST['item_cantidad']) ? $_POST['item_cantidad'] : array();
    $items_precio      = isset($_POST['item_precio']) ? $_POST['item_precio'] : array();

    if ($id_proveedor === '' || $numero_factura === '' || $fecha_factura === '') {
        $error = 'Proveedor, número de factura y fecha son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM facturas WHERE id_proveedor = ? AND numero_factura = ? LIMIT 1");
        $stmt->execute(array((int)$id_proveedor, $numero_factura));
        if ($stmt->fetch()) {
            $error = 'Ya existe una factura con ese número para el proveedor seleccionado.';
        } else {
            $detalleLimpio = array();
            $monto_neto = 0;

            for ($i = 0; $i < count($items_producto); $i++) {
                $id_producto = isset($items_producto[$i]) ? (int)$items_producto[$i] : 0;
                $desc        = isset($items_descripcion[$i]) ? trim($items_descripcion[$i]) : '';
                $cant        = isset($items_cantidad[$i]) ? (float)$items_cantidad[$i] : 0;
                $precio      = isset($items_precio[$i]) ? (float)$items_precio[$i] : 0;

                if ($id_producto > 0 && $cant > 0) {
                    $subtotal = $cant * $precio;
                    $monto_neto += $subtotal;

                    if ($desc === '') {
                        foreach ($productos as $prd) {
                            if ($prd['id'] == $id_producto) { $desc = $prd['nombre']; break; }
                        }
                    }

                    $detalleLimpio[] = array(
                        'id_producto' => $id_producto,
                        'descripcion_item' => $desc,
                        'cantidad' => $cant,
                        'precio_unitario' => $precio,
                        'subtotal' => $subtotal
                    );
                }
            }

            if (!$detalleLimpio) {
                $error = 'Debes agregar al menos un producto con cantidad mayor a cero.';
            } else {
                $monto_iva = round($monto_neto * 0.19, 2);
                $monto_total = $monto_neto + $monto_iva;

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO facturas 
                        (id_bodega, id_proveedor, id_orden_compra, numero_oc, numero_factura, fecha_factura, fecha_recepcion,
                        monto_neto, monto_iva, monto_total, estado, observacion, created_by) 
                        VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute(array(
                        $id_bodega, (int)$id_proveedor,
                        ($numero_oc !== '' ? $numero_oc : null),
                        $numero_factura, $fecha_factura,
                        ($fecha_recepcion !== '' ? $fecha_recepcion : null),
                        $monto_neto, $monto_iva, $monto_total,
                        $estado, $observacion,
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                    ));
                    $id_factura = $pdo->lastInsertId();

                    $stmtDetalle = $pdo->prepare("INSERT INTO facturas_detalle (id_factura, id_producto, descripcion_item, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtMov = $pdo->prepare("
                        INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario) 
                        VALUES (?, ?, 'entrada_compra', ?, ?, ?, 'factura', ?, ?, ?)
                    ");

                    foreach ($detalleLimpio as $item) {
                        $stmtDetalle->execute(array($id_factura, $item['id_producto'], $item['descripcion_item'], $item['cantidad'], $item['precio_unitario'], $item['subtotal']));

                        if ($estado === 'ingresada') {
                            $stmtMov->execute(array(
                                $id_bodega, $item['id_producto'],
                                $item['cantidad'], $item['precio_unitario'], $item['subtotal'],
                                $id_factura,
                                'Ingreso por factura N° ' . $numero_factura,
                                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                            ));

                            $stmtStock = $pdo->prepare("SELECT id FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
                            $stmtStock->execute(array($id_bodega, $item['id_producto']));
                            $sb = $stmtStock->fetch();

                            if ($sb) {
                                $pdo->prepare("UPDATE stock_bodega SET stock_actual = stock_actual + ?, costo_promedio = ? WHERE id = ?")
                                    ->execute(array($item['cantidad'], $item['precio_unitario'], (int)$sb['id']));
                            } else {
                                $pdo->prepare("INSERT INTO stock_bodega (id_bodega, id_producto, stock_actual, costo_promedio) VALUES (?, ?, ?, ?)")
                                    ->execute(array($id_bodega, $item['id_producto'], $item['cantidad'], $item['precio_unitario']));
                            }
                        }
                    }

                    $pdo->commit();
                    set_flash('success', 'Factura registrada. Stock actualizado.');
                    redirect('facturas_ver.php?id=' . $id_factura);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error al guardar: ' . $e->getMessage();
                }
            }
        }
    }
}

// Array JS para búsqueda
$productosJS = array();
foreach ($productos as $p) {
    $productosJS[] = array(
        'id' => (int)$p['id'],
        'codigo' => $p['codigo'],
        'nombre' => $p['nombre'],
        'unidad' => $p['unidad'] ?: '',
        'activo_fijo' => (int)$p['activo_fijo'],
        'ultimo_costo' => (float)$p['ultimo_costo']
    );
}

$pageTitle = 'Nueva Factura';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold"><i class="bi bi-receipt text-primary me-2"></i>Ingresar Nueva Factura</h1>
        <small class="text-muted">Los productos ingresan al stock de la bodega central automáticamente</small>
    </div>
    <a href="facturas_lista.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post" id="formFactura">

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white py-2 border-0">
        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-file-earmark-text me-1"></i> Datos de la Factura</h6>
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold small text-secondary text-uppercase">Bodega Destino</label>
                <input type="text" class="form-control bg-light" value="<?php echo h($bodegaCentral['nombre']); ?> (<?php echo h($bodegaCentral['codigo']); ?>)" readonly>
                <small class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Bodega central por defecto</small>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small text-secondary text-uppercase">Proveedor <span class="text-danger">*</span></label>
                <select name="id_proveedor" class="form-select" required>
                    <option value="">Seleccione proveedor...</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo (post('id_proveedor') == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo h($p['razon_social']); ?> <?php echo $p['rut'] ? '- ' . h($p['rut']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small text-secondary text-uppercase">N° Factura <span class="text-danger">*</span></label>
                <input type="text" name="numero_factura" value="<?php echo h(post('numero_factura')); ?>" class="form-control" placeholder="Ej: 12345" required>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold small text-secondary text-uppercase">Fecha Emisión <span class="text-danger">*</span></label>
                <input type="date" name="fecha_factura" value="<?php echo h(post('fecha_factura', date('Y-m-d'))); ?>" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-secondary text-uppercase">Fecha Recepción</label>
                <input type="date" name="fecha_recepcion" value="<?php echo h(post('fecha_recepcion', date('Y-m-d'))); ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-secondary text-uppercase">N° OC (ref.)</label>
                <input type="text" name="numero_oc" value="<?php echo h(post('numero_oc')); ?>" class="form-control" placeholder="Opcional">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-secondary text-uppercase">Estado</label>
                <select name="estado" class="form-select">
                    <option value="ingresada">Ingresada (afecta stock)</option>
                    <option value="borrador">Borrador (no afecta)</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold small text-secondary text-uppercase">Observación</label>
                <textarea name="observacion" class="form-control" rows="1" placeholder="Opcional..."><?php echo h(post('observacion')); ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- SELECTOR + DETALLE -->
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-2 border-0">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-search me-1"></i> Buscar y agregar productos</h6>
            </div>
            <div class="card-body p-2">
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text bg-primary text-white border-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscadorProducto" class="form-control border-primary" 
                           placeholder="Escribe código o nombre..." autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary" id="btnLimpiarBuscador" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="small text-muted mb-2 px-1">
                    <i class="bi bi-lightbulb me-1"></i>Click o <kbd>Enter</kbd> para agregar. Si ya existe, suma cantidad.
                </div>
                <div id="listaProductos" style="max-height: 420px; overflow-y: auto;" class="border rounded bg-light"></div>
                <div class="small text-muted mt-2 text-center">
                    <span id="contadorProductos">0</span> producto(s) disponibles
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-2 border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-cart-check me-1"></i> Detalle de la Factura</h6>
                <span class="badge bg-primary" id="badgeItems">0 ítems</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="tablaDetalle">
                        <thead class="table-light">
                            <tr class="small text-secondary text-uppercase">
                                <th class="px-2" style="width: 40%;">Producto</th>
                                <th class="text-end" style="width: 18%;">Cantidad</th>
                                <th class="text-end" style="width: 20%;">P. Unit.</th>
                                <th class="text-end" style="width: 17%;">Subtotal</th>
                                <th class="text-center px-2" style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="detalleBody">
                            <tr id="filaVacia">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-arrow-left-circle display-5 d-block mb-2 opacity-50"></i>
                                    Busca productos en el panel izquierdo y agrégalos
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-5 ms-auto">
        <div class="card shadow-sm border-0 bg-primary bg-opacity-10">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold text-secondary">Neto:</span>
                    <span class="fw-bold">$ <span id="resumenNeto">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold text-secondary">IVA (19%):</span>
                    <span class="fw-bold">$ <span id="resumenIva">0</span></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fs-5">
                    <span class="fw-bold text-dark">Total:</span>
                    <span class="fw-bold text-primary">$ <span id="resumenTotal">0</span></span>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3 py-2 fw-semibold" id="btnGuardar">
                    <i class="bi bi-check-circle me-2"></i>Registrar e Ingresar a Stock
                </button>
            </div>
        </div>
    </div>
</div>

</form>

<style>
.producto-item {
    cursor: pointer;
    transition: background .15s;
    padding: 6px 10px;
    border-bottom: 1px solid #e9ecef;
}
.producto-item:hover { background: #e7f1ff; }
.producto-item.ya-agregado { background: #d1e7dd; }
.producto-item .btn-add { opacity: 0.7; transition: all .15s; font-size: 1.35rem; }
.producto-item:hover .btn-add { opacity: 1; transform: scale(1.15); }
kbd { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 3px; padding: 1px 5px; font-size: 0.75em; }
</style>

<script>
(function() {
    var PRODUCTOS = <?php echo json_encode($productosJS); ?>;
    var buscador = document.getElementById('buscadorProducto');
    var btnLimpiar = document.getElementById('btnLimpiarBuscador');
    var listaDiv = document.getElementById('listaProductos');
    var contadorSpan = document.getElementById('contadorProductos');
    var detalleBody = document.getElementById('detalleBody');
    var filaVacia = document.getElementById('filaVacia');
    var badgeItems = document.getElementById('badgeItems');
    var agregados = {};

    function formatoCLP(n) { return Math.round(n).toLocaleString('es-CL'); }
    function normalizar(s) { return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }
    function escapeHtml(s) {
        return (s||'').toString().replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function renderLista(filtro) {
        var q = normalizar(filtro);
        var html = '';
        var count = 0;
        var primerVisible = null;

        for (var i = 0; i < PRODUCTOS.length; i++) {
            var p = PRODUCTOS[i];
            var texto = normalizar(p.codigo + ' ' + p.nombre);
            if (q === '' || texto.indexOf(q) !== -1) {
                count++;
                if (!primerVisible) primerVisible = p;
                var yaAgr = agregados[p.id] ? 'ya-agregado' : '';
                var icono = p.activo_fijo 
                    ? '<i class="bi bi-building text-info me-1" title="Activo fijo"></i>' 
                    : '<i class="bi bi-basket text-secondary me-1"></i>';
                var costoStr = p.ultimo_costo > 0 ? '<small class="text-muted">~$' + formatoCLP(p.ultimo_costo) + '</small>' : '';
                var checkIcon = agregados[p.id] 
                    ? '<i class="bi bi-check-circle-fill text-success btn-add"></i>' 
                    : '<i class="bi bi-plus-circle-fill text-primary btn-add"></i>';
                
                html += '<div class="producto-item d-flex justify-content-between align-items-center ' + yaAgr + '" data-id="' + p.id + '">';
                html += '<div style="min-width:0; flex:1;">';
                html += '<div class="small text-truncate">' + icono + '<span class="badge bg-white text-dark border font-monospace me-1">' + escapeHtml(p.codigo) + '</span><strong>' + escapeHtml(p.nombre) + '</strong></div>';
                html += '<div class="small text-muted">' + (p.unidad ? escapeHtml(p.unidad) : '—') + ' ' + costoStr + '</div>';
                html += '</div>';
                html += '<div class="ms-2">' + checkIcon + '</div>';
                html += '</div>';
            }
        }

        if (count === 0) {
            html = '<div class="text-center text-muted py-4 small"><i class="bi bi-inbox display-6 d-block mb-2"></i>Sin resultados para "<strong>' + escapeHtml(filtro) + '</strong>"</div>';
        }

        listaDiv.innerHTML = html;
        contadorSpan.textContent = count;

        var items = listaDiv.querySelectorAll('.producto-item');
        for (var j = 0; j < items.length; j++) {
            items[j].onclick = function() { agregarProducto(parseInt(this.getAttribute('data-id'))); };
        }

        return primerVisible;
    }

    function buscarProducto(id) {
        for (var i = 0; i < PRODUCTOS.length; i++) { if (PRODUCTOS[i].id === id) return PRODUCTOS[i]; }
        return null;
    }

    function agregarProducto(id) {
        var p = buscarProducto(id);
        if (!p) return;

        if (agregados[id]) {
            var inputCant = agregados[id].querySelector('.item-cantidad');
            inputCant.value = (parseFloat(inputCant.value || 0) + 1).toFixed(2);
            recalcularFila(agregados[id]);
            destacarFila(agregados[id]);
            return;
        }

        if (filaVacia) filaVacia.style.display = 'none';

        var tr = document.createElement('tr');
        tr.setAttribute('data-pid', id);
        tr.innerHTML = ''
            + '<td class="px-2">'
            + '<input type="hidden" name="item_id_producto[]" value="' + p.id + '">'
            + '<input type="hidden" name="item_descripcion[]" value="' + escapeHtml(p.nombre) + '">'
            + '<div class="small"><span class="badge bg-light text-dark border font-monospace">' + escapeHtml(p.codigo) + '</span></div>'
            + '<div class="small fw-semibold text-dark text-truncate" style="max-width:260px;" title="' + escapeHtml(p.nombre) + '">' + escapeHtml(p.nombre) + '</div>'
            + '<div class="small text-muted">' + (p.unidad ? escapeHtml(p.unidad) : '—') + '</div>'
            + '</td>'
            + '<td><input type="number" step="0.01" min="0.01" name="item_cantidad[]" value="1" class="form-control form-control-sm item-cantidad text-end"></td>'
            + '<td><input type="number" step="0.01" min="0" name="item_precio[]" value="' + (p.ultimo_costo || 0) + '" class="form-control form-control-sm item-precio text-end"></td>'
            + '<td class="text-end fw-bold item-subtotal">0</td>'
            + '<td class="text-center px-2"><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila" title="Quitar"><i class="bi bi-x-lg"></i></button></td>';

        detalleBody.appendChild(tr);
        agregados[id] = tr;

        tr.querySelector('.item-cantidad').oninput = function() { recalcularFila(tr); };
        tr.querySelector('.item-precio').oninput = function() { recalcularFila(tr); };
        tr.querySelector('.btn-eliminar-fila').onclick = function() {
            tr.remove();
            delete agregados[id];
            if (Object.keys(agregados).length === 0 && filaVacia) filaVacia.style.display = '';
            actualizarTotales();
            renderLista(buscador.value);
        };

        recalcularFila(tr);
        destacarFila(tr);
        renderLista(buscador.value);
    }

    function destacarFila(tr) {
        tr.style.transition = 'background .4s';
        tr.style.background = '#fff3cd';
        setTimeout(function() { tr.style.background = ''; }, 600);
    }

    function recalcularFila(tr) {
        var c = parseFloat(tr.querySelector('.item-cantidad').value || 0);
        var p = parseFloat(tr.querySelector('.item-precio').value || 0);
        tr.querySelector('.item-subtotal').textContent = formatoCLP(c * p);
        actualizarTotales();
    }

    function actualizarTotales() {
        var neto = 0;
        var filas = detalleBody.querySelectorAll('tr[data-pid]');
        for (var i = 0; i < filas.length; i++) {
            var c = parseFloat(filas[i].querySelector('.item-cantidad').value || 0);
            var p = parseFloat(filas[i].querySelector('.item-precio').value || 0);
            neto += c * p;
        }
        var iva = Math.round(neto * 0.19);
        document.getElementById('resumenNeto').textContent = formatoCLP(neto);
        document.getElementById('resumenIva').textContent = formatoCLP(iva);
        document.getElementById('resumenTotal').textContent = formatoCLP(neto + iva);
        badgeItems.textContent = filas.length + ' ítem' + (filas.length === 1 ? '' : 's');
    }

    buscador.oninput = function() { renderLista(this.value); };
    buscador.onkeydown = function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var primero = renderLista(this.value);
            if (primero) {
                agregarProducto(primero.id);
                this.value = '';
                renderLista('');
                this.focus();
            }
        } else if (e.key === 'Escape') {
            this.value = '';
            renderLista('');
        }
    };
    btnLimpiar.onclick = function() {
        buscador.value = '';
        renderLista('');
        buscador.focus();
    };

    document.getElementById('formFactura').onsubmit = function(e) {
        if (Object.keys(agregados).length === 0) {
            e.preventDefault();
            alert('Debes agregar al menos un producto a la factura.');
            buscador.focus();
            return false;
        }
    };

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA')) {
            e.preventDefault();
            buscador.focus();
            buscador.select();
        }
    });

    renderLista('');
    setTimeout(function() { buscador.focus(); }, 100);
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>