<?php
session_start();
require_once '../security/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Test basic students query
    $studentsQuery = "SELECT COUNT(*) as total FROM students";
    $stmt = $pdo->prepare($studentsQuery);
    $stmt->execute();
    $studentsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Test computed_grades query
    $gradesQuery = "SELECT COUNT(*) as total FROM computed_grades";
    $stmt = $pdo->prepare($gradesQuery);
    $stmt->execute();
    $gradesCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Test the actual graduates query
    $graduatesQuery = "
        SELECT DISTINCT
            s.student_number,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            s.course,
            s.created_at as enrollment_date,
            COALESCE(cg.final_grade, 0) as final_grade,
            CASE 
                WHEN cg.final_grade >= 75 THEN 'Completed'
                ELSE 'In Progress'
            END as status,
            cg.updated_at as completion_date
        FROM students s
        LEFT JOIN computed_grades cg ON s.id = cg.student_id
        WHERE s.course IS NOT NULL
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($graduatesQuery);
    $stmt->execute();
    $graduates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get sample students data
    $sampleStudentsQuery = "SELECT student_number, first_name, last_name, course FROM students LIMIT 5";
    $stmt = $pdo->prepare($sampleStudentsQuery);
    $stmt->execute();
    $sampleStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get sample grades data
    $sampleGradesQuery = "SELECT student_id, final_grade, updated_at FROM computed_grades LIMIT 5";
    $stmt = $pdo->prepare($sampleGradesQuery);
    $stmt->execute();
    $sampleGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'debug_info' => [
            'total_students' => $studentsCount,
            'total_grades' => $gradesCount,
            'graduates_found' => count($graduates),
            'sample_students' => $sampleStudents,
            'sample_grades' => $sampleGrades,
            'graduates_data' => $graduates
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
