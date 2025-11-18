<?php
// Include database connection
require_once '../security/db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Get the action from POST or GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'mark_attendance':
            markAttendance();
            break;
        case 'get_attendance':
            getAttendance();
            break;
        case 'get_courses':
            getCourses();
            break;
        case 'get_students':
            getStudents();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log('Attendance handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

function markAttendance() {
    global $pdo;
    
    // Get form data
    $studentId = trim($_POST['student_id'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $score = intval($_POST['score'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $batch = intval($_POST['batch'] ?? 1);
    
    // Basic validation
    if (empty($studentId) || empty($status) || empty($date)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Map attendance to grade_details table
    $gradeNumber = 2; // Grade 2 is for attendance
    $component = $status === 'present' ? 'Present' : 'Absent';
    $rawScore = $score;
    $totalItems = 100; // Total possible score
    $transmuted = $score; // Transmuted score is the same as raw score for attendance
    
    // Check if attendance already exists for this student on this date
    $checkStmt = $pdo->prepare("
        SELECT id FROM grade_details 
        WHERE student_number = ? 
        AND grade_number = ? 
        AND date_given = ?
    ");
    $checkStmt->execute([$studentId, $gradeNumber, $date]);
    $existingRecord = $checkStmt->fetch();
    
    if ($existingRecord) {
        // Update existing record
        $updateStmt = $pdo->prepare("
            UPDATE grade_details 
            SET component = ?, raw_score = ?, total_items = ?, transmuted = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updateStmt->execute([$component, $rawScore, $totalItems, $transmuted, $existingRecord['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance updated successfully',
            'action' => 'updated'
        ]);
    } else {
        // Insert new record
        $insertStmt = $pdo->prepare("
            INSERT INTO grade_details 
            (student_number, grade_number, component, date_given, raw_score, total_items, transmuted) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$studentId, $gradeNumber, $component, $date, $rawScore, $totalItems, $transmuted]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance saved successfully',
            'action' => 'inserted'
        ]);
    }
}

function getAttendance() {
    global $pdo;
    
    $studentId = trim($_POST['student_id'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $batch = intval($_POST['batch'] ?? 1);
    
    // Basic validation
    if (empty($studentId) || empty($date)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Get attendance for specific student and date
    $stmt = $pdo->prepare("
        SELECT component, raw_score, transmuted, date_given 
        FROM grade_details 
        WHERE student_number = ? 
        AND grade_number = 2 
        AND date_given = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $date]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attendance) {
        $status = $attendance['component'] === 'Present' ? 'present' : 'absent';
        $score = intval($attendance['transmuted']);
        
        echo json_encode([
            'success' => true,
            'attendance' => [
                'status' => $status,
                'score' => $score,
                'component' => $attendance['component'],
                'date' => $attendance['date_given']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'attendance' => null
        ]);
    }
}

// Function to consolidate duplicate courses
function consolidateDuplicateCourses($courses) {
    // Define course mappings - all caps versions should be merged into normal case versions
    $courseMappings = [
        'HAIRDRESSING (HDR)' => 'Hairdressing',
        'DRESSMAKING (DRM)' => 'Dressmaking',
        'HOUSEKEEPING (HSK)' => 'Housekeeping',
        'ELECTRONIC PRODUCTS AND ASSEMBLY SERVICNG (EPAS)' => 'Electronic Products and Assembly Servicing',
        'ELECTRONIC PRODUCTS AND ASSEMBLY SERVICING (EPAS)' => 'Electronic Products and Assembly Servicing',
        'ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)' => 'Electrical Installation and Maintenance',
        'EVENTS MANAGEMENT SERVICES (EVM)' => 'Events Management Services',
        'FOOD AND BEVERAGE SERVICES (FBS)' => 'Food and Beverage Services',
        'FOOD PROCESSING (FOP)' => 'Food Processing',
        'MASSAGE THERAPY (MAT)' => 'Massage Therapy',
        'RAC SERVICING (RAC)' => 'RAC Servicing',
        'SHIELDED METAL ARC WELDING (SMAW)' => 'Shielded Metal Arc Welding',
        'BEAUTY CARE (NAIL CARE) (BEC)' => 'Beauty Care (Nail Care)',
        'BREAD AND PASTRY PRODUCTION (BPP)' => 'Bread and Pastry Production',
        'COMPUTER SYSTEMS SERVICING (CSS)' => 'Computer Systems Servicing',
        'BASIC COMPUTER LITERACY (BCL)' => 'Basic Computer Literacy',
        'AUTOMOTIVE SERVICING (ATS)' => 'Automotive Servicing'
    ];
    
    $consolidatedCourses = [];
    $courseStats = [];
    
    // First pass: collect all course data
    foreach ($courses as $course) {
        $courseName = $course['name'];
        $normalizedName = $courseMappings[$courseName] ?? $courseName;
        
        if (!isset($courseStats[$normalizedName])) {
            $courseStats[$normalizedName] = [
                'name' => $normalizedName,
                'student_count' => 0,
                'attendance_rate' => 0,
                'total_attendance' => 0,
                'count' => 0
            ];
        }
        
        $courseStats[$normalizedName]['student_count'] += $course['student_count'];
        $courseStats[$normalizedName]['total_attendance'] += $course['attendance_rate'] * $course['student_count'];
        $courseStats[$normalizedName]['count']++;
    }
    
    // Second pass: calculate averages and create final courses
    foreach ($courseStats as $courseData) {
        if ($courseData['student_count'] > 0) {
            $courseData['attendance_rate'] = $courseData['total_attendance'] / $courseData['student_count'];
        }
        unset($courseData['total_attendance'], $courseData['count']);
        $consolidatedCourses[] = $courseData;
    }
    
    // Sort by course name
    usort($consolidatedCourses, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $consolidatedCourses;
}

function getCourses() {
    global $pdo;
    
    try {
        // Get courses directly from students table since courses table may not exist
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.course as name,
                   COUNT(DISTINCT s.id) as student_count,
                   COALESCE(AVG(gd.transmuted), 0) as attendance_rate
            FROM students s
            LEFT JOIN grade_details gd ON s.student_number = gd.student_number 
                AND gd.grade_number = 2 
                AND gd.component IN ('Present', 'Absent')
            WHERE s.course IS NOT NULL AND s.course != ''
            GROUP BY s.course
            ORDER BY s.course
        ");
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Consolidate duplicate courses
        $courses = consolidateDuplicateCourses($courses);
        
        // Add ID and code fields for compatibility
        foreach ($courses as &$course) {
            $course['id'] = md5($course['name']); // Generate ID from course name
            $course['code'] = strtoupper(substr(str_replace(' ', '', $course['name']), 0, 3)); // Generate code from name
        }
        
        // Debug: Log the courses found
        error_log('Courses found after consolidation: ' . json_encode($courses));
        
        // Add batch information to each course with actual student counts
        foreach ($courses as &$course) {
            $courseName = $course['name'];
            
            // Get student count for each batch
            $batchCounts = [];
            for ($batchNum = 1; $batchNum <= 4; $batchNum++) {
                $batchStmt = $pdo->prepare("
                    SELECT COUNT(*) as student_count 
                    FROM students 
                    WHERE course = ? AND batch = ?
                ");
                $batchStmt->execute([$courseName, $batchNum]);
                $batchResult = $batchStmt->fetch(PDO::FETCH_ASSOC);
                $batchCounts[$batchNum] = (int)($batchResult['student_count'] ?? 0);
            }
            
            $course['batches'] = [
                [
                    'batch_number' => 1,
                    'name' => 'Batch 1',
                    'period' => 'January - March',
                    'student_count' => $batchCounts[1]
                ],
                [
                    'batch_number' => 2,
                    'name' => 'Batch 2',
                    'period' => 'April - June',
                    'student_count' => $batchCounts[2]
                ],
                [
                    'batch_number' => 3,
                    'name' => 'Batch 3',
                    'period' => 'July - September',
                    'student_count' => $batchCounts[3]
                ],
                [
                    'batch_number' => 4,
                    'name' => 'Batch 4',
                    'period' => 'October - December',
                    'student_count' => $batchCounts[4]
                ]
            ];
        }
        
        echo json_encode(['success' => true, 'courses' => $courses]);
    } catch (Exception $e) {
        error_log('getCourses error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching courses: ' . $e->getMessage()]);
    }
}


function getStudents() {
    global $pdo;
    
    $courseId = $_GET['course_id'] ?? '';
    $batchNumber = $_GET['batch'] ?? 1;
    
    try {
        // Handle "all" parameters to get all students
        if ($courseId === 'all' && $batchNumber === 'all') {
            // Get all students with their attendance data
            $stmt = $pdo->prepare("
                SELECT s.id, s.student_number, 
                       CONCAT(s.first_name, ' ', s.last_name) as full_name,
                       s.course,
                       COALESCE(attendance_data.present_days, 0) as present_days,
                       COALESCE(attendance_data.absent_days, 0) as absent_days,
                       COALESCE(attendance_data.attendance_rate, 0) as attendance_rate,
                       s.created_at as enrollment_date
                FROM students s
                LEFT JOIN (
                    SELECT 
                        gd.student_number,
                        SUM(CASE WHEN gd.component = 'Present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN gd.component = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                        AVG(gd.transmuted) as attendance_rate
                    FROM grade_details gd
                    WHERE gd.grade_number = 2 
                    GROUP BY gd.student_number
                ) attendance_data ON s.student_number = attendance_data.student_number
                ORDER BY s.course, s.student_number
            ");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'students' => $students]);
            return;
        }
        
        // Original logic for specific course
        if (empty($courseId)) {
            echo json_encode(['success' => false, 'message' => 'Course ID required']);
            return;
        }
        
        // Since courseId is actually the course name (from the generated ID), use it directly
        $courseName = $courseId;
        
        // Define course mappings for student queries
        $courseMappings = [
            'HAIRDRESSING (HDR)' => 'Hairdressing',
            'DRESSMAKING (DRM)' => 'Dressmaking',
            'HOUSEKEEPING (HSK)' => 'Housekeeping',
            'ELECTRONIC PRODUCTS AND ASSEMBLY SERVICNG (EPAS)' => 'Electronic Products and Assembly Servicing',
            'ELECTRONIC PRODUCTS AND ASSEMBLY SERVICING (EPAS)' => 'Electronic Products and Assembly Servicing',
            'ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)' => 'Electrical Installation and Maintenance',
            'EVENTS MANAGEMENT SERVICES (EVM)' => 'Events Management Services',
            'FOOD AND BEVERAGE SERVICES (FBS)' => 'Food and Beverage Services',
            'FOOD PROCESSING (FOP)' => 'Food Processing',
            'MASSAGE THERAPY (MAT)' => 'Massage Therapy',
            'RAC SERVICING (RAC)' => 'RAC Servicing',
            'SHIELDED METAL ARC WELDING (SMAW)' => 'Shielded Metal Arc Welding',
            'BEAUTY CARE (NAIL CARE) (BEC)' => 'Beauty Care (Nail Care)',
            'BREAD AND PASTRY PRODUCTION (BPP)' => 'Bread and Pastry Production',
            'COMPUTER SYSTEMS SERVICING (CSS)' => 'Computer Systems Servicing',
            'BASIC COMPUTER LITERACY (BCL)' => 'Basic Computer Literacy',
            'AUTOMOTIVE SERVICING (ATS)' => 'Automotive Servicing'
        ];
        
        // Get students for this course and batch, including all variations
        $courseVariations = [$courseName];
        foreach ($courseMappings as $variation => $normalized) {
            if ($normalized === $courseName) {
                $courseVariations[] = $variation;
            }
        }
        
        $placeholders = str_repeat('?,', count($courseVariations) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_number, 
                   CONCAT(s.first_name, ' ', s.last_name) as full_name,
                   s.course, s.batch,
                   COALESCE(attendance_data.present_days, 0) as present_days,
                   COALESCE(attendance_data.absent_days, 0) as absent_days,
                   COALESCE(attendance_data.attendance_rate, 0) as attendance_rate,
                   s.created_at as enrollment_date
            FROM students s
            LEFT JOIN (
                SELECT 
                    gd.student_number,
                    SUM(CASE WHEN gd.component = 'Present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN gd.component = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                    AVG(gd.transmuted) as attendance_rate
                FROM grade_details gd
                WHERE gd.grade_number = 2 
                GROUP BY gd.student_number
            ) attendance_data ON s.student_number = attendance_data.student_number
            WHERE s.course IN ($placeholders) AND s.batch = ?
            ORDER BY s.student_number
        ");
        $params = array_merge($courseVariations, [$batchNumber]);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'students' => $students]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching students: ' . $e->getMessage()]);
    }
}
?>