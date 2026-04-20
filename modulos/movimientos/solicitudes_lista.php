<?php
/**
 * solicitudes_lista.php
 * Lista de solicitudes con botón "Revisar" → solicitudes_ver.php
 */
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega', 'solicitante'));
// Caducar solicitudes vencidas (sin cron, se ejecuta en cada carga)
caducar_solicitudes_vencidas($pdo);

$user     = current_user();
$miBodega = user_bodega_id();
$miUid    = (int)$user['id'];

// ── Helper permiso ──────────────────────────────────────────
function puede_procesar($sol) {
    if (is_admin()) return true;
    if (is_encargado()) {
        return ((int)$sol['id_bodega_origen'] === (int)user_bodega_id());
    }
    return false;
}

// ============================================================
// ACCIÓN: RECHAZAR (desde modal de lista, sin entrar a ver.php)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rechazar') {
    $id_sol = (int)post('id_sol');
    $motivo = post('motivo_rechazo');

    $stmtSol = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? AND estado IN ('pendiente','en_revision') LIMIT 1");
    $stmtSol->execute(array($id_sol));
    $sol = $stmtSol->fetch();

    if (!$sol) {
        set_flash('error', 'Solicitud no encontrada o ya procesada.');
    } elseif (!puede_procesar($sol)) {
        set_flash('error', 'No tienes permisos para rechazar esta solicitud.');
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE solicitudes
                SET    estado='rechazada', observacion_respuesta=?, id_usuario_respuesta=?, fecha_respuesta=NOW()
                WHERE  id=? AND estado IN ('pendiente','en_revision')
            ")->execute(array($motivo, $miUid, $id_sol));

            $pdo->prepare("UPDATE solicitudes_detalle SET estado='rechazado' WHERE id_solicitud=?")
                ->execute(array($id_sol));

            // Log
            $pdo->prepare("INSERT INTO solicitudes_log (id_solicitud, id_usuario, accion, detalle) VALUES (?,?,?,?)")
                ->execute(array($id_sol, $miUid, 'rechazada', $motivo));

            $pdo->commit();
            set_flash('success', 'Solicitud rechazada.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
    redirect('solicitudes_lista.php');
}

// ============================================================
// VISTA Y FILTROS
// ============================================================
$filtroEstado = get('estado', '');
$vista        = get('vista', '');

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
    FROM   solicitudes s
    LEFT   JOIN usuarios u  ON u.id  = s.id_usuario
    LEFT   JOIN bodegas  bo ON bo.id = s.id_bodega_origen
    LEFT   JOIN bodegas  bd ON bd.id = s.id_bodega_destino
    WHERE  1=1
";

$where  = '';
$params = array();

if (is_admin()) {
    // sin filtro
} elseif (is_encargado()) {
    if ($vista === 'enviadas') {
        $where .= " AND s.id_usuario = :uid";
        $params[':uid'] = $miUid;
    } else {
        $where .= " AND s.id_bodega_origen = :bod";
        $params[':bod'] = $miBodega;
    }
} else {
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

// Contadores tabs
$countRecibidas = 0; $countEnviadas = 0; $countRecibidasPend = 0;
if (is_encargado()) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE id_bodega_origen = ?");
    $st->execute(array($miBodega));
    $countRecibidas = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE id_bodega_origen = ? AND estado IN ('pendiente','en_revision')");
    $st->execute(array($miBodega));
    $countRecibidasPend = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE id_usuario = ?");
    $st->execute(array($miUid));
    $countEnviadas = (int)$st->fetchColumn();
}

// Ítems para modal detalle
$allItems = array();
if ($solicitudes) {
    $ids = array();
    foreach ($solicitudes as $s) $ids[] = (int)$s['id'];
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtAll = $pdo->prepare("
            SELECT sd.id_solicitud, p.codigo, p.nombre,
                   sd.cantidad, sd.cantidad_aprobada, sd.estado AS det_estado, sd.observacion
            FROM   solicitudes_detalle sd
            INNER  JOIN productos p ON p.id = sd.id_producto
            WHERE  sd.id_solicitud IN ($placeholders)
            ORDER  BY sd.id_solicitud, sd.id
        ");
        $stmtAll->execute($ids);
        foreach ($stmtAll->fetchAll() as $row) {
            $sid = $row['id_solicitud'];
            if (!isset($allItems[$sid])) $allItems[$sid] = array();
            $allItems[$sid][] = array(
                'codigo'            => $row['codigo'],
                'nombre'            => $row['nombre'],
                'cantidad'          => number_format((float)$row['cantidad'], 2, ',', '.'),
                'cantidad_aprobada' => ($row['cantidad_aprobada'] !== null)
                                       ? number_format((float)$row['cantidad_aprobada'], 2, ',', '.') : null,
                'det_estado'        => $row['det_estado'],
                'observacion'       => $row['observacion'],
            );
        }
    }
}

$pageTitle = 'Solicitudes de Traslado';
require_once __DIR__ . '/../../inc/header.php';

// ── Badge estado solicitud ──────────────────────────────────
function badge_estado($estado) {
    switch ($estado) {
        case 'pendiente':
            return '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente</span>';
        case 'en_revision':
            return '<span class="badge bg-info text-dark"><i class="bi bi-search me-1"></i>En revisión</span>';
        case 'procesada':
            return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Ejecutada</span>';
        case 'procesada_parcial':
            return '<span class="badge text-white" style="background:#0d9488"><i class="bi bi-check2-all me-1"></i>Parcial</span>';
        case 'rechazada':
            return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rechazada</span>';
        case 'caducada':
            return '<span class="badge bg-secondary"><i class="bi bi-hourglass-bottom me-1"></i>Caducada</span>';
        default:
            return '<span class="badge bg-secondary">' . h($estado) . '</span>';
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-clipboard-check text-primary me-2"></i>
            <?php
            if (is_encargado())       echo ($vista === 'enviadas') ? 'Mis Solicitudes Enviadas' : 'Solicitudes Recibidas';
            elseif (is_solicitante()) echo 'Mis Solicitudes';
            else                      echo 'Solicitudes de Traslado';
            ?>
        </h1>
        <?php if (is_encargado()): ?>
        <p class="text-muted mb-0 small mt-1">
            <?php if ($vista === 'enviadas'): ?>
                Solicitudes que tú enviaste a otras bodegas.
            <?php else: ?>
                Solicitudes recibidas. Usa <strong>Revisar</strong> para validar stock y aprobar.
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
    <a href="solicitudes_crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nueva Solicitud
    </a>
</div>

<!-- Tabs encargado -->
<?php if (is_encargado()): ?>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo ($vista !== 'enviadas') ? 'active fw-bold' : ''; ?>" href="?vista=recibidas">
            <i class="bi bi-inbox me-1"></i> Recibidas
            <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1"><?php echo $countRecibidas; ?></span>
            <?php if ($countRecibidasPend > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?php echo $countRecibidasPend; ?> pend.</span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($vista === 'enviadas') ? 'active fw-bold' : ''; ?>" href="?vista=enviadas">
            <i class="bi bi-send me-1"></i> Enviadas
            <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1"><?php echo $countEnviadas; ?></span>
        </a>
    </li>
</ul>
<?php endif; ?>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="fw-bold text-secondary small">Filtrar:</span>
            <?php
            $estados = array(
                ''                  => 'Todas',
                'pendiente'         => 'Pendientes',
                'en_revision'       => 'En revisión',
                'procesada'         => 'Ejecutadas',
                'procesada_parcial' => 'Parciales',
                'rechazada'         => 'Rechazadas',
                'caducada'          => 'Caducadas',
            );
            foreach ($estados as $val => $lbl):
                $url = '?' . (is_encargado() ? 'vista=' . urlencode($vista) . '&' : '') . 'estado=' . urlencode($val);
            ?>
                <a href="<?php echo h($url); ?>"
                   class="btn btn-sm <?php echo ($filtroEstado === $val) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    <?php echo $lbl; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th>N° Solicitud</th>
                    <th class="d-none d-md-table-cell">Origen</th>
                    <th class="d-none d-md-table-cell">Destino</th>
                    <th class="d-none d-sm-table-cell">Solicitante</th>
                    <th>Estado</th>
                    <th class="d-none d-lg-table-cell">Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$solicitudes): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No hay solicitudes que coincidan con el filtro.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($solicitudes as $s):
                    $esPend = in_array($s['estado'], array('pendiente', 'en_revision'));
                ?>
                <tr>
                    <td>
                        <a href="solicitudes_ver.php?id=<?php echo (int)$s['id']; ?>"
                           class="fw-semibold text-decoration-none">
                            <?php echo h($s['numero_solicitud']); ?>
                        </a>
                        <?php if (isset($allItems[$s['id']])): ?>
                        <br>
                        <small class="text-muted">
                            <?php echo count($allItems[$s['id']]); ?> ítem(s)
                        </small>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?php echo h($s['origen_nombre'] ?: '—'); ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?php echo h($s['destino_nombre']); ?>
                    </td>
                    <td class="d-none d-sm-table-cell small">
                        <?php echo h($s['usuario_nombre']); ?>
                    </td>
                    <td>
                        <?php echo badge_estado($s['estado']); ?>
                        <?php if (in_array($s['estado'], array('pendiente','en_revision')) && $s['fecha_limite']): ?>
                            <?php
                            $diasRestantes = (int)ceil((strtotime($s['fecha_limite']) - time()) / 86400);
                            if ($diasRestantes <= 0):
                            ?>
                                <br><small class="text-danger"><i class="bi bi-alarm me-1"></i>Vence hoy</small>
                            <?php elseif ($diasRestantes <= 2): ?>
                                <br><small class="text-danger"><i class="bi bi-alarm me-1"></i>Vence en <?php echo $diasRestantes; ?> día(s)</small>
                            <?php elseif ($diasRestantes <= 4): ?>
                                <br><small class="text-warning"><i class="bi bi-alarm me-1"></i><?php echo $diasRestantes; ?> días restantes</small>
                            <?php else: ?>
                                <br><small class="text-muted"><i class="bi bi-alarm me-1"></i><?php echo $diasRestantes; ?> días restantes</small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?php echo date('d/m/Y', strtotime($s['created_at'])); ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                            <!-- Ver detalle siempre -->
                            <a href="solicitudes_ver.php?id=<?php echo (int)$s['id']; ?>"
                               class="btn btn-sm btn-outline-secondary"
                               title="Ver detalle">
                                <i class="bi bi-eye"></i>
                                <span class="d-none d-md-inline ms-1">Ver</span>
                            </a>

                            <?php if ($esPend && puede_procesar($s)): ?>
                                <!-- Botón REVISAR → revisar stock, editar ítems, ejecutar -->
                                <a href="solicitudes_ver.php?id=<?php echo (int)$s['id']; ?>"
                                   class="btn btn-sm btn-primary"
                                   title="Revisar y aprobar">
                                    <i class="bi bi-search me-1"></i>Revisar
                                </a>
                                <!-- Rechazo rápido desde lista -->
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger btn-rechazar"
                                        data-id="<?php echo (int)$s['id']; ?>"
                                        data-numero="<?php echo h($s['numero_solicitud']); ?>"
                                        title="Rechazar">
                                    <i class="bi bi-x-lg"></i>
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

<!-- Modal: Rechazar rápido -->
<?php if (is_admin() || is_encargado()): ?>
<div class="modal fade" id="modalRechazar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action"  value="rechazar">
                <input type="hidden" name="id_sol"  id="rechazarIdSol" value="">
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
                    <label class="form-label fw-semibold">Motivo del rechazo <span class="text-danger">*</span></label>
                    <textarea name="motivo_rechazo" class="form-control" rows="3"
                              placeholder="Explica al solicitante el motivo..."
                              required></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i> Confirmar Rechazo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    // Abrir modal rechazar
    document.querySelectorAll('.btn-rechazar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('rechazarIdSol').value = this.getAttribute('data-id');
            document.getElementById('rechazarNumero').textContent = this.getAttribute('data-numero');
            var modal = new bootstrap.Modal(document.getElementById('modalRechazar'));
            modal.show();
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>