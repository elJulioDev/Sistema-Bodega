<?php
// modulos/funcionarios/funcionarios_lista.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

/*
|--------------------------------------------------------------------------
| Acciones rápidas
|--------------------------------------------------------------------------
*/

// Toggle estado funcionario (y su usuario si existe)
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE funcionarios SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));

    // Sincronizar estado del usuario vinculado (si existe)
    $stmt = $pdo->prepare("UPDATE usuarios SET estado = (SELECT estado FROM funcionarios WHERE id = ?) WHERE id_funcionario = ?");
    $stmt->execute(array($id, $id));

    set_flash('success', 'Estado del funcionario actualizado.');
    redirect('funcionarios_lista.php');
}

// Revocar acceso al sistema (eliminar usuario pero conservar funcionario)
if (isset($_GET['revocar_acceso'])) {
    $id = (int)$_GET['revocar_acceso'];

    // Validar que no sea su propio usuario
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id_funcionario = ? LIMIT 1");
    $stmt->execute(array($id));
    $uid = (int)$stmt->fetchColumn();

    if ($uid === (int)$_SESSION['user_id']) {
        set_flash('error', 'No puedes revocar tu propio acceso.');
    } elseif ($uid > 0) {
        try {
            // Desvincular encargado de bodegas si aplica
            $pdo->prepare("UPDATE bodegas SET id_encargado = NULL WHERE id_encargado = ?")
                ->execute(array($uid));

            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")
                ->execute(array($uid));

            set_flash('success', 'Acceso revocado correctamente.');
        } catch (Exception $e) {
            set_flash('error', 'No se puede revocar: el usuario tiene registros asociados.');
        }
    }
    redirect('funcionarios_lista.php');
}

// Eliminar funcionario
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];

    // Bloquear si tiene usuario ligado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_funcionario = ?");
    $stmt->execute(array($id));
    if ((int)$stmt->fetchColumn() > 0) {
        set_flash('error', 'No se puede eliminar: revoca primero el acceso al sistema de este funcionario.');
    } else {
        try {
            $pdo->prepare("DELETE FROM funcionarios WHERE id = ?")->execute(array($id));
            set_flash('success', 'Funcionario eliminado.');
        } catch (Exception $e) {
            set_flash('error', 'No se puede eliminar: existen registros asociados.');
        }
    }
    redirect('funcionarios_lista.php');
}

/*
|--------------------------------------------------------------------------
| Filtros y listado
|--------------------------------------------------------------------------
*/
$q         = trim((string)get('q'));
$id_unidad = (int)get('id_unidad');
$rol_f     = trim((string)get('rol'));
$acceso    = trim((string)get('acceso'));
$estado    = get('estado', '');
$pagina    = max(1, (int)get('p'));
$perPage   = 50;
$offset    = ($pagina - 1) * $perPage;

$where  = array("1=1");
$params = array();

if ($q !== '') {
    $where[] = "(f.nombre LIKE :q OR f.rut LIKE :q OR f.codigo LIKE :q OR f.email LIKE :q)";
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
if ($rol_f !== '') {
    $where[] = "u.rol = :rol";
    $params[':rol'] = $rol_f;
}
if ($acceso === 'si') {
    $where[] = "u.id IS NOT NULL";
} elseif ($acceso === 'no') {
    $where[] = "u.id IS NULL";
}

$whereSql = implode(' AND ', $where);

// Total
$sqlCount = "SELECT COUNT(*) FROM funcionarios f
             LEFT JOIN usuarios u ON u.id_funcionario = f.id
             WHERE $whereSql";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / $perPage));

// Listado
$sql = "SELECT f.*,
               un.nombre AS unidad_nombre,
               u.id AS usuario_id,
               u.rol AS usuario_rol,
               u.estado AS usuario_estado,
               b.nombre AS bodega_nombre,
               b.codigo AS bodega_codigo,
               ub.nombre AS unidad_usuario_nombre
        FROM funcionarios f
        LEFT JOIN unidades_organizacionales un ON un.id = f.id_unidad
        LEFT JOIN usuarios u ON u.id_funcionario = f.id
        LEFT JOIN bodegas b ON b.id = u.id_bodega
        LEFT JOIN unidades_organizacionales ub ON ub.id = u.id_unidad
        WHERE $whereSql
        ORDER BY f.nombre ASC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

// Totales para KPIs
$kpis = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN f.estado = 1 THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN u.id IS NOT NULL THEN 1 ELSE 0 END) AS con_acceso
    FROM funcionarios f
    LEFT JOIN usuarios u ON u.id_funcionario = f.id
")->fetch();

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

// Badge por rol
function rolBadge($rol) {
    $map = array(
        'admin'       => array('Administrador', 'bg-danger bg-opacity-10 text-danger border-danger-subtle'),
        'bodega'      => array('Encargado',     'bg-primary bg-opacity-10 text-primary border-primary-subtle'),
        'solicitante' => array('Solicitante',   'bg-info bg-opacity-10 text-info border-info-subtle'),
    );
    return isset($map[$rol]) ? $map[$rol] : array(ucfirst($rol), 'bg-secondary bg-opacity-10 text-secondary');
}

