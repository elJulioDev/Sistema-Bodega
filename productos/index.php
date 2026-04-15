<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE productos SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute([$id]);
    set_flash('success', 'Estado del producto actualizado.');
    redirect('index.php');
}

$buscar = get('buscar');

$sql = "SELECT p.*, tp.nombre AS tipo_nombre, um.nombre AS unidad_nombre
        FROM productos p
        LEFT JOIN tipos_producto tp ON tp.id = p.id_tipo_producto
        LEFT JOIN unidades_medida um ON um.id = p.id_unidad_medida
        WHERE 1=1";
$params = [];

if ($buscar !== '') {
    $sql .= " AND (
        p.codigo LIKE :buscar
        OR p.nombre LIKE :buscar
        OR p.marca LIKE :buscar
        OR p.modelo LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$pageTitle = 'Productos';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Productos</h1>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; margin:0;">
        <input
            type="text"
            name="buscar"
            value="<?php echo h($buscar); ?>"
            placeholder="Buscar por código, nombre, marca o modelo"
            style="padding:10px 12px; min-width:300px; border:1px solid #d1d5db; border-radius:10px;"
        >
        <button type="submit" class="btn">Buscar</button>
        <?php if ($buscar !== ''): ?>
            <a href="index.php" class="btn btn--secondary">Limpiar</a>
        <?php endif; ?>
    </form>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="tipos.php" class="btn btn--secondary">Tipos</a>
        <a href="unidades.php" class="btn btn--secondary">Unidades</a>
        <a href="crear.php" class="btn">+ Nuevo producto</a>
    </div>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:1100px;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">ID</th>
                <th style="padding:12px 10px;">Código</th>
                <th style="padding:12px 10px;">Nombre</th>
                <th style="padding:12px 10px;">Tipo</th>
                <th style="padding:12px 10px;">Unidad</th>
                <th style="padding:12px 10px;">Marca</th>
                <th style="padding:12px 10px;">Modelo</th>
                <th style="padding:12px 10px;">Stock mínimo</th>
                <th style="padding:12px 10px;">Controla stock</th>
                <th style="padding:12px 10px;">Estado</th>
                <th style="padding:12px 10px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$productos): ?>
            <tr>
                <td colspan="11" style="padding:18px 10px; color:#6b7280;">
                    No se encontraron productos.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($productos as $p): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:12px 10px;"><?php echo (int)$p['id']; ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['codigo']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['tipo_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['unidad_nombre']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['marca']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['modelo']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['stock_minimo']); ?></td>
                    <td style="padding:12px 10px;">
                        <?php echo ((int)$p['controla_stock'] === 1) ? 'Sí' : 'No'; ?>
                    </td>
                    <td style="padding:12px 10px;">
                        <?php if ((int)$p['estado'] === 1): ?>
                            <span style="color:#166534; font-weight:700;">Activo</span>
                        <?php else: ?>
                            <span style="color:#991b1b; font-weight:700;">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 10px; white-space:nowrap;">
                        <a href="editar.php?id=<?php echo (int)$p['id']; ?>">Editar</a>
                        &nbsp;|&nbsp;
                        <a href="index.php?toggle=<?php echo (int)$p['id']; ?>" onclick="return confirm('¿Deseas cambiar el estado del producto?');">Activar/Desactivar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>