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

$input = json_decode(file_get_contents('php://input'), true);

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

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$action = $input['action'];

try {
    switch ($action) {
        case 'create_exam':
            createExam($input['exam']);
            break;
        case 'get_exams':
            getExams();
            break;
        case 'get_exam':
            getExam($input['exam_id']);
            break;
        case 'update_exam':
            updateExam($input['exam_id'], $input['exam']);
            break;
        case 'delete_exam':
            deleteExam($input['exam_id']);
            break;
        case 'publish_exam':
            publishExam($input['exam_id']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createExam($examData) {
    global $pdo, $instructor_id;
    $title = $examData['title'];
    $description = $examData['description'] ?? '';
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert exam
        $sql = "INSERT INTO exams (instructor_id, title, description, status) VALUES (?, ?, ?, 'draft')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$instructor_id, $title, $description]);
        $exam_id = $pdo->lastInsertId();
        
        // Insert questions
        if (isset($examData['questions']) && is_array($examData['questions'])) {
            foreach ($examData['questions'] as $questionData) {
                $question_text = $questionData['title'];
                $question_type = 'multiple_choice'; // default
                if ($questionData['type'] === 'multiple choice') {
                    $question_type = 'multiple_choice';
                } elseif ($questionData['type'] === 'checkbox') {
                    $question_type = 'checkbox';
                } elseif ($questionData['type'] === 'short answer') {
                    $question_type = 'short_answer';
                } elseif ($questionData['type'] === 'paragraph') {
                    $question_type = 'paragraph';
                }
                $is_required = $questionData['required'] ? 1 : 0;
                $question_order = $questionData['order'];
                
                $sql = "INSERT INTO exam_questions (exam_id, question_text, question_type, is_required, question_order) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$exam_id, $question_text, $question_type, $is_required, $question_order]);
                $question_id = $pdo->lastInsertId();
                
                // Insert options for multiple choice questions
                if (($question_type === 'multiple_choice' || $question_type === 'checkbox') && isset($questionData['options']) && is_array($questionData['options'])) {
                    foreach ($questionData['options'] as $index => $option) {
                        $option_order = $index + 1;
                        $is_correct = 0;
                        
                        // Check if this is the correct answer
                        if (isset($questionData['correctAnswerIndex']) && $index === $questionData['correctAnswerIndex']) {
                            $is_correct = 1;
                        }
                        
                        $sql = "INSERT INTO exam_question_options (question_id, option_text, option_order, is_correct) VALUES (?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$question_id, $option, $option_order, $is_correct]);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Exam created successfully',
            'exam_id' => $exam_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getExams() {
    global $pdo, $instructor_id;
    
    $sql = "SELECT e.*, 
                   COUNT(eq.id) as question_count,
                   COUNT(es.id) as submission_count
            FROM exams e 
            LEFT JOIN exam_questions eq ON e.id = eq.exam_id
            LEFT JOIN exam_submissions es ON e.id = es.exam_id
            WHERE e.instructor_id = ?
            GROUP BY e.id
            ORDER BY e.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$instructor_id]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'exams' => $exams
    ]);
}

function getExam($exam_id) {
    global $pdo, $instructor_id;
    
    // Get exam details
    $sql = "SELECT * FROM exams WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id, $instructor_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        return;
    }
    
    // Get questions
    $sql = "SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY question_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get options for each question
    foreach ($questions as &$question) {
        if ($question['question_type'] === 'multiple_choice') {
            $sql = "SELECT * FROM exam_question_options WHERE question_id = ? ORDER BY option_order";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$question['id']]);
            $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    $exam['questions'] = $questions;
    
    echo json_encode([
        'success' => true,
        'exam' => $exam
    ]);
}

function updateExam($exam_id, $examData) {
    global $pdo, $instructor_id;
    $title = $examData['title'];
    $description = $examData['description'] ?? '';
    
    // Check if exam exists and belongs to instructor
    $sql = "SELECT id FROM exams WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id, $instructor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        return;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update exam
        $sql = "UPDATE exams SET title = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $exam_id]);
        
        // Delete existing questions and options
        $sql = "DELETE FROM exam_question_options WHERE question_id IN (SELECT id FROM exam_questions WHERE exam_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exam_id]);
        
        $sql = "DELETE FROM exam_questions WHERE exam_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exam_id]);
        
        // Insert new questions
        if (isset($examData['questions']) && is_array($examData['questions'])) {
            foreach ($examData['questions'] as $questionData) {
                $question_text = $questionData['title'];
                $question_type = 'multiple_choice'; // default
                if ($questionData['type'] === 'multiple choice') {
                    $question_type = 'multiple_choice';
                } elseif ($questionData['type'] === 'checkbox') {
                    $question_type = 'checkbox';
                } elseif ($questionData['type'] === 'short answer') {
                    $question_type = 'short_answer';
                } elseif ($questionData['type'] === 'paragraph') {
                    $question_type = 'paragraph';
                }
                $is_required = $questionData['required'] ? 1 : 0;
                $question_order = $questionData['order'];
                
                $sql = "INSERT INTO exam_questions (exam_id, question_text, question_type, is_required, question_order) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$exam_id, $question_text, $question_type, $is_required, $question_order]);
                $question_id = $pdo->lastInsertId();
                
                // Insert options for multiple choice questions
                if (($question_type === 'multiple_choice' || $question_type === 'checkbox') && isset($questionData['options']) && is_array($questionData['options'])) {
                    foreach ($questionData['options'] as $index => $option) {
                        $option_order = $index + 1;
                        $is_correct = 0;
                        
                        // Check if this is the correct answer
                        if (isset($questionData['correctAnswerIndex']) && $index === $questionData['correctAnswerIndex']) {
                            $is_correct = 1;
                        }
                        
                        $sql = "INSERT INTO exam_question_options (question_id, option_text, option_order, is_correct) VALUES (?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$question_id, $option, $option_order, $is_correct]);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Exam updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteExam($exam_id) {
    global $pdo, $instructor_id;
    
    // Check if exam exists and belongs to instructor
    $sql = "SELECT id FROM exams WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id, $instructor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        return;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete exam answers first
        $sql = "DELETE FROM exam_answers WHERE submission_id IN (SELECT id FROM exam_submissions WHERE exam_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exam_id]);
        
        // Delete exam submissions
        $sql = "DELETE FROM exam_submissions WHERE exam_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exam_id]);
        
        // Delete options
        $sql = "DELETE FROM exam_question_options WHERE question_id IN (SELECT id FROM exam_questions WHERE exam_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exam_id]);
        
        // Delete questions
        $sql = "DELETE FROM exam_questions WHERE exam_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exam_id]);
        
        // Delete exam
        $sql = "DELETE FROM exams WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exam_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Exam deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function publishExam($exam_id) {
    global $pdo, $instructor_id;
    
    // Check if exam exists and belongs to instructor
    $sql = "SELECT id, title FROM exams WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id, $instructor_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        return;
    }
    
    // Check if exam has questions
    $sql = "SELECT COUNT(*) as question_count FROM exam_questions WHERE exam_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['question_count'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot publish exam without questions']);
        return;
    }
    
    // Update exam status to published
    $sql = "UPDATE exams SET status = 'published' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Exam published successfully'
    ]);
}
?>