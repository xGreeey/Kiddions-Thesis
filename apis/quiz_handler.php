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
        case 'create_quiz':
            createQuiz($input['quiz']);
            break;
        case 'get_quizzes':
            getQuizzes();
            break;
        case 'get_quiz':
            getQuiz($input['quiz_id']);
            break;
        case 'update_quiz':
            updateQuiz($input['quiz_id'], $input['quiz']);
            break;
        case 'delete_quiz':
            deleteQuiz($input['quiz_id']);
            break;
        case 'publish_quiz':
            publishQuiz($input['quiz_id']);
            break;
        case 'unpublish_quiz':
            unpublishQuiz($input['quiz_id']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createQuiz($quizData) {
    global $pdo, $instructor_id;
    $title = $quizData['title'];
    $description = $quizData['description'] ?? '';
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert quiz
        $sql = "INSERT INTO quizzes (instructor_id, title, description, status) VALUES (?, ?, ?, 'draft')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$instructor_id, $title, $description]);
        $quiz_id = $pdo->lastInsertId();
        
        // Insert questions
        if (isset($quizData['questions']) && is_array($quizData['questions'])) {
            foreach ($quizData['questions'] as $questionData) {
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
                
                $sql = "INSERT INTO quiz_questions (quiz_id, question_text, question_type, is_required, question_order) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$quiz_id, $question_text, $question_type, $is_required, $question_order]);
                $question_id = $pdo->lastInsertId();
                
                // Insert options for multiple choice questions
                if (($question_type === 'multiple_choice' || $question_type === 'checkbox') && isset($questionData['options']) && is_array($questionData['options'])) {
                    foreach ($questionData['options'] as $index => $option) {
                        $option_order = $index + 1;
                        $option_text = is_array($option) ? $option['text'] : $option;
                        $is_correct = is_array($option) ? ($option['is_correct'] ? 1 : 0) : 0;
                        $sql = "INSERT INTO quiz_question_options (question_id, option_text, option_order, is_correct) VALUES (?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$question_id, $option_text, $option_order, $is_correct]);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz created successfully',
            'quiz_id' => $quiz_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getQuizzes() {
    global $pdo, $instructor_id;
    
    $sql = "SELECT q.*, 
                   COUNT(qq.id) as question_count,
                   COUNT(qs.id) as submission_count
            FROM quizzes q 
            LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id
            LEFT JOIN quiz_submissions qs ON q.id = qs.quiz_id
            WHERE q.instructor_id = ?
            GROUP BY q.id
            ORDER BY q.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$instructor_id]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'quizzes' => $quizzes
    ]);
}

function getQuiz($quiz_id) {
    global $pdo, $instructor_id;
    
    // Get quiz details
    $sql = "SELECT * FROM quizzes WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id, $instructor_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
        return;
    }
    
    // Get questions
    $sql = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get options for each question
    foreach ($questions as &$question) {
        if ($question['question_type'] === 'multiple_choice') {
            $sql = "SELECT * FROM quiz_question_options WHERE question_id = ? ORDER BY option_order";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$question['id']]);
            $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    $quiz['questions'] = $questions;
    
    echo json_encode([
        'success' => true,
        'quiz' => $quiz
    ]);
}

function updateQuiz($quiz_id, $quizData) {
    global $pdo, $instructor_id;
    $title = $quizData['title'];
    $description = $quizData['description'] ?? '';
    
    // Check if quiz exists and belongs to instructor
    $sql = "SELECT id FROM quizzes WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id, $instructor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
        return;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update quiz
        $sql = "UPDATE quizzes SET title = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $quiz_id]);
        
        // Delete existing questions and options
        $sql = "DELETE FROM quiz_question_options WHERE question_id IN (SELECT id FROM quiz_questions WHERE quiz_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quiz_id]);
        
        $sql = "DELETE FROM quiz_questions WHERE quiz_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quiz_id]);
        
        // Insert new questions
        if (isset($quizData['questions']) && is_array($quizData['questions'])) {
            foreach ($quizData['questions'] as $questionData) {
                $question_text = $questionData['title'];
                $question_type = $questionData['type'] === 'multiple choice' ? 'multiple_choice' : 'paragraph';
                $is_required = $questionData['required'] ? 1 : 0;
                $question_order = $questionData['order'];
                
                $sql = "INSERT INTO quiz_questions (quiz_id, question_text, question_type, is_required, question_order) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$quiz_id, $question_text, $question_type, $is_required, $question_order]);
                $question_id = $pdo->lastInsertId();
                
                // Insert options for multiple choice questions
                if (($question_type === 'multiple_choice' || $question_type === 'checkbox') && isset($questionData['options']) && is_array($questionData['options'])) {
                    foreach ($questionData['options'] as $index => $option) {
                        $option_order = $index + 1;
                        $option_text = is_array($option) ? $option['text'] : $option;
                        $is_correct = is_array($option) ? ($option['is_correct'] ? 1 : 0) : 0;
                        $sql = "INSERT INTO quiz_question_options (question_id, option_text, option_order, is_correct) VALUES (?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$question_id, $option_text, $option_order, $is_correct]);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteQuiz($quiz_id) {
    global $pdo, $instructor_id;
    
    // Check if quiz exists and belongs to instructor
    $sql = "SELECT id FROM quizzes WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id, $instructor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
        return;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete quiz answers first
        $sql = "DELETE FROM quiz_answers WHERE submission_id IN (SELECT id FROM quiz_submissions WHERE quiz_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quiz_id]);
        
        // Delete quiz submissions
        $sql = "DELETE FROM quiz_submissions WHERE quiz_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quiz_id]);
        
        // Delete options
        $sql = "DELETE FROM quiz_question_options WHERE question_id IN (SELECT id FROM quiz_questions WHERE quiz_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quiz_id]);
        
        // Delete questions
        $sql = "DELETE FROM quiz_questions WHERE quiz_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quiz_id]);
        
        // Delete quiz
        $sql = "DELETE FROM quizzes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quiz_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function publishQuiz($quiz_id) {
    global $pdo, $instructor_id;
    
    // Check if quiz exists and belongs to instructor
    $sql = "SELECT id, title FROM quizzes WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id, $instructor_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
        return;
    }
    
    // Check if quiz has questions
    $sql = "SELECT COUNT(*) as question_count FROM quiz_questions WHERE quiz_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['question_count'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot publish quiz without questions']);
        return;
    }
    
    // Update quiz status to published
    $sql = "UPDATE quizzes SET status = 'published' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quiz published successfully'
    ]);
}

function unpublishQuiz($quiz_id) {
    global $pdo, $instructor_id;
    
    // Check if quiz exists and belongs to instructor
    $sql = "SELECT id, title FROM quizzes WHERE id = ? AND instructor_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id, $instructor_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
        return;
    }
    
    // Update quiz status to draft
    $sql = "UPDATE quizzes SET status = 'draft' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quiz unpublished successfully'
    ]);
}
?>  