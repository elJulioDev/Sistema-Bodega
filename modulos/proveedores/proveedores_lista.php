<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

// activar / desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE proveedores SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado del proveedor actualizado.');
    redirect('proveedores_lista.php');
}

$buscar = get('buscar');

$sql = "SELECT * FROM proveedores WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        rut LIKE :buscar
        OR razon_social LIKE :buscar
        OR nombre_fantasia LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proveedores = $stmt->fetchAll();

$totalActivos   = 0;
$totalInactivos = 0;
foreach ($proveedores as $p) {
    if ((int)$p['estado'] === 1) $totalActivos++; else $totalInactivos++;
}

$pageTitle = 'Proveedores';
require_once __DIR__ . '/../../inc/header.php';
?>

<style>
/* ══ CARDS — móvil ════════════════════════════════════════ */
.prov-cards { display:none; flex-direction:column; gap:.7rem; }

.prov-card {
    background:#fff; border:1px solid #e9ecef; border-radius:12px;
    overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.05);
}
.prov-card-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:.7rem 1rem; background:#f8f9ff;
    border-bottom:1px solid #e9ecef; gap:.5rem;
}
.prov-card-head-l { display:flex; align-items:center; gap:.5rem; min-width:0; flex:1; }
.prov-card-razon {
    font-size:.88rem; font-weight:700; color:#1a1f36;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.prov-card-body {
    padding:.7rem 1rem; display:grid;
    grid-template-columns:1fr 1fr; gap:.45rem .75rem;
}
.prov-cf { display:flex; flex-direction:column; gap:2px; }
.prov-cf-l {
    font-size:.67rem; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; color:#9ca3af;
}
.prov-cf-v { font-size:.82rem; color:#374151; }
.prov-card-foot {
    display:flex; align-items:center; justify-content:space-between;
    padding:.55rem 1rem; border-top:1px solid #f0f2f5;
}
.prov-av {
    width:34px; height:34px; border-radius:8px; flex-shrink:0;
    background:linear-gradient(135deg,#e7f0ff,#c8dcff);
    color:#0d6efd; font-weight:700; font-size:.75rem;
    display:inline-flex; align-items:center; justify-content:center;
}

/* ══ RESPONSIVE ════════════════════════════════════════════ */
@media (max-width: 767.98px) {
    .prov-tbl-wrap { display:none; }
    .prov-cards    { display:flex; }
}
@media (max-width: 479.98px) {
    .prov-card-body { grid-template-columns:1fr; }
}
</style>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-truck text-primary me-2"></i>Proveedores
        </h1>
        <small class="text-muted">Gestiona el directorio de proveedores registrados</small>
    </div>
    <a href="proveedores_crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Proveedor
    </a>
</div>

<!-- KPIs -->
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Total</div>
                <div class="h4 mb-0 fw-bold"><?php echo count($proveedores); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Activos</div>
                <div class="h4 mb-0 fw-bold text-success"><?php echo $totalActivos; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 border-start border-4 border-danger">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Inactivos</div>
                <div class="h4 mb-0 fw-bold text-danger"><?php echo $totalInactivos; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Buscador -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2 px-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-secondary">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="buscar"
                           value="<?php echo h($buscar); ?>"
                           class="form-control border-start-0 ps-1"
                           placeholder="Buscar por RUT, Razón Social o Nombre Fantasía…">
                    <?php if ($buscar !== ''): ?>
                    <button type="button" class="btn btn-light border"
                            onclick="window.location='proveedores_lista.php'" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">
                    <i class="bi bi-search me-1"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$proveedores): ?>

<div class="card shadow-sm border-0">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-truck display-4 d-block mb-2 opacity-25"></i>
        <p class="fw-semibold text-dark mb-1">
            <?php echo $buscar !== '' ? 'Sin resultados' : 'Sin proveedores registrados'; ?>
        </p>
        <p class="small mb-0">
            <?php if ($buscar !== ''): ?>
                No hay coincidencias para "<strong><?php echo h($buscar); ?></strong>".
                <a href="proveedores_lista.php">Limpiar búsqueda</a>
            <?php else: ?>
                <a href="proveedores_crear.php">Agregar el primer proveedor</a>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php else: ?>

<!-- ══ TABLA (≥ 768px) ═══════════════════════════════════════ -->
<div class="prov-tbl-wrap card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size:.78rem; letter-spacing:.03em;">
                    <tr>
                        <th class="px-4 py-3">RUT</th>
                        <th class="py-3">RAZÓN SOCIAL</th>
                        <th class="py-3">FANTASÍA</th>
                        <th class="py-3">COMUNA</th>
                        <th class="py-3">CONTACTO</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($proveedores as $p):
                    $on = (int)$p['estado'] === 1;
                ?>
                <tr>
                    <td class="px-4">
                        <span class="badge bg-light text-dark border font-monospace"><?php echo h($p['rut']); ?></span>
                    </td>
                    <td class="fw-semibold text-dark"><?php echo h($p['razon_social']); ?></td>
                    <td class="text-muted">
                        <?php echo $p['nombre_fantasia'] !== '' ? h($p['nombre_fantasia']) : '—'; ?>
                    </td>
                    <td class="text-muted">
                        <?php echo $p['comuna'] !== '' ? h($p['comuna']) : '—'; ?>
                    </td>
                    <td>
                        <?php if ($p['telefono'] !== ''): ?>
                            <div class="small text-secondary"><i class="bi bi-telephone me-1"></i><?php echo h($p['telefono']); ?></div>
                        <?php endif; ?>
                        <?php if ($p['email'] !== ''): ?>
                            <div class="small text-secondary"><i class="bi bi-envelope me-1"></i><?php echo h($p['email']); ?></div>
                        <?php endif; ?>
                        <?php if ($p['telefono'] === '' && $p['email'] === ''): ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($on): ?>
                            <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 border-0">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 border-0">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 text-end">
                        <div class="btn-group" role="group">
                            <a href="proveedores_editar.php?id=<?php echo (int)$p['id']; ?>"
                               class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="#"
                               class="btn btn-sm btn-outline-<?php echo $on ? 'danger' : 'success'; ?>"
                               title="<?php echo $on ? 'Desactivar' : 'Activar'; ?>"
                               data-bs-toggle="modal" data-bs-target="#modalToggle"
                               data-id="<?php echo (int)$p['id']; ?>"
                               data-nombre="<?php echo h($p['razon_social']); ?>"
                               data-activo="<?php echo $on ? '1' : '0'; ?>">
                                <i class="bi bi-<?php echo $on ? 'slash-circle' : 'check-circle'; ?>"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══ CARDS (< 768px) ══════════════════════════════════════ -->
<div class="prov-cards">
<?php foreach ($proveedores as $p): ?>
<?php
    $pals = preg_split('/\s+/', trim($p['razon_social']));
    $ini  = strtoupper(substr($pals[0], 0, 1) . (isset($pals[1]) ? substr($pals[1], 0, 1) : ''));
    $on   = (int)$p['estado'] === 1;
    $ub   = array_filter(array($p['comuna'], $p['ciudad']));
?>
<div class="prov-card">

    <div class="prov-card-head">
        <div class="prov-card-head-l">
            <span class="prov-av"><?php echo $ini; ?></span>
            <span class="prov-card-razon"><?php echo h($p['razon_social']); ?></span>
        </div>
        <?php if ($on): ?>
            <span class="badge bg-success bg-opacity-10 text-success border-0">Activo</span>
        <?php else: ?>
            <span class="badge bg-danger bg-opacity-10 text-danger border-0">Inactivo</span>
        <?php endif; ?>
    </div>

    <div class="prov-card-body">

        <div class="prov-cf">
            <span class="prov-cf-l">RUT</span>
            <span class="prov-cf-v">
                <span class="badge bg-light text-dark border font-monospace"><?php echo h($p['rut']); ?></span>
            </span>
        </div>

        <?php if ($p['nombre_fantasia'] !== ''): ?>
        <div class="prov-cf">
            <span class="prov-cf-l">Fantasía</span>
            <span class="prov-cf-v"><?php echo h($p['nombre_fantasia']); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($p['giro'] !== ''): ?>
        <div class="prov-cf" style="grid-column:1/-1;">
            <span class="prov-cf-l">Giro</span>
            <span class="prov-cf-v"><?php echo h($p['giro']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($ub)): ?>
        <div class="prov-cf">
            <span class="prov-cf-l">Ubicación</span>
            <span class="prov-cf-v"><?php echo h(implode(', ', $ub)); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($p['telefono'] !== ''): ?>
        <div class="prov-cf">
            <span class="prov-cf-l">Teléfono</span>
            <span class="prov-cf-v"><i class="bi bi-telephone me-1 text-muted"></i><?php echo h($p['telefono']); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($p['email'] !== ''): ?>
        <div class="prov-cf" style="grid-column:1/-1;">
            <span class="prov-cf-l">Email</span>
            <span class="prov-cf-v"><i class="bi bi-envelope me-1 text-muted"></i><?php echo h($p['email']); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($p['contacto'] !== ''): ?>
        <div class="prov-cf">
            <span class="prov-cf-l">Persona de contacto</span>
            <span class="prov-cf-v"><i class="bi bi-person me-1 text-muted"></i><?php echo h($p['contacto']); ?></span>
        </div>
        <?php endif; ?>

    </div>

    <div class="prov-card-foot">
        <span class="text-muted" style="font-size:.71rem;">ID #<?php echo (int)$p['id']; ?></span>
        <div class="d-flex gap-2">
            <a href="proveedores_editar.php?id=<?php echo (int)$p['id']; ?>"
               class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="#"
               class="btn btn-sm btn-outline-<?php echo $on ? 'danger' : 'success'; ?> d-flex align-items-center gap-1"
               data-bs-toggle="modal" data-bs-target="#modalToggle"
               data-id="<?php echo (int)$p['id']; ?>"
               data-nombre="<?php echo h($p['razon_social']); ?>"
               data-activo="<?php echo $on ? '1' : '0'; ?>">
                <i class="bi bi-<?php echo $on ? 'slash-circle' : 'check-circle'; ?>"></i>
                <?php echo $on ? 'Desactivar' : 'Activar'; ?>
            </a>
        </div>
    </div>

</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Modal confirmar toggle -->
<div class="modal fade" id="modalToggle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-1" id="modalToggleHeader">
                <h6 class="modal-title fw-bold" id="modalToggleTitle">Confirmar acción</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <p class="mb-0 small" id="modalToggleMsg"></p>
            </div>
            <div class="modal-footer border-0 pt-1 gap-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="modalToggleBtn" class="btn btn-sm btn-danger">Confirmar</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalToggle');
    modal.addEventListener('show.bs.modal', function (e) {
        var t      = e.relatedTarget;
        var id     = t.getAttribute('data-id');
        var nombre = t.getAttribute('data-nombre');
        var on     = t.getAttribute('data-activo') === '1';
        var head   = document.getElementById('modalToggleHeader');
        var title  = document.getElementById('modalToggleTitle');
        var msg    = document.getElementById('modalToggleMsg');
        var btn    = document.getElementById('modalToggleBtn');

        if (on) {
            head.style.background  = 'linear-gradient(135deg,#fff5f5,#ffecec)';
            title.textContent      = 'Desactivar proveedor';
            msg.innerHTML          = '¿Deseas <strong>desactivar</strong> a <em>' + nombre + '</em>?';
            btn.className          = 'btn btn-sm btn-danger';
            btn.textContent        = 'Sí, desactivar';
        } else {
            head.style.background  = 'linear-gradient(135deg,#f0fdf4,#dcfce7)';
            title.textContent      = 'Activar proveedor';
            msg.innerHTML          = '¿Deseas <strong>activar</strong> nuevamente a <em>' + nombre + '</em>?';
            btn.className          = 'btn btn-sm btn-success';
            btn.textContent        = 'Sí, activar';
        }

        btn.href = 'proveedores_lista.php?toggle=' + id;
    });
});
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>