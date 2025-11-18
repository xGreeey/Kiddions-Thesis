<?php
// Prevent caches from storing sensitive exports
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/session_config.php';
require_once '../security/auth_functions.php';

// Only admins (role 2) or authenticated users may export; mirror auth checks used in other handlers
requireAnyRole(['admin']);

$type = $_GET['type'] ?? 'grades';

function getTableColumns($pdo, $table) {
    try {
        $cols = [];
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "`");
        $stmt->execute();
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $cols[] = $r['Field']; }
        return $cols;
    } catch (Throwable $e) { return []; }
}

function sendCsv($filename, $header, $rows) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=' . $filename);
    $out = fopen('php://output', 'w');
    if ($header) { fputcsv($out, $header); }
    foreach ($rows as $r) { fputcsv($out, $r); }
    fclose($out);
    exit();
}

try {
    switch ($type) {
        case 'students': {
            $stmt = $pdo->query("SELECT id, user_id, student_number, first_name, last_name, middle_name, email, course, profile_photo, created_at FROM students ORDER BY created_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendCsv('students_backup.csv', array_keys($rows ? $rows[0] : ['id','user_id','student_number','first_name','last_name','middle_name','email','course','profile_photo','created_at']), array_map('array_values', $rows));
            break;
        }
        case 'trainees': {
            // Some installs use add_trainees for source-of-truth
            $rows = [];
            try {
                $st = $pdo->query("SELECT * FROM add_trainees ORDER BY created_at DESC");
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
            $header = $rows ? array_keys($rows[0]) : [];
            sendCsv('trainees_backup.csv', $header, array_map('array_values', $rows));
            break;
        }
        case 'jobs': {
            $stmt = $pdo->query("SELECT id, title, company, location, salary, experience, description, course, is_active, created_at, updated_at FROM jobs ORDER BY created_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendCsv('jobs_backup.csv', array_keys($rows ? $rows[0] : ['id','title','company','location','salary','experience','description','course','is_active','created_at','updated_at']), array_map('array_values', $rows));
            break;
        }
        case 'announcements': {
            $rows = [];
            try {
                $st = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
            $header = $rows ? array_keys($rows[0]) : [];
            sendCsv('announcements_backup.csv', $header, array_map('array_values', $rows));
            break;
        }
        case 'notifications': {
            $rows = [];
            try {
                $st = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
            $header = $rows ? array_keys($rows[0]) : [];
            sendCsv('notifications_backup.csv', $header, array_map('array_values', $rows));
            break;
        }
        case 'grades': {
            // Build safe column list
            $available = getTableColumns($pdo, 'grade_details');
            $preferred = ['id','student_number','grade_number','component','raw','transmuted','created_at','updated_at'];
            $cols = array_values(array_intersect($preferred, $available));
            if (empty($cols)) { $cols = $available; }
            $sql = 'SELECT ' . implode(',', array_map(function($c){ return '`'.$c.'`'; }, $cols)) . ' FROM grade_details ORDER BY ' . (in_array('created_at',$available)?'created_at':'id') . ' DESC';
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendCsv('grades_backup.csv', $cols, array_map('array_values', $rows));
            break;
        }
        case 'all': {
            if (!class_exists('ZipArchive')) { http_response_code(500); echo 'ZipArchive not available'; exit(); }
            $files = [];
            $datasets = [
                'students' => "SELECT id, user_id, student_number, first_name, last_name, middle_name, email, course, profile_photo, created_at FROM students ORDER BY created_at DESC",
                // 'grades' will be filled dynamically below
                'jobs' => "SELECT id, title, company, location, salary, experience, description, course, is_active, created_at, updated_at FROM jobs ORDER BY created_at DESC",
            ];
            // Optional tables
            $optional = [ 'trainees' => 'SELECT * FROM add_trainees ORDER BY created_at DESC', 'announcements' => 'SELECT * FROM announcements ORDER BY created_at DESC', 'notifications' => 'SELECT * FROM notifications ORDER BY created_at DESC' ];
            foreach ($optional as $k=>$sql) {
                try { $pdo->query($sql); $datasets[$k] = $sql; } catch (Throwable $e) {}
            }
            // Compute dynamic grades SQL
            $gAvail = getTableColumns($pdo, 'grade_details');
            $gPref = ['id','student_number','grade_number','component','raw','transmuted','created_at','updated_at'];
            $gCols = array_values(array_intersect($gPref, $gAvail));
            if (empty($gCols)) { $gCols = $gAvail; }
            if (!empty($gCols)) {
                $datasets['grades'] = 'SELECT ' . implode(',', array_map(function($c){ return '`'.$c.'`'; }, $gCols)) . ' FROM grade_details ORDER BY ' . (in_array('created_at',$gAvail)?'created_at':'id') . ' DESC';
            }
            $tmpDir = sys_get_temp_dir();
            foreach ($datasets as $name=>$sql) {
                $stmt = $pdo->query($sql);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $path = $tmpDir . DIRECTORY_SEPARATOR . $name . '_backup.csv';
                $fp = fopen($path, 'w');
                if ($rows && $fp) {
                    fputcsv($fp, array_keys($rows[0]));
                    foreach ($rows as $r) { fputcsv($fp, array_values($r)); }
                } else if ($fp) {
                    fputcsv($fp, []);
                }
                if ($fp) fclose($fp);
                $files[] = $path;
            }
            $zipName = 'mmtvtc_backup_' . date('Ymd_His') . '.zip';
            $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { http_response_code(500); echo 'Failed to create zip'; exit(); }
            foreach ($files as $f) { $zip->addFile($f, basename($f)); }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . $zipName);
            readfile($zipPath);
            // Cleanup temp files
            @unlink($zipPath);
            foreach ($files as $f) { @unlink($f); }
            exit();
            break;
        }
        default:
            http_response_code(400);
            echo 'Invalid type';
            exit();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
?>


