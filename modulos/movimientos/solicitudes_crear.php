<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega', 'solicitante'));

$error = '';

// ============================================================
// DESTINO SEGUN ROL
// ============================================================
// - Encargado: destino = su bodega (bloqueado)
// - Solicitante: destino = bodega de su unidad (bloqueado)
// - Admin: destino libre
$destinoFijo     = null;   // array bodega si esta bloqueado
$destinoFijoId   = 0;
$destinoLabel    = '';

if (is_encargado()) {
    $destinoFijoId = user_bodega_id();
    if ($destinoFijoId <= 0) {
        set_flash('error', 'Tu usuario no tiene una bodega asignada. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
    $stmt = $pdo->prepare("SELECT id, codigo, nombre FROM bodegas WHERE id = ? AND estado = 1 LIMIT 1");
    $stmt->execute(array($destinoFijoId));
    $destinoFijo = $stmt->fetch();
    $destinoLabel = 'Tu bodega';
}
elseif (is_solicitante()) {
    $uniId = user_unidad_id();
    if ($uniId <= 0) {
        set_flash('error', 'Tu usuario no tiene una unidad asignada. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
    $stmt = $pdo->prepare("SELECT id, codigo, nombre FROM bodegas WHERE id_unidad = ? AND estado = 1 LIMIT 1");
    $stmt->execute(array($uniId));
    $destinoFijo = $stmt->fetch();
    if (!$destinoFijo) {
        set_flash('error', 'Tu unidad no tiene una bodega asociada. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
    $destinoFijoId = (int)$destinoFijo['id'];
    $destinoLabel  = 'Bodega de tu unidad';
}

// ============================================================
// BODEGAS (para origen y, si admin, destino)
// ============================================================
$bodegas = $pdo->query("
    SELECT id, codigo, nombre
    FROM bodegas
    WHERE estado = 1
    ORDER BY (codigo='CENTRAL') DESC, nombre ASC
")->fetchAll();

// Bodega central (sugerencia de origen por defecto)
$idCentral = 0;
foreach ($bodegas as $b) {
    if ($b['codigo'] === 'CENTRAL') { $idCentral = (int)$b['id']; break; }
}

// ============================================================
// PRODUCTOS + MAPA DE STOCK POR BODEGA
// ============================================================
$productos = $pdo->query("
    SELECT p.id, p.codigo, p.nombre,
           um.nombre AS unidad_nombre,
           tp.nombre AS tipo_nombre
    FROM productos p
    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    LEFT JOIN tipos_producto tp  ON tp.id = p.id_tipo_producto
    WHERE p.estado = 1 AND p.controla_stock = 1
    ORDER BY p.nombre ASC
")->fetchAll();

$stockRows = $pdo->query("
    SELECT id_bodega, id_producto, stock_actual, costo_promedio
    FROM stock_bodega
")->fetchAll();

$stockMap = array();
foreach ($stockRows as $r) {
    $bi = (int)$r['id_bodega'];
    $pi = (int)$r['id_producto'];
    if (!isset($stockMap[$bi])) { $stockMap[$bi] = array(); }
    $stockMap[$bi][$pi] = array(
        'stock' => (float)$r['stock_actual'],
        'costo' => (float)$r['costo_promedio']
    );
}

// ============================================================
// POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_origen  = (int)post('id_bodega_origen');
    $id_destino = (int)post('id_bodega_destino');
    $motivo     = trim(post('observacion'));
    $items_prod = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_cant = isset($_POST['item_cantidad'])    ? $_POST['item_cantidad']    : array();
    $items_obs  = isset($_POST['item_obs'])         ? $_POST['item_obs']         : array();

    // Forzar destino segun rol
    if (is_encargado() || is_solicitante()) {
        $id_destino = $destinoFijoId;
    }

    if ($id_origen <= 0) {
        $error = 'Debes seleccionar la bodega origen.';
    } elseif ($id_destino <= 0) {
        $error = 'Bodega destino no definida.';
    } elseif ($id_origen === $id_destino) {
        $error = 'La bodega origen y destino no pueden ser la misma.';
    } elseif ($motivo === '') {
        $error = 'Debes ingresar un motivo para la solicitud.';
    } else {
        // Construir detalle limpio
        $detalle = array();
        $n = count($items_prod);
        for ($i = 0; $i < $n; $i++) {
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

                $pdo->prepare("
                    INSERT INTO solicitudes
                        (numero_solicitud, id_bodega_origen, id_bodega_destino, id_usuario, observacion)
                    VALUES ('PENDIENTE', ?, ?, ?, ?)
                ")->execute(array($id_origen, $id_destino, $uid, $motivo));

                $id_sol = (int)$pdo->lastInsertId();
                $numero = 'SOL-' . date('Y') . '-' . str_pad($id_sol, 5, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE solicitudes SET numero_solicitud = ? WHERE id = ?")
                    ->execute(array($numero, $id_sol));

                $stmtD = $pdo->prepare("
                    INSERT INTO solicitudes_detalle (id_solicitud, id_producto, cantidad, observacion)
                    VALUES (?, ?, ?, ?)
                ");
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

$pageTitle = 'Nueva Solicitud';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="text-muted small mb-1">
            <a href="solicitudes_lista.php" class="text-decoration-none text-muted">
                <i class="bi bi-chevron-left"></i> Solicitudes
            </a>
        </div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-clipboard-plus text-primary me-2"></i>Nueva Solicitud de Traslado
        </h1>
        <p class="text-muted mb-0 small mt-1">
            <?php if (is_encargado() || is_solicitante()): ?>
                Solicita productos desde otra bodega hacia <strong><?php echo h($destinoFijo['nombre']); ?></strong>.
            <?php else: ?>
                Crea una solicitud de traslado entre bodegas.
            <?php endif; ?>
        </p>
    </div>
    <a href="solicitudes_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Cancelar
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger d-flex align-items-start">
        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
        <div><?php echo h($error); ?></div>
    </div>
<?php endif; ?>

<form method="post" id="formSolicitud" autocomplete="off">

    <!-- PASO 1: ORIGEN -> DESTINO + MOTIVO -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center">
            <span class="badge bg-primary rounded-circle me-2" style="width:28px;height:28px;line-height:20px;">1</span>
            <h5 class="mb-0 fw-bold">¿Desde dónde y por qué motivo?</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-stretch">

                <!-- ORIGEN -->
                <div class="col-md-5">
                    <label class="form-label fw-bold text-primary small text-uppercase" style="letter-spacing:.5px;">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Bodega Origen (de dónde saco)
                    </label>
                    <select name="id_bodega_origen" id="selOrigen" class="form-select form-select-lg" required>
                        <option value="">Seleccione una bodega…</option>
                        <?php foreach ($bodegas as $b):
                            // Excluir bodega destino si esta bloqueada
                            if ($destinoFijoId > 0 && (int)$b['id'] === $destinoFijoId) continue;
                            $sel = ((int)post('id_bodega_origen') === (int)$b['id'] || (!post('id_bodega_origen') && $idCentral === (int)$b['id']));
                        ?>
                            <option value="<?php echo (int)$b['id']; ?>" <?php echo $sel ? 'selected' : ''; ?>>
                                <?php echo h($b['nombre'] . ' (' . $b['codigo'] . ')'); ?>
                                <?php echo ($b['codigo'] === 'CENTRAL') ? ' ★' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Solo verás productos con stock disponible aquí.</div>
                </div>

                <div class="col-md-2 d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-arrow-right-circle-fill text-primary" style="font-size:2.5rem;"></i>
                    <span class="small text-muted fw-bold">SOLICITA</span>
                </div>

                <!-- DESTINO -->
                <div class="col-md-5">
                    <label class="form-label fw-bold text-success small text-uppercase" style="letter-spacing:.5px;">
                        <i class="bi bi-box-arrow-in-down-left me-1"></i>Bodega Destino (a dónde llega)
                    </label>

                    <?php if ($destinoFijo): ?>
                        <div class="form-control form-control-lg bg-light d-flex align-items-center justify-content-between" style="min-height:58px;">
                            <div>
                                <div class="fw-bold text-dark"><?php echo h($destinoFijo['nombre']); ?></div>
                                <small class="text-muted"><?php echo h($destinoFijo['codigo']); ?> · <?php echo h($destinoLabel); ?></small>
                            </div>
                            <i class="bi bi-lock-fill text-muted"></i>
                        </div>
                        <input type="hidden" name="id_bodega_destino" id="selDestino" value="<?php echo (int)$destinoFijoId; ?>">
                    <?php else: ?>
                        <!-- Admin: destino libre -->
                        <select name="id_bodega_destino" id="selDestino" class="form-select form-select-lg" required>
                            <option value="">Seleccione una bodega…</option>
                            <?php foreach ($bodegas as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>" <?php echo ((int)post('id_bodega_destino') === (int)$b['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($b['nombre'] . ' (' . $b['codigo'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- MOTIVO -->
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary small">
                        Motivo de la solicitud <span class="text-danger">*</span>
                    </label>
                    <textarea name="observacion" class="form-control" rows="2"
                              placeholder="Ej: Reposición mensual, materiales para proyecto X, mantenimiento…"
                              required><?php echo h(post('observacion')); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 2: PRODUCTOS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-2 d-flex align-items-center flex-wrap gap-2">
            <span class="badge bg-primary rounded-circle me-2" style="width:28px;height:28px;line-height:20px;">2</span>
            <h5 class="mb-0 fw-bold me-auto">Seleccionar productos a solicitar</h5>

            <div class="input-group" style="max-width: 280px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-secondary"></i></span>
                <input type="text" id="buscadorProductos" class="form-control border-start-0 ps-0" placeholder="Buscar producto...">
            </div>

            <span class="badge bg-light text-dark border" id="badgeDisponibles">0 disponibles</span>
        </div>

        <div id="mensajeOrigen" class="card-body text-center text-muted py-5" style="display:none;">
            <i class="bi bi-arrow-up fs-1 d-block mb-2 text-primary"></i>
            <div class="fw-bold mb-1">Selecciona primero una bodega origen</div>
            <div class="small">Luego verás aquí los productos disponibles para solicitar.</div>
        </div>

        <div id="sinProductos" class="card-body text-center text-muted py-5" style="display:none;">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <div class="fw-bold mb-1">No hay productos con stock</div>
            <div class="small">La bodega seleccionada no tiene productos con stock disponible.</div>
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
                            <th style="width:18%;" class="text-center">Cantidad solicitada</th>
                            <th style="width:25%;">Especificación</th>
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
            </div>

            <div class="d-flex gap-2">
                <a href="solicitudes_lista.php" class="btn btn-light border px-4">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-lg px-4" id="btnConfirmar" disabled>
                    <i class="bi bi-send me-1"></i> Enviar Solicitud
                </button>
            </div>
        </div>
    </div>

</form>

<script>
(function () {
    var stockMap  = <?php echo json_encode($stockMap); ?>;
    var productos = <?php echo json_encode($productos); ?>;

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
            mensajeOrigen.style.display = '';
            sinProductos.style.display  = 'none';
            contenidoProd.style.display = 'none';
            badgeDisp.textContent = '0 disponibles';
            return;
        }

        mensajeOrigen.style.display = 'none';

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
                    stock: parseFloat(st.stock)
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
            html += '<tr class="fila-producto" data-id="' + d.id + '" data-stock="' + d.stock + '" data-nombre="' + escapeHtml(d.nombre.toLowerCase()) + '" data-codigo="' + escapeHtml(d.codigo.toLowerCase()) + '">' +
                    '<td class="px-3"><input type="checkbox" class="form-check-input chk-item"></td>' +
                    '<td><span class="badge bg-light text-dark border">' + escapeHtml(d.codigo) + '</span></td>' +
                    '<td>' +
                        '<div class="fw-bold text-dark">' + escapeHtml(d.nombre) + '</div>' +
                        '<div class="text-muted small">' + (d.unidad ? '<i class="bi bi-ruler me-1"></i>' + escapeHtml(d.unidad) : '') + (d.tipo ? ' · ' + escapeHtml(d.tipo) : '') + '</div>' +
                    '</td>' +
                    '<td class="text-end">' +
                        '<span class="fw-bold text-success">' + fmt(d.stock) + '</span>' +
                        (d.unidad ? '<div class="text-muted small">' + escapeHtml(d.unidad) + '</div>' : '') +
                    '</td>' +
                    '<td class="text-center">' +
                        '<div class="input-group input-group-sm" style="max-width: 180px; margin:0 auto;">' +
                            '<input type="number" class="form-control form-control-sm inp-cantidad text-end" step="0.01" min="0.01" max="' + d.stock + '" value="" disabled placeholder="0">' +
                            '<button type="button" class="btn btn-outline-secondary btn-sm btn-max" title="Usar stock completo" disabled>MAX</button>' +
                        '</div>' +
                        '<div class="text-danger small mt-1 mensaje-error" style="display:none;"></div>' +
                    '</td>' +
                    '<td>' +
                        '<input type="text" class="form-control form-control-sm inp-obs" placeholder="Opcional: color, talla, etc." disabled>' +
                    '</td>' +
                    '</tr>';
        }

        tbody.innerHTML = html;
        enlazarFilas();
        recalcular();
    }

    function enlazarFilas() {
        var filas = tbody.querySelectorAll('tr.fila-producto');
        for (var i = 0; i < filas.length; i++) {
            (function (tr) {
                var chk   = tr.querySelector('.chk-item');
                var inp   = tr.querySelector('.inp-cantidad');
                var btn   = tr.querySelector('.btn-max');
                var obs   = tr.querySelector('.inp-obs');
                var stock = parseFloat(tr.getAttribute('data-stock'));

                chk.addEventListener('change', function () {
                    inp.disabled = !chk.checked;
                    btn.disabled = !chk.checked;
                    obs.disabled = !chk.checked;
                    if (chk.checked && (!inp.value || parseFloat(inp.value) <= 0)) {
                        inp.value = 1;
                    }
                    if (!chk.checked) {
                        inp.value = '';
                        obs.value = '';
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
        var items = 0, cant = 0, todoOk = true;

        for (var i = 0; i < filas.length; i++) {
            var tr  = filas[i];
            var chk = tr.querySelector('.chk-item');
            var inp = tr.querySelector('.inp-cantidad');

            if (chk.checked) {
                var val = parseFloat(inp.value);
                if (isNaN(val) || val <= 0 || val > parseFloat(tr.getAttribute('data-stock'))) {
                    todoOk = false;
                } else {
                    items++;
                    cant += val;
                }
            }
        }

        resumenItems.textContent    = items;
        resumenCantidad.textContent = fmt(cant);

        var origenVal  = selOrigen.value;
        var destinoVal = selDestino.value;

        btnConfirmar.disabled = (items === 0) || !todoOk || !origenVal || !destinoVal || (origenVal === destinoVal);
    }

    // Buscador
    buscador.addEventListener('input', function () {
        var q = buscador.value.trim().toLowerCase();
        var filas = tbody.querySelectorAll('tr.fila-producto');
        for (var i = 0; i < filas.length; i++) {
            var tr = filas[i];
            var nombre = tr.getAttribute('data-nombre');
            var codigo = tr.getAttribute('data-codigo');
            var match = (q === '' || nombre.indexOf(q) >= 0 || codigo.indexOf(q) >= 0);
            tr.style.display = match ? '' : 'none';
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
            if (selDestino.tagName === 'SELECT') selDestino.classList.add('is-invalid');
        } else {
            if (selDestino.tagName === 'SELECT') selDestino.classList.remove('is-invalid');
        }
        recalcular();
    }

    selOrigen.addEventListener('change', function () {
        renderProductos();
        validarBodegas();
    });
    if (selDestino.tagName === 'SELECT') {
        selDestino.addEventListener('change', validarBodegas);
    }

    // Submit: serializar items seleccionados
    document.getElementById('formSolicitud').addEventListener('submit', function (e) {
        var prev = this.querySelectorAll('input[name="item_id_producto[]"], input[name="item_cantidad[]"], input[name="item_obs[]"]');
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
                var ob = document.createElement('input');
                ob.type = 'hidden'; ob.name = 'item_obs[]'; ob.value = tr.querySelector('.inp-obs').value;
                this.appendChild(idp);
                this.appendChild(can);
                this.appendChild(ob);
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