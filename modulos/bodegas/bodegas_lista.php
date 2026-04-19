<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

/*
|--------------------------------------------------------------------------
| Procesar cambio de estado
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

            // Si está activa y la quieren desactivar
            if ($estadoActual === 1) {
                $stmtStock = $pdo->prepare("
                    SELECT COALESCE(SUM(stock_actual), 0) AS total_stock
                    FROM stock_bodega
                    WHERE id_bodega = :id_bodega
                ");
                $stmtStock->execute(array(':id_bodega' => $id));
                $rowStock = $stmtStock->fetch(PDO::FETCH_ASSOC);

                $totalStock = isset($rowStock['total_stock']) ? (float)$rowStock['total_stock'] : 0;

                if ($totalStock > 0) {
                    set_flash('danger', 'No se puede desactivar la bodega porque contiene productos en existencia.');
                    redirect('bodegas_lista.php');
                }

                $stmtUpdate = $pdo->prepare("UPDATE bodegas SET estado = 0 WHERE id = :id");
                $stmtUpdate->execute(array(':id' => $id));

                set_flash('success', 'Bodega desactivada correctamente.');
                redirect('bodegas_lista.php');
            } else {
                // Si está inactiva, se puede activar
                $stmtUpdate = $pdo->prepare("UPDATE bodegas SET estado = 1 WHERE id = :id");
                $stmtUpdate->execute(array(':id' => $id));

                set_flash('success', 'Bodega activada correctamente.');
                redirect('bodegas_lista.php');
            }
        } else {
            set_flash('danger', 'La bodega no existe.');
            redirect('bodegas_lista.php');
        }
    } else {
        set_flash('danger', 'ID de bodega no válido.');
        redirect('bodegas_lista.php');
    }
}

/*
|--------------------------------------------------------------------------
| Eliminar bodega
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id, nombre, estado FROM bodegas WHERE id = :id LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $bodega = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bodega) {
            set_flash('danger', 'La bodega no existe.');
            redirect('bodegas_lista.php');
        }

        // Solo permitir eliminar si está inactiva
        if ((int)$bodega['estado'] !== 0) {
            set_flash('danger', 'Solo se pueden eliminar bodegas inactivas.');
            redirect('bodegas_lista.php');
        }

        // Validar que no tenga stock
        $stmtStock = $pdo->prepare("
            SELECT COALESCE(SUM(stock_actual), 0) AS total_stock
            FROM stock_bodega
            WHERE id_bodega = :id_bodega
        ");
        $stmtStock->execute(array(':id_bodega' => $id));
        $rowStock = $stmtStock->fetch(PDO::FETCH_ASSOC);

        $totalStock = isset($rowStock['total_stock']) ? (float)$rowStock['total_stock'] : 0;

        if ($totalStock > 0) {
            set_flash('danger', 'No se puede eliminar la bodega porque aún tiene productos en existencia.');
            redirect('bodegas_lista.php');
        }

        // Opcional: validar que no existan registros en stock_bodega, aunque estén en 0
        $stmtRelacion = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM stock_bodega
            WHERE id_bodega = :id_bodega
        ");
        $stmtRelacion->execute(array(':id_bodega' => $id));
        $rowRelacion = $stmtRelacion->fetch(PDO::FETCH_ASSOC);

        $totalRelacion = isset($rowRelacion['total']) ? (int)$rowRelacion['total'] : 0;

        if ($totalRelacion > 0) {
            set_flash('danger', 'No se puede eliminar la bodega porque tiene movimientos o registros asociados en stock.');
            redirect('bodegas_lista.php');
        }

        $stmtDelete = $pdo->prepare("DELETE FROM bodegas WHERE id = :id");
        $stmtDelete->execute(array(':id' => $id));

        set_flash('success', 'Bodega eliminada correctamente.');
        redirect('bodegas_lista.php');
    } else {
        set_flash('danger', 'ID de bodega no válido.');
        redirect('bodegas_lista.php');
    }
}

/*
|--------------------------------------------------------------------------
| Cargar vista
|--------------------------------------------------------------------------
*/
$pageTitle = 'Bodegas';
require_once __DIR__ . '/../../inc/header.php';

$stmt = $pdo->query("SELECT * FROM bodegas WHERE estado = 1 ORDER BY id DESC");
$bodegasActivas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM bodegas WHERE estado = 0 ORDER BY id DESC");
$bodegasInactivas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flashSuccess = function_exists('get_flash') ? get_flash('success') : '';
$flashDanger  = function_exists('get_flash') ? get_flash('danger') : '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-buildings text-primary me-2"></i>Gestión de Bodegas
    </h1>
    <a href="bodegas_crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nueva Bodega
    </a>
</div>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo h($flashSuccess); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>

<?php if (!empty($flashDanger)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($flashDanger); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>

<!-- BODEGAS ACTIVAS -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 py-3">
        <h2 class="h5 mb-0 text-success">
            <i class="bi bi-check-circle me-2"></i>Bodegas Activas
        </h2>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">CÓDIGO</th>
                        <th class="py-3">NOMBRE</th>
                        <th class="py-3">RESPONSABLE</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$bodegasActivas): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            No hay bodegas activas registradas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bodegasActivas as $b): ?>
                        <tr>
                            <td class="px-4 fw-medium text-muted"><?php echo (int)$b['id']; ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo h($b['codigo']); ?></span></td>
                            <td class="fw-bold text-dark"><?php echo h($b['nombre']); ?></td>
                            <td>
                                <?php if (!empty($b['responsable'])): ?>
                                    <i class="bi bi-person me-1 text-muted"></i><?php echo h($b['responsable']); ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 border-0">Activo</span>
                            </td>
                            <td class="px-4 text-end">
                                <div class="btn-group" role="group">
                                    <a href="bodegas_editar.php?id=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <a href="?toggle=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       title="Desactivar"
                                       onclick="return confirm('¿Deseas desactivar esta bodega?');">
                                        <i class="bi bi-power"></i>
                                    </a>
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

<!-- BODEGAS INACTIVAS -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 py-3">
        <h2 class="h5 mb-0 text-danger">
            <i class="bi bi-x-circle me-2"></i>Bodegas Inactivas
        </h2>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">CÓDIGO</th>
                        <th class="py-3">NOMBRE</th>
                        <th class="py-3">RESPONSABLE</th>
                        <th class="py-3 text-center">ESTADO</th>
                        <th class="px-4 py-3 text-end">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$bodegasInactivas): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            No hay bodegas inactivas registradas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bodegasInactivas as $b): ?>
                        <tr>
                            <td class="px-4 fw-medium text-muted"><?php echo (int)$b['id']; ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo h($b['codigo']); ?></span></td>
                            <td class="fw-bold text-dark"><?php echo h($b['nombre']); ?></td>
                            <td>
                                <?php if (!empty($b['responsable'])): ?>
                                    <i class="bi bi-person me-1 text-muted"></i><?php echo h($b['responsable']); ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 border-0">Inactivo</span>
                            </td>
                            <td class="px-4 text-end">
                                <div class="btn-group" role="group">
                                    <a href="bodegas_editar.php?id=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <a href="?toggle=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-success"
                                       title="Activar"
                                       onclick="return confirm('¿Deseas activar esta bodega?');">
                                        <i class="bi bi-check-circle"></i>
                                    </a>

                                    <a href="?delete=<?php echo (int)$b['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       title="Eliminar"
                                       onclick="return confirm('¿Deseas eliminar definitivamente esta bodega inactiva?');">
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
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>