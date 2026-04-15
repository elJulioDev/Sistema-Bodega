<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM bodegas WHERE id = ?");
$stmt->execute([$id]);
$bodega = $stmt->fetch();

if (!$bodega) {
    die('Bodega no encontrada');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = post('codigo');
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    $responsable = post('responsable');

    if ($codigo === '' || $nombre === '') {
        $error = 'Código y nombre son obligatorios';
    } else {

        $sql = "UPDATE bodegas SET
                codigo = :codigo,
                nombre = :nombre,
                descripcion = :descripcion,
                responsable = :responsable
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':responsable' => $responsable,
            ':id' => $id
        ]);

        set_flash('success', 'Bodega actualizada');
        redirect('index.php');
    }
}

$pageTitle = 'Editar Bodega';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Editar Bodega</h1>

<div class="card">

<?php if ($error): ?>
    <div class="flash flash--error"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post">

    <div style="margin-bottom:12px;">
        <label>Código</label>
        <input type="text" name="codigo" value="<?php echo h($bodega['codigo']); ?>" style="width:100%; padding:10px;">
    </div>

    <div style="margin-bottom:12px;">
        <label>Nombre</label>
        <input type="text" name="nombre" value="<?php echo h($bodega['nombre']); ?>" style="width:100%; padding:10px;">
    </div>

    <div style="margin-bottom:12px;">
        <label>Descripción</label>
        <textarea name="descripcion" style="width:100%; padding:10px;"><?php echo h($bodega['descripcion']); ?></textarea>
    </div>

    <div style="margin-bottom:12px;">
        <label>Responsable</label>
        <input type="text" name="responsable" value="<?php echo h($bodega['responsable']); ?>" style="width:100%; padding:10px;">
    </div>

    <button class="btn">Guardar cambios</button>
    <a href="index.php" class="btn btn--secondary">Volver</a>

</form>

</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>