<?php
// modulos/usuarios/usuarios_editar.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("
    SELECT u.*, f.rut AS func_rut, f.nombre AS func_nombre, f.email AS func_email,
           f.cargo AS func_cargo, f.id_unidad AS func_id_unidad,
           un.nombre AS func_unidad_nombre
    FROM usuarios u
    LEFT JOIN funcionarios f ON f.id = u.id_funcionario
    LEFT JOIN unidades_organizacionales un ON un.id = f.id_unidad
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute(array($id));
$usuario_db = $stmt->fetch();

if (!$usuario_db) {
    die('Usuario no encontrado.');
}

$error = '';

// Carga de selectores
$bodegas = $pdo->query("
    SELECT id, codigo, nombre, id_unidad, es_central
    FROM bodegas WHERE estado = 1
    ORDER BY es_central DESC, nombre ASC
")->fetchAll();

$unidades = $pdo->query("
    SELECT id, nombre FROM unidades_organizacionales
    WHERE estado = 1 ORDER BY nombre ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clave     = (string)post('clave');
    $rol       = (string)post('rol');
    $id_bodega = (int)post('id_bodega');
    $id_unidad = (int)post('id_unidad');

    if (!in_array($rol, array('admin', 'bodega', 'solicitante'), true)) {
        $error = 'Rol inválido.';
    } elseif ($rol === 'bodega' && $id_bodega <= 0) {
        $error = 'Para rol Encargado debes asignar una bodega.';
    } elseif ($rol === 'solicitante' && $id_unidad <= 0) {
        $error = 'Para rol Solicitante debes asignar una unidad.';
    } elseif ($clave !== '' && strlen($clave) < 4) {
        $error = 'La contraseña debe tener al menos 4 caracteres.';
    } else {
        // Normalizar asignaciones segun rol
        $bodFinal = ($rol === 'bodega') ? $id_bodega : null;
        $uniFinal = ($rol === 'solicitante') ? $id_unidad : null;

        if ($clave !== '') {
            $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET clave_hash=?, rol=?, id_bodega=?, id_unidad=? WHERE id=?");
            $stmt->execute(array($clave_hash, $rol, $bodFinal, $uniFinal, $id));
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET rol=?, id_bodega=?, id_unidad=? WHERE id=?");
            $stmt->execute(array($rol, $bodFinal, $uniFinal, $id));
        }

        set_flash('success', 'Usuario actualizado correctamente.');
        redirect('usuarios_lista.php');
    }
}

$pageTitle = 'Editar Usuario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Usuario
    </h1>
    <a href="usuarios_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Datos funcionario (solo lectura) -->
                <div class="alert alert-light border mb-4">
                    <div class="row g-2 small">
                        <div class="col-12 mb-1">
                            <strong class="text-dark"><i class="bi bi-person-badge me-1"></i><?php echo h($usuario_db['nombre']); ?></strong>
                        </div>
                        <div class="col-md-4"><strong>RUT:</strong> <?php echo h($usuario_db['usuario']); ?></div>
                        <div class="col-md-4"><strong>Email:</strong> <?php echo h($usuario_db['email'] ? $usuario_db['email'] : '—'); ?></div>
                        <div class="col-md-4"><strong>Unidad:</strong> <?php echo h($usuario_db['func_unidad_nombre'] ? $usuario_db['func_unidad_nombre'] : '—'); ?></div>
                        <?php if (!$usuario_db['id_funcionario']): ?>
                            <div class="col-12 mt-2">
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Usuario legacy sin funcionario vinculado
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" class="row g-3">

                    <!-- Clave -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Nueva Contraseña</label>
                        <input type="password" name="clave" class="form-control" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                    </div>

                    <!-- Rol -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">
                            Rol del Sistema <span class="text-danger">*</span>
                        </label>
                        <select name="rol" id="selRol" class="form-select" required>
                            <option value="admin"       <?php echo ($usuario_db['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="bodega"      <?php echo ($usuario_db['rol'] === 'bodega') ? 'selected' : ''; ?>>Encargado de Bodega</option>
                            <option value="solicitante" <?php echo ($usuario_db['rol'] === 'solicitante') ? 'selected' : ''; ?>>Solicitante</option>
                        </select>
                    </div>

                    <!-- Bodega -->
                    <div class="col-12" id="grupoBodega" style="display:none;">
                        <label class="form-label fw-bold text-secondary">
                            Bodega a cargo <span class="text-danger">*</span>
                        </label>
                        <select name="id_bodega" id="selBodega" class="form-select">
                            <option value="">— Selecciona una bodega —</option>
                            <?php foreach ($bodegas as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>" <?php echo ((int)$usuario_db['id_bodega'] === (int)$b['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($b['codigo'] . ' — ' . $b['nombre']); ?>
                                    <?php echo ((int)$b['es_central'] === 1) ? ' [CENTRAL]' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Unidad -->
                    <div class="col-12" id="grupoUnidad" style="display:none;">
                        <label class="form-label fw-bold text-secondary">
                            Unidad asociada <span class="text-danger">*</span>
                        </label>
                        <select name="id_unidad" id="selUnidad" class="form-select">
                            <option value="">— Selecciona una unidad —</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$usuario_db['id_unidad'] === (int)$u['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                        <a href="usuarios_lista.php" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy me-1"></i> Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-info-circle me-2"></i>Notas
                </h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-2">Los datos del funcionario (nombre, RUT, email) se editan desde el módulo de Funcionarios.</li>
                    <li class="mb-2">El funcionario vinculado no se puede cambiar una vez creado el usuario.</li>
                    <li>Para reasignar a otro funcionario, elimina este usuario y crea uno nuevo.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var selRol    = document.getElementById('selRol');
    var selBodega = document.getElementById('selBodega');
    var selUnidad = document.getElementById('selUnidad');
    var grupoBod  = document.getElementById('grupoBodega');
    var grupoUni  = document.getElementById('grupoUnidad');

    function actualizarCampos() {
        var rol = selRol.value;
        grupoBod.style.display = (rol === 'bodega') ? '' : 'none';
        grupoUni.style.display = (rol === 'solicitante') ? '' : 'none';
        selBodega.required = (rol === 'bodega');
        selUnidad.required = (rol === 'solicitante');
    }

    selRol.addEventListener('change', actualizarCampos);
    actualizarCampos();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>