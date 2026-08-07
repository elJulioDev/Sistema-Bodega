<?php
// modulos/configuraciones/editar.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/settings.php';

require_login();
require_role('admin');

$claves = array(
    'site_nombre'      => 'Nombre del sistema',
    'site_descripcion' => 'Descripción (subtítulo)',
    'site_icono'       => 'Ícono (clase Bootstrap Icons)',
    'site_color'       => 'Color principal (marca)',
    'site_color_secundario' => 'Color secundario (acento)',
    'tema_default'     => 'Tema por defecto',
    'org_nombre'       => 'Nombre de la organización',
    'org_email_dominio' => 'Dominio de correo de la organización',
);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = array();
    foreach ($claves as $clave => $etiqueta) {
        $datos[$clave] = (string)post($clave);
    }

    if ($datos['site_nombre'] === '') {
        $error = 'El nombre del sistema no puede quedar vacío.';
    } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $datos['site_color'])) {
        $error = 'El color principal debe tener formato hexadecimal (#rrggbb).';
    } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $datos['site_color_secundario'])) {
        $error = 'El color secundario debe tener formato hexadecimal (#rrggbb).';
    } elseif (!in_array($datos['tema_default'], array('light', 'dark', 'auto'), true)) {
        $error = 'Tema por defecto inválido.';
    } else {
        foreach ($datos as $clave => $valor) {
            site_save_config($clave, $valor);
        }
        set_flash('success', 'Configuración guardada correctamente.');
        redirect('editar.php');
    }
}

$pageTitle = 'Personalización';
require_once __DIR__ . '/../../inc/header.php';
?>

<?php ui_page_header(
    'bi-palette',
    'Personalización',
    'Configura la identidad visual y los textos del sistema.'
); ?>

