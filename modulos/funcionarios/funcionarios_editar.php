<?php
// modulos/funcionarios/funcionarios_editar.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ? LIMIT 1");
$stmt->execute(array($id));
$f = $stmt->fetch();

if (!$f) {
    die('Funcionario no encontrado.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo    = trim((string)post('codigo'));
    $rut       = trim((string)post('rut'));
    $nombre    = trim((string)post('nombre'));
    $id_unidad = (int)post('id_unidad');
    $cargo     = trim((string)post('cargo'));
    $programa  = trim((string)post('programa'));
    $email     = trim((string)post('email'));

    if ($rut === '' || $nombre === '') {
        $error = 'RUT y nombre son obligatorios.';
    } else {
        // Validar que no exista otro funcionario con el mismo RUT
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE rut = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($rut, $id));
        if ($stmt->fetch()) {
            $error = 'Ya existe otro funcionario con ese RUT.';
        } else {
            $sql = "UPDATE funcionarios SET
                        codigo    = :codigo,
                        rut       = :rut,
                        nombre    = :nombre,
                        id_unidad = :id_unidad,
                        cargo     = :cargo,
                        programa  = :programa,
                        email     = :email
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ':codigo'    => $codigo,
                ':rut'       => $rut,
                ':nombre'    => $nombre,
                ':id_unidad' => $id_unidad > 0 ? $id_unidad : null,
                ':cargo'     => $cargo,
                ':programa'  => $programa,
                ':email'     => $email,
                ':id'        => $id
            ));
            set_flash('success', 'Funcionario actualizado correctamente.');
            redirect('funcionarios_lista.php');
        }

        // Refresca valores si hubo error
        $f = array_merge($f, array(
            'codigo' => $codigo, 'rut' => $rut, 'nombre' => $nombre,
            'id_unidad' => $id_unidad, 'cargo' => $cargo,
            'programa' => $programa, 'email' => $email
        ));
    }
}

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$pageTitle = 'Editar Funcionario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Funcionario
    </h1>
    <a href="funcionarios_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Código Funcionario</label>
                <input type="text" name="codigo" value="<?php echo h($f['codigo']); ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">RUT <span class="text-danger">*</span></label>
                <input type="text" name="rut" value="<?php echo h($f['rut']); ?>" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Email</label>
                <input type="email" name="email" value="<?php echo h($f['email']); ?>" class="form-control">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Nombre Completo <span class="text-danger">*</span></label>
                <input type="text" name="nombre" value="<?php echo h($f['nombre']); ?>" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Unidad</label>
                <select name="id_unidad" class="form-select">
                    <option value="">— Sin unidad asignada —</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$f['id_unidad'] === (int)$u['id']) ? 'selected' : ''; ?>>
                            <?php echo h($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Cargo</label>
                <input type="text" name="cargo" value="<?php echo h($f['cargo']); ?>" class="form-control">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Programa</label>
                <input type="text" name="programa" value="<?php echo h($f['programa']); ?>" class="form-control">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                <a href="funcionarios_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>