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

// Function to get all GPV monthly CSV files
function get_all_monthly_files() {
    $files = [];
    $pattern = '*_*_*.csv'; // Matches: GPVNAME_Month_Year.csv

    foreach (glob($pattern) as $filename) {
        // Skip the master database files
        if ($filename === 'tabla_vendidas.csv' || $filename === 'tabla_activadas.csv') {
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

// Handle individual file download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $file = basename($_GET['download']); // Security: prevent directory traversal
    
    if (file_exists($file) && strpos($file, '_') !== false) { // Basic validation
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
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

// Get all files
$monthly_files = get_all_monthly_files();

// Group files by month for better organization
$grouped_files = [];
foreach ($monthly_files as $file) {
    // Extract month_year from filename (e.g., "November_2025" from "JUANLU_November_2025.csv")
    $parts = explode('_', $file['name']);
    if (count($parts) >= 3) {
        $month_year = $parts[count($parts)-2] . '_' . str_replace('.csv', '', $parts[count($parts)-1]);
        if (!isset($grouped_files[$month_year])) {
            $grouped_files[$month_year] = [];
        }
        $grouped_files[$month_year][] = $file;
    }
}
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
                <p>Gestión de Archivos de Registros</p>
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
                    <h3>Total de Archivos</h3>
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
            
            <!-- Files List -->
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
        </div>
    </div>
</body>
</html>