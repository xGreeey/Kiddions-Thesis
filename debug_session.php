<?php
/**
 * Session Debug Script
 * 
 * This script shows all current session data to help debug
 * authorization issues.
 */

session_start();

// Security check - only allow admin access
$userRole = $_SESSION['is_role'] ?? $_SESSION['user_role'] ?? null;
if ($userRole != 2) {
    // Show debug info even if not admin
    echo "<h2>Session Debug Information</h2>";
    echo "<p><strong>Current Role:</strong> " . ($userRole ?? 'not set') . "</p>";
    echo "<p><strong>is_role:</strong> " . ($_SESSION['is_role'] ?? 'not set') . "</p>";
    echo "<p><strong>user_role:</strong> " . ($_SESSION['user_role'] ?? 'not set') . "</p>";
    echo "<p><strong>user_verified:</strong> " . ($_SESSION['user_verified'] ?? 'not set') . "</p>";
    echo "<p><strong>email:</strong> " . ($_SESSION['email'] ?? 'not set') . "</p>";
    echo "<p><strong>id:</strong> " . ($_SESSION['id'] ?? 'not set') . "</p>";
    echo "<p><strong>student_number:</strong> " . ($_SESSION['student_number'] ?? 'not set') . "</p>";
    echo "<hr>";
    echo "<h3>All Session Data:</h3>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    echo "<p><strong>Note:</strong> You need role 2 (Admin) to access the migration tool.</p>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Debug - Admin Access</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #28a745; color: white; padding: 20px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
        .session-data { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .success { color: #28a745; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Session Debug - Admin Access</h1>
            <p>You have admin access! Here's your current session data:</p>
        </div>

        <div class="session-data">
            <h3>Key Session Variables</h3>
            <p><strong>is_role:</strong> <span class="<?php echo $_SESSION['is_role'] == 2 ? 'success' : 'error'; ?>"><?php echo $_SESSION['is_role'] ?? 'not set'; ?></span></p>
            <p><strong>user_role:</strong> <span class="<?php echo $_SESSION['user_role'] == 2 ? 'success' : 'warning'; ?>"><?php echo $_SESSION['user_role'] ?? 'not set'; ?></span></p>
            <p><strong>user_verified:</strong> <span class="<?php echo $_SESSION['user_verified'] ? 'success' : 'error'; ?>"><?php echo $_SESSION['user_verified'] ? 'Yes' : 'No'; ?></span></p>
            <p><strong>email:</strong> <?php echo $_SESSION['email'] ?? 'not set'; ?></p>
            <p><strong>id:</strong> <?php echo $_SESSION['id'] ?? 'not set'; ?></p>
            <p><strong>student_number:</strong> <?php echo $_SESSION['student_number'] ?? 'not set'; ?></p>
        </div>

        <div class="session-data">
            <h3>Complete Session Data</h3>
            <pre><?php print_r($_SESSION); ?></pre>
        </div>

        <div class="session-data">
            <h3>Authorization Test</h3>
            <p><strong>Migration Tool Access:</strong> 
                <span class="success">✅ Allowed</span>
            </p>
            <p><strong>Role Check:</strong> 
                <?php if ($userRole == 2): ?>
                    <span class="success">✅ Admin (Role 2)</span>
                <?php else: ?>
                    <span class="error">❌ Not Admin (Role: <?php echo $userRole ?? 'not set'; ?>)</span>
                <?php endif; ?>
            </p>
        </div>

        <div style="margin-top: 20px;">
            <a href="migrate_authorization.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Go to Migration Tool
            </a>
            <a href="admin_dashboard.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-left: 10px;">
                Go to Admin Dashboard
            </a>
        </div>
    </div>
</body>
</html>
