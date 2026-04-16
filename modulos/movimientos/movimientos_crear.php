<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_login();

$error    = '';
$bodegas  = $pdo->query("SELECT id, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre")->fetchAll();
$productos = $pdo->query("
    SELECT p.id, p.codigo, p.nombre, um.nombre AS unidad_nombre
    FROM productos p LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    WHERE p.estado = 1 AND p.controla_stock = 1 ORDER BY p.nombre
")->fetchAll();

$stockRows = $pdo->query("SELECT id_bodega, id_producto, stock_actual FROM stock_bodega")->fetchAll();
$stockMap  = array();
foreach ($stockRows as $r) {
    $stockMap[$r['id_bodega'] . '_' . $r['id_producto']] = (float)$r['stock_actual'];
}

$pre_tipo     = get('tipo', 'salida_consumo');
$pre_bodega   = get('id_bodega', '');
$pre_producto = get('id_producto', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo        = post('tipo_movimiento');
    $id_bodega   = post('id_bodega');
    $id_destino  = post('id_bodega_destino');
    $obs         = post('observacion');
    $items_prod  = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_cant  = isset($_POST['item_cantidad'])    ? $_POST['item_cantidad']    : array();
    $items_precio= isset($_POST['item_precio'])      ? $_POST['item_precio']      : array();

    $esTraslado = ($tipo === 'traslado');
    $tiposOK    = array('salida_consumo', 'ajuste_entrada', 'ajuste_salida', 'traslado');

    if (!in_array($tipo, $tiposOK) || $id_bodega === '') {
        $error = 'Tipo y bodega son obligatorios.';
    } elseif ($esTraslado && ($id_destino === '' || (int)$id_destino === (int)$id_bodega)) {
        $error = 'Traslado: selecciona bodega destino diferente a origen.';
    } else {
        $detalle = array();
        for ($i = 0; $i < count($items_prod); $i++) {
            $p  = (int)(isset($items_prod[$i])   ? $items_prod[$i]   : 0);
            $c  = (float)str_replace(',', '.', isset($items_cant[$i])   ? $items_cant[$i]   : '0');
            $pr = (float)str_replace(',', '.', isset($items_precio[$i]) ? $items_precio[$i] : '0');
            if ($p > 0 && $c > 0) {
                $detalle[] = array('p' => $p, 'c' => $c, 'pr' => $pr);
            }
        }

        if (!$detalle) {
            $error = 'Agrega al menos un producto con cantidad válida.';
        } else {
            $esSalida = in_array($tipo, array('salida_consumo', 'ajuste_salida', 'traslado'));
            if ($esSalida) {
                foreach ($detalle as $d) {
                    $stS = $pdo->prepare("SELECT stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
                    $stS->execute(array((int)$id_bodega, $d['p']));
                    $cur = (float)($stS->fetchColumn() ?: 0);
                    if ($cur < $d['c']) {
                        $nQ = $pdo->prepare("SELECT nombre FROM productos WHERE id = ?");
                        $nQ->execute(array($d['p']));
                        $error = 'Stock insuficiente: "' . $nQ->fetchColumn() . '". Disponible: ' . number_format($cur, 2, ',', '.');
                        break;
                    }
                }
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

                foreach ($detalle as $d) {
                    $total = $d['c'] * $d['pr'];

                    if ($esTraslado) {
                        $pdo->prepare("INSERT INTO movimientos_bodega (id_bodega,id_producto,tipo_movimiento,cantidad,precio_unitario,total,referencia_tipo,referencia_id,observacion,id_usuario) VALUES (?,?,'traslado_salida',?,?,?,'traslado',0,?,?)")
                            ->execute(array((int)$id_bodega, $d['p'], $d['c'], $d['pr'], $total, $obs, $uid));
                        $idM = (int)$pdo->lastInsertId();
                        $pdo->prepare("UPDATE movimientos_bodega SET referencia_id=? WHERE id=?")->execute(array($idM, $idM));
                        $pdo->prepare("UPDATE stock_bodega SET stock_actual=stock_actual-? WHERE id_bodega=? AND id_producto=?")->execute(array($d['c'], (int)$id_bodega, $d['p']));

                        $pdo->prepare("INSERT INTO movimientos_bodega (id_bodega,id_producto,tipo_movimiento,cantidad,precio_unitario,total,referencia_tipo,referencia_id,observacion,id_usuario) VALUES (?,?,'traslado_entrada',?,?,?,'traslado',?,?,?)")
                            ->execute(array((int)$id_destino, $d['p'], $d['c'], $d['pr'], $total, $idM, $obs, $uid));

                        $stD = $pdo->prepare("SELECT id FROM stock_bodega WHERE id_bodega=? AND id_producto=? LIMIT 1");
                        $stD->execute(array((int)$id_destino, $d['p']));
                        $sD = $stD->fetch();
                        if ($sD) {
                            $pdo->prepare("UPDATE stock_bodega SET stock_actual=stock_actual+? WHERE id=?")->execute(array($d['c'], $sD['id']));
                        } else {
                            $pdo->prepare("INSERT INTO stock_bodega (id_bodega,id_producto,stock_actual,costo_promedio) VALUES (?,?,?,?)")->execute(array((int)$id_destino, $d['p'], $d['c'], $d['pr']));
                        }
                    } else {
                        $esE = ($tipo === 'ajuste_entrada');
                        $pdo->prepare("INSERT INTO movimientos_bodega (id_bodega,id_producto,tipo_movimiento,cantidad,precio_unitario,total,referencia_tipo,referencia_id,observacion,id_usuario) VALUES (?,?,?,?,?,?,'manual',0,?,?)")
                            ->execute(array((int)$id_bodega, $d['p'], $tipo, $d['c'], $d['pr'], $total, $obs, $uid));
                        $idM = (int)$pdo->lastInsertId();
                        $pdo->prepare("UPDATE movimientos_bodega SET referencia_id=? WHERE id=?")->execute(array($idM, $idM));

                        $stS = $pdo->prepare("SELECT id, stock_actual FROM stock_bodega WHERE id_bodega=? AND id_producto=? LIMIT 1");
                        $stS->execute(array((int)$id_bodega, $d['p']));
                        $sS = $stS->fetch();
                        if ($sS) {
                            $pdo->prepare("UPDATE stock_bodega SET stock_actual=stock_actual+? WHERE id=?")->execute(array($esE ? $d['c'] : -$d['c'], $sS['id']));
                        } elseif ($esE) {
                            $pdo->prepare("INSERT INTO stock_bodega (id_bodega,id_producto,stock_actual,costo_promedio) VALUES (?,?,?,?)")->execute(array((int)$id_bodega, $d['p'], $d['c'], $d['pr']));
                        }
                    }
                }

                $pdo->commit();
                set_flash('success', count($detalle) . ' producto(s) registrado(s) correctamente.');
                redirect('movimientos_lista.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }

    $pre_tipo   = $tipo;
    $pre_bodega = $id_bodega;
}

$pageTitle = 'Nuevo Movimiento';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-arrow-left-right text-primary me-2"></i>Nuevo Movimiento</h1>
    <a href="movimientos_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Historial</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post" id="formMov">

    <!-- TIPO -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0"><h5 class="mb-0 fw-bold">Tipo de Movimiento</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <?php
                $tipos_mov = array(
                    'salida_consumo' => array('icon'=>'bi-box-arrow-right','color'=>'danger', 'label'=>'Salida por Consumo',     'desc'=>'Entrega para uso interno'),
                    'traslado'       => array('icon'=>'bi-arrow-repeat',   'color'=>'primary','label'=>'Traslado entre Bodegas', 'desc'=>'Mover stock a otra bodega'),
                    'ajuste_entrada' => array('icon'=>'bi-plus-circle',    'color'=>'success','label'=>'Ajuste Entrada',         'desc'=>'Corrección positiva'),
                    'ajuste_salida'  => array('icon'=>'bi-dash-circle',    'color'=>'warning','label'=>'Ajuste Salida',          'desc'=>'Corrección negativa'),
                );
                foreach ($tipos_mov as $val => $t): ?>
                <div class="col-6 col-md-3">
                    <input type="radio" class="btn-check" name="tipo_movimiento" id="tipo_<?php echo $val; ?>" value="<?php echo $val; ?>" autocomplete="off" required <?php echo ($pre_tipo === $val) ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-<?php echo $t['color']; ?> w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 gap-2 text-center"
                           for="tipo_<?php echo $val; ?>" style="min-height:110px;border-width:2px;cursor:pointer;">
                        <i class="bi <?php echo $t['icon']; ?> fs-2"></i>
                        <span class="fw-bold" style="font-size:.82rem;"><?php echo $t['label']; ?></span>
                        <span class="text-muted" style="font-size:.7rem;"><?php echo $t['desc']; ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- BODEGAS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0"><h5 class="mb-0 fw-bold">Bodegas</h5></div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">Bodega Origen <span class="text-danger">*</span></label>
                    <select name="id_bodega" id="id_bodega" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>" <?php echo ((string)$pre_bodega === (string)$b['id']) ? 'selected' : ''; ?>><?php echo h($b['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="wrap-destino" style="display:none;">
                    <label class="form-label fw-bold text-secondary">Bodega Destino <span class="text-danger">*</span></label>
                    <select name="id_bodega_destino" id="id_bodega_destino" class="form-select">
                        <option value="">Seleccione</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>"><?php echo h($b['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- PRODUCTOS (CARRITO) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Productos</h5>
            <button type="button" id="btnAdd" class="btn btn-sm btn-success"><i class="bi bi-plus-lg me-1"></i> Agregar Producto</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary" style="font-size:.85rem;">
                        <tr>
                            <th class="px-3 py-3" style="min-width:260px;">PRODUCTO</th>
                            <th class="py-3 text-center" style="width:110px;">DISPONIBLE</th>
                            <th class="py-3" style="width:120px;">CANTIDAD</th>
                            <th class="py-3" style="width:140px;">PRECIO UNIT.</th>
                            <th class="py-3 text-center" style="width:50px;"><i class="bi bi-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr>
                            <td class="px-3">
                                <select name="item_id_producto[]" class="form-select form-select-sm item-producto">
                                    <option value="">— Seleccione —</option>
                                    <?php foreach ($productos as $pr): ?>
                                        <option value="<?php echo (int)$pr['id']; ?>"
                                            data-unidad="<?php echo h($pr['unidad_nombre']); ?>"
                                            <?php echo ((string)$pre_producto === (string)$pr['id']) ? 'selected' : ''; ?>>
                                            <?php echo h($pr['codigo'] . ' — ' . $pr['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <span class="item-stock badge bg-secondary bg-opacity-10 text-secondary" style="display:none;font-size:.75rem;">—</span>
                            </td>
                            <td>
                                <input type="number" name="item_cantidad[]" class="form-control form-control-sm item-cantidad" value="1" step="0.01" min="0.01" required>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="item_precio[]" class="form-control item-precio" value="0" step="0.01" min="0">
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-del"><i class="bi bi-x-lg"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- OBSERVACION + SUBMIT -->
    <div class="row g-4 mb-5">
        <div class="col-md-8">
            <label class="form-label fw-bold text-secondary">Observación / Motivo</label>
            <textarea name="observacion" class="form-control" rows="2" placeholder="Ej: Entrega Dir. Obras, corrección inventario..."><?php echo isset($_POST['observacion']) ? h(post('observacion')) : ''; ?></textarea>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100 py-2" id="btnSubmit">
                <i class="bi bi-floppy me-2"></i>Registrar Movimiento
            </button>
        </div>
    </div>

</form>

<script>
(function () {
    var stockMap    = <?php echo json_encode($stockMap); ?>;
    var selOrigenEl = document.getElementById('id_bodega');
    var wrapDest    = document.getElementById('wrap-destino');
    var selDestino  = document.getElementById('id_bodega_destino');
    var itemsBody   = document.getElementById('itemsBody');
    var btnAdd      = document.getElementById('btnAdd');
    var btnSubmit   = document.getElementById('btnSubmit');

    var productoSelectHTML = document.querySelector('.item-producto').innerHTML;

    var btnLabels = {
        'salida_consumo': 'Registrar Salida',
        'traslado':       'Registrar Traslado',
        'ajuste_entrada': 'Registrar Ajuste Entrada',
        'ajuste_salida':  'Registrar Ajuste Salida'
    };

    function getTipo() {
        var c = document.querySelector('input[name="tipo_movimiento"]:checked');
        return c ? c.value : '';
    }

    function getStock(bodega, producto) {
        var key = bodega + '_' + producto;
        return stockMap.hasOwnProperty(key) ? parseFloat(stockMap[key]) : 0;
    }

    function updateRowStock(tr) {
        var bodega   = selOrigenEl.value;
        var producto = tr.querySelector('.item-producto').value;
        var el       = tr.querySelector('.item-stock');
        var tipo     = getTipo();
        var mostrar  = (tipo === 'salida_consumo' || tipo === 'ajuste_salida' || tipo === 'traslado');

        if (bodega && producto && mostrar) {
            var s = getStock(bodega, producto);
            el.style.display = '';
            el.textContent   = 'Disp: ' + s.toFixed(2);
            el.className     = 'item-stock badge ' + (s > 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger');
        } else {
            el.style.display = 'none';
        }
    }

    function updateAllStocks() {
        itemsBody.querySelectorAll('tr').forEach(function (tr) { updateRowStock(tr); });
    }

    function updateTipo() {
        var tipo = getTipo();
        var esT  = (tipo === 'traslado');
        wrapDest.style.display = esT ? '' : 'none';
        selDestino.required    = esT;
        btnSubmit.innerHTML    = '<i class="bi bi-floppy me-2"></i>' + (btnLabels[tipo] || 'Registrar Movimiento');
        updateAllStocks();
    }

    function enlazarFila(tr) {
        tr.querySelector('.item-producto').onchange = function () { updateRowStock(tr); };
        tr.querySelector('.btn-del').onclick = function () {
            if (itemsBody.querySelectorAll('tr').length > 1) { tr.remove(); }
        };
    }

    document.querySelectorAll('input[name="tipo_movimiento"]').forEach(function (r) {
        r.addEventListener('change', updateTipo);
    });

    selOrigenEl.addEventListener('change', updateAllStocks);

    btnAdd.onclick = function () {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="px-3"><select name="item_id_producto[]" class="form-select form-select-sm item-producto">' + productoSelectHTML + '</select></td>' +
            '<td class="text-center"><span class="item-stock badge bg-secondary bg-opacity-10 text-secondary" style="display:none;font-size:.75rem;">—</span></td>' +
            '<td><input type="number" name="item_cantidad[]" class="form-control form-control-sm item-cantidad" value="1" step="0.01" min="0.01" required></td>' +
            '<td><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" name="item_precio[]" class="form-control item-precio" value="0" step="0.01" min="0"></div></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-del"><i class="bi bi-x-lg"></i></button></td>';
        // reset selected
        Array.from(tr.querySelector('.item-producto').options).forEach(function (o) { o.removeAttribute('selected'); });
        itemsBody.appendChild(tr);
        enlazarFila(tr);
    };

    itemsBody.querySelectorAll('tr').forEach(enlazarFila);
    updateTipo();
    updateAllStocks();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>