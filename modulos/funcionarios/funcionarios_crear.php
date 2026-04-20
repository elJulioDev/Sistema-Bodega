<?php
// modulos/funcionarios/funcionarios_crear.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';

// Valores por defecto
$codigo = $rut = $nombre = $cargo = $programa = $email = '';
$id_unidad    = 0;
$crear_acceso = 0;
$rol          = 'solicitante';
$id_bodega    = 0;
$id_unidad_u  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Datos funcionario
    $codigo     = trim((string)post('codigo'));
    $rut        = trim((string)post('rut'));
    $nombre     = trim((string)post('nombre'));
    $id_unidad  = (int)post('id_unidad');
    $cargo      = trim((string)post('cargo'));
    $programa   = trim((string)post('programa'));
    $email      = trim((string)post('email'));

    // Datos acceso (opcional)
    $crear_acceso = (int)post('crear_acceso');
    $clave        = (string)post('clave');
    $rol          = (string)post('rol');
    $id_bodega    = (int)post('id_bodega');
    $id_unidad_u  = (int)post('id_unidad_usuario');

    if ($rut === '' || $nombre === '') {
        $error = 'RUT y nombre son obligatorios.';
    } else {
        // Validar RUT duplicado en funcionarios
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE rut = ? LIMIT 1");
        $stmt->execute(array($rut));
        if ($stmt->fetch()) {
            $error = 'Ya existe un funcionario con ese RUT.';
        } elseif ($crear_acceso === 1) {
            // Validar datos acceso
            if ($clave === '' || strlen($clave) < 4) {
                $error = 'La contraseña debe tener al menos 4 caracteres.';
            } elseif (!in_array($rol, array('admin', 'bodega', 'solicitante'), true)) {
                $error = 'Rol inválido.';
            } elseif ($rol === 'bodega' && $id_bodega <= 0) {
                $error = 'Para rol Encargado debes asignar una bodega.';
            } elseif ($rol === 'solicitante' && $id_unidad_u <= 0) {
                $error = 'Para rol Solicitante debes asignar una unidad.';
            } else {
                // Validar RUT duplicado como usuario
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
                $stmt->execute(array($rut));
                if ($stmt->fetch()) {
                    $error = 'Ya existe un usuario con ese RUT. No se puede crear acceso.';
                }
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                // Insertar funcionario
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
                $idFuncionario = (int)$pdo->lastInsertId();

                // Insertar acceso (si corresponde)
                if ($crear_acceso === 1) {
                    $bodFinal = ($rol === 'bodega') ? $id_bodega : null;
                    $uniFinal = ($rol === 'solicitante') ? $id_unidad_u : null;
                    $clave_hash = password_hash($clave, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO usuarios
                              (id_funcionario, nombre, email, usuario, clave_hash, rol, id_bodega, id_unidad, estado)
                            VALUES
                              (:id_funcionario, :nombre, :email, :usuario, :clave_hash, :rol, :id_bodega, :id_unidad, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array(
                        ':id_funcionario' => $idFuncionario,
                        ':nombre'         => $nombre,
                        ':email'          => $email !== '' ? $email : null,
                        ':usuario'        => $rut,
                        ':clave_hash'     => $clave_hash,
                        ':rol'            => $rol,
                        ':id_bodega'      => $bodFinal,
                        ':id_unidad'      => $uniFinal
                    ));
                    $idUsuarioNuevo = (int)$pdo->lastInsertId();

                    // Si el rol es encargado, marcar a este usuario como id_encargado de la bodega
                    if ($rol === 'bodega' && $id_bodega > 0) {
                        $pdo->prepare("UPDATE bodegas SET id_encargado = ? WHERE id = ?")
                            ->execute(array($idUsuarioNuevo, $id_bodega));
                    }
                }

                $pdo->commit();

                $msg = 'Funcionario creado correctamente';
                if ($crear_acceso === 1) {
                    $msg .= ' con acceso al sistema';
                }
                set_flash('success', $msg . '.');
                redirect('funcionarios_lista.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Error al crear: ' . $e->getMessage();
            }
        }
    }
}

// Datos para selectores
$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

// Bodegas sin encargado asignado (y que no sean central)
$bodegas = $pdo->query("
    SELECT b.id, b.codigo, b.nombre, b.es_central,
           u.id AS encargado_id, f.nombre AS encargado_nombre
    FROM bodegas b
    LEFT JOIN usuarios u ON u.id = b.id_encargado
    LEFT JOIN funcionarios f ON f.id = u.id_funcionario
    WHERE b.estado = 1
    ORDER BY b.es_central DESC, b.nombre ASC
")->fetchAll();

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

<?php if ($error !== ''): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
    </div>
<?php endif; ?>

<form method="post" id="formFuncionario">

<div class="row g-3">
    <div class="col-lg-8">

        <!-- Datos del funcionario -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-person-vcard text-primary me-2"></i>Datos del funcionario
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary">Código RRHH</label>
                        <input type="text" name="codigo" value="<?php echo h($codigo); ?>" class="form-control" placeholder="Ej: 001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary">RUT <span class="text-danger">*</span></label>
                        <input type="text" name="rut" id="inpRut" value="<?php echo h($rut); ?>" class="form-control" placeholder="12345678-9" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary">Email</label>
                        <input type="email" name="email" value="<?php echo h($email); ?>" class="form-control" placeholder="correo@coltauco.cl">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" value="<?php echo h($nombre); ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Unidad organizacional</label>
                        <select name="id_unidad" id="selUnidadFunc" class="form-select">
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
                        <input type="text" name="cargo" value="<?php echo h($cargo); ?>" class="form-control" placeholder="Ej: Administrativo">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary">Programa / Proyecto</label>
                        <input type="text" name="programa" value="<?php echo h($programa); ?>" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <!-- Acceso al sistema -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-shield-lock text-success me-2"></i>Acceso al sistema
                </h5>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="chkCrearAcceso" name="crear_acceso" value="1" <?php echo ($crear_acceso === 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold small" for="chkCrearAcceso">Habilitar acceso</label>
                </div>
            </div>
            <div class="card-body p-4" id="seccionAcceso" style="display:<?php echo ($crear_acceso === 1) ? 'block' : 'none'; ?>;">

                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    El nombre de usuario será el <strong>RUT</strong> del funcionario.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="clave" id="inpClave" class="form-control" autocomplete="new-password" minlength="4">
                        <div class="form-text">Mínimo 4 caracteres.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Rol del sistema <span class="text-danger">*</span></label>
                        <select name="rol" id="selRol" class="form-select">
                            <option value="admin"       <?php echo ($rol === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="bodega"      <?php echo ($rol === 'bodega') ? 'selected' : ''; ?>>Encargado de bodega</option>
                            <option value="solicitante" <?php echo ($rol === 'solicitante') ? 'selected' : ''; ?>>Solicitante</option>
                        </select>
                    </div>

                    <div class="col-12" id="grupoBodega" style="display:none;">
                        <label class="form-label fw-bold text-secondary">Bodega a cargo <span class="text-danger">*</span></label>
                        <select name="id_bodega" id="selBodega" class="form-select">
                            <option value="">— Selecciona una bodega —</option>
                            <?php foreach ($bodegas as $b): ?>
                                <?php
                                    $yaTiene = !empty($b['encargado_id']);
                                    $label = $b['codigo'] . ' — ' . $b['nombre'];
                                    if ((int)$b['es_central'] === 1) $label .= ' [CENTRAL]';
                                    if ($yaTiene) $label .= ' · ya asignada a ' . $b['encargado_nombre'];
                                ?>
                                <option value="<?php echo (int)$b['id']; ?>"
                                        <?php echo ($id_bodega === (int)$b['id']) ? 'selected' : ''; ?>
                                        <?php echo $yaTiene ? 'data-asignada="1"' : ''; ?>>
                                    <?php echo h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Si seleccionas una bodega que ya tiene encargado, este será reemplazado.
                        </div>
                    </div>

                    <div class="col-12" id="grupoUnidadU" style="display:none;">
                        <label class="form-label fw-bold text-secondary">Unidad asociada <span class="text-danger">*</span></label>
                        <select name="id_unidad_usuario" id="selUnidadU" class="form-select">
                            <option value="">— Selecciona una unidad —</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad_u === (int)$u['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Las solicitudes se dirigirán a la bodega de esta unidad.</div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Panel lateral informativo -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-info-circle me-2"></i>Roles del sistema
                </h6>
                <div class="mb-3">
                    <span class="badge bg-danger bg-opacity-10 text-danger mb-1 border border-danger-subtle">ADMINISTRADOR</span>
                    <p class="small text-muted mb-0">Acceso total. No requiere bodega ni unidad.</p>
                </div>
                <div class="mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-1 border border-primary-subtle">ENCARGADO</span>
                    <p class="small text-muted mb-0">Gestiona una bodega. Puede registrar consumos y traslados.</p>
                </div>
                <div class="mb-0">
                    <span class="badge bg-info bg-opacity-10 text-info mb-1 border border-info-subtle">SOLICITANTE</span>
                    <p class="small text-muted mb-0">Solicita insumos desde cualquier bodega hacia su unidad.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
    <a href="funcionarios_lista.php" class="btn btn-light border">Cancelar</a>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-floppy me-1"></i> Guardar funcionario
    </button>
</div>

</form>

<script>
(function() {
    var chkAcceso  = document.getElementById('chkCrearAcceso');
    var seccion    = document.getElementById('seccionAcceso');
    var selRol     = document.getElementById('selRol');
    var selBodega  = document.getElementById('selBodega');
    var selUnidadU = document.getElementById('selUnidadU');
    var grupoBod   = document.getElementById('grupoBodega');
    var grupoUni   = document.getElementById('grupoUnidadU');
    var inpClave   = document.getElementById('inpClave');
    var selUniFunc = document.getElementById('selUnidadFunc');

    function toggleAcceso() {
        var on = chkAcceso.checked;
        seccion.style.display = on ? 'block' : 'none';
        inpClave.required = on;
        actualizarCampos();
    }

    function actualizarCampos() {
        if (!chkAcceso.checked) {
            grupoBod.style.display = 'none';
            grupoUni.style.display = 'none';
            selBodega.required = false;
            selUnidadU.required = false;
            return;
        }
        var rol = selRol.value;
        grupoBod.style.display = (rol === 'bodega')      ? '' : 'none';
        grupoUni.style.display = (rol === 'solicitante') ? '' : 'none';
        selBodega.required  = (rol === 'bodega');
        selUnidadU.required = (rol === 'solicitante');

        // Si es solicitante, auto-copiar unidad del funcionario
        if (rol === 'solicitante' && !selUnidadU.value && selUniFunc.value) {
            selUnidadU.value = selUniFunc.value;
        }
    }

    chkAcceso.addEventListener('change', toggleAcceso);
    selRol.addEventListener('change', actualizarCampos);

    toggleAcceso();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>