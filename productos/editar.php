<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute(array($id));
$producto = $stmt->fetch();

if (!$producto) {
    die('Producto no encontrado');
}

$stmt = $pdo->query("SELECT id, nombre FROM tipos_producto ORDER BY nombre");
$tipos = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nombre FROM unidades_medida ORDER BY nombre");
$unidades = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = post('codigo');
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    $id_tipo_producto = post('id_tipo_producto');
    $id_unidad_medida = post('id_unidad_medida');
    $marca = post('marca');
    $modelo = post('modelo');
    $stock_minimo = post('stock_minimo');
    $controla_stock = post('controla_stock');
    $activo_fijo = post('activo_fijo');

    if ($codigo === '' || $nombre === '') {
        $error = 'Código y nombre obligatorios';
    } else {

        $stmt = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? AND id <> ?");
        $stmt->execute(array($codigo, $id));
        $existe = $stmt->fetch();

        if ($existe) {
            $error = 'Código duplicado';
        } else {

            $stmt = $pdo->prepare("
                UPDATE productos SET
                codigo=?, nombre=?, descripcion=?, id_tipo_producto=?, id_unidad_medida=?,
                marca=?, modelo=?, stock_minimo=?, controla_stock=?, activo_fijo=?
                WHERE id=?
            ");

            $stmt->execute(array(
                $codigo,
                $nombre,
                $descripcion,
                ($id_tipo_producto !== '' ? $id_tipo_producto : null),
                ($id_unidad_medida !== '' ? $id_unidad_medida : null),
                $marca,
                $modelo,
                ($stock_minimo !== '' ? $stock_minimo : 0),
                $controla_stock,
                $activo_fijo,
                $id
            ));

            set_flash('success', 'Producto actualizado');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Editar Producto';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Editar Producto</h1>

<div class="card">

<?php if ($error): ?>
<div class="flash flash--error"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post">

<label>Código</label>
<input type="text" name="codigo" value="<?php echo h($producto['codigo']); ?>">

<label>Nombre</label>
<input type="text" name="nombre" value="<?php echo h($producto['nombre']); ?>">

<label>Tipo</label>
<select name="id_tipo_producto">
<option value="">Seleccione</option>
<?php foreach ($tipos as $t): ?>
<option value="<?php echo $t['id']; ?>" <?php echo ($producto['id_tipo_producto'] == $t['id']) ? 'selected' : ''; ?>>
<?php echo h($t['nombre']); ?>
</option>
<?php endforeach; ?>
</select>

<label>Unidad</label>
<select name="id_unidad_medida">
<option value="">Seleccione</option>
<?php foreach ($unidades as $u): ?>
<option value="<?php echo $u['id']; ?>" <?php echo ($producto['id_unidad_medida'] == $u['id']) ? 'selected' : ''; ?>>
<?php echo h($u['nombre']); ?>
</option>
<?php endforeach; ?>
</select>

<label>Marca</label>
<input type="text" name="marca" value="<?php echo h($producto['marca']); ?>">

<label>Modelo</label>
<input type="text" name="modelo" value="<?php echo h($producto['modelo']); ?>">

<label>Stock mínimo</label>
<input type="number" name="stock_minimo" value="<?php echo h($producto['stock_minimo']); ?>">

<label>Controla stock</label>
<select name="controla_stock">
<option value="1" <?php echo ($producto['controla_stock']==1?'selected':''); ?>>Sí</option>
<option value="0" <?php echo ($producto['controla_stock']==0?'selected':''); ?>>No</option>
</select>

<label>Activo fijo</label>
<select name="activo_fijo">
<option value="0" <?php echo ($producto['activo_fijo']==0?'selected':''); ?>>No</option>
<option value="1" <?php echo ($producto['activo_fijo']==1?'selected':''); ?>>Sí</option>
</select>

<label>Descripción</label>
<textarea name="descripcion"><?php echo h($producto['descripcion']); ?></textarea>

<br><br>

<button class="btn">Guardar</button>
<a href="index.php" class="btn btn--secondary">Volver</a>

</form>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>