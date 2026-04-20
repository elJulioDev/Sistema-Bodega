<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM bodegas WHERE id = ?");
$stmt->execute(array($id));
$bodega = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bodega) { die('Bodega no encontrada'); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre       = trim((string)post('nombre'));
    $descripcion  = trim((string)post('descripcion'));
    $ubicacion    = trim((string)post('ubicacion_referencial'));
    $id_encargado = (int)post('id_encargado');
    $id_unidad    = (int)post('id_unidad');

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        // Validar nombre duplicado (excepto la misma bodega)
        $stmt = $pdo->prepare("SELECT id FROM bodegas WHERE nombre = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($nombre, $id));
        if ($stmt->fetch()) {
            $error = 'Ya existe otra bodega con ese nombre.';
        } else {
            try {
                $pdo->beginTransaction();

                // Obtener nombre del encargado (legacy field)
                $responsableNombre = null;
                if ($id_encargado > 0) {
                    $stmt = $pdo->prepare("SELECT f.nombre FROM usuarios u
                                           LEFT JOIN funcionarios f ON f.id = u.id_funcionario
                                           WHERE u.id = ? LIMIT 1");
                    $stmt->execute(array($id_encargado));
                    $responsableNombre = $stmt->fetchColumn();
                }

                $sql = "UPDATE bodegas SET
                            nombre = :nombre,
                            id_unidad = :id_unidad,
                            id_encargado = :id_encargado,
                            descripcion = :descripcion,
                            ubicacion_referencial = :ubicacion,
                            responsable = :responsable
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array(
                    ':nombre'       => $nombre,
                    ':id_unidad'    => $id_unidad > 0 ? $id_unidad : null,
                    ':id_encargado' => $id_encargado > 0 ? $id_encargado : null,
                    ':descripcion'  => $descripcion,
                    ':ubicacion'    => $ubicacion,
                    ':responsable'  => $responsableNombre ? $responsableNombre : null,
                    ':id'           => $id
                ));

                // Sincronizar usuarios.id_bodega
                if ($id_encargado > 0) {
                    // Liberar al anterior encargado de esta bodega si era distinto
                    if ((int)$bodega['id_encargado'] > 0 && (int)$bodega['id_encargado'] !== $id_encargado) {
                        $pdo->prepare("UPDATE usuarios SET id_bodega = NULL WHERE id = ?")
                            ->execute(array((int)$bodega['id_encargado']));
                    }
                    // Liberar al nuevo encargado de otras bodegas
                    $pdo->prepare("UPDATE bodegas SET id_encargado = NULL WHERE id_encargado = ? AND id <> ?")
                        ->execute(array($id_encargado, $id));
                    // Asignarle esta bodega
                    $pdo->prepare("UPDATE usuarios SET id_bodega = ? WHERE id = ?")
                        ->execute(array($id, $id_encargado));
                } else {
                    // Removido: liberar al encargado que tenía
                    if ((int)$bodega['id_encargado'] > 0) {
                        $pdo->prepare("UPDATE usuarios SET id_bodega = NULL WHERE id = ?")
                            ->execute(array((int)$bodega['id_encargado']));
                    }
                }

                $pdo->commit();
                set_flash('success', 'Bodega actualizada correctamente.');
                redirect('bodegas_lista.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Error al actualizar: ' . $e->getMessage();
            }
        }

        // Refrescar valores
        $bodega['nombre'] = $nombre;
        $bodega['descripcion'] = $descripcion;
        $bodega['ubicacion_referencial'] = $ubicacion;
        $bodega['id_encargado'] = $id_encargado > 0 ? $id_encargado : null;
        $bodega['id_unidad'] = $id_unidad > 0 ? $id_unidad : null;
    }
}

// Encargados disponibles
$encargados = $pdo->query("
    SELECT u.id, u.usuario, COALESCE(f.nombre, u.nombre) AS nombre,
           f.rut, f.cargo, un.nombre AS unidad_nombre,
           b.id AS bodega_asignada_id, b.codigo AS bodega_asignada_codigo,
           b.nombre AS bodega_asignada_nombre
    FROM usuarios u
    LEFT JOIN funcionarios f ON f.id = u.id_funcionario
    LEFT JOIN unidades_organizacionales un ON un.id = f.id_unidad
    LEFT JOIN bodegas b ON b.id_encargado = u.id
    WHERE u.rol = 'bodega' AND u.estado = 1
    ORDER BY COALESCE(f.nombre, u.nombre) ASC
")->fetchAll();

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

$pageTitle = 'Editar Bodega';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Bodega
    </h1>
    <div class="d-flex gap-2">
        <a href="bodegas_ver.php?id=<?php echo (int)$bodega['id']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-eye me-1"></i> Ver detalle
        </a>
        <a href="bodegas_lista.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-4">
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Código interno</label>
                <input type="text" value="<?php echo h($bodega['codigo']); ?>" class="form-control bg-light" readonly>
                <div class="form-text">Generado por el sistema, no editable.</div>
            </div>

            <div class="col-md-8">
                <label class="form-label fw-bold text-secondary">Nombre de la bodega <span class="text-danger">*</span></label>
                <input type="text" name="nombre" value="<?php echo h($bodega['nombre']); ?>" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Unidad organizacional</label>
                <select name="id_unidad" class="form-select">
                    <option value="">— Sin unidad asignada —</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$bodega['id_unidad'] === (int)$u['id']) ? 'selected' : ''; ?>>
                            <?php echo h($u['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Ubicación referencial</label>
                <input type="text" name="ubicacion_referencial" value="<?php echo h($bodega['ubicacion_referencial']); ?>" class="form-control">
            </div>

            <!-- Selector de encargado -->
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
                            No hay encargados disponibles.
                            <a href="../funcionarios/funcionarios_crear.php" class="alert-link">Crea un funcionario con rol Encargado</a>.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="listaEncargados">
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-2 encargado-item" data-search="sin ninguno">
                                <input type="radio" class="form-check-input mt-0" name="id_encargado" value="0" <?php echo empty($bodega['id_encargado']) ? 'checked' : ''; ?>>
                                <span class="text-muted fst-italic small">— Sin encargado asignado —</span>
                            </label>
                            <?php foreach ($encargados as $e):
                                $searchText = strtolower(($e['nombre'] ? $e['nombre'] : '') . ' ' . ($e['rut'] ? $e['rut'] : '') . ' ' . ($e['cargo'] ? $e['cargo'] : ''));
                                $asignadaOtra = !empty($e['bodega_asignada_id']) && (int)$e['bodega_asignada_id'] !== (int)$bodega['id'];
                                $esActual = ((int)$bodega['id_encargado'] === (int)$e['id']);
                            ?>
                                <label class="list-group-item list-group-item-action encargado-item <?php echo $esActual ? 'bg-light' : ''; ?>"
                                       data-search="<?php echo h($searchText); ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <input type="radio" class="form-check-input mt-1" name="id_encargado" value="<?php echo (int)$e['id']; ?>"
                                               <?php echo $esActual ? 'checked' : ''; ?>>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="fw-bold small text-dark">
                                                        <?php echo h($e['nombre']); ?>
                                                        <?php if ($esActual): ?>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle ms-1" style="font-size:.6rem;">ACTUAL</span>
                                                        <?php endif; ?>
                                                    </div>
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
                                                <?php if ($asignadaOtra): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle" style="font-size:.65rem;" title="Ya asignado a <?php echo h($e['bodega_asignada_nombre']); ?>">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                        <?php echo h($e['bodega_asignada_codigo']); ?>
                                                    </span>
                                                <?php elseif (!$esActual): ?>
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
                <textarea name="descripcion" class="form-control" rows="3"><?php echo h($bodega['descripcion']); ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                <a href="bodegas_lista.php" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Guardar cambios
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