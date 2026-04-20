<?php
// modulos/funcionarios/funcionarios_ver.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/bodegas_helpers.php';

require_login();
require_role('admin');

$id = (int)get('id');

$stmt = $pdo->prepare("
    SELECT f.*,
           un.nombre AS unidad_nombre,
           u.id AS usuario_id, u.usuario, u.rol AS usuario_rol,
           u.estado AS usuario_estado, u.id_unidad AS uid_unidad,
           u.created_at AS usuario_created,
           ub.nombre AS unidad_usuario_nombre
    FROM funcionarios f
    LEFT JOIN unidades_organizacionales un ON un.id = f.id_unidad
    LEFT JOIN usuarios u ON u.id_funcionario = f.id
    LEFT JOIN unidades_organizacionales ub ON ub.id = u.id_unidad
    WHERE f.id = ?
    LIMIT 1
");
$stmt->execute(array($id));
$f = $stmt->fetch();

if (!$f) { die('Funcionario no encontrado.'); }

$tieneUsuario = !empty($f['usuario_id']);
$uid          = $tieneUsuario ? (int)$f['usuario_id'] : 0;

// Bodegas M:N del usuario
$misBodegas = array();
if ($uid > 0) {
    $misBodegas = user_bodegas($uid);
}

$movimientos = 0;
$solicitudes = 0;
if ($tieneUsuario) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM movimientos_bodega WHERE id_usuario = ?");
    $stmt->execute(array($uid));
    $movimientos = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE id_usuario = ?");
    $stmt->execute(array($uid));
    $solicitudes = (int)$stmt->fetchColumn();
}

$rolLabel = array(
    'admin'       => array('Administrador', 'bg-danger bg-opacity-10 text-danger border-danger-subtle'),
    'bodega'      => array('Encargado',     'bg-primary bg-opacity-10 text-primary border-primary-subtle'),
    'solicitante' => array('Solicitante',   'bg-info bg-opacity-10 text-info border-info-subtle'),
);

$pageTitle = 'Detalle Funcionario';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb" class="small mb-1">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="funcionarios_lista.php" class="text-decoration-none">Funcionarios</a></li>
                <li class="breadcrumb-item active"><?php echo h($f['nombre']); ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-person-vcard text-primary me-2"></i><?php echo h($f['nombre']); ?>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <a href="funcionarios_editar.php?id=<?php echo (int)$f['id']; ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="funcionarios_lista.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Datos personales -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-person-badge text-primary me-2"></i>Datos del funcionario
                </h5>
            </div>
            <div class="card-body p-4">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Código</dt>
                    <dd class="col-sm-8 fw-medium"><?php echo h($f['codigo'] ? $f['codigo'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">RUT</dt>
                    <dd class="col-sm-8 fw-medium"><?php echo h($f['rut']); ?></dd>

                    <dt class="col-sm-4 text-muted">Email</dt>
                    <dd class="col-sm-8"><?php echo h($f['email'] ? $f['email'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Unidad</dt>
                    <dd class="col-sm-8"><?php echo h($f['unidad_nombre'] ? $f['unidad_nombre'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Cargo</dt>
                    <dd class="col-sm-8"><?php echo h($f['cargo'] ? $f['cargo'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Programa</dt>
                    <dd class="col-sm-8"><?php echo h($f['programa'] ? $f['programa'] : '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Estado</dt>
                    <dd class="col-sm-8">
                        <?php if ((int)$f['estado'] === 1): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">Inactivo</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Fecha registro</dt>
                    <dd class="col-sm-8"><?php echo h(date('d-m-Y H:i', strtotime($f['created_at']))); ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Acceso al sistema -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-shield-lock text-success me-2"></i>Acceso al sistema
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (!$tieneUsuario): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-shield-x fs-1 text-muted d-block mb-2"></i>
                        <p class="text-muted mb-3">Este funcionario no tiene acceso al sistema.</p>
                        <a href="funcionarios_editar.php?id=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-shield-plus me-1"></i> Habilitar acceso
                        </a>
                    </div>
                <?php else:
                    $rolInfo = isset($rolLabel[$f['usuario_rol']]) ? $rolLabel[$f['usuario_rol']] : array(ucfirst($f['usuario_rol']), 'bg-secondary bg-opacity-10 text-secondary');
                ?>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Usuario</dt>
                        <dd class="col-sm-8 fw-medium"><?php echo h($f['usuario']); ?></dd>

                        <dt class="col-sm-4 text-muted">Rol</dt>
                        <dd class="col-sm-8">
                            <span class="badge <?php echo $rolInfo[1]; ?> border px-2 py-1"><?php echo $rolInfo[0]; ?></span>
                        </dd>

                        <?php if ($f['usuario_rol'] === 'bodega'): ?>
                        <dt class="col-sm-4 text-muted">Bodegas</dt>
                        <dd class="col-sm-8">
                            <?php if (!$misBodegas): ?>
                                <span class="text-muted fst-italic">Sin asignar</span>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($misBodegas as $mb): ?>
                                        <span class="badge <?php echo ((int)$mb['es_principal'] === 1) ? 'bg-primary bg-opacity-10 text-primary border border-primary-subtle' : 'bg-light text-dark border'; ?>" style="font-size:.7rem;">
                                            <?php if ((int)$mb['es_principal'] === 1): ?><i class="bi bi-star-fill me-1"></i><?php endif; ?>
                                            <?php echo h($mb['codigo']); ?> — <?php echo h($mb['nombre']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </dd>
                        <?php endif; ?>

                        <?php if ($f['usuario_rol'] === 'solicitante' && !empty($f['unidad_usuario_nombre'])): ?>
                        <dt class="col-sm-4 text-muted">Unidad</dt>
                        <dd class="col-sm-8"><?php echo h($f['unidad_usuario_nombre']); ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4 text-muted">Estado acceso</dt>
                        <dd class="col-sm-8">
                            <?php if ((int)$f['usuario_estado'] === 1): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">Inactivo</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4 text-muted">Desde</dt>
                        <dd class="col-sm-8"><?php echo h(date('d-m-Y H:i', strtotime($f['usuario_created']))); ?></dd>
                    </dl>

                    <div class="mt-3 pt-3 border-top d-flex gap-2">
                        <a href="funcionarios_editar.php?id=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-key me-1"></i> Cambiar rol / bodegas / contraseña
                        </a>
                        <a href="funcionarios_lista.php?revocar_acceso=<?php echo (int)$f['id']; ?>"
                           class="btn btn-sm btn-outline-warning"
                           onclick="return confirm('¿Revocar el acceso al sistema? El funcionario se mantiene.');">
                            <i class="bi bi-shield-slash me-1"></i> Revocar acceso
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($tieneUsuario): ?>
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-activity text-info me-2"></i>Actividad en el sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <div class="p-3 rounded bg-light border">
                            <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Movimientos registrados</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($movimientos, 0, ',', '.'); ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="p-3 rounded bg-light border">
                            <p class="text-muted text-uppercase mb-1 fw-bold" style="font-size:.7rem;letter-spacing:.5px;">Solicitudes realizadas</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($solicitudes, 0, ',', '.'); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>