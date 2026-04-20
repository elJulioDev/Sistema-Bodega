<?php
// modulos/funcionarios/funcionarios_lista.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

// Toggle estado
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE funcionarios SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado del funcionario actualizado.');
    redirect('funcionarios_lista.php');
}

// Eliminar
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];

    // Bloquear si tiene usuario ligado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_funcionario = ?");
    $stmt->execute(array($id));
    if ((int)$stmt->fetchColumn() > 0) {
        set_flash('error', 'No se puede eliminar: existe un usuario ligado a este funcionario.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id = ?");
        $stmt->execute(array($id));
        set_flash('success', 'Funcionario eliminado.');
    }
    redirect('funcionarios_lista.php');
}

// Filtros
$q        = trim((string)get('q'));
$id_unidad = (int)get('id_unidad');
$estado   = get('estado', '');
$pagina   = max(1, (int)get('p'));
$perPage  = 50;
$offset   = ($pagina - 1) * $perPage;

$where  = array("1=1");
$params = array();

if ($q !== '') {
    $where[] = "(f.nombre LIKE :q OR f.rut LIKE :q OR f.codigo LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($id_unidad > 0) {
    $where[] = "f.id_unidad = :uid";
    $params[':uid'] = $id_unidad;
}
if ($estado !== '') {
    $where[] = "f.estado = :est";
    $params[':est'] = (int)$estado;
}

$whereSql = implode(' AND ', $where);

// Total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM funcionarios f WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / $perPage));

// Listado
$sql = "SELECT f.*, u.nombre AS unidad_nombre
        FROM funcionarios f
        LEFT JOIN unidades_organizacionales u ON u.id = f.id_unidad
        WHERE $whereSql
        ORDER BY f.nombre ASC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$pageTitle = 'Funcionarios';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-person-badge text-primary me-2"></i>Funcionarios Municipales
    </h1>
    <div class="d-flex gap-2">
        <a href="funcionarios_importar.php" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-arrow-up me-1"></i> Importar CSV
        </a>
        <a href="funcionarios_crear.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Nuevo Funcionario
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">Buscar</label>
                <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control form-control-sm" placeholder="Nombre, RUT o código">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">Unidad</label>
                <select name="id_unidad" class="form-select form-select-sm">
                    <option value="">Todas las unidades</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad === (int)$u['id']) ? 'selected' : ''; ?>>
                            <?php echo h($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="1" <?php echo ($estado === '1') ? 'selected' : ''; ?>>Activos</option>
                    <option value="0" <?php echo ($estado === '0') ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="funcionarios_lista.php" class="btn btn-sm btn-outline-secondary" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Listado -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.80rem;">
                    <tr>
                        <th class="px-3 py-2">CÓDIGO</th>
                        <th class="py-2">RUT</th>
                        <th class="py-2">NOMBRE</th>
                        <th class="py-2">UNIDAD</th>
                        <th class="py-2">CARGO</th>
                        <th class="py-2 text-center">ESTADO</th>
                        <th class="px-3 py-2 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$funcionarios): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                            No se encontraron funcionarios.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($funcionarios as $f): ?>
                    <tr>
                        <td class="px-3 small text-muted"><?php echo h($f['codigo']); ?></td>
                        <td class="small fw-medium"><?php echo h($f['rut']); ?></td>
                        <td class="small"><?php echo h($f['nombre']); ?></td>
                        <td class="small text-muted"><?php echo h($f['unidad_nombre'] ? $f['unidad_nombre'] : '—'); ?></td>
                        <td class="small text-muted"><?php echo h($f['cargo'] ? $f['cargo'] : '—'); ?></td>
                        <td class="text-center">
                            <?php if ((int)$f['estado'] === 1): ?>
                                <span class="badge bg-success bg-opacity-10 text-success" style="font-size:.65rem;">ACTIVO</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:.65rem;">INACTIVO</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="funcionarios_editar.php?id=<?php echo (int)$f['id']; ?>"
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?toggle=<?php echo (int)$f['id']; ?>"
                                   class="btn btn-outline-<?php echo ((int)$f['estado'] === 1) ? 'warning' : 'success'; ?>"
                                   title="<?php echo ((int)$f['estado'] === 1) ? 'Desactivar' : 'Activar'; ?>"
                                   onclick="return confirm('¿Cambiar el estado de este funcionario?');">
                                    <i class="bi bi-<?php echo ((int)$f['estado'] === 1) ? 'pause-fill' : 'play-fill'; ?>"></i>
                                </a>
                                <a href="?eliminar=<?php echo (int)$f['id']; ?>"
                                   class="btn btn-outline-danger" title="Eliminar"
                                   onclick="return confirm('¿Eliminar definitivamente? Esta acción no se puede deshacer.');">
                                    <i class="bi bi-trash"></i>
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
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <small class="text-muted">
                Mostrando <?php echo count($funcionarios); ?> de <?php echo $total; ?> funcionarios
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPaginas; $i++):
                        $qs = $_GET; $qs['p'] = $i; $url = '?' . http_build_query($qs); ?>
                        <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo h($url); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>