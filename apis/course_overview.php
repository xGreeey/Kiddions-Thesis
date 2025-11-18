<?php
session_start();
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/session_config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    if (!isset($pdo)) { throw new Exception('DB not available'); }

    $rows = [];
    // Preferred: courses table joined with users, to only list active courses with active students
    try {
        $stmt = $pdo->query("SELECT c.name AS course, COUNT(u.id) AS cnt
                              FROM courses c
                              JOIN mmtvtc_users u ON u.is_role = 0 AND u.course = c.name
                              WHERE c.is_active = 1
                              GROUP BY c.name
                              HAVING cnt > 0
                              ORDER BY c.name");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $ignore) { /* fallbacks below */ }

    if (empty($rows)) {
        // Fallback 1: derive from users table directly
        $stmt = $pdo->query("SELECT course, COUNT(*) AS cnt
                             FROM mmtvtc_users
                             WHERE is_role = 0 AND course IS NOT NULL AND course <> ''
                             GROUP BY course
                             HAVING cnt > 0
                             ORDER BY course");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
    if (empty($rows)) {
        // Fallback 2: legacy students table
        $stmt = $pdo->query("SELECT course, COUNT(*) AS cnt
                             FROM students
                             WHERE course IS NOT NULL AND course <> ''
                             GROUP BY course
                             HAVING cnt > 0
                             ORDER BY course");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => []]);
}
?>


