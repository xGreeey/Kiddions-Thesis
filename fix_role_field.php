<?php
/**
 * Role Field Migration Script
 * 
 * This script helps identify and fix files that still use 'user_role' 
 * instead of the correct 'is_role' field name.
 */

// Security check - only allow admin access
session_start();
require_once 'security/csp.php';
if (!isset($_SESSION['is_role']) || $_SESSION['is_role'] != 2) {
    die('Access denied. Admin privileges required.');
}

// Files to check for role field usage
$filesToCheck = [
    'admin_dashboard.php',
    'instructors_dashboard.php', 
    'student_dashboard.php',
    'index.php',
    'login_users_mmtvtc.php',
    'forgot_password.php',
    'reset_pass.php',
    'create_pass.php',
    'apis/session_status.php',
    'apis/student_profile.php',
    'apis/student_count.php',
    'apis/abuse_monitoring.php',
    'apis/announcement_handler.php',
    'apis/trainee_handler.php',
    'apis/message_handler.php',
    'apis/notifications_handler.php',
    'apis/course_list_with_counts.php',
    'apis/course_overview.php',
    'apis/grade_details.php',
    'apis/jobs_handler.php',
    'apis/log_client_event.php',
    'apis/csp_report.php',
    'apis/backup_export.php',
    'otpforusers/send_otp.php',
    'otpforusers/verify_email_otp.php'
];

$results = [];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $hasUserRole = strpos($content, 'user_role') !== false;
        $hasIsRole = strpos($content, 'is_role') !== false;
        
        $results[$file] = [
            'exists' => true,
            'has_user_role' => $hasUserRole,
            'has_is_role' => $hasIsRole,
            'needs_fix' => $hasUserRole && !$hasIsRole,
            'already_fixed' => $hasIsRole && !$hasUserRole,
            'mixed' => $hasUserRole && $hasIsRole
        ];
    } else {
        $results[$file] = [
            'exists' => false,
            'has_user_role' => false,
            'has_is_role' => false,
            'needs_fix' => false,
            'already_fixed' => false,
            'mixed' => false
        ];
    }
}

// Handle fix request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'fix_file' && isset($_POST['file'])) {
        $file = $_POST['file'];
        
        if (!isset($results[$file]) || !$results[$file]['exists']) {
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        
        try {
            $content = file_get_contents($file);
            
            // Replace common patterns
            $replacements = [
                '$_SESSION[\'user_role\']' => '$_SESSION[\'is_role\']',
                'user_role' => 'is_role',
                'user_role' => 'is_role'
            ];
            
            $newContent = $content;
            foreach ($replacements as $search => $replace) {
                $newContent = str_replace($search, $replace, $newContent);
            }
            
            // Write back to file
            file_put_contents($file, $newContent);
            
            echo json_encode([
                'success' => true,
                'message' => "File {$file} updated successfully"
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Field Migration Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #003366; color: white; padding: 20px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
        .file-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .file-table th, .file-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .file-table th { background: #f8f9fa; font-weight: bold; }
        .status-needs-fix { color: #dc3545; font-weight: bold; }
        .status-already-fixed { color: #28a745; font-weight: bold; }
        .status-mixed { color: #ffc107; font-weight: bold; }
        .status-not-found { color: #6c757d; }
        .btn { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn:hover { background: #0056b3; }
        .btn:disabled { background: #6c757d; cursor: not-allowed; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-warning:hover { background: #e0a800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Role Field Migration Tool</h1>
            <p>Fix files using 'user_role' instead of the correct 'is_role' field name</p>
        </div>

        <div class="stats">
            <h3>Migration Status</h3>
            <p><strong>Total Files:</strong> <?php echo count($results); ?></p>
            <p><strong>Need Fix:</strong> <?php echo count(array_filter($results, function($r) { return $r['needs_fix']; })); ?></p>
            <p><strong>Already Fixed:</strong> <?php echo count(array_filter($results, function($r) { return $r['already_fixed']; })); ?></p>
            <p><strong>Mixed Usage:</strong> <?php echo count(array_filter($results, function($r) { return $r['mixed']; })); ?></p>
        </div>

        <table class="file-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Status</th>
                    <th>Has user_role</th>
                    <th>Has is_role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $file => $status): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file); ?></td>
                        <td>
                            <?php if (!$status['exists']): ?>
                                <span class="status-not-found">Not Found</span>
                            <?php elseif ($status['needs_fix']): ?>
                                <span class="status-needs-fix">Needs Fix</span>
                            <?php elseif ($status['already_fixed']): ?>
                                <span class="status-already-fixed">Already Fixed</span>
                            <?php elseif ($status['mixed']): ?>
                                <span class="status-mixed">Mixed Usage</span>
                            <?php else: ?>
                                <span class="status-not-found">No Role Usage</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $status['has_user_role'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $status['has_is_role'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <?php if ($status['needs_fix']): ?>
                                <button class="btn btn-warning" onclick="fixFile('<?php echo $file; ?>')">
                                    Fix File
                                </button>
                            <?php elseif ($status['already_fixed']): ?>
                                <span style="color: #28a745;">✓ Fixed</span>
                            <?php elseif ($status['mixed']): ?>
                                <button class="btn btn-warning" onclick="fixFile('<?php echo $file; ?>')">
                                    Review & Fix
                                </button>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <h3>About the Migration</h3>
            <p><strong>Database Field:</strong> The correct field name in the database is <code>is_role</code>, not <code>user_role</code>.</p>
            <p><strong>Role Values:</strong> 0 = Student, 1 = Instructor, 2 = Admin</p>
            <p><strong>Backward Compatibility:</strong> The authorization system supports both field names for smooth migration.</p>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        function fixFile(file) {
            if (!confirm(`Fix role field usage in ${file}? This will replace 'user_role' with 'is_role'.`)) {
                return;
            }
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Fixing...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=fix_file&file=${encodeURIComponent(file)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File updated successfully!');
                    location.reload();
                } else {
                    alert('Update failed: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Fix File';
                }
            })
            .catch(error => {
                alert('Update failed: ' + error.message);
                btn.disabled = false;
                btn.textContent = 'Fix File';
            });
        }
    </script>
</body>
</html>
