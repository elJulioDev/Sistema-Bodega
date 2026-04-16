<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$buscar = get('buscar');
$id_bodega = get('id_bodega');
$tipo_movimiento = get('tipo_movimiento');

$stmt = $pdo->query("SELECT id, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre ASC");
$bodegas = $stmt->fetchAll();

$sql = "SELECT
            m.*,
            b.nombre AS bodega_nombre,
            p.codigo AS producto_codigo,
            p.nombre AS producto_nombre,
            u.nombre AS usuario_nombre
        FROM movimientos_bodega m
        INNER JOIN bodegas b ON b.id = m.id_bodega
        INNER JOIN productos p ON p.id = m.id_producto
        LEFT JOIN usuarios u ON u.id = m.id_usuario
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        p.codigo LIKE :buscar
        OR p.nombre LIKE :buscar
        OR b.nombre LIKE :buscar
        OR m.observacion LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

if ($id_bodega !== '') {
    $sql .= " AND m.id_bodega = :id_bodega";
    $params[':id_bodega'] = (int)$id_bodega;
}

if ($tipo_movimiento !== '') {
    $sql .= " AND m.tipo_movimiento = :tipo_movimiento";
    $params[':tipo_movimiento'] = $tipo_movimiento;
}

$sql .= " ORDER BY m.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

$pageTitle = 'Movimientos';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-arrow-left-right text-primary me-2"></i>Historial de Movimientos</h1>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label text-secondary fw-bold small">Buscar general</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Producto, bodega, u observación...">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label text-secondary fw-bold small">Bodega</label>
                <select name="id_bodega" class="form-select">
                    <option value="">Todas las bodegas</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>" <?php echo ((string)$id_bodega === (string)$b['id']) ? 'selected' : ''; ?>>
                            <?php echo h($b['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label text-secondary fw-bold small">Tipo de Movimiento</label>
                <select name="tipo_movimiento" class="form-select">
                    <option value="">Todos los tipos</option>
                    <option value="entrada_compra" <?php echo ($tipo_movimiento === 'entrada_compra') ? 'selected' : ''; ?>>Entrada por Compra</option>
                    <option value="salida_consumo" <?php echo ($tipo_movimiento === 'salida_consumo') ? 'selected' : ''; ?>>Salida por Consumo</option>
                    <option value="ajuste_entrada" <?php echo ($tipo_movimiento === 'ajuste_entrada') ? 'selected' : ''; ?>>Ajuste de Entrada</option>
                    <option value="ajuste_salida" <?php echo ($tipo_movimiento === 'ajuste_salida') ? 'selected' : ''; ?>>Ajuste de Salida</option>
                    <option value="traslado_entrada" <?php echo ($tipo_movimiento === 'traslado_entrada') ? 'selected' : ''; ?>>Traslado Entrada</option>
                    <option value="traslado_salida" <?php echo ($tipo_movimiento === 'traslado_salida') ? 'selected' : ''; ?>>Traslado Salida</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                <?php if ($buscar !== '' || $id_bodega !== '' || $tipo_movimiento !== ''): ?>
                    <a href="movimientos_lista.php" class="btn btn-light border" title="Limpiar"><i class="bi bi-eraser"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="table-light text-secondary" style="font-size: 0.85rem;">
                    <tr>
                        <th class="px-4 py-3">FECHA</th>
                        <th class="py-3">BODEGA</th>
                        <th class="py-3">PRODUCTO</th>
                        <th class="py-3 text-center">TIPO</th>
                        <th class="py-3 text-end">CANTIDAD</th>
                        <th class="py-3 text-end">TOTAL $</th>
                        <th class="py-3">REFERENCIA</th>
                        <th class="py-3">USUARIO</th>
                        <th class="px-4 py-3">OBSERVACIÓN</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$movimientos): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">No se encontraron movimientos.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td class="px-4 text-muted small"><?php echo date('d/m/Y H:i', strtotime($m['fecha_movimiento'])); ?></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary border-0"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h($m['bodega_nombre']); ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo h($m['producto_nombre']); ?></div>
                                <div class="text-muted small">Cód: <?php echo h($m['producto_codigo']); ?></div>
                            </td>
                            <td class="text-center">
                                <?php 
                                    $tipo = h($m['tipo_movimiento']);
                                    $badgeClass = (strpos($tipo, 'entrada') !== false) ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> border-0 text-uppercase">
                                    <?php echo str_replace('_', ' ', $tipo); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold fs-6 <?php echo (strpos($tipo, 'entrada') !== false) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo (strpos($tipo, 'entrada') !== false ? '+' : '-'); ?> <?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end fw-medium text-dark">$<?php echo number_format((float)$m['total'], 0, ',', '.'); ?></td>
                            <td class="text-muted small"><?php echo h($m['referencia_tipo'] . ' #' . $m['referencia_id']); ?></td>
                            <td class="text-secondary small"><i class="bi bi-person me-1"></i><?php echo !empty($m['usuario_nombre']) ? h($m['usuario_nombre']) : 'Sistema'; ?></td>
                            <td class="px-4 text-muted small text-truncate" style="max-width: 200px;" title="<?php echo h($m['observacion']); ?>">
                                <?php echo !empty($m['observacion']) ? h($m['observacion']) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php';