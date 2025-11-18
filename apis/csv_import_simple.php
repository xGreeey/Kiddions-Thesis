<?php
// Ultra-simple CSV import handler
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start output buffering to catch any errors
ob_start();

try {
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Get form data
    $chartType = $_POST['chart_type'] ?? '';
    $csvFile = $_FILES['csv_file'] ?? null;

    if (empty($chartType)) {
        throw new Exception('Chart type is required');
    }

    if (!$csvFile || $csvFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error');
    }

    // Validate chart type
    $allowedTypes = ['employment', 'graduates', 'graduates_course_popularity', 'industry'];
    if (!in_array($chartType, $allowedTypes)) {
        throw new Exception('Invalid chart type');
    }

    // Validate file type
    $extension = strtolower(pathinfo($csvFile['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new Exception('Only CSV files are allowed');
    }

    // Validate file size (max 10MB)
    if ($csvFile['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size too large (max 10MB)');
    }

    // Determine target file
    $dataDir = __DIR__ . '/../data/';
    $targetFile = '';
    
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

    if (empty($targetFile)) {
        throw new Exception('Invalid chart type');
    }

    // Create data directory if needed
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            throw new Exception('Cannot create data directory');
        }
    }

    // Remove existing file
    if (file_exists($targetFile)) {
        unlink($targetFile);
    }

    // Move uploaded file
    if (!move_uploaded_file($csvFile['tmp_name'], $targetFile)) {
        throw new Exception('Failed to save file');
    }

    // Set permissions
    chmod($targetFile, 0644);

    // Clear any output buffer
    ob_clean();

    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'CSV imported successfully',
        'chart_type' => $chartType
    ]);

} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    // Return error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output buffer
    ob_clean();
    
    // Return error
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>
