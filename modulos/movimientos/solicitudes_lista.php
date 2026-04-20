<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega', 'solicitante'));

$user      = current_user();
$miBodega  = user_bodega_id();
$miUid     = (int)$user['id'];

// ============================================================
// HELPER: ¿puede este usuario aprobar/rechazar esta solicitud?
// ============================================================
function puede_procesar($sol) {
    if (is_admin()) return true;
    if (is_encargado()) {
        return ((int)$sol['id_bodega_origen'] === (int)user_bodega_id());
    }
    return false;
}

// ============================================================
// ACCION: PROCESAR (aprobar y ejecutar traslado)
// ============================================================
if (isset($_GET['procesar'])) {
    $id_sol = (int)$_GET['procesar'];

    $stmtSol = $pdo->prepare("
        SELECT s.*, bo.nombre AS bodega_origen_nombre, bd.nombre AS bodega_destino_nombre
        FROM solicitudes s
        LEFT JOIN bodegas bo ON bo.id = s.id_bodega_origen
        INNER JOIN bodegas bd ON bd.id = s.id_bodega_destino
        WHERE s.id = ? AND s.estado = 'pendiente' LIMIT 1
    ");
    $stmtSol->execute(array($id_sol));
    $sol = $stmtSol->fetch();

    if (!$sol) {
        set_flash('error', 'Solicitud no encontrada o ya procesada.');
        redirect('solicitudes_lista.php');
    }
    if (!puede_procesar($sol)) {
        set_flash('error', 'No tienes permisos para procesar esta solicitud.');
        redirect('solicitudes_lista.php');
    }
    if (!$sol['id_bodega_origen']) {
        set_flash('error', 'Solicitud sin bodega origen especificada.');
        redirect('solicitudes_lista.php');
    }

    $stmtItems = $pdo->prepare("
        SELECT sd.*, p.nombre AS producto_nombre
        FROM solicitudes_detalle sd
        INNER JOIN productos p ON p.id = sd.id_producto
        WHERE sd.id_solicitud = ?
    ");
    $stmtItems->execute(array($id_sol));
    $items = $stmtItems->fetchAll();

    // Validar stock
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
        $obsBase = 'Solicitud ' . $sol['numero_solicitud'];

        foreach ($items as $item) {
            $cant = (float)$item['cantidad'];

            // Salida origen
            $pdo->prepare("
                INSERT INTO movimientos_bodega
                    (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total,
                     referencia_tipo, referencia_id, observacion, id_usuario)
                VALUES (?, ?, 'traslado_salida', ?, 0, 0, 'solicitud', 0, ?, ?)
            ")->execute(array((int)$sol['id_bodega_origen'], $item['id_producto'], $cant, $obsBase, $miUid));

            $idM = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE movimientos_bodega SET referencia_id=? WHERE id=?")
                ->execute(array($idM, $idM));
            $pdo->prepare("UPDATE stock_bodega SET stock_actual=stock_actual-? WHERE id_bodega=? AND id_producto=?")
                ->execute(array($cant, (int)$sol['id_bodega_origen'], $item['id_producto']));

            // Entrada destino
            $pdo->prepare("
                INSERT INTO movimientos_bodega
                    (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total,
                     referencia_tipo, referencia_id, observacion, id_usuario)
                VALUES (?, ?, 'traslado_entrada', ?, 0, 0, 'solicitud', ?, ?, ?)
            ")->execute(array((int)$sol['id_bodega_destino'], $item['id_producto'], $cant, $idM, $obsBase, $miUid));

            $stD = $pdo->prepare("SELECT id FROM stock_bodega WHERE id_bodega=? AND id_producto=? LIMIT 1");
            $stD->execute(array((int)$sol['id_bodega_destino'], $item['id_producto']));
            $sD = $stD->fetch();
            if ($sD) {
                $pdo->prepare("UPDATE stock_bodega SET stock_actual=stock_actual+? WHERE id=?")
                    ->execute(array($cant, $sD['id']));
            } else {
                $pdo->prepare("INSERT INTO stock_bodega (id_bodega,id_producto,stock_actual,costo_promedio) VALUES (?,?,?,0)")
                    ->execute(array((int)$sol['id_bodega_destino'], $item['id_producto'], $cant));
            }
        }

        $pdo->prepare("
            UPDATE solicitudes SET estado='procesada', id_usuario_respuesta=?, fecha_respuesta=NOW()
            WHERE id=?
        ")->execute(array($miUid, $id_sol));

        $pdo->commit();
        set_flash('success', 'Solicitud ' . $sol['numero_solicitud'] . ' procesada. Stock trasladado.');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Error al procesar: ' . $e->getMessage());
    }

    redirect('solicitudes_lista.php');
}

// ============================================================
// ACCION: RECHAZAR
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rechazar') {
    $id_sol = (int)post('id_sol');
    $motivo = post('motivo_rechazo');

    $stmtSol = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? AND estado = 'pendiente' LIMIT 1");
    $stmtSol->execute(array($id_sol));
    $sol = $stmtSol->fetch();

    if (!$sol) {
        set_flash('error', 'Solicitud no encontrada o ya procesada.');
    } elseif (!puede_procesar($sol)) {
        set_flash('error', 'No tienes permisos para rechazar esta solicitud.');
    } else {
        $pdo->prepare("
            UPDATE solicitudes
            SET estado='rechazada', observacion_respuesta=?, id_usuario_respuesta=?, fecha_respuesta=NOW()
            WHERE id=? AND estado='pendiente'
        ")->execute(array($motivo, $miUid, $id_sol));
        set_flash('success', 'Solicitud rechazada.');
    }
    redirect('solicitudes_lista.php');
}

// ============================================================
// VISTA Y FILTROS
// ============================================================
$filtroEstado = get('estado', '');
$vista        = get('vista', '');

// Default vista para encargado
if (is_encargado() && $vista === '') {
    $vista = 'recibidas';
}

// ============================================================
// LISTADO
// ============================================================
$baseSelect = "
    SELECT s.*,
           u.nombre  AS usuario_nombre,
           bo.nombre AS origen_nombre,  bo.codigo AS origen_codigo,
           bd.nombre AS destino_nombre, bd.codigo AS destino_codigo
    FROM solicitudes s
    LEFT  JOIN usuarios u  ON u.id  = s.id_usuario
    LEFT  JOIN bodegas  bo ON bo.id = s.id_bodega_origen
    LEFT  JOIN bodegas  bd ON bd.id = s.id_bodega_destino
    WHERE 1=1
";

$where  = '';
$params = array();

if (is_admin()) {
    // sin filtro adicional
} elseif (is_encargado()) {
    if ($vista === 'enviadas') {
        $where .= " AND s.id_usuario = :uid";
        $params[':uid'] = $miUid;
    } else {
        // recibidas: las que debo atender
        $where .= " AND s.id_bodega_origen = :bod";
        $params[':bod'] = $miBodega;
    }
} else {
    // solicitante: solo mias
    $where .= " AND s.id_usuario = :uid";
    $params[':uid'] = $miUid;
}

if ($filtroEstado !== '') {
    $where .= " AND s.estado = :estado";
    $params[':estado'] = $filtroEstado;
}

$sql = $baseSelect . $where . " ORDER BY s.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

// Contadores para tabs/badges del encargado
$countRecibidas = 0;
$countEnviadas  = 0;
$countRecibidasPend = 0;
if (is_encargado()) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE id_bodega_origen = ?");
    $st->execute(array($miBodega));
    $countRecibidas = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE id_bodega_origen = ? AND estado = 'pendiente'");
    $st->execute(array($miBodega));
    $countRecibidasPend = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE id_usuario = ?");
    $st->execute(array($miUid));
    $countEnviadas = (int)$st->fetchColumn();
}

// Detalles para modal (precargados)
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
                'codigo'      => $row['codigo'],
                'nombre'      => $row['nombre'],
                'cantidad'    => number_format((float)$row['cantidad'], 2, ',', '.'),
                'observacion' => $row['observacion']
            );
        }
    }
}

