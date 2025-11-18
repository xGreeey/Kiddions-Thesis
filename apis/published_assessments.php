<?php
header('Content-Type: application/json');
// Prevent caching so students see live data
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../security/db_connect.php';
require_once __DIR__ . '/../security/session_config.php';

try {
    $studentNumber = isset($_GET['student_number']) ? trim((string)$_GET['student_number']) : '';
    $typeFilter = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : '';
    $courseFilter = isset($_GET['course']) ? trim((string)$_GET['course']) : '';
    $action = isset($_GET['action']) ? strtolower(trim((string)$_GET['action'])) : 'list';
    $detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $studentId = null;
    $studentCourse = '';
    if ($studentNumber !== '') {
        $s = $pdo->prepare('SELECT id, course FROM students WHERE student_number = ? ORDER BY id DESC LIMIT 1');
        $s->execute([$studentNumber]);
        if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (int)$row['id'];
            $studentCourse = (string)($row['course'] ?? '');
        } else {
            // Fallback to add_trainees for course if not in students yet
            $s2 = $pdo->prepare('SELECT course FROM add_trainees WHERE student_number = ? ORDER BY id DESC LIMIT 1');
            $s2->execute([$studentNumber]);
            if ($r2 = $s2->fetch(PDO::FETCH_ASSOC)) {
                $studentCourse = (string)($r2['course'] ?? '');
            }
        }
    }
    
    // Debug logging for troubleshooting
    error_log("Published Assessments Debug - Student Number: $studentNumber, Student ID: " . ($studentId ?? 'null') . ", Course: $studentCourse");
    
    // If no student found by student_number, try to find any student ID that has submissions
    if (!$studentId) {
        // First try to find a student with quiz submissions
        $fallbackStmt = $pdo->prepare('SELECT DISTINCT student_id FROM quiz_submissions ORDER BY student_id ASC LIMIT 1');
        $fallbackStmt->execute();
        $fallbackResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        if ($fallbackResult) {
            $studentId = (int)$fallbackResult['student_id'];
        } else {
            // If no quiz submissions, try exam submissions
            $fallbackStmt = $pdo->prepare('SELECT DISTINCT student_id FROM exam_submissions ORDER BY student_id ASC LIMIT 1');
            $fallbackStmt->execute();
            $fallbackResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            if ($fallbackResult) {
                $studentId = (int)$fallbackResult['student_id'];
            } else {
                // Last resort: get any student ID
                $fallbackStmt = $pdo->prepare('SELECT id FROM students ORDER BY id ASC LIMIT 1');
                $fallbackStmt->execute();
                $fallbackResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                if ($fallbackResult) {
                    $studentId = (int)$fallbackResult['id'];
                }
            }
        }
    }

    $bindCourse = $courseFilter !== '' ? $courseFilter : $studentCourse;

    // Detail mode: return full quiz/exam structure with questions and options
    if ($action === 'detail' && $detailId > 0 && ($typeFilter === 'quiz' || $typeFilter === 'exam')) {
        if ($typeFilter === 'quiz') {
            $q = $pdo->prepare("SELECT id, title, description, status, created_at FROM quizzes WHERE id = ? AND status = 'published' LIMIT 1");
            $q->execute([$detailId]);
            $quiz = $q->fetch(PDO::FETCH_ASSOC);
            if (!$quiz) { echo json_encode(['success' => false, 'message' => 'Quiz not found']); return; }
            $qs = $pdo->prepare('SELECT id, question_text, question_type, is_required, question_order FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order');
            $qs->execute([$detailId]);
            $questions = $qs->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($questions as &$question) {
                if (($question['question_type'] ?? '') === 'multiple_choice' || ($question['question_type'] ?? '') === 'checkbox') {
                    $ops = $pdo->prepare('SELECT id, option_text, option_order FROM quiz_question_options WHERE question_id = ? ORDER BY option_order');
                    $ops->execute([(int)$question['id']]);
                    $question['options'] = $ops->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $question['options'] = [];
                }
            }
            echo json_encode(['success' => true, 'type' => 'quiz', 'assessment' => $quiz, 'questions' => $questions]);
            return;
        } else {
            $e = $pdo->prepare("SELECT id, title, description, status, created_at FROM exams WHERE id = ? AND status = 'published' LIMIT 1");
            $e->execute([$detailId]);
            $exam = $e->fetch(PDO::FETCH_ASSOC);
            if (!$exam) { echo json_encode(['success' => false, 'message' => 'Exam not found']); return; }
            $qs = $pdo->prepare('SELECT id, question_text, question_type, is_required, question_order FROM exam_questions WHERE exam_id = ? ORDER BY question_order');
            $qs->execute([$detailId]);
            $questions = $qs->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($questions as &$question) {
                if (($question['question_type'] ?? '') === 'multiple_choice' || ($question['question_type'] ?? '') === 'checkbox') {
                    $ops = $pdo->prepare('SELECT id, option_text, option_order FROM exam_question_options WHERE question_id = ? ORDER BY option_order');
                    $ops->execute([(int)$question['id']]);
                    $question['options'] = $ops->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } else {
                    $question['options'] = [];
                }
            }
            echo json_encode(['success' => true, 'type' => 'exam', 'assessment' => $exam, 'questions' => $questions]);
            return;
        }
    }

    $results = [ 'success' => true, 'data' => [] ];

    // Build queries for quizzes and exams
    $queries = [];
    if ($typeFilter === '' || $typeFilter === 'quiz') {
        if ($studentId) {
            $queries[] = [
                'sql' => "SELECT 'quiz' AS type, q.id, q.title, q.description, q.created_at, q.status,
                                 i.first_name, i.last_name, i.primary_course,
                                 COALESCE(qs.status, 'Not started') AS submission_status,
                                 qs.submitted_at, qs.score
                            FROM quizzes q
                            JOIN instructors i ON i.id = q.instructor_id
                            LEFT JOIN quiz_submissions qs ON qs.quiz_id = q.id AND qs.student_id = :student_id
                            WHERE q.status = 'published'
                            ORDER BY q.created_at DESC",
                'type' => 'quiz'
            ];
        } else {
            $queries[] = [
                'sql' => "SELECT 'quiz' AS type, q.id, q.title, q.description, q.created_at, q.status,
                                 i.first_name, i.last_name, i.primary_course,
                                 'Not started' AS submission_status
                            FROM quizzes q
                            JOIN instructors i ON i.id = q.instructor_id
                            WHERE q.status = 'published'
                            ORDER BY q.created_at DESC",
                'type' => 'quiz'
            ];
        }
    }
    if ($typeFilter === '' || $typeFilter === 'exam') {
        if ($studentId) {
            $queries[] = [
                'sql' => "SELECT 'exam' AS type, e.id, e.title, e.description, e.created_at, e.status,
                                 i.first_name, i.last_name, i.primary_course,
                                 COALESCE(es.status, 'Not started') AS submission_status,
                                 es.submitted_at, es.score
                            FROM exams e
                            JOIN instructors i ON i.id = e.instructor_id
                            LEFT JOIN exam_submissions es ON es.exam_id = e.id AND es.student_id = :student_id
                            WHERE e.status = 'published'
                            ORDER BY e.created_at DESC",
                'type' => 'exam'
            ];
        } else {
            $queries[] = [
                'sql' => "SELECT 'exam' AS type, e.id, e.title, e.description, e.created_at, e.status,
                                 i.first_name, i.last_name, i.primary_course,
                                 'Not started' AS submission_status
                            FROM exams e
                            JOIN instructors i ON i.id = e.instructor_id
                            WHERE e.status = 'published'
                            ORDER BY e.created_at DESC",
                'type' => 'exam'
            ];
        }
    }

    $items = [];
    foreach ($queries as $q) {
        $stmt = $pdo->prepare($q['sql']);
        if ($studentId) { 
            // Bind the parameter multiple times for nested queries
            $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        }
        $stmt->execute();
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'type' => (string)$r['type'],
                'id' => (int)$r['id'],
                'title' => (string)($r['title'] ?? ''),
                'description' => (string)($r['description'] ?? ''),
                'created_at' => (string)($r['created_at'] ?? ''),
                'instructor' => trim(((string)($r['first_name'] ?? '')) . ' ' . ((string)($r['last_name'] ?? ''))),
                'primary_course' => (string)($r['primary_course'] ?? ''),
                'status' => (string)($r['status'] ?? ''),
                'submission_status' => (string)($r['submission_status'] ?? ''),
                'submitted_at' => (string)($r['submitted_at'] ?? ''),
                'score' => (float)($r['score'] ?? 0)
            ];
        }
    }

    $results['data'] = $items;
    
    // Add debugging information for troubleshooting
    $results['debug'] = [
        'student_number' => $studentNumber,
        'student_id' => $studentId,
        'student_course' => $studentCourse,
        'course_filter' => $courseFilter,
        'type_filter' => $typeFilter,
        'total_items' => count($items),
        'has_submissions' => $studentId ? 'yes' : 'no'
    ];
    
    echo json_encode($results);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load assessments', 'error' => $e->getMessage()]);
}
?>


