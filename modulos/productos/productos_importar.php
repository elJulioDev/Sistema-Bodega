<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

// --- LÓGICA DE DESCARGA DE PLANTILLA ---
if (isset($_GET['plantilla'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_productos.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM para Excel
    
    // CABECERAS (6 columnas)
    fputcsv($output, array('Codigo', 'Nombre', 'Unidad_Medida', 'Activo_Fijo_0_o_1', 'Stock_Minimo', 'Descripcion'), ';');
    
    // FILAS DE EJEMPLO
    fputcsv($output, array('PROD-200', 'Clavos 2 Pulgadas', 'Unidad', '0', '50.00', 'Clavos de acero galvanizado'), ';');
    fputcsv($output, array('PROD-201', 'Resma Papel Carta', 'Paquete', '0', '20.00', 'Resma 500 hojas'), ';');
    fputcsv($output, array('PROD-202', 'Notebook HP', 'Unidad', '1', '2.00', 'Equipo computacional'), ';');
    fclose($output);
    exit;
}

$error = '';
$resultados = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];

    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $f = fopen($archivo['tmp_name'], 'r');
        
        // Saltar BOM si existe
        $bom = fread($f, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($f);
        }
        
        fgetcsv($f, 1000, ';'); // Saltar cabecera
        
        $pdo->beginTransaction();
        $fila = 1;
        $importados = 0;
        $omitidos = 0;
        
        try {
            $stmtUnidad = $pdo->prepare("SELECT id FROM unidades_medida WHERE (nombre = ? OR codigo = ?) AND estado = 1 LIMIT 1");
            $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? LIMIT 1");
            $stmtInsert = $pdo->prepare("INSERT INTO productos (codigo, nombre, id_unidad_medida, activo_fijo, stock_minimo, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?, 1)");

            while (($datos = fgetcsv($f, 1000, ';')) !== FALSE) {
                $fila++;
                if (empty(array_filter($datos))) continue;

                // VALIDACIÓN DE COLUMNAS: Deben ser 6
                if (count($datos) < 6) {
                    throw new Exception("Fila {$fila}: El archivo debe tener 6 columnas. Descarga la nueva plantilla.");
                }

                $codigo       = trim($datos[0]);
                $nombre       = trim($datos[1]);
                $uni_nombre   = trim($datos[2]);
                $activo_fijo  = (int)trim($datos[3]);
                $stock_minimo = (float)str_replace(',', '.', $datos[4]);
                $descripcion  = trim($datos[5]);

                if ($codigo === '' || $nombre === '') {
                    throw new Exception("Fila {$fila}: Código y Nombre son obligatorios.");
                }

                // Validar duplicado
                $stmtCheck->execute(array($codigo));
                if ($stmtCheck->fetch()) {
                    $omitidos++;
                    continue;
                }

                // Buscar unidad (por nombre o código)
                $stmtUnidad->execute(array($uni_nombre, $uni_nombre));
                $resUnidad = $stmtUnidad->fetch();
                if (!$resUnidad) {
                    throw new Exception("Fila {$fila}: La unidad de medida '{$uni_nombre}' no existe en el sistema.");
                }
                $id_unidad = $resUnidad['id'];

                $stmtInsert->execute(array(
                    $codigo, 
                    $nombre, 
                    $id_unidad, 
                    $activo_fijo, 
                    $stock_minimo, 
                    ($descripcion !== '' ? $descripcion : null)
                ));
                $importados++;
            }

            $pdo->commit();
            $msg = "¡Importación completada! {$importados} productos importados";
            if ($omitidos > 0) { $msg .= ", {$omitidos} omitidos (códigos duplicados)"; }
            $msg .= '.';
            set_flash('success', $msg);
            redirect('productos_lista.php');

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
        fclose($f);
    } else {
        $error = 'Error al subir el archivo. Intenta nuevamente.';
    }
}

// Unidades disponibles para mostrar
$unidadesDisp = $pdo->query("SELECT codigo, nombre FROM unidades_medida WHERE estado = 1 ORDER BY nombre")->fetchAll();

$pageTitle = 'Importar Productos';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 text-dark fw-bold"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Importación Masiva</h1>
        <small class="text-muted">Carga múltiples productos desde un archivo CSV</small>
    </div>
    <a href="productos_lista.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-start border-4 border-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-primary rounded-circle me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;">1</span>
                    <h6 class="fw-bold mb-0">Descarga la plantilla</h6>
                </div>
                <p class="text-muted small mb-3">Usa la plantilla oficial con las 6 columnas requeridas.</p>
                <a href="?plantilla=1" class="btn btn-success w-100 fw-semibold">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>Descargar plantilla CSV
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 border-0">
                <h6 class="m-0 fw-bold text-dark small"><i class="bi bi-info-circle me-1"></i> Reglas de datos</h6>
            </div>
            <div class="card-body p-3 small">
                <ul class="text-secondary ps-3 mb-3">
                    <li><strong>Código:</strong> único, obligatorio.</li>
                    <li><strong>Unidad_Medida:</strong> debe existir previamente (nombre o código).</li>
                    <li><strong>Activo_Fijo:</strong> <code>1</code> = Sí, <code>0</code> = No.</li>
                    <li><strong>Stock_Minimo:</strong> números decimales (ej: 10.5).</li>
                    <li><strong>Separador:</strong> punto y coma (;).</li>
                    <li class="text-warning"><strong>Duplicados:</strong> se omiten automáticamente.</li>
                </ul>
                
                <hr class="my-2">
                <p class="fw-bold small text-dark mb-1">Unidades disponibles:</p>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($unidadesDisp as $u): ?>
                        <span class="badge bg-light text-dark border"><?php echo h($u['nombre']); ?> <span class="text-muted">(<?php echo h($u['codigo']); ?>)</span></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge bg-primary rounded-circle me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;">2</span>
                    <h6 class="fw-bold mb-0">Carga el archivo</h6>
                </div>
                
                <form method="post" enctype="multipart/form-data">
                    <div class="py-5 border border-2 border-dashed rounded-3 bg-light mb-3 text-center">
                        <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                        <p class="text-muted mb-2">Selecciona tu archivo CSV</p>
                        <input class="form-control w-75 mx-auto" type="file" name="archivo_csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Importar productos
                    </button>
                </form>

                <div class="alert alert-info mt-3 small mb-0 py-2">
                    <i class="bi bi-lightbulb me-1"></i> <strong>Tip:</strong> Si tienes problemas de codificación, abre el CSV en Excel y guárdalo como "CSV UTF-8".
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>