<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$buscar = get('buscar');
$id_bodega = get('id_bodega');

$stmt = $pdo->query("SELECT id, nombre FROM bodegas WHERE estado = 1 ORDER BY nombre ASC");
$bodegas = $stmt->fetchAll();

$sql = "SELECT 
            sb.*,
            b.nombre AS bodega_nombre,
            p.codigo AS producto_codigo,
            p.nombre AS producto_nombre,
            um.nombre AS unidad_nombre,
            tp.nombre AS tipo_nombre
        FROM stock_bodega sb
        INNER JOIN bodegas b ON b.id = sb.id_bodega
        INNER JOIN productos p ON p.id = sb.id_producto
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        LEFT JOIN tipos_producto tp ON tp.id = p.id_tipo_producto
        WHERE 1=1";
$params = array();

if ($buscar !== '') {
    $sql .= " AND (
        p.codigo LIKE :buscar
        OR p.nombre LIKE :buscar
        OR b.nombre LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

if ($id_bodega !== '') {
    $sql .= " AND sb.id_bodega = :id_bodega";
    $params[':id_bodega'] = (int)$id_bodega;
}

$sql .= " ORDER BY b.nombre ASC, p.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll();

$pageTitle = 'Stock';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Stock por Bodega</h1>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin:0;">
        <div>
            <label style="display:block; margin-bottom:6px; font-weight:700;">Buscar</label>
            <input
                type="text"
                name="buscar"
                value="<?php echo h($buscar); ?>"
                placeholder="Producto, código o bodega"
                style="padding:10px 12px; min-width:280px; border:1px solid #d1d5db; border-radius:10px;"
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

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn">Buscar</button>
            <a href="index.php" class="btn btn--secondary">Limpiar</a>
        </div>
    </form>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:1100px;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">Bodega</th>
                <th style="padding:12px 10px;">Código</th>
                <th style="padding:12px 10px;">Producto</th>
                <th style="padding:12px 10px;">Tipo</th>
                <th style="padding:12px 10px;">Unidad</th>
                <th style="padding:12px 10px;">Stock actual</th>
                <th style="padding:12px 10px;">Costo promedio</th>
                <th style="padding:12px 10px;">Última actualización</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$stocks): ?>
            <tr>
                <td colspan="8" style="padding:18px 10px; color:#6b7280;">No hay stock registrado.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($stocks as $s): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:12px 10px;"><?php echo h($s['bodega_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($s['producto_codigo']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($s['producto_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($s['tipo_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($s['unidad_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$s['stock_actual'], 2, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo number_format((float)$s['costo_promedio'], 0, ',', '.'); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($s['updated_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>