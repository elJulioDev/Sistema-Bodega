<?php
// modulos/funcionarios/funcionarios_editar.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/bodegas_helpers.php';

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
$uidActual    = $tieneUsuario ? (int)$f['usuario_id'] : 0;
$error = '';

// Bodegas que ya gestiona el usuario actual (M:N) — para pre-seleccionar
$bodegasAsignadas = array();
if ($uidActual > 0) {
    $bodegasAsignadas = user_bodegas_ids($uidActual);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo    = trim((string)post('codigo'));
    $rut       = trim((string)post('rut'));
    $nombre    = trim((string)post('nombre'));
    $id_unidad = (int)post('id_unidad');
    $cargo     = trim((string)post('cargo'));
    $programa  = trim((string)post('programa'));
    $email     = trim((string)post('email'));

    $crear_acceso = (int)post('crear_acceso');
    $clave        = (string)post('clave');
    $rol          = (string)post('rol');
    $id_unidad_u  = (int)post('id_unidad_usuario');
    // Lista de bodegas M:N para rol 'bodega'
    $bodegasSel   = isset($_POST['bodegas']) && is_array($_POST['bodegas']) ? $_POST['bodegas'] : array();
    $bodegasSel   = array_map('intval', $bodegasSel);
    $bodegaPrincipalId = (int)post('bodega_principal');

    if ($rut === '' || $nombre === '') {
        $error = 'RUT y nombre son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE rut = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($rut, $id));
        if ($stmt->fetch()) {
            $error = 'Ya existe otro funcionario con ese RUT.';
        } elseif ($crear_acceso === 1) {
            if (!$tieneUsuario && ($clave === '' || strlen($clave) < 4)) {
                $error = 'La contraseña debe tener al menos 4 caracteres.';
            } elseif ($clave !== '' && strlen($clave) < 4) {
                $error = 'La nueva contraseña debe tener al menos 4 caracteres.';
            } elseif (!in_array($rol, array('admin', 'bodega', 'solicitante'), true)) {
                $error = 'Rol inválido.';
            } elseif ($rol === 'bodega' && !$bodegasSel) {
                $error = 'Para rol Encargado debes asignar al menos una bodega.';
            } elseif ($rol === 'bodega' && $bodegaPrincipalId > 0 && !in_array($bodegaPrincipalId, $bodegasSel, true)) {
                $error = 'La bodega principal debe estar dentro de las bodegas asignadas.';
            } elseif ($rol === 'solicitante' && $id_unidad_u <= 0) {
                $error = 'Para rol Solicitante debes asignar una unidad.';
            } elseif (!$tieneUsuario) {
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
                    $uniFinal = ($rol === 'solicitante') ? $id_unidad_u : null;

                    if ($tieneUsuario) {
                        if ($clave !== '') {
                            $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE usuarios SET
                                nombre = ?, email = ?, usuario = ?, clave_hash = ?,
                                rol = ?, id_unidad = ?
                                WHERE id = ?");
                            $stmt->execute(array(
                                $nombre, $email !== '' ? $email : null, $rut, $clave_hash,
                                $rol, $uniFinal, $uidActual
                            ));
                        } else {
                            $stmt = $pdo->prepare("UPDATE usuarios SET
                                nombre = ?, email = ?, usuario = ?,
                                rol = ?, id_unidad = ?
                                WHERE id = ?");
                            $stmt->execute(array(
                                $nombre, $email !== '' ? $email : null, $rut,
                                $rol, $uniFinal, $uidActual
                            ));
                        }
                        $uidFinal = $uidActual;
                    } else {
                        $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO usuarios
                                  (id_funcionario, nombre, email, usuario, clave_hash, rol, id_bodega, id_unidad, estado)
                                VALUES
                                  (:id_funcionario, :nombre, :email, :usuario, :clave_hash, :rol, NULL, :id_unidad, 1)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array(
                            ':id_funcionario' => $id,
                            ':nombre'         => $nombre,
                            ':email'          => $email !== '' ? $email : null,
                            ':usuario'        => $rut,
                            ':clave_hash'     => $clave_hash,
                            ':rol'            => $rol,
                            ':id_unidad'      => $uniFinal
                        ));
                        $uidFinal = (int)$pdo->lastInsertId();
                    }

                    // 3) Sincronizar M:N usuarios_bodegas
                    if ($rol === 'bodega') {
                        // Obtener asignaciones actuales
                        $stA = $pdo->prepare("SELECT id_bodega FROM usuarios_bodegas WHERE id_usuario = ?");
                        $stA->execute(array($uidFinal));
                        $actuales = array_map('intval', $stA->fetchAll(PDO::FETCH_COLUMN));

                        $paraAgregar = array_diff($bodegasSel, $actuales);
                        $paraQuitar  = array_diff($actuales, $bodegasSel);

                        // Determinar principal (explícita o primera)
                        if ($bodegaPrincipalId <= 0 && $bodegasSel) {
                            $bodegaPrincipalId = (int)$bodegasSel[0];
                        }

                        foreach ($paraAgregar as $bid) {
                            asignar_encargado_bodega($uidFinal, (int)$bid, ((int)$bid === $bodegaPrincipalId));
                        }
                        foreach ($paraQuitar as $bid) {
                            desasignar_encargado_bodega($uidFinal, (int)$bid);
                        }

                        // Asegurar que la principal quede marcada correctamente
                        if ($bodegaPrincipalId > 0 && in_array($bodegaPrincipalId, $bodegasSel, true)) {
                            set_bodega_principal($uidFinal, $bodegaPrincipalId);
                        }
                    } else {
                        // No es encargado: limpiar todas sus bodegas M:N
                        $stA = $pdo->prepare("SELECT id_bodega FROM usuarios_bodegas WHERE id_usuario = ?");
                        $stA->execute(array($uidFinal));
                        $actuales = array_map('intval', $stA->fetchAll(PDO::FETCH_COLUMN));
                        foreach ($actuales as $bid) {
                            desasignar_encargado_bodega($uidFinal, (int)$bid);
                        }
                        // Limpiar legacy id_bodega
                        $pdo->prepare("UPDATE usuarios SET id_bodega = NULL WHERE id = ?")->execute(array($uidFinal));
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

        $f = array_merge($f, array(
            'codigo' => $codigo, 'rut' => $rut, 'nombre' => $nombre,
            'id_unidad' => $id_unidad, 'cargo' => $cargo,
            'programa' => $programa, 'email' => $email
        ));
        // Mantener selección tras error
        $bodegasAsignadas = $bodegasSel;
    }
}

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$bodegas = $pdo->query("
    SELECT b.id, b.codigo, b.nombre, b.es_central,
           (SELECT COUNT(*) FROM usuarios_bodegas WHERE id_bodega = b.id) AS total_encargados
    FROM bodegas b
    WHERE b.estado = 1
    ORDER BY b.es_central DESC, b.nombre ASC
")->fetchAll();

$crear_acceso_default = $tieneUsuario ? 1 : 0;
$rol_default          = $tieneUsuario ? $f['usuario_rol'] : 'solicitante';
$id_unidad_u_default  = $tieneUsuario ? (int)$f['usuario_id_unidad'] : 0;

// Principal actual
$bodegaPrincipalActual = 0;
if ($uidActual > 0) {
    $st = $pdo->prepare("SELECT id_bodega FROM usuarios_bodegas WHERE id_usuario = ? AND es_principal = 1 LIMIT 1");
    $st->execute(array($uidActual));
    $bodegaPrincipalActual = (int)$st->fetchColumn();
}

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
                        Usuario activo. Deja la contraseña en blanco para conservar la actual.
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

                    <!-- Bodegas M:N (solo rol bodega) -->
                    <div class="col-12" id="grupoBodegas" style="display:none;">
                        <label class="form-label fw-bold text-secondary">Bodegas a cargo <span class="text-danger">*</span></label>
                        <div class="border rounded p-2" style="max-height:240px;overflow-y:auto;">
                            <?php foreach ($bodegas as $b):
                                $checked = in_array((int)$b['id'], $bodegasAsignadas, true);
                                $esPrincipal = ((int)$b['id'] === $bodegaPrincipalActual);
                            ?>
                                <div class="form-check d-flex align-items-center gap-2 mb-1">
                                    <input type="checkbox" class="form-check-input chk-bod" name="bodegas[]"
                                           value="<?php echo (int)$b['id']; ?>"
                                           id="bod_<?php echo (int)$b['id']; ?>"
                                           data-principal="<?php echo $esPrincipal ? '1' : '0'; ?>"
                                           <?php echo $checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label flex-grow-1 small" for="bod_<?php echo (int)$b['id']; ?>">
                                        <span class="badge bg-light text-dark border me-1"><?php echo h($b['codigo']); ?></span>
                                        <?php echo h($b['nombre']); ?>
                                        <?php if ((int)$b['es_central'] === 1): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle ms-1" style="font-size:.6rem;">CENTRAL</span>
                                        <?php endif; ?>
                                        <?php if ((int)$b['total_encargados'] > 0): ?>
                                            <span class="text-muted ms-2" style="font-size:.7rem;">
                                                <i class="bi bi-people me-1"></i><?php echo (int)$b['total_encargados']; ?> encargado(s)
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="radio" class="form-check-input rd-principal" name="bodega_principal"
                                           value="<?php echo (int)$b['id']; ?>"
                                           <?php echo $esPrincipal ? 'checked' : ''; ?>
                                           <?php echo $checked ? '' : 'disabled'; ?>
                                           title="Marcar como bodega principal">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">
                            ☑ Marca las bodegas que gestionará · ◉ Marca la <strong>bodega principal</strong> (la que verá por defecto).
                        </div>
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
                    <li class="mb-2">Un encargado puede gestionar <strong>varias bodegas</strong> a la vez.</li>
                    <li class="mb-2">Una bodega puede tener <strong>varios encargados</strong> (M:N).</li>
                    <li class="mb-2">La <strong>bodega principal</strong> es la que se muestra por defecto.</li>
                    <li>Un solicitante podrá pedir a todas las bodegas de su unidad.</li>
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
    var selUnidadU = document.getElementById('selUnidadU');
    var grupoBods  = document.getElementById('grupoBodegas');
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
            grupoBods.style.display = 'none';
            grupoUni.style.display  = 'none';
            selUnidadU.required     = false;
            return;
        }
        var rol = selRol.value;
        grupoBods.style.display = (rol === 'bodega')      ? '' : 'none';
        grupoUni.style.display  = (rol === 'solicitante') ? '' : 'none';
        selUnidadU.required     = (rol === 'solicitante');

        if (rol === 'solicitante' && !selUnidadU.value && selUniFunc.value) {
            selUnidadU.value = selUniFunc.value;
        }
    }

    // Habilitar/deshabilitar radios de principal según checkbox
    document.querySelectorAll('.chk-bod').forEach(function(chk) {
        chk.addEventListener('change', function() {
            var id   = this.value;
            var rad  = document.querySelector('.rd-principal[value="' + id + '"]');
            if (!rad) return;
            rad.disabled = !this.checked;
            if (!this.checked && rad.checked) {
                rad.checked = false;
                // Auto-seleccionar primera marcada como principal
                var firstChk = document.querySelector('.chk-bod:checked');
                if (firstChk) {
                    var r = document.querySelector('.rd-principal[value="' + firstChk.value + '"]');
                    if (r) r.checked = true;
                }
            }
        });
    });

    chkAcceso.addEventListener('change', toggleAcceso);
    selRol.addEventListener('change', actualizarCampos);

    toggleAcceso();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>