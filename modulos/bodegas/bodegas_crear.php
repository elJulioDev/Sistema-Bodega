<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$error = '';
$nombre = $descripcion = $ubicacion = '';
$id_encargado = 0;
$id_unidad    = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre       = trim((string)post('nombre'));
    $descripcion  = trim((string)post('descripcion'));
    $ubicacion    = trim((string)post('ubicacion_referencial'));
    $id_encargado = (int)post('id_encargado');
    $id_unidad    = (int)post('id_unidad');

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        // Validar nombre duplicado
        $stmt = $pdo->prepare("SELECT id FROM bodegas WHERE nombre = ? LIMIT 1");
        $stmt->execute(array($nombre));
        if ($stmt->fetch()) {
            $error = 'Ya existe una bodega con ese nombre.';
        } else {
            try {
                $pdo->beginTransaction();

                // Generar código automático
                $row = $pdo->query("SELECT MAX(id) AS max_id FROM bodegas")->fetch();
                $nextId = isset($row['max_id']) ? ((int)$row['max_id'] + 1) : 1;
                $codigo = 'BOD-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);

                // Obtener nombre del encargado para el campo responsable (legacy)
                $responsableNombre = null;
                if ($id_encargado > 0) {
                    $stmt = $pdo->prepare("SELECT f.nombre FROM usuarios u
                                           LEFT JOIN funcionarios f ON f.id = u.id_funcionario
                                           WHERE u.id = ? LIMIT 1");
                    $stmt->execute(array($id_encargado));
                    $responsableNombre = $stmt->fetchColumn();
                }

                $sql = "INSERT INTO bodegas
                          (codigo, nombre, id_unidad, id_encargado, descripcion, ubicacion_referencial, responsable, estado)
                        VALUES
                          (:codigo, :nombre, :id_unidad, :id_encargado, :descripcion, :ubicacion, :responsable, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array(
                    ':codigo'       => $codigo,
                    ':nombre'       => $nombre,
                    ':id_unidad'    => $id_unidad > 0 ? $id_unidad : null,
                    ':id_encargado' => $id_encargado > 0 ? $id_encargado : null,
                    ':descripcion'  => $descripcion,
                    ':ubicacion'    => $ubicacion,
                    ':responsable'  => $responsableNombre ? $responsableNombre : null
                ));
                $idBodega = (int)$pdo->lastInsertId();

                // Asignar encargado: liberar bodega anterior + promover rol
                if ($id_encargado > 0) {
                    // Si el usuario estaba asignado a otra bodega, liberarla
                    $pdo->prepare("UPDATE bodegas SET id_encargado = NULL WHERE id_encargado = ? AND id <> ?")
                        ->execute(array($id_encargado, $idBodega));
                    // Asignar esta bodega y promover a rol encargado
                    $pdo->prepare("UPDATE usuarios SET rol = 'bodega', id_bodega = ? WHERE id = ?")
                        ->execute(array($idBodega, $id_encargado));
                }

                $pdo->commit();
                set_flash('success', 'Bodega creada correctamente con código ' . $codigo . '.');
                redirect('bodegas_lista.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'No fue posible crear la bodega: ' . $e->getMessage();
            }
        }
    }
}

