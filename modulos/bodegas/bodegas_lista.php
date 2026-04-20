<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

/*
|--------------------------------------------------------------------------
| Toggle estado (activar / desactivar)
|--------------------------------------------------------------------------
*/
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id, nombre, estado FROM bodegas WHERE id = :id LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $bodega = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bodega) {
            $estadoActual = (int)$bodega['estado'];

            if ($estadoActual === 1) {
                // Validar sin stock
                $stmtStock = $pdo->prepare("
                    SELECT COALESCE(SUM(stock_actual), 0) AS total_stock
                    FROM stock_bodega WHERE id_bodega = :id_bodega
                ");
                $stmtStock->execute(array(':id_bodega' => $id));
                $totalStock = (float)$stmtStock->fetchColumn();

                if ($totalStock > 0) {
                    set_flash('danger', 'No se puede desactivar: contiene productos en existencia.');
                    redirect('bodegas_lista.php');
                }

                $pdo->prepare("UPDATE bodegas SET estado = 0 WHERE id = :id")->execute(array(':id' => $id));
                set_flash('success', 'Bodega desactivada correctamente.');
            } else {
                $pdo->prepare("UPDATE bodegas SET estado = 1 WHERE id = :id")->execute(array(':id' => $id));
                set_flash('success', 'Bodega activada correctamente.');
            }
        } else {
            set_flash('danger', 'La bodega no existe.');
        }
    }
    redirect('bodegas_lista.php');
}

