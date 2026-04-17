<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

// --- LÓGICA DE DESCARGA DE PLANTILLA ---
if (isset($_GET['plantilla'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_productos_v3.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM para Excel
    
    // CABECERAS (7 columnas exactas)
    fputcsv($output, array('Codigo', 'Nombre', 'Categoria', 'Unidad_Medida', 'Activo_Fijo_0_o_1', 'Stock_Minimo', 'Descripcion'), ';');
    
    // FILA DE EJEMPLO
    // Nota: 'Unidad' y '0' ahora están en sus columnas correctas (4ta y 5ta)
    fputcsv($output, array('PROD-100', 'Clavos 2 Pulgadas', 'Ferretería', 'Unidad', '0', '50.00', 'Clavos de acero'), ';');
    fclose($output);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];

    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $f = fopen($archivo['tmp_name'], 'r');
        fgetcsv($f, 1000, ';'); // Saltar cabecera
        
        $pdo->beginTransaction();
        $fila = 1;
        $importados = 0;
        
        try {
            $stmtTipo = $pdo->prepare("SELECT id FROM tipos_producto WHERE nombre = ? LIMIT 1");
            $stmtUnidad = $pdo->prepare("SELECT id FROM unidades_medida WHERE nombre = ? LIMIT 1");
            $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? LIMIT 1");
            $stmtInsert = $pdo->prepare("INSERT INTO productos (codigo, nombre, id_tipo_producto, id_unidad_medida, activo_fijo, stock_minimo, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

            while (($datos = fgetcsv($f, 1000, ';')) !== FALSE) {
                $fila++;
                if (empty(array_filter($datos))) continue;

                // VALIDACIÓN DE COLUMNAS: Deben ser 7
                if (count($datos) < 7) {
                    throw new Exception("Fila {$fila}: El archivo no tiene las 7 columnas requeridas. Por favor, descarga la nueva plantilla.");
                }

                $codigo       = trim($datos[0]);
                $nombre       = trim($datos[1]);
                $cat_nombre   = trim($datos[2]);
                $uni_nombre   = trim($datos[3]);
                $activo_fijo  = (int)trim($datos[4]); // Columna 5 (índice 4)
                $stock_minimo = (float)str_replace(',', '.', $datos[5]); // Columna 6 (índice 5)
                $descripcion  = trim($datos[6]); // Columna 7 (índice 6)

                if ($codigo === '' || $nombre === '') throw new Exception("Fila {$fila}: Código y Nombre son obligatorios.");

                // Validar duplicado
                $stmtCheck->execute(array($codigo));
                if ($stmtCheck->fetch()) throw new Exception("Fila {$fila}: El código '{$codigo}' ya existe en el sistema.");

                // Buscar IDs relacionales
                $stmtTipo->execute(array($cat_nombre));
                $resTipo = $stmtTipo->fetch();
                $id_tipo = $resTipo ? $resTipo['id'] : null;

                $stmtUnidad->execute(array($uni_nombre));
                $resUnidad = $stmtUnidad->fetch();
                $id_unidad = $resUnidad ? $resUnidad['id'] : null;

                $stmtInsert->execute(array($codigo, $nombre, $id_tipo, $id_unidad, $activo_fijo, $stock_minimo, $descripcion));
                $importados++;
            }

            $pdo->commit();
            set_flash('success', "¡Éxito! Se han importado {$importados} productos.");
            redirect('productos_lista.php');

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
        fclose($f);
    }
}

$pageTitle = 'Importar Productos';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Importación Masiva</h1>
        <a href="productos_lista.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>

    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary">1. Descargar Nueva Plantilla</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Es obligatorio usar esta versión (V3) porque incluye la separación de Categoría y Unidad.</p>
                    <a href="?plantilla=1" class="btn btn-success w-100 fw-bold">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Descargar Plantilla V3
                    </a>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-dark">Reglas de datos</h6>
                </div>
                <div class="card-body small">
                    <ul class="text-secondary ps-3">
                        <li><strong>Categoria/Unidad:</strong> Deben existir previamente en el sistema.</li>
                        <li><strong>Activo_Fijo:</strong> Usa <code>1</code> para Sí y <code>0</code> para No.</li>
                        <li><strong>Stock_Minimo:</strong> Usa números decimales (ej: 10.5).</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <?php if ($error): ?>
                <div class="alert alert-danger shadow-sm border-start border-4 border-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary">2. Cargar Archivo</h6>
                </div>
                <div class="card-body p-4 text-center">
                    <form method="post" enctype="multipart/form-data">
                        <div class="py-4 border border-2 border-dashed rounded-3 bg-light mb-4">
                            <i class="bi bi-upload fs-1 text-secondary"></i>
                            <input class="form-control w-75 mx-auto mt-3" type="file" name="archivo_csv" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-cloud-arrow-up-fill me-2"></i>Importar ahora
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>