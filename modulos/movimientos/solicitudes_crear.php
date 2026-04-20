<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega', 'solicitante'));

$error    = '';
$bodegas  = $pdo->query("SELECT id, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre")->fetchAll();
$productos = $pdo->query("
    SELECT p.id, p.codigo, p.nombre, um.nombre AS unidad_nombre
    FROM productos p LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    WHERE p.estado = 1 AND p.controla_stock = 1 ORDER BY p.nombre
")->fetchAll();

// Cargar el stock actual de todas las bodegas para usarlo en el formulario
$stockRows = $pdo->query("SELECT id_bodega, id_producto, stock_actual FROM stock_bodega")->fetchAll();
$stockMap  = array();
foreach ($stockRows as $r) {
    $stockMap[$r['id_bodega'] . '_' . $r['id_producto']] = (float)$r['stock_actual'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_origen  = post('id_bodega_origen');
    $id_destino = post('id_bodega_destino');
    $obs        = post('observacion');
    $items_prod = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_cant = isset($_POST['item_cantidad'])    ? $_POST['item_cantidad']    : array();
    $items_obs  = isset($_POST['item_obs'])         ? $_POST['item_obs']         : array();

    if ($id_destino === '') {
        $error = 'Bodega destino es obligatoria.';
    } elseif ($id_origen !== '' && (int)$id_origen === (int)$id_destino) {
        $error = 'Bodega origen y destino deben ser diferentes.';
    } else {
        $detalle = array();
        for ($i = 0; $i < count($items_prod); $i++) {
            $p  = (int)(isset($items_prod[$i]) ? $items_prod[$i] : 0);
            $c  = (float)str_replace(',', '.', isset($items_cant[$i]) ? $items_cant[$i] : '0');
            $io = isset($items_obs[$i]) ? trim($items_obs[$i]) : '';
            if ($p > 0 && $c > 0) {
                $detalle[] = array('p' => $p, 'c' => $c, 'obs' => $io);
            }
        }

        if (!$detalle) {
            $error = 'Agrega al menos un producto con cantidad válida.';
        } else {
            try {
                $pdo->beginTransaction();
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

                $pdo->prepare("INSERT INTO solicitudes (numero_solicitud, id_bodega_origen, id_bodega_destino, id_usuario, observacion) VALUES ('PENDIENTE', ?, ?, ?, ?)")
                    ->execute(array($id_origen !== '' ? (int)$id_origen : null, (int)$id_destino, $uid, $obs));
                $id_sol = (int)$pdo->lastInsertId();
                $numero = 'SOL-' . date('Y') . '-' . str_pad($id_sol, 5, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE solicitudes SET numero_solicitud = ? WHERE id = ?")->execute(array($numero, $id_sol));

                $stmtD = $pdo->prepare("INSERT INTO solicitudes_detalle (id_solicitud, id_producto, cantidad, observacion) VALUES (?,?,?,?)");
                foreach ($detalle as $d) {
                    $stmtD->execute(array($id_sol, $d['p'], $d['c'], $d['obs']));
                }

                $pdo->commit();
                set_flash('success', 'Solicitud ' . $numero . ' enviada. Queda pendiente de aprobación.');
                redirect('solicitudes_lista.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Nueva Solicitud de Traslado';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-clipboard-plus text-primary me-2"></i>Nueva Solicitud de Traslado</h1>
    <a href="solicitudes_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Mis Solicitudes</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0"><h5 class="mb-0 fw-bold">¿Dónde y hacia dónde?</h5></div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">Bodega Origen (stock disponible)</label>
                    <select name="id_bodega_origen" id="id_bodega_origen" class="form-select">
                        <option value="">Cualquiera / No especifica</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>"><?php echo h($b['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Si no sabes de dónde viene, déjalo en blanco.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary">Bodega / Destino Solicitado <span class="text-danger">*</span></label>
                    <select name="id_bodega_destino" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>"><?php echo h($b['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary">Motivo / Observación</label>
                    <textarea name="observacion" class="form-control" rows="2" placeholder="Ej: Reposición para proyecto X, mantenimiento, etc."><?php echo isset($_POST['observacion']) ? h(post('observacion')) : ''; ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Productos Solicitados</h5>
            <button type="button" id="btnAdd" class="btn btn-sm btn-success"><i class="bi bi-plus-lg me-1"></i> Agregar Producto</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary" style="font-size:.85rem;">
                         <tr>
                          <th class="px-3 py-3" style="min-width:260px;">PRODUCTO</th>
                          <th class="py-3 text-center" style="width:110px;">DISPONIBLE</th> <th class="py-3" style="width:120px;">CANTIDAD</th>
                          <th class="py-3">OBSERVACIÓN (opcional)</th>
                          <th class="py-3 text-center" style="width:50px;"><i class="bi bi-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr>
                            <td class="px-3">
                                <select name="item_id_producto[]" class="form-select form-select-sm item-producto" required>
                                    <option value="">— Seleccione —</option>
                                    <?php foreach ($productos as $pr): ?>
                                        <option value="<?php echo (int)$pr['id']; ?>" data-unidad="<?php echo h($pr['unidad_nombre']); ?>">
                                            <?php echo h($pr['codigo'] . ' — ' . $pr['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="item_cantidad[]" class="form-control form-control-sm item-cantidad" value="1" step="0.01" min="0.01" required>
                            </td>
                            <td>
                                <input type="text" name="item_obs[]" class="form-control form-control-sm" placeholder="Especificación adicional...">
                            </td>
                            <td class="text-center">
                                <span class="item-stock badge bg-secondary bg-opacity-10 text-secondary" style="display:none;font-size:.75rem;">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="solicitudes_lista.php" class="btn btn-light border px-4">Cancelar</a>
        <button type="submit" class="btn btn-primary px-5 py-2">
            <i class="bi bi-send me-2"></i>Enviar Solicitud
        </button>
    </div>

</form>

<script>
(function () {
    // Pasar el arreglo de PHP a Javascript
    var stockMap = <?php echo json_encode($stockMap); ?>;
    var selOrigenEl = document.getElementById('id_bodega_origen');
    var itemsBody = document.getElementById('itemsBody');
    var btnAdd    = document.getElementById('btnAdd');
    var productoSelectHTML = document.querySelector('.item-producto').innerHTML;

    // Función para obtener el stock del array
    function getStock(bodega, producto) {
        var key = bodega + '_' + producto;
        return stockMap.hasOwnProperty(key) ? parseFloat(stockMap[key]) : 0;
    }

    // Actualiza el badge visual en una fila específica
    function updateRowStock(tr) {
        var bodega   = selOrigenEl.value;
        var producto = tr.querySelector('.item-producto').value;
        var el       = tr.querySelector('.item-stock');

        // Solo muestra el stock si hay una bodega de origen y un producto seleccionados
        if (bodega && producto) {
            var s = getStock(bodega, producto);
            el.style.display = '';
            el.textContent   = 'Disp: ' + s.toFixed(2);
            el.className     = 'item-stock badge ' + (s > 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger');
        } else {
            el.style.display = 'none';
        }
    }

    // Actualiza todas las filas (útil cuando se cambia la bodega principal)
    function updateAllStocks() {
        itemsBody.querySelectorAll('tr').forEach(function (tr) { updateRowStock(tr); });
    }

    function enlazarFila(tr) {
        tr.querySelector('.item-producto').addEventListener('change', function () {
            updateRowStock(tr);
        });
        tr.querySelector('.btn-del').onclick = function () {
            if (itemsBody.querySelectorAll('tr').length > 1) tr.remove();
        };
    }

    // Escuchar cambios en el selector de la Bodega de Origen
    selOrigenEl.addEventListener('change', updateAllStocks);

    btnAdd.onclick = function () {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="px-3"><select name="item_id_producto[]" class="form-select form-select-sm item-producto" required>' + productoSelectHTML + '</select></td>' +
            '<td class="text-center"><span class="item-stock badge bg-secondary bg-opacity-10 text-secondary" style="display:none;font-size:.75rem;">—</span></td>' +
            '<td><input type="number" name="item_cantidad[]" class="form-control form-control-sm item-cantidad" value="1" step="0.01" min="0.01" required></td>' +
            '<td><input type="text" name="item_obs[]" class="form-control form-control-sm" placeholder="Especificación adicional..."></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-del"><i class="bi bi-x-lg"></i></button></td>';
        Array.from(tr.querySelector('.item-producto').options).forEach(function (o) { o.removeAttribute('selected'); });
        itemsBody.appendChild(tr);
        enlazarFila(tr);
    };

    itemsBody.querySelectorAll('tr').forEach(enlazarFila);
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>