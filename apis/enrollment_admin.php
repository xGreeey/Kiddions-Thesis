<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Suppress any output before JSON
ob_start();

try {
    require_once __DIR__ . '/../security/db_connect.php';
    require_once __DIR__ . '/../security/session_config.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database connection failed']);
    exit;
}

function ok($d){ 
    ob_clean();
    echo json_encode(['success'=>true]+$d); 
    exit; 
}
function bad($m){ 
    ob_clean();
    http_response_code(400); 
    echo json_encode(['success'=>false,'message'=>$m]); 
    exit; 
}

// Check if PDO is available
if (!isset($pdo) || !$pdo) {
    bad('Database connection not available');
}

$isAdmin = isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === 2;
if(!$isAdmin){ 
    ob_clean();
    http_response_code(403); 
    echo json_encode(['success'=>false,'message'=>'Forbidden']); 
    exit; 
}

// Debug logging
error_log("Enrollment admin - User role: " . ($_SESSION['user_role'] ?? 'not set'));
error_log("Enrollment admin - Is admin: " . ($isAdmin ? 'yes' : 'no'));
error_log("Enrollment admin - Action: " . $action);

// Add enrollment tracking columns to existing students table
try {
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_status ENUM('enrolled','completed','withdrawn') DEFAULT 'enrolled'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_start_date DATE NULL");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_end_date DATE NULL");
} catch (Throwable $e) {
    // Columns might already exist, ignore error
    error_log("Enrollment admin - Column creation error: " . $e->getMessage());
}

// History table to retain previous courses
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_course_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_number VARCHAR(20) NOT NULL,
        course VARCHAR(150) NOT NULL,
        status ENUM('completed','withdrawn') NOT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sn (student_number),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');

