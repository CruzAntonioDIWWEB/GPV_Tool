<?php
// --- FILE CONFIGURATION ---
$vendidas_file = 'tabla_vendidas.csv'; // File for "Vendidas" data
$activadas_file = 'tabla_activadas.csv'; // File for "Instaladas" data
$log_file = 'registros.csv'; 
$data_found = false; 
$message = ''; 

// GPV List for Dropdown and Validation (New Feature)
$gpv_list = ['JENI', 'MAR', 'JUANLU', 'SARAY', 'CRUSERTEL'];

// Array to store combined data, initialized to '0'
$current_data = [
    'Vendidas FTTH' => '0',
    'Vendidas Móvil' => '0',
    'Activadas FTTH' => '0',
    'Activadas Móvil' => '0',
];

// Get POST values, default to empty string if not set
$gpv_input = strtoupper(trim($_POST['gpv'] ?? ''));
$pdv_input = strtoupper(trim($_POST['pdv'] ?? ''));

/**
 * Function that searches for the PDV in a CSV file and validates it belongs to the GPV.
 * Returns:
 * - Associative array (if PDV and GPV match)
 * - ['error' => string] (if file fails to open/read)
 * - ['gpv_mismatch' => true] (if PDV is found but GPV is wrong)
 * - false (if PDV is not found)
 */
function search_file($file_name, $gpv_input, $pdv_input) {
    // Delimiter is set to SEMICOLON (;) based on the user's provided snippet.
    $delimiter = ';'; 

    if (!file_exists($file_name)) {
        // MENSAJE DE ERROR TRADUCIDO
        return ['error' => "El archivo maestro de datos '{$file_name}' no existe."];
    }
    
    if (($handle = fopen($file_name, 'r')) !== FALSE) {
        
        // Read the actual data header (Assumes header is the very first row)
        $headers = fgetcsv($handle, 1000, $delimiter); 

        if ($headers === FALSE) {
             fclose($handle);
             // MENSAJE DE ERROR TRADUCIDO
             return ['error' => "No se pudo leer el encabezado en {$file_name}."];
        }

        // Search for the key column names ('PDV' and 'GPV')
        $pdv_col_index = array_search('PDV', $headers); 
        $gpv_col_index = array_search('GPV', $headers);

        // Fallback: If 'PDV' or 'GPV' are not found, assume index 1 and 0 respectively.
        if ($pdv_col_index === false) { $pdv_col_index = 1; }
        if ($gpv_col_index === false) { $gpv_col_index = 0; } 

        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            
            $file_pdv = strtoupper(trim($row[$pdv_col_index] ?? ''));
            
            // 1. Check if the PDV matches
            if ($file_pdv === $pdv_input) {
                
                // 2. If PDV matches, check if the GPV also matches (Case-insensitive)
                $file_gpv = strtoupper(trim($row[$gpv_col_index] ?? ''));
                
                if ($file_gpv === $gpv_input) {
                    // PDV and GPV match: SUCCESS
                    fclose($handle);
                    return array_combine($headers, $row); 
                } else {
                    // PDV found, but GPV doesn't match: VALIDATION FAILURE
                    fclose($handle);
                    return ['gpv_mismatch' => true]; 
                }
            }
        }
        fclose($handle);
    }
    return false; // PDV not found in file
}


