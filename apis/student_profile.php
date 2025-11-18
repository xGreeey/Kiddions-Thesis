<?php
require_once '../security/session_config.php';
require_once '../security/db_connect.php';
require_once __DIR__ . '/../security/app_logger.php';
require_once __DIR__ . '/../security/csrf.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (!isset($pdo)) {
    appLogError('DB_UNAVAILABLE', ['where' => 'apis/student_profile.php']);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit();
}

if (!isset($_SESSION['id'])) {
    appLogError('UNAUTHORIZED_STUDENT_PROFILE_API', [
        'reason' => 'missing_session_id',
    ]);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($method === 'POST' && $action === 'update_password') {
        csrfRequireValid();
        $userId = (int)$_SESSION['id'];
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if ($current === '' || $new === '') { throw new Exception('Missing fields'); }

        // Check if new password is the same as current password
        if ($current === $new) {
            throw new Exception('New password must be different from your current password');
        }

        // Load current password hash from mmtvtc_users
        $stmt = $pdo->prepare('SELECT password FROM mmtvtc_users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new Exception('User not found'); }
        $hash = $row['password'] ?? '';

        // Some deployments may store plaintext; support both safely
        $valid = false;
        if ($hash !== '' && password_get_info($hash)['algo'] !== 0) {
            $valid = password_verify($current, $hash);
        } else {
            $valid = hash('sha256', $current) === $hash || $current === $hash;
        }
        if (!$valid) { throw new Exception('Current password is incorrect'); }

        // Check if new password matches any password used in the last 24 months
        require_once __DIR__ . '/../security/security_core.php';
        $newHash = hashPassword($new);
        
        // Check against current password
        if (password_verify($new, $hash)) {
            throw new Exception('You cannot reuse your current password');
        }
        
        // Check against password history (last 24 months)
        $historyStmt = $pdo->prepare('
            SELECT password_hash FROM password_history 
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
        ');
        $historyStmt->execute([$userId]);
        $historyPasswords = $historyStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($historyPasswords as $oldHash) {
            if (password_verify($new, $oldHash)) {
                throw new Exception('You cannot reuse a password that you have used in the past 24 months');
            }
        }

        // Start transaction to ensure data consistency
        $pdo->beginTransaction();
        
        try {
            // Save current password to history before updating
            $saveHistoryStmt = $pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)');
            $saveHistoryStmt->execute([$userId, $hash]);
            
            // Update user's password
            $upd = $pdo->prepare('UPDATE mmtvtc_users SET password = ? WHERE id = ?');
            $upd->execute([$newHash, $userId]);
            
            // Clean up old password history (older than 24 months)
            $cleanupStmt = $pdo->prepare('DELETE FROM password_history WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 24 MONTH)');
            $cleanupStmt->execute([$userId]);
            
            $pdo->commit();
            appLogInfo('PASSWORD_UPDATED', ['user_id' => $userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            appLogError('PASSWORD_UPDATE_FAILED', ['user_id' => $userId], $e);
            throw $e;
        }
        exit();
    }

    if ($method === 'POST' && $action === 'upload_avatar') {
        csrfRequireValid();
        $userId = (int)$_SESSION['id'];
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded');
        }
        $file = $_FILES['avatar'];

        // Size cap (~5MB)
        $maxBytes = 5 * 1024 * 1024;
        if (!isset($file['size']) || (int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
            throw new Exception('File too large');
        }

        // MIME validation using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        if (!isset($allowedMimes[$mime])) { throw new Exception('Invalid file type'); }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'jpeg') { $ext = 'jpg'; }
        if (!in_array($ext, ['jpg','png','gif'])) { throw new Exception('Invalid file type'); }

        // Quarantine: move to temp, scan, then release
        $quarantineDir = realpath(__DIR__ . '/../uploads_quarantine');
        if ($quarantineDir === false) { $quarantineDir = __DIR__ . '/../uploads_quarantine'; }
        if (!is_dir($quarantineDir)) { @mkdir($quarantineDir, 0755, true); }
        $qName = 'q_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $qPath = rtrim($quarantineDir, '/\\') . '/' . $qName;
        if (!move_uploaded_file($file['tmp_name'], $qPath)) {
            appLogError('AVATAR_MOVE_TO_QUARANTINE_FAILED', ['dest' => $qPath, 'tmp' => $file['tmp_name']]);
            throw new Exception('Failed to save file');
        }

        // Antivirus scan: try clamdscan, then clamscan
        $scanResult = ['status' => 'unknown', 'engine' => null, 'detail' => null];
        try {
            $scanResult = (function($path){
                $engines = [
                    ['cmd' => 'clamdscan --no-summary %s', 'name' => 'clamdscan'],
                    ['cmd' => 'clamscan --no-summary %s',  'name' => 'clamscan']
                ];
                foreach ($engines as $e) {
                    $cmd = sprintf($e['cmd'], escapeshellarg($path));
                    $descriptorSpec = [1 => ['pipe','w'], 2 => ['pipe','w']];
                    $proc = @proc_open($cmd, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
                    if (!is_resource($proc)) { continue; }
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    foreach ($pipes as $p) { @fclose($p); }
                    $code = proc_close($proc);
                    if ($code === 0) { return ['status' => 'clean', 'engine' => $e['name'], 'detail' => trim($stdout) ?: 'OK']; }
                    if ($code === 1) { return ['status' => 'infected', 'engine' => $e['name'], 'detail' => trim($stdout) ?: 'FOUND']; }
                }
                return ['status' => 'unavailable', 'engine' => null, 'detail' => 'scanner not available'];
            })($qPath);
        } catch (Throwable $e) {
            $scanResult = ['status' => 'error', 'engine' => null, 'detail' => $e->getMessage()];
        }

        if (($scanResult['status'] ?? 'unknown') === 'infected') {
            @unlink($qPath);
            appLogError('AVATAR_VIRUS_DETECTED', ['user_id' => $userId, 'detail' => $scanResult]);
            throw new Exception('Upload blocked by antivirus');
        }

        // Resolve to public web root so the browser can fetch the file (Hostinger/public_html)
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot === '' || !is_dir($docRoot)) {
            $dir = realpath(__DIR__ . '/../images');
            if ($dir === false) { $dir = __DIR__ . '/../images'; }
        } else {
            $dir = $docRoot . '/images';
        }
        $avatarDir = $dir . '/avatars';
        if (!is_dir($avatarDir)) { @mkdir($avatarDir, 0755, true); }
        if (!is_dir($avatarDir) || !is_writable($avatarDir)) {
            appLogError('AVATAR_DIR_NOT_WRITABLE', ['path' => $avatarDir]);
        }
        $fname = 'stu_' . $userId . '_' . time() . '.' . $ext;
        $dest = $avatarDir . '/' . $fname;
        // Release from quarantine to public path
        if (!@rename($qPath, $dest)) {
            if (!@copy($qPath, $dest)) {
                appLogError('AVATAR_RELEASE_FAILED', ['dest' => $dest]);
                @unlink($qPath);
                throw new Exception('Failed to save file');
            }
            @unlink($qPath);
        }

        // Try to persist path in students.profile_photo if column exists
        // Use a root-relative public URL to avoid path issues on subpaths
        $publicPath = '/images/avatars/' . $fname;
        @chmod($dest, 0644);
        if (!is_file($dest)) { appLogError('AVATAR_FILE_MISSING_AFTER_SAVE', ['dest' => $dest]); }
        try {
            $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'profile_photo'");
            if ($col && $col->fetch(PDO::FETCH_ASSOC)) {
                $upd = $pdo->prepare('UPDATE students SET profile_photo = ? WHERE user_id = ?');
                $upd->execute([$publicPath, $userId]);
            }
        } catch (Throwable $e) { /* ignore */ }

        appLogInfo('AVATAR_UPLOADED', ['user_id' => $userId, 'file' => $fname, 'av' => $scanResult]);
        echo json_encode(['success' => true, 'path' => $publicPath]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} catch (Throwable $e) {
    appLogError('STUDENT_PROFILE_API_ERROR', [], $e);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


