<?php
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/session_config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    if (!isset($pdo)) { throw new Exception('DB not available'); }

    // Fetch latest 4 registrations across users and legacy students to ensure visibility
    $rows = [];
    try {
        $all = [];
        // mmtvtc_users
        try {
            $stmt = $pdo->query("SELECT student_number, first_name, last_name, course, created_at FROM mmtvtc_users WHERE is_role = 0 ORDER BY created_at DESC LIMIT 8");
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                $all[] = [
                    'student_number' => (string)($r['student_number'] ?? ''),
                    'first_name' => (string)($r['first_name'] ?? ''),
                    'last_name' => (string)($r['last_name'] ?? ''),
                    'course' => (string)($r['course'] ?? ''),
                    'created_at' => (string)($r['created_at'] ?? '')
                ];
            }
        } catch (Throwable $e1) {}

        // students
        try {
            $stmt = $pdo->query("SELECT student_number, first_name, last_name, course, created_at FROM students ORDER BY created_at DESC LIMIT 8");
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                $all[] = [
                    'student_number' => (string)($r['student_number'] ?? ''),
                    'first_name' => (string)($r['first_name'] ?? ''),
                    'last_name' => (string)($r['last_name'] ?? ''),
                    'course' => (string)($r['course'] ?? ''),
                    'created_at' => (string)($r['created_at'] ?? '')
                ];
            }
        } catch (Throwable $e2) {}

        // add_trainees
        try {
            $stmt = $pdo->query("SELECT id, surname, firstname, student_number, course, created_at FROM add_trainees ORDER BY created_at DESC, id DESC LIMIT 8");
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                $all[] = [
                    'student_number' => (string)($r['student_number'] ?? (string)($r['id'] ?? '')),
                    'first_name' => (string)($r['firstname'] ?? ''),
                    'last_name' => (string)($r['surname'] ?? ''),
                    'course' => (string)($r['course'] ?? ''),
                    'created_at' => (string)($r['created_at'] ?? '')
                ];
            }
        } catch (Throwable $e3) {}

        // Sort by created_at desc (fallback to timestamp 0 if missing) then limit 4
        usort($all, function($a, $b){
            $ta = strtotime($a['created_at'] ?? '') ?: 0;
            $tb = strtotime($b['created_at'] ?? '') ?: 0;
            if ($ta === $tb) return 0;
            return ($ta > $tb) ? -1 : 1;
        });
        $rows = array_slice($all, 0, 4);
    } catch (Throwable $inner) {
        // Progressive fallbacks if anything above fails
        $rows = [];
        try {
            $stmt = $pdo->query("SELECT student_number, first_name, last_name, course, created_at FROM mmtvtc_users WHERE is_role = 0 ORDER BY created_at DESC LIMIT 4");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $ignored) {}
        if (empty($rows)) {
            try {
                $stmt = $pdo->query("SELECT student_number, first_name, last_name, course, created_at FROM students ORDER BY created_at DESC LIMIT 4");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            } catch (Throwable $ignored2) {}
        }
        if (empty($rows)) {
            try {
                $stmt = $pdo->query("SELECT id, surname, firstname, student_number, course, created_at FROM add_trainees ORDER BY created_at DESC, id DESC LIMIT 4");
                foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                    $rows[] = [
                        'student_number' => (string)($r['student_number'] ?? (string)($r['id'] ?? '')),
                        'first_name' => (string)($r['firstname'] ?? ''),
                        'last_name' => (string)($r['surname'] ?? ''),
                        'course' => (string)($r['course'] ?? ''),
                        'created_at' => (string)($r['created_at'] ?? '')
                    ];
                }
            } catch (Throwable $ignored3) {}
        }
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => []]);
}
?>