// --- EXPORT FUNCTION (big form) ---
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'exportar') {
    // Collect data from the big form
    $log_data = [
        date("Y-m-d H:i:s"), 
        $_POST['gpv_log'],
        $_POST['pdv_log'],
        $_POST['fecha_hora'],
        $_POST['top'],
        $_POST['plv'],
        $_POST['acciones'],
        (int)($_POST['compromiso_movil'] ?? 0),
        (int)($_POST['compromiso_fibra'] ?? 0),
        $_POST['fecha_proxima_visita']
    ];

    if (($handle = fopen($log_file, 'a')) !== FALSE ) {
        // Write headers only if the file is empty (log file still uses semicolon)
        if(filesize($log_file) == 0) {
            $headers = ['Fecha_Registro', 'GPV', 'PDV', 'Fecha_hora', 'TOP', 'PLV', 'Acciones', 'Compromiso_Movil', 'Compromiso_Fibra', 'Fecha_Proxima_Visita'];
            fputcsv($handle, $headers, ';');
        }
        fputcsv($handle, $log_data, ';');
        fclose($handle); 
        $message = "¡Datos guardados con éxito!"; // MENSAJE DE ÉXITO EN ESPAÑOL

    // Needed so the search code runs and displays the table after successful export
        $gpv_input = $_POST['gpv_log'];
        $pdv_input = $_POST['pdv_log'];
    } else {
        // MENSAJE DE ERROR TRADUCIDO
        $message = "ERROR: No se pudo escribir en el archivo de registro. Compruebe los permisos.";
    }
}


