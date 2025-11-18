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
        
        // Use a default instructor ID for operations without authentication
        $instructor_id = 1; // Default instructor ID - change this as needed
        
        switch ($action) {
            case 'get_exam_submissions':
                getExamSubmissions();
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
    
    function getExamSubmissions() {
        global $pdo, $instructor_id;
        
        $sql = "SELECT es.id AS submission_id, es.exam_id, es.student_id, es.submitted_at, es.score, es.total_questions, es.correct_answers, es.status,
                       e.title AS exam_title,
                       s.first_name, s.last_name, s.student_number, s.course
                FROM exam_submissions es
                JOIN exams e ON es.exam_id = e.id
                JOIN students s ON es.student_id = s.id
                WHERE e.instructor_id = ?
                ORDER BY es.submitted_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$instructor_id]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'submissions' => $submissions]);
    }
    
    function getSubmissionDetails($submission_id, $type) {
        global $pdo, $instructor_id;
        
        if ($type === 'exam') {
            $sql = "SELECT es.id AS submission_id, es.exam_id, es.student_id, es.submitted_at, es.score, es.total_questions, es.correct_answers, es.status,
                           e.title AS exam_title,
                           s.first_name, s.last_name, s.student_number, s.course
                    FROM exam_submissions es
                    JOIN exams e ON es.exam_id = e.id
                    JOIN students s ON es.student_id = s.id
                    WHERE es.id = ? AND e.instructor_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$submission_id, $instructor_id]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$submission) {
                throw new Exception('Submission not found or unauthorized');
            }
            
            $answersSql = "SELECT ea.id AS answer_id, ea.question_id, ea.answer_text, ea.selected_option_id, ea.is_correct,
                                   eq.question_text, eq.question_type,
                                   eqo.option_text
                            FROM exam_answers ea
                            JOIN exam_questions eq ON ea.question_id = eq.id
                            LEFT JOIN exam_question_options eqo ON ea.selected_option_id = eqo.id
                            WHERE ea.submission_id = ?
                            ORDER BY eq.question_order";
            $answersStmt = $pdo->prepare($answersSql);
            $answersStmt->execute([$submission_id]);
            $submission['answers'] = $answersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'submission' => $submission]);
        } else {
            throw new Exception('Invalid submission type');
        }
    }
    ?>
