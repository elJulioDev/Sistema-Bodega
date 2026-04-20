<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega'));

$error = '';

// Seguridad: asegurar tablas (legacy)
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

// ============================================================
// BLOQUEO POR ROL ENCARGADO
// ============================================================
// El encargado solo puede sacar stock desde SU bodega.
// No puede trasladar desde central u otras bodegas.
$encargadoBodegaId = 0;
$miBodega = null;
if (is_encargado()) {
    $encargadoBodegaId = user_bodega_id();
    if ($encargadoBodegaId <= 0) {
        set_flash('error', 'Tu usuario no tiene una bodega asignada. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
    $stmtMiBod = $pdo->prepare("SELECT id, codigo, nombre FROM bodegas WHERE id = ? AND estado = 1 LIMIT 1");
    $stmtMiBod->execute(array($encargadoBodegaId));
    $miBodega = $stmtMiBod->fetch();
    if (!$miBodega) {
        set_flash('error', 'Tu bodega asignada no existe o está inactiva.');
        redirect('/Bodega/index.php');
    }
}

// --- Datos base ---
$bodegas = $pdo->query("SELECT id, codigo, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

// Productos activos
$sqlProd = "
    SELECT DISTINCT p.id, p.codigo, p.nombre, um.nombre AS unidad_nombre, tp.nombre AS tipo_nombre
    FROM productos p
    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    LEFT JOIN tipos_producto tp  ON tp.id = p.id_tipo_producto
    WHERE p.estado = 1
    ORDER BY p.nombre ASC
";
$productos = $pdo->query($sqlProd)->fetchAll();

// Mapa de stock: { id_bodega: { id_producto: {stock, costo} } }
// Para encargado: solo su bodega (no necesita datos de otras)
if (is_encargado()) {
    $stmtStock = $pdo->prepare("
        SELECT id_bodega, id_producto, stock_actual, costo_promedio
        FROM stock_bodega
        WHERE id_bodega = ?
    ");
    $stmtStock->execute(array($encargadoBodegaId));
    $stockRows = $stmtStock->fetchAll();
} else {
    $stockRows = $pdo->query("SELECT id_bodega, id_producto, stock_actual, costo_promedio FROM stock_bodega")->fetchAll();
}

$stockMap = array();
foreach ($stockRows as $r) {
    $bi = (int)$r['id_bodega'];
    $pi = (int)$r['id_producto'];
    if (!isset($stockMap[$bi])) { $stockMap[$bi] = array(); }
    $stockMap[$bi][$pi] = array(
        'stock' => (float)$r['stock_actual'],
        'costo' => (float)$r['costo_promedio'],
    );
}

// Params iniciales desde URL
$origenPre   = is_encargado() ? $encargadoBodegaId : (int)get('id_bodega');
$productoPre = (int)get('id_producto');

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_bodega_origen  = (int)post('id_bodega_origen');
    $id_bodega_destino = (int)post('id_bodega_destino');
    $fecha             = trim(post('fecha'));
    $observacion       = trim(post('observacion'));

    // Forzar origen a bodega del encargado
    if (is_encargado()) {
        $id_bodega_origen = $encargadoBodegaId;
    }

    $items_producto = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_cantidad = isset($_POST['item_cantidad'])    ? $_POST['item_cantidad']    : array();

    if ($id_bodega_origen <= 0 || $id_bodega_destino <= 0 || $fecha === '') {
        $error = 'Bodega origen, destino y fecha son obligatorios.';
    } elseif ($id_bodega_origen === $id_bodega_destino) {
        $error = 'La bodega origen y destino deben ser distintas.';
    } elseif (is_encargado() && $id_bodega_origen !== $encargadoBodegaId) {
        $error = 'Solo puedes realizar traslados desde tu propia bodega.';
    } else {
        $detalleLimpio = array();
        $totalItems    = count($items_producto);

        for ($i = 0; $i < $totalItems; $i++) {
            $id_producto = isset($items_producto[$i]) ? (int)$items_producto[$i] : 0;
            $cantidad    = isset($items_cantidad[$i]) ? (float)str_replace(',', '.', $items_cantidad[$i]) : 0;

            if ($id_producto > 0 && $cantidad > 0) {
                $detalleLimpio[] = array(
                    'id_producto' => $id_producto,
                    'cantidad'    => $cantidad,
                );
            }
        }

        if (!$detalleLimpio) {
            $error = 'Debes seleccionar al menos un producto con cantidad mayor a 0.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO traspasos_bodega
                        (id_bodega_origen, id_bodega_destino, fecha, estado, observacion, created_by)
                    VALUES (?, ?, ?, 'completado', ?, ?)
                ");
                $stmt->execute(array(
                    $id_bodega_origen,
                    $id_bodega_destino,
                    $fecha,
                    ($observacion !== '' ? $observacion : null),
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                ));
                $id_traspaso = (int)$pdo->lastInsertId();

                $stmtDetalle = $pdo->prepare("
                    INSERT INTO traspasos_bodega_detalle
                        (id_traspaso, id_producto, descripcion_item, cantidad, costo_unitario, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmtMov = $pdo->prepare("
                    INSERT INTO movimientos_bodega
                        (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total,
                         referencia_tipo, referencia_id, fecha_movimiento, observacion, id_usuario)
                    VALUES (?, ?, ?, ?, ?, ?, 'traslado', ?, NOW(), ?, ?)
                ");

                $stmtStockOrigen = $pdo->prepare("
                    SELECT sb.id, sb.stock_actual, sb.costo_promedio, p.nombre
                    FROM stock_bodega sb
                    INNER JOIN productos p ON p.id = sb.id_producto
                    WHERE sb.id_bodega = ? AND sb.id_producto = ? LIMIT 1
                ");
                $stmtStockDestino = $pdo->prepare("
                    SELECT id, stock_actual, costo_promedio FROM stock_bodega
                    WHERE id_bodega = ? AND id_producto = ? LIMIT 1
                ");
                $stmtUpdateStock = $pdo->prepare("
                    UPDATE stock_bodega SET stock_actual = ?, costo_promedio = ? WHERE id = ?
                ");
                $stmtInsertStock = $pdo->prepare("
                    INSERT INTO stock_bodega (id_bodega, id_producto, stock_actual, costo_promedio)
                    VALUES (?, ?, ?, ?)
                ");

                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

                foreach ($detalleLimpio as $it) {
                    $id_producto = $it['id_producto'];
                    $cantidad    = $it['cantidad'];

                    $stmtStockOrigen->execute(array($id_bodega_origen, $id_producto));
                    $stockOrigen = $stmtStockOrigen->fetch();

                    if (!$stockOrigen) {
                        throw new Exception('Producto ID ' . $id_producto . ' no existe en la bodega origen.');
                    }
                    if ((float)$stockOrigen['stock_actual'] < $cantidad) {
                        throw new Exception('Stock insuficiente para "' . $stockOrigen['nombre'] . '" (disp: ' . $stockOrigen['stock_actual'] . ', pedido: ' . $cantidad . ').');
                    }

                    $costoUnitario = (float)$stockOrigen['costo_promedio'];
                    $subtotal      = $cantidad * $costoUnitario;

                    $stmtDetalle->execute(array(
                        $id_traspaso,
                        $id_producto,
                        $stockOrigen['nombre'],
                        $cantidad,
                        $costoUnitario,
                        $subtotal,
                    ));

                    $nuevoStockOrigen = (float)$stockOrigen['stock_actual'] - $cantidad;
                    $stmtUpdateStock->execute(array($nuevoStockOrigen, $costoUnitario, (int)$stockOrigen['id']));

                    $stmtStockDestino->execute(array($id_bodega_destino, $id_producto));
                    $stockDestino = $stmtStockDestino->fetch();

                    if ($stockDestino) {
                        $nuevoStockDestino = (float)$stockDestino['stock_actual'] + $cantidad;
                        $stmtUpdateStock->execute(array($nuevoStockDestino, $costoUnitario, (int)$stockDestino['id']));
                    } else {
                        $stmtInsertStock->execute(array($id_bodega_destino, $id_producto, $cantidad, $costoUnitario));
                    }

                    $stmtMov->execute(array(
                        $id_bodega_origen, $id_producto, 'traslado_salida', $cantidad, $costoUnitario, $subtotal,
                        $id_traspaso, 'Salida por traslado a bodega ID ' . $id_bodega_destino, $uid,
                    ));
                    $stmtMov->execute(array(
                        $id_bodega_destino, $id_producto, 'traslado_entrada', $cantidad, $costoUnitario, $subtotal,
                        $id_traspaso, 'Entrada por traslado desde bodega ID ' . $id_bodega_origen, $uid,
                    ));
                }

                $pdo->commit();
                set_flash('success', 'Traslado #' . $id_traspaso . ' registrado correctamente.');
                redirect('movimientos_ver.php?id=' . $id_traspaso);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Nuevo Traslado entre Bodegas';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="text-muted small mb-1">
            <a href="movimientos_lista.php" class="text-decoration-none text-muted">
                <i class="bi bi-chevron-left"></i> Movimientos
            </a>
        </div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>Nuevo Traslado entre Bodegas
        </h1>
        <p class="text-muted mb-0 small mt-1">
            <?php if (is_encargado()): ?>
                Saca stock de tu bodega y trasládalo a otra bodega.
            <?php else: ?>
                Selecciona bodega origen, bodega destino y marca los productos a trasladar.
            <?php endif; ?>
        </p>
    </div>
    <a href="movimientos_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Cancelar
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger d-flex align-items-start">
        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
        <div><?php echo h($error); ?></div>
    </div>
<?php endif; ?>

<form method="post" id="formTraslado" autocomplete="off">

    <!-- PASO 1: Origen -> Destino -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center">
            <span class="badge bg-primary rounded-circle me-2" style="width:28px;height:28px;line-height:20px;">1</span>
            <h5 class="mb-0 fw-bold">¿Desde dónde y hacia dónde?</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-stretch">

                <!-- ORIGEN -->
                <div class="col-md-5">
                    <label class="form-label fw-bold text-danger small text-uppercase" style="letter-spacing:.5px;">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Bodega Origen
                    </label>

                    <?php if (is_encargado()): ?>
                        <!-- Encargado: origen bloqueado a su bodega -->
                        <div class="form-control form-control-lg bg-light d-flex align-items-center justify-content-between" style="min-height:58px;">
                            <div>
                                <div class="fw-bold text-dark"><?php echo h($miBodega['nombre']); ?></div>
                                <small class="text-muted"><?php echo h($miBodega['codigo']); ?> · Tu bodega</small>
                            </div>
                            <i class="bi bi-lock-fill text-muted"></i>
                        </div>
                        <input type="hidden" name="id_bodega_origen" id="selOrigen" value="<?php echo (int)$encargadoBodegaId; ?>">
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Como encargado, solo puedes sacar stock desde tu bodega.
                        </div>
                    <?php else: ?>
                        <select name="id_bodega_origen" id="selOrigen" class="form-select form-select-lg" required>
                            <option value="">Seleccione una bodega…</option>
                            <?php foreach ($bodegas as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>"
                                    <?php echo ((int)post('id_bodega_origen') === (int)$b['id'] || (!post('id_bodega_origen') && $origenPre === (int)$b['id'])) ? 'selected' : ''; ?>>
                                    <?php echo h($b['nombre'] . ' (' . $b['codigo'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><i class="bi bi-info-circle me-1"></i>Stock se descuenta desde aquí.</div>
                    <?php endif; ?>
                </div>

                <div class="col-md-2 d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-arrow-right-circle-fill text-primary" style="font-size:2.5rem;"></i>
                    <span class="small text-muted fw-bold">TRASLADO</span>
                </div>

                <!-- DESTINO -->
                <div class="col-md-5">
                    <label class="form-label fw-bold text-success small text-uppercase" style="letter-spacing:.5px;">
                        <i class="bi bi-box-arrow-in-down-left me-1"></i>Bodega Destino
                    </label>
                    <select name="id_bodega_destino" id="selDestino" class="form-select form-select-lg" required>
                        <option value="">Seleccione una bodega…</option>
                        <?php foreach ($bodegas as $b):
                            // Encargado no puede trasladar a su propia bodega
                            if (is_encargado() && (int)$b['id'] === $encargadoBodegaId) continue;
                        ?>
                            <option value="<?php echo (int)$b['id']; ?>" <?php echo ((int)post('id_bodega_destino') === (int)$b['id']) ? 'selected' : ''; ?>>
                                <?php echo h($b['nombre'] . ' (' . $b['codigo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Stock entra a esta bodega.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-secondary small">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="<?php echo h(post('fecha', date('Y-m-d'))); ?>" required>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-bold text-secondary small">Observación <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" name="observacion" class="form-control" maxlength="500"
                           placeholder="Motivo del traslado..." value="<?php echo h(post('observacion')); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 2: Productos -->
    <div class="card shadow-sm border-0 mb-4" id="cardProductos">
        <div class="card-header bg-white border-0 pt-3 pb-2 d-flex align-items-center flex-wrap gap-2">
            <span class="badge bg-primary rounded-circle me-2" style="width:28px;height:28px;line-height:20px;">2</span>
            <h5 class="mb-0 fw-bold me-auto">Seleccionar productos</h5>

            <div class="input-group" style="max-width: 280px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-secondary"></i></span>
                <input type="text" id="buscadorProductos" class="form-control border-start-0 ps-0" placeholder="Buscar producto...">
            </div>

            <span class="badge bg-light text-dark border" id="badgeDisponibles">0 disponibles</span>
        </div>

        <div id="mensajeOrigen" class="card-body text-center text-muted py-5" style="<?php echo is_encargado() ? 'display:none;' : ''; ?>">
            <i class="bi bi-arrow-up fs-1 d-block mb-2 text-primary"></i>
            <div class="fw-bold mb-1">Selecciona primero una bodega origen</div>
            <div class="small">Luego verás aquí los productos disponibles para trasladar.</div>
        </div>

        <div id="sinProductos" class="card-body text-center text-muted py-5" style="display:none;">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <div class="fw-bold mb-1">No hay productos con stock</div>
            <div class="small">Esta bodega no tiene productos con stock disponible.</div>
        </div>

        <div id="contenidoProductos" style="display:none;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-secondary">
                            <th class="px-3" style="width:5%;"><input type="checkbox" id="chkTodos" class="form-check-input" title="Marcar todos"></th>
                            <th style="width:12%;">Código</th>
                            <th>Producto</th>
                            <th style="width:12%;" class="text-end">Stock Disp.</th>
                            <th style="width:12%;" class="text-end">Costo</th>
                            <th style="width:18%;" class="text-center">Cantidad a Trasladar</th>
                            <th style="width:14%;" class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyProductos"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FOOTER STICKY -->
    <div class="card shadow-sm border-0 mb-5" style="position:sticky; bottom:0; z-index:5;">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="d-flex gap-4 flex-wrap">
                <div>
                    <div class="text-muted small text-uppercase fw-bold" style="font-size:.65rem;letter-spacing:.5px;">Productos</div>
                    <div class="h5 mb-0 fw-bold" id="resumenItems">0</div>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold" style="font-size:.65rem;letter-spacing:.5px;">Cantidad total</div>
                    <div class="h5 mb-0 fw-bold text-primary" id="resumenCantidad">0,00</div>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold" style="font-size:.65rem;letter-spacing:.5px;">Valor</div>
                    <div class="h5 mb-0 fw-bold text-success" id="resumenTotal">$ 0</div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="movimientos_lista.php" class="btn btn-light border px-4">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-lg px-4" id="btnConfirmar" disabled>
                    <i class="bi bi-check-lg me-1"></i> Confirmar Traslado
                </button>
            </div>
        </div>
    </div>

</form>

<script>
(function () {
    var stockMap    = <?php echo json_encode($stockMap); ?>;
    var productos   = <?php echo json_encode($productos); ?>;
    var productoPre = <?php echo (int)$productoPre; ?>;
    var esEncargado = <?php echo is_encargado() ? 'true' : 'false'; ?>;

    var selOrigen      = document.getElementById('selOrigen');
    var selDestino     = document.getElementById('selDestino');
    var buscador       = document.getElementById('buscadorProductos');
    var tbody          = document.getElementById('tbodyProductos');
    var mensajeOrigen  = document.getElementById('mensajeOrigen');
    var contenidoProd  = document.getElementById('contenidoProductos');
    var sinProductos   = document.getElementById('sinProductos');
    var badgeDisp      = document.getElementById('badgeDisponibles');
    var chkTodos       = document.getElementById('chkTodos');
    var btnConfirmar   = document.getElementById('btnConfirmar');

    var resumenItems    = document.getElementById('resumenItems');
    var resumenCantidad = document.getElementById('resumenCantidad');
    var resumenTotal    = document.getElementById('resumenTotal');

    function fmt(n, dec) {
        if (isNaN(n)) n = 0;
        return n.toFixed(dec === undefined ? 2 : dec).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function escapeHtml(s) {
        s = (s == null) ? '' : String(s);
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function renderProductos() {
        var idOrigen = parseInt(selOrigen.value, 10);
        tbody.innerHTML = '';

        if (!idOrigen) {
            mensajeOrigen.style.display  = '';
            sinProductos.style.display   = 'none';
            contenidoProd.style.display  = 'none';
            badgeDisp.textContent = '0 disponibles';
            return;
        }

        mensajeOrigen.style.display  = 'none';

        var stockBodega = stockMap[idOrigen] || {};
        var disponibles = [];

        for (var i = 0; i < productos.length; i++) {
            var p  = productos[i];
            var st = stockBodega[p.id];
            if (st && parseFloat(st.stock) > 0) {
                disponibles.push({
                    id: p.id,
                    codigo: p.codigo,
                    nombre: p.nombre,
                    unidad: p.unidad_nombre || '',
                    tipo:   p.tipo_nombre || '',
                    stock: parseFloat(st.stock),
                    costo: parseFloat(st.costo)
                });
            }
        }

        if (!disponibles.length) {
            sinProductos.style.display  = '';
            contenidoProd.style.display = 'none';
            badgeDisp.textContent = '0 disponibles';
            return;
        }

        sinProductos.style.display  = 'none';
        contenidoProd.style.display = '';
        badgeDisp.textContent = disponibles.length + ' disponible' + (disponibles.length === 1 ? '' : 's');

        var html = '';
        for (var j = 0; j < disponibles.length; j++) {
            var d = disponibles[j];
            var pre = (productoPre === d.id) ? ' checked' : '';
            html += '<tr class="fila-producto" data-id="' + d.id + '" data-stock="' + d.stock + '" data-costo="' + d.costo + '" data-nombre="' + escapeHtml(d.nombre.toLowerCase()) + '" data-codigo="' + escapeHtml(d.codigo.toLowerCase()) + '">' +
                    '<td class="px-3"><input type="checkbox" class="form-check-input chk-item"' + pre + '></td>' +
                    '<td><span class="badge bg-light text-dark border">' + escapeHtml(d.codigo) + '</span></td>' +
                    '<td>' +
                        '<div class="fw-bold text-dark">' + escapeHtml(d.nombre) + '</div>' +
                        '<div class="text-muted small">' + (d.unidad ? '<i class="bi bi-ruler me-1"></i>' + escapeHtml(d.unidad) : '') + (d.tipo ? ' · ' + escapeHtml(d.tipo) : '') + '</div>' +
                    '</td>' +
                    '<td class="text-end">' +
                        '<span class="fw-bold text-success">' + fmt(d.stock) + '</span>' +
                        (d.unidad ? '<div class="text-muted small">' + escapeHtml(d.unidad) + '</div>' : '') +
                    '</td>' +
                    '<td class="text-end text-muted">$ ' + fmt(d.costo, 0) + '</td>' +
                    '<td class="text-center">' +
                        '<div class="input-group input-group-sm" style="max-width: 180px; margin:0 auto;">' +
                            '<input type="number" class="form-control form-control-sm inp-cantidad text-end" step="0.01" min="0.01" max="' + d.stock + '" value="' + (pre ? d.stock : '') + '" ' + (pre ? '' : 'disabled') + ' placeholder="0">' +
                            '<button type="button" class="btn btn-outline-secondary btn-sm btn-max" title="Usar stock completo" ' + (pre ? '' : 'disabled') + '>MAX</button>' +
                        '</div>' +
                        '<div class="text-danger small mt-1 mensaje-error" style="display:none;"></div>' +
                    '</td>' +
                    '<td class="text-end fw-medium subtotal-fila">$ 0</td>' +
                    '</tr>';
        }

        tbody.innerHTML = html;
        enlazarFilas();
        recalcular();

        if (productoPre) {
            productoPre = 0;
        }
    }

    function enlazarFilas() {
        var filas = tbody.querySelectorAll('tr.fila-producto');
        for (var i = 0; i < filas.length; i++) {
            (function (tr) {
                var chk   = tr.querySelector('.chk-item');
                var inp   = tr.querySelector('.inp-cantidad');
                var btn   = tr.querySelector('.btn-max');
                var stock = parseFloat(tr.getAttribute('data-stock'));

                chk.addEventListener('change', function () {
                    inp.disabled = !chk.checked;
                    btn.disabled = !chk.checked;
                    if (chk.checked && (!inp.value || parseFloat(inp.value) <= 0)) {
                        inp.value = stock;
                    }
                    if (!chk.checked) {
                        inp.value = '';
                        tr.querySelector('.mensaje-error').style.display = 'none';
                    }
                    validarFila(tr);
                    recalcular();
                });

                inp.addEventListener('input', function () {
                    validarFila(tr);
                    recalcular();
                });

                btn.addEventListener('click', function () {
                    inp.value = stock;
                    validarFila(tr);
                    recalcular();
                });
            })(filas[i]);
        }
    }

    function validarFila(tr) {
        var chk   = tr.querySelector('.chk-item');
        var inp   = tr.querySelector('.inp-cantidad');
        var stock = parseFloat(tr.getAttribute('data-stock'));
        var msg   = tr.querySelector('.mensaje-error');

        if (!chk.checked) {
            inp.classList.remove('is-invalid');
            msg.style.display = 'none';
            return true;
        }

        var val = parseFloat(inp.value);
        if (isNaN(val) || val <= 0) {
            inp.classList.add('is-invalid');
            msg.textContent   = 'Cantidad inválida';
            msg.style.display = '';
            return false;
        }
        if (val > stock) {
            inp.classList.add('is-invalid');
            msg.textContent   = 'Máximo: ' + fmt(stock);
            msg.style.display = '';
            return false;
        }

        inp.classList.remove('is-invalid');
        msg.style.display = 'none';
        return true;
    }

    function recalcular() {
        var filas = tbody.querySelectorAll('tr.fila-producto');
        var items = 0, cant = 0, total = 0, todoOk = true;

        for (var i = 0; i < filas.length; i++) {
            var tr    = filas[i];
            var chk   = tr.querySelector('.chk-item');
            var inp   = tr.querySelector('.inp-cantidad');
            var costo = parseFloat(tr.getAttribute('data-costo'));
            var sub   = tr.querySelector('.subtotal-fila');

            if (chk.checked) {
                var val = parseFloat(inp.value);
                if (isNaN(val) || val <= 0 || val > parseFloat(tr.getAttribute('data-stock'))) {
                    todoOk = false;
                    sub.textContent = '—';
                } else {
                    items++;
                    cant += val;
                    var subtotal = val * costo;
                    total += subtotal;
                    sub.textContent = '$ ' + fmt(subtotal, 0);
                }
            } else {
                sub.textContent = '$ 0';
            }
        }

        resumenItems.textContent    = items;
        resumenCantidad.textContent = fmt(cant);
        resumenTotal.textContent    = '$ ' + fmt(total, 0);

        var origenVal  = selOrigen.value;
        var destinoVal = selDestino.value;

        btnConfirmar.disabled = (items === 0) || !todoOk || !origenVal || !destinoVal || (origenVal === destinoVal);
    }

    // Buscador
    buscador.addEventListener('input', function () {
        var q = buscador.value.trim().toLowerCase();
        var filas = tbody.querySelectorAll('tr.fila-producto');
        var visibles = 0;
        for (var i = 0; i < filas.length; i++) {
            var tr = filas[i];
            var nombre = tr.getAttribute('data-nombre');
            var codigo = tr.getAttribute('data-codigo');
            var match = (q === '' || nombre.indexOf(q) >= 0 || codigo.indexOf(q) >= 0);
            tr.style.display = match ? '' : 'none';
            if (match) visibles++;
        }
    });

    // Marcar todos visibles
    chkTodos.addEventListener('change', function () {
        var filas = tbody.querySelectorAll('tr.fila-producto');
        for (var i = 0; i < filas.length; i++) {
            if (filas[i].style.display !== 'none') {
                var chk = filas[i].querySelector('.chk-item');
                if (chk.checked !== chkTodos.checked) {
                    chk.checked = chkTodos.checked;
                    chk.dispatchEvent(new Event('change'));
                }
            }
        }
    });

    // Validación bodegas iguales
    function validarBodegas() {
        if (selOrigen.value && selDestino.value && selOrigen.value === selDestino.value) {
            selDestino.classList.add('is-invalid');
        } else {
            selDestino.classList.remove('is-invalid');
        }
        recalcular();
    }

    // Solo escuchar cambios si el origen es un SELECT (admin)
    if (selOrigen.tagName === 'SELECT') {
        selOrigen.addEventListener('change', function () {
            renderProductos();
            validarBodegas();
        });
    }
    selDestino.addEventListener('change', validarBodegas);

    // Submit: serializar seleccionados
    document.getElementById('formTraslado').addEventListener('submit', function (e) {
        var prev = this.querySelectorAll('input[name="item_id_producto[]"], input[name="item_cantidad[]"]');
        for (var i = 0; i < prev.length; i++) prev[i].remove();

        var filas = tbody.querySelectorAll('tr.fila-producto');
        var count = 0;
        for (var j = 0; j < filas.length; j++) {
            var tr  = filas[j];
            var chk = tr.querySelector('.chk-item');
            if (chk.checked && validarFila(tr)) {
                var idp = document.createElement('input');
                idp.type = 'hidden'; idp.name = 'item_id_producto[]'; idp.value = tr.getAttribute('data-id');
                var can = document.createElement('input');
                can.type = 'hidden'; can.name = 'item_cantidad[]'; can.value = tr.querySelector('.inp-cantidad').value;
                this.appendChild(idp);
                this.appendChild(can);
                count++;
            }
        }

        if (count === 0) {
            e.preventDefault();
            alert('Debes seleccionar al menos un producto válido.');
            return false;
        }
    });

    // Arranque
    renderProductos();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>