<?php if ($error !== ''): ?>
    <?php ui_alert('danger', $error); ?>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="post" class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php echo csrf_field(); ?>

                <h6 class="fw-bold text-body mb-3">
                    <i class="bi bi-building me-2"></i>Identidad
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Nombre del sistema</label>
                        <input type="text" name="site_nombre" class="form-control" required
                               value="<?php echo h(site_config('site_nombre', 'Sistema de Bodega')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Descripción (subtítulo)</label>
                        <input type="text" name="site_descripcion" class="form-control"
                               value="<?php echo h(site_config('site_descripcion', 'Panel de Administración y Control')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Ícono (clase Bootstrap Icons)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi <?php echo h(site_config('site_icono', 'bi-box-seam')); ?>"></i></span>
                            <input type="text" name="site_icono" class="form-control"
                                   placeholder="bi-box-seam"
                                   value="<?php echo h(site_config('site_icono', 'bi-box-seam')); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Dominio de correo de la organización</label>
                        <input type="text" name="org_email_dominio" class="form-control"
                               placeholder="coltauco.cl"
                               value="<?php echo h(site_config('org_email_dominio', '')); ?>">
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold text-body mb-3">
                    <i class="bi bi-palette me-2"></i>Apariencia
                </h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Color principal</label>
                        <div class="input-group">
                            <span class="input-group-text p-1">
                                <input type="color" id="colorPicker" class="form-control form-control-color border-0 p-0" style="width:2rem;height:2rem;" value="<?php echo h(site_config('site_color', '#0d6efd')); ?>">
                            </span>
                            <input type="text" name="site_color" id="colorHex" class="form-control"
                                   value="<?php echo h(site_config('site_color', '#0d6efd')); ?>"
                                   pattern="#[0-9a-fA-F]{6}" title="#rrggbb">
                        </div>
                        <div class="form-text">Aplica a la barra superior, botones y acentos.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Color secundario (acento)</label>
                        <div class="input-group">
                            <span class="input-group-text p-1">
                                <input type="color" id="colorPickerSec" class="form-control form-control-color border-0 p-0" style="width:2rem;height:2rem;" value="<?php echo h(site_config('site_color_secundario', '#8b5cf6')); ?>">
                            </span>
                            <input type="text" name="site_color_secundario" id="colorHexSec" class="form-control"
                                   value="<?php echo h(site_config('site_color_secundario', '#8b5cf6')); ?>"
                                   pattern="#[0-9a-fA-F]{6}" title="#rrggbb">
                        </div>
                        <div class="form-text">Detalles como la línea de la barra superior y el indicador del menú.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Tema por defecto</label>
                        <select name="tema_default" class="form-select">
                            <option value="light" <?php echo site_config('tema_default', 'auto') === 'light' ? 'selected' : ''; ?>>Claro</option>
                            <option value="dark"  <?php echo site_config('tema_default', 'auto') === 'dark'  ? 'selected' : ''; ?>>Oscuro</option>
                            <option value="auto"  <?php echo site_config('tema_default', 'auto') === 'auto'  ? 'selected' : ''; ?>>Automático (sigue el sistema)</option>
                        </select>
                        <div class="form-text">Cada usuario puede cambiarlo desde el menú de su perfil.</div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold text-body mb-3">
                    <i class="bi bi-org me-2"></i>Organización
                </h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary">Nombre de la organización</label>
                        <input type="text" name="org_nombre" class="form-control"
                               placeholder="Municipalidad de Coltauco"
                               value="<?php echo h(site_config('org_nombre', '')); ?>">
                        <div class="form-text">Se muestra en la pantalla de inicio de sesión y en los comprobantes.</div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body d-flex justify-content-end gap-2">
                <a href="<?php echo BASE_URL . '/index.php'; ?>" class="btn btn-light border">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3">
                    <i class="bi bi-eye me-2"></i>Vista previa
                </h6>
                <div id="previewCard" class="rounded-3 border p-3" style="transition:background .2s, border-color .2s;">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span id="previewIcon" class="d-inline-flex align-items-center justify-content-center rounded-2 text-white" style="width:2.2rem;height:2.2rem;">
                            <i class="bi <?php echo h(site_config('site_icono', 'bi-box-seam')); ?>"></i>
                        </span>
                        <div class="lh-sm">
                            <strong id="previewNombre" class="d-block"><?php echo h(site_config('site_nombre', 'Sistema de Bodega')); ?></strong>
                            <small id="previewOrg" class="text-muted"><?php echo h(site_config('org_nombre', '')); ?></small>
                        </div>
                    </div>
                    <div id="previewBar" class="rounded-2 mb-2" style="height:.5rem;"></div>
                    <div class="d-flex gap-2">
                        <span id="previewBtn" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Ingresar</span>
                        <span class="btn btn-outline-secondary btn-sm">Cancelar</span>
                    </div>
                </div>
                <p class="small text-muted mt-3 mb-0">
                    La vista previa se actualiza mientras editas. El color y los textos se aplican en todo el sistema al guardar.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var picker   = document.getElementById('colorPicker');
    var hex      = document.getElementById('colorHex');
    var picker2  = document.getElementById('colorPickerSec');
    var hex2     = document.getElementById('colorHexSec');
    var previewCard = document.getElementById('previewCard');
    var previewBar  = document.getElementById('previewBar');
    var previewBtn  = document.getElementById('previewBtn');
    var previewIcon = document.getElementById('previewIcon');
    var previewNombre = document.getElementById('previewNombre');
    var previewOrg = document.getElementById('previewOrg');

    function oscurecer(hexColor, factor) {
        var h = hexColor.replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        if (!/^[0-9a-fA-F]{6}$/.test(h)) return '#0a58ca';
        var r = Math.max(0, Math.round(parseInt(h.substr(0, 2), 16) * (1 - factor)));
        var g = Math.max(0, Math.round(parseInt(h.substr(2, 2), 16) * (1 - factor)));
        var b = Math.max(0, Math.round(parseInt(h.substr(4, 2), 16) * (1 - factor)));
        return '#' + r.toString(16).padStart(2, '0') + g.toString(16).padStart(2, '0') + b.toString(16).padStart(2, '0');
    }

    function actualizar() {
        var color  = /^#[0-9a-fA-F]{6}$/.test(hex.value)  ? hex.value  : '#0d6efd';
        var color2 = /^#[0-9a-fA-F]{6}$/.test(hex2.value) ? hex2.value : '#8b5cf6';
        var deep   = oscurecer(color, 0.18);
        previewBar.style.background = color2;
        previewBtn.style.background = 'linear-gradient(135deg, ' + deep + ', ' + color + ')';
        previewBtn.style.borderColor = color;
        previewIcon.style.background = color;
        previewNombre.textContent = document.querySelector('input[name="site_nombre"]').value || 'Sistema de Bodega';
        previewOrg.textContent = document.querySelector('input[name="org_nombre"]').value || '';
    }

    function vincularPicker(p, h) {
        p.addEventListener('input', function () { h.value = p.value; actualizar(); });
        h.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(h.value)) p.value = h.value;
            actualizar();
        });
    }

    vincularPicker(picker, hex);
    vincularPicker(picker2, hex2);

    ['site_nombre', 'org_nombre'].forEach(function (name) {
        var el = document.querySelector('input[name="' + name + '"]');
        if (el) el.addEventListener('input', actualizar);
    });
    actualizar();
})();
</script>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>
