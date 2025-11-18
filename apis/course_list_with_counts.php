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

    // Prefer courses table with counts from mmtvtc_users
    $stmt = $pdo->query(
        "SELECT c.name AS course, COUNT(u.id) AS cnt
         FROM courses c
         LEFT JOIN mmtvtc_users u
           ON u.is_role = 0 AND u.course = c.name
         WHERE c.is_active = 1
         GROUP BY c.name
         ORDER BY c.name"
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (empty($rows)) {
        // Fallback: derive from users
        $stmt = $pdo->query("SELECT course, COUNT(*) AS cnt FROM mmtvtc_users WHERE is_role = 0 AND course IS NOT NULL AND course <> '' GROUP BY course ORDER BY course");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => []]);
}
?>