$pageTitle = 'Funcionarios';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-person-badge text-primary me-2"></i>Funcionarios
        </h1>
        <p class="text-muted small mb-0 mt-1">Registro único de funcionarios y sus accesos al sistema.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="funcionarios_importar.php" class="btn btn-success">
            <i class="bi bi-file-earmark-arrow-up me-1"></i> Importar CSV
        </a>
        <a href="funcionarios_crear.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Nuevo Funcionario
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Funcionarios</p>
                <h3 class="mb-0 fw-bold text-dark"><?php echo (int)$kpis['total']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Activos</p>
                <h3 class="mb-0 fw-bold text-success"><?php echo (int)$kpis['activos']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Con acceso al sistema</p>
                <h3 class="mb-0 fw-bold text-info"><?php echo (int)$kpis['con_acceso']; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">Buscar</label>
                <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control form-control-sm" placeholder="Nombre, RUT, código o email">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Unidad</label>
                <select name="id_unidad" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad === (int)$u['id']) ? 'selected' : ''; ?>>
                            <?php echo h($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Acceso</label>
                <select name="acceso" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="si" <?php echo ($acceso === 'si') ? 'selected' : ''; ?>>Con acceso</option>
                    <option value="no" <?php echo ($acceso === 'no') ? 'selected' : ''; ?>>Sin acceso</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Rol</label>
                <select name="rol" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="admin"       <?php echo ($rol_f === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                    <option value="bodega"      <?php echo ($rol_f === 'bodega') ? 'selected' : ''; ?>>Encargado</option>
                    <option value="solicitante" <?php echo ($rol_f === 'solicitante') ? 'selected' : ''; ?>>Solicitante</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-bold text-secondary mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="1" <?php echo ($estado === '1') ? 'selected' : ''; ?>>Activos</option>
                    <option value="0" <?php echo ($estado === '0') ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1" title="Filtrar">
                    <i class="bi bi-funnel"></i>
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
                        <th class="px-3 py-2">RUT</th>
                        <th class="py-2">NOMBRE</th>
                        <th class="py-2 d-none d-md-table-cell">UNIDAD</th>
                        <th class="py-2 d-none d-lg-table-cell">CARGO</th>
                        <th class="py-2 text-center">ACCESO</th>
                        <th class="py-2 text-center d-none d-sm-table-cell">ESTADO</th>
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
                    <?php foreach ($funcionarios as $f):
                        $tieneUsuario = !empty($f['usuario_id']);
                        $rolInfo = $tieneUsuario ? rolBadge($f['usuario_rol']) : null;
                        $asignacion = '';
                        if ($tieneUsuario) {
                            if ($f['usuario_rol'] === 'bodega' && !empty($f['bodega_nombre'])) {
                                $asignacion = $f['bodega_codigo'] . ' — ' . $f['bodega_nombre'];
                            } elseif ($f['usuario_rol'] === 'solicitante' && !empty($f['unidad_usuario_nombre'])) {
                                $asignacion = $f['unidad_usuario_nombre'];
                            }
                        }
                    ?>
                    <tr>
                        <td class="px-3 small fw-medium"><?php echo h($f['rut']); ?></td>
                        <td>
                            <div class="small fw-medium text-dark"><?php echo h($f['nombre']); ?></div>
                            <?php if (!empty($f['email'])): ?>
                                <div class="text-muted" style="font-size:.7rem;"><?php echo h($f['email']); ?></div>
                            <?php endif; ?>
                            <div class="d-md-none text-muted" style="font-size:.7rem;">
                                <?php echo h($f['unidad_nombre'] ? $f['unidad_nombre'] : 'Sin unidad'); ?>
                            </div>
                        </td>
                        <td class="small text-muted d-none d-md-table-cell"><?php echo h($f['unidad_nombre'] ? $f['unidad_nombre'] : '—'); ?></td>
                        <td class="small text-muted d-none d-lg-table-cell"><?php echo h($f['cargo'] ? $f['cargo'] : '—'); ?></td>
                        <td class="text-center">
                            <?php if ($tieneUsuario): ?>
                                <span class="badge <?php echo $rolInfo[1]; ?> border px-2 py-1"><?php echo $rolInfo[0]; ?></span>
                                <?php if ($asignacion !== ''): ?>
                                    <div class="text-muted mt-1" style="font-size:.68rem;"><?php echo h($asignacion); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Sin acceso</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center d-none d-sm-table-cell">
                            <?php if ((int)$f['estado'] === 1): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 text-end">
                            <div class="btn-group" role="group">
                                <a href="funcionarios_ver.php?id=<?php echo (int)$f['id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="funcionarios_editar.php?id=<?php echo (int)$f['id']; ?>"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($tieneUsuario): ?>
                                    <a href="?revocar_acceso=<?php echo (int)$f['id']; ?>"
                                       class="btn btn-sm btn-outline-warning" title="Revocar acceso al sistema"
                                       onclick="return confirm('¿Revocar el acceso al sistema de <?php echo h(addslashes($f['nombre'])); ?>? El funcionario se mantiene, pero ya no podrá ingresar.');">
                                        <i class="bi bi-shield-slash"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?toggle=<?php echo (int)$f['id']; ?>"
                                   class="btn btn-sm btn-outline-<?php echo ((int)$f['estado'] === 1) ? 'danger' : 'success'; ?>"
                                   title="<?php echo ((int)$f['estado'] === 1) ? 'Desactivar' : 'Activar'; ?>"
                                   onclick="return confirm('¿Cambiar estado de este funcionario?');">
                                    <i class="bi bi-power"></i>
                                </a>
                                <?php if (!$tieneUsuario): ?>
                                    <a href="?eliminar=<?php echo (int)$f['id']; ?>"
                                       class="btn btn-sm btn-outline-danger" title="Eliminar"
                                       onclick="return confirm('¿Eliminar definitivamente este funcionario?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
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

<?php if ($totalPaginas > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($i = 1; $i <= $totalPaginas; $i++):
            $qs = $_GET; $qs['p'] = $i; $url = '?' . http_build_query($qs);
        ?>
            <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo h($url); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>