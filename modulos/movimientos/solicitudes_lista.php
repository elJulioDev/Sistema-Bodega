<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_login();

$user      = current_user();
$esBodega  = has_role(array('admin', 'bodega'));

// --- ACCIÓN: PROCESAR ---
if (isset($_GET['procesar']) && $esBodega) {
    $id_sol = (int)$_GET['procesar'];

    $stmtSol = $pdo->prepare("SELECT s.*, bo.nombre AS bodega_origen_nombre, bd.nombre AS bodega_destino_nombre FROM solicitudes s LEFT JOIN bodegas bo ON bo.id = s.id_bodega_origen INNER JOIN bodegas bd ON bd.id = s.id_bodega_destino WHERE s.id = ? AND s.estado = 'pendiente' LIMIT 1");
    $stmtSol->execute(array($id_sol));
    $sol = $stmtSol->fetch();

    if (!$sol) {
        set_flash('error', 'Solicitud no encontrada o ya procesada.');
        redirect('solicitudes_lista.php');
    }

    $stmtItems = $pdo->prepare("SELECT sd.*, p.nombre AS producto_nombre FROM solicitudes_detalle sd INNER JOIN productos p ON p.id = sd.id_producto WHERE sd.id_solicitud = ?");
    $stmtItems->execute(array($id_sol));
    $items = $stmtItems->fetchAll();

    // id_bodega_origen es requerido para el traslado real
    if (!$sol['id_bodega_origen']) {
        set_flash('error', 'Solicitud sin bodega origen especificada. Edita la solicitud antes de procesar.');
        redirect('solicitudes_lista.php');
    }

    // Validar stock de todos los items
    $errorStock = '';
    foreach ($items as $item) {
        $stS = $pdo->prepare("SELECT stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
        $stS->execute(array((int)$sol['id_bodega_origen'], $item['id_producto']));
        $cur = (float)($stS->fetchColumn() ?: 0);
        if ($cur < (float)$item['cantidad']) {
            $errorStock = 'Stock insuficiente: "' . $item['producto_nombre'] . '". Disponible: ' . number_format($cur, 2, ',', '.') . ', requerido: ' . number_format((float)$item['cantidad'], 2, ',', '.');
            break;
        }
    }

    if ($errorStock !== '') {
        set_flash('error', $errorStock);
        redirect('solicitudes_lista.php');
    }

    try {
        $pdo->beginTransaction();
        $uid     = (int)$user['id'];
        $obsBase = 'Solicitud ' . $sol['numero_solicitud'];

        foreach ($items as $item) {
            $cant = (float)$item['cantidad'];

            // Salida origen
            $pdo->prepare("INSERT INTO movimientos_bodega (id_bodega,id_producto,tipo_movimiento,cantidad,precio_unitario,total,referencia_tipo,referencia_id,observacion,id_usuario) VALUES (?,?,'traslado_salida',?,0,0,'solicitud',0,?,?)")
                ->execute(array((int)$sol['id_bodega_origen'], $item['id_producto'], $cant, $obsBase, $uid));
            $idM = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE movimientos_bodega SET referencia_id=? WHERE id=?")->execute(array($idM, $idM));
            $pdo->prepare("UPDATE stock_bodega SET stock_actual=stock_actual-? WHERE id_bodega=? AND id_producto=?")->execute(array($cant, (int)$sol['id_bodega_origen'], $item['id_producto']));

            // Entrada destino
            $pdo->prepare("INSERT INTO movimientos_bodega (id_bodega,id_producto,tipo_movimiento,cantidad,precio_unitario,total,referencia_tipo,referencia_id,observacion,id_usuario) VALUES (?,?,'traslado_entrada',?,0,0,'solicitud',?,?,?)")
                ->execute(array((int)$sol['id_bodega_destino'], $item['id_producto'], $cant, $idM, $obsBase, $uid));

            $stD = $pdo->prepare("SELECT id FROM stock_bodega WHERE id_bodega=? AND id_producto=? LIMIT 1");
            $stD->execute(array((int)$sol['id_bodega_destino'], $item['id_producto']));
            $sD = $stD->fetch();
            if ($sD) {
                $pdo->prepare("UPDATE stock_bodega SET stock_actual=stock_actual+? WHERE id=?")->execute(array($cant, $sD['id']));
            } else {
                $pdo->prepare("INSERT INTO stock_bodega (id_bodega,id_producto,stock_actual,costo_promedio) VALUES (?,?,?,0)")->execute(array((int)$sol['id_bodega_destino'], $item['id_producto'], $cant));
            }
        }

        $pdo->prepare("UPDATE solicitudes SET estado='procesada', id_usuario_respuesta=?, fecha_respuesta=NOW() WHERE id=?")->execute(array($uid, $id_sol));
        $pdo->commit();
        set_flash('success', 'Solicitud ' . $sol['numero_solicitud'] . ' procesada. Stock actualizado.');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Error al procesar: ' . $e->getMessage());
    }

    redirect('solicitudes_lista.php');
}

// --- ACCIÓN: RECHAZAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rechazar' && $esBodega) {
    $id_sol  = (int)post('id_sol');
    $motivo  = post('motivo_rechazo');
    $uid     = (int)$user['id'];
    $pdo->prepare("UPDATE solicitudes SET estado='rechazada', observacion_respuesta=?, id_usuario_respuesta=?, fecha_respuesta=NOW() WHERE id=? AND estado='pendiente'")->execute(array($motivo, $uid, $id_sol));
    set_flash('success', 'Solicitud rechazada.');
    redirect('solicitudes_lista.php');
}

