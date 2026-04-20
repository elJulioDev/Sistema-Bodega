<?php
// modulos/funcionarios/funcionarios_editar.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("
    SELECT f.*,
           u.id AS usuario_id, u.rol AS usuario_rol, u.id_bodega AS usuario_id_bodega,
           u.id_unidad AS usuario_id_unidad, u.estado AS usuario_estado, u.email AS usuario_email
    FROM funcionarios f
    LEFT JOIN usuarios u ON u.id_funcionario = f.id
    WHERE f.id = ?
    LIMIT 1
");
$stmt->execute(array($id));
$f = $stmt->fetch();

if (!$f) { die('Funcionario no encontrado.'); }

$tieneUsuario = !empty($f['usuario_id']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Datos funcionario
    $codigo    = trim((string)post('codigo'));
    $rut       = trim((string)post('rut'));
    $nombre    = trim((string)post('nombre'));
    $id_unidad = (int)post('id_unidad');
    $cargo     = trim((string)post('cargo'));
    $programa  = trim((string)post('programa'));
    $email     = trim((string)post('email'));

    // Datos acceso
    $crear_acceso = (int)post('crear_acceso');
    $clave        = (string)post('clave');
    $rol          = (string)post('rol');
    $id_bodega    = (int)post('id_bodega');
    $id_unidad_u  = (int)post('id_unidad_usuario');

    if ($rut === '' || $nombre === '') {
        $error = 'RUT y nombre son obligatorios.';
    } else {
        // RUT duplicado en otros funcionarios
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE rut = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($rut, $id));
        if ($stmt->fetch()) {
            $error = 'Ya existe otro funcionario con ese RUT.';
        } elseif ($crear_acceso === 1) {
            // Validar datos de acceso
            if (!$tieneUsuario && ($clave === '' || strlen($clave) < 4)) {
                $error = 'La contraseña debe tener al menos 4 caracteres.';
            } elseif ($clave !== '' && strlen($clave) < 4) {
                $error = 'La nueva contraseña debe tener al menos 4 caracteres.';
            } elseif (!in_array($rol, array('admin', 'bodega', 'solicitante'), true)) {
                $error = 'Rol inválido.';
            } elseif ($rol === 'bodega' && $id_bodega <= 0) {
                $error = 'Para rol Encargado debes asignar una bodega.';
            } elseif ($rol === 'solicitante' && $id_unidad_u <= 0) {
                $error = 'Para rol Solicitante debes asignar una unidad.';
            } elseif (!$tieneUsuario) {
                // Crear nuevo: validar RUT no exista como usuario
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

                // 1) Actualizar funcionario
                $sql = "UPDATE funcionarios SET
                            codigo = :codigo, rut = :rut, nombre = :nombre,
                            id_unidad = :id_unidad, cargo = :cargo,
                            programa = :programa, email = :email
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

                // 2) Gestionar acceso
                if ($crear_acceso === 1) {
                    $bodFinal = ($rol === 'bodega') ? $id_bodega : null;
                    $uniFinal = ($rol === 'solicitante') ? $id_unidad_u : null;

                    if ($tieneUsuario) {
                        // Actualizar usuario existente
                        if ($clave !== '') {
                            $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE usuarios SET
                                nombre = ?, email = ?, usuario = ?, clave_hash = ?,
                                rol = ?, id_bodega = ?, id_unidad = ?
                                WHERE id = ?");
                            $stmt->execute(array(
                                $nombre, $email !== '' ? $email : null, $rut, $clave_hash,
                                $rol, $bodFinal, $uniFinal, $f['usuario_id']
                            ));
                        } else {
                            $stmt = $pdo->prepare("UPDATE usuarios SET
                                nombre = ?, email = ?, usuario = ?,
                                rol = ?, id_bodega = ?, id_unidad = ?
                                WHERE id = ?");
                            $stmt->execute(array(
                                $nombre, $email !== '' ? $email : null, $rut,
                                $rol, $bodFinal, $uniFinal, $f['usuario_id']
                            ));
                        }
                        $uidFinal = (int)$f['usuario_id'];
                    } else {
                        // Crear usuario nuevo
                        $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO usuarios
                                  (id_funcionario, nombre, email, usuario, clave_hash, rol, id_bodega, id_unidad, estado)
                                VALUES
                                  (:id_funcionario, :nombre, :email, :usuario, :clave_hash, :rol, :id_bodega, :id_unidad, 1)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array(
                            ':id_funcionario' => $id,
                            ':nombre'         => $nombre,
                            ':email'          => $email !== '' ? $email : null,
                            ':usuario'        => $rut,
                            ':clave_hash'     => $clave_hash,
                            ':rol'            => $rol,
                            ':id_bodega'      => $bodFinal,
                            ':id_unidad'      => $uniFinal
                        ));
                        $uidFinal = (int)$pdo->lastInsertId();
                    }

                    // Sincronizar id_encargado de bodegas
                    if ($rol === 'bodega' && $id_bodega > 0) {
                        // Limpiar si era encargado de otra bodega
                        $pdo->prepare("UPDATE bodegas SET id_encargado = NULL WHERE id_encargado = ? AND id <> ?")
                            ->execute(array($uidFinal, $id_bodega));
                        // Asignar a la nueva
                        $pdo->prepare("UPDATE bodegas SET id_encargado = ? WHERE id = ?")
                            ->execute(array($uidFinal, $id_bodega));
                    } else {
                        // Si ya no es encargado, liberar cualquier bodega que tuviera
                        $pdo->prepare("UPDATE bodegas SET id_encargado = NULL WHERE id_encargado = ?")
                            ->execute(array($uidFinal));
                    }
                }

                $pdo->commit();
                set_flash('success', 'Funcionario actualizado correctamente.');
                redirect('funcionarios_lista.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Error al actualizar: ' . $e->getMessage();
            }
        }

        // Refresca valores
        $f = array_merge($f, array(
            'codigo' => $codigo, 'rut' => $rut, 'nombre' => $nombre,
            'id_unidad' => $id_unidad, 'cargo' => $cargo,
            'programa' => $programa, 'email' => $email
        ));
    }
}

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$bodegas = $pdo->query("
    SELECT b.id, b.codigo, b.nombre, b.es_central, b.id_encargado,
           ff.nombre AS encargado_nombre
    FROM bodegas b
    LEFT JOIN usuarios uu ON uu.id = b.id_encargado
    LEFT JOIN funcionarios ff ON ff.id = uu.id_funcionario
    WHERE b.estado = 1
    ORDER BY b.es_central DESC, b.nombre ASC
")->fetchAll();

$crear_acceso_default = $tieneUsuario ? 1 : 0;
$rol_default          = $tieneUsuario ? $f['usuario_rol'] : 'solicitante';
$id_bodega_default    = $tieneUsuario ? (int)$f['usuario_id_bodega'] : 0;
$id_unidad_u_default  = $tieneUsuario ? (int)$f['usuario_id_unidad'] : 0;

$pageTitle = 'Editar Funcionario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Funcionario
    </h1>
    <div class="d-flex gap-2">
        <a href="funcionarios_ver.php?id=<?php echo (int)$id; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-eye me-1"></i> Ver detalle
        </a>
        <a href="funcionarios_lista.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
    </div>
<?php endif; ?>

<form method="post" id="formFuncionario">

<div class="row g-3">
    <div class="col-lg-8">

        <!-- Datos funcionario -->
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
                        <label class="form-label fw-bold text-secondary">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" value="<?php echo h($f['nombre']); ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Unidad organizacional</label>
                        <select name="id_unidad" id="selUnidadFunc" class="form-select">
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
                        <label class="form-label fw-bold text-secondary">Programa / Proyecto</label>
                        <input type="text" name="programa" value="<?php echo h($f['programa']); ?>" class="form-control">
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
                    <input class="form-check-input" type="checkbox" id="chkCrearAcceso" name="crear_acceso" value="1" <?php echo ($crear_acceso_default === 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold small" for="chkCrearAcceso">
                        <?php echo $tieneUsuario ? 'Acceso habilitado' : 'Habilitar acceso'; ?>
                    </label>
                </div>
            </div>
            <div class="card-body p-4" id="seccionAcceso" style="display:<?php echo ($crear_acceso_default === 1) ? 'block' : 'none'; ?>;">

                <?php if ($tieneUsuario): ?>
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Usuario del sistema activo. Deja la contraseña en blanco para conservar la actual.
                        Para revocar el acceso usa el botón correspondiente en el listado.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        El nombre de usuario será el <strong>RUT</strong> del funcionario.
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">
                            <?php echo $tieneUsuario ? 'Nueva contraseña' : 'Contraseña'; ?>
                            <?php if (!$tieneUsuario): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        <input type="password" name="clave" id="inpClave" class="form-control" autocomplete="new-password" minlength="4" placeholder="<?php echo $tieneUsuario ? 'Dejar en blanco para no cambiar' : ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Rol del sistema <span class="text-danger">*</span></label>
                        <select name="rol" id="selRol" class="form-select">
                            <option value="admin"       <?php echo ($rol_default === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="bodega"      <?php echo ($rol_default === 'bodega') ? 'selected' : ''; ?>>Encargado de bodega</option>
                            <option value="solicitante" <?php echo ($rol_default === 'solicitante') ? 'selected' : ''; ?>>Solicitante</option>
                        </select>
                    </div>

                    <div class="col-12" id="grupoBodega" style="display:none;">
                        <label class="form-label fw-bold text-secondary">Bodega a cargo <span class="text-danger">*</span></label>
                        <select name="id_bodega" id="selBodega" class="form-select">
                            <option value="">— Selecciona una bodega —</option>
                            <?php foreach ($bodegas as $b):
                                $yaTiene = !empty($b['id_encargado']) && (int)$b['id_encargado'] !== (int)$f['usuario_id'];
                                $label = $b['codigo'] . ' — ' . $b['nombre'];
                                if ((int)$b['es_central'] === 1) $label .= ' [CENTRAL]';
                                if ($yaTiene) $label .= ' · asignada a ' . $b['encargado_nombre'];
                            ?>
                                <option value="<?php echo (int)$b['id']; ?>" <?php echo ($id_bodega_default === (int)$b['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12" id="grupoUnidadU" style="display:none;">
                        <label class="form-label fw-bold text-secondary">Unidad asociada <span class="text-danger">*</span></label>
                        <select name="id_unidad_usuario" id="selUnidadU" class="form-select">
                            <option value="">— Selecciona una unidad —</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad_u_default === (int)$u['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-light mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-info-circle me-2"></i>Notas
                </h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-2">Si cambias el RUT, también se actualiza el nombre de usuario del acceso.</li>
                    <li class="mb-2">Al asignar una bodega a un Encargado, se actualiza automáticamente en el módulo de Bodegas.</li>
                    <li>Para eliminar el acceso al sistema usa la opción <em>Revocar acceso</em> en el listado.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
    <a href="funcionarios_lista.php" class="btn btn-light border">Cancelar</a>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-floppy me-1"></i> Guardar cambios
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
    var yaTiene    = <?php echo $tieneUsuario ? 'true' : 'false'; ?>;

    function toggleAcceso() {
        var on = chkAcceso.checked;
        seccion.style.display = on ? 'block' : 'none';
        inpClave.required = on && !yaTiene;
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