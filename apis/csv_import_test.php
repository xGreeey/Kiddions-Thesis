<?php
// Simple test version of CSV import handler
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    // Get form data
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

    // Determine target file path
    $dataDir = __DIR__ . '/../data/';
    $targetFile = null;
    
    switch ($chartType) {
        case 'employment':
            $targetFile = $dataDir . 'mmtvtc_employment_rates.csv';
            break;
        case 'graduates':
        case 'graduates_course_popularity':
            $targetFile = $dataDir . 'Graduates_.csv';
            break;
        case 'industry':
            $targetFile = $dataDir . 'industry_data.csv';
            break;
    }

    if (!$targetFile) {
        echo json_encode(['success' => false, 'message' => 'Invalid chart type for file path.']);
        exit;
    }

    // Create data directory if it doesn't exist
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    // Create backup of existing file if it exists
    if (file_exists($targetFile)) {
        $backupFile = $targetFile . '.backup.' . date('Y-m-d_H-i-s');
        copy($targetFile, $backupFile);
    }

    // Remove existing file to ensure clean overwrite
    if (file_exists($targetFile)) {
        unlink($targetFile);
    }

    // Move uploaded file to target location
    if (!move_uploaded_file($csvFile['tmp_name'], $targetFile)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save CSV file.']);
        exit;
    }

    // Set proper file permissions
    chmod($targetFile, 0644);

    // Clear any potential file caches
    if (function_exists('clearstatcache')) {
        clearstatcache(true, $targetFile);
    }

    // Force file system to recognize the change
    touch($targetFile);

    echo json_encode([
        'success' => true, 
        'message' => 'CSV file imported successfully. Charts will be updated automatically.',
        'chart_type' => $chartType,
        'target_file' => $targetFile,
        'file_size' => filesize($targetFile),
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
