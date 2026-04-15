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

<h1 class="page-title">Movimientos de Bodega</h1>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin:0;">
        <div>
            <label style="display:block; margin-bottom:6px; font-weight:700;">Buscar</label>
            <input
                type="text"
                name="buscar"
                value="<?php echo h($buscar); ?>"
                placeholder="Producto, bodega u observación"
                style="padding:10px 12px; min-width:260px; border:1px solid #d1d5db; border-radius:10px;"
            >
        </div>

        <div>
            <label style="display:block; margin-bottom:6px; font-weight:700;">Bodega</label>
            <select name="id_bodega" style="padding:10px 12px; min-width:220px; border:1px solid #d1d5db; border-radius:10px;">
                <option value="">Todas</option>
                <?php foreach ($bodegas as $b): ?>
                    <option value="<?php echo (int)$b['id']; ?>" <?php echo ((string)$id_bodega === (string)$b['id']) ? 'selected' : ''; ?>>
                        <?php echo h($b['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display:block; margin-bottom:6px; font-weight:700;">Tipo</label>
            <select name="tipo_movimiento" style="padding:10px 12px; min-width:220px; border:1px solid #d1d5db; border-radius:10px;">
                <option value="">Todos</option>
                <option value="entrada_compra" <?php echo ($tipo_movimiento === 'entrada_compra') ? 'selected' : ''; ?>>entrada_compra</option>
                <option value="salida_consumo" <?php echo ($tipo_movimiento === 'salida_consumo') ? 'selected' : ''; ?>>salida_consumo</option>
                <option value="ajuste_entrada" <?php echo ($tipo_movimiento === 'ajuste_entrada') ? 'selected' : ''; ?>>ajuste_entrada</option>
                <option value="ajuste_salida" <?php echo ($tipo_movimiento === 'ajuste_salida') ? 'selected' : ''; ?>>ajuste_salida</option>
                <option value="traslado_entrada" <?php echo ($tipo_movimiento === 'traslado_entrada') ? 'selected' : ''; ?>>traslado_entrada</option>
                <option value="traslado_salida" <?php echo ($tipo_movimiento === 'traslado_salida') ? 'selected' : ''; ?>>traslado_salida</option>
            </select>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn">Buscar</button>
            <a href="index.php" class="btn btn--secondary">Limpiar</a>
        </div>
    </form>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:1400px;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">Fecha</th>
                <th style="padding:12px 10px;">Bodega</th>
                <th style="padding:12px 10px;">Código</th>
                <th style="padding:12px 10px;">Producto</th>
                <th style="padding:12px 10px;">Tipo</th>
                <th style="padding:12px 10px;">Cantidad</th>
                <th style="padding:12px 10px;">Precio</th>
                <th style="padding:12px 10px;">Total</th>
                <th style="padding:12px 10px;">Referencia</th>
                <th style="padding:12px 10px;">Observación</th>
                <th style="padding:12px 10px;">Usuario</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$movimientos): ?>
            <tr>
                <td colspan="11" style="padding:18px 10px; color:#6b7280;">No se encontraron movimientos.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($movimientos as $m): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:12px 10px;"><?php echo h($m['fecha_movimiento']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($m['bodega_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($m['producto_codigo']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($m['producto_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($m['tipo_movimiento']); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$m['cantidad'], 2, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$m['precio_unitario'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$m['total'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($m['referencia_tipo'] . ' #' . $m['referencia_id']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($m['observacion']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($m['usuario_nombre']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>