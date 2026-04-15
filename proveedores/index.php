<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

// activar / desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    $stmt = $pdo->prepare("UPDATE proveedores SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Estado del proveedor actualizado.');
    redirect('index.php');
}

$buscar = get('buscar');

$sql = "SELECT *
        FROM proveedores
        WHERE 1=1";
$params = [];

if ($buscar !== '') {
    $sql .= " AND (
        rut LIKE :buscar
        OR razon_social LIKE :buscar
        OR nombre_fantasia LIKE :buscar
    )";
    $params[':buscar'] = '%' . $buscar . '%';
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proveedores = $stmt->fetchAll();

$pageTitle = 'Proveedores';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Proveedores</h1>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:0;">
        <input
            type="text"
            name="buscar"
            value="<?php echo h($buscar); ?>"
            placeholder="Buscar por RUT o razón social"
            style="padding:10px 12px; min-width:280px; border:1px solid #d1d5db; border-radius:10px;"
        >
        <button type="submit" class="btn">Buscar</button>
        <?php if ($buscar !== ''): ?>
            <a href="index.php" class="btn btn--secondary">Limpiar</a>
        <?php endif; ?>
    </form>

    <a href="crear.php" class="btn">+ Nuevo proveedor</a>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:900px;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">ID</th>
                <th style="padding:12px 10px;">RUT</th>
                <th style="padding:12px 10px;">Razón social</th>
                <th style="padding:12px 10px;">Fantasia</th>
                <th style="padding:12px 10px;">Comuna</th>
                <th style="padding:12px 10px;">Teléfono</th>
                <th style="padding:12px 10px;">Email</th>
                <th style="padding:12px 10px;">Estado</th>
                <th style="padding:12px 10px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$proveedores): ?>
            <tr>
                <td colspan="9" style="padding:18px 10px; color:#6b7280;">
                    No se encontraron proveedores.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($proveedores as $p): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:12px 10px;"><?php echo (int)$p['id']; ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['rut']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['razon_social']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['nombre_fantasia']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['comuna']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['telefono']); ?></td>
                    <td style="padding:12px 10px;"><?php echo h($p['email']); ?></td>
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
                        <a href="?toggle=<?php echo (int)$p['id']; ?>"
                           onclick="return confirm('¿Deseas cambiar el estado de este proveedor?');">
                            Activar/Desactivar
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>