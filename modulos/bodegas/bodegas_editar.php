<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/bodegas_helpers.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("SELECT * FROM bodegas WHERE id = ?");
$stmt->execute(array($id));
$bodega = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bodega) { die('Bodega no encontrada'); }

// Encargado principal actual (para el form simplificado)
$principalActualId = 0;
$stmtP = $pdo->prepare("SELECT id_usuario FROM usuarios_bodegas WHERE id_bodega = ? AND es_principal = 1 LIMIT 1");
$stmtP->execute(array($id));
$rp = $stmtP->fetchColumn();
if ($rp) { $principalActualId = (int)$rp; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre       = trim((string)post('nombre'));
    $descripcion  = trim((string)post('descripcion'));
    $ubicacion    = trim((string)post('ubicacion_referencial'));
    $id_principal = (int)post('id_encargado');
    $id_unidad    = (int)post('id_unidad');

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM bodegas WHERE nombre = ? AND id <> ? LIMIT 1");
        $stmt->execute(array($nombre, $id));
        if ($stmt->fetch()) {
            $error = 'Ya existe otra bodega con ese nombre.';
        } else {
            try {
                $pdo->beginTransaction();

                // Obtener nombre del encargado (legacy)
                $responsableNombre = null;
                if ($id_principal > 0) {
                    $stmt = $pdo->prepare("SELECT f.nombre FROM usuarios u
                                           LEFT JOIN funcionarios f ON f.id = u.id_funcionario
                                           WHERE u.id = ? LIMIT 1");
                    $stmt->execute(array($id_principal));
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
                    ':id_encargado' => $id_principal > 0 ? $id_principal : null,
                    ':descripcion'  => $descripcion,
                    ':ubicacion'    => $ubicacion,
                    ':responsable'  => $responsableNombre ? $responsableNombre : null,
                    ':id'           => $id
                ));

                // Gestión del principal vía helper M:N
                if ($id_principal > 0) {
                    if ($principalActualId !== $id_principal) {
                        // Asegurar que el nuevo esté asignado y marcado como principal
                        asignar_encargado_bodega($id_principal, $id, true);
                        // set_bodega_principal además actualiza legacy (redundante pero seguro)
                        set_bodega_principal($id_principal, $id);
                    }
                } else {
                    // Se quitó el encargado principal: solo quitar la marca, no la asignación de otros
                    $pdo->prepare("UPDATE usuarios_bodegas SET es_principal = 0 WHERE id_bodega = ?")
                        ->execute(array($id));
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
        $bodega['id_encargado'] = $id_principal > 0 ? $id_principal : null;
        $bodega['id_unidad'] = $id_unidad > 0 ? $id_unidad : null;
        $principalActualId = $id_principal;
    }
}

// Lista de encargados candidatos (muestra cuántas bodegas ya gestiona cada uno)
$encargados = $pdo->query("
    SELECT u.id, u.usuario, u.rol AS usuario_rol,
           COALESCE(f.nombre, u.nombre) AS nombre,
           f.rut, f.cargo, un.nombre AS unidad_nombre,
           (SELECT COUNT(*) FROM usuarios_bodegas WHERE id_usuario = u.id) AS total_bodegas
    FROM usuarios u
    LEFT JOIN funcionarios f ON f.id = u.id_funcionario
    LEFT JOIN unidades_organizacionales un ON un.id = f.id_unidad
    WHERE u.estado = 1 AND u.rol <> 'admin'
    ORDER BY COALESCE(f.nombre, u.nombre) ASC
")->fetchAll();

$unidades = $pdo->query("SELECT id, nombre FROM unidades_organizacionales WHERE estado = 1 ORDER BY nombre")->fetchAll();

// Total encargados M:N actuales
$totalEncMn = 0;
$stTE = $pdo->prepare("SELECT COUNT(*) FROM usuarios_bodegas WHERE id_bodega = ?");
$stTE->execute(array($id));
$totalEncMn = (int)$stTE->fetchColumn();

$pageTitle = 'Editar Bodega';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Bodega
    </h1>
    <div class="d-flex gap-2">
        <a href="bodegas_encargados.php?id=<?php echo (int)$bodega['id']; ?>" class="btn btn-outline-success">
            <i class="bi bi-people-fill me-1"></i> Encargados (<?php echo $totalEncMn; ?>)
        </a>
        <a href="bodegas_ver.php?id=<?php echo (int)$bodega['id']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-eye me-1"></i> Ver detalle
        </a>
        <a href="bodegas_lista.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<?php if ($totalEncMn > 1): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Esta bodega tiene <strong><?php echo $totalEncMn; ?> encargados</strong> asignados. Aquí solo puedes cambiar el
    <strong>encargado principal</strong>. Para agregar/quitar otros encargados usa
    <a href="bodegas_encargados.php?id=<?php echo (int)$bodega['id']; ?>" class="alert-link">Gestionar encargados</a>.
</div>
<?php endif; ?>

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
                <div class="form-text">Una unidad puede tener varias bodegas.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Ubicación referencial</label>
                <input type="text" name="ubicacion_referencial" value="<?php echo h($bodega['ubicacion_referencial']); ?>" class="form-control">
            </div>

            <div class="col-12">
                <label class="form-label fw-bold text-secondary">Encargado principal</label>

                <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscadorEncargado" class="form-control" placeholder="Filtrar por nombre, RUT o cargo...">
                </div>

                <div class="border rounded" style="max-height:260px; overflow-y:auto;">
                    <?php if (!$encargados): ?>
                        <div class="p-3 text-center text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            No hay encargados disponibles.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="listaEncargados">
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-2 encargado-item" data-search="sin ninguno">
                                <input type="radio" class="form-check-input mt-0" name="id_encargado" value="0" <?php echo empty($principalActualId) ? 'checked' : ''; ?>>
                                <span class="text-muted fst-italic small">— Sin encargado principal —</span>
                            </label>
                            <?php foreach ($encargados as $e):
                                $searchText = strtolower(($e['nombre'] ? $e['nombre'] : '') . ' ' . ($e['rut'] ? $e['rut'] : '') . ' ' . ($e['cargo'] ? $e['cargo'] : ''));
                                $esActual = ($principalActualId === (int)$e['id']);
                                $tieneOtras = ((int)$e['total_bodegas'] > 0);
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
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle ms-1" style="font-size:.6rem;">PRINCIPAL ACTUAL</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size:.72rem;">
                                                        <i class="bi bi-person-badge me-1"></i><?php echo h($e['rut'] ? $e['rut'] : $e['usuario']); ?>
                                                        <?php if (!empty($e['cargo'])): ?> · <?php echo h($e['cargo']); ?><?php endif; ?>
                                                        <?php if (!empty($e['unidad_nombre'])): ?> · <?php echo h($e['unidad_nombre']); ?><?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($tieneOtras && !$esActual): ?>
                                                    <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle" style="font-size:.65rem;" title="Gestiona otras bodegas">
                                                        <i class="bi bi-buildings me-1"></i><?php echo (int)$e['total_bodegas']; ?>
                                                    </span>
                                                <?php elseif (!$tieneOtras): ?>
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
                    <i class="bi bi-info-circle me-1"></i>
                    Aquí solo configuras el <strong>principal</strong>. Para agregar/quitar co-encargados usa la sección
                    <em>Gestionar encargados</em>.
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