try {
    switch($action){
    case 'list':
        $sn = $_GET['student_number'] ?? '';
        error_log("Enrollment admin - Looking for student: " . $sn);
        
        try {
            $st = $sn !== ''
                ? ($q=$pdo->prepare("SELECT id, student_number, first_name, last_name, course, batch, enrollment_status, enrollment_start_date, enrollment_end_date, created_at FROM students WHERE student_number=? ORDER BY created_at DESC")) && $q->execute([$sn]) && $q
                : $pdo->query("SELECT id, student_number, first_name, last_name, course, batch, enrollment_status, enrollment_start_date, enrollment_end_date, created_at FROM students ORDER BY created_at DESC");
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            error_log("Enrollment admin - Found " . count($rows) . " students");
            ok(['data'=>$rows]);
        } catch (Exception $e) {
            error_log("Enrollment admin - Database error: " . $e->getMessage());
            bad('Database error: ' . $e->getMessage());
        }
        break;
    case 'enroll':
        $sn = trim((string)($_POST['student_number'] ?? ''));
        $code = trim((string)($_POST['course_code'] ?? ''));
        $inputStart = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
        if($sn===''||$code==='') bad('Missing fields');
        
        // Check if student exists
        $chk = $pdo->prepare("SELECT id, enrollment_status, course FROM students WHERE student_number=? LIMIT 1");
        $chk->execute([$sn]);
        $student = $chk->fetch(PDO::FETCH_ASSOC);
        if(!$student) bad('Student not found');
        
        // Get course details (include duration to recompute end date if custom start is provided)
        $c = $pdo->prepare("SELECT 
                COALESCE(start_date,CURDATE()) AS s,
                COALESCE(end_date,DATE_ADD(COALESCE(start_date,CURDATE()), INTERVAL COALESCE(default_duration_days,90) DAY)) AS e,
                COALESCE(default_duration_days,90) AS d,
                name 
            FROM courses 
            WHERE code=? LIMIT 1");
        $c->execute([$code]);
        $course = $c->fetch(PDO::FETCH_ASSOC);
        if(!$course) bad('Course not found');
        $courseName = $course['name'];
        
        // Block if student has completed this course previously (history or current record)
        $hist = $pdo->prepare("SELECT COUNT(*) FROM student_course_history WHERE student_number=? AND course=? AND status='completed'");
        $hist->execute([$sn, $courseName]);
        $completedCount = (int)$hist->fetchColumn();
        if($completedCount > 0){
            bad('Cannot Enroll on the previous Course');
        }
        if(isset($student['course']) && isset($student['enrollment_status']) && $student['course'] === $courseName && $student['enrollment_status'] === 'completed'){
            bad('Cannot Enroll on the previous Course');
        }
        
        // Ensure no active enrollment exists for this student
        if(($student['enrollment_status'] ?? null) === 'enrolled') bad('Student has an active enrollment');
        
        // Determine start date (prefer provided input if valid) and validate not in the past
        $today = new DateTime('today');
        $startDate = null;
        if($inputStart !== ''){
            try {
                $dt = new DateTime($inputStart);
            } catch (Throwable $e) {
                bad('Invalid start date format');
            }
            if($dt < $today){
                bad('Start date cannot be in the past');
            }
            $startDate = $dt->format('Y-m-d');
        } else {
            $startDate = $course['s'] ?: date('Y-m-d');
            $dt = new DateTime($startDate);
            if($dt < $today){
                // If course default start is in the past, bump to today
                $startDate = $today->format('Y-m-d');
            }
        }
        
        // Determine end date based on duration from selected start
        $durationDays = (int)($course['d'] ?? 90);
        $endDate = (new DateTime($startDate))->modify('+' . $durationDays . ' days')->format('Y-m-d');
        
        // Update student with new enrollment
        $upd = $pdo->prepare("UPDATE students SET course=?, enrollment_status='enrolled', enrollment_start_date=?, enrollment_end_date=? WHERE student_number=?");
        $upd->execute([$courseName, $startDate, $endDate, $sn]);
        ok(['message'=>'Enrolled','start_date'=>$startDate,'end_date'=>$endDate]);
        break;
        
    case 'update_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if($id<=0 || !in_array($status, ['enrolled','completed','withdrawn'], true)) bad('Invalid data');
        // Fetch current student for history snapshot
        $cur = $pdo->prepare("SELECT student_number, course, enrollment_start_date, enrollment_end_date FROM students WHERE id=? LIMIT 1");
        $cur->execute([$id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
        $end = ($status !== 'enrolled') ? date('Y-m-d') : null;
        $st = $pdo->prepare("UPDATE students SET enrollment_status=?, enrollment_end_date=COALESCE(?, enrollment_end_date) WHERE id=?");
        $st->execute([$status, $end, $id]);
        // If marking as completed/withdrawn, store history
        if(in_array($status, ['completed','withdrawn'], true)){
            if(!empty($row['student_number']) && !empty($row['course'])){
                $ins = $pdo->prepare("INSERT INTO student_course_history (student_number, course, status, start_date, end_date) VALUES (?,?,?,?,?)");
                $ins->execute([$row['student_number'], $row['course'], $status, ($row['enrollment_start_date'] ?? null), ($end ?: ($row['enrollment_end_date'] ?? null))]);
            }
        }
        ok(['message'=>'Updated']);
        break;
        
    case 'adjust_dates':
        $id = (int)($_POST['id'] ?? 0);
        $start = $_POST['start_date'] ?? null;
        $end = $_POST['end_date'] ?? null;
        if($id<=0) bad('Invalid id');
        $sets=[];$vals=[];
        if($start!==null){ $sets[]='enrollment_start_date=?'; $vals[]=$start!==''?$start:null; }
        if($end!==null){ $sets[]='enrollment_end_date=?'; $vals[]=$end!==''?$end:null; }
        if(!$sets) ok(['message'=>'No changes']);
        $vals[]=$id;
        $st = $pdo->prepare('UPDATE students SET '.implode(',', $sets).' WHERE id=?');
        $st->execute($vals);
        ok(['message'=>'Updated']);
        break;
    default:
        bad('Invalid action');
    }
} catch (Exception $e) {
    ob_clean();
    error_log("Enrollment admin error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Internal server error']);
    exit;
}
?>


