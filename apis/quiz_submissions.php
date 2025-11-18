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
        case 'get_quiz_submissions':
            getQuizSubmissions($instructor_id);
            break;
        case 'get_exam_submissions':
            getExamSubmissions($instructor_id);
            break;
        case 'get_submission_details':
            getSubmissionDetails($input['submission_id'], $input['type']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getQuizSubmissions($instructor_id) {
    global $pdo;
    
    // Get course parameter from input
    $input = json_decode(file_get_contents('php://input'), true);
    $course_filter = $input['course'] ?? null;
    
    $sql = "SELECT 
                qs.id as submission_id,
                qs.quiz_id,
                qs.student_id,
                qs.submitted_at,
                qs.score,
                qs.total_questions,
                qs.correct_answers,
                qs.status,
                q.title as quiz_title,
                s.student_number,
                s.first_name,
                s.last_name,
                s.course
            FROM quiz_submissions qs
            JOIN quizzes q ON q.id = qs.quiz_id
            JOIN students s ON s.id = qs.student_id
            WHERE q.instructor_id = ?";
    
    $params = [$instructor_id];
    
    // Add course filter if provided
    if ($course_filter && $course_filter !== 'all') {
        $sql .= " AND s.course = ?";
        $params[] = $course_filter;
    }
    
    $sql .= " ORDER BY qs.submitted_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'submissions' => $submissions,
        'course_filter' => $course_filter
    ]);
}

function getExamSubmissions($instructor_id) {
    global $pdo;
    
    $sql = "SELECT 
                es.id as submission_id,
                es.exam_id,
                es.student_id,
                es.submitted_at,
                es.score,
                es.total_questions,
                es.correct_answers,
                es.status,
                e.title as exam_title,
                s.student_number,
                s.first_name,
                s.last_name,
                s.course
            FROM exam_submissions es
            JOIN exams e ON e.id = es.exam_id
            JOIN students s ON s.id = es.student_id
            WHERE e.instructor_id = ?
            ORDER BY es.submitted_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$instructor_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'submissions' => $submissions
    ]);
}

function getSubmissionDetails($submission_id, $type) {
    global $pdo;
    
    if ($type === 'quiz') {
        $sql = "SELECT 
                    qs.*,
                    q.title as quiz_title,
                    s.student_number,
                    s.first_name,
                    s.last_name,
                    s.course
                FROM quiz_submissions qs
                JOIN quizzes q ON q.id = qs.quiz_id
                JOIN students s ON s.id = qs.student_id
                WHERE qs.id = ?";
    } else {
        $sql = "SELECT 
                    es.*,
                    e.title as exam_title,
                    s.student_number,
                    s.first_name,
                    s.last_name,
                    s.course
                FROM exam_submissions es
                JOIN exams e ON e.id = es.exam_id
                JOIN students s ON s.id = es.student_id
                WHERE es.id = ?";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Submission not found']);
        return;
    }
    
    // Get answers
    if ($type === 'quiz') {
        $answersSql = "SELECT 
                            qa.*,
                            qq.question_text,
                            qq.question_type,
                            qo.option_text
                        FROM quiz_answers qa
                        JOIN quiz_questions qq ON qq.id = qa.question_id
                        LEFT JOIN quiz_question_options qo ON qo.id = qa.selected_option_id
                        WHERE qa.submission_id = ?
                        ORDER BY qq.question_order";
    } else {
        $answersSql = "SELECT 
                            ea.*,
                            eq.question_text,
                            eq.question_type,
                            eo.option_text
                        FROM exam_answers ea
                        JOIN exam_questions eq ON eq.id = ea.question_id
                        LEFT JOIN exam_question_options eo ON eo.id = ea.selected_option_id
                        WHERE ea.submission_id = ?
                        ORDER BY eq.question_order";
    }
    
    $answersStmt = $pdo->prepare($answersSql);
    $answersStmt->execute([$submission_id]);
    $answers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $submission['answers'] = $answers;
    
    echo json_encode([
        'success' => true,
        'submission' => $submission
    ]);
}
?>
