<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

// ============================================================
// TOGGLE ESTADO
// ============================================================
if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT id, estado FROM unidades_organizacionales WHERE id = ? LIMIT 1");
    $stmt->execute(array($tid));
    $row = $stmt->fetch();
    if ($row) {
        $nuevo = ((int)$row['estado'] === 1) ? 0 : 1;
        $pdo->prepare("UPDATE unidades_organizacionales SET estado = ? WHERE id = ?")
            ->execute(array($nuevo, $tid));
        set_flash('success', 'Estado actualizado correctamente.');
    }
    redirect('unidades_lista.php');
}

// ============================================================
// FILTROS Y PAGINACION
// ============================================================
$buscar   = trim((string)get('buscar'));
$estado   = get('estado', '');
$perPage  = 50;
$pagina   = max(1, (int)get('pagina', 1));
$offset   = ($pagina - 1) * $perPage;

$where  = array('1=1');
$params = array();

if ($buscar !== '') {
    $where[] = "(uo.codigo LIKE :buscar OR uo.nombre LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
}
if ($estado !== '') {
    $where[] = "uo.estado = :estado";
    $params[':estado'] = (int)$estado;
}

$whereSql = implode(' AND ', $where);

// Total
$sqlCount = "SELECT COUNT(*) FROM unidades_organizacionales uo WHERE $whereSql";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / $perPage));

// Listado con conteo de funcionarios
$sql = "
    SELECT uo.*,
           COUNT(f.id) AS total_funcionarios,
           SUM(CASE WHEN f.estado = 1 THEN 1 ELSE 0 END) AS funcionarios_activos
    FROM unidades_organizacionales uo
    LEFT JOIN funcionarios f ON f.id_unidad = uo.id
    WHERE $whereSql
    GROUP BY uo.id
    ORDER BY uo.nombre ASC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$unidades = $stmt->fetchAll();

// KPIs
$kpis = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS activas,
        SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) AS inactivas
    FROM unidades_organizacionales
")->fetch();

$pageTitle = 'Unidades Organizacionales';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-diagram-3 text-primary me-2"></i>Unidades Organizacionales
        </h1>
        <p class="text-muted small mb-0 mt-1">Departamentos y direcciones de la municipalidad.</p>
    </div>
    <a href="unidades_crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nueva Unidad
    </a>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Total</p>
                <h3 class="mb-0 fw-bold text-dark"><?php echo (int)$kpis['total']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Activas</p>
                <h3 class="mb-0 fw-bold text-success"><?php echo (int)$kpis['activas']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card shadow-sm border-0 border-start border-secondary border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Inactivas</p>
                <h3 class="mb-0 fw-bold text-secondary"><?php echo (int)$kpis['inactivas']; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2 px-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>"
                           class="form-control border-start-0 ps-0"
                           placeholder="Buscar por código o nombre...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <option value="1" <?php echo ($estado === '1') ? 'selected' : ''; ?>>Activas</option>
                    <option value="0" <?php echo ($estado === '0') ? 'selected' : ''; ?>>Inactivas</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <?php if ($buscar !== '' || $estado !== ''): ?>
                    <a href="unidades_lista.php" class="btn btn-sm btn-light border" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABLA -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.9rem;">
                <thead class="table-light text-secondary" style="font-size:0.75rem;">
                    <tr>
                        <th class="px-3 py-2">CÓDIGO</th>
                        <th class="py-2">NOMBRE</th>
                        <th class="py-2 text-center">FUNCIONARIOS</th>
                        <th class="py-2 text-center">ESTADO</th>
                        <th class="py-2 text-muted">CREADA</th>
                        <th class="px-3 py-2 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$unidades): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No se encontraron unidades con los filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($unidades as $u):
                        $activa = ((int)$u['estado'] === 1);
                    ?>
                    <tr>
                        <td class="px-3">
                            <span class="badge bg-light text-dark border font-monospace">
                                <?php echo h($u['codigo']); ?>
                            </span>
                        </td>
                        <td class="fw-semibold text-dark"><?php echo h($u['nombre']); ?></td>
                        <td class="text-center">
                            <?php if ((int)$u['total_funcionarios'] > 0): ?>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">
                                    <?php echo (int)$u['funcionarios_activos']; ?> / <?php echo (int)$u['total_funcionarios']; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($activa): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle" style="font-size:.7rem;">
                                    ACTIVA
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.7rem;">
                                    INACTIVA
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo date('d-m-Y', strtotime($u['created_at'])); ?>
                        </td>
                        <td class="px-3 text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="unidades_editar.php?id=<?php echo (int)$u['id']; ?>"
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?toggle=<?php echo (int)$u['id']; ?>"
                                   class="btn btn-outline-<?php echo $activa ? 'warning' : 'success'; ?>"
                                   title="<?php echo $activa ? 'Desactivar' : 'Activar'; ?>"
                                   onclick="return confirm('<?php echo $activa ? '¿Desactivar esta unidad?' : '¿Activar esta unidad?'; ?>')">
                                    <i class="bi bi-<?php echo $activa ? 'pause-circle' : 'play-circle'; ?>"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div class="px-3 py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Mostrando <?php echo ($offset + 1); ?>–<?php echo min($offset + $perPage, $total); ?> de <?php echo $total; ?> unidades
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <li class="page-item <?php echo ($p === $pagina) ? 'active' : ''; ?>">
                            <a class="page-link"
                               href="?pagina=<?php echo $p; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo urlencode($estado); ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>