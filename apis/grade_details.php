<?php
require_once '../security/db_connect.php';
require_once '../security/session_config.php';
require_once '../security/csrf.php';
require_once '../security/auth_functions.php';

header('Content-Type: application/json');
// Prevent caching so dashboard shows live data
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Development-friendly: allow all actions without strict auth
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'add': {
            requireAnyRole(['admin','instructor']);
            csrfRequireValid();
            $studentNumber = trim($_POST['student_number'] ?? '');
            $gradeNumber   = (int)($_POST['grade_number'] ?? 0);
            $component     = trim($_POST['component'] ?? '');
            $dateGiven     = trim($_POST['date'] ?? '');
            $raw           = (int)($_POST['raw'] ?? 0);
            $total         = (int)($_POST['total'] ?? 0);
            $transmuted    = (float)($_POST['transmuted'] ?? 0);

            // Normalize component for empty input
            if ($component === '') {
                $component = 'quiz';
            }

            if ($studentNumber === '' || $gradeNumber < 1 || $gradeNumber > 4 || $total <= 0 || $raw < 0 || $raw > $total) {
                throw new Exception('Invalid payload');
            }

            $stmt = $pdo->prepare("INSERT INTO grade_details (
                student_number, grade_number, component, date_given, raw_score, total_items, transmuted
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$studentNumber, $gradeNumber, $component, $dateGiven ?: null, $raw, $total, $transmuted]);
            // Debug: log insert result for troubleshooting environment/db mismatches
            try {
                $newId = $pdo->lastInsertId();
                error_log("GradeDetails ADD: id={$newId} sn={$studentNumber} gn={$gradeNumber} comp={$component} date=" . ($dateGiven ?: 'NULL') . " raw={$raw} total={$total} trans={$transmuted}");
            } catch (Throwable $_) { /* ignore logging failures */ }

            echo json_encode(['success' => true, 'id' => $newId ?? null]);
            break;
        }

        case 'list': {
            $studentNumber = trim($_GET['student_number'] ?? '');
            $gradeNumber   = (int)($_GET['grade_number'] ?? 0);
            if ($studentNumber === '' || $gradeNumber < 1 || $gradeNumber > 4) {
                throw new Exception('Invalid query');
            }
            $stmt = $pdo->prepare('SELECT * FROM grade_details WHERE student_number = ? AND grade_number = ? ORDER BY date_given IS NULL, date_given ASC, id ASC');
            $stmt->execute([$studentNumber, $gradeNumber]);
            $rows = $stmt->fetchAll();
            // Debug: log list count
            try { error_log("GradeDetails LIST: sn={$studentNumber} gn={$gradeNumber} rows=" . (is_array($rows)?count($rows):0)); } catch (Throwable $_) { /* ignore */ }
            echo json_encode(['success' => true, 'data' => $rows]);
            break;
        }

        case 'aggregate': {
            $studentNumber = trim($_GET['student_number'] ?? '');
            $gradeNumber   = (int)($_GET['grade_number'] ?? 0);
            if ($studentNumber === '' || $gradeNumber < 1 || $gradeNumber > 4) {
                throw new Exception('Invalid query');
            }
            $stmt = $pdo->prepare('SELECT AVG(transmuted) AS avg_transmuted FROM grade_details WHERE student_number = ? AND grade_number = ?');
            $stmt->execute([$studentNumber, $gradeNumber]);
            $row = $stmt->fetch();
            $avg = isset($row['avg_transmuted']) && $row['avg_transmuted'] !== null ? (float)$row['avg_transmuted'] : 0.0;
            echo json_encode(['success' => true, 'average' => $avg]);
            break;
        }

        case 'averages': {
            // Accepts comma-separated student_numbers via GET student_numbers
            $csv = trim($_GET['student_numbers'] ?? '');
            if ($csv === '') {
                throw new Exception('No student_numbers provided');
            }
            $list = array_values(array_filter(array_map('trim', explode(',', $csv)), function($v){ return $v !== ''; }));
            if (empty($list)) {
                throw new Exception('No valid student_numbers provided');
            }
            // De-duplicate
            $list = array_values(array_unique($list));
            // Build placeholders
            $placeholders = implode(',', array_fill(0, count($list), '?'));
            $sql = "SELECT student_number, AVG(transmuted) AS avg_transmuted
                    FROM grade_details
                    WHERE student_number IN ($placeholders)
                    GROUP BY student_number";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($list);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $map = [];
            foreach ($rows as $r) {
                $sn = (string)($r['student_number'] ?? '');
                $map[$sn] = isset($r['avg_transmuted']) && $r['avg_transmuted'] !== null ? (float)$r['avg_transmuted'] : 0.0;
            }
            // Ensure all requested S/N present (set 0 if missing)
            foreach ($list as $sn) {
                if (!array_key_exists($sn, $map)) {
                    $map[$sn] = 0.0;
                }
            }
            echo json_encode(['success' => true, 'averages' => $map]);
            break;
        }

        case 'delete': {
            requireAnyRole(['admin','instructor']);
            csrfRequireValid();
            // Supports deleting a single id (POST id) or multiple (POST ids[])
            // Return deleted count
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $ids = [];
            if ($id > 0) {
                $ids = [$id];
            } elseif (!empty($_POST['ids']) && is_array($_POST['ids'])) {
                foreach ($_POST['ids'] as $maybeId) {
                    $maybeId = (int)$maybeId;
                    if ($maybeId > 0) $ids[] = $maybeId;
                }
            }

            if (empty($ids)) {
                throw new Exception('No valid ids provided');
            }

            // Build dynamic IN clause safely
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM grade_details WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>


