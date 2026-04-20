<?php
// modulos/bodegas/bodegas_ver.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("
    SELECT b.*,
           un.nombre AS unidad_nombre,
           u.id AS encargado_id, u.usuario AS encargado_usuario,
           COALESCE(f.nombre, u.nombre) AS encargado_nombre,
           f.rut AS encargado_rut, f.email AS encargado_email, f.cargo AS encargado_cargo,
           f.id AS encargado_funcionario_id
    FROM bodegas b
    LEFT JOIN unidades_organizacionales un ON un.id = b.id_unidad
    LEFT JOIN usuarios u ON u.id = b.id_encargado
    LEFT JOIN funcionarios f ON f.id = u.id_funcionario
    WHERE b.id = ?
    LIMIT 1
");
$stmt->execute(array($id));
$b = $stmt->fetch();

if (!$b) { die('Bodega no encontrada.'); }

// KPIs
$sqlKpi = "
    SELECT
        COUNT(DISTINCT sb.id_producto) AS productos,
        COALESCE(SUM(sb.stock_actual), 0) AS unidades,
        COALESCE(SUM(sb.stock_actual * sb.costo_promedio), 0) AS valor,
        SUM(CASE WHEN sb.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
        SUM(CASE WHEN p.stock_minimo > 0 AND sb.stock_actual > 0 AND sb.stock_actual <= p.stock_minimo THEN 1 ELSE 0 END) AS stock_bajo
    FROM stock_bodega sb
    INNER JOIN productos p ON p.id = sb.id_producto
    WHERE sb.id_bodega = :idb AND p.estado = 1
";
$stmt = $pdo->prepare($sqlKpi);
$stmt->execute(array(':idb' => $id));
$kpi = $stmt->fetch();

// Stock completo
$sqlStock = "
    SELECT sb.id_producto, sb.stock_actual, sb.costo_promedio,
           p.codigo, p.nombre, p.stock_minimo,
           um.codigo AS um_codigo
    FROM stock_bodega sb
    INNER JOIN productos p ON p.id = sb.id_producto
    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    WHERE sb.id_bodega = :idb AND p.estado = 1
    ORDER BY sb.stock_actual DESC, p.nombre ASC
";
$stmt = $pdo->prepare($sqlStock);
$stmt->execute(array(':idb' => $id));
$stock = $stmt->fetchAll();

// Movimientos recientes (últimos 10)
$sqlMov = "
    SELECT m.id, m.tipo_movimiento, m.cantidad, m.precio_unitario, m.total,
           m.fecha_movimiento, m.observacion,
           p.codigo AS producto_codigo, p.nombre AS producto_nombre,
           u.nombre AS usuario_nombre
    FROM movimientos_bodega m
    LEFT JOIN productos p ON p.id = m.id_producto
    LEFT JOIN usuarios u ON u.id = m.id_usuario
    WHERE m.id_bodega = :idb
    ORDER BY m.fecha_movimiento DESC, m.id DESC
    LIMIT 10
";
$stmt = $pdo->prepare($sqlMov);
$stmt->execute(array(':idb' => $id));
$movimientos = $stmt->fetchAll();

$totalMov = (int)$pdo->query("SELECT COUNT(*) FROM movimientos_bodega WHERE id_bodega = " . (int)$id)->fetchColumn();
$totalStockRows = (int)$pdo->query("SELECT COUNT(*) FROM stock_bodega WHERE id_bodega = " . (int)$id)->fetchColumn();

// Determinar si se puede desactivar/eliminar
$puedeDesactivar = ((float)$kpi['unidades'] <= 0);
$puedeEliminar   = ((int)$b['estado'] === 0) && ((float)$kpi['unidades'] <= 0) && ($totalStockRows === 0) && ($totalMov === 0);

// Labels para tipos de movimiento
$tipoLabel = array(
    'entrada_compra'   => array('Entrada compra',  'bg-success bg-opacity-10 text-success border-success-subtle'),
    'salida_consumo'   => array('Salida consumo',  'bg-danger bg-opacity-10 text-danger border-danger-subtle'),
    'ajuste_entrada'   => array('Ajuste entrada',  'bg-info bg-opacity-10 text-info border-info-subtle'),
    'ajuste_salida'    => array('Ajuste salida',   'bg-warning bg-opacity-10 text-warning border-warning-subtle'),
    'traslado_entrada' => array('Traslado entrada','bg-primary bg-opacity-10 text-primary border-primary-subtle'),
    'traslado_salida'  => array('Traslado salida', 'bg-secondary bg-opacity-10 text-secondary border-secondary-subtle'),
);

$pageTitle = 'Detalle Bodega';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb" class="small mb-1">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="bodegas_lista.php" class="text-decoration-none">Bodegas</a></li>
                <li class="breadcrumb-item active"><?php echo h($b['nombre']); ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-buildings text-primary me-2"></i><?php echo h($b['nombre']); ?>
            <span class="badge bg-light text-dark border ms-2" style="font-size:.6em;"><?php echo h($b['codigo']); ?></span>
            <?php if ((int)$b['es_central'] === 1): ?>
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle ms-1" style="font-size:.6em;">CENTRAL</span>
            <?php endif; ?>
        </h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="bodegas_editar.php?id=<?php echo (int)$b['id']; ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>

        <?php if ((int)$b['estado'] === 1): ?>
            <?php if ($puedeDesactivar): ?>
                <a href="bodegas_lista.php?toggle=<?php echo (int)$b['id']; ?>"
                   class="btn btn-outline-danger"
                   onclick="return confirm('¿Desactivar esta bodega?');">
                    <i class="bi bi-power me-1"></i> Desactivar
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-danger" disabled title="No se puede desactivar con stock en existencia">
                    <i class="bi bi-power me-1"></i> Desactivar
                </button>
            <?php endif; ?>
        <?php else: ?>
            <a href="bodegas_lista.php?toggle=<?php echo (int)$b['id']; ?>"
               class="btn btn-outline-success"
               onclick="return confirm('¿Activar esta bodega?');">
                <i class="bi bi-check-circle me-1"></i> Activar
            </a>

            <?php if ($puedeEliminar): ?>
                <a href="bodegas_lista.php?delete=<?php echo (int)$b['id']; ?>"
                   class="btn btn-outline-danger"
                   onclick="return confirm('¿Eliminar definitivamente esta bodega? Esta acción no se puede deshacer.');">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-danger" disabled title="Solo se puede eliminar si está inactiva y sin stock ni movimientos registrados">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <a href="bodegas_lista.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Productos</p>
                <h3 class="mb-0 fw-bold text-dark"><?php echo number_format((int)$kpi['productos'], 0, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Unidades</p>
                <h3 class="mb-0 fw-bold text-dark"><?php echo number_format((float)$kpi['unidades'], 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Valor total</p>
                <h3 class="mb-0 fw-bold text-success">$<?php echo number_format((float)$kpi['valor'], 0, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
            <div class="card-body py-3">
                <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Stock bajo</p>
                <h3 class="mb-0 fw-bold text-warning"><?php echo (int)$kpi['stock_bajo']; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Información + Encargado -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-info-circle text-primary me-2"></i>Información
                </h5>
            </div>
            <div class="card-body p-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Código</dt>
                    <dd class="col-sm-8 fw-medium"><?php echo h($b['codigo']); ?></dd>

                    <dt class="col-sm-4 text-muted">Nombre</dt>
                    <dd class="col-sm-8 fw-medium"><?php echo h($b['nombre']); ?></dd>

                    <dt class="col-sm-4 text-muted">Unidad</dt>
                    <dd class="col-sm-8"><?php echo h($b['unidad_nombre'] ? $b['unidad_nombre'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Ubicación</dt>
                    <dd class="col-sm-8"><?php echo h($b['ubicacion_referencial'] ? $b['ubicacion_referencial'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Descripción</dt>
                    <dd class="col-sm-8"><?php echo h($b['descripcion'] ? $b['descripcion'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Estado</dt>
                    <dd class="col-sm-8">
                        <?php if ((int)$b['estado'] === 1): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">Activa</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">Inactiva</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Creada</dt>
                    <dd class="col-sm-8"><?php echo h(date('d-m-Y H:i', strtotime($b['created_at']))); ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-person-badge text-success me-2"></i>Encargado
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($b['encargado_id'])): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-person-x fs-1 text-muted d-block mb-2"></i>
                        <p class="text-muted mb-3">Esta bodega no tiene encargado asignado.</p>
                        <a href="bodegas_editar.php?id=<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-person-plus me-1"></i> Asignar encargado
                        </a>
                    </div>
                <?php else: ?>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Nombre</dt>
                        <dd class="col-sm-8 fw-bold text-dark"><?php echo h($b['encargado_nombre']); ?></dd>

                        <?php if (!empty($b['encargado_rut'])): ?>
                        <dt class="col-sm-4 text-muted">RUT</dt>
                        <dd class="col-sm-8"><?php echo h($b['encargado_rut']); ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($b['encargado_email'])): ?>
                        <dt class="col-sm-4 text-muted">Email</dt>
                        <dd class="col-sm-8"><?php echo h($b['encargado_email']); ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($b['encargado_cargo'])): ?>
                        <dt class="col-sm-4 text-muted">Cargo</dt>
                        <dd class="col-sm-8"><?php echo h($b['encargado_cargo']); ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4 text-muted">Usuario</dt>
                        <dd class="col-sm-8"><code><?php echo h($b['encargado_usuario']); ?></code></dd>
                    </dl>

                    <?php if (!empty($b['encargado_funcionario_id'])): ?>
                    <div class="mt-3 pt-3 border-top">
                        <a href="../funcionarios/funcionarios_ver.php?id=<?php echo (int)$b['encargado_funcionario_id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i> Ver ficha del funcionario
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Stock actual -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-boxes text-info me-2"></i>Stock actual
            <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2"><?php echo count($stock); ?></span>
        </h5>
        <a href="../stock_lista.php?id_bodega=<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list me-1"></i> Ver stock completo
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (!$stock): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                Esta bodega no tiene productos en stock.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary" style="font-size: 0.80rem;">
                        <tr>
                            <th class="px-3 py-2">CÓDIGO</th>
                            <th class="py-2">PRODUCTO</th>
                            <th class="py-2 text-center">UM</th>
                            <th class="py-2 text-end">STOCK</th>
                            <th class="py-2 text-end d-none d-md-table-cell">COSTO PROM.</th>
                            <th class="py-2 text-end d-none d-md-table-cell">VALOR</th>
                            <th class="py-2 text-center pe-3">ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stock as $s):
                        $alerta = '';
                        if ((float)$s['stock_actual'] <= 0) {
                            $alerta = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">Sin stock</span>';
                        } elseif ((float)$s['stock_minimo'] > 0 && (float)$s['stock_actual'] <= (float)$s['stock_minimo']) {
                            $alerta = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle">Stock bajo</span>';
                        } else {
                            $alerta = '<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">OK</span>';
                        }
                        $valor = (float)$s['stock_actual'] * (float)$s['costo_promedio'];
                    ?>
                        <tr>
                            <td class="px-3 small"><span class="badge bg-light text-dark border"><?php echo h($s['codigo']); ?></span></td>
                            <td class="small fw-medium"><?php echo h($s['nombre']); ?></td>
                            <td class="text-center small text-muted"><?php echo h($s['um_codigo']); ?></td>
                            <td class="text-end small fw-bold"><?php echo number_format((float)$s['stock_actual'], 2, ',', '.'); ?></td>
                            <td class="text-end small d-none d-md-table-cell">$<?php echo number_format((float)$s['costo_promedio'], 0, ',', '.'); ?></td>
                            <td class="text-end small d-none d-md-table-cell text-success fw-bold">$<?php echo number_format($valor, 0, ',', '.'); ?></td>
                            <td class="text-center pe-3"><?php echo $alerta; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Movimientos recientes -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-clock-history text-primary me-2"></i>Últimos movimientos
            <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2"><?php echo $totalMov; ?> totales</span>
        </h5>
        <a href="../movimientos/movimientos_lista.php?id_bodega=<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list me-1"></i> Ver todos
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (!$movimientos): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                No hay movimientos registrados.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary" style="font-size: 0.80rem;">
                        <tr>
                            <th class="px-3 py-2">FECHA</th>
                            <th class="py-2">TIPO</th>
                            <th class="py-2">PRODUCTO</th>
                            <th class="py-2 text-end">CANTIDAD</th>
                            <th class="py-2 text-end d-none d-md-table-cell">TOTAL</th>
                            <th class="py-2 d-none d-lg-table-cell pe-3">USUARIO</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movimientos as $m):
                        $ti = isset($tipoLabel[$m['tipo_movimiento']]) ? $tipoLabel[$m['tipo_movimiento']] : array($m['tipo_movimiento'], 'bg-secondary bg-opacity-10 text-secondary');
                    ?>
                        <tr>
                            <td class="px-3 small text-muted"><?php echo h(date('d-m-Y H:i', strtotime($m['fecha_movimiento']))); ?></td>
                            <td><span class="badge <?php echo $ti[1]; ?> border small"><?php echo $ti[0]; ?></span></td>
                            <td class="small">
                                <div class="fw-medium"><?php echo h($m['producto_nombre']); ?></div>
                                <div class="text-muted" style="font-size:.7rem;"><?php echo h($m['producto_codigo']); ?></div>
                            </td>
                            <td class="text-end small fw-bold"><?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?></td>
                            <td class="text-end small d-none d-md-table-cell">$<?php echo number_format((float)$m['total'], 0, ',', '.'); ?></td>
                            <td class="small text-muted d-none d-lg-table-cell pe-3"><?php echo h($m['usuario_nombre'] ? $m['usuario_nombre'] : '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$puedeDesactivar || !$puedeEliminar): ?>
<div class="alert alert-light border mt-3 small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Notas:</strong>
    <ul class="mb-0 mt-1">
        <?php if (!$puedeDesactivar && (int)$b['estado'] === 1): ?>
            <li>Para <strong>desactivar</strong> esta bodega, primero debe estar <strong>sin stock en existencia</strong>.</li>
        <?php endif; ?>
        <?php if (!$puedeEliminar && (int)$b['estado'] === 0): ?>
            <li>Para <strong>eliminar definitivamente</strong> esta bodega, debe estar inactiva y sin stock ni movimientos registrados.</li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>