<?php
// PASSWORD
define('ADMIN_PASSWORD', '1234');

session_start();

// Check if user is trying to login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    if($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "Contraseña incorrecta. Inténtalo de nuevo.";
    }
}

// Check if user is trying to logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// If not logged in, show login form
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin - Login</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="login-container">
            <h1>🔐 Panel de Administración</h1>
            <p>Introduce la contraseña para acceder</p>
            <form method="POST">
                <input type="password" name="password" placeholder="Contraseña" required autofocus>
                <button type="submit">Entrar</button>
                <a href="index.php" class="return-btn">← Volver a la Herramienta</a>
            </form>
            <?php if (isset($login_error)): ?>
                <p class="error-message"><?php echo $login_error; ?></p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// USER LOGGED IN
//===============

// MASTER DATABASE FILES
define('VENDIDAS_FILE', 'tabla_vendidas.csv');
define('ACTIVADAS_FILE', 'tabla_activadas.csv');

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_csv'])) {
    $upload_type = $_POST['upload_type'] ?? '';
    $target_file = '';
    
    // Determine which file to replace
    if ($upload_type === 'vendidas') {
        $target_file = VENDIDAS_FILE;
    } elseif ($upload_type === 'activadas') {
        $target_file = ACTIVADAS_FILE;
    }
    
    if (empty($target_file)) {
        $error_message = "ERROR: Tipo de archivo no especificado.";
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $error_message = "ERROR: No se seleccionó ningún archivo.";
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "ERROR: Error al subir el archivo.";
    } else {
        $uploaded_file = $_FILES['csv_file'];
        
        // Validate file extension
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'csv') {
            $error_message = "ERROR: Solo se permiten archivos CSV.";
        } else {
            // Validate CSV structure
            $validation_result = validate_csv_structure($uploaded_file['tmp_name'], $upload_type);
            
            if ($validation_result['valid']) {
                // Create backup of existing file
                if (file_exists($target_file)) {
                    $backup_name = 'backup_' . date('Y-m-d_His') . '_' . $target_file;
                    if (!copy($target_file, $backup_name)) {
                        $error_message = "ERROR: No se pudo crear el backup del archivo anterior.";
                    }
                }
                
                // Move uploaded file to replace the target file
                if (!isset($error_message)) {
                    if (move_uploaded_file($uploaded_file['tmp_name'], $target_file)) {
                        $success_message = "✅ Archivo actualizado correctamente: <strong>" . htmlspecialchars($target_file) . "</strong>";
                        if (isset($backup_name)) {
                            $success_message .= "<br>📦 Backup guardado como: <strong>" . htmlspecialchars($backup_name) . "</strong>";
                        }
                    } else {
                        $error_message = "ERROR: No se pudo guardar el archivo.";
                    }
                }
            } else {
                $error_message = "ERROR: Estructura del CSV inválida. " . $validation_result['message'];
            }
        }
    }
}

/**
 * Validate CSV structure to ensure it has the required columns
 */
function validate_csv_structure($file_path, $type) {
    $delimiter = ';';
    
    if (($handle = fopen($file_path, 'r')) === FALSE) {
        return ['valid' => false, 'message' => 'No se pudo abrir el archivo.'];
    }
    
    // Read headers
    $headers = fgetcsv($handle, 1000, $delimiter);
    fclose($handle);
    
    if ($headers === FALSE || empty($headers)) {
        return ['valid' => false, 'message' => 'No se pudo leer el encabezado del archivo.'];
    }
    
    // Required columns based on file type
    $required_columns = [];
    
    if ($type === 'vendidas') {
        $required_columns = ['GPV', 'PDV', 'Vendidas FTTH', 'Vendidas Móvil'];
    } elseif ($type === 'activadas') {
        $required_columns = ['GPV', 'PDV', 'Activadas FTTH', 'Activadas Móvil'];
    }
    
    // Check if all required columns exist
    foreach ($required_columns as $required_col) {
        if (!in_array($required_col, $headers)) {
            return [
                'valid' => false, 
                'message' => "Falta la columna requerida: <strong>{$required_col}</strong>"
            ];
        }
    }
    
    return ['valid' => true, 'message' => 'Estructura válida.'];
}