// --- LISTADO ---
$filtroEstado = get('estado', '');

if ($esBodega) {
    $sql = "SELECT s.*, u.nombre AS usuario_nombre, bo.nombre AS origen_nombre, bd.nombre AS destino_nombre
            FROM solicitudes s
            INNER JOIN usuarios u ON u.id = s.id_usuario
            LEFT JOIN bodegas bo ON bo.id = s.id_bodega_origen
            INNER JOIN bodegas bd ON bd.id = s.id_bodega_destino
            WHERE 1=1";
    $params = array();
    if ($filtroEstado !== '') {
        $sql .= " AND s.estado = :estado";
        $params[':estado'] = $filtroEstado;
    }
    $sql .= " ORDER BY s.id DESC";
} else {
    $sql = "SELECT s.*, u.nombre AS usuario_nombre, bo.nombre AS origen_nombre, bd.nombre AS destino_nombre
            FROM solicitudes s
            INNER JOIN usuarios u ON u.id = s.id_usuario
            LEFT JOIN bodegas bo ON bo.id = s.id_bodega_origen
            INNER JOIN bodegas bd ON bd.id = s.id_bodega_destino
            WHERE s.id_usuario = :uid ORDER BY s.id DESC";
    $params = array(':uid' => (int)$user['id']);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

$pageTitle = 'Solicitudes de Traslado';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-clipboard-check text-primary me-2"></i>Solicitudes de Traslado</h1>
    <a href="solicitudes_crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nueva Solicitud</a>
</div>

<?php if ($esBodega): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
            <label class="fw-bold text-secondary small mb-0">Filtrar:</label>
            <?php
            $estados = array('' => 'Todas', 'pendiente' => 'Pendientes', 'procesada' => 'Procesadas', 'rechazada' => 'Rechazadas');
            foreach ($estados as $val => $lbl): ?>
                <a href="?estado=<?php echo $val; ?>" class="btn btn-sm <?php echo ($filtroEstado === $val) ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $lbl; ?></a>
            <?php endforeach; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size:.85rem;">
                    <tr>
                        <th class="px-4 py-3">SOLICITUD</th>
                        <th class="py-3">SOLICITANTE</th>
                        <th class="py-3">ORIGEN → DESTINO</th>
                        <th class="py-3">FECHA</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$solicitudes): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No hay solicitudes registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($solicitudes as $s): ?>
                        <?php
                        $est = $s['estado'];
                        $badgeClass = 'bg-secondary';
                        if ($est === 'pendiente')  $badgeClass = 'bg-warning text-dark';
                        if ($est === 'procesada')  $badgeClass = 'bg-success';
                        if ($est === 'rechazada')  $badgeClass = 'bg-danger';
                        ?>
                        <tr>
                            <td class="px-4">
                                <span class="fw-bold text-dark"><?php echo h($s['numero_solicitud']); ?></span>
                                <div class="small text-muted mt-1" style="max-width:200px;" title="<?php echo h($s['observacion']); ?>">
                                    <?php echo $s['observacion'] ? h(mb_strimwidth($s['observacion'], 0, 60, '…')) : '—'; ?>
                                </div>
                            </td>
                            <td><i class="bi bi-person-circle text-muted me-1"></i><?php echo h($s['usuario_nombre']); ?></td>
                            <td>
                                <span class="text-muted small"><?php echo $s['origen_nombre'] ? h($s['origen_nombre']) : '<em>Sin especificar</em>'; ?></span>
                                <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                <span class="badge bg-primary bg-opacity-10 text-primary border-0"><?php echo h($s['destino_nombre']); ?></span>
                            </td>
                            <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $badgeClass; ?> border-0 text-uppercase"><?php echo h($est); ?></span>
                            </td>
                            <td class="px-4 text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <!-- Ver detalle -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-ver-detalle"
                                            data-id="<?php echo (int)$s['id']; ?>"
                                            data-numero="<?php echo h($s['numero_solicitud']); ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <?php if ($esBodega && $est === 'pendiente'): ?>
                                        <a href="?procesar=<?php echo (int)$s['id']; ?>"
                                           class="btn btn-sm btn-success"
                                           title="Procesar traslado"
                                           onclick="return confirm('¿Procesar solicitud <?php echo h($s['numero_solicitud']); ?>? Se descontará stock de bodega origen.');">
                                            <i class="bi bi-check-lg"></i> Procesar
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-rechazar"
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-numero="<?php echo h($s['numero_solicitud']); ?>">
                                            <i class="bi bi-x-lg"></i> Rechazar
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($est === 'rechazada' && $s['observacion_respuesta']): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border-0 small p-2" title="<?php echo h($s['observacion_respuesta']); ?>">
                                            <i class="bi bi-chat-left-text me-1"></i><?php echo h(mb_strimwidth($s['observacion_respuesta'], 0, 30, '…')); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Ver Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-clipboard-check text-primary me-2"></i><span id="modalDetalleNumero"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalDetalleBody" class="p-4 text-center text-muted">Cargando...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Rechazar -->
