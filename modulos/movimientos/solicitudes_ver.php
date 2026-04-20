<?php
/**
 * solicitudes_ver.php
 * Revisión y aprobación de solicitudes con validación de stock por ítem.
 * Admin y Encargado pueden: editar cantidades aprobadas, rechazar ítems, ejecutar o rechazar.
 */
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega', 'solicitante'));

$miUid    = (int)current_user()['id'];
$id_sol   = (int)get('id');

if ($id_sol <= 0) {
    set_flash('error', 'Solicitud no válida.');
    redirect('solicitudes_lista.php');
}

// ── Cargar solicitud ────────────────────────────────────────
$stmtSol = $pdo->prepare("
    SELECT s.*,
           u.nombre   AS usuario_nombre,
           ur.nombre  AS respuesta_nombre,
           bo.nombre  AS origen_nombre,  bo.codigo AS origen_codigo,
           bd.nombre  AS destino_nombre, bd.codigo AS destino_codigo
    FROM   solicitudes s
    LEFT JOIN usuarios u  ON u.id  = s.id_usuario
    LEFT JOIN usuarios ur ON ur.id = s.id_usuario_respuesta
    LEFT JOIN bodegas  bo ON bo.id = s.id_bodega_origen
    LEFT JOIN bodegas  bd ON bd.id = s.id_bodega_destino
    WHERE  s.id = ?
    LIMIT 1
");
$stmtSol->execute(array($id_sol));
$sol = $stmtSol->fetch();

if (!$sol) {
    set_flash('error', 'Solicitud no encontrada.');
    redirect('solicitudes_lista.php');
}

// ── Helper permiso ──────────────────────────────────────────
function puede_procesar_ver($sol) {
    if (is_admin()) return true;
    if (is_encargado()) {
        return ((int)$sol['id_bodega_origen'] === (int)user_bodega_id());
    }
    return false;
}

$puedeGestionar = puede_procesar_ver($sol);
$esPendiente = ($sol['estado'] === 'pendiente' || $sol['estado'] === 'en_revision');
    // 'caducada', 'rechazada', 'procesada', 'procesada_parcial' → solo lectura

// ── Cargar ítems con stock actual ───────────────────────────
$stmtItems = $pdo->prepare("
    SELECT sd.*,
           p.nombre AS producto_nombre,
           p.codigo AS producto_codigo,
           p.controla_stock,
           COALESCE(sb.stock_actual, 0) AS stock_disponible
    FROM   solicitudes_detalle sd
    INNER  JOIN productos p ON p.id = sd.id_producto
    LEFT   JOIN stock_bodega sb
           ON  sb.id_producto = sd.id_producto
           AND sb.id_bodega   = ?
    WHERE  sd.id_solicitud = ?
    ORDER  BY sd.id
");
$stmtItems->execute(array((int)$sol['id_bodega_origen'], $id_sol));
$items = $stmtItems->fetchAll();

// ── Log de auditoría ────────────────────────────────────────
$stmtLog = $pdo->prepare("
    SELECT sl.*, u.nombre AS usuario_nombre
    FROM   solicitudes_log sl
    LEFT   JOIN usuarios u ON u.id = sl.id_usuario
    WHERE  sl.id_solicitud = ?
    ORDER  BY sl.id DESC
");
$stmtLog->execute(array($id_sol));
$logs = $stmtLog->fetchAll();

// ============================================================
// ACCIÓN: GUARDAR REVISIÓN (sin ejecutar)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_revision') {
    if (!$puedeGestionar || !$esPendiente) {
        set_flash('error', 'Sin permiso o solicitud ya procesada.');
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }

    $itemEstados   = isset($_POST['item_estado'])    ? $_POST['item_estado']    : array();
    $itemCantAprob = isset($_POST['item_cant_ap'])   ? $_POST['item_cant_ap']   : array();
    $itemMotivo    = isset($_POST['item_motivo'])     ? $_POST['item_motivo']    : array();
    $itemIds       = isset($_POST['item_id'])         ? $_POST['item_id']        : array();

    try {
        $pdo->beginTransaction();

        $stmtUpd = $pdo->prepare("
            UPDATE solicitudes_detalle
            SET    estado            = ?,
                   cantidad_aprobada = ?,
                   motivo_ajuste     = ?
            WHERE  id            = ?
              AND  id_solicitud  = ?
        ");

        $logLineas = array();
        foreach ($itemIds as $k => $detId) {
            $detId    = (int)$detId;
            $est      = (isset($itemEstados[$k])   && in_array($itemEstados[$k], array('aprobado','rechazado','pendiente')))
                        ? $itemEstados[$k] : 'pendiente';
            $cantAp   = (isset($itemCantAprob[$k]) && $itemCantAprob[$k] !== '')
                        ? (float)str_replace(',', '.', $itemCantAprob[$k]) : null;
            $motivo   = isset($itemMotivo[$k]) ? trim($itemMotivo[$k]) : '';

            $stmtUpd->execute(array($est, $cantAp, $motivo ?: null, $detId, $id_sol));
            $logLineas[] = 'Ítem #' . $detId . ': ' . $est . ($cantAp !== null ? ' (' . $cantAp . ')' : '');
        }

        // Poner estado en_revision
        $pdo->prepare("UPDATE solicitudes SET estado='en_revision' WHERE id=? AND estado='pendiente'")
            ->execute(array($id_sol));

        // Log
        $pdo->prepare("INSERT INTO solicitudes_log (id_solicitud, id_usuario, accion, detalle) VALUES (?,?,?,?)")
            ->execute(array($id_sol, $miUid, 'revision_guardada', implode(' | ', $logLineas)));

        $pdo->commit();
        set_flash('success', 'Revisión guardada. Puedes continuar editando o ejecutar.');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Error: ' . $e->getMessage());
    }
    redirect('solicitudes_ver.php?id=' . $id_sol);
}

// ============================================================
// ACCIÓN: EJECUTAR (aprobar y mover stock)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ejecutar') {
    if (!$puedeGestionar || !$esPendiente) {
        set_flash('error', 'Sin permiso o solicitud ya procesada.');
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }

    // Guardar revisión antes de ejecutar
    $itemEstados   = isset($_POST['item_estado'])  ? $_POST['item_estado']  : array();
    $itemCantAprob = isset($_POST['item_cant_ap']) ? $_POST['item_cant_ap'] : array();
    $itemMotivo    = isset($_POST['item_motivo'])   ? $_POST['item_motivo']  : array();
    $itemIds       = isset($_POST['item_id'])       ? $_POST['item_id']      : array();

    // Construir mapa de aprobaciones
    $aprobados = array(); // detId => cantidad_aprobada
    foreach ($itemIds as $k => $detId) {
        $detId  = (int)$detId;
        $est    = isset($itemEstados[$k]) ? $itemEstados[$k] : 'pendiente';
        $cantAp = (isset($itemCantAprob[$k]) && $itemCantAprob[$k] !== '')
                  ? (float)str_replace(',', '.', $itemCantAprob[$k]) : null;
        if ($est === 'aprobado' && $cantAp > 0) {
            $aprobados[$detId] = $cantAp;
        }
    }

    if (!$aprobados) {
        set_flash('error', 'Debes aprobar al menos un ítem con cantidad válida.');
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }

    // Validar stock en tiempo real (race-condition safe via SELECT FOR UPDATE)
    $stmtRel = $pdo->prepare("
        SELECT sd.id, sd.id_producto, p.nombre AS pnombre,
               COALESCE(sb.stock_actual, 0) AS stock_disp
        FROM   solicitudes_detalle sd
        INNER  JOIN productos p ON p.id = sd.id_producto
        LEFT   JOIN stock_bodega sb ON sb.id_producto = sd.id_producto AND sb.id_bodega = ?
        WHERE  sd.id_solicitud = ?
    ");
    $stmtRel->execute(array((int)$sol['id_bodega_origen'], $id_sol));
    $itemsReales = $stmtRel->fetchAll();
    $stockMap = array();
    foreach ($itemsReales as $ir) {
        $stockMap[$ir['id']] = array('stock' => (float)$ir['stock_disp'], 'nombre' => $ir['pnombre'], 'id_producto' => $ir['id_producto']);
    }

    $errStock = '';
    foreach ($aprobados as $detId => $cantAp) {
        if (!isset($stockMap[$detId])) continue;
        $disp = $stockMap[$detId]['stock'];
        if ($cantAp > $disp) {
            $errStock = 'Stock insuficiente para "' . $stockMap[$detId]['nombre']
                      . '". Disponible: ' . number_format($disp, 2, ',', '.')
                      . ', aprobado: '    . number_format($cantAp, 2, ',', '.');
            break;
        }
    }

    if ($errStock !== '') {
        set_flash('error', $errStock . ' Ajusta la cantidad aprobada.');
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }

    try {
        $pdo->beginTransaction();

        // Guardar estados finales de ítems
        $stmtUpdItm = $pdo->prepare("
            UPDATE solicitudes_detalle
            SET    estado            = ?,
                   cantidad_aprobada = ?,
                   motivo_ajuste     = ?
            WHERE  id = ? AND id_solicitud = ?
        ");
        foreach ($itemIds as $k => $detId) {
            $detId  = (int)$detId;
            $est    = isset($itemEstados[$k]) ? $itemEstados[$k] : 'rechazado';
            $cantAp = isset($aprobados[$detId]) ? $aprobados[$detId] : null;
            $mot    = isset($itemMotivo[$k]) ? trim($itemMotivo[$k]) : null;
            // Si no está en aprobados, forzar rechazado
            if (!isset($aprobados[$detId])) $est = 'rechazado';
            $stmtUpdItm->execute(array($est, $cantAp, $mot ?: null, $detId, $id_sol));
        }

        $obsBase      = 'Solicitud ' . $sol['numero_solicitud'];
        $todosAprob   = (count($aprobados) === count($items));
        $estadoFinal  = $todosAprob ? 'procesada' : 'procesada_parcial';

        foreach ($aprobados as $detId => $cantAp) {
            $idProd = $stockMap[$detId]['id_producto'];

            // Salida bodega origen
            $pdo->prepare("
                INSERT INTO movimientos_bodega
                    (id_bodega, id_producto, tipo_movimiento, cantidad,
                     precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario)
                VALUES (?, ?, 'traslado_salida', ?, 0, 0, 'solicitud', ?, ?, ?)
            ")->execute(array((int)$sol['id_bodega_origen'], $idProd, $cantAp, $id_sol, $obsBase, $miUid));

            $pdo->prepare("
                UPDATE stock_bodega SET stock_actual = stock_actual - ?
                WHERE  id_bodega = ? AND id_producto = ?
            ")->execute(array($cantAp, (int)$sol['id_bodega_origen'], $idProd));

            // Entrada bodega destino
            $pdo->prepare("
                INSERT INTO movimientos_bodega
                    (id_bodega, id_producto, tipo_movimiento, cantidad,
                     precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario)
                VALUES (?, ?, 'traslado_entrada', ?, 0, 0, 'solicitud', ?, ?, ?)
            ")->execute(array((int)$sol['id_bodega_destino'], $idProd, $cantAp, $id_sol, $obsBase, $miUid));

            $stD = $pdo->prepare("SELECT id FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
            $stD->execute(array((int)$sol['id_bodega_destino'], $idProd));
            $sD = $stD->fetch();
            if ($sD) {
                $pdo->prepare("UPDATE stock_bodega SET stock_actual = stock_actual + ? WHERE id = ?")
                    ->execute(array($cantAp, $sD['id']));
            } else {
                $pdo->prepare("INSERT INTO stock_bodega (id_bodega, id_producto, stock_actual, costo_promedio) VALUES (?,?,?,0)")
                    ->execute(array((int)$sol['id_bodega_destino'], $idProd, $cantAp));
            }
        }

        // Actualizar solicitud
        $pdo->prepare("
            UPDATE solicitudes
            SET    estado='$estadoFinal', id_usuario_respuesta=?, fecha_respuesta=NOW()
            WHERE  id=?
        ")->execute(array($miUid, $id_sol));

        // Log
        $nAprob = count($aprobados);
        $nTotal = count($items);
        $pdo->prepare("INSERT INTO solicitudes_log (id_solicitud, id_usuario, accion, detalle) VALUES (?,?,?,?)")
            ->execute(array(
                $id_sol, $miUid, 'ejecutada',
                $nAprob . ' de ' . $nTotal . ' ítems ejecutados. Estado: ' . $estadoFinal
            ));

        $pdo->commit();

        $msg = $todosAprob
            ? 'Solicitud ejecutada completamente. Stock trasladado.'
            : 'Solicitud ejecutada parcialmente (' . $nAprob . '/' . $nTotal . ' ítems). Ítems sin stock rechazados.';
        set_flash('success', $msg);
        redirect('solicitudes_lista.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Error al ejecutar: ' . $e->getMessage());
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }
}

// ============================================================
// ACCIÓN: RECHAZAR TOTAL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rechazar') {
    if (!$puedeGestionar || !$esPendiente) {
        set_flash('error', 'Sin permiso o solicitud ya procesada.');
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }
    $motivoRec = trim(isset($_POST['motivo_rechazo']) ? $_POST['motivo_rechazo'] : '');
    if ($motivoRec === '') {
        set_flash('error', 'Debes indicar el motivo de rechazo.');
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE solicitudes
            SET    estado='rechazada', observacion_respuesta=?, id_usuario_respuesta=?, fecha_respuesta=NOW()
            WHERE  id=?
        ")->execute(array($motivoRec, $miUid, $id_sol));

        $pdo->prepare("UPDATE solicitudes_detalle SET estado='rechazado' WHERE id_solicitud=?")
            ->execute(array($id_sol));

        $pdo->prepare("INSERT INTO solicitudes_log (id_solicitud, id_usuario, accion, detalle) VALUES (?,?,?,?)")
            ->execute(array($id_sol, $miUid, 'rechazada', $motivoRec));

        $pdo->commit();
        set_flash('success', 'Solicitud rechazada.');
        redirect('solicitudes_lista.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Error: ' . $e->getMessage());
        redirect('solicitudes_ver.php?id=' . $id_sol);
    }
}

// ============================================================
// VISTA
// ============================================================
$pageTitle = 'Revisar Solicitud ' . h($sol['numero_solicitud']);
require_once __DIR__ . '/../../inc/header.php';

// Helpers para badge de estado solicitud
function badge_sol($estado) {
    switch ($estado) {
        case 'pendiente':         return '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente</span>';
        case 'en_revision':       return '<span class="badge bg-info text-dark"><i class="bi bi-search me-1"></i>En revisión</span>';
        case 'procesada':         return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Ejecutada</span>';
        case 'procesada_parcial': return '<span class="badge bg-teal text-white" style="background:#0d9488!important"><i class="bi bi-check2-all me-1"></i>Parcial</span>';
        case 'rechazada':         return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rechazada</span>';
        case 'caducada':          return '<span class="badge bg-secondary"><i class="bi bi-hourglass-bottom me-1"></i>Caducada</span>';
        default:                  return '<span class="badge bg-secondary">' . h($estado) . '</span>';
    }
}

// Badge estado ítem
function badge_item($est) {
    switch ($est) {
        case 'aprobado':  return '<span class="badge bg-success-subtle text-success border border-success-subtle">Aprobado</span>';
        case 'rechazado': return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Rechazado</span>';
        default:          return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pendiente</span>';
    }
}
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="text-muted small mb-1">
            <a href="solicitudes_lista.php" class="text-decoration-none text-muted">
                <i class="bi bi-chevron-left"></i> Solicitudes
            </a>
        </div>
        <h1 class="h3 mb-0">
            <i class="bi bi-clipboard-check text-primary me-2"></i>
            <?php echo h($sol['numero_solicitud']); ?>
            &nbsp;<?php echo badge_sol($sol['estado']); ?>
        </h1>
        <p class="text-muted small mt-1 mb-0">
            Solicitado por <strong><?php echo h($sol['usuario_nombre']); ?></strong>
            el <?php echo date('d/m/Y H:i', strtotime($sol['created_at'])); ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($puedeGestionar && $esPendiente): ?>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    data-bs-toggle="modal" data-bs-target="#modalRechazar">
                <i class="bi bi-x-circle me-1"></i> Rechazar Todo
            </button>
        <?php endif; ?>
        <a href="solicitudes_lista.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1"><i class="bi bi-box-arrow-right text-danger me-1"></i> Origen</p>
                <p class="fw-bold mb-0"><?php echo h($sol['origen_nombre'] ?: '—'); ?></p>
                <?php if ($sol['origen_codigo']): ?>
                    <small class="text-muted"><?php echo h($sol['origen_codigo']); ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1"><i class="bi bi-box-arrow-in-right text-success me-1"></i> Destino</p>
                <p class="fw-bold mb-0"><?php echo h($sol['destino_nombre']); ?></p>
                <small class="text-muted"><?php echo h($sol['destino_codigo']); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1"><i class="bi bi-chat-left-text me-1"></i> Motivo</p>
                <p class="mb-0"><?php echo h($sol['observacion'] ?: '—'); ?></p>
            </div>
        </div>
    </div>
    
    <?php if (!empty($sol['fecha_limite']) && in_array($sol['estado'], array('pendiente','en_revision'))): ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 <?php echo (strtotime($sol['fecha_limite']) < strtotime('+2 days')) ? 'border-warning border-2' : ''; ?>">
            <div class="card-body">
                <p class="text-muted small mb-1">
                    <i class="bi bi-alarm <?php echo (strtotime($sol['fecha_limite']) <= time()) ? 'text-danger' : 'text-warning'; ?> me-1"></i>
                    Vence
                </p>
                <?php
                $diasR = (int)ceil((strtotime($sol['fecha_limite']) - time()) / 86400);
                ?>
                <p class="fw-bold mb-0"><?php echo date('d/m/Y', strtotime($sol['fecha_limite'])); ?></p>
                <?php if ($diasR <= 0): ?>
                    <small class="text-danger fw-bold">Vence hoy</small>
                <?php elseif ($diasR === 1): ?>
                    <small class="text-danger">Vence mañana</small>
                <?php else: ?>
                    <small class="text-muted"><?php echo $diasR; ?> días restantes</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($sol['estado'] === 'rechazada' && $sol['observacion_respuesta']): ?>
<div class="alert alert-danger d-flex gap-2 align-items-start mb-4">
    <i class="bi bi-x-octagon-fill fs-5 flex-shrink-0 mt-1"></i>
    <div>
        <strong>Motivo de rechazo:</strong> <?php echo h($sol['observacion_respuesta']); ?>
        <?php if ($sol['respuesta_nombre']): ?>
            <br><small class="text-muted">Por <?php echo h($sol['respuesta_nombre']); ?> — <?php echo date('d/m/Y H:i', strtotime($sol['fecha_respuesta'])); ?></small>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($sol['estado'] === 'caducada'): ?>
    <div class="alert alert-secondary d-flex gap-2 align-items-start mb-4">
        <i class="bi bi-hourglass-bottom fs-5 flex-shrink-0 mt-1"></i>
        <div>
            <strong>Solicitud caducada.</strong>
            Venció el <?php echo $sol['fecha_limite'] ? date('d/m/Y', strtotime($sol['fecha_limite'])) : '—'; ?>
            sin ser aprobada. El solicitante deberá generar una nueva solicitud si aún requiere los productos.
        </div>
    </div>
<?php endif; ?>

<?php if ($puedeGestionar && $esPendiente): ?>
<form method="post" id="formRevision">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-list-check text-primary me-2"></i>
                Ítems solicitados
                <span class="badge bg-secondary ms-1"><?php echo count($items); ?></span>
            </h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="aprobarTodos()">
                    <i class="bi bi-check-all me-1"></i> Aprobar todos
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="rechazarTodos()">
                    <i class="bi bi-x-lg me-1"></i> Rechazar todos
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaItems">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th style="width:30px">#</th>
                        <th>Producto</th>
                        <th class="text-end">Solicitado</th>
                        <th class="text-end">Stock disp.</th>
                        <th style="width:130px" class="text-end">Cant. aprobada</th>
                        <th style="width:170px">Nota</th>
                        <th style="width:130px" class="text-center">Decisión</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $k => $itm):
                    $stock      = (float)$itm['stock_disponible'];
                    $solicit    = (float)$itm['cantidad'];
                    $cantApDef  = ($itm['cantidad_aprobada'] !== null)
                                  ? (float)$itm['cantidad_aprobada']
                                  : min($solicit, $stock);
                    $estadoDet  = $itm['estado'] ?: 'pendiente';
                    $motivoDet  = $itm['motivo_ajuste'] ?: '';

                    // Semáforo stock
                    if ($stock <= 0)               { $semClass = 'danger';  $semIcon = 'exclamation-triangle-fill'; }
                    elseif ($stock < $solicit)      { $semClass = 'warning'; $semIcon = 'exclamation-circle-fill'; }
                    else                            { $semClass = 'success'; $semIcon = 'check-circle-fill'; }
                ?>
                <tr class="item-row" data-idx="<?php echo $k; ?>" data-stock="<?php echo $stock; ?>" data-solicit="<?php echo $solicit; ?>">
                    <td class="text-muted small"><?php echo $k + 1; ?></td>
                    <td>
                        <input type="hidden" name="item_id[]" value="<?php echo (int)$itm['id']; ?>">
                        <span class="fw-semibold"><?php echo h($itm['producto_nombre']); ?></span>
                        <br><small class="text-muted"><?php echo h($itm['producto_codigo']); ?></small>
                    </td>
                    <td class="text-end fw-semibold"><?php echo number_format($solicit, 2, ',', '.'); ?></td>
                    <td class="text-end">
                        <span class="text-<?php echo $semClass; ?> fw-bold">
                            <i class="bi bi-<?php echo $semIcon; ?> me-1"></i><?php echo number_format($stock, 2, ',', '.'); ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <input type="number"
                               name="item_cant_ap[]"
                               class="form-control form-control-sm text-end item-cant-ap"
                               style="min-width:90px"
                               step="0.01" min="0.01"
                               max="<?php echo $stock; ?>"
                               value="<?php echo number_format($cantApDef, 2, '.', ''); ?>"
                               <?php echo ($estadoDet === 'rechazado') ? 'disabled' : ''; ?>>
                    </td>
                    <td>
                        <input type="text"
                               name="item_motivo[]"
                               class="form-control form-control-sm"
                               placeholder="Opcional"
                               value="<?php echo h($motivoDet); ?>"
                               maxlength="200">
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm decision-group" role="group">
                            <input type="radio"
                                   class="btn-check decision-radio"
                                   name="item_estado[]"
                                   id="est_ap_<?php echo $k; ?>"
                                   value="aprobado"
                                   <?php echo ($estadoDet === 'aprobado' || $estadoDet === 'pendiente') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-success" for="est_ap_<?php echo $k; ?>">
                                <i class="bi bi-check-lg"></i>
                            </label>

                            <input type="radio"
                                   class="btn-check decision-radio"
                                   name="item_estado[]"
                                   id="est_rec_<?php echo $k; ?>"
                                   value="rechazado"
                                   <?php echo ($estadoDet === 'rechazado') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-danger" for="est_rec_<?php echo $k; ?>">
                                <i class="bi bi-x-lg"></i>
                            </label>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top-0 pt-0">
            <div class="row g-2 mt-1">
                <div class="col-auto">
                    <span class="badge bg-success-subtle text-success border border-success-subtle p-2" id="kpiAprob">
                        <i class="bi bi-check-circle me-1"></i> <span id="kpiAprobN">0</span> aprobados
                    </span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-2" id="kpiRec">
                        <i class="bi bi-x-circle me-1"></i> <span id="kpiRecN">0</span> rechazados
                    </span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle p-2" id="kpiSinStock">
                        <i class="bi bi-exclamation-triangle me-1"></i> <span id="kpiSinStockN">0</span> sin stock suficiente
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-5">
        <button type="submit" name="action" value="guardar_revision" class="btn btn-outline-primary">
            <i class="bi bi-floppy me-1"></i> Guardar revisión
        </button>
        <button type="submit" name="action" value="ejecutar" class="btn btn-success px-4"
                id="btnEjecutar"
                onclick="return confirmarEjecucion()">
            <i class="bi bi-play-circle-fill me-1"></i> Ejecutar traslado
        </button>
    </div>

</form>

<?php else: /* Solicitud ya procesada / rechazada — solo lectura */ ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-list-check text-primary me-2"></i> Ítems de la solicitud
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th class="text-end">Solicitado</th>
                    <th class="text-end">Aprobado</th>
                    <th>Nota</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $k => $itm): ?>
            <tr>
                <td class="text-muted small"><?php echo $k + 1; ?></td>
                <td>
                    <span class="fw-semibold"><?php echo h($itm['producto_nombre']); ?></span>
                    <br><small class="text-muted"><?php echo h($itm['producto_codigo']); ?></small>
                </td>
                <td class="text-end"><?php echo number_format((float)$itm['cantidad'], 2, ',', '.'); ?></td>
                <td class="text-end fw-bold">
                    <?php echo ($itm['cantidad_aprobada'] !== null)
                        ? number_format((float)$itm['cantidad_aprobada'], 2, ',', '.')
                        : '—'; ?>
                </td>
                <td class="text-muted small"><?php echo h($itm['motivo_ajuste'] ?: '—'); ?></td>
                <td class="text-center"><?php echo badge_item($itm['estado']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php if ($logs): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-secondary">
            <i class="bi bi-journal-text me-2"></i> Historial de acciones
        </h6>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($logs as $lg):
            $iconMap = array(
                'ejecutada'        => 'bi-play-circle text-success',
                'rechazada'        => 'bi-x-circle text-danger',
                'revision_guardada'=> 'bi-pencil-square text-info',
            );
            $ico = isset($iconMap[$lg['accion']]) ? $iconMap[$lg['accion']] : 'bi-dot text-secondary';
        ?>
        <li class="list-group-item px-4 py-2 d-flex gap-3 align-items-start">
            <i class="bi <?php echo $ico; ?> mt-1 flex-shrink-0"></i>
            <div class="flex-grow-1">
                <span class="fw-semibold text-capitalize"><?php echo h(str_replace('_', ' ', $lg['accion'])); ?></span>
                <span class="text-muted ms-2 small"><?php echo h($lg['usuario_nombre']); ?></span>
                <br>
                <?php if ($lg['detalle']): ?>
                    <small class="text-muted"><?php echo h($lg['detalle']); ?></small>
                <?php endif; ?>
            </div>
            <small class="text-muted flex-shrink-0"><?php echo date('d/m/Y H:i', strtotime($lg['created_at'])); ?></small>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($puedeGestionar && $esPendiente): ?>
<div class="modal fade" id="modalRechazar" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-x-octagon me-2"></i>Rechazar solicitud
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Esta acción rechazará todos los ítems. El solicitante será notificado del motivo.</p>
                <label class="form-label fw-semibold">Motivo de rechazo <span class="text-danger">*</span></label>
                <textarea name="motivo_rechazo" class="form-control" rows="3"
                          placeholder="Ej: Stock insuficiente, fuera de período, etc." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="action" value="rechazar" class="btn btn-danger">
                    <i class="bi bi-x-circle me-1"></i> Confirmar rechazo
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    // Actualizar estado de fila al cambiar decisión
    document.querySelectorAll('.decision-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var row    = this.closest('tr.item-row');
            var cantIn = row.querySelector('.item-cant-ap');
            var stock  = parseFloat(row.dataset.stock);

            if (this.value === 'rechazado') {
                cantIn.disabled = true;
                row.classList.add('table-secondary');
                row.classList.remove('table-warning', 'table-danger');
            } else {
                cantIn.disabled = false;
                row.classList.remove('table-secondary');
                validarFila(row);
            }
            actualizarKPIs();
        });
    });

    // Validar cantidad aprobada en tiempo real
    document.querySelectorAll('.item-cant-ap').forEach(function (inp) {
        inp.addEventListener('input', function () {
            validarFila(this.closest('tr.item-row'));
        });
    });

    function validarFila(row) {
        var cantIn  = row.querySelector('.item-cant-ap');
        var stock   = parseFloat(row.dataset.stock);
        var val     = parseFloat(cantIn.value);

        row.classList.remove('table-danger', 'table-warning');
        cantIn.classList.remove('is-invalid', 'is-warning');

        if (isNaN(val) || val <= 0) {
            cantIn.classList.add('is-invalid');
            row.classList.add('table-danger');
        } else if (val > stock) {
            cantIn.classList.add('is-invalid');
            row.classList.add('table-danger');
        }
        actualizarKPIs();
    }

    function actualizarKPIs() {
        var rows      = document.querySelectorAll('tr.item-row');
        var nAprob    = 0;
        var nRec      = 0;
        var nSinStock = 0;

        rows.forEach(function (row) {
            var radio  = row.querySelector('.decision-radio[value="aprobado"]');
            var stock  = parseFloat(row.dataset.stock);
            var solict = parseFloat(row.dataset.solicit);

            if (radio && radio.checked) {
                nAprob++;
                if (stock < solict) nSinStock++;
            } else {
                nRec++;
            }
        });

        document.getElementById('kpiAprobN').textContent    = nAprob;
        document.getElementById('kpiRecN').textContent      = nRec;
        document.getElementById('kpiSinStockN').textContent = nSinStock;
    }

    function aprobarTodos() {
        document.querySelectorAll('.decision-radio[value="aprobado"]').forEach(function (r) {
            r.checked = true; r.dispatchEvent(new Event('change'));
        });
    }
    function rechazarTodos() {
        document.querySelectorAll('.decision-radio[value="rechazado"]').forEach(function (r) {
            r.checked = true; r.dispatchEvent(new Event('change'));
        });
    }

    window.aprobarTodos   = aprobarTodos;
    window.rechazarTodos  = rechazarTodos;

    window.confirmarEjecucion = function () {
        var nAprob = parseInt(document.getElementById('kpiAprobN').textContent, 10);
        var nRec   = parseInt(document.getElementById('kpiRecN').textContent, 10);

        // Verificar campos inválidos
        var invalidos = document.querySelectorAll('.item-cant-ap.is-invalid');
        if (invalidos.length > 0) {
            alert('Hay cantidades aprobadas que superan el stock disponible. Corrígelas antes de ejecutar.');
            return false;
        }
        if (nAprob === 0) {
            alert('No hay ítems aprobados. Usa "Rechazar Todo" si deseas rechazar la solicitud completa.');
            return false;
        }
        if (nRec > 0) {
            return confirm(nRec + ' ítem(s) serán rechazados. ¿Ejecutar traslado con ' + nAprob + ' ítem(s) aprobado(s)?');
        }
        return confirm('¿Ejecutar traslado con todos los ítems aprobados?');
    };

    // Inicializar KPIs y validaciones
    document.querySelectorAll('tr.item-row').forEach(function (row) {
        var stock  = parseFloat(row.dataset.stock);
        var solict = parseFloat(row.dataset.solicit);
        var radio  = row.querySelector('.decision-radio[value="rechazado"]');
        if (radio && radio.checked) {
            row.querySelector('.item-cant-ap').disabled = true;
            row.classList.add('table-secondary');
        }
        if (stock <= 0) row.classList.add('table-danger');
        else if (stock < solict) row.classList.add('table-warning');
    });
    actualizarKPIs();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>