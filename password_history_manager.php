<?php
/**
 * Password History Manager
 * Administrative utility to view and manage password history
 */

session_start();
require_once 'security/db_connect.php';
require_once 'security/session_config.php';

// Check if user is admin (adjust role check as needed)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 1) {
    die('Access denied. Admin privileges required.');
}

$action = $_GET['action'] ?? 'view';
$userId = (int)($_GET['user_id'] ?? 0);

try {
    switch ($action) {
        case 'view':
            // View password history for a specific user
            if ($userId > 0) {
                $stmt = $pdo->prepare('
                    SELECT ph.*, u.email, u.student_number 
                    FROM password_history ph 
                    JOIN mmtvtc_users u ON ph.user_id = u.id 
                    WHERE ph.user_id = ? 
                    ORDER BY ph.created_at DESC 
                    LIMIT 50
                ');
                $stmt->execute([$userId]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h2>Password History for User ID: $userId</h2>";
                if (!empty($history)) {
                    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                    echo "<tr><th>Date</th><th>User</th><th>Student Number</th><th>Hash (first 20 chars)</th></tr>";
                    foreach ($history as $record) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($record['created_at']) . "</td>";
                        echo "<td>" . htmlspecialchars($record['email']) . "</td>";
                        echo "<td>" . htmlspecialchars($record['student_number']) . "</td>";
                        echo "<td>" . htmlspecialchars(substr($record['password_hash'], 0, 20)) . "...</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>No password history found for this user.</p>";
                }
            } else {
                // List all users with recent password changes
                $stmt = $pdo->prepare('
                    SELECT u.id, u.email, u.student_number, 
                           COUNT(ph.id) as password_changes,
                           MAX(ph.created_at) as last_change
                    FROM mmtvtc_users u 
                    LEFT JOIN password_history ph ON u.id = ph.user_id 
                    GROUP BY u.id 
                    ORDER BY last_change DESC 
                    LIMIT 100
                ');
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h2>Users Password History Summary</h2>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>User ID</th><th>Email</th><th>Student Number</th><th>Password Changes</th><th>Last Change</th><th>Actions</th></tr>";
                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['student_number']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['password_changes']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['last_change'] ?? 'Never') . "</td>";
                    echo "<td><a href='?action=view&user_id=" . $user['id'] . "'>View History</a></td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            break;
            
        case 'cleanup':
            // Clean up old password history records
            $stmt = $pdo->prepare('DELETE FROM password_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 MONTH)');
            $result = $stmt->execute();
            $deleted = $stmt->rowCount();
            echo "<h2>Cleanup Complete</h2>";
            echo "<p>Deleted $deleted old password history records (older than 24 months).</p>";
            echo "<p><a href='?action=view'>Back to View</a></p>";
            break;
            
        case 'stats':
            // Show password security statistics
            $stats = [];
            
            // Total users
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM mmtvtc_users');
            $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Users with password history
            $stmt = $pdo->query('SELECT COUNT(DISTINCT user_id) as count FROM password_history');
            $stats['users_with_history'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Total password changes
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM password_history');
            $stats['total_changes'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Recent changes (last 30 days)
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM password_history WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
            $stats['recent_changes'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<h2>Password Security Statistics</h2>";
            echo "<ul>";
            echo "<li>Total Users: " . $stats['total_users'] . "</li>";
            echo "<li>Users with Password History: " . $stats['users_with_history'] . "</li>";
            echo "<li>Total Password Changes: " . $stats['total_changes'] . "</li>";
            echo "<li>Recent Changes (30 days): " . $stats['recent_changes'] . "</li>";
            echo "</ul>";
            echo "<p><a href='?action=view'>Back to View</a></p>";
            break;
            
        default:
            echo "<h2>Password History Manager</h2>";
            echo "<ul>";
            echo "<li><a href='?action=view'>View Password History</a></li>";
            echo "<li><a href='?action=stats'>View Statistics</a></li>";
            echo "<li><a href='?action=cleanup'>Cleanup Old Records</a></li>";
            echo "</ul>";
            break;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
