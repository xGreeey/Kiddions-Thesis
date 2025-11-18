<?php
require_once '../security/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if requesting courses list
    if (isset($_GET['action']) && $_GET['action'] === 'courses') {
        // Get all courses
        $stmt = $pdo->prepare("SELECT DISTINCT course, COUNT(*) as student_count FROM students WHERE course IS NOT NULL AND course != '' GROUP BY course ORDER BY course");
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'courses' => $courses
        ]);
        exit;
    }
    
    // Get course parameter
    $course = $_GET['course'] ?? '';
    $batch = $_GET['batch'] ?? '1'; // Default to batch 1
    
    // Debug logging
    error_log("Course Students API - Course: $course, Batch: $batch");
    
    if (empty($course)) {
        echo json_encode(['success' => false, 'message' => 'Course parameter is required']);
        exit;
    }
    
    // Decode URL-encoded course name
    $course = urldecode($course);
    
    // Try exact match first with batch filter
    $stmt = $pdo->prepare("SELECT student_number, first_name, last_name, course, batch FROM students WHERE course = ? AND batch = ? ORDER BY id DESC LIMIT 200");
    $stmt->execute([$course, $batch]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the query results
    error_log("Course Students API - Found " . count($students) . " students for course: $course, batch: $batch");
    foreach ($students as $student) {
        error_log("Student: " . $student['student_number'] . " - " . $student['first_name'] . " " . $student['last_name'] . " (Batch: " . $student['batch'] . ")");
    }
    
    // If no exact match, try case-insensitive search with batch filter
    if (empty($students)) {
        $stmt = $pdo->prepare("SELECT student_number, first_name, last_name, course, batch FROM students WHERE LOWER(course) = LOWER(?) AND batch = ? ORDER BY id DESC LIMIT 200");
        $stmt->execute([$course, $batch]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If still no match, try partial match with batch filter
    if (empty($students)) {
        $stmt = $pdo->prepare("SELECT student_number, first_name, last_name, course, batch FROM students WHERE course LIKE ? AND batch = ? ORDER BY id DESC LIMIT 200");
        $stmt->execute(['%' . $course . '%', $batch]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Calculate final grades for each student (if grades table exists)
    foreach ($students as &$student) {
        $studentNumber = $student['student_number'];
        
        try {
            // Check if grades table exists first
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'grades'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                // Get all grades for this student
                $gradeStmt = $pdo->prepare("SELECT grade FROM grades WHERE student_number = ?");
                $gradeStmt->execute([$studentNumber]);
                $gradeRows = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $grades = [];
                foreach ($gradeRows as $gradeRow) {
                    $grades[] = floatval($gradeRow['grade']);
                }
                
                // Calculate final grade
                if (!empty($grades)) {
                    $finalGrade = array_sum($grades) / count($grades);
                    $student['final_grade'] = round($finalGrade, 2);
                } else {
                    $student['final_grade'] = null;
                }
            } else {
                // Grades table doesn't exist, set final_grade to null
                $student['final_grade'] = null;
            }
        } catch (Exception $e) {
            // If there's an error with grades, just set to null
            $student['final_grade'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'course' => $course,
        'count' => count($students)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>