// Function to get all GPV monthly CSV files
function get_all_monthly_files() {
    $files = [];
    $pattern = '*_*_*.xlsx'; // Matches: GPVNAME_Month_Year.xlsx

    foreach (glob($pattern) as $filename) {
        // Skip the master database files (they remain CSV)
        if ($filename === VENDIDAS_FILE || $filename === ACTIVADAS_FILE) {
            continue;
        }

        $files[] = [
            'name' => $filename,
            'size' => filesize($filename),
            'date' => filemtime($filename),
            'readable_size' => format_bytes(filesize($filename)),
            'readable_date' => date('d/m/Y H:i:s', filemtime($filename))
        ];
    }

    // Sort by date (newest first)
    usort($files, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    return $files;
}

// Function to format bytes into human-readable format
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Get info about master database files
function get_database_file_info($filename) {
    if (file_exists($filename)) {
        return [
            'exists' => true,
            'size' => format_bytes(filesize($filename)),
            'date' => date('d/m/Y H:i:s', filemtime($filename)),
            'rows' => count_csv_rows($filename)
        ];
    }
    return ['exists' => false];
}

// Count rows in CSV (excluding header)
function count_csv_rows($filename) {
    if (!file_exists($filename)) return 0;
    
    $count = 0;
    if (($handle = fopen($filename, 'r')) !== FALSE) {
        // Skip header
        fgetcsv($handle, 1000, ';');
        
        while (fgetcsv($handle, 1000, ';') !== FALSE) {
            $count++;
        }
        fclose($handle);
    }
    return $count;
}

// Handle individual file download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $file = basename($_GET['download']); // Security: prevent directory traversal
    
    if (file_exists($file) && (strpos($file, '_') !== false)) { // Basic validation
        $file_extension = pathinfo($file, PATHINFO_EXTENSION);
        
        if ($file_extension === 'xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } else {
            header('Content-Type: application/csv'); // Fallback for CSV
        }
        
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// Handle ZIP download of all files
if (isset($_GET['download_all'])) {
    $files = get_all_monthly_files();
    
    if (count($files) > 0) {
        $zip_filename = 'registros_todos_' . date('Y-m-d') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                $zip->addFile($file['name'], $file['name']);
            }
            $zip->close();
            
            // Send ZIP file to browser
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
            header('Content-Length: ' . filesize($zip_filename));
            readfile($zip_filename);
            
            // Delete temporary ZIP file
            unlink($zip_filename);
            exit;
        } else {
            $error_message = "ERROR: No se pudo crear el archivo ZIP.";
        }
    } else {
        $error_message = "No hay archivos para descargar.";
    }
}

// Handle file deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $file = basename($_GET['delete']);
    
    if (file_exists($file) && strpos($file, '_') !== false) {
        if (unlink($file)) {
            $success_message = "Archivo eliminado correctamente: " . htmlspecialchars($file);
        } else {
            $error_message = "ERROR: No se pudo eliminar el archivo.";
        }
    }
}

// Handle backup file deletion
if (isset($_GET['delete_backup']) && !empty($_GET['delete_backup'])) {
    $file = basename($_GET['delete_backup']);
    
    if (file_exists($file) && strpos($file, 'backup_') === 0) {
        if (unlink($file)) {
            $success_message = "Backup eliminado correctamente: " . htmlspecialchars($file);
        } else {
            $error_message = "ERROR: No se pudo eliminar el backup.";
        }
    }
}

// Get all files
$monthly_files = get_all_monthly_files();

