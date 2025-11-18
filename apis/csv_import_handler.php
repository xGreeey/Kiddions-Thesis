<?php
// Set content type
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

// No authentication required - removed all restrictions

// No CSRF token required - removed for smooth operation

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Validate required fields
$chartType = $_POST['chart_type'] ?? '';
$csvFile = $_FILES['csv_file'] ?? null;

if (empty($chartType) || !$csvFile || $csvFile['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields or file upload error.']);
    exit;
}

// Validate chart type
$allowedChartTypes = ['employment', 'graduates', 'graduates_course_popularity', 'industry'];
if (!in_array($chartType, $allowedChartTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid chart type.']);
    exit;
}

// Validate file type
$fileExtension = strtolower(pathinfo($csvFile['name'], PATHINFO_EXTENSION));
if ($fileExtension !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed.']);
    exit;
}

// Validate file size (max 10MB)
if ($csvFile['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 10MB allowed.']);
    exit;
}

// Additional security: Check file content for malicious patterns
$csvContent = file_get_contents($csvFile['tmp_name']);
if ($csvContent === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to read CSV file.']);
    exit;
}

// Check for potential security issues
if (strpos($csvContent, '<?php') !== false || 
    strpos($csvContent, '<script') !== false || 
    strpos($csvContent, 'javascript:') !== false) {
    echo json_encode(['success' => false, 'message' => 'CSV file contains potentially malicious content.']);
    exit;
}

try {
    // CSV content already read above for security check
    
    // Parse CSV to validate format
    $lines = array_filter(array_map('trim', explode("\n", $csvContent)));
    if (count($lines) < 2) {
        throw new Exception('CSV file must contain at least a header row and one data row.');
    }
    
    $header = str_getcsv($lines[0]);
    $header = array_map('trim', $header);
    
    // Validate CSV format based on chart type
    $validationResult = validateCsvFormat($chartType, $header, $lines);
    if (!$validationResult['valid']) {
        echo json_encode(['success' => false, 'message' => $validationResult['message']]);
        exit;
    }
    
    // Determine target file path
    $targetFile = getTargetFilePath($chartType);
    if (!$targetFile) {
        throw new Exception('Invalid chart type for file path.');
    }
    
    // Create backup of existing file if it exists
    if (file_exists($targetFile)) {
        $backupFile = $targetFile . '.backup.' . date('Y-m-d_H-i-s');
        if (!copy($targetFile, $backupFile)) {
            error_log("Failed to create backup of $targetFile");
        }
    }
    
    // Move uploaded file to target location
    if (!move_uploaded_file($csvFile['tmp_name'], $targetFile)) {
        throw new Exception('Failed to save CSV file.');
    }
    
    // Log the import action
    error_log("CSV import successful: $chartType chart data imported by admin user " . $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'CSV file imported successfully. Charts will be updated automatically.',
        'chart_type' => $chartType
    ]);
    
} catch (Exception $e) {
    error_log("CSV import error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log("CSV import PHP error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// Clean any unexpected output
$output = ob_get_clean();
if (!empty($output)) {
    error_log("Unexpected output in CSV import: " . $output);
}

/**
 * Validate CSV format based on chart type
 */
function validateCsvFormat($chartType, $header, $lines) {
    switch ($chartType) {
        case 'employment':
            $requiredColumns = ['course_name', 'course_code', 'year', 'employment_rate'];
            break;
        case 'graduates':
            $requiredColumns = ['year', 'course_id', 'batch', 'student_count'];
            break;
        case 'graduates_course_popularity':
            $requiredColumns = ['year', 'course_id', 'student_count'];
            break;
        case 'industry':
            $requiredColumns = ['industry_id', 'year', 'batch', 'student_count'];
            break;
        default:
            return ['valid' => false, 'message' => 'Invalid chart type.'];
    }
    
    // Check if all required columns are present
    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $header)) {
            $missingColumns[] = $col;
        }
    }
    
    if (!empty($missingColumns)) {
        return [
            'valid' => false, 
            'message' => 'Missing required columns: ' . implode(', ', $missingColumns)
        ];
    }
    
    // Validate data rows
    for ($i = 1; $i < count($lines); $i++) {
        $row = str_getcsv($lines[$i]);
        if (count($row) !== count($header)) {
            return [
                'valid' => false, 
                'message' => "Row " . ($i + 1) . " has incorrect number of columns."
            ];
        }
        
        // Basic data validation
        if ($chartType === 'employment') {
            $yearIndex = array_search('year', $header);
            $rateIndex = array_search('employment_rate', $header);
            if ($yearIndex !== false && (!is_numeric($row[$yearIndex]) || $row[$yearIndex] < 2000 || $row[$yearIndex] > 2030)) {
                return ['valid' => false, 'message' => "Row " . ($i + 1) . " has invalid year."];
            }
            if ($rateIndex !== false && (!is_numeric($row[$rateIndex]) || $row[$rateIndex] < 0 || $row[$rateIndex] > 100)) {
                return ['valid' => false, 'message' => "Row " . ($i + 1) . " has invalid employment rate (must be 0-100)."];
            }
        }
    }
    
    return ['valid' => true, 'message' => 'CSV format is valid.'];
}

/**
 * Get target file path based on chart type
 */
function getTargetFilePath($chartType) {
    $dataDir = __DIR__ . '/../data/';
    
    switch ($chartType) {
        case 'employment':
            return $dataDir . 'mmtvtc_employment_rates.csv';
        case 'graduates':
        case 'graduates_course_popularity':
            return $dataDir . 'Graduates_.csv';
        case 'industry':
            return $dataDir . 'industry_data.csv';
        default:
            return null;
    }
}
?>
