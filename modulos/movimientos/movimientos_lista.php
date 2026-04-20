<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega'));

// --- Filtros ---
$buscar          = get('buscar');
$id_bodega       = get('id_bodega');
$tipo_movimiento = get('tipo_movimiento');
$fecha_desde     = get('fecha_desde');
$fecha_hasta     = get('fecha_hasta');

// ============================================================
// BLOQUEO POR ROL ENCARGADO
// ============================================================
// Encargado solo ve movimientos de SU bodega, ignora cualquier
// intento de filtrar otra bodega por URL.
if (is_encargado()) {
    $miBodegaId = user_bodega_id();
    if ($miBodegaId <= 0) {
        set_flash('error', 'Tu usuario no tiene una bodega asignada. Contacta al administrador.');
        redirect('/Bodega/index.php');
    }
    $id_bodega = (string)$miBodegaId;
}

// --- Paginación ---
$porPagina = 50;
$pagina    = max(1, (int)get('p'));
$offset    = ($pagina - 1) * $porPagina;

// Bodegas para el selector (solo admin ve todas)
if (is_encargado()) {
    $stmtB = $pdo->prepare("SELECT id, nombre FROM bodegas WHERE id = ? AND estado = 1 LIMIT 1");
    $stmtB->execute(array(user_bodega_id()));
    $bodegas  = $stmtB->fetchAll();
    $miBodega = !empty($bodegas[0]) ? $bodegas[0] : null;
} else {
    $bodegas  = $pdo->query("SELECT id, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
    $miBodega = null;
}

// --- WHERE dinámico ---
$where  = " WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $where .= " AND (
        p.codigo LIKE :buscar
        OR p.nombre LIKE :buscar
        OR b.nombre LIKE :buscar
        OR m.observacion LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

if ($id_bodega !== '') {
    $where .= " AND m.id_bodega = :id_bodega";
    $params[':id_bodega'] = (int)$id_bodega;
}

if ($tipo_movimiento !== '') {
    $where .= " AND m.tipo_movimiento = :tipo_movimiento";
    $params[':tipo_movimiento'] = $tipo_movimiento;
}

if ($fecha_desde !== '') {
    $where .= " AND DATE(m.fecha_movimiento) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if ($fecha_hasta !== '') {
    $where .= " AND DATE(m.fecha_movimiento) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

// --- KPIs (respetando filtros) ---
$sqlKpi = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN m.tipo_movimiento IN ('entrada_compra','ajuste_entrada','traslado_entrada') THEN 1 ELSE 0 END) AS entradas,
        SUM(CASE WHEN m.tipo_movimiento IN ('salida_consumo','ajuste_salida','traslado_salida')    THEN 1 ELSE 0 END) AS salidas,
        SUM(CASE WHEN m.tipo_movimiento IN ('traslado_entrada','traslado_salida')                   THEN 1 ELSE 0 END) AS traslados
    FROM movimientos_bodega m
    INNER JOIN bodegas b   ON b.id = m.id_bodega
    INNER JOIN productos p ON p.id = m.id_producto
    $where
";
$stmtKpi = $pdo->prepare($sqlKpi);
$stmtKpi->execute($params);
$kpi = $stmtKpi->fetch();

// --- Total para paginación ---
$sqlCount = "
    SELECT COUNT(*) FROM movimientos_bodega m
    INNER JOIN bodegas b   ON b.id = m.id_bodega
    INNER JOIN productos p ON p.id = m.id_producto
    $where
";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRows    = (int)$stmtCount->fetchColumn();
$totalPaginas = max(1, (int)ceil($totalRows / $porPagina));

// --- Listado principal ---
$sql = "
    SELECT
        m.*,
        b.nombre  AS bodega_nombre,
        b.codigo  AS bodega_codigo,
        p.codigo  AS producto_codigo,
        p.nombre  AS producto_nombre,
        u.nombre  AS usuario_nombre,
        um.nombre AS unidad_nombre
    FROM movimientos_bodega m
    INNER JOIN bodegas b          ON b.id  = m.id_bodega
    INNER JOIN productos p        ON p.id  = m.id_producto
    LEFT  JOIN usuarios u         ON u.id  = m.id_usuario
    LEFT  JOIN unidades_medida um ON um.id = p.id_unidad_medida
    $where
    ORDER BY m.id DESC
    LIMIT $porPagina OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

// Helper: etiqueta visual del tipo
function tipo_badge($tipo) {
    $t = (string)$tipo;
    $map = array(
        'entrada_compra'    => array('bg-success',   'bi-box-arrow-in-down', 'Entrada Compra'),
        'salida_consumo'    => array('bg-danger',    'bi-box-arrow-up',      'Salida Consumo'),
        'ajuste_entrada'    => array('bg-info',      'bi-plus-circle',       'Ajuste Entrada'),
        'ajuste_salida'     => array('bg-warning',   'bi-dash-circle',       'Ajuste Salida'),
        'traslado_entrada'  => array('bg-primary',   'bi-arrow-down-left',   'Traslado Entrada'),
        'traslado_salida'   => array('bg-secondary', 'bi-arrow-up-right',    'Traslado Salida'),
    );
    return isset($map[$t]) ? $map[$t] : array('bg-light text-dark', 'bi-question-circle', $t);
}

$pageTitle = is_encargado() ? 'Movimientos de mi Bodega' : 'Movimientos';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1 text-gray-800">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>
            <?php echo is_encargado() ? 'Movimientos de mi Bodega' : 'Historial de Movimientos'; ?>
        </h1>
        <?php if (is_encargado() && $miBodega): ?>
            <p class="text-muted mb-0 small">
                <i class="bi bi-geo-alt-fill me-1"></i>
                <?php echo h($miBodega['nombre']); ?>
                · Entradas, salidas y traslados asociados a tu bodega.
            </p>
        <?php else: ?>
            <p class="text-muted mb-0 small">Registro unificado de entradas, salidas, ajustes y traslados.</p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="movimientos_crear.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Registrar Movimiento
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-primary border-4">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Total</div>
                <div class="h4 mb-0 fw-bold"><?php echo number_format((int)$kpi['total'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm border-start border-success border-4">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Entradas</div>
                <div class="h4 mb-0 fw-bold text-success"><?php echo number_format((int)$kpi['entradas'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm border-start border-danger border-4">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Salidas</div>
                <div class="h4 mb-0 fw-bold text-danger"><?php echo number_format((int)$kpi['salidas'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm border-start border-primary border-4">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Traslados</div>
                <div class="h4 mb-0 fw-bold text-primary"><?php echo number_format((int)$kpi['traslados'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-<?php echo is_encargado() ? '4' : '3'; ?>">
                <label class="form-label text-secondary fw-bold small">Buscar</label>
                <input type="text" name="buscar" value="<?php echo h($buscar); ?>"
                       class="form-control" placeholder="Producto, código, bodega…">
            </div>

            <?php if (!is_encargado()): ?>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label text-secondary fw-bold small">Bodega</label>
                <select name="id_bodega" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>"
                            <?php echo ($id_bodega == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo h($b['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="id_bodega" value="<?php echo (int)$id_bodega; ?>">
            <?php endif; ?>

            <div class="col-12 col-sm-6 col-md-<?php echo is_encargado() ? '3' : '2'; ?>">
                <label class="form-label text-secondary fw-bold small">Tipo</label>
                <select name="tipo_movimiento" class="form-select">
                    <option value="">Todos</option>
                    <option value="entrada_compra"   <?php echo ($tipo_movimiento === 'entrada_compra')   ? 'selected' : ''; ?>>Entrada Compra</option>
                    <option value="salida_consumo"   <?php echo ($tipo_movimiento === 'salida_consumo')   ? 'selected' : ''; ?>>Salida Consumo</option>
                    <option value="ajuste_entrada"   <?php echo ($tipo_movimiento === 'ajuste_entrada')   ? 'selected' : ''; ?>>Ajuste Entrada</option>
                    <option value="ajuste_salida"    <?php echo ($tipo_movimiento === 'ajuste_salida')    ? 'selected' : ''; ?>>Ajuste Salida</option>
                    <option value="traslado_entrada" <?php echo ($tipo_movimiento === 'traslado_entrada') ? 'selected' : ''; ?>>Traslado Entrada</option>
                    <option value="traslado_salida"  <?php echo ($tipo_movimiento === 'traslado_salida')  ? 'selected' : ''; ?>>Traslado Salida</option>
                </select>
            </div>

            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label text-secondary fw-bold small">Desde</label>
                <input type="date" name="fecha_desde" value="<?php echo h($fecha_desde); ?>" class="form-control">
            </div>

            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label text-secondary fw-bold small">Hasta</label>
                <input type="date" name="fecha_hasta" value="<?php echo h($fecha_hasta); ?>" class="form-control">
            </div>

            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100" title="Filtrar">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if ($buscar !== '' || (is_admin() && $id_bodega !== '') || $tipo_movimiento !== '' || $fecha_desde !== '' || $fecha_hasta !== ''): ?>
                    <a href="movimientos_lista.php" class="btn btn-light border" title="Limpiar">
                        <i class="bi bi-eraser"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="table-light text-secondary" style="font-size: 0.78rem; letter-spacing: 0.03em;">
                    <tr>
                        <th class="px-3 py-3" style="min-width:90px;">FECHA</th>
                        <?php if (!is_encargado()): ?>
                            <th class="py-3" style="min-width:110px;">BODEGA</th>
                        <?php endif; ?>
                        <th class="py-3" style="min-width:160px;">PRODUCTO</th>
                        <th class="py-3 text-center" style="min-width:120px;">TIPO</th>
                        <th class="py-3 text-end" style="min-width:70px;">CANT.</th>
                        <th class="py-3 text-end" style="min-width:80px;">TOTAL $</th>
                        <th class="px-3 py-3 text-center" style="width:60px;">VER</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$movimientos): ?>
                    <tr>
                        <td colspan="<?php echo is_encargado() ? 6 : 7; ?>" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No se encontraron movimientos con los filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movimientos as $m):
                        $badge       = tipo_badge($m['tipo_movimiento']);
                        $esEntrada   = in_array($m['tipo_movimiento'], array('entrada_compra','ajuste_entrada','traslado_entrada'), true);
                        $esTraslado  = ($m['referencia_tipo'] === 'traslado');
                    ?>
                        <tr>
                            <td class="px-3 text-nowrap small text-secondary">
                                <?php echo date('d/m/Y', strtotime($m['fecha_movimiento'])); ?>
                                <div style="font-size:.72rem;"><?php echo date('H:i', strtotime($m['fecha_movimiento'])); ?></div>
                            </td>
                            <?php if (!is_encargado()): ?>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo h($m['bodega_codigo']); ?></span>
                                    <div class="small"><?php echo h($m['bodega_nombre']); ?></div>
                                </td>
                            <?php endif; ?>
                            <td>
                                <div class="fw-medium"><?php echo h($m['producto_nombre']); ?></div>
                                <div class="text-muted small">
                                    <span><?php echo h($m['producto_codigo']); ?></span>
                                    <?php if ($m['unidad_nombre']): ?>
                                        <span class="text-muted ms-1" style="font-size:.72rem;">
                                            <?php echo h($m['unidad_nombre']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $badge[0]; ?> bg-opacity-85"
                                      style="font-size:.72rem; white-space:normal; line-height:1.4;">
                                    <i class="bi <?php echo $badge[1]; ?> me-1"></i><?php echo h($badge[2]); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold <?php echo $esEntrada ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $esEntrada ? '+' : '−'; ?><?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end fw-medium text-dark">
                                $<?php echo number_format((float)$m['total'], 0, ',', '.'); ?>
                            </td>
                            <td class="px-3 text-center">
                                <?php if ($esTraslado): ?>
                                    <a href="movimientos_ver.php?id=<?php echo (int)$m['referencia_id']; ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Ver detalle del traslado">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <div class="text-muted small">
            Mostrando <strong><?php echo count($movimientos); ?></strong>
            de <strong><?php echo number_format($totalRows, 0, ',', '.'); ?></strong> movimientos
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $qs = $_GET;
                unset($qs['p']);
                $baseUrl = 'movimientos_lista.php?' . http_build_query($qs);
                $sep     = ($baseUrl !== 'movimientos_lista.php?') ? '&' : '';

                $prev = max(1, $pagina - 1);
                $next = min($totalPaginas, $pagina + 1);
                ?>
                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo h($baseUrl . $sep . 'p=' . $prev); ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <?php
                $rango_ini = max(1, $pagina - 2);
                $rango_fin = min($totalPaginas, $pagina + 2);
                for ($i = $rango_ini; $i <= $rango_fin; $i++):
                ?>
                    <li class="page-item <?php echo ($i === $pagina) ? 'active' : ''; ?>">
                        <a class="page-link"
                           href="<?php echo h($baseUrl . $sep . 'p=' . $i); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo h($baseUrl . $sep . 'p=' . $next); ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>