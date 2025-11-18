<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../security/db_connect.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? '';
    
    // Use instructor ID from request or find the first available instructor
    if (isset($input['instructor_id'])) {
        $instructor_id = (int)$input['instructor_id'];
    } else {
        // Find the first available instructor ID
        $stmt = $pdo->prepare("SELECT id FROM instructors ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $instructor_id = $result ? (int)$result['id'] : null;
    }
    
    switch ($action) {
        case 'sync_quiz_grades':
            syncQuizGrades($instructor_id);
            break;
        case 'sync_exam_grades':
            syncExamGrades($instructor_id);
            break;
        case 'get_student_quiz_summary':
            getStudentQuizSummary($input['student_number']);
            break;
        case 'get_student_exam_summary':
            getStudentExamSummary($input['student_number']);
            break;
        case 'auto_populate_grade1':
            autoPopulateGrade1($input['student_number']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function syncQuizGrades($instructor_id) {
    global $pdo;
    
    // Get all quiz submissions for this instructor
    $sql = "SELECT 
                qs.id as submission_id,
                qs.quiz_id,
                qs.student_id,
                qs.submitted_at,
                qs.score,
                qs.total_questions,
                qs.correct_answers,
                q.title as quiz_title,
                s.student_number,
                s.first_name,
                s.last_name,
                s.course
            FROM quiz_submissions qs
            JOIN quizzes q ON q.id = qs.quiz_id
            JOIN students s ON s.id = qs.student_id
            WHERE q.instructor_id = ? AND qs.status = 'submitted'
            ORDER BY s.student_number, qs.submitted_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$instructor_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    $errors = [];
    
    foreach ($submissions as $submission) {
        try {
            // Check if grade already exists for this quiz submission
            $existingStmt = $pdo->prepare("SELECT id FROM grade_details WHERE student_number = ? AND component = ? AND date_given = ?");
            $existingStmt->execute([$submission['student_number'], $submission['quiz_title'], $submission['submitted_at']]);
            
            if ($existingStmt->fetch()) {
                continue; // Skip if already exists
            }
            
            // Insert into grade_details table
            $insertStmt = $pdo->prepare("INSERT INTO grade_details (
                student_number, grade_number, component, date_given, raw_score, total_items, transmuted
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $rawScore = $submission['correct_answers'] ?? 0;
            $totalItems = $submission['total_questions'] ?? 0;
            $transmuted = $submission['score'] ?? 0;
            
            $insertStmt->execute([
                $submission['student_number'],
                1, // Grade 1
                $submission['quiz_title'],
                $submission['submitted_at'],
                $rawScore,
                $totalItems,
                $transmuted
            ]);
            
            $processed++;
            
        } catch (Exception $e) {
            $errors[] = "Error processing submission for {$submission['student_number']}: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Processed {$processed} quiz submissions",
        'processed' => $processed,
        'errors' => $errors
    ]);
}

function getStudentQuizSummary($student_number) {
    global $pdo;
    
    // Get all quiz submissions for this student
    $sql = "SELECT 
                qs.id as submission_id,
                qs.quiz_id,
                qs.submitted_at,
                qs.score,
                qs.total_questions,
                qs.correct_answers,
                q.title as quiz_title,
                s.student_number,
                s.first_name,
                s.last_name,
                s.course
            FROM quiz_submissions qs
            JOIN quizzes q ON q.id = qs.quiz_id
            JOIN students s ON s.id = qs.student_id
            WHERE s.student_number = ? AND qs.status = 'submitted'
            ORDER BY qs.submitted_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_number]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary
    $totalQuizzes = count($submissions);
    $totalQuestions = array_sum(array_column($submissions, 'total_questions'));
    $totalCorrect = array_sum(array_column($submissions, 'correct_answers'));
    $averageScore = $totalQuizzes > 0 ? array_sum(array_column($submissions, 'score')) / $totalQuizzes : 0;
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_quizzes' => $totalQuizzes,
            'total_questions' => $totalQuestions,
            'total_correct' => $totalCorrect,
            'average_score' => round($averageScore, 2),
            'submissions' => $submissions
        ]
    ]);
}

