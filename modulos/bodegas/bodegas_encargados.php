<?php
// modulos/bodegas/bodegas_encargados.php
// Gestión M:N de encargados de una bodega.
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/bodegas_helpers.php';

require_login();
require_role('admin');

$id_bodega = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM bodegas WHERE id = ? LIMIT 1");
$stmt->execute(array($id_bodega));
$bodega = $stmt->fetch();

if (!$bodega) {
    set_flash('error', 'Bodega no encontrada.');
    redirect('bodegas_lista.php');
}

// ============================================================
// ACCIONES
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string)post('accion');

    if ($accion === 'asignar') {
        $ids_usuarios = isset($_POST['ids_usuarios']) ? (array)$_POST['ids_usuarios'] : array();
        $n = 0;
        foreach ($ids_usuarios as $uid) {
            if (asignar_encargado_bodega((int)$uid, $id_bodega, false)) $n++;
        }
        if ($n > 0) set_flash('success', $n . ' encargado(s) asignado(s) a la bodega.');
        else        set_flash('error',   'No se seleccionó ningún usuario válido.');

    } elseif ($accion === 'quitar') {
        $uid = (int)post('id_usuario');
        if (desasignar_encargado_bodega($uid, $id_bodega)) {
            set_flash('success', 'Encargado desasignado.');
        }

    } elseif ($accion === 'principal') {
        $uid = (int)post('id_usuario');
        set_bodega_principal($uid, $id_bodega);
        set_flash('success', 'Bodega marcada como principal para ese encargado.');
    }

    redirect('bodegas_encargados.php?id=' . $id_bodega);
}

// ============================================================
// DATOS VISTA
// ============================================================
$encargados = encargados_de_bodega($id_bodega);
$idsAsignados = array();
foreach ($encargados as $e) $idsAsignados[] = (int)$e['id'];

// Usuarios disponibles para asignar (activos, no admin, no ya asignados)
$sqlDisp = "
    SELECT u.id, u.usuario, u.rol,
           COALESCE(f.nombre, u.nombre) AS nombre,
           f.rut, f.cargo, uo.nombre AS unidad_nombre,
           (SELECT COUNT(*) FROM usuarios_bodegas WHERE id_usuario = u.id)
               AS total_bodegas
    FROM   usuarios u
    LEFT   JOIN funcionarios f ON f.id = u.id_funcionario
    LEFT   JOIN unidades_organizacionales uo ON uo.id = u.id_unidad
    WHERE  u.estado = 1
      AND  u.rol <> 'admin'
";
$params = array();
if ($idsAsignados) {
    $ph = implode(',', array_fill(0, count($idsAsignados), '?'));
    $sqlDisp .= " AND u.id NOT IN ($ph)";
    $params = $idsAsignados;
}
$sqlDisp .= " ORDER BY nombre ASC";
$st = $pdo->prepare($sqlDisp);
$st->execute($params);
$disponibles = $st->fetchAll();

$pageTitle = 'Encargados — ' . $bodega['nombre'];
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb" class="small mb-1">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="bodegas_lista.php" class="text-decoration-none">Bodegas</a></li>
                <li class="breadcrumb-item"><a href="bodegas_ver.php?id=<?php echo (int)$bodega['id']; ?>" class="text-decoration-none"><?php echo h($bodega['nombre']); ?></a></li>
                <li class="breadcrumb-item active">Encargados</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">
            <i class="bi bi-people-fill text-primary me-2"></i>
            Encargados de <?php echo h($bodega['nombre']); ?>
            <span class="badge bg-light text-dark border ms-2" style="font-size:.6em;"><?php echo h($bodega['codigo']); ?></span>
        </h1>
        <p class="text-muted small mt-1 mb-0">
            Una bodega puede tener múltiples encargados. Uno puede marcarse como <strong>principal</strong>.
        </p>
    </div>
    <a href="bodegas_ver.php?id=<?php echo (int)$bodega['id']; ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
</div>