// Get backup files
$backup_files = glob('backup_*');
usort($backup_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Group files by month for better organization
$grouped_files = [];
foreach ($monthly_files as $file) {
    // Extract month_year from filename (e.g., "November_2025" from "JUANLU_November_2025.csv")
    $parts = explode('_', $file['name']);
    if (count($parts) >= 3) {
        $month_year = $parts[count($parts)-2] . '_' . str_replace(['.csv', '.xlsx'], '', $parts[count($parts)-1]);
        if (!isset($grouped_files[$month_year])) {
            $grouped_files[$month_year] = [];
        }
        $grouped_files[$month_year][] = $file;
    }
}

// Get database files info
$vendidas_info = get_database_file_info(VENDIDAS_FILE);
$activadas_info = get_database_file_info(ACTIVADAS_FILE);
?>
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gestión de Archivos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>📊 Panel de Administración</h1>
                <p>Gestión de Archivos y Bases de Datos</p>
            </div>
        </div>
        
        <div class="admin-content">
            <?php if (isset($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="admin-stats">
                <div class="stat-card">
                    <h3>Total de Registros</h3>
                    <p><?php echo count($monthly_files); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Meses Registrados</h3>
                    <p><?php echo count($grouped_files); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Tamaño Total</h3>
                    <p><?php 
                        $total_size = array_sum(array_column($monthly_files, 'size'));
                        echo format_bytes($total_size);
                    ?></p>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="admin-actions">
                <a href="index.php" class="btn-admin btn-primary">← Volver a la Herramienta</a>
                <?php if (count($monthly_files) > 0): ?>
                    <a href="?download_all=1" class="btn-admin btn-success">📦 Descargar Todos (ZIP)</a>
                <?php endif; ?>
            </div>
            
            <?php if (count($monthly_files) > 0): ?>
                <?php foreach ($grouped_files as $month_year => $files): ?>
                    <div class="month-group">
                        <div class="month-header">
                            📅 <?php echo str_replace('_', ' ', $month_year); ?>
                        </div>
                        
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre del Archivo</th>
                                    <th>Tamaño</th>
                                    <th>Última Modificación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($file['name']); ?></strong></td>
                                        <td><?php echo $file['readable_size']; ?></td>
                                        <td><?php echo $file['readable_date']; ?></td>
                                        <td>
                                            <div class="file-actions">
                                                <a href="?download=<?php echo urlencode($file['name']); ?>" 
                                                   class="btn-admin btn-download">
                                                   ⬇️ Descargar
                                                </a>
                                                <a href="?delete=<?php echo urlencode($file['name']); ?>" 
                                                   class="btn-admin btn-danger"
                                                   onclick="return confirm('¿Estás seguro de que quieres eliminar este archivo? Esta acción no se puede deshacer.');">
                                                   🗑️ Eliminar
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <span>📁</span>
                    <p>No hay archivos de registro todavía.</p>
                    <p class="empty-subtitle">
                        Los archivos aparecerán aquí cuando los GPVs empiecen a usar la herramienta.
                    </p>
                </div>
            <?php endif; ?>
            
            <hr class="section-divider">
            
            <!-- DATABASE UPLOAD SECTION -->
            <div class="upload-section">
                <h2>📤 Actualizar Bases de Datos Maestras</h2>
                <p class="upload-info">Sube archivos .CSV para actulizar la base de datos, se creará una copia de seguridad automática del archivo anterior.</p>
                
                <div class="database-cards">
                    <!-- VENDIDAS DATABASE -->
                    <div class="database-card">
                        <h3>📋 Vendidas (FTTH y Móvil)</h3>
                        <div class="database-info">
                            <?php if ($vendidas_info['exists']): ?>
                                <p><strong>Estado:</strong> <span class="status-active">Activo</span></p>
                                <p><strong>Tamaño:</strong> <?php echo $vendidas_info['size']; ?></p>
                                <p><strong>Registros:</strong> <?php echo $vendidas_info['rows']; ?></p>
                                <p><strong>Última modificación:</strong> <?php echo $vendidas_info['date']; ?></p>
                            <?php else: ?>
                                <p><strong>Estado:</strong> <span class="status-inactive">No existe</span></p>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="upload_csv" value="1">
                            <input type="hidden" name="upload_type" value="vendidas">
                            <div class="file-input-wrapper">
                                <input type="file" name="csv_file" accept=".csv" required id="file-vendidas">
                                <label for="file-vendidas" class="file-label">Seleccionar archivo CSV</label>
                            </div>
                            <button type="submit" class="btn-upload">Actualizar Vendidas</button>
                        </form>
                        <p class="csv-requirements">
                            <strong>Columnas requeridas:</strong> GPV, PDV, Vendidas FTTH, Vendidas Móvil
                        </p>
                    </div>
                    
                    <!-- ACTIVADAS DATABASE -->
                    <div class="database-card">
                        <h3>📋 Activadas (FTTH y Móvil)</h3>
                        <div class="database-info">
                            <?php if ($activadas_info['exists']): ?>
                                <p><strong>Estado:</strong> <span class="status-active">Activo</span></p>
                                <p><strong>Tamaño:</strong> <?php echo $activadas_info['size']; ?></p>
                                <p><strong>Registros:</strong> <?php echo $activadas_info['rows']; ?></p>
                                <p><strong>Última modificación:</strong> <?php echo $activadas_info['date']; ?></p>
                            <?php else: ?>
                                <p><strong>Estado:</strong> <span class="status-inactive">No existe</span></p>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="upload_csv" value="1">
                            <input type="hidden" name="upload_type" value="activadas">
                            <div class="file-input-wrapper">
                                <input type="file" name="csv_file" accept=".csv" required id="file-activadas">
                                <label for="file-activadas" class="file-label">Seleccionar archivo CSV</label>
                            </div>
                            <button type="submit" class="btn-upload">Actualizar Activadas</button>
                        </form>
                        <p class="csv-requirements">
                            <strong>Columnas requeridas:</strong> GPV, PDV, Activadas FTTH, Activadas Móvil
                        </p>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- BACKUPS SECTION -->
            <?php if (count($backup_files) > 0): ?>
                <div class="backup-section">
                    <h2>💾 Archivos de Backup</h2>
                    <p class="backup-info">Backups automáticos creados al actualizar las bases de datos.</p>
                    
                    <table class="admin-table backup-table">
                        <thead>
                            <tr>
                                <th>Nombre del Archivo</th>
                                <th>Tamaño</th>
                                <th>Fecha de Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $backup): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($backup); ?></strong></td>
                                    <td><?php echo format_bytes(filesize($backup)); ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', filemtime($backup)); ?></td>
                                    <td>
                                        <div class="file-actions">
                                            <a href="?download=<?php echo urlencode($backup); ?>" 
                                               class="btn-admin btn-download">
                                               ⬇️ Descargar
                                            </a>
                                            <a href="?delete_backup=<?php echo urlencode($backup); ?>" 
                                               class="btn-admin btn-danger"
                                               onclick="return confirm('¿Estás seguro de que quieres eliminar este backup?');">
                                               🗑️ Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // File input visual feedback
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const label = this.nextElementSibling;
                if (this.files.length > 0) {
                    label.textContent = this.files[0].name;
                    label.style.backgroundColor = '#2ecc71';
                    label.style.color = 'white';
                } else {
                    label.textContent = 'Seleccionar archivo CSV';
                    label.style.backgroundColor = '';
                    label.style.color = '';
                }
            });
        });
    </script>
</body>
</html>