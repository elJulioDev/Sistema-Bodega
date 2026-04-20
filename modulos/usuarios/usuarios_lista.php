<?php
// modulos/usuarios/usuarios_lista.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

// Toggle estado
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash('error', 'No puedes desactivar tu propia cuenta.');
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET estado = IF(estado=1,0,1) WHERE id = ?");
        $stmt->execute(array($id));
        set_flash('success', 'Estado del usuario actualizado.');
    }
    redirect('usuarios_lista.php');
}

// Eliminar
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash('error', 'No puedes eliminar tu propia cuenta.');
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute(array($id));
            set_flash('success', 'Usuario eliminado.');
        } catch (Exception $e) {
            set_flash('error', 'No se puede eliminar: el usuario tiene registros asociados.');
        }
    }
    redirect('usuarios_lista.php');
}

// Filtros
$q      = trim((string)get('q'));
$rol_f  = trim((string)get('rol'));
$estado = get('estado', '');

$where  = array("1=1");
$params = array();

if ($q !== '') {
    $where[] = "(u.nombre LIKE :q OR u.usuario LIKE :q OR f.rut LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($rol_f !== '') {
    $where[] = "u.rol = :r";
    $params[':r'] = $rol_f;
}
if ($estado !== '') {
    $where[] = "u.estado = :e";
    $params[':e'] = (int)$estado;
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT u.id, u.nombre, u.usuario, u.rol, u.estado,
               f.rut AS funcionario_rut,
               b.nombre AS bodega_nombre,
               un.nombre AS unidad_nombre
        FROM usuarios u
        LEFT JOIN funcionarios f           ON f.id  = u.id_funcionario
        LEFT JOIN bodegas b                ON b.id  = u.id_bodega
        LEFT JOIN unidades_organizacionales un ON un.id = u.id_unidad
        WHERE $whereSql
        ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$pageTitle = 'Usuarios';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-people text-primary me-2"></i>Gestión de Usuarios
    </h1>
    <a href="usuarios_crear.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Nuevo Usuario
    </a>
</div>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-secondary mb-1">Buscar</label>
                <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control form-control-sm" placeholder="Nombre, usuario o RUT">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Rol</label>
                <select name="rol" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="admin"       <?php echo ($rol_f === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                    <option value="bodega"      <?php echo ($rol_f === 'bodega') ? 'selected' : ''; ?>>Encargado</option>
                    <option value="solicitante" <?php echo ($rol_f === 'solicitante') ? 'selected' : ''; ?>>Solicitante</option>
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
                <a href="usuarios_lista.php" class="btn btn-sm btn-outline-secondary" title="Limpiar">
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
                        <th class="px-3 py-2">USUARIO (RUT)</th>
                        <th class="py-2">NOMBRE</th>
                        <th class="py-2 text-center">ROL</th>
                        <th class="py-2">ASIGNACIÓN</th>
                        <th class="py-2 text-center">ESTADO</th>
                        <th class="px-3 py-2 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$usuarios): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                            No se encontraron usuarios.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u):
                        $rolLabel = array(
                            'admin'       => array('Administrador', 'bg-danger bg-opacity-10 text-danger'),
                            'bodega'      => array('Encargado',     'bg-primary bg-opacity-10 text-primary'),
                            'solicitante' => array('Solicitante',   'bg-info bg-opacity-10 text-info'),
                            'consulta'    => array('Consulta',      'bg-secondary bg-opacity-10 text-secondary'),
                            'auditor'     => array('Auditor',       'bg-warning bg-opacity-10 text-warning'),
                        );
                        $rolInfo = isset($rolLabel[$u['rol']]) ? $rolLabel[$u['rol']] : array($u['rol'], 'bg-secondary bg-opacity-10 text-secondary');

                        // Asignación según rol
                        $asignacion = '—';
                        if ($u['rol'] === 'bodega' && $u['bodega_nombre']) {
                            $asignacion = '<i class="bi bi-buildings text-primary me-1"></i>' . h($u['bodega_nombre']);
                        } elseif ($u['rol'] === 'solicitante' && $u['unidad_nombre']) {
                            $asignacion = '<i class="bi bi-diagram-3 text-info me-1"></i>' . h($u['unidad_nombre']);
                        } elseif ($u['rol'] === 'admin') {
                            $asignacion = '<span class="text-muted">Acceso total</span>';
                        }
                    ?>
                    <tr>
                        <td class="px-3 small fw-medium"><?php echo h($u['usuario']); ?></td>
                        <td class="small">
                            <?php echo h($u['nombre']); ?>
                            <?php if ($u['funcionario_rut']): ?>
                                <div class="text-muted" style="font-size:.70rem;">
                                    <i class="bi bi-person-badge"></i> Funcionario vinculado
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?php echo $rolInfo[1]; ?>" style="font-size:.65rem;">
                                <?php echo strtoupper($rolInfo[0]); ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?php echo $asignacion; ?></td>
                        <td class="text-center">
                            <?php if ((int)$u['estado'] === 1): ?>
                                <span class="badge bg-success bg-opacity-10 text-success" style="font-size:.65rem;">ACTIVO</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:.65rem;">INACTIVO</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="usuarios_editar.php?id=<?php echo (int)$u['id']; ?>"
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?toggle=<?php echo (int)$u['id']; ?>"
                                   class="btn btn-outline-<?php echo ((int)$u['estado'] === 1) ? 'warning' : 'success'; ?>"
                                   title="<?php echo ((int)$u['estado'] === 1) ? 'Desactivar' : 'Activar'; ?>"
                                   onclick="return confirm('¿Cambiar estado de este usuario?');">
                                    <i class="bi bi-<?php echo ((int)$u['estado'] === 1) ? 'pause-fill' : 'play-fill'; ?>"></i>
                                </a>
                                <a href="?eliminar=<?php echo (int)$u['id']; ?>"
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

        <div class="p-3 border-top">
            <small class="text-muted">Total: <strong><?php echo count($usuarios); ?></strong> usuarios</small>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>