<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$error = '';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE tipos_producto SET estado = IF(estado=1,0,1) WHERE id = ?");
    $stmt->execute(array($id));
    set_flash('success', 'Estado actualizado.');
    redirect('tipos.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id', 0);
    $nombre = post('nombre');
    $descripcion = post('descripcion');

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT id FROM tipos_producto WHERE nombre = ? AND id <> ? LIMIT 1");
            $stmt->execute(array($nombre, $id));
            $existe = $stmt->fetch();

            if ($existe) {
                $error = 'Ya existe otro tipo con ese nombre.';
            } else {
                $stmt = $pdo->prepare("UPDATE tipos_producto SET nombre = ?, descripcion = ? WHERE id = ?");
                $stmt->execute(array($nombre, $descripcion, $id));
                set_flash('success', 'Tipo actualizado correctamente.');
                redirect('tipos.php');
            }
        } else {
            $stmt = $pdo->prepare("SELECT id FROM tipos_producto WHERE nombre = ? LIMIT 1");
            $stmt->execute(array($nombre));
            $existe = $stmt->fetch();

            if ($existe) {
                $error = 'Ya existe un tipo con ese nombre.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tipos_producto (nombre, descripcion, estado) VALUES (?, ?, 1)");
                $stmt->execute(array($nombre, $descripcion));
                set_flash('success', 'Tipo creado correctamente.');
                redirect('tipos.php');
            }
        }
    }
}

$editando = null;
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM tipos_producto WHERE id = ? LIMIT 1");
    $stmt->execute(array($idEditar));
    $editando = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM tipos_producto ORDER BY nombre ASC");
$tipos = $stmt->fetchAll();

$pageTitle = 'Tipos de Producto';
require_once __DIR__ . '/../inc/header.php';
?>

<h1 class="page-title">Tipos de Producto</h1>

<div class="card">
    <?php if ($error !== ''): ?>
        <div class="flash flash--error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?php echo $editando ? (int)$editando['id'] : 0; ?>">

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px;">
            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Nombre *</label>
                <input type="text" name="nombre" value="<?php echo h(isset($editando['nombre']) ? $editando['nombre'] : ''); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:6px; font-weight:700;">Descripción</label>
                <input type="text" name="descripcion" value="<?php echo h(isset($editando['descripcion']) ? $editando['descripcion'] : ''); ?>" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px;">
            </div>
        </div>

        <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn"><?php echo $editando ? 'Guardar cambios' : 'Guardar'; ?></button>
            <?php if ($editando): ?>
                <a href="tipos.php" class="btn btn--secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card" style="overflow:auto;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                <th style="padding:12px 10px;">ID</th>
                <th style="padding:12px 10px;">Nombre</th>
                <th style="padding:12px 10px;">Descripción</th>
                <th style="padding:12px 10px;">Estado</th>
                <th style="padding:12px 10px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tipos as $t): ?>
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:12px 10px;"><?php echo (int)$t['id']; ?></td>
                <td style="padding:12px 10px;"><?php echo h($t['nombre']); ?></td>
                <td style="padding:12px 10px;"><?php echo h($t['descripcion']); ?></td>
                <td style="padding:12px 10px;">
                    <?php if ((int)$t['estado'] === 1): ?>
                        <span style="color:#166534; font-weight:700;">Activo</span>
                    <?php else: ?>
                        <span style="color:#991b1b; font-weight:700;">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td style="padding:12px 10px;">
                    <a href="tipos.php?editar=<?php echo (int)$t['id']; ?>">Editar</a>
                    &nbsp;|&nbsp;
                    <a href="tipos.php?toggle=<?php echo (int)$t['id']; ?>" onclick="return confirm('¿Deseas cambiar el estado?');">Activar/Desactivar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>