function autoPopulateGrade1($student_number) {
    global $pdo;
    
    // Get student's quiz summary
    $summaryResult = getStudentQuizSummary($student_number);
    $summary = json_decode($summaryResult, true);
    
    if (!$summary['success']) {
        echo json_encode(['success' => false, 'message' => 'Failed to get quiz summary']);
        return;
    }
    
    $data = $summary['summary'];
    
    // Check if Grade 1 already has data for this student
    $existingStmt = $pdo->prepare("SELECT id FROM grade_details WHERE student_number = ? AND grade_number = 1");
    $existingStmt->execute([$student_number]);
    
    if ($existingStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Grade 1 already has data for this student']);
        return;
    }
    
    // Insert aggregated quiz data into Grade 1
    $insertStmt = $pdo->prepare("INSERT INTO grade_details (
        student_number, grade_number, component, date_given, raw_score, total_items, transmuted
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $insertStmt->execute([
        $student_number,
        1, // Grade 1
        'Quiz Summary',
        date('Y-m-d H:i:s'),
        $data['total_correct'],
        $data['total_questions'],
        $data['average_score']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Grade 1 populated with quiz data',
        'data' => $data
    ]);
}

function syncExamGrades($instructor_id) {
    global $pdo;
    
    // Get all exam submissions for this instructor
    $sql = "SELECT 
                es.id as submission_id,
                es.exam_id,
                es.student_id,
                es.submitted_at,
                es.score,
                es.total_questions,
                es.correct_answers,
                e.title as exam_title,
                s.student_number,
                s.first_name,
                s.last_name,
                s.course
            FROM exam_submissions es
            JOIN exams e ON e.id = es.exam_id
            JOIN students s ON s.id = es.student_id
            WHERE e.instructor_id = ? AND es.status = 'submitted'
            ORDER BY s.student_number, es.submitted_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$instructor_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    $errors = [];
    
    foreach ($submissions as $submission) {
        try {
            // Check if grade already exists for this exam submission
            $existingStmt = $pdo->prepare("SELECT id FROM grade_details WHERE student_number = ? AND component = ? AND date_given = ?");
            $existingStmt->execute([$submission['student_number'], $submission['exam_title'], $submission['submitted_at']]);
            
            if ($existingStmt->fetch()) {
                continue; // Skip if already exists
            }
            
            // Insert into grade_details table
            $insertStmt = $pdo->prepare("INSERT INTO grade_details (
                student_number, grade_number, component, date_given, raw_score, total_items, transmuted
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $rawScore = $submission['correct_answers'] ?? 0;
            $totalItems = $submission['total_questions'] ?? 0;
            $transmuted = $submission['score'] ?? 0;
            
            $insertStmt->execute([
                $submission['student_number'],
                1, // Grade 1
                $submission['exam_title'],
                $submission['submitted_at'],
                $rawScore,
                $totalItems,
                $transmuted
            ]);
            
            $processed++;
            
        } catch (Exception $e) {
            $errors[] = "Error processing submission for {$submission['student_number']}: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Processed {$processed} exam submissions",
        'processed' => $processed,
        'errors' => $errors
    ]);
}

function getStudentExamSummary($student_number) {
    global $pdo;
    
    // Get all exam submissions for this student
    $sql = "SELECT 
                es.id as submission_id,
                es.exam_id,
                es.submitted_at,
                es.score,
                es.total_questions,
                es.correct_answers,
                e.title as exam_title,
                s.student_number,
                s.first_name,
                s.last_name,
                s.course
            FROM exam_submissions es
            JOIN exams e ON e.id = es.exam_id
            JOIN students s ON s.id = es.student_id
            WHERE s.student_number = ? AND es.status = 'submitted'
            ORDER BY es.submitted_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_number]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary
    $totalExams = count($submissions);
    $totalQuestions = array_sum(array_column($submissions, 'total_questions'));
    $totalCorrect = array_sum(array_column($submissions, 'correct_answers'));
    $averageScore = $totalExams > 0 ? array_sum(array_column($submissions, 'score')) / $totalExams : 0;
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_exams' => $totalExams,
            'total_questions' => $totalQuestions,
            'total_correct' => $totalCorrect,
            'average_score' => round($averageScore, 2),
            'submissions' => $submissions
        ]
    ]);
}
?>
