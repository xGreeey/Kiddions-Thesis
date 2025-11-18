<?php
header('Content-Type: application/json');
error_log("Course admin API accessed at: " . date('Y-m-d H:i:s'));

require_once __DIR__ . '/../security/db_connect.php';
require_once __DIR__ . '/../security/session_config.php';

function ok($data){ echo json_encode(['success'=>true]+$data); exit; }
function bad($msg){ http_response_code(400); echo json_encode(['success'=>false,'message'=>$msg]); exit; }

// Basic admin check (is_role==2 assumed for admins per mmtvtc_users)
$isAdmin = isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === 2;
error_log("Admin check - user_role: " . ($_SESSION['user_role'] ?? 'not set') . ", isAdmin: " . ($isAdmin ? 'true' : 'false'));

if(!$isAdmin){ 
    error_log("Access denied - user is not admin");
    http_response_code(403); 
    echo json_encode(['success'=>false,'message'=>'Forbidden - Admin access required']); 
    exit; 
}

// Ensure schema
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS status ENUM('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming'");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS start_date DATE NULL");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS end_date DATE NULL");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS default_duration_days INT DEFAULT 90");
} catch (Throwable $e) {}

$action = isset($_POST['action']) ? $_POST['action'] : ($_GET['action'] ?? 'list');
error_log("API action received: " . $action);

switch($action){
    case 'test':
        ok(['message'=>'API is working', 'timestamp'=>date('Y-m-d H:i:s')]);
    case 'list':
        $st = $pdo->query("SELECT id, code, name, is_active, COALESCE(status,'upcoming') AS status, start_date, end_date, COALESCE(default_duration_days,90) AS default_duration_days FROM courses ORDER BY name");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        ok(['data'=>$rows]);
    case 'create':
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        
        if(empty($code) || empty($name)) bad('Course code and name are required');
        
        // Check if course code already exists
        $st = $pdo->prepare("SELECT id FROM courses WHERE code = ?");
        $st->execute([$code]);
        if($st->fetch()) bad('Course code already exists');
        
        // Insert new course
        $st = $pdo->prepare("INSERT INTO courses (code, name, is_active, status, default_duration_days) VALUES (?, ?, 1, 'upcoming', 90)");
        $st->execute([$code, $name]);
        
        $newId = $pdo->lastInsertId();
        ok(['message'=>'Course created successfully', 'id'=>$newId]);
    case 'delete':
        // Debug logging
        error_log("Delete request received. POST data: " . print_r($_POST, true));
        
        $courseId = (int)($_POST['course_id'] ?? 0);
        error_log("Course ID from POST: " . $courseId);
        
        if($courseId <= 0) {
            error_log("Invalid course ID: " . $courseId);
            bad('Invalid course ID');
        }
        
        try {
            // Start transaction for data integrity
            $pdo->beginTransaction();
            
            // Get course details for logging
            $st = $pdo->prepare("SELECT code, name FROM courses WHERE id = ?");
            $st->execute([$courseId]);
            $course = $st->fetch(PDO::FETCH_ASSOC);
            
            if(!$course) {
                $pdo->rollBack();
                bad('Course not found');
            }
            
            $courseCode = $course['code'];
            $courseName = $course['name'];
            
            // Delete related data in proper order (foreign key constraints)
            
            // 1. Delete exam submissions and related data
            $stmt = $pdo->prepare("DELETE es FROM exam_submissions es 
                       JOIN exams e ON es.exam_id = e.id 
                       WHERE e.course_id = ?");
            $stmt->execute([$courseId]);
            
            $stmt = $pdo->prepare("DELETE eqo FROM exam_question_options eqo 
                       JOIN exam_questions eq ON eqo.question_id = eq.id 
                       JOIN exams e ON eq.exam_id = e.id 
                       WHERE e.course_id = ?");
            $stmt->execute([$courseId]);
            
            $stmt = $pdo->prepare("DELETE eq FROM exam_questions eq 
                       JOIN exams e ON eq.exam_id = e.id 
                       WHERE e.course_id = ?");
            $stmt->execute([$courseId]);
            
            $stmt = $pdo->prepare("DELETE FROM exams WHERE course_id = ?");
            $stmt->execute([$courseId]);
            
            // 2. Delete quiz submissions and related data
            $stmt = $pdo->prepare("DELETE qs FROM quiz_submissions qs 
                       JOIN quizzes q ON qs.quiz_id = q.id 
                       WHERE q.course_id = ?");
            $stmt->execute([$courseId]);
            
            $stmt = $pdo->prepare("DELETE qqo FROM quiz_question_options qqo 
                       JOIN quiz_questions qq ON qqo.question_id = qq.id 
                       JOIN quizzes q ON qq.quiz_id = q.id 
                       WHERE q.course_id = ?");
            $stmt->execute([$courseId]);
            
            $stmt = $pdo->prepare("DELETE qq FROM quiz_questions qq 
                       JOIN quizzes q ON qq.quiz_id = q.id 
                       WHERE q.course_id = ?");
            $stmt->execute([$courseId]);
            
            $stmt = $pdo->prepare("DELETE FROM quizzes WHERE course_id = ?");
            $stmt->execute([$courseId]);
            
            // 3. Delete assessments and related data (assessments table doesn't have course_id)
            // Note: Assessments table doesn't have course_id column, so we skip this deletion
            
            // 4. Note: We are NOT deleting student-related data to preserve student records
            // The following deletions are commented out to prevent data loss:
            // - grade_details (student grades)
            // - computed_grades (student computed grades) 
            // - student_course_history (student course history)
            // - enrollments (student enrollments)
            // - student course references
            
            // This ensures that when a course is deleted, student records remain intact
            
            // 5. Finally delete the course itself
            $del = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $del->execute([$courseId]);
            
            if($del->rowCount() === 0) {
                $pdo->rollBack();
                bad("Course could not be deleted");
            }
            
            // Commit transaction
            $pdo->commit();
            
            ok([
                'message' => 'Course deleted successfully',
                'course_name' => $courseName,
                'course_code' => $courseCode
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Database error during course deletion: " . $e->getMessage());
            bad("Database error: " . $e->getMessage());
        }
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if($id<=0) bad('Invalid id');
        $status = $_POST['status'] ?? null;
        $start = $_POST['start_date'] ?? null;
        $end = $_POST['end_date'] ?? null;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
        $duration = isset($_POST['default_duration_days']) ? (int)$_POST['default_duration_days'] : null;
        $sets = [];$vals=[];
        if($status!==null){ $sets[]='status=?'; $vals[]=$status; }
        if($start!==null){ $sets[]='start_date=?'; $vals[]=$start!==''?$start:null; }
        if($end!==null){ $sets[]='end_date=?'; $vals[]=$end!==''?$end:null; }
        if($duration!==null){ $sets[]='default_duration_days=?'; $vals[]=$duration; }
        if($isActive!==null){ $sets[]='is_active=?'; $vals[]=$isActive; }
        if(!$sets) ok(['message'=>'No changes']);
        $vals[]=$id;
        $sql = 'UPDATE courses SET '.implode(',', $sets).' WHERE id=?';
        $st = $pdo->prepare($sql); $st->execute($vals);
        ok(['message'=>'Updated']);
    default:
        bad('Invalid action');
}
?>