<?php if ($esBodega): ?>
<div class="modal fade" id="modalRechazar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="rechazar">
                <input type="hidden" name="id_sol" id="rechazarIdSol" value="">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-x-circle text-danger me-2"></i>Rechazar <span id="rechazarNumero"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-secondary">Motivo del rechazo</label>
                    <textarea name="motivo_rechazo" class="form-control" rows="3" placeholder="Explica el motivo al solicitante..." required></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    // Modal ver detalle — carga items via fetch inline
    var detalleData = <?php
        // Build a JS object: {id: [{producto, cantidad, obs}, ...]}
        $allItems = array();
        if ($solicitudes) {
            $ids = array();
            foreach ($solicitudes as $s) $ids[] = (int)$s['id'];
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmtAll = $pdo->prepare("
                    SELECT sd.id_solicitud, p.codigo, p.nombre, sd.cantidad, sd.observacion
                    FROM solicitudes_detalle sd
                    INNER JOIN productos p ON p.id = sd.id_producto
                    WHERE sd.id_solicitud IN ($placeholders)
                    ORDER BY sd.id_solicitud, sd.id
                ");
                $stmtAll->execute($ids);
                foreach ($stmtAll->fetchAll() as $row) {
                    $sid = $row['id_solicitud'];
                    if (!isset($allItems[$sid])) $allItems[$sid] = array();
                    $allItems[$sid][] = array(
                        'codigo'     => $row['codigo'],
                        'nombre'     => $row['nombre'],
                        'cantidad'   => number_format((float)$row['cantidad'], 2, ',', '.'),
                        'observacion'=> $row['observacion']
                    );
                }
            }
        }
        echo json_encode($allItems);
    ?>;

    document.querySelectorAll('.btn-ver-detalle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id     = this.getAttribute('data-id');
            var numero = this.getAttribute('data-numero');
            document.getElementById('modalDetalleNumero').textContent = numero;
            var items  = detalleData[id] || [];
            var html   = '';
            if (!items.length) {
                html = '<p class="text-muted">Sin productos registrados.</p>';
            } else {
                html = '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Código</th><th>Producto</th><th class="text-end">Cantidad</th><th>Obs.</th></tr></thead><tbody>';
                items.forEach(function (it) {
                    html += '<tr><td><span class="badge bg-light text-dark border">' + it.codigo + '</span></td><td class="fw-medium">' + it.nombre + '</td><td class="text-end fw-bold">' + it.cantidad + '</td><td class="text-muted small">' + (it.observacion || '—') + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            document.getElementById('modalDetalleBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
    });

    // Modal rechazar
    document.querySelectorAll('.btn-rechazar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('rechazarIdSol').value   = this.getAttribute('data-id');
            document.getElementById('rechazarNumero').textContent = this.getAttribute('data-numero');
            new bootstrap.Modal(document.getElementById('modalRechazar')).show();
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>