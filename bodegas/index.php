<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$pageTitle = 'Bodegas';
require_once __DIR__ . '/../inc/header.php';

// activar/desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    $pdo->exec("UPDATE bodegas SET estado = IF(estado=1,0,1) WHERE id = {$id}");
    set_flash('success', 'Estado actualizado');
    redirect('index.php');
}

// listado
$stmt = $pdo->query("SELECT * FROM bodegas ORDER BY id DESC");
$bodegas = $stmt->fetchAll();
?>

<h1 class="page-title">Bodegas</h1>

<div class="card">
    <a href="crear.php" class="btn">+ Nueva bodega</a>
</div>

<div class="card">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>ID</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Responsable</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bodegas as $b): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?php echo $b['id']; ?></td>
                <td><?php echo h($b['codigo']); ?></td>
                <td><?php echo h($b['nombre']); ?></td>
                <td><?php echo h($b['responsable']); ?></td>
                <td>
                    <?php if ($b['estado']): ?>
                        <span style="color:green;">Activo</span>
                    <?php else: ?>
                        <span style="color:red;">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="editar.php?id=<?php echo $b['id']; ?>">Editar</a> |
                    <a href="?toggle=<?php echo $b['id']; ?>">Activar/Desactivar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>