$pageTitle = 'Solicitudes de Traslado';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-clipboard-check text-primary me-2"></i>
            <?php
            if (is_encargado()) {
                echo ($vista === 'enviadas') ? 'Mis Solicitudes Enviadas' : 'Solicitudes Recibidas';
            } elseif (is_solicitante()) {
                echo 'Mis Solicitudes';
            } else {
                echo 'Solicitudes de Traslado';
            }
            ?>
        </h1>
        <?php if (is_encargado()): ?>
            <p class="text-muted mb-0 small mt-1">
                <?php if ($vista === 'enviadas'): ?>
                    Solicitudes que tú enviaste a otras bodegas.
                <?php else: ?>
                    Solicitudes que otras bodegas o usuarios te enviaron. Puedes aprobarlas o rechazarlas.
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <a href="solicitudes_crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nueva Solicitud
    </a>
</div>

<?php /* ============================================================
        TABS (solo encargado)
        ============================================================ */ ?>
<?php if (is_encargado()): ?>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo ($vista !== 'enviadas') ? 'active fw-bold' : ''; ?>"
           href="?vista=recibidas">
            <i class="bi bi-inbox me-1"></i> Recibidas
            <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1"><?php echo $countRecibidas; ?></span>
            <?php if ($countRecibidasPend > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?php echo $countRecibidasPend; ?> pend.</span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($vista === 'enviadas') ? 'active fw-bold' : ''; ?>"
           href="?vista=enviadas">
            <i class="bi bi-send me-1"></i> Enviadas
            <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1"><?php echo $countEnviadas; ?></span>
        </a>
    </li>
</ul>
<?php endif; ?>

<!-- Filtros por estado -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
            <?php if (is_encargado()): ?>
                <input type="hidden" name="vista" value="<?php echo h($vista); ?>">
            <?php endif; ?>
            <label class="fw-bold text-secondary small mb-0">Filtrar:</label>
            <?php
            $estados = array(
                ''           => 'Todas',
                'pendiente'  => 'Pendientes',
                'procesada'  => 'Procesadas',
                'rechazada'  => 'Rechazadas'
            );
            foreach ($estados as $val => $lbl):
                $url = '?' . (is_encargado() ? 'vista=' . urlencode($vista) . '&' : '') . 'estado=' . urlencode($val);
            ?>
                <a href="<?php echo h($url); ?>"
                   class="btn btn-sm <?php echo ($filtroEstado === $val) ? 'btn-primary' : 'btn-light border'; ?>">
                    <?php echo h($lbl); ?>
                </a>
            <?php endforeach; ?>
        </form>
    </div>
</div>

<!-- TABLA -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-secondary">
                    <tr>
                        <th class="px-3 py-2">Número</th>
                        <th class="py-2">Origen → Destino</th>
                        <?php if (is_admin() || (is_encargado() && $vista !== 'enviadas')): ?>
                            <th class="py-2">Solicitante</th>
                        <?php endif; ?>
                        <th class="py-2">Motivo</th>
                        <th class="py-2 text-center">Estado</th>
                        <th class="py-2 text-nowrap">Fecha</th>
                        <th class="px-3 py-2 text-end" style="min-width:200px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$solicitudes): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No se encontraron solicitudes con los filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($solicitudes as $s):
                        $est = $s['estado'];
                        $estInfo = array(
                            'pendiente' => array('bg-warning text-dark', 'bi-hourglass-split', 'Pendiente'),
                            'procesada' => array('bg-success',            'bi-check-circle',    'Procesada'),
                            'rechazada' => array('bg-danger',             'bi-x-circle',        'Rechazada'),
                        );
                        $badge = isset($estInfo[$est]) ? $estInfo[$est] : array('bg-secondary', 'bi-question-circle', $est);

                        $canProcess = ($est === 'pendiente' && puede_procesar($s));
                        $esMia      = ((int)$s['id_usuario'] === $miUid);
                    ?>
                        <tr>
                            <td class="px-3 fw-bold small"><?php echo h($s['numero_solicitud']); ?></td>
                            <td class="small">
                                <div>
                                    <i class="bi bi-box-arrow-up-right text-primary me-1"></i>
                                    <span class="fw-medium"><?php echo h($s['origen_nombre']); ?></span>
                                </div>
                                <div>
                                    <i class="bi bi-box-arrow-in-down-left text-success me-1"></i>
                                    <span class="fw-medium"><?php echo h($s['destino_nombre']); ?></span>
                                </div>
                            </td>
                            <?php if (is_admin() || (is_encargado() && $vista !== 'enviadas')): ?>
                                <td class="small">
                                    <div class="fw-medium"><?php echo h($s['usuario_nombre']); ?></div>
                                </td>
                            <?php endif; ?>
                            <td class="small text-muted" style="max-width:240px;">
                                <?php echo h(mb_strimwidth((string)$s['observacion'], 0, 80, '…')); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $badge[0]; ?>" style="font-size:.72rem;">
                                    <i class="bi <?php echo $badge[1]; ?> me-1"></i><?php echo h($badge[2]); ?>
                                </span>
                                <?php if ($est === 'rechazada' && $s['observacion_respuesta']): ?>
                                    <div class="text-danger small mt-1" title="<?php echo h($s['observacion_respuesta']); ?>">
                                        <i class="bi bi-chat-left-text"></i>
                                        <?php echo h(mb_strimwidth((string)$s['observacion_respuesta'], 0, 25, '…')); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary text-nowrap">
                                <?php echo date('d/m/Y', strtotime($s['created_at'])); ?>
                                <div style="font-size:.7rem;"><?php echo date('H:i', strtotime($s['created_at'])); ?></div>
                            </td>
                            <td class="px-3 text-end">
                                <div class="d-flex gap-1 justify-content-end flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-ver-detalle"
                                            data-id="<?php echo (int)$s['id']; ?>"
                                            data-numero="<?php echo h($s['numero_solicitud']); ?>">
                                        <i class="bi bi-eye"></i> Detalle
                                    </button>

                                    <?php if ($canProcess): ?>
                                        <a href="?procesar=<?php echo (int)$s['id']; ?>"
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('¿Procesar solicitud <?php echo h($s['numero_solicitud']); ?>? Se descontará stock de la bodega origen.');">
                                            <i class="bi bi-check-lg"></i> Aprobar
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-rechazar"
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-numero="<?php echo h($s['numero_solicitud']); ?>">
                                            <i class="bi bi-x-lg"></i> Rechazar
                                        </button>
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
<?php if (is_admin() || is_encargado()): ?>
<div class="modal fade" id="modalRechazar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="rechazar">
                <input type="hidden" name="id_sol" id="rechazarIdSol" value="">
                <?php if (is_encargado()): ?>
                    <input type="hidden" name="vista" value="<?php echo h($vista); ?>">
                <?php endif; ?>
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-x-circle text-danger me-2"></i>
                        Rechazar <span id="rechazarNumero"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-secondary">Motivo del rechazo</label>
                    <textarea name="motivo_rechazo" class="form-control" rows="3"
                              placeholder="Explica al solicitante por qué no puedes atender esta solicitud..."
                              required></textarea>
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
    var detalleData = <?php echo json_encode($allItems); ?>;

    document.querySelectorAll('.btn-ver-detalle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id     = this.getAttribute('data-id');
            var numero = this.getAttribute('data-numero');
            document.getElementById('modalDetalleNumero').textContent = numero;
            var items = detalleData[id] || [];
            var html;
            if (!items.length) {
                html = '<p class="text-muted py-4 text-center mb-0">Sin productos registrados.</p>';
            } else {
                html = '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th class="px-3">Código</th><th>Producto</th><th class="text-end">Cantidad</th><th class="px-3">Obs.</th></tr></thead><tbody>';
                items.forEach(function (it) {
                    html += '<tr>'
                         +  '<td class="px-3"><span class="badge bg-light text-dark border">' + it.codigo + '</span></td>'
                         +  '<td class="fw-medium">' + it.nombre + '</td>'
                         +  '<td class="text-end fw-bold">' + it.cantidad + '</td>'
                         +  '<td class="px-3 text-muted small">' + (it.observacion || '—') + '</td>'
                         +  '</tr>';
                });
                html += '</tbody></table>';
            }
            document.getElementById('modalDetalleBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
    });

    document.querySelectorAll('.btn-rechazar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('rechazarIdSol').value        = this.getAttribute('data-id');
            document.getElementById('rechazarNumero').textContent = this.getAttribute('data-numero');
            new bootstrap.Modal(document.getElementById('modalRechazar')).show();
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>