<div class="row g-3">
    <!-- Encargados actuales -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-person-check text-success me-2"></i>Encargados asignados
                </h5>
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo count($encargados); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$encargados): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                        Esta bodega aún no tiene encargados asignados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-secondary">
                                <tr>
                                    <th class="px-3">Nombre</th>
                                    <th>RUT / Cargo</th>
                                    <th class="text-center">Principal</th>
                                    <th class="px-3 text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($encargados as $e): ?>
                                <tr>
                                    <td class="px-3">
                                        <div class="fw-semibold"><?php echo h($e['nombre']); ?></div>
                                        <small class="text-muted">@<?php echo h($e['usuario']); ?></small>
                                    </td>
                                    <td class="small">
                                        <?php if ($e['rut']): ?>
                                            <div><?php echo h($e['rut']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($e['cargo']): ?>
                                            <small class="text-muted"><?php echo h($e['cargo']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$e['es_principal'] === 1): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle">
                                                <i class="bi bi-star-fill me-1"></i>Principal
                                            </span>
                                        <?php else: ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="accion" value="principal">
                                                <input type="hidden" name="id_usuario" value="<?php echo (int)$e['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Marcar como principal">
                                                    <i class="bi bi-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 text-end">
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('¿Quitar a <?php echo h(addslashes($e['nombre'])); ?> como encargado de esta bodega?');">
                                            <input type="hidden" name="accion" value="quitar">
                                            <input type="hidden" name="id_usuario" value="<?php echo (int)$e['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Quitar">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Asignar nuevos -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-person-plus text-primary me-2"></i>Asignar encargados
                </h5>
            </div>
            <div class="card-body p-3">
                <?php if (!$disponibles): ?>
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                        Todos los usuarios disponibles ya son encargados.
                    </div>
                <?php else: ?>
                <form method="post">
                    <input type="hidden" name="accion" value="asignar">

                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="filtroUsuario" class="form-control" placeholder="Filtrar por nombre, RUT, unidad...">
                    </div>

                    <div class="border rounded" style="max-height:360px; overflow-y:auto;">
                        <div class="list-group list-group-flush" id="listaDisp">
                            <?php foreach ($disponibles as $d):
                                $srch = strtolower(trim(
                                    ($d['nombre'] ? $d['nombre'] : '') . ' ' .
                                    ($d['rut']    ? $d['rut']    : '') . ' ' .
                                    ($d['cargo']  ? $d['cargo']  : '') . ' ' .
                                    ($d['unidad_nombre'] ? $d['unidad_nombre'] : '')
                                ));
                            ?>
                                <label class="list-group-item list-group-item-action item-disp"
                                       data-search="<?php echo h($srch); ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <input type="checkbox" class="form-check-input mt-1" name="ids_usuarios[]" value="<?php echo (int)$d['id']; ?>">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold small text-dark"><?php echo h($d['nombre']); ?></div>
                                            <div class="text-muted" style="font-size:.7rem;">
                                                <?php echo h($d['rut'] ? $d['rut'] : $d['usuario']); ?>
                                                <?php if ($d['cargo']): ?> · <?php echo h($d['cargo']); ?><?php endif; ?>
                                                <?php if ($d['unidad_nombre']): ?> · <?php echo h($d['unidad_nombre']); ?><?php endif; ?>
                                            </div>
                                            <?php if ((int)$d['total_bodegas'] > 0): ?>
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle mt-1" style="font-size:.6rem;">
                                                    Ya encargado de <?php echo (int)$d['total_bodegas']; ?> bodega(s)
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($d['rol'] === 'solicitante'): ?>
                                                <span class="badge bg-light text-muted border mt-1" style="font-size:.6rem;">
                                                    Rol actual: Solicitante → será promovido
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i> Asignar seleccionados
                        </button>
                    </div>

                    <p class="text-muted small mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Los usuarios con rol <em>Solicitante</em> serán promovidos automáticamente a <em>Encargado</em>.
                    </p>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var inp = document.getElementById('filtroUsuario');
    if (!inp) return;
    inp.addEventListener('input', function(){
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('.item-disp').forEach(function(el){
            var t = el.getAttribute('data-search') || '';
            el.style.display = (q === '' || t.indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>