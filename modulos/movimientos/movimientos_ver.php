<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role(array('admin', 'bodega'));

$id = (int)get('id');

if ($id <= 0) {
    die('ID de traspaso inválido.');
}

// --- Cabecera del traspaso ---
$sql = "
    SELECT
        t.*,
        bo.codigo AS bodega_origen_codigo,
        bo.nombre AS bodega_origen_nombre,
        bd.codigo AS bodega_destino_codigo,
        bd.nombre AS bodega_destino_nombre
    FROM traspasos_bodega t
    INNER JOIN bodegas bo ON bo.id = t.id_bodega_origen
    INNER JOIN bodegas bd ON bd.id = t.id_bodega_destino
    WHERE t.id = ?
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($id));
$traspaso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$traspaso) {
    die('Traspaso no encontrado.');
}

// --- Usuario creador ---
$creadoPor = 'No registrado';
if (!empty($traspaso['created_by'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ? LIMIT 1");
        $stmtUser->execute(array((int)$traspaso['created_by']));
        $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($u && !empty($u['nombre'])) {
            $creadoPor = $u['nombre'];
        } else {
            $creadoPor = 'Usuario ID ' . (int)$traspaso['created_by'];
        }
    } catch (Exception $e) {
        $creadoPor = 'Usuario ID ' . (int)$traspaso['created_by'];
    }
}

// --- Detalle de productos traspasados ---
$sqlDetalle = "
    SELECT
        td.*,
        p.codigo AS producto_codigo,
        p.nombre AS producto_nombre,
        um.nombre AS unidad_nombre
    FROM traspasos_bodega_detalle td
    LEFT JOIN productos p  ON p.id  = td.id_producto
    LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
    WHERE td.id_traspaso = ?
    ORDER BY td.id ASC
";
$stmtDetalle = $pdo->prepare($sqlDetalle);
$stmtDetalle->execute(array($id));
$detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

// --- Stock actual bodega origen y destino ---
$stockActual = array();
if ($detalle) {
    $ids = array();
    foreach ($detalle as $d) { $ids[] = (int)$d['id_producto']; }
    $ids = array_unique($ids);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmtStock = $pdo->prepare("
            SELECT id_bodega, id_producto, stock_actual
            FROM stock_bodega
            WHERE id_producto IN ($ph)
              AND id_bodega IN (?, ?)
        ");
        $args = array_merge($ids, array((int)$traspaso['id_bodega_origen'], (int)$traspaso['id_bodega_destino']));
        $stmtStock->execute($args);
        foreach ($stmtStock->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $stockActual[$r['id_bodega'] . '_' . $r['id_producto']] = (float)$r['stock_actual'];
        }
    }
}

// --- Totales ---
$totalTraspaso = 0;
$totalItems    = 0;
$totalCantidad = 0;
foreach ($detalle as $d) {
    $totalTraspaso += (float)$d['subtotal'];
    $totalCantidad += (float)$d['cantidad'];
    $totalItems++;
}

$pageTitle = 'Traslado #' . (int)$traspaso['id'];
require_once __DIR__ . '/../../inc/header.php';

function estado_badge_ver($estado) {
    $map = array(
        'completado' => array('bg-success',             'bi-check-circle-fill'),
        'pendiente'  => array('bg-warning text-dark',   'bi-hourglass-split'),
        'anulado'    => array('bg-danger',               'bi-x-circle-fill'),
    );
    $d   = isset($map[$estado]) ? $map[$estado] : array('bg-secondary', 'bi-circle');
    return '<span class="badge ' . $d[0] . ' text-uppercase"><i class="bi ' . $d[1] . ' me-1"></i>' . h($estado) . '</span>';
}
?>

<?php /* ===================== ESTILOS PANTALLA + IMPRESIÓN ===================== */ ?>
<style>
/* ---- Impresión ---- */
@media print {
    @page { size: portrait; margin: 12mm 14mm; }

    /* Ocultar layout del sistema */
    .sidebar,
    .topbar,
    .d-print-none,
    .alert { display: none !important; }

    /* Liberar el scroll del layout */
    body, .layout-wrapper, .main-content,
    .content-scrollable { overflow: visible !important; height: auto !important; }

    /* Quitar cards decorativas */
    .card {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        margin-bottom: 12pt !important;
        background: transparent !important;
        padding: 0 !important;
    }
    .card-header, .card-body { padding: 0 !important; background: transparent !important; }

    /* ---- Encabezado del documento impreso ---- */
    .print-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        border: 2pt solid #000 !important;
        padding: 8pt 12pt !important;
        margin-bottom: 10pt !important;
    }
    .print-header-left h2 { font-size: 14pt !important; margin: 0 0 4pt !important; font-weight: bold !important; }
    .print-header-left p  { font-size: 9pt  !important; margin: 1pt 0 !important; color: #000 !important; }
    .print-logo-img { max-height: 55pt !important; max-width: 80pt !important; object-fit: contain !important; }

    /* ---- Bloque origen → destino ---- */
    .print-ruta {
        display: flex !important;
        gap: 0 !important;
        border: 1pt solid #000 !important;
        margin-bottom: 10pt !important;
    }
    .print-ruta-box {
        flex: 1 !important;
        padding: 6pt 10pt !important;
        border-right: 1pt solid #000 !important;
    }
    .print-ruta-box:last-child { border-right: none !important; }
    .print-ruta-box strong { display: block !important; font-size: 7pt !important; text-transform: uppercase !important; color: #555 !important; margin-bottom: 2pt !important; letter-spacing: .5pt !important; }
    .print-ruta-box span   { font-size: 10pt !important; font-weight: bold !important; color: #000 !important; }
    .print-ruta-arrow { display: flex !important; align-items: center !important; justify-content: center !important; padding: 0 10pt !important; font-size: 16pt !important; font-weight: bold !important; color: #000 !important; border-right: 1pt solid #000 !important; }

    /* ---- Ocultar secciones de pantalla que no van al impreso ---- */
    .screen-only, .kpi-row, .info-cards-row { display: none !important; }

    /* ---- Título de sección en impresión ---- */
    .print-section-title {
        display: block !important;
        font-size: 9pt !important;
        font-weight: bold !important;
        text-transform: uppercase !important;
        letter-spacing: .5pt !important;
        margin-bottom: 4pt !important;
        border-bottom: 1pt solid #000 !important;
        padding-bottom: 2pt !important;
    }

    /* ---- Tabla de detalle ---- */
    .table-wrap { overflow: visible !important; }
    table.print-table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        border: 1pt solid #000 !important;
        table-layout: fixed !important;
        font-size: 8pt !important;
    }
    table.print-table th,
    table.print-table td {
        border: 1pt solid #000 !important;
        padding: 3pt 5pt !important;
        color: #000 !important;
        background: #fff !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        vertical-align: middle !important;
        font-size: 8pt !important;
    }
    table.print-table th {
        background: #e5e7eb !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    table.print-table tfoot td,
    table.print-table tfoot th {
        background: #f3f4f6 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Anchos columna detalle de productos (7 cols) */
    table.print-table.tbl-detalle th:nth-child(1),
    table.print-table.tbl-detalle td:nth-child(1) { width: 5%;  text-align: center !important; }
    table.print-table.tbl-detalle th:nth-child(2),
    table.print-table.tbl-detalle td:nth-child(2) { width: 13%; }
    table.print-table.tbl-detalle th:nth-child(3),
    table.print-table.tbl-detalle td:nth-child(3) { width: 32%; }
    table.print-table.tbl-detalle th:nth-child(4),
    table.print-table.tbl-detalle td:nth-child(4) { width: 9%;  text-align: right !important; }
    table.print-table.tbl-detalle th:nth-child(5),
    table.print-table.tbl-detalle td:nth-child(5) { width: 13%; text-align: right !important; }
    table.print-table.tbl-detalle th:nth-child(6),
    table.print-table.tbl-detalle td:nth-child(6) { width: 14%; text-align: right !important; }
    table.print-table.tbl-detalle th:nth-child(7),
    table.print-table.tbl-detalle td:nth-child(7) { width: 14%; text-align: right !important; }

    /* Quitar colores/formas de badges */
    .badge {
        background: transparent !important;
        color: #000 !important;
        padding: 0 !important;
        border: none !important;
        font-weight: bold !important;
        font-size: 8pt !important;
    }

    /* Pie de firma */
    .print-firmas {
        display: flex !important;
        gap: 20pt !important;
        margin-top: 20pt !important;
        page-break-inside: avoid !important;
    }
    .print-firma-box {
        flex: 1 !important;
        border-top: 1pt solid #000 !important;
        padding-top: 4pt !important;
        text-align: center !important;
        font-size: 8pt !important;
    }
    .print-pie {
        display: block !important;
        margin-top: 14pt !important;
        font-size: 7pt !important;
        color: #555 !important;
        text-align: right !important;
        border-top: .5pt solid #ccc !important;
        padding-top: 4pt !important;
    }
}

/* ---- Pantalla: ocultar elementos exclusivos de impresión ---- */
@media screen {
    .print-header,
    .print-ruta,
    .print-section-title,
    .print-firmas,
    .print-pie { display: none; }
}
</style>

<?php /* ======================== BREADCRUMB + BOTONES ======================== */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3 d-print-none">
    <div>
        <div class="text-muted small mb-1">
            <a href="movimientos_lista.php" class="text-decoration-none text-muted">
                <i class="bi bi-chevron-left"></i> Movimientos
            </a>
            <span class="mx-1">/</span>
            <span>Traslado #<?php echo (int)$traspaso['id']; ?></span>
        </div>
        <h1 class="h3 mb-1 text-gray-800">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>Traslado
            <span class="fw-light">#<?php echo (int)$traspaso['id']; ?></span>
            <span class="ms-2"><?php echo estado_badge_ver($traspaso['estado']); ?></span>
        </h1>
        <div class="text-muted small">
            <i class="bi bi-calendar3 me-1"></i>
            <?php echo h(date('d/m/Y', strtotime($traspaso['fecha']))); ?>
            &nbsp;·&nbsp;
            <i class="bi bi-person me-1"></i>
            <?php echo h($creadoPor); ?>
            <?php if (!empty($traspaso['created_at'])): ?>
                &nbsp;·&nbsp;
                <i class="bi bi-clock me-1"></i>
                <?php echo h(date('d/m/Y H:i', strtotime($traspaso['created_at']))); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="movimientos_lista.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
        <button type="button" class="btn btn-success btn-sm" onclick="window.print();">
            <i class="bi bi-printer-fill me-1"></i> Imprimir / PDF
        </button>
    </div>
</div>

<?php /* ========== ENCABEZADO EXCLUSIVO PARA IMPRESIÓN ========== */ ?>
<div class="print-header">
    <div class="print-header-left">
        <h2>Comprobante de Traslado entre Bodegas</h2>
        <p><strong>N° Traslado:</strong> #<?php echo (int)$traspaso['id']; ?> &nbsp;|&nbsp;
           <strong>Fecha:</strong> <?php echo h(date('d/m/Y', strtotime($traspaso['fecha']))); ?> &nbsp;|&nbsp;
           <strong>Estado:</strong> <?php echo strtoupper(h($traspaso['estado'])); ?></p>
        <p><strong>Creado por:</strong> <?php echo h($creadoPor); ?> &nbsp;|&nbsp;
           <strong>Generado el:</strong> <?php echo date('d/m/Y H:i'); ?></p>
    </div>
    <img src="<?php echo h(rtrim(dirname(dirname(dirname($_SERVER['PHP_SELF']))), '/') . '/static/img/logo.png'); ?>"
         class="print-logo-img" alt="Logo">
</div>

<?php /* ========== RUTA ORIGEN → DESTINO (SOLO IMPRESIÓN) ========== */ ?>
<div class="print-ruta">
    <div class="print-ruta-box">
        <strong>Bodega Origen</strong>
        <span><?php echo h($traspaso['bodega_origen_nombre']); ?></span>
        <div style="font-size:7pt;color:#555;">Cód: <?php echo h($traspaso['bodega_origen_codigo']); ?></div>
    </div>
    <div class="print-ruta-arrow">→</div>
    <div class="print-ruta-box">
        <strong>Bodega Destino</strong>
        <span><?php echo h($traspaso['bodega_destino_nombre']); ?></span>
        <div style="font-size:7pt;color:#555;">Cód: <?php echo h($traspaso['bodega_destino_codigo']); ?></div>
    </div>
    <div class="print-ruta-box" style="flex:0 0 auto; min-width:120pt;">
        <strong>Ítems trasladados</strong>
        <span><?php echo $totalItems; ?> producto<?php echo $totalItems !== 1 ? 's' : ''; ?></span>
        <div style="font-size:7pt;color:#555;">Cant. total: <?php echo number_format($totalCantidad, 2, ',', '.'); ?></div>
    </div>
    <div class="print-ruta-box" style="flex:0 0 auto; min-width:120pt; border-right:none;">
        <strong>Valor total traslado</strong>
        <span>$ <?php echo number_format($totalTraspaso, 0, ',', '.'); ?></span>
        <?php if (!empty($traspaso['observacion'])): ?>
            <div style="font-size:7pt;color:#555;">Obs: <?php echo h(mb_substr($traspaso['observacion'], 0, 60)); ?><?php echo mb_strlen($traspaso['observacion']) > 60 ? '…' : ''; ?></div>
        <?php endif; ?>
    </div>
</div>

<?php /* ======================== PANTALLA: VISUALIZACIÓN ORIGEN → DESTINO ======================== */ ?>
<div class="card shadow-sm border-0 mb-4 screen-only">
    <div class="card-body p-4">
        <div class="row align-items-center text-center g-3">
            <div class="col-md-5">
                <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size:.7rem;letter-spacing:1px;">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Bodega Origen
                </div>
                <div class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25">
                    <div class="fs-5 fw-bold text-danger"><?php echo h($traspaso['bodega_origen_nombre']); ?></div>
                    <div class="text-muted small">Cód: <?php echo h($traspaso['bodega_origen_codigo']); ?></div>
                </div>
            </div>
            <div class="col-md-2 d-flex flex-column align-items-center text-primary">
                <i class="bi bi-arrow-right-circle-fill" style="font-size:2.5rem;"></i>
                <div class="small fw-bold mt-2"><?php echo $totalItems; ?> ítem<?php echo $totalItems !== 1 ? 's' : ''; ?></div>
            </div>
            <div class="col-md-5">
                <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size:.7rem;letter-spacing:1px;">
                    <i class="bi bi-box-arrow-in-down-left me-1"></i>Bodega Destino
                </div>
                <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                    <div class="fs-5 fw-bold text-success"><?php echo h($traspaso['bodega_destino_nombre']); ?></div>
                    <div class="text-muted small">Cód: <?php echo h($traspaso['bodega_destino_codigo']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php /* ======================== KPIs ======================== */ ?>
<div class="row g-3 mb-4 kpi-row d-print-none">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Ítems</div>
                <div class="h4 mb-0 fw-bold"><?php echo number_format($totalItems, 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Cantidad Total</div>
                <div class="h4 mb-0 fw-bold text-primary"><?php echo number_format($totalCantidad, 2, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Valor Total</div>
                <div class="h4 mb-0 fw-bold text-success">$<?php echo number_format($totalTraspaso, 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase fw-bold" style="font-size:.7rem;">Estado</div>
                <div class="mt-1"><?php echo estado_badge_ver($traspaso['estado']); ?></div>
            </div>
        </div>
    </div>
</div>

<?php /* ======================== INFO + OBSERVACIÓN (PANTALLA) ======================== */ ?>
<div class="row g-4 mb-4 info-cards-row d-print-none">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="mb-0 fw-bold text-secondary text-uppercase" style="font-size:.78rem;letter-spacing:.5px;">
                    <i class="bi bi-info-circle me-1"></i>Información del Traslado
                </h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">ID Traslado:</dt>
                    <dd class="col-7 fw-bold">#<?php echo (int)$traspaso['id']; ?></dd>

                    <dt class="col-5 text-muted fw-normal">Fecha:</dt>
                    <dd class="col-7"><?php echo h(date('d/m/Y', strtotime($traspaso['fecha']))); ?></dd>

                    <dt class="col-5 text-muted fw-normal">Estado:</dt>
                    <dd class="col-7"><?php echo estado_badge_ver($traspaso['estado']); ?></dd>

                    <dt class="col-5 text-muted fw-normal">Creado por:</dt>
                    <dd class="col-7">
                        <i class="bi bi-person-circle me-1 text-muted"></i><?php echo h($creadoPor); ?>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Fecha creación:</dt>
                    <dd class="col-7"><?php echo h(date('d/m/Y H:i', strtotime($traspaso['created_at']))); ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="mb-0 fw-bold text-secondary text-uppercase" style="font-size:.78rem;letter-spacing:.5px;">
                    <i class="bi bi-chat-left-text me-1"></i>Observación
                </h6>
            </div>
            <div class="card-body">
                <?php if (trim((string)$traspaso['observacion']) !== ''): ?>
                    <div class="small"><?php echo nl2br(h($traspaso['observacion'])); ?></div>
                <?php else: ?>
                    <p class="text-muted fst-italic small mb-0">Sin observación registrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php /* ======================== TABLA DETALLE ======================== */ ?>

<?php /* Título solo impresión */ ?>
<span class="print-section-title">Detalle de Productos Trasladados</span>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center d-print-none">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-box-seam me-2 text-primary"></i>Productos Trasladados
        </h5>
        <span class="badge bg-light text-dark border"><?php echo $totalItems; ?> registros</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive table-wrap">
            <table class="table table-hover align-middle mb-0 print-table tbl-detalle">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th class="px-3 text-center" style="width:4%;">#</th>
                        <th style="width:12%;">Código</th>
                        <th>Producto</th>
                        <th class="text-end" style="width:9%;">Cant.</th>
                        <th class="text-end" style="width:12%;">Costo Unit.</th>
                        <th class="text-end" style="width:13%;">Subtotal</th>
                        <th class="text-end" style="width:12%;">Stock Actual</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$detalle): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>No hay detalle registrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $n = 1; foreach ($detalle as $d):
                        $kD = (int)$traspaso['id_bodega_destino'] . '_' . (int)$d['id_producto'];
                        $stockDest = isset($stockActual[$kD]) ? $stockActual[$kD] : null;
                    ?>
                        <tr>
                            <td class="px-3 text-center text-muted small"><?php echo $n; ?></td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:.75rem;">
                                    <?php echo h($d['producto_codigo']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><?php echo h($d['producto_nombre']); ?></div>
                                <?php if (!empty($d['unidad_nombre'])): ?>
                                    <div class="text-muted small mt-1" style="font-size:.75rem;">
                                        <i class="bi bi-ruler me-1"></i><?php echo h($d['unidad_nombre']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($d['descripcion_item'])): ?>
                                    <div class="text-muted small mt-1" style="font-size:.73rem; font-style:italic;">
                                        <?php echo h($d['descripcion_item']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-primary">
                                <?php echo number_format((float)$d['cantidad'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end text-muted small">
                                $ <?php echo number_format((float)$d['costo_unitario'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end fw-medium">
                                $ <?php echo number_format((float)$d['subtotal'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end">
                                <?php if ($stockDest !== null): ?>
                                    <span class="fw-medium <?php echo $stockDest > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($stockDest, 2, ',', '.'); ?>
                                    </span>
                                    <div class="text-muted small d-print-none" style="font-size:.72rem;">en destino</div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php $n++; endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($detalle): ?>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end text-muted small text-uppercase" style="font-size:.78rem;">
                            Total
                        </th>
                        <th class="text-end text-primary">
                            <?php echo number_format($totalCantidad, 2, ',', '.'); ?>
                        </th>
                        <th></th>
                        <th class="text-end text-success fw-bold">
                            $ <?php echo number_format($totalTraspaso, 2, ',', '.'); ?>
                        </th>
                        <th></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <div class="px-3 py-2 small text-muted bg-light border-top d-print-none">
            <i class="bi bi-info-circle me-1"></i>
            El stock mostrado es el <strong>actual</strong> en bodega destino (posterior al traslado).
        </div>
    </div>
</div>

<?php /* ======================== FIRMAS (SOLO IMPRESIÓN) ======================== */ ?>
<div class="print-firmas">
    <div class="print-firma-box">
        Responsable Bodega Origen<br>
        <strong><?php echo h($traspaso['bodega_origen_nombre']); ?></strong>
    </div>
    <div class="print-firma-box">
        Responsable Bodega Destino<br>
        <strong><?php echo h($traspaso['bodega_destino_nombre']); ?></strong>
    </div>
    <div class="print-firma-box">
        Autorizado por<br>
        <strong>&nbsp;</strong>
    </div>
</div>

<span class="print-pie">
    Impreso el <?php echo date('d/m/Y \a \l\a\s H:i'); ?> &nbsp;·&nbsp;
    Sistema de Bodega &nbsp;·&nbsp; Traslado #<?php echo (int)$traspaso['id']; ?>
</span>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>