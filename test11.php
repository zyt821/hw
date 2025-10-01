<?php
// At the top of the file add
session_start();

require_once "ExcelComparisonTool.php";

// Database connection details
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'compliance_db');

// Database connection function
function connectToDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Create database if it doesn't exist
    //$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->select_db(DB_NAME);

    return $conn;
}


// store Csv Data To Database
function storeCsvDataToDatabase($csvData, $tableName, $sessionId, $fieldMapping) {
    try {
        $conn = connectToDatabase();

        // creat db based on name
        switch ($tableName) {
            case 'dce_data':
                $sql = "INSERT INTO dce_data (session_id, hostname, useruid, sn_number, livedate, userwhen) VALUES (?, ?, ?, ?, ?, ?)";
                break;
            case 'argos_data':
                $sql = "INSERT INTO argos_data (session_id, hostname, owner, serial_number, state) VALUES (?, ?, ?, ?, ?)";
                break;
            case 'sn_data':
                $sql = "INSERT INTO sn_data (session_id, serial_number, State, pending_collection) VALUES (?, ?, ?, ?)";
                break;
            default:
                throw new Exception("Unknown table name: $tableName");
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        // insert data
        foreach ($csvData as $row) {
            // temp variable
            $param1 = $sessionId; // session_id unique key
            $param2 = '';
            $param3 = '';
            $param4 = '';
            $param5 = '';
            $param6 = '';

            switch ($tableName) {
                case 'dce_data':
                    $param2 = $row[$fieldMapping['hostname']] ?? '';
                    $param3 = $row[$fieldMapping['useruid']] ?? '';
                    $param4 = $row[$fieldMapping['sn_number']] ?? '';
                    $param5 = $row[$fieldMapping['livedate']] ?? '';
                    $param6 = $row[$fieldMapping['userwhen']] ?? '';

                    $stmt->bind_param("ssssss",
                        $param1, // sessionId
                        $param2, // hostname
                        $param3, // useruid
                        $param4, // sn_number
                        $param5, // livedate
                        $param6  // userwhen
                    );
                    break;
                case 'argos_data':
                    $param2 = $row[$fieldMapping['hostname']] ?? '';
                    $param3 = $row[$fieldMapping['owner']] ?? '';
                    $param4 = $row[$fieldMapping['serial_number']] ?? '';
                    $param5 = $row[$fieldMapping['state']] ?? '';

                    $stmt->bind_param("sssss",
                        $param1, // sessionId
                        $param2, // hostname
                        $param3, // owner
                        $param4, // serial_number
                        $param5  // State
                    );
                    break;
                case 'sn_data':
                    $param2 = $row[$fieldMapping['serial_number']] ?? '';
                    $param3 = $row[$fieldMapping['State']] ?? '';
                    $param4 = $row[$fieldMapping['pending_collection']] ?? '';

                    $stmt->bind_param("ssss",
                        $param1, // sessionId
                        $param2, // serial_number
                        $param3, // state
                        $param4  // pending_collection
                    );
                    break;
            }

            if (!$stmt->execute()) {
                error_log("Failed to insert row: " . $stmt->error);
            }
        }

        $stmt->close();
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// read csv from sql
function loadCsvDataFromDatabase($tableName, $sessionId) {
    try {
        $conn = connectToDatabase();

        switch ($tableName) {
            case 'dce_data':
                $sql = "SELECT hostname, useruid, sn_number as 'serial', livedate, userwhen FROM dce_data WHERE session_id = ?";
                break;
            case 'argos_data':
                $sql = "SELECT hostname, owner, serial_number as 'serial#', state FROM argos_data WHERE session_id = ?";
                break;
            case 'sn_data':
                $sql = "SELECT serial_number as 'Serial number', State, pending_collection as 'Pending collection [Hardware]' FROM sn_data WHERE session_id = ?";
                break;
            default:
                throw new Exception("Unknown table name: $tableName");
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $stmt->close();
        $conn->close();
        return $data;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}

// Function to store comparison results in database
function storeComparisonResults($results) {
    try {
        $conn = connectToDatabase();

        // Generate a unique session ID (we'll use this to retrieve results later)
        $sessionId = uniqid('comp_', true);

        // Serialize the results array
        $serializedResults = json_encode($results);

        // Prepare and execute the SQL statement
        $stmt = $conn->prepare("INSERT INTO temp_comparison_results (session_id, result_data) VALUES (?, ?)");
        $stmt->bind_param("ss", $sessionId, $serializedResults);

        if (!$stmt->execute()) {
            throw new Exception("Failed to store comparison results: " . $stmt->error);
        }

        $stmt->close();
        $conn->close();

        return $sessionId;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// Function to retrieve comparison results from database
function getComparisonResults($sessionId) {
    try {
        $conn = connectToDatabase();

        // Prepare and execute the SQL statement
        $stmt = $conn->prepare("SELECT result_data FROM temp_comparison_results WHERE session_id = ?");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $stmt->bind_result($resultData);

        if ($stmt->fetch()) {
            $results = json_decode($resultData, true);
            $stmt->close();
            $conn->close();
            return $results;
        }

        $stmt->close();
        $conn->close();
        return null;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return null;
    }
}

// Function to clean up old temporary results (call this periodically)
function cleanupOldResults($hoursOld = 24) {
    try {
        $conn = connectToDatabase();
        $stmt = $conn->prepare("DELETE FROM temp_comparison_results WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)");
        $stmt->bind_param("i", $hoursOld);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Database error during cleanup: " . $e->getMessage());
    }
}

// Save results to database
function saveResultsToDatabase($results) {
    try {
        $nonCompliantResults = array_filter($results, function($result) {
            return $result['compliance_status'] === 'non-compliant';
        });

        $conn = connectToDatabase();

        // Prepare the SQL statement
        $stmt = $conn->prepare("INSERT INTO compliance_results 
            (hostname, 
             dce_useruid, 
             argos_owner, 
             dce_sn, 
             argos_serial, 
             sn_serial, 
             dce_livedate,
             dce_userwhen,
             argos_state,
             sn_state,
             sn_pending_collection,
             dce_status,
             compliance_status,
             compliance_reason) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        // Insert each result
        foreach ($nonCompliantResults as $result) {
            $stmt->bind_param(
                "ssssssssssssss",
                $result['hostname'],
                $result['dce_useruid'],
                $result['argos_owner'],
                $result['dce_sn'],
                $result['argos_serial'],
                $result['sn_serial'],
                $result['dce_livedate'],
                $result['dce_userwhen'],
                $result['argos_state'],
                $result['sn_state'],
                $result['sn_pending_collection'],
                $result['dce_status'],
                $result['compliance_status'],
                $result['compliance_reason']
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        }

        $stmt->close();
        $conn->close();

        return true;
    } catch (Exception $e) {
        // Log the error but don't stop the process
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}


// Handle export logic
try {
    if (isset($_GET['export']) && $_GET['export'] === 'non_compliant' && isset($_GET['session_id'])) {
        // Get the comparison results using the session ID
        $comparisonResults = getComparisonResults($_GET['session_id']);

        if (!$comparisonResults) {
            throw new Exception("No comparison results found. Please upload files first.");
        }

        $comparator = new ExcelComparisonTool();
        $exportedFile = $comparator
            ->setComparisonResults($comparisonResults)
            ->exportNonCompliantResults();

        // Provide file download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($exportedFile) . '"');
        header('Content-Length: ' . filesize($exportedFile));
        readfile($exportedFile);
        exit;
    }
} catch (Exception $e) {
    // Output detailed error information
    echo "Error: " . $e->getMessage();
    echo "<br>Trace: " . $e->getTraceAsString();
    exit;
}

// Execute processing and display results
try {
    $comparisonResults = [];
    $nonCompliantResults = [];
    $sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : null;

    // Clean up old results (24 hours)
    cleanupOldResults(24);

    if ($sessionId) {
        // Retrieve previously processed results
        $comparisonResults = getComparisonResults($sessionId);
        if (!$comparisonResults) {
            // Invalid or expired session ID
            echo "<div style='color:red;margin:10px 0;'>Session expired or invalid. Please upload files again.</div>";
            $comparisonResults = []; // Set empty array to avoid errors
        } else {
            // Filter for non-compliant items
            $nonCompliantResults = array_filter($comparisonResults, function($result) {
                return $result['compliance_status'] === 'non-compliant';
            });
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['dce_file'])) {
		
		if (isset($_SESSION['comparison_results'])) {
			unset($_SESSION['comparison_results']);
		}
        // Process new file upload
        $comparisonResults = handleFileUpload();

        // Save non-compliant results to main database
        saveResultsToDatabase($comparisonResults);

        // Filter for non-compliant items
        $nonCompliantResults = array_filter($comparisonResults, function($result) {
            return $result['compliance_status'] === 'non-compliant';
        });

        // Store all results in temporary storage and get session ID
        $sessionId = storeComparisonResults($comparisonResults);

        // Redirect to the same page with session ID in URL
        header("Location: test11.php?session_id=" . $sessionId);
        exit;
    }
} catch (Exception $e) {
    // Handle file upload errors
    die("Upload error: " . $e->getMessage());
}

// Handle view records request
$dbRecords = [];
if (isset($_GET['view']) && $_GET['view'] === 'records') {
    $dbRecords = getComplianceRecords();
}

function handleFileUpload() {
    // check if the file has uploaded
    if (!isset($_FILES['dce_file']) || !isset($_FILES['argos_file']) || !isset($_FILES['sn_file'])) {
        throw new Exception("Please upload DCE, ARGOS, and SN files");
    }

    // generate unique session ID
    $sessionId = uniqid('upload_', true);

    // ensure tables exist
    //createDataTables();

    try {
        // dce
        $dceFile = $_FILES['dce_file']['tmp_name'];
        $dceData = [];
        if (($handle = fopen($dceFile, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) == count($headers)) {
                    $dceData[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }

        // store DCE to sql
        $dceFieldMapping = [
            'hostname' => 'hostname',
            'useruid' => 'useruid',
            'sn_number' => 'serial',
            'livedate' => 'livedate',
            'userwhen' => 'userwhen'
        ];
        storeCsvDataToDatabase($dceData, 'dce_data', $sessionId, $dceFieldMapping);

        // ARGOS
        $argosFile = $_FILES['argos_file']['tmp_name'];
        $argosData = [];
        if (($handle = fopen($argosFile, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) == count($headers)) {
                    $argosData[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }

        // argos to sql
        $argosFieldMapping = [
            'hostname' => 'Hostname',
            'owner' => 'Owner',
            'serial_number' => 'Serial #',
            'state' => 'State'
        ];
        storeCsvDataToDatabase($argosData, 'argos_data', $sessionId, $argosFieldMapping);

        // sn
        $snFile = $_FILES['sn_file']['tmp_name'];
        $snData = [];
        if (($handle = fopen($snFile, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) == count($headers)) {
                    $snData[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }

        // SN to sql
        $snFieldMapping = [
            'serial_number' => 'Serial number',
            'State' => 'State',
            'pending_collection' => 'Pending collection [Hardware]'
        ];
        storeCsvDataToDatabase($snData, 'sn_data', $sessionId, $snFieldMapping);

        $comparator = new ExcelComparisonTool();
        $comparator->setSessionId($sessionId);

        // load data from sql
        $comparator->importDceFile();
        $comparator->importArgosFile();
        $comparator->importSnFile();

        // clean null data
        $comparator->cleanAllData();

        // proceed with comparison
        $results = $comparator->compareData();

        // store the result to SESSION
        $_SESSION['comparison_results'] = $results;
        $_SESSION['current_session_id'] = $sessionId;

        return $results;
    } catch (Exception $e) {
        throw new Exception("handling errors: " . $e->getMessage());
    }
}

// cleanupOldCsvData
function cleanupOldCsvData($hoursOld = 24) {
    try {
        $conn = connectToDatabase();

        // clear old csv data in sql
        $tables = ['dce_data', 'argos_data', 'sn_data'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)");
            $stmt->bind_param("i", $hoursOld);
            $stmt->execute();
            $stmt->close();
        }

        $conn->close();
    } catch (Exception $e) {
        error_log("Database error during CSV data cleanup: " . $e->getMessage());
    }
}

// continue with main class
try {
    $comparisonResults = [];
    $nonCompliantResults = [];
    $sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : null;

    // clear old data（24hrs）
    cleanupOldResults(24);
    cleanupOldCsvData(24);

    if ($sessionId) {
        // Retrieve results of previous processing
        $comparisonResults = getComparisonResults($sessionId);
        if (!$comparisonResults) {
            echo "<div style='color:red;margin:10px 0;'>Session expired or invalid. Please upload files again.</div>";
            $comparisonResults = [];
        } else {
            $nonCompliantResults = array_filter($comparisonResults, function($result) {
                return $result['compliance_status'] === 'non-compliant';
            });
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['dce_file'])) {
		
		if (isset($_SESSION['comparison_results'])) {
			unset($_SESSION['comparison_results']);
		}
		
        // Processing new file uploads
        $comparisonResults = handleFileUpload();

        // Get current session ID
        $sessionId = $_SESSION['current_session_id'];

        // Saving non-compliant results to the database
        saveResultsToDatabase($comparisonResults);

        // Filtering non-compliant items
        $nonCompliantResults = array_filter($comparisonResults, function($result) {
            return $result['compliance_status'] === 'non-compliant';
        });

        // Store all results to temporary storage and get session IDs
        $tempSessionId = storeComparisonResults($comparisonResults);

        // Redirects to the same page with the session ID
        header("Location: test11.php?session_id=" . $tempSessionId);
        exit;
    }
} catch (Exception $e) {
    die("Upload Error: " . $e->getMessage());
}


// Function to view database records
function getComplianceRecords() {
    try {
        $conn = connectToDatabase();
        $result = $conn->query("SELECT * FROM compliance_results ORDER BY created_at DESC LIMIT 100");

        $records = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }

        $conn->close();
        return $records;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}

// Handle view records request
$dbRecords = [];
if (isset($_GET['view']) && $_GET['view'] === 'records') {
    $dbRecords = getComplianceRecords();
}

// Handle filter for non-compliant items
$nonCompliantResults = [];
if (!empty($comparisonResults)) {
    $nonCompliantResults = array_filter($comparisonResults, function($result) {
        return $result['compliance_status'] === 'non-compliant';
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compare results</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1800px;
            margin: 0 auto;
            padding: 20px;
        }
        .result-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .result-table th, .result-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .compliant { background-color: #dff0d8; }
        .non-compliant { background-color: #f2dede; }
        .action-btns {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            flex-wrap: wrap;
            gap: 10px;
        }
        .return-btn, .export-btn, .records-btn, .non-compliant-btn {
            display: block;
            width: 190px;
            padding: 10px;
            background-color: #007bff;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
        }
        .export-btn {
            background-color: #28a745;
        }
        .records-btn {
            background-color: #6c757d;
        }
        .non-compliant-btn {
            background-color: #dc3545;
        }
        .tab-content {
            margin-top: 20px;
        }
        .tabs {
            margin-bottom: 20px;
        }
        .tabs a {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tabs a.active {
            background-color: #f0f0f0;
            border-bottom: 1px solid white;
        }
        .data-cleaning-stats {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .stats-table th, .stats-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .stats-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
<?php if (isset($_SESSION['data_cleaning_stats'])): ?>
    <div class="data-cleaning-stats">
        <h3>NULL Data Cleaning Statistics</h3>
        <table class="stats-table">
            <thead>
            <tr>
                <th>File Type</th>
                <th>Original Records</th>
                <th>After Cleaning</th>
                <th>Removed Records</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>DCE File</td>
                <td><?= $_SESSION['data_cleaning_stats']['original_dce_count'] ?></td>
                <td><?= $_SESSION['data_cleaning_stats']['cleaned_dce_count'] ?></td>
                <td><?= $_SESSION['data_cleaning_stats']['original_dce_count'] - $_SESSION['data_cleaning_stats']['cleaned_dce_count'] ?></td>
            </tr>
            <tr>
                <td>ARGOS File</td>
                <td><?= $_SESSION['data_cleaning_stats']['original_argos_count'] ?></td>
                <td><?= $_SESSION['data_cleaning_stats']['cleaned_argos_count'] ?></td>
                <td><?= $_SESSION['data_cleaning_stats']['original_argos_count'] - $_SESSION['data_cleaning_stats']['cleaned_argos_count'] ?></td>
            </tr>
            <tr>
                <td>SN File</td>
                <td><?= $_SESSION['data_cleaning_stats']['original_sn_count'] ?></td>
                <td><?= $_SESSION['data_cleaning_stats']['cleaned_sn_count'] ?></td>
                <td><?= $_SESSION['data_cleaning_stats']['original_sn_count'] - $_SESSION['data_cleaning_stats']['cleaned_sn_count'] ?></td>
            </tr>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<h2>Compare results</h2>
<?php if (isset($_GET['view']) && $_GET['view'] === 'records'): ?>
    <!-- Database Records View -->
    <h3>Database Records</h3>
    <div class="action-btns">
        <?php if (isset($sessionId) && $sessionId): ?>
            <a href="test11.php?session_id=<?= $sessionId ?>" class="return-btn">Back to All Results</a>
        <?php else: ?>
            <a href="test11.php" class="return-btn">Back to Upload</a>
        <?php endif; ?>
    </div>
    <div class="tabs">
        <?php if (isset($sessionId) && $sessionId): ?>
            <a href="test11.php?session_id=<?= $sessionId ?>">All Results</a>
            <a href="?view=non_compliant&session_id=<?= $sessionId ?>">Non-Compliant Results</a>
        <?php else: ?>
            <a href="test11.php">All Results</a>
            <a href="?view=non_compliant">Non-Compliant Results</a>
        <?php endif; ?>
        <a href="?view=records" class="active">Database Records</a>
    </div>
    <p>Database has: <?= count($dbRecords) ?> records</p>
    <table class="result-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Hostname</th>
            <th>DCE UID</th>
            <th>AGROS UID</th>
            <th>DCE Serial#</th>
            <th>SN Serial Number</th>
            <th>AGROS Serial#</th>
            <th>DCE Livedate</th>
            <th>DCE Userwhen</th>
            <th>Created At</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($dbRecords as $record): ?>
            <tr class="<?= $record['compliance_status'] ?>">
                <td><?= htmlspecialchars($record['id']) ?></td>
                <td><?= htmlspecialchars($record['hostname']) ?></td>
                <td><?= htmlspecialchars($record['dce_useruid']) ?></td>
                <td><?= htmlspecialchars($record['argos_owner']) ?></td>
                <td><?= htmlspecialchars($record['dce_sn']) ?></td>
                <td><?= htmlspecialchars($record['argos_serial']) ?></td>
                <td><?= htmlspecialchars($record['sn_serial']) ?></td>
                <td><?= htmlspecialchars($record['dce_livedate']) ?></td>
                <td><?= htmlspecialchars($record['dce_userwhen']) ?></td>
                <td><?= $record['created_at'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif (isset($_GET['view']) && $_GET['view'] === 'non_compliant'): ?>
    <!-- Non-Compliant Results View -->
    <h3>Non-Compliant Results</h3>
    <div class="action-btns">
        <a href="test11.php?session_id=<?= $sessionId ?>" class="return-btn">Back to All Results</a>
        <?php if (isset($sessionId) && $sessionId): ?>
            <a href="?export=non_compliant&session_id=<?= $sessionId ?>" class="export-btn">Export Results</a>
        <?php endif; ?>
    </div>
    <div class="tabs">
        <a href="test11.php?session_id=<?= $sessionId ?>">All Results</a>
        <a href="?view=non_compliant&session_id=<?= $sessionId ?>" class="active">Non-Compliant Results</a>
        <a href="?view=records<?= isset($sessionId) ? '&session_id='.$sessionId : '' ?>">Database Records</a>
    </div>
    <p>This run finds: <?= count($nonCompliantResults) ?> non-compliant records</p>
    <table class="result-table">
        <thead>
        <tr>
            <th>Hostname</th>
            <th>DCE UID</th>
            <th>AGROS UID</th>
            <th>DCE Serial#</th>
            <th>AGROS Serial#</th>
            <th>SN Serial Number</th>
            <th>DCE Livedate</th>
            <th>DCE Userwhen</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($nonCompliantResults as $result): ?>
            <tr class="non-compliant">
                <td><?= htmlspecialchars($result['hostname']) ?></td>
                <td><?= htmlspecialchars($result['dce_useruid']) ?></td>
                <td><?= htmlspecialchars($result['argos_owner']) ?></td>
                <td><?= htmlspecialchars($result['dce_sn']) ?></td>
                <td><?= htmlspecialchars($result['argos_serial']) ?></td>
                <td><?= htmlspecialchars($result['sn_serial']) ?></td>
                <td><?= htmlspecialchars($result['dce_livedate']) ?></td>
                <td><?= htmlspecialchars($result['dce_userwhen']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php else: ?>
    <!-- All Results View (Default) -->
    <div class="action-btns">
        <a href="index.html" class="return-btn">Return to upload page</a>
        <?php if (isset($sessionId) && $sessionId): ?>
            <a href="?export=non_compliant&session_id=<?= $sessionId ?>" class="export-btn">Export Results</a>
        <?php endif; ?>
    </div>
    <div class="tabs">
        <a href="test11.php?session_id=<?= $sessionId ?>" class="active">All Results</a>
        <a href="?view=non_compliant&session_id=<?= $sessionId ?>">Non-Compliant Results</a>
        <a href="?view=records<?= isset($sessionId) ? '&session_id='.$sessionId : '' ?>">Database Records</a>
    </div>
    <p>This run returns: <?= count($comparisonResults) ?> records in total</p>
    <table class="result-table">
        <thead>
        <tr>
            <th>Hostname</th>
            <th>DCE UID</th>
            <th>AGROS UID</th>
            <th>DCE Serial#</th>
            <th>AGROS Serial#</th>
            <th>SN Serial Number</th>
            <th>DCE Livedate</th>
            <th>DCE Userwhen</th>
            <th>DCE status</th>
            <th>AGROS State</th>
            <th>SN State</th>
            <th>SN Pending collection</th>
            <th>Compliance status</th>
            <th>Reason</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($comparisonResults as $result): ?>
            <tr class="<?= $result['compliance_status'] ?>">
                <td><?= htmlspecialchars($result['hostname']) ?></td>
                <td><?= htmlspecialchars($result['dce_useruid']) ?></td>
                <td><?= htmlspecialchars($result['argos_owner']) ?></td>
                <td><?= htmlspecialchars($result['dce_sn']) ?></td>
                <td><?= htmlspecialchars($result['argos_serial']) ?></td>
                <td><?= htmlspecialchars($result['sn_serial']) ?></td>
                <td><?= htmlspecialchars($result['dce_livedate']) ?></td>
                <td><?= htmlspecialchars($result['dce_userwhen']) ?></td>
                <td><?= htmlspecialchars($result['dce_status']) ?></td>
                <td><?= htmlspecialchars($result['argos_state']) ?></td>
                <td><?= htmlspecialchars($result['sn_state']) ?></td>
                <td><?= htmlspecialchars($result['sn_pending_collection']) ?></td>
                <td><?= $result['compliance_status'] ?></td>
                <td><?= htmlspecialchars($result['compliance_reason']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>