// --- SEARCH FUNCTION GPV-PDV (small form) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && ( (isset($_POST['accion']) && $_POST['accion'] == 'buscar') || isset($_POST['gpv_log'])) ){

    $vendidas_result = search_file($vendidas_file, $gpv_input, $pdv_input);
    $activadas_result = search_file($activadas_file, $gpv_input, $pdv_input);
    
    // 1. Handle file access errors first
    if (isset($vendidas_result['error'])) {
        $message = "ERROR: " . $vendidas_result['error'];
    } elseif (isset($activadas_result['error'])) {
         $message = "ERROR: " . $activadas_result['error'];
    } 
    // 2. Handle GPV mismatch (PDV was found, but associated GPV was wrong)
    elseif (isset($vendidas_result['gpv_mismatch']) || isset($activadas_result['gpv_mismatch'])) {
        // MENSAJE DE ERROR TRADUCIDO
        $message = "ERROR: El PDV '" . htmlspecialchars($pdv_input) . "' fue encontrado, pero pertenece a otro GPV. Por favor, revise su selección.";
    }
    // 3. If data is found in at least one file (and no mismatch)
    elseif ($vendidas_result !== false || $activadas_result !== false) {
        $data_found = true;
        
        // Load found data into the array that feeds the HTML table
        // Sold Data (Vendidas)
        if (is_array($vendidas_result)) {
            // Mapeo para el archivo de Vendidas
            $current_data['Vendidas FTTH'] = $vendidas_result['Vendidas FTTH'] ?? '0'; 
            $current_data['Vendidas Móvil'] = $vendidas_result['Vendidas Móvil'] ?? '0'; 
        }

        // Activated Data (Activadas)
        if (is_array($activadas_result)) {
            // FIX: Se revierte la corrección anterior. Asumimos que el archivo 'tabla_instaladas.csv'
            // usa los mismos nombres de cabecera 'Vendidas FTTH' y 'Vendidas Móvil' internamente
            // para mostrar los datos de Instaladas/Activadas, ya que así funcionaba previamente.
            $current_data['Activadas FTTH'] = $activadas_result['Activadas FTTH'] ?? '0'; 
            $current_data['Activadas Móvil'] = $activadas_result['Activadas Móvil'] ?? '0';
        }
        
        if (isset($_POST['accion']) && $_POST['accion'] == 'buscar') {
            $message = "Datos cargados correctamente para el PDV: " . htmlspecialchars($pdv_input);
            
            // Añadir un mensaje de diagnóstico si la data de Vendidas es 0 pero la de Activadas sí cargó.
            if (!is_array($vendidas_result) && is_array($activadas_result)) {
                $message .= "<br>AVISO: El PDV no se encontró en el archivo de **VENDIDAS**. Los datos de Vendidas se muestran como 0.";
            }
        }
        
    } else {
        // 4. If no data is found (PDV not present in either file)
        if (isset($_POST['accion']) && $_POST['accion'] == 'buscar') {
            // MENSAJE DE ERROR TRADUCIDO
            $message = "ERROR: El PDV '" . htmlspecialchars($pdv_input) . "' no se encontró en los archivos de datos maestros.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Herramienta de Gestión</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php
    // Display error/success messages
    if (!empty($message)):
        // Add a class based on whether it's an ERROR message or a WARNING/SUCCESS message
        $class = (strpos($message, 'ERROR:') !== false) ? 'error-message' : 'success-message';
        echo "<p class=\"{$class}\">" . $message . "</p>";
    endif;
    ?>

    <!-- 1. SEARCH FORM (Small Form) -->
    <form id="smallForm" method="POST" action="index.php">
        <input type="hidden" name="accion" value="buscar">

        <h2>GPV</h2>
        <!-- GPV DROPDOWN MENU -->
        <select id="gpv" name="gpv" required>
            <option value="" disabled selected>Seleccione GPV</option>
            <?php foreach ($gpv_list as $gpv): ?>
                <option value="<?php echo htmlspecialchars($gpv); ?>" <?php echo ($gpv_input === $gpv) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($gpv); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <!-- END GPV DROPDOWN -->

        <h2>PDV</h2>
        <input type="text" id="pdv" name="pdv" required value="<?php echo htmlspecialchars($pdv_input); ?>">

        <button type="submit">Iniciar</button>
    </form>

    <hr>

    <?php
    // 2. CONDITIONAL CONTENT (Table and Big Form)
    // Only display if data was found (and therefore passed validation)
    if ($data_found):
    ?>

        <!-- RESULTS TABLE -->
        <table id="resultsTable" border="1">
            <thead>
                <tr>
                    <th></th>
                    <th>FTTH</th>
                    <th>Móvil</th>
                </tr>
            </thead>
            <tbody>
                <!-- Sold Data -->
                <tr>
                    <td>Vendidas</td>
                    <td><?php echo htmlspecialchars($current_data['Vendidas FTTH'] ?? '0'); ?></td>
                    <td><?php echo htmlspecialchars($current_data['Vendidas Móvil'] ?? '0'); ?></td>
                </tr>
                <!-- Activated Data -->
                <tr>
                    <td>Activadas</td>
                    <td><?php echo htmlspecialchars($current_data['Activadas FTTH'] ?? '0'); ?></td>
                    <td><?php echo htmlspecialchars($current_data['Activadas Móvil'] ?? '0'); ?></td>
                </tr>
            </tbody>
        </table>

        <hr>

        <!-- EXPORT FORM (Big Form) -->
        <form id="bigForm" method="POST" action="index.php">
            <input type="hidden" name="accion" value="exportar">
            <input type="hidden" name="gpv_log" value="<?php echo htmlspecialchars($gpv_input); ?>">
            <input type="hidden" name="pdv_log" value="<?php echo htmlspecialchars($pdv_input); ?>">

            <label for="fecha_hora">Fecha y Hora</label>
            <input type="datetime-local" id="fecha_hora" name="fecha_hora" required>

            <fieldset>
                <legend>TOP</legend>
                <input type="radio" id="top_si" name="top" value="SI" required>
                <label for="top_si">SI</label>
                <input type="radio" id="top_no" name="top" value="NO">
                <label for="top_no">NO</label>
            </fieldset>

            <label for="plv">PLV</label>
            <input type="text" id="plv" name="plv" required>

            <label for="acciones">Acciones</label>
            <textarea id="acciones" name="acciones" rows="4" required></textarea>

            <fieldset>
                <legend>Compromiso</legend>
                <div>
                    <label for="compromiso_movil">Móvil</label>
                    <input type="number" id="compromiso_movil" name="compromiso_movil" value="0" min="0">
                </div>
                <div>
                    <label for="compromiso_fibra">Fibra</label>
                    <input type="number" id="compromiso_fibra" name="compromiso_fibra" value="0" min="0">
                </div>
            </fieldset>

            <label for="fecha_proxima_visita">Fecha de la Próxima Visita</label>
            <input type="date" id="fecha_proxima_visita" name="fecha_proxima_visita" required>

            <button type="submit">ENVIAR</button>
        </form>

    <?php endif; ?>

</body>

</html>
