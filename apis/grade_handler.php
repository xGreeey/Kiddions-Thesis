<?php
require_once '../security/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get the request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    $studentId = $input['student_id'] ?? '';
    $gradeNumber = $input['grade_number'] ?? '';
    $entries = $input['entries'] ?? [];
    
    if (empty($action) || empty($studentId) || empty($gradeNumber)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    if ($action === 'update_grades') {
        if (empty($entries)) {
            echo json_encode(['success' => false, 'message' => 'No entries to update']);
            exit;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update each entry
            foreach ($entries as $entry) {
                $index = $entry['index'] ?? 0;
                $date = $entry['date'] ?? '';
                $component = $entry['component'] ?? '';
                $rawScore = $entry['raw_score'] ?? 0;
                $totalItems = $entry['total_items'] ?? 100;
                $transmuted = $entry['transmuted'] ?? 0;
                
                if (empty($date) || empty($component)) {
                    continue; // Skip invalid entries
                }
                
                // Get the existing entry to update
                $stmt = $pdo->prepare("
                    SELECT id FROM grade_details 
                    WHERE student_number = ? AND grade_number = ? 
                    ORDER BY created_at ASC 
                    LIMIT 1 OFFSET ?
                ");
                $stmt->execute([$studentId, $gradeNumber, $index]);
                $existingEntry = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingEntry) {
                    // Update existing entry
                    $updateStmt = $pdo->prepare("
                        UPDATE grade_details 
                        SET date_given = ?, component = ?, raw_score = ?, total_items = ?, transmuted = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$date, $component, $rawScore, $totalItems, $transmuted, $existingEntry['id']]);
                } else {
                    // Insert new entry if index is beyond existing entries
                    $insertStmt = $pdo->prepare("
                        INSERT INTO grade_details (student_number, grade_number, date_given, component, raw_score, total_items, transmuted, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ");
                    $insertStmt->execute([$studentId, $gradeNumber, $date, $component, $rawScore, $totalItems, $transmuted]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Grades updated successfully',
                'updated_count' => count($entries)
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            throw $e;
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating grades: ' . $e->getMessage()
    ]);
}
?>
