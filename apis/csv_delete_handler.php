<?php
// CSV Delete Handler
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start output buffering
ob_start();

try {
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Get data type to delete
    $dataType = $_POST['data_type'] ?? '';
    
    if (empty($dataType)) {
        throw new Exception('Data type is required');
    }

    $dataDir = __DIR__ . '/../data/';
    $deletedFiles = [];
    $errors = [];

    // Define file mappings
    $fileMappings = [
        'employment' => ['mmtvtc_employment_rates.csv'],
        'graduates' => ['Graduates_.csv'],
        'graduates_course_popularity' => ['Graduates_.csv'],
        'industry' => ['industry_data.csv'],
        'all' => ['mmtvtc_employment_rates.csv', 'Graduates_.csv', 'industry_data.csv']
    ];

    if (!isset($fileMappings[$dataType])) {
        throw new Exception('Invalid data type');
    }

    $filesToDelete = $fileMappings[$dataType];

    // Delete files
    foreach ($filesToDelete as $filename) {
        $filePath = $dataDir . $filename;
        
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $deletedFiles[] = $filename;
            } else {
                $errors[] = "Failed to delete $filename";
            }
        }
    }

    // Clear any output buffer
    ob_clean();

    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Some files could not be deleted: ' . implode(', ', $errors),
            'deleted_files' => $deletedFiles
        ]);
    } else {
        $message = empty($deletedFiles) ? 'No files found to delete' : 'Data deleted successfully';
        echo json_encode([
            'success' => true,
            'message' => $message,
            'deleted_files' => $deletedFiles,
            'data_type' => $dataType,
            'timestamp' => time(),
            'cache_bust' => 'deleted_' . time()
        ]);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>
