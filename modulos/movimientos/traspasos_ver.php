<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');

if ($id <= 0) {
    die('ID de traspaso inválido.');
}

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

$creadoPor = 'No registrado';

/*
|--------------------------------------------------------------------------
| Ajusta esta parte si tu tabla de usuarios tiene otro nombre
|--------------------------------------------------------------------------
*/
if (!empty($traspaso['created_by'])) {
    try {
        $stmtUser = $pdo->prepare("
            SELECT id, nombre
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");
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

$sqlDetalle = "
    SELECT
        td.*,
        p.codigo AS producto_codigo,
        p.nombre AS producto_nombre
    FROM traspasos_bodega_detalle td
    LEFT JOIN productos p ON p.id = td.id_producto
    WHERE td.id_traspaso = ?
    ORDER BY td.id ASC
";
$stmtDetalle = $pdo->prepare($sqlDetalle);
$stmtDetalle->execute(array($id));
$detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Movimientos asociados
|--------------------------------------------------------------------------
| Más flexible: trae por referencia_id y ordena.
*/
$sqlMov = "
    SELECT
        mb.*,
        p.codigo AS producto_codigo,
        p.nombre AS producto_nombre,
        b.codigo AS bodega_codigo,
        b.nombre AS bodega_nombre
    FROM movimientos_bodega mb
    LEFT JOIN productos p ON p.id = mb.id_producto
    LEFT JOIN bodegas b ON b.id = mb.id_bodega
    WHERE mb.referencia_id = ?
    ORDER BY mb.id ASC
";
$stmtMov = $pdo->prepare($sqlMov);
$stmtMov->execute(array($id));
$movimientos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

$totalTraspaso = 0;
foreach ($detalle as $d) {
    $totalTraspaso += (float)$d['subtotal'];
}

$pageTitle = 'Ver Traspaso';
require_once __DIR__ . '/../../inc/header.php';

function traspaso_tipo_label($tipo)
{
    switch ((string)$tipo) {
        case 'traspaso_salida':
            return '<span class="badge bg-danger">Salida</span>';
        case 'traspaso_entrada':
            return '<span class="badge bg-success">Entrada</span>';
        default:
            return '<span class="badge bg-secondary">' . h($tipo) . '</span>';
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>Traspaso #<?php echo (int)$traspaso['id']; ?>
        </h1>
        <div class="text-muted">Fecha: <?php echo h($traspaso['fecha']); ?></div>
    </div>

    <div class="d-flex gap-2">
        <a href="traspasos_lista.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
        <button type="button" class="btn btn-outline-primary" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h5 class="mb-0 fw-bold">Bodega Origen</h5>
            </div>
            <div class="card-body">
                <div class="fs-5 fw-semibold"><?php echo h($traspaso['bodega_origen_nombre']); ?></div>
                <div class="text-muted">Código: <?php echo h($traspaso['bodega_origen_codigo']); ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h5 class="mb-0 fw-bold">Bodega Destino</h5>
            </div>
            <div class="card-body">
                <div class="fs-5 fw-semibold"><?php echo h($traspaso['bodega_destino_nombre']); ?></div>
                <div class="text-muted">Código: <?php echo h($traspaso['bodega_destino_codigo']); ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h5 class="mb-0 fw-bold">Resumen</h5>
            </div>
            <div class="card-body">
                <div class="mb-2"><span class="text-muted">Estado:</span> <strong><?php echo h($traspaso['estado']); ?></strong></div>
                <div class="mb-2"><span class="text-muted">Creado por:</span> <strong><?php echo h($creadoPor); ?></strong></div>
                <div class="mb-2"><span class="text-muted">Creado el:</span> <strong><?php echo h($traspaso['created_at']); ?></strong></div>
                <div><span class="text-muted">Total:</span> <strong class="text-primary">$ <?php echo number_format($totalTraspaso, 2, ',', '.'); ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-bold">Observación</h5>
    </div>
    <div class="card-body">
        <?php if (trim((string)$traspaso['observacion']) !== ''): ?>
            <div><?php echo nl2br(h($traspaso['observacion'])); ?></div>
        <?php else: ?>
            <div class="text-muted">Sin observación.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-bold">Detalle del Traspaso</h5>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%;">#</th>
                        <th style="width: 22%;">Código</th>
                        <th>Producto</th>
                        <th style="width: 12%;" class="text-end">Cantidad</th>
                        <th style="width: 14%;" class="text-end">Costo Unitario</th>
                        <th style="width: 14%;" class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$detalle): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No hay detalle registrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php $n = 1; ?>
                        <?php foreach ($detalle as $d): ?>
                            <tr>
                                <td><?php echo $n; ?></td>
                                <td><?php echo h($d['producto_codigo'] ? $d['producto_codigo'] : '-'); ?></td>
                                <td><?php echo h($d['producto_nombre'] ? $d['producto_nombre'] : ($d['descripcion_item'] ? $d['descripcion_item'] : 'Producto no disponible')); ?></td>
                                <td class="text-end"><?php echo number_format((float)$d['cantidad'], 2, ',', '.'); ?></td>
                                <td class="text-end">$ <?php echo number_format((float)$d['costo_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-end">$ <?php echo number_format((float)$d['subtotal'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php $n++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if ($detalle): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Total</th>
                            <th class="text-end text-primary">$ <?php echo number_format($totalTraspaso, 2, ',', '.'); ?></th>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-bold">Movimientos Generados</h5>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%;">ID</th>
                        <th style="width: 14%;">Tipo</th>
                        <th style="width: 18%;">Bodega</th>
                        <th>Producto</th>
                        <th style="width: 10%;" class="text-end">Cantidad</th>
                        <th style="width: 12%;" class="text-end">Precio</th>
                        <th style="width: 12%;" class="text-end">Total</th>
                        <th style="width: 16%;">Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$movimientos): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay movimientos asociados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td><?php echo (int)$m['id']; ?></td>
                                <td><?php echo traspaso_tipo_label($m['tipo_movimiento']); ?></td>
                                <td><?php echo h(($m['bodega_nombre'] ? $m['bodega_nombre'] : '-') . ($m['bodega_codigo'] ? ' (' . $m['bodega_codigo'] . ')' : '')); ?></td>
                                <td><?php echo h(($m['producto_codigo'] ? $m['producto_codigo'] . ' - ' : '') . ($m['producto_nombre'] ? $m['producto_nombre'] : 'Producto')); ?></td>
                                <td class="text-end"><?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?></td>
                                <td class="text-end">$ <?php echo number_format((float)$m['precio_unitario'], 2, ',', '.'); ?></td>
                                <td class="text-end">$ <?php echo number_format((float)$m['total'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php echo h((string)$m['referencia_tipo']); ?><br>
                                    <small class="text-muted">ID <?php echo (int)$m['referencia_id']; ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .navbar, .sidebar, .topbar, footer { display:none !important; }
    .card { box-shadow:none !important; border:1px solid #ddd !important; }
    body { background:#fff !important; }
}
</style>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>