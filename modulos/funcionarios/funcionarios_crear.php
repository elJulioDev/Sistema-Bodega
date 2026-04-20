<?php
// modulos/funcionarios/funcionarios_crear.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';
$codigo = $rut = $nombre = $cargo = $programa = $email = '';
$id_unidad = 0;

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
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE rut = ? LIMIT 1");
        $stmt->execute(array($rut));
        if ($stmt->fetch()) {
            $error = 'Ya existe un funcionario con ese RUT.';
        } else {
            $sql = "INSERT INTO funcionarios (codigo, rut, nombre, id_unidad, cargo, programa, email, estado)
                    VALUES (:codigo, :rut, :nombre, :id_unidad, :cargo, :programa, :email, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ':codigo'    => $codigo,
                ':rut'       => $rut,
                ':nombre'    => $nombre,
                ':id_unidad' => $id_unidad > 0 ? $id_unidad : null,
                ':cargo'     => $cargo,
                ':programa'  => $programa,
                ':email'     => $email
            ));
            set_flash('success', 'Funcionario creado correctamente.');
            redirect('funcionarios_lista.php');
        }
    }
}

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$pageTitle = 'Nuevo Funcionario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-person-plus text-primary me-2"></i>Nuevo Funcionario
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
                <input type="text" name="codigo" value="<?php echo h($codigo); ?>" class="form-control" placeholder="Ej: 001">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">RUT <span class="text-danger">*</span></label>
                <input type="text" name="rut" value="<?php echo h($rut); ?>" class="form-control" placeholder="12345678-9" required>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Email</label>
                <input type="email" name="email" value="<?php echo h($email); ?>" class="form-control" placeholder="correo@coltauco.cl">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Nombre Completo <span class="text-danger">*</span></label>
                <input type="text" name="nombre" value="<?php echo h($nombre); ?>" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Unidad</label>
                <select name="id_unidad" class="form-select">
                    <option value="">— Sin unidad asignada —</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad === (int)$u['id']) ? 'selected' : ''; ?>>
                            <?php echo h($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Cargo</label>
                <input type="text" name="cargo" value="<?php echo h($cargo); ?>" class="form-control">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Programa</label>
                <input type="text" name="programa" value="<?php echo h($programa); ?>" class="form-control">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                <a href="funcionarios_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>