// Todos los usuarios activos con acceso (excluyendo admin)
$encargados = $pdo->query("
    SELECT u.id, u.usuario, u.rol AS usuario_rol,
           COALESCE(f.nombre, u.nombre) AS nombre,
           f.rut, f.cargo, un.nombre AS unidad_nombre,
           b.id AS bodega_asignada_id, b.codigo AS bodega_asignada_codigo,
           b.nombre AS bodega_asignada_nombre
    FROM usuarios u
    LEFT JOIN funcionarios f ON f.id = u.id_funcionario
    LEFT JOIN unidades_organizacionales un ON un.id = f.id_unidad
    LEFT JOIN bodegas b ON b.id_encargado = u.id
    WHERE u.estado = 1 AND u.rol <> 'admin'
    ORDER BY COALESCE(f.nombre, u.nombre) ASC
")->fetchAll();

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$pageTitle = 'Nueva Bodega';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-building-add text-primary me-2"></i>Nueva Bodega
    </h1>
    <a href="bodegas_lista.php" class="btn btn-outline-secondary">
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

        <form method="post" class="row g-4">
            <div class="col-12">
                <label class="form-label fw-bold text-secondary">
                    Nombre de la bodega <span class="text-danger">*</span>
                </label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej: Bodega Central de Insumos"
                       value="<?php echo h($nombre); ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Unidad organizacional</label>
                <select name="id_unidad" class="form-select">
                    <option value="">— Sin unidad asignada —</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo ($id_unidad === (int)$u['id']) ? 'selected' : ''; ?>>
                            <?php echo h($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Una bodega solo puede pertenecer a una unidad.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Ubicación referencial</label>
                <input type="text" name="ubicacion_referencial" class="form-control" placeholder="Ej: Edificio A, piso 2"
                       value="<?php echo h($ubicacion); ?>">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Encargado de bodega</label>

                <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscadorEncargado" class="form-control" placeholder="Filtrar por nombre, RUT o cargo...">
                </div>

                <div class="border rounded" style="max-height:260px; overflow-y:auto;">
                    <?php if (!$encargados): ?>
                        <div class="p-3 text-center text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            No hay usuarios con acceso al sistema registrados.
                            <a href="../funcionarios/funcionarios_crear.php" class="alert-link">Crea un funcionario</a>
                            para habilitarlo automáticamente.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="listaEncargados">
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-2 encargado-item" data-search="sin ninguno">
                                <input type="radio" class="form-check-input mt-0" name="id_encargado" value="0" <?php echo ($id_encargado === 0) ? 'checked' : ''; ?>>
                                <span class="text-muted fst-italic small">— Sin encargado asignado —</span>
                            </label>
                            <?php foreach ($encargados as $e):
                                $searchText = strtolower(($e['nombre'] ? $e['nombre'] : '') . ' ' . ($e['rut'] ? $e['rut'] : '') . ' ' . ($e['cargo'] ? $e['cargo'] : ''));
                                $asignada = !empty($e['bodega_asignada_id']);
                            ?>
                                <label class="list-group-item list-group-item-action encargado-item"
                                       data-search="<?php echo h($searchText); ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <input type="radio" class="form-check-input mt-1" name="id_encargado" value="<?php echo (int)$e['id']; ?>"
                                               <?php echo ($id_encargado === (int)$e['id']) ? 'checked' : ''; ?>>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="fw-bold small text-dark"><?php echo h($e['nombre']); ?></div>
                                                    <div class="text-muted" style="font-size:.72rem;">
                                                        <i class="bi bi-person-badge me-1"></i><?php echo h($e['rut'] ? $e['rut'] : $e['usuario']); ?>
                                                        <?php if (!empty($e['cargo'])): ?>
                                                            · <?php echo h($e['cargo']); ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($e['unidad_nombre'])): ?>
                                                            · <?php echo h($e['unidad_nombre']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($asignada): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle" style="font-size:.65rem;" title="Ya asignado a otra bodega">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                        <?php echo h($e['bodega_asignada_codigo']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle" style="font-size:.65rem;">Disponible</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-text">
                    Si seleccionas un encargado ya asignado a otra bodega, será reasignado a esta.
                </div>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles o información adicional..."><?php echo h($descripcion); ?></textarea>
            </div>

            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    El código de la bodega se genera automáticamente al guardar (BOD-001, BOD-002, etc.).
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                <a href="bodegas_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Crear bodega
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var buscador = document.getElementById('buscadorEncargado');
    var items    = document.querySelectorAll('.encargado-item');
    if (!buscador || !items.length) return;

    buscador.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        items.forEach(function(it) {
            var txt = it.getAttribute('data-search') || '';
            it.style.display = (q === '' || txt.indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>