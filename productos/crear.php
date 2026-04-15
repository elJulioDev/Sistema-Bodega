<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$error = '';

$stmt = $pdo->query("SELECT id, nombre FROM tipos_producto WHERE estado = 1 ORDER BY nombre ASC");
$tipos = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nombre, codigo FROM unidades_medida WHERE estado = 1 ORDER BY nombre ASC");
$unidades = $stmt->fetchAll();

$codigo = '';
$nombre = '';
$descripcion = '';
$id_tipo_producto = '';
$id_unidad_medida = '';
$marca = '';
$modelo = '';
$stock_minimo = '0.00';
$controla_stock = '1';
$activo_fijo = '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = post('codigo');
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    $id_tipo_producto = post('id_tipo_producto');
    $id_unidad_medida = post('id_unidad_medida');
    $marca = post('marca');
    $modelo = post('modelo');
    $stock_minimo = post('stock_minimo', '0.00');
    $controla_stock = post('controla_stock', '1');
    $activo_fijo = post('activo_fijo', '0');

    if ($codigo === '' || $nombre === '') {
        $error = 'El código y el nombre son obligatorios.';
    } else {

        $stmt = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? LIMIT 1");
        $stmt->execute(array($codigo));
        $existe = $stmt->fetch();

        if ($existe) {
            $error = 'Ya existe un producto con ese código.';
        } else {

            $stmt = $pdo->prepare("
                INSERT INTO productos (
                    codigo, nombre, descripcion, id_tipo_producto, id_unidad_medida,
                    marca, modelo, stock_minimo, controla_stock, activo_fijo, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute(array(
                $codigo,
                $nombre,
                ($descripcion !== '' ? $descripcion : null),
                ($id_tipo_producto !== '' ? (int)$id_tipo_producto : null),
                ($id_unidad_medida !== '' ? (int)$id_unidad_medida : null),
                $marca,
                $modelo,
                ($stock_minimo !== '' ? $stock_minimo : 0),
                (int)$controla_stock,
                (int)$activo_fijo
            ));

            set_flash('success', 'Producto creado correctamente.');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Nuevo Producto';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Nuevo Producto</h1>

<div class="card">

<?php if ($error !== ''): ?>
    <div class="flash flash--error"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post">

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px;">

<div>
<label>Código *</label>
<input type="text" name="codigo" value="<?php echo h($codigo); ?>">
</div>

<div>
<label>Nombre *</label>
<input type="text" name="nombre" value="<?php echo h($nombre); ?>">
</div>

<div>
<label>Tipo</label>
<select name="id_tipo_producto">
<option value="">Seleccione</option>
<?php foreach ($tipos as $t): ?>
<option value="<?php echo $t['id']; ?>"><?php echo h($t['nombre']); ?></option>
<?php endforeach; ?>
</select>
</div>

<div>
<label>Unidad</label>
<select name="id_unidad_medida">
<option value="">Seleccione</option>
<?php foreach ($unidades as $u): ?>
<option value="<?php echo $u['id']; ?>"><?php echo h($u['nombre']); ?></option>
<?php endforeach; ?>
</select>
</div>

<div>
<label>Marca</label>
<input type="text" name="marca" value="<?php echo h($marca); ?>">
</div>

<div>
<label>Modelo</label>
<input type="text" name="modelo" value="<?php echo h($modelo); ?>">
</div>

<div>
<label>Stock mínimo</label>
<input type="number" step="0.01" name="stock_minimo" value="<?php echo h($stock_minimo); ?>">
</div>

<div>
<label>Controla stock</label>
<select name="controla_stock">
<option value="1">Sí</option>
<option value="0">No</option>
</select>
</div>

<div>
<label>Activo fijo</label>
<select name="activo_fijo">
<option value="0">No</option>
<option value="1">Sí</option>
</select>
</div>

<div style="grid-column:1 / -1;">
<label>Descripción</label>
<textarea name="descripcion"><?php echo h($descripcion); ?></textarea>
</div>

</div>

<div style="margin-top:15px;">
<button class="btn">Guardar</button>
<a href="index.php" class="btn btn--secondary">Volver</a>
</div>

</form>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>