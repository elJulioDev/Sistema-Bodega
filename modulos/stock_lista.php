<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/bodegas_helpers.php';

require_login();
require_role(array('admin', 'bodega', 'solicitante'));

$buscar        = trim((string)get('buscar'));
$id_bodega     = get('id_bodega');
$filtro_alerta = get('alerta', '');

/*
|--------------------------------------------------------------------------
| Bodegas visibles según rol (M:N)
|--------------------------------------------------------------------------
| - Encargado: sus bodegas asignadas en usuarios_bodegas
| - Solicitante: todas las bodegas activas de su unidad
| - Admin: todas las bodegas activas
*/
$user          = current_user();
$uid           = (int)$user['id'];
$uniId         = (int)(isset($user['id_unidad']) ? $user['id_unidad'] : 0);
$esRestringido = (is_encargado() || is_solicitante());

$bodegasPermitidasIds = array();
$bodegas              = array();

if (is_admin()) {
    $bodegas = $pdo->query("
        SELECT id, codigo, nombre
        FROM   bodegas
        WHERE  estado = 1
        ORDER  BY (codigo='CENTRAL') DESC, nombre ASC
    ")->fetchAll();
} elseif (is_encargado()) {
    $bodegas = user_bodegas($uid);
    foreach ($bodegas as $b) { $bodegasPermitidasIds[] = (int)$b['id']; }

    if (!$bodegasPermitidasIds) {
        set_flash('error', 'No tienes bodegas asignadas. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
} else {
    // Solicitante: bodegas de su unidad
    if ($uniId <= 0) {
        set_flash('error', 'Tu usuario no tiene unidad asignada. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
    $bodegas = bodegas_de_unidad($uniId);
    foreach ($bodegas as $b) { $bodegasPermitidasIds[] = (int)$b['id']; }

    if (!$bodegasPermitidasIds) {
        set_flash('error', 'Tu unidad no tiene bodegas asignadas. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
}

/*
|--------------------------------------------------------------------------
| Validar/normalizar filtro de bodega
|--------------------------------------------------------------------------
*/
if ($esRestringido) {
    // Si pidió una bodega específica, debe estar en las permitidas
    if ($id_bodega !== '' && !in_array((int)$id_bodega, $bodegasPermitidasIds, true)) {
        $id_bodega = '';
    }
}

// Central (para default admin)
$stmtBC = $pdo->prepare("SELECT id FROM bodegas WHERE estado=1 AND codigo='CENTRAL' LIMIT 1");
$stmtBC->execute();
$idBodegaCentral = (int)$stmtBC->fetchColumn();

if (is_admin() && !isset($_GET['id_bodega']) && $idBodegaCentral > 0) {
    $id_bodega = (string)$idBodegaCentral;
}

/*
|--------------------------------------------------------------------------
| Construcción dinámica WHERE
|--------------------------------------------------------------------------
*/
$whereBase  = " p.estado = 1 ";
$paramsBase = array();

// Restringir por lista de bodegas permitidas
if ($esRestringido) {
    $phs = array();
    foreach ($bodegasPermitidasIds as $i => $bid) {
        $k = ':bp' . $i;
        $phs[] = $k;
        $paramsBase[$k] = $bid;
    }
    $whereBase .= " AND sb.id_bodega IN (" . implode(',', $phs) . ") ";
}

// Filtro por bodega específica
if ($id_bodega !== '') {
    $whereBase .= " AND sb.id_bodega = :idb ";
    $paramsBase[':idb'] = (int)$id_bodega;
}

/*
|--------------------------------------------------------------------------
| Estadísticas
|--------------------------------------------------------------------------
*/
$sqlStats = "
    SELECT
        COUNT(*) AS total_registros,
        COALESCE(SUM(sb.stock_actual), 0) AS stock_total,
        COALESCE(SUM(sb.stock_actual * sb.costo_promedio), 0) AS valor_total,
        SUM(CASE WHEN sb.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
        SUM(CASE WHEN p.stock_minimo > 0 AND sb.stock_actual > 0 AND sb.stock_actual <= p.stock_minimo THEN 1 ELSE 0 END) AS stock_bajo
    FROM   stock_bodega sb
    INNER  JOIN productos p ON p.id = sb.id_producto
    WHERE  $whereBase
";
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($paramsBase);
$stats = $stmtStats->fetch();

/*
|--------------------------------------------------------------------------
| Consulta principal
|--------------------------------------------------------------------------
*/
$paramsList = $paramsBase;
$whereList  = $whereBase;

if ($buscar !== '') {
    $whereList .= " AND (p.codigo LIKE :buscar OR p.nombre LIKE :buscar OR b.nombre LIKE :buscar) ";
    $paramsList[':buscar'] = '%' . $buscar . '%';
}

if ($filtro_alerta === 'bajo') {
    $whereList .= " AND p.stock_minimo > 0 AND sb.stock_actual <= p.stock_minimo ";
} elseif ($filtro_alerta === 'sin') {
    $whereList .= " AND sb.stock_actual <= 0 ";
}

$sql = "
    SELECT sb.*,
           b.nombre  AS bodega_nombre,
           b.codigo  AS bodega_codigo,
           p.codigo  AS producto_codigo,
           p.nombre  AS producto_nombre,
           p.stock_minimo,
           p.activo_fijo,
           um.nombre AS unidad_nombre,
           um.codigo AS unidad_codigo
    FROM   stock_bodega sb
    INNER  JOIN bodegas b       ON b.id  = sb.id_bodega
    INNER  JOIN productos p     ON p.id  = sb.id_producto
    LEFT   JOIN unidades_medida um ON um.id = p.id_unidad_medida
    WHERE  $whereList
    ORDER  BY (b.codigo='CENTRAL') DESC, b.nombre ASC, p.nombre ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsList);
$stocks = $stmt->fetchAll();

$canOperate = has_role(array('admin', 'bodega'));

/*
|--------------------------------------------------------------------------
| Título / subtítulo
|--------------------------------------------------------------------------
*/
if (is_encargado())        $pageTitle = 'Stock de mis Bodegas';
elseif (is_solicitante())  $pageTitle = 'Stock de mi Unidad';
else                       $pageTitle = 'Control de Stock';

require_once __DIR__ . '/../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold">
            <i class="bi bi-inboxes text-primary me-2"></i><?php echo h($pageTitle); ?>
        </h1>
        <?php if (is_encargado()): ?>
            <small class="text-muted">
                <i class="bi bi-geo-alt-fill me-1"></i>
                <?php echo count($bodegasPermitidasIds); ?> bodega<?php echo count($bodegasPermitidasIds)===1?'':'s'; ?> a tu cargo
            </small>
        <?php elseif (is_solicitante()): ?>
            <small class="text-muted">
                <i class="bi bi-diagram-3 me-1"></i>
                Bodegas de tu unidad (<?php echo count($bodegasPermitidasIds); ?>)
            </small>
        <?php else: ?>
            <small class="text-muted">Stock actual por bodega y producto</small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (is_admin()): ?>
            <a href="facturas/facturas_crear.php" class="btn btn-sm btn-success">
                <i class="bi bi-receipt me-1"></i> Ingresar Factura
            </a>
        <?php endif; ?>
        <?php if ($canOperate): ?>
            <a href="movimientos/movimientos_crear.php" class="btn btn-sm btn-primary">
                <i class="bi bi-arrow-left-right me-1"></i>
                <?php echo is_encargado() ? 'Nuevo Traslado' : 'Registrar Movimiento'; ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPIs -->
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Productos en Stock</div>
                <div class="h4 mb-0 fw-bold"><?php echo (int)$stats['total_registros']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Unidades Totales</div>
                <div class="h4 mb-0 fw-bold text-success"><?php echo number_format((float)$stats['stock_total'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Stock Bajo</div>
                <div class="h4 mb-0 fw-bold text-warning"><?php echo (int)$stats['stock_bajo']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body py-2 px-3">
                <div class="text-muted small text-uppercase fw-semibold">Valor stock</div>
                <div class="h4 mb-0 fw-bold text-info">$<?php echo number_format((float)$stats['valor_total'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2 px-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-<?php echo (count($bodegas) > 1) ? '4' : '6'; ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="buscar" value="<?php echo h($buscar); ?>" class="form-control border-start-0 ps-0" placeholder="Buscar por código o nombre...">
                </div>
            </div>

            <?php if (count($bodegas) > 1): ?>
            <div class="col-md-3">
                <select name="id_bodega" class="form-select form-select-sm">
                    <option value="">Todas mis bodegas</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>" <?php echo ((string)$id_bodega === (string)$b['id']) ? 'selected' : ''; ?>>
                            <?php echo h($b['nombre']); ?><?php echo ($b['codigo'] === 'CENTRAL') ? ' ★' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php elseif (count($bodegas) === 1): ?>
                <input type="hidden" name="id_bodega" value="<?php echo (int)$bodegas[0]['id']; ?>">
            <?php endif; ?>

            <div class="col-md-3">
                <select name="alerta" class="form-select form-select-sm">
                    <option value="">Sin filtro de alerta</option>
                    <option value="bajo" <?php echo $filtro_alerta === 'bajo' ? 'selected' : ''; ?>>⚠ Stock bajo mínimo</option>
                    <option value="sin"  <?php echo $filtro_alerta === 'sin'  ? 'selected' : ''; ?>>✗ Sin stock</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                <?php if ($buscar !== '' || $id_bodega !== '' || $filtro_alerta !== ''): ?>
                    <a href="stock_lista.php" class="btn btn-sm btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="table-light text-secondary" style="font-size: 0.75rem;">
                    <tr>
                        <th class="px-3 py-2">BODEGA</th>
                        <th class="py-2">PRODUCTO</th>
                        <th class="py-2 text-center">UNIDAD</th>
                        <th class="py-2 text-end">STOCK</th>
                        <th class="py-2 text-end">MÍN.</th>
                        <th class="py-2 text-end">C. PROMEDIO</th>
                        <th class="py-2 text-end">VALOR</th>
                        <th class="py-2 text-center">ÚLTIMA ACT.</th>
                        <?php if ($canOperate): ?>
                            <th class="px-3 py-2 text-center">ACCIONES</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$stocks): ?>
                    <?php $colspan = 8; if ($canOperate) $colspan++; ?>
                    <tr>
                        <td colspan="<?php echo $colspan; ?>" class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No hay stock registrado con los filtros aplicados.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stocks as $s):
                        $stock     = (float)$s['stock_actual'];
                        $minimo    = (float)$s['stock_minimo'];
                        $sinStock  = ($stock <= 0);
                        $bajoMin   = ($minimo > 0 && $stock > 0 && $stock <= $minimo);
                        $valor     = $stock * (float)$s['costo_promedio'];
                        $esCentral = ($s['bodega_codigo'] === 'CENTRAL');

                        $rowClass = '';
                        if ($sinStock)     $rowClass = 'table-danger';
                        elseif ($bajoMin)  $rowClass = 'table-warning';
                    ?>
                        <tr<?php echo $rowClass ? ' class="' . $rowClass . '"' : ''; ?>>
                            <td class="px-3">
                                <div class="fw-semibold small"><?php echo h($s['bodega_nombre']); ?></div>
                                <small class="text-muted">
                                    <?php echo h($s['bodega_codigo']); ?>
                                    <?php if ($esCentral): ?> <span class="text-warning">★</span><?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <div class="fw-medium small"><?php echo h($s['producto_nombre']); ?></div>
                                <small class="text-muted"><?php echo h($s['producto_codigo']); ?></small>
                            </td>
                            <td class="text-center text-secondary small">
                                <?php echo h($s['unidad_codigo'] ? $s['unidad_codigo'] : '—'); ?>
                            </td>
                            <td class="text-end fw-bold <?php echo $sinStock ? 'text-danger' : ($bajoMin ? 'text-warning' : ''); ?>">
                                <?php echo number_format($stock, 2, ',', '.'); ?>
                            </td>
                            <td class="text-end text-secondary small">
                                <?php echo $minimo > 0 ? number_format($minimo, 2, ',', '.') : '—'; ?>
                            </td>
                            <td class="text-end text-secondary small">
                                $<?php echo number_format((float)$s['costo_promedio'], 0, ',', '.'); ?>
                            </td>
                            <td class="text-end fw-semibold small">
                                $<?php echo number_format($valor, 0, ',', '.'); ?>
                            </td>
                            <td class="text-center text-muted small">
                                <?php echo date('d/m/Y', strtotime($s['updated_at'])); ?>
                                <br><small><?php echo date('H:i', strtotime($s['updated_at'])); ?></small>
                            </td>
                            <?php if ($canOperate): ?>
                                <td class="px-3 text-center">
                                    <?php
                                    // Encargado: solo puede operar si la bodega está en sus permisos
                                    $puedoOperarFila = is_admin() || in_array((int)$s['id_bodega'], $bodegasPermitidasIds, true);
                                    ?>
                                    <?php if ($puedoOperarFila): ?>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (is_admin()): ?>
                                            <a href="movimientos/movimientos_crear.php?tipo=salida_consumo&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                               class="btn btn-outline-danger" title="Salida por consumo">
                                                <i class="bi bi-box-arrow-right"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="movimientos/movimientos_crear.php?tipo=traslado&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                           class="btn btn-outline-primary" title="Trasladar a otra bodega">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </a>
                                        <?php if (is_admin()): ?>
                                            <a href="movimientos/movimientos_crear.php?tipo=ajuste_entrada&id_bodega=<?php echo (int)$s['id_bodega']; ?>&id_producto=<?php echo (int)$s['id_producto']; ?>"
                                               class="btn btn-outline-success" title="Ajuste de entrada">
                                                <i class="bi bi-plus-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($stocks): ?>
            <div class="card-footer bg-white py-2 px-3 border-top">
                <small class="text-muted">
                    Mostrando <strong><?php echo count($stocks); ?></strong> registro(s).
                    Filas <span class="badge bg-warning bg-opacity-25 text-warning">amarillas</span> = stock bajo mínimo.
                    Filas <span class="badge bg-danger bg-opacity-25 text-danger">rojas</span> = sin stock.
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>