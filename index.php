<?php
// --- FILE CONFIGURATION ---
$vendidas_file = 'tabla_vendidas.csv'; // File for "Vendidas" data
$activadas_file = 'tabla_instaladas.csv'; // File for "Instaladas" data
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
 * Function that searches for the PDV in a CSV file and returns the data row.
 * ASSUMPTION: The first row is the header, and the delimiter is a SEMICOLON (;).
 */
function search_file($file_name, $gpv_input, $pdv_input) {
    // Delimiter is set to SEMICOLON (;) based on the user's provided snippet.
    $delimiter = ';'; 

    if (!file_exists($file_name)) {
        return ['error' => "Master data file '{$file_name}' does not exist."];
    }
    
    if (($handle = fopen($file_name, 'r')) !== FALSE) {
        // Read the actual data header (Assumes header is the very first row)
        $headers = fgetcsv($handle, 1000, $delimiter); 

        if ($headers === FALSE) {
             fclose($handle);
             return ['error' => "Could not read the header in {$file_name}."];
        }

        // Search for the key column name ('PDV' is the identifier in the simplified export)
        $pdv_col_index = array_search('PDV', $headers); 
        
        // Fallback: If 'PDV' is not found, assume PDV is the second column (index 1) based on the snippet.
        if ($pdv_col_index === false) {
             $pdv_col_index = 1; 
        }

        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            // Compare the PDV from the CSV with the user's input
            if (strtoupper(trim($row[$pdv_col_index] ?? '')) === $pdv_input) {
                fclose($handle);
                return array_combine($headers, $row); // Return the entire row as an associative array
            }
        }
        fclose($handle);
    }
    return false; // Data not found
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
        $message = "¡Datos guardados con éxito!";

        // Needed so the search code runs and displays the table after successful export
        $gpv_input = $_POST['gpv_log'];
        $pdv_input = $_POST['pdv_log'];
    } else {
        $message = "ERROR: Could not write to the log file. Check permissions on IONOS.";
    }
}


// --- SEARCH FUNCTION GPV-PDV (small form) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && ( (isset($_POST['accion']) && $_POST['accion'] == 'buscar') || isset($_POST['gpv_log'])) ){

    // Note: search_file function definition has been changed to remove rows_to_skip
    $vendidas_result = search_file($vendidas_file, $gpv_input, $pdv_input);
    $activadas_result = search_file($activadas_file, $gpv_input, $pdv_input);
    
    // 1. Handle file errors first
    if (isset($vendidas_result['error'])) {
        $message = "ERROR: " . $vendidas_result['error'];
    } elseif (isset($activadas_result['error'])) {
         $message = "ERROR: " . $activadas_result['error'];
    } 
    // 2. If data is found in at least one file
    elseif ($vendidas_result !== false || $activadas_result !== false) {
        $data_found = true;
        
        // Load found data into the array that feeds the HTML table
        // Sold Data (Vendidas)
        if (is_array($vendidas_result)) {
            // MAPPING UPDATED: Using the simplified CSV column headers
            $current_data['Vendidas FTTH'] = $vendidas_result['Vendidas FTTH'] ?? '0'; 
            $current_data['Vendidas Móvil'] = $vendidas_result['Vendidas Móvil'] ?? '0'; 
        }

        // Activated Data (Activadas)
        if (is_array($activadas_result)) {
            // MAPPING UPDATED: Assuming 'Instaladas' file also uses the simplified column headers
            $current_data['Activadas FTTH'] = $activadas_result['Vendidas FTTH'] ?? '0'; 
            $current_data['Activadas Móvil'] = $activadas_result['Vendidas Móvil'] ?? '0';
        }
        
    } else {
        // 3. If no data is found (only show error if 'buscar' was explicitly pressed)
        if (isset($_POST['accion']) && $_POST['accion'] == 'buscar') {
            $message = "ERROR: PDV not found in either master data file.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Management Tool</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php
    // Display error/success messages
    if (!empty($message)):
        echo "<p>" . $message . "</p>";
    endif;
    ?>

    <!-- 1. SEARCH FORM (Small Form) -->
    <form id="smallForm" method="POST" action="index.php">
        <input type="hidden" name="accion" value="buscar">

        <h2>GPV</h2>
        <!-- GPV DROPDOWN MENU -->
        <select id="gpv" name="gpv" required>
            <option value="" disabled selected>Select GPV</option>
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

            <label for="fecha_hora">Date and Time</label>
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

            <label for="acciones">Actions</label>
            <textarea id="acciones" name="acciones" rows="4" required></textarea>

            <fieldset>
                <legend>Commitment (Compromiso)</legend>
                <div>
                    <label for="compromiso_movil">Móvil</label>
                    <input type="number" id="compromiso_movil" name="compromiso_movil" value="0" min="0">
                </div>
                <div>
                    <label for="compromiso_fibra">Fibra</label>
                    <input type="number" id="compromiso_fibra" name="compromiso_fibra" value="0" min="0">
                </div>
            </fieldset>

            <label for="fecha_proxima_visita">Date of Next Visit</label>
            <input type="date" id="fecha_proxima_visita" name="fecha_proxima_visita" required>

            <button type="submit">ENVIAR</button>
        </form>

    <?php endif; ?>

</body>

</html>
