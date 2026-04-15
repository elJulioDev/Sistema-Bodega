<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = post('codigo');
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    $responsable = post('responsable');

    if ($codigo === '' || $nombre === '') {
        $error = 'Código y nombre son obligatorios';
    } else {

        $sql = "INSERT INTO bodegas (codigo, nombre, descripcion, responsable, estado)
                VALUES (:codigo, :nombre, :descripcion, :responsable, 1)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':responsable' => $responsable
        ]);

        set_flash('success', 'Bodega creada correctamente');
        redirect('index.php');
    }
}

$pageTitle = 'Nueva Bodega';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Nueva Bodega</h1>

<div class="card">

<?php if ($error): ?>
    <div class="flash flash--error"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post">

    <div style="margin-bottom:12px;">
        <label>Código</label>
        <input type="text" name="codigo" style="width:100%; padding:10px;">
    </div>

    <div style="margin-bottom:12px;">
        <label>Nombre</label>
        <input type="text" name="nombre" style="width:100%; padding:10px;">
    </div>

    <div style="margin-bottom:12px;">
        <label>Descripción</label>
        <textarea name="descripcion" style="width:100%; padding:10px;"></textarea>
    </div>

    <div style="margin-bottom:12px;">
        <label>Responsable</label>
        <input type="text" name="responsable" style="width:100%; padding:10px;">
    </div>

    <button class="btn">Guardar</button>
    <a href="index.php" class="btn btn--secondary">Volver</a>

</form>

</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>