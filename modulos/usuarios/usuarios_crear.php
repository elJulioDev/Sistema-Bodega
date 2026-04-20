<?php
// modulos/usuarios/usuarios_crear.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';
$id_funcionario = 0;
$rol = 'solicitante';
$id_bodega = 0;
$id_unidad = 0;

// Cargar datos para formulario
// Funcionarios activos aun NO vinculados a un usuario
$funcionarios = $pdo->query("
    SELECT f.id, f.rut, f.nombre, f.email, f.id_unidad, f.cargo
    FROM funcionarios f
    LEFT JOIN usuarios u ON u.id_funcionario = f.id
    WHERE f.estado = 1 AND u.id IS NULL
    ORDER BY f.nombre ASC
")->fetchAll();

// Bodegas activas (excluye central para encargados)
$bodegas = $pdo->query("
    SELECT id, codigo, nombre, id_unidad, es_central
    FROM bodegas
    WHERE estado = 1
    ORDER BY es_central DESC, nombre ASC
")->fetchAll();

// Unidades
$unidades = $pdo->query("
    SELECT id, nombre FROM unidades_organizacionales
    WHERE estado = 1 ORDER BY nombre ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_funcionario = (int)post('id_funcionario');
    $clave          = (string)post('clave');
    $rol            = (string)post('rol');
    $id_bodega      = (int)post('id_bodega');
    $id_unidad      = (int)post('id_unidad');

    if ($id_funcionario <= 0) {
        $error = 'Debes seleccionar un funcionario.';
    } elseif ($clave === '' || strlen($clave) < 4) {
        $error = 'La contraseña debe tener al menos 4 caracteres.';
    } elseif (!in_array($rol, array('admin', 'bodega', 'solicitante'), true)) {
        $error = 'Rol inválido.';
    } elseif ($rol === 'bodega' && $id_bodega <= 0) {
        $error = 'Para rol Encargado debes asignar una bodega.';
    } elseif ($rol === 'solicitante' && $id_unidad <= 0) {
        $error = 'Para rol Solicitante debes asignar una unidad.';
    } else {
        // Obtener datos del funcionario
        $stmt = $pdo->prepare("SELECT id, rut, nombre, email FROM funcionarios WHERE id = ? AND estado = 1 LIMIT 1");
        $stmt->execute(array($id_funcionario));
        $func = $stmt->fetch();

        if (!$func) {
            $error = 'Funcionario no encontrado o inactivo.';
        } else {
            // Validar RUT no duplicado como usuario
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute(array($func['rut']));
            if ($stmt->fetch()) {
                $error = 'Ya existe un usuario con ese RUT.';
            } else {
                // Ajustar asignaciones segun rol
                if ($rol === 'admin') {
                    $id_bodega = null;
                    $id_unidad = null;
                } elseif ($rol === 'bodega') {
                    $id_unidad = null;
                } elseif ($rol === 'solicitante') {
                    $id_bodega = null;
                }

                $clave_hash = password_hash($clave, PASSWORD_DEFAULT);

                $sql = "INSERT INTO usuarios
                          (id_funcionario, nombre, email, usuario, clave_hash, rol, id_bodega, id_unidad, estado)
                        VALUES
                          (:id_funcionario, :nombre, :email, :usuario, :clave_hash, :rol, :id_bodega, :id_unidad, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array(
                    ':id_funcionario' => $func['id'],
                    ':nombre'         => $func['nombre'],
                    ':email'          => $func['email'],
                    ':usuario'        => $func['rut'],
                    ':clave_hash'     => $clave_hash,
                    ':rol'            => $rol,
                    ':id_bodega'      => $id_bodega ? $id_bodega : null,
                    ':id_unidad'      => $id_unidad ? $id_unidad : null
                ));

                set_flash('success', 'Usuario creado correctamente para ' . $func['nombre'] . '.');
                redirect('usuarios_lista.php');
            }
        }
    }
}

// Armar mapa de funcionarios para JS
$funcionariosJs = array();
foreach ($funcionarios as $f) {
    $funcionariosJs[(int)$f['id']] = array(
        'rut'       => $f['rut'],
        'nombre'    => $f['nombre'],
        'email'     => $f['email'],
        'id_unidad' => (int)$f['id_unidad'],
        'cargo'     => $f['cargo']
    );
}

$pageTitle = 'Nuevo Usuario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-person-plus text-primary me-2"></i>Nuevo Usuario
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

                <?php if (!$funcionarios): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle me-2"></i>
                        No hay funcionarios disponibles para vincular. Todos los funcionarios activos ya tienen un usuario, o aún no se han cargado.
                        <br>
                        <a href="../funcionarios/funcionarios_lista.php" class="alert-link">
                            Ir al módulo de funcionarios
                        </a>
                    </div>
                <?php else: ?>

                <form method="post" class="row g-3" id="formUsuario">

                    <!-- Funcionario -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">
                            Funcionario <span class="text-danger">*</span>
                        </label>
                        <select name="id_funcionario" id="selFuncionario" class="form-select" required>
                            <option value="">— Selecciona un funcionario —</option>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo (int)$f['id']; ?>" <?php echo ($id_funcionario === (int)$f['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($f['rut'] . ' — ' . $f['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Solo se listan funcionarios sin usuario creado.</div>
                    </div>

                    <!-- Preview funcionario (solo lectura) -->
                    <div class="col-12" id="previewFuncionario" style="display:none;">
                        <div class="alert alert-info mb-0">
                            <div class="row g-2 small">
                                <div class="col-md-4"><strong>RUT:</strong> <span id="pvRut">—</span></div>
                                <div class="col-md-4"><strong>Email:</strong> <span id="pvEmail">—</span></div>
                                <div class="col-md-4"><strong>Cargo:</strong> <span id="pvCargo">—</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Clave -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">
                            Contraseña <span class="text-danger">*</span>
                        </label>
                        <input type="password" name="clave" class="form-control" placeholder="Mínimo 4 caracteres" required autocomplete="new-password">
                    </div>

                    <!-- Rol -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">
                            Rol del Sistema <span class="text-danger">*</span>
                        </label>
                        <select name="rol" id="selRol" class="form-select" required>
                            <option value="admin"       <?php echo ($rol === 'admin') ? 'selected' : ''; ?>>Administrador (acceso total)</option>
                            <option value="bodega"      <?php echo ($rol === 'bodega') ? 'selected' : ''; ?>>Encargado de Bodega</option>
                            <option value="solicitante" <?php echo ($rol === 'solicitante') ? 'selected' : ''; ?>>Solicitante</option>
                        </select>
                    </div>

                    <!-- Bodega (solo rol = bodega) -->
                    <div class="col-12" id="grupoBodega" style="display:none;">
                        <label class="form-label fw-bold text-secondary">
                            Bodega a cargo <span class="text-danger">*</span>
                        </label>
                        <select name="id_bodega" id="selBodega" class="form-select">
                            <option value="">— Selecciona una bodega —</option>
                            <?php foreach ($bodegas as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>" <?php echo ($id_bodega === (int)$b['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($b['codigo'] . ' — ' . $b['nombre']); ?>
                                    <?php echo ((int)$b['es_central'] === 1) ? ' [CENTRAL]' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Unidad (solo rol = solicitante) -->
                    <div class="col-12" id="grupoUnidad" style="display:none;">
                        <label class="form-label fw-bold text-secondary">
                            Unidad asociada <span class="text-danger">*</span>
                        </label>
                        <select name="id_unidad" id="selUnidad" class="form-select">
                            <option value="">— Selecciona una unidad —</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad === (int)$u['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Las solicitudes del usuario se dirigirán a la bodega de esta unidad.</div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                        <a href="usuarios_lista.php" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy me-1"></i> Crear Usuario
                        </button>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel lateral: explicación de roles -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-info-circle me-2"></i>Roles del sistema
                </h6>

                <div class="mb-3">
                    <span class="badge bg-danger bg-opacity-10 text-danger mb-1">ADMINISTRADOR</span>
                    <p class="small text-muted mb-0">Acceso total al sistema. No requiere bodega ni unidad.</p>
                </div>

                <div class="mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-1">ENCARGADO</span>
                    <p class="small text-muted mb-0">Administra una bodega específica. Puede realizar traslados desde su bodega y aprobar solicitudes entrantes.</p>
                </div>

                <div class="mb-0">
                    <span class="badge bg-info bg-opacity-10 text-info mb-1">SOLICITANTE</span>
                    <p class="small text-muted mb-0">Solicita movimientos desde cualquier bodega hacia la bodega asociada a su unidad.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var funcionarios = <?php echo json_encode($funcionariosJs); ?>;

    var selFunc   = document.getElementById('selFuncionario');
    var selRol    = document.getElementById('selRol');
    var selBodega = document.getElementById('selBodega');
    var selUnidad = document.getElementById('selUnidad');
    var grupoBod  = document.getElementById('grupoBodega');
    var grupoUni  = document.getElementById('grupoUnidad');
    var preview   = document.getElementById('previewFuncionario');

    function actualizarPreview() {
        var id = selFunc.value;
        if (!id || !funcionarios[id]) {
            preview.style.display = 'none';
            return;
        }
        var f = funcionarios[id];
        document.getElementById('pvRut').textContent   = f.rut || '—';
        document.getElementById('pvEmail').textContent = f.email || '—';
        document.getElementById('pvCargo').textContent = f.cargo || '—';
        preview.style.display = '';

        // Pre-seleccionar unidad si el rol es solicitante
        if (selRol.value === 'solicitante' && f.id_unidad && !selUnidad.value) {
            selUnidad.value = f.id_unidad;
        }
    }

    function actualizarCampos() {
        var rol = selRol.value;
        grupoBod.style.display = (rol === 'bodega') ? '' : 'none';
        grupoUni.style.display = (rol === 'solicitante') ? '' : 'none';

        selBodega.required = (rol === 'bodega');
        selUnidad.required = (rol === 'solicitante');

        // Al pasar a solicitante, pre-cargar unidad del funcionario si aplica
        if (rol === 'solicitante') {
            var id = selFunc.value;
            if (id && funcionarios[id] && funcionarios[id].id_unidad && !selUnidad.value) {
                selUnidad.value = funcionarios[id].id_unidad;
            }
        }
    }

    selFunc.addEventListener('change', function() {
        actualizarPreview();
        actualizarCampos();
    });
    selRol.addEventListener('change', actualizarCampos);

    // Inicial
    actualizarPreview();
    actualizarCampos();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>