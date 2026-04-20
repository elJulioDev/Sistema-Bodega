<?php
// modulos/funcionarios/funcionarios_importar.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();
require_role('admin');

// --- Descarga de plantilla ---
if (isset($_GET['plantilla'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_funcionarios.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    fputcsv($output, array('Codigo', 'RUT', 'Nombre', 'Unidad', 'Cargo', 'Programa'), ';');

    fputcsv($output, array('001', '12345678-9', 'Juan Perez Gonzalez', 'Direccion de Transito', 'Conductor', 'Programa Municipal'), ';');
    fputcsv($output, array('002', '15987654-3', 'Maria Soto Rojas', 'Direccion de Administracion y Finanzas', 'Contadora', 'Gestion'), ';');
    fputcsv($output, array('003', '18555444-1', 'Pedro Diaz Munoz', 'Direccion de Obras Municipal', 'Inspector', ''), ';');
    fclose($output);
    exit;
}

$error = '';
$resultado = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error al subir el archivo.';
    } else {
        $f = fopen($archivo['tmp_name'], 'r');

        // Saltar BOM si existe
        $bom = fread($f, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($f);
        }

        fgetcsv($f, 2000, ';'); // Saltar cabecera

        // Cargar mapeo unidades (nombre en minusculas sin acentos => id)
        $unidadMap = array();
        $rs = $pdo->query("SELECT id, codigo, nombre FROM unidades_organizacionales WHERE estado = 1");
        foreach ($rs as $u) {
            $key = _normalizar_texto($u['nombre']);
            $unidadMap[$key] = (int)$u['id'];
            $unidadMap[strtolower($u['codigo'])] = (int)$u['id'];
        }

        $pdo->beginTransaction();
        $fila = 1;
        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $usuariosCreados = 0;
        $errores = array();

        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM funcionarios WHERE rut = ? LIMIT 1");
            $stmtInsert = $pdo->prepare("INSERT INTO funcionarios (codigo, rut, nombre, id_unidad, cargo, programa, estado)
                                         VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmtUpdate = $pdo->prepare("UPDATE funcionarios SET codigo=?, nombre=?, id_unidad=?, cargo=?, programa=? WHERE rut=?");

            // Busca usuario por RUT (campo usuario) O por id_funcionario
            $stmtChkUsuario = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmtInsertUsuario = $pdo->prepare("
                INSERT INTO usuarios
                    (id_funcionario, nombre, email, usuario, clave_hash, rol, id_unidad, id_bodega, estado)
                VALUES (?, ?, NULL, ?, ?, 'solicitante', ?, NULL, 1)
            ");
            // Actualiza id_funcionario si el usuario ya existe pero apunta a funcionario eliminado
            $stmtLinkUsuario = $pdo->prepare("UPDATE usuarios SET id_funcionario=?, id_unidad=? WHERE usuario=?");

            while (($datos = fgetcsv($f, 2000, ';')) !== FALSE) {
                $fila++;
                if (empty(array_filter($datos))) continue;

                $datos = array_map(function($valor) {
                    return mb_convert_encoding(trim($valor), 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
                }, $datos);

                if (count($datos) < 6) {
                    $errores[] = "Fila $fila: debe tener 6 columnas.";
                    $omitidos++;
                    continue;
                }

                $codigo    = $datos[0];
                $rut       = $datos[1];
                $nombre    = $datos[2];
                $unidadTxt = $datos[3];
                $cargo     = $datos[4];
                $programa  = $datos[5];

                if ($rut === '' || $nombre === '') {
                    $errores[] = "Fila $fila: RUT y nombre son obligatorios.";
                    $omitidos++;
                    continue;
                }

                // Mapear unidad
                $id_unidad = null;
                if ($unidadTxt !== '') {
                    $key = _normalizar_texto($unidadTxt);
                    if (isset($unidadMap[$key])) {
                        $id_unidad = $unidadMap[$key];
                    } else {
                        $errores[] = "Fila $fila: unidad no reconocida: \"$unidadTxt\"";
                    }
                }

                // ¿Existe el funcionario?
                $stmtCheck->execute(array($rut));
                $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                $stmtCheck->closeCursor(); // [FIX] cerrar cursor antes de continuar

                if ($existe) {
                    $stmtUpdate->execute(array($codigo, $nombre, $id_unidad, $cargo, $programa, $rut));
                    $actualizados++;
                    $idFuncionario = (int)$existe['id'];
                } else {
                    $stmtInsert->execute(array($codigo, $rut, $nombre, $id_unidad, $cargo, $programa));
                    $idFuncionario = (int)$pdo->lastInsertId();
                    $insertados++;
                }

                // ¿Ya tiene cuenta de usuario?
                $stmtChkUsuario->execute(array($rut));
                $usuarioExiste = $stmtChkUsuario->fetch(PDO::FETCH_ASSOC);
                $stmtChkUsuario->closeCursor(); // [FIX] cerrar cursor

                $claveAuto = ($codigo !== '') ? $codigo : $rut;
                $hashAuto  = password_hash($claveAuto, PASSWORD_BCRYPT);

                if (!$usuarioExiste) {
                    // Crear cuenta nueva
                    $ok = $stmtInsertUsuario->execute(array(
                        $idFuncionario, $nombre, $rut, $hashAuto, $id_unidad
                    ));
                    if ($ok) {
                        $usuariosCreados++;
                    } else {
                        // [FIX] capturar error silencioso de PDO
                        $info = $stmtInsertUsuario->errorInfo();
                        $errores[] = "Fila $fila: no se pudo crear usuario ($rut): " . $info[2];
                    }
                } else {
                    // [FIX] Re-vincular si el id_funcionario quedó huérfano (ej: DB fue limpiada parcialmente)
                    if ((int)$usuarioExiste['id'] !== $idFuncionario) {
                        $stmtLinkUsuario->execute(array($idFuncionario, $id_unidad, $rut));
                    }
                }
            }

            $pdo->commit();
            fclose($f);

            $resultado = array(
                'insertados'      => $insertados,
                'actualizados'    => $actualizados,
                'omitidos'        => $omitidos,
                'usuariosCreados' => $usuariosCreados,
                'errores'         => $errores
            );

        } catch (Exception $e) {
            $pdo->rollBack();
            fclose($f);
            $error = 'Error al procesar el archivo: ' . $e->getMessage();
        }
    }
}

// Helper para normalizar texto (remueve acentos, minusculas)
// Prefijo _normalizar_texto para evitar colision con functions.php
function _normalizar_texto($txt) {
    $txt = mb_strtolower(trim($txt), 'UTF-8');
    $txt = str_replace(
        array('á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'),
        array('a','e','i','o','u','n','a','e','i','o','u','n'),
        $txt
    );
    return preg_replace('/\s+/', ' ', $txt);
}

$pageTitle = 'Importar Funcionarios';
require_once __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Importar Funcionarios desde CSV
    </h1>
    <a href="funcionarios_lista.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($resultado): ?>
                    <div class="alert alert-success mb-3">
                        <h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Importación completada</h5>
                        <ul class="mb-0 small">
                            <li><strong><?php echo $resultado['insertados']; ?></strong> funcionarios nuevos insertados</li>
                            <li><strong><?php echo $resultado['actualizados']; ?></strong> funcionarios existentes actualizados (por RUT)</li>
                            <li><strong><?php echo $resultado['omitidos']; ?></strong> filas omitidas</li>
                            <li><strong><?php echo $resultado['usuariosCreados']; ?></strong> cuentas de acceso creadas</li>
                        </ul>
                    </div>

                    <?php if (!empty($resultado['errores'])): ?>
                        <div class="alert alert-warning">
                            <strong>Advertencias (<?php echo count($resultado['errores']); ?>):</strong>
                            <ul class="mb-0 small mt-2" style="max-height:200px;overflow-y:auto;">
                                <?php foreach ($resultado['errores'] as $er): ?>
                                    <li><?php echo h($er); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Archivo CSV</label>
                        <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                        <div class="form-text">
                            Separador: <code>;</code> (punto y coma). Codificación UTF-8.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Procesar archivo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-info-circle me-2"></i>Instrucciones
                </h6>

                <ol class="small ps-3 mb-3">
                    <li class="mb-2">Descarga la plantilla CSV con las columnas correctas.</li>
                    <li class="mb-2">Completa los datos respetando los nombres exactos de las unidades.</li>
                    <li class="mb-2">Si un RUT ya existe, se actualizarán sus datos (no se duplica).</li>
                    <li class="mb-2">Cada funcionario recibe acceso automático con rol <strong>solicitante</strong>.</li>
                    <li>La contraseña inicial es el <strong>código RRHH</strong> (o el RUT si no tiene código).</li>
                </ol>

                <a href="?plantilla=1" class="btn btn-outline-success w-100 mb-3">
                    <i class="bi bi-download me-1"></i> Descargar Plantilla CSV
                </a>

                <hr>

                <h6 class="fw-bold text-dark mb-2 small">Columnas requeridas</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><code>Codigo</code> — Código interno RRHH (contraseña inicial)</li>
                    <li><code>RUT</code> — Formato 12345678-9 (nombre de usuario)</li>
                    <li><code>Nombre</code> — Nombre completo (obligatorio)</li>
                    <li><code>Unidad</code> — Nombre exacto o código</li>
                    <li><code>Cargo</code> — Cargo del funcionario</li>
                    <li><code>Programa</code> — Programa asignado</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>