/*
|--------------------------------------------------------------------------
| Eliminar bodega
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id, nombre, estado, id_encargado FROM bodegas WHERE id = :id LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $bodega = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bodega) {
            set_flash('danger', 'La bodega no existe.');
            redirect('bodegas_lista.php');
        }

        if ((int)$bodega['estado'] !== 0) {
            set_flash('danger', 'Solo se pueden eliminar bodegas inactivas.');
            redirect('bodegas_lista.php');
        }

        // Validar sin stock
        $stmtStock = $pdo->prepare("
            SELECT COALESCE(SUM(stock_actual), 0) AS total_stock,
                   COUNT(*) AS total_filas
            FROM stock_bodega WHERE id_bodega = :id_bodega
        ");
        $stmtStock->execute(array(':id_bodega' => $id));
        $r = $stmtStock->fetch(PDO::FETCH_ASSOC);

        if ((float)$r['total_stock'] > 0) {
            set_flash('danger', 'No se puede eliminar: tiene productos en existencia.');
            redirect('bodegas_lista.php');
        }

        if ((int)$r['total_filas'] > 0) {
            set_flash('danger', 'No se puede eliminar: tiene registros de stock asociados.');
            redirect('bodegas_lista.php');
        }

        // Validar sin movimientos
        $stmtMov = $pdo->prepare("SELECT COUNT(*) FROM movimientos_bodega WHERE id_bodega = :id_bodega");
        $stmtMov->execute(array(':id_bodega' => $id));
        if ((int)$stmtMov->fetchColumn() > 0) {
            set_flash('danger', 'No se puede eliminar: tiene movimientos registrados.');
            redirect('bodegas_lista.php');
        }

        try {
            // Liberar al encargado si lo tenía
            if (!empty($bodega['id_encargado'])) {
                $pdo->prepare("UPDATE usuarios SET id_bodega = NULL WHERE id = ?")
                    ->execute(array((int)$bodega['id_encargado']));
            }

            $pdo->prepare("DELETE FROM bodegas WHERE id = :id")->execute(array(':id' => $id));
            set_flash('success', 'Bodega eliminada correctamente.');
        } catch (Exception $e) {
            set_flash('danger', 'No se puede eliminar: ' . $e->getMessage());
        }
    }
    redirect('bodegas_lista.php');
}

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/
$q = trim((string)get('q'));

$where  = array("1=1");
$params = array();

if ($q !== '') {
    $where[] = "(b.nombre LIKE :q OR b.codigo LIKE :q OR f.nombre LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$whereSql = implode(' AND ', $where);

$sqlBase = "
    SELECT b.*,
           un.nombre AS unidad_nombre,
           COALESCE(f.nombre, u.nombre) AS encargado_nombre,
           f.rut AS encargado_rut,
           u.id AS encargado_usuario_id,
           (SELECT COALESCE(SUM(stock_actual), 0) FROM stock_bodega WHERE id_bodega = b.id) AS total_stock,
           (SELECT COUNT(DISTINCT id_producto) FROM stock_bodega WHERE id_bodega = b.id AND stock_actual > 0) AS total_productos
    FROM bodegas b
    LEFT JOIN unidades_organizacionales un ON un.id = b.id_unidad
    LEFT JOIN usuarios u ON u.id = b.id_encargado
    LEFT JOIN funcionarios f ON f.id = u.id_funcionario
    WHERE $whereSql
    ORDER BY b.es_central DESC, b.nombre ASC
";
$stmt = $pdo->prepare($sqlBase);
$stmt->execute($params);
$todas = $stmt->fetchAll();

$bodegasActivas   = array();
$bodegasInactivas = array();
foreach ($todas as $b) {
    if ((int)$b['estado'] === 1) $bodegasActivas[] = $b;
    else                         $bodegasInactivas[] = $b;
}

$pageTitle = 'Bodegas';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-buildings text-primary me-2"></i>Gestión de Bodegas
        </h1>
        <p class="text-muted small mb-0 mt-1">Administra las bodegas y sus encargados.</p>
    </div>
    <a href="bodegas_crear.php" class="btn btn-primary">
        <i class="bi bi-building-add me-1"></i> Nueva Bodega
    </a>
</div>

<!-- Filtro -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-8">
                <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control form-control-sm" placeholder="Buscar por nombre, código o encargado...">
            </div>
            <div class="col-md-4 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="bodegas_lista.php" class="btn btn-sm btn-outline-secondary" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Bodegas activas -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 py-3">
        <h2 class="h5 mb-0 text-success">
            <i class="bi bi-check-circle me-2"></i>Bodegas Activas
            <span class="badge bg-success bg-opacity-10 text-success ms-2"><?php echo count($bodegasActivas); ?></span>
        </h2>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.80rem;">
                    <tr>
                        <th class="px-3 py-2">CÓDIGO</th>
                        <th class="py-2">NOMBRE</th>
                        <th class="py-2 d-none d-md-table-cell">UNIDAD</th>
                        <th class="py-2">ENCARGADO</th>
                        <th class="py-2 text-end d-none d-sm-table-cell">STOCK</th>
                        <th class="px-3 py-2 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$bodegasActivas): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-1"></i>No hay bodegas activas.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($bodegasActivas as $b):
                        $puedeDesactivar = ((float)$b['total_stock'] <= 0);
                    ?>
                        <tr>
                            <td class="px-3">
                                <span class="badge bg-light text-dark border"><?php echo h($b['codigo']); ?></span>
                                <?php if ((int)$b['es_central'] === 1): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle" style="font-size:.6rem;">CENTRAL</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark small"><?php echo h($b['nombre']); ?></div>
                                <?php if (!empty($b['ubicacion_referencial'])): ?>
                                    <div class="text-muted" style="font-size:.7rem;"><i class="bi bi-geo-alt me-1"></i><?php echo h($b['ubicacion_referencial']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted d-none d-md-table-cell">
                                <?php echo h($b['unidad_nombre'] ? $b['unidad_nombre'] : '—'); ?>
                            </td>
                            <td class="small">
                                <?php if (!empty($b['encargado_nombre'])): ?>
                                    <div class="fw-medium"><i class="bi bi-person me-1 text-muted"></i><?php echo h($b['encargado_nombre']); ?></div>
                                    <?php if (!empty($b['encargado_rut'])): ?>
                                        <div class="text-muted" style="font-size:.7rem;"><?php echo h($b['encargado_rut']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small d-none d-sm-table-cell">
                                <span class="fw-bold"><?php echo number_format((float)$b['total_stock'], 2, ',', '.'); ?></span>
                                <div class="text-muted" style="font-size:.7rem;"><?php echo (int)$b['total_productos']; ?> productos</div>
                            </td>
                            <td class="px-3 text-end">
                                <div class="btn-group" role="group">
                                    <a href="bodegas_ver.php?id=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="bodegas_editar.php?id=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($puedeDesactivar): ?>
                                        <a href="?toggle=<?php echo (int)$b['id']; ?>"
                                           class="btn btn-sm btn-outline-danger" title="Desactivar"
                                           onclick="return confirm('¿Desactivar esta bodega?');">
                                            <i class="bi bi-power"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                title="No se puede desactivar: contiene stock">
                                            <i class="bi bi-power"></i>
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

<!-- Bodegas inactivas -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 py-3">
        <h2 class="h5 mb-0 text-danger">
            <i class="bi bi-x-circle me-2"></i>Bodegas Inactivas
            <span class="badge bg-danger bg-opacity-10 text-danger ms-2"><?php echo count($bodegasInactivas); ?></span>
        </h2>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.80rem;">
                    <tr>
                        <th class="px-3 py-2">CÓDIGO</th>
                        <th class="py-2">NOMBRE</th>
                        <th class="py-2">ENCARGADO</th>
                        <th class="py-2 text-end d-none d-sm-table-cell">STOCK</th>
                        <th class="px-3 py-2 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$bodegasInactivas): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle fs-3 d-block mb-1"></i>No hay bodegas inactivas.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($bodegasInactivas as $b):
                        $puedeEliminar = ((float)$b['total_stock'] <= 0);
                        if ($puedeEliminar) {
                            // Verificación extra: sin filas stock y sin movimientos
                            $chk = (int)$pdo->query("SELECT
                                (SELECT COUNT(*) FROM stock_bodega WHERE id_bodega={$b['id']}) +
                                (SELECT COUNT(*) FROM movimientos_bodega WHERE id_bodega={$b['id']})")->fetchColumn();
                            $puedeEliminar = ($chk === 0);
                        }
                    ?>
                        <tr>
                            <td class="px-3"><span class="badge bg-light text-dark border"><?php echo h($b['codigo']); ?></span></td>
                            <td>
                                <div class="fw-bold text-dark small"><?php echo h($b['nombre']); ?></div>
                            </td>
                            <td class="small">
                                <?php if (!empty($b['encargado_nombre'])): ?>
                                    <i class="bi bi-person me-1 text-muted"></i><?php echo h($b['encargado_nombre']); ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small d-none d-sm-table-cell">
                                <?php echo number_format((float)$b['total_stock'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-3 text-end">
                                <div class="btn-group" role="group">
                                    <a href="bodegas_ver.php?id=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="bodegas_editar.php?id=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?toggle=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-success" title="Activar"
                                       onclick="return confirm('¿Activar esta bodega?');">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                    <?php if ($puedeEliminar): ?>
                                        <a href="?delete=<?php echo (int)$b['id']; ?>"
                                           class="btn btn-sm btn-outline-danger" title="Eliminar definitivamente"
                                           onclick="return confirm('¿Eliminar definitivamente esta bodega? Esta acción no se puede deshacer.');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                title="No se puede eliminar: tiene stock o movimientos registrados">
                                            <i class="bi bi-trash"></i>
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

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>