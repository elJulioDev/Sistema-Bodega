<?php
/**
 * solicitudes_crear.php
 *
 * ACTUALIZACIÓN (modelo M:N):
 *   - Encargado: destino = cualquiera de SUS bodegas (M:N).
 *   - Solicitante: destino = cualquier bodega de SU unidad
 *                  (soporta múltiples bodegas por unidad).
 *   - Origen: cualquier bodega activa; el solicitante solo puede
 *     pedir desde bodegas que NO sean de su propia unidad, pero
 *     el destino SÍ debe ser de su unidad.
 */
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/bodegas_helpers.php';

require_login();
require_role(array('admin', 'bodega', 'solicitante'));

$user  = current_user();
$miUid = (int)$user['id'];

// ── Bodegas destino permitidas según rol ────────────────────
$bodegasDestino   = array();   // filas
$bodegasDestinoIds = array();  // solo ids

if (is_encargado()) {
    $bodegasDestino = user_bodegas($miUid);  // todas sus bodegas
    foreach ($bodegasDestino as $b) $bodegasDestinoIds[] = (int)$b['id'];
} elseif (is_solicitante()) {
    $bodegasDestino = bodegas_destino_solicitante($miUid);
    foreach ($bodegasDestino as $b) $bodegasDestinoIds[] = (int)$b['id'];
}

if ((is_encargado() || is_solicitante()) && !$bodegasDestino) {
    set_flash('error', 'Tu usuario/unidad no tiene bodegas configuradas como destino. Contacta al administrador.');
    redirect('/Bodega/index.php');
}

// ── Bodegas origen (todas las activas) ──────────────────────
$bodegas = $pdo->query("
    SELECT id, codigo, nombre, id_unidad
    FROM   bodegas
    WHERE  estado = 1
    ORDER  BY (codigo = 'CENTRAL') DESC, nombre ASC
")->fetchAll();

$idCentral = 0;
foreach ($bodegas as $b) {
    if ($b['codigo'] === 'CENTRAL') { $idCentral = (int)$b['id']; break; }
}

// ── Productos ───────────────────────────────────────────────
$productos = $pdo->query("
    SELECT p.id, p.codigo, p.nombre,
           um.nombre AS unidad_nombre,
           tp.nombre AS tipo_nombre
    FROM   productos p
    LEFT   JOIN unidades_medida um ON um.id = p.id_unidad_medida
    LEFT   JOIN tipos_producto  tp ON tp.id = p.id_tipo_producto
    WHERE  p.estado = 1 AND p.controla_stock = 1
    ORDER  BY p.nombre ASC
")->fetchAll();

// ── Stock real por bodega ───────────────────────────────────
$stockRows = $pdo->query("
    SELECT id_bodega, id_producto, stock_actual, costo_promedio
    FROM   stock_bodega
")->fetchAll();

$stockMap = array();
foreach ($stockRows as $r) {
    $bi = (int)$r['id_bodega'];
    $pi = (int)$r['id_producto'];
    if (!isset($stockMap[$bi])) $stockMap[$bi] = array();
    $stockMap[$bi][$pi] = array(
        'stock' => (float)$r['stock_actual'],
        'costo' => (float)$r['costo_promedio'],
    );
}

// ── Reservado en solicitudes pendientes ─────────────────────
$reservadoRows = $pdo->query("
    SELECT s.id_bodega_origen, sd.id_producto, SUM(sd.cantidad) AS reservado
    FROM   solicitudes_detalle sd
    INNER  JOIN solicitudes s ON s.id = sd.id_solicitud
    WHERE  s.estado IN ('pendiente', 'en_revision')
      AND  (sd.estado IS NULL OR sd.estado = 'pendiente')
    GROUP  BY s.id_bodega_origen, sd.id_producto
")->fetchAll();

$reservadoMap = array();
foreach ($reservadoRows as $r) {
    $bi = (int)$r['id_bodega_origen'];
    $pi = (int)$r['id_producto'];
    if (!isset($reservadoMap[$bi])) $reservadoMap[$bi] = array();
    $reservadoMap[$bi][$pi] = (float)$r['reservado'];
}

// ── POST: guardar solicitud ─────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_origen  = (int)post('id_bodega_origen');
    $id_destino = (int)post('id_bodega_destino');
    $motivo     = trim((string)post('observacion'));
    $dias_lim   = (int)post('dias_limite');
    if ($dias_lim < 1 || $dias_lim > 7) $dias_lim = 3;

    $items_prod = isset($_POST['item_id_producto']) ? $_POST['item_id_producto'] : array();
    $items_cant = isset($_POST['item_cantidad'])    ? $_POST['item_cantidad']    : array();
    $items_obs  = isset($_POST['item_obs'])         ? $_POST['item_obs']         : array();

    // Validar destino según rol
    if (is_encargado() || is_solicitante()) {
        if (!in_array($id_destino, $bodegasDestinoIds, true)) {
            $error = 'La bodega destino no está permitida para tu usuario/unidad.';
        }
    }

    if ($error === '') {
        if ($id_origen <= 0) {
            $error = 'Debes seleccionar la bodega origen.';
        } elseif ($id_destino <= 0) {
            $error = 'Debes seleccionar la bodega destino.';
        } elseif ($id_origen === $id_destino) {
            $error = 'La bodega origen y destino no pueden ser la misma.';
        } elseif ($motivo === '') {
            $error = 'Debes ingresar un motivo para la solicitud.';
        }
    }

    if ($error === '') {
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
            $advertencias = array();
            foreach ($detalle as $d) {
                $stockReal = isset($stockMap[$id_origen][$d['p']]) ? $stockMap[$id_origen][$d['p']]['stock'] : 0;
                $reservado = isset($reservadoMap[$id_origen][$d['p']]) ? $reservadoMap[$id_origen][$d['p']] : 0;
                $libre     = max(0, $stockReal - $reservado);
                if ($d['c'] > $libre) $advertencias[] = $d['p'];
            }

            try {
                $pdo->beginTransaction();

                $fechaLimite = date('Y-m-d', strtotime('+' . $dias_lim . ' days'));

                $pdo->prepare("
                    INSERT INTO solicitudes
                        (numero_solicitud, id_bodega_origen, id_bodega_destino,
                         id_usuario, observacion, dias_limite, fecha_limite)
                    VALUES ('PENDIENTE', ?, ?, ?, ?, ?, ?)
                ")->execute(array($id_origen, $id_destino, $miUid, $motivo, $dias_lim, $fechaLimite));

                $id_sol  = (int)$pdo->lastInsertId();
                $numero  = 'SOL-' . date('Y') . '-' . str_pad($id_sol, 5, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE solicitudes SET numero_solicitud = ? WHERE id = ?")
                    ->execute(array($numero, $id_sol));

                $stmtD = $pdo->prepare("
                    INSERT INTO solicitudes_detalle (id_solicitud, id_producto, cantidad, observacion)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($detalle as $d) {
                    $stmtD->execute(array($id_sol, $d['p'], $d['c'], $d['obs']));
                }

                $pdo->prepare("INSERT INTO solicitudes_log (id_solicitud, id_usuario, accion, detalle) VALUES (?,?,?,?)")
                    ->execute(array(
                        $id_sol, $miUid, 'creada',
                        'Fecha límite: ' . date('d/m/Y', strtotime($fechaLimite)) . ' (' . $dias_lim . ' días)'
                    ));

                $pdo->commit();

                $msg = 'Solicitud ' . $numero . ' enviada. Vence el '
                     . date('d/m/Y', strtotime($fechaLimite)) . '.';
                if ($advertencias) {
                    $msg .= ' ⚠️ Algunos productos tienen stock reservado: el encargado revisará disponibilidad al aprobar.';
                }
                set_flash('success', $msg);
                redirect('solicitudes_lista.php');

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
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
            <?php if (is_solicitante()): ?>
                Solicita productos hacia las bodegas de tu unidad.
            <?php elseif (is_encargado()): ?>
                Solicita productos hacia una de tus bodegas asignadas.
            <?php else: ?>
                Crea una solicitud de traslado entre bodegas.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?></div>
<?php endif; ?>

<div class="alert alert-info d-flex gap-2 align-items-start border-0 shadow-sm mb-4" id="alertaReservado" style="display:none!important">
    <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
    <div>
        <strong>Stock con reservas activas</strong> — el stock libre mostrado descuenta cantidades comprometidas
        en solicitudes pendientes de otros funcionarios.
    </div>
</div>

<form method="post" id="formSolicitud">

    <!-- PASO 1 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">
                <span class="badge bg-primary rounded-circle me-2">1</span> Datos de la solicitud
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- Origen -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Bodega Origen <span class="text-danger">*</span></label>
                    <select name="id_bodega_origen" id="selOrigen" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($bodegas as $b):
                            // Solicitante/Encargado: no puede ser la misma que su destino
                            $isDest = in_array((int)$b['id'], $bodegasDestinoIds, true);
                        ?>
                            <option value="<?php echo (int)$b['id']; ?>"
                                data-dest="<?php echo $isDest ? '1' : '0'; ?>"
                                <?php echo ((int)$b['id'] === $idCentral && !$isDest) ? ' selected' : ''; ?>>
                                <?php echo h($b['nombre']); ?>
                                <?php echo ((int)$b['id'] === $idCentral) ? ' ★' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Destino -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Bodega Destino <span class="text-danger">*</span></label>
                    <?php if (is_encargado() || is_solicitante()): ?>
                        <?php if (count($bodegasDestino) === 1): ?>
                            <?php $b = $bodegasDestino[0]; ?>
                            <input type="hidden" name="id_bodega_destino" value="<?php echo (int)$b['id']; ?>">
                            <input type="text" class="form-control bg-light" value="<?php echo h($b['nombre']); ?>" readonly>
                            <div class="form-text">
                                <?php if (is_solicitante()): ?>Única bodega de tu unidad.<?php else: ?>Única bodega asignada.<?php endif; ?>
                            </div>
                        <?php else: ?>
                            <select name="id_bodega_destino" id="selDestino" class="form-select" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($bodegasDestino as $b): ?>
                                    <option value="<?php echo (int)$b['id']; ?>">
                                        <?php echo h($b['nombre']); ?> (<?php echo h($b['codigo']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <?php if (is_solicitante()): ?>
                                    <?php echo count($bodegasDestino); ?> bodega(s) de tu unidad disponibles.
                                <?php else: ?>
                                    <?php echo count($bodegasDestino); ?> bodega(s) bajo tu gestión.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <select name="id_bodega_destino" id="selDestino" class="form-select" required>
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($bodegas as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>"><?php echo h($b['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Plazo aprobación <span class="text-muted fw-normal small ms-1">(días)</span>
                    </label>
                    <select name="dias_limite" class="form-select">
                        <?php for ($d = 1; $d <= 7; $d++): ?>
                            <option value="<?php echo $d; ?>" <?php echo ($d === 3) ? 'selected' : ''; ?>>
                                <?php echo $d; ?> día<?php echo $d > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Motivo / Descripción <span class="text-danger">*</span></label>
                    <textarea name="observacion" class="form-control" rows="2"
                              placeholder="Ej: Reposición mensual de insumos..." required></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 2 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 fw-bold">
                <span class="badge bg-primary rounded-circle me-2">2</span>
                Productos a solicitar
                <span class="badge bg-secondary ms-2" id="badgeDisponibles">—</span>
            </h6>
            <div class="d-flex gap-2 align-items-center">
                <span class="d-none d-md-flex align-items-center gap-1 me-2 small text-muted">
                    <i class="bi bi-circle-fill text-success" style="font-size:.55rem"></i> libre
                    <i class="bi bi-circle-fill text-warning ms-2" style="font-size:.55rem"></i> reservado
                </span>
                <input type="text" id="buscadorProductos" class="form-control form-control-sm"
                       style="max-width:200px" placeholder="Buscar producto...">
            </div>
        </div>

        <div class="text-center text-muted py-5" id="mensajeOrigen">
            <i class="bi bi-arrow-up-circle fs-1 d-block mb-2 text-primary opacity-50"></i>
            Selecciona la bodega origen para ver productos disponibles.
        </div>

        <div class="text-center text-muted py-5" id="sinProductos" style="display:none">
            <i class="bi bi-box-seam fs-1 d-block mb-2 opacity-25"></i>
            No hay stock disponible en la bodega seleccionada.
        </div>

        <div id="contenidoProductos" style="display:none">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th style="width:36px"><input type="checkbox" class="form-check-input" id="chkTodos"></th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="text-end">Stock libre</th>
                            <th class="text-end" style="width:130px">Cantidad</th>
                            <th class="d-none d-md-table-cell">Nota</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyProductos"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm sticky-bottom bg-white">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 py-3">
            <div class="d-flex gap-4">
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
    var stockMap     = <?php echo json_encode($stockMap); ?>;
    var reservadoMap = <?php echo json_encode($reservadoMap); ?>;
    var productos    = <?php echo json_encode(array_values($productos)); ?>;

    var selOrigen     = document.getElementById('selOrigen');
    var selDestino    = document.getElementById('selDestino'); // puede ser null si fijo
    var buscador      = document.getElementById('buscadorProductos');
    var tbody         = document.getElementById('tbodyProductos');
    var mensajeOrigen = document.getElementById('mensajeOrigen');
    var contenidoProd = document.getElementById('contenidoProductos');
    var sinProductos  = document.getElementById('sinProductos');
    var badgeDisp     = document.getElementById('badgeDisponibles');
    var chkTodos      = document.getElementById('chkTodos');
    var btnConfirmar  = document.getElementById('btnConfirmar');
    var resumenItems    = document.getElementById('resumenItems');
    var resumenCantidad = document.getElementById('resumenCantidad');
    var alertaReservado = document.getElementById('alertaReservado');

    function fmt(n, dec) {
        if (isNaN(n)) n = 0;
        return n.toFixed(dec === undefined ? 2 : dec)
                .replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function escapeHtml(s) {
        s = (s == null) ? '' : String(s);
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function getDestinoActual() {
        if (selDestino) return parseInt(selDestino.value, 10) || 0;
        var hid = document.querySelector('input[name="id_bodega_destino"]');
        return hid ? (parseInt(hid.value, 10) || 0) : 0;
    }

    function renderProductos() {
        var idOrigen = parseInt(selOrigen.value, 10);
        tbody.innerHTML = '';

        if (!idOrigen) {
            mensajeOrigen.style.display = '';
            sinProductos.style.display  = 'none';
            contenidoProd.style.display = 'none';
            badgeDisp.textContent = '—';
            alertaReservado.style.display = 'none';
            return;
        }

        mensajeOrigen.style.display = 'none';
        var stockBodega    = stockMap[idOrigen]     || {};
        var reservadoBodega = reservadoMap[idOrigen] || {};
        var hayReservas    = false;
        var disponibles    = [];

        for (var i = 0; i < productos.length; i++) {
            var p  = productos[i];
            var st = stockBodega[p.id];
            if (!st) continue;
            var stockReal = parseFloat(st.stock);
            var reservado = parseFloat(reservadoBodega[p.id] || 0);
            var libre     = Math.max(0, stockReal - reservado);
            if (stockReal > 0) {
                disponibles.push({
                    id: p.id, codigo: p.codigo, nombre: p.nombre,
                    unidad: p.unidad_nombre || '', tipo: p.tipo_nombre || '',
                    stockReal: stockReal, reservado: reservado, libre: libre
                });
                if (reservado > 0) hayReservas = true;
            }
        }

        alertaReservado.style.display = hayReservas ? '' : 'none';

        if (!disponibles.length) {
            sinProductos.style.display  = '';
            contenidoProd.style.display = 'none';
            badgeDisp.textContent = '0 disponibles';
            return;
        }

        sinProductos.style.display  = 'none';
        contenidoProd.style.display = '';
        var libres = disponibles.filter(function(d){ return d.libre > 0; }).length;
        badgeDisp.textContent = libres + ' con stock libre';

        var html = '';
        for (var j = 0; j < disponibles.length; j++) {
            var d = disponibles[j];
            var agotado = (d.libre <= 0);
            var colStock = agotado
                ? '<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Sin stock libre</span>'
                : '<span class="fw-bold text-success">' + fmt(d.libre) + '</span>';
            if (d.reservado > 0) {
                colStock += '<br><small class="text-warning"><i class="bi bi-clock me-1"></i>'
                         + fmt(d.reservado) + ' reservado</small>';
            }

            html += '<tr class="fila-producto'+ (agotado ? ' table-secondary opacity-75' : '') +'"'
                  + ' data-id="' + d.id + '" data-stock="' + d.libre + '"'
                  + ' data-nombre="' + escapeHtml(d.nombre.toLowerCase()) + '"'
                  + ' data-codigo="' + escapeHtml(d.codigo.toLowerCase()) + '">'
                  + '<td class="px-3"><input type="checkbox" class="form-check-input chk-item"'
                  +   (agotado ? ' disabled' : '') + '></td>'
                  + '<td><span class="badge bg-light text-dark border">' + escapeHtml(d.codigo) + '</span></td>'
                  + '<td><div class="fw-bold">' + escapeHtml(d.nombre) + '</div>'
                  +   '<div class="text-muted small">' + (d.unidad ? escapeHtml(d.unidad) : '')
                  +   (d.tipo ? ' · ' + escapeHtml(d.tipo) : '') + '</div></td>'
                  + '<td class="text-end">' + colStock + '</td>'
                  + '<td class="text-end" style="min-width:110px">'
                  +   (agotado ? '<span class="text-muted small">No disponible</span>'
                               : '<input type="number" class="form-control form-control-sm text-end input-cantidad" '
                                 + 'name="item_cantidad[]" step="0.01" min="0.01" max="' + d.libre + '" '
                                 + 'placeholder="0,00">'
                                 + '<input type="hidden" name="item_id_producto[]" value="' + d.id + '">')
                  + '</td>'
                  + '<td class="d-none d-md-table-cell">'
                  +   (agotado ? '' : '<input type="text" class="form-control form-control-sm" name="item_obs[]" placeholder="Nota opcional" maxlength="200">')
                  + '</td></tr>';
        }
        tbody.innerHTML = html;
        bindFilas();
        actualizarResumen();
    }

    function bindFilas() {
        tbody.querySelectorAll('.chk-item').forEach(function (chk) {
            chk.addEventListener('change', function () {
                var fila   = this.closest('tr');
                var cantIn = fila.querySelector('.input-cantidad');
                if (this.checked && cantIn && !cantIn.value) cantIn.value = '1';
                else if (!this.checked && cantIn) cantIn.value = '';
                actualizarResumen();
            });
        });
        tbody.querySelectorAll('.input-cantidad').forEach(function (inp) {
            inp.addEventListener('input', function () {
                var chk = this.closest('tr').querySelector('.chk-item');
                var max = parseFloat(this.max);
                var val = parseFloat(this.value);
                this.classList.remove('is-invalid');
                if (this.value && val > 0) {
                    chk.checked = true;
                    if (val > max) this.classList.add('is-invalid');
                } else {
                    chk.checked = false;
                }
                actualizarResumen();
            });
        });
        chkTodos.addEventListener('change', function () {
            tbody.querySelectorAll('.chk-item:not(:disabled)').forEach(function (c) {
                c.checked = this.checked;
                var cantIn = c.closest('tr').querySelector('.input-cantidad');
                if (this.checked && cantIn && !cantIn.value) cantIn.value = '1';
                else if (!this.checked && cantIn) cantIn.value = '';
            }.bind(this));
            actualizarResumen();
        });
    }

    function actualizarResumen() {
        var nItems = 0, total = 0;
        tbody.querySelectorAll('.chk-item').forEach(function (chk) {
            if (chk.checked) {
                nItems++;
                var cantIn = chk.closest('tr').querySelector('.input-cantidad');
                var v = cantIn ? parseFloat(cantIn.value) : 0;
                if (!isNaN(v)) total += v;
            }
        });
        resumenItems.textContent    = nItems;
        resumenCantidad.textContent = fmt(total);

        // Validar que origen != destino
        var dst = getDestinoActual();
        var org = parseInt(selOrigen.value, 10) || 0;
        btnConfirmar.disabled = (nItems === 0) || !org || !dst || (org === dst);
    }

    buscador.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        tbody.querySelectorAll('tr.fila-producto').forEach(function (tr) {
            var match = !q || tr.dataset.nombre.indexOf(q) !== -1 || tr.dataset.codigo.indexOf(q) !== -1;
            tr.style.display = match ? '' : 'none';
        });
    });

    selOrigen.addEventListener('change', renderProductos);
    if (selDestino) selDestino.addEventListener('change', actualizarResumen);

    renderProductos();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>