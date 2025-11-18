<?php
require_once 'security/session_config.php';
require_once 'security/csp.php';
// Prevent caching of authenticated pages to avoid showing after logout
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once 'security/db_connect.php';

// Get validated URL parameters
$urlParams = getUrlParameters('dashboard');
$userId = $urlParams['id'] ?? $urlParams['user_id'] ?? null;

function logSecurityEvent($event, $details) {
    $logDir = 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $log = date('Y-m-d H:i:s') . " - " . $event . " - " . json_encode($details) . "\n";
    @file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);
}

function validateStudentSession() {
    if (!isset($_SESSION['user_verified']) || !$_SESSION['user_verified']) {
        return false;
    }
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 0) { // 0 for student
        return false;
    }
    if (!isset($_SESSION['email']) || !isset($_SESSION['student_number'])) {
        return false;
    }
    // Session timeout is handled by session_config.php (2 hours)
    // Removed redundant 30-minute timeout check to match admin dashboard behavior
    return true;
}

if (isset($_POST['logout'])) {
    if (function_exists('clearRememberMe')) { clearRememberMe(); }
    if (function_exists('destroySession')) { destroySession(); } else { session_unset(); session_destroy(); }
    header('Location: ' . generateObfuscatedUrl('login'));
    exit();
}

if (!validateStudentSession()) {
    logSecurityEvent('UNAUTHORIZED_STUDENT_ACCESS', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'attempted_access' => 'student_dashboard'
    ]);
    session_unset();
    session_destroy();
    header('Location: ' . generateSecureUrl('home'));
    exit();
}

logSecurityEvent('STUDENT_DASHBOARD_ACCESS', [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'email' => $_SESSION['email'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);
?>

<?php
// Fetch current student's grade summaries
$currentStudentNumber = $_SESSION['student_number'] ?? '';
$currentStudentCourse = '';
$currentEnrollmentStatus = '';
$currentStudentAvatar = '';
$currentStudentName = '';
// Resolve student's course and name from students table (fallback to add_trainees)
if ($currentStudentNumber !== '') {
    try {
        $c1 = $pdo->prepare('SELECT course, profile_photo, first_name, last_name, middle_name, enrollment_status FROM students WHERE student_number = ? ORDER BY id DESC LIMIT 1');
        $c1->execute([$currentStudentNumber]);
        if ($r = $c1->fetch(PDO::FETCH_ASSOC)) { 
            $currentStudentCourse = (string)($r['course'] ?? ''); 
            $currentStudentAvatar = (string)($r['profile_photo'] ?? ''); 
            $currentEnrollmentStatus = (string)($r['enrollment_status'] ?? '');
            // Build full name from database fields
            $firstName = trim($r['first_name'] ?? '');
            $lastName = trim($r['last_name'] ?? '');
            $middleName = trim($r['middle_name'] ?? '');
            if ($firstName || $lastName) {
                $currentStudentName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
            }
        }
        if ($currentStudentCourse === '') {
            $c2 = $pdo->prepare('SELECT course FROM add_trainees WHERE student_number = ? ORDER BY created_at DESC LIMIT 1');
            $c2->execute([$currentStudentNumber]);
            if ($r2 = $c2->fetch(PDO::FETCH_ASSOC)) { $currentStudentCourse = (string)($r2['course'] ?? ''); }
        }
        // If name is still empty, try to get it from add_trainees
        if ($currentStudentName === '') {
            $c3 = $pdo->prepare('SELECT firstname, surname, middlename FROM add_trainees WHERE student_number = ? ORDER BY created_at DESC LIMIT 1');
            $c3->execute([$currentStudentNumber]);
            if ($r3 = $c3->fetch(PDO::FETCH_ASSOC)) {
                $firstName = trim($r3['firstname'] ?? '');
                $lastName = trim($r3['surname'] ?? '');
                $middleName = trim($r3['middlename'] ?? '');
                if ($firstName || $lastName) {
                    $currentStudentName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                }
            }
        }
        // Normalize avatar path to root-relative to avoid 404 from relative resolution
        if ($currentStudentAvatar !== '') {
            $trimmed = ltrim((string)$currentStudentAvatar);
            if (stripos($trimmed, 'http://') !== 0 && stripos($trimmed, 'https://') !== 0) {
                if ($trimmed[0] !== '/') { $currentStudentAvatar = '/' . $trimmed; }
            }
            // Verify file exists under document root; if missing, fall back to default to avoid 404
            $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            if ($docRoot !== '') {
                $fsPath = $docRoot . $currentStudentAvatar;
                if (!is_file($fsPath)) {
                    error_log('Avatar file not found, falling back: ' . $fsPath);
                    $currentStudentAvatar = '';
                }
            }
        }
    } catch (Throwable $e) { error_log('Failed to resolve student course: ' . $e->getMessage()); }
}
$gradeAverages = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
$overallAverage = 0.0;
if ($currentStudentNumber !== '') {
    try {
        $stmt = $pdo->prepare('SELECT grade_number, AVG(transmuted) AS avg_transmuted FROM grade_details WHERE student_number = ? GROUP BY grade_number');
        $stmt->execute([$currentStudentNumber]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sum = 0.0; $count = 0;
        foreach ($rows as $r) {
            $gn = (int)($r['grade_number'] ?? 0);
            $avg = isset($r['avg_transmuted']) && $r['avg_transmuted'] !== null ? (float)$r['avg_transmuted'] : 0.0;
            if ($gn >= 1 && $gn <= 4) {
                $gradeAverages[$gn] = $avg;
                $sum += $avg; $count += 1;
            }
        }
        $overallAverage = $count > 0 ? ($sum / $count) : 0.0;
    } catch (Throwable $e) {
        error_log('Failed to fetch student grade summary: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MMTVTC Student Dashboard</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link rel="stylesheet" href="CSS/student.css?v=<?php echo urlencode((string)@filemtime(__DIR__."/CSS/student.css")); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Removed external Chart.js CDN for stricter CSP. Use a local bundle if needed. -->
    <style nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        :root {
            --glass-bg: rgba(255, 255, 255, 0.06);
            --glass-border: rgba(255, 255, 255, 0.18);
            --brand: #ffd633;
            --text: #e8ecf3;
            --muted: #a5afc0;
            --btn: #ffd633;
            --btn-text: #1b1f2a;
            --shadow: 0 10px 40px rgba(0,0,0,.35);
            --accent-blue: #5aa2ff;
            --success: #4ade80;
            --warning: #fbbf24;
            --danger: #f87171;
        }

        /* Dark theme background */
        body[data-theme="dark"],
        body.dark-theme {
            background:
                linear-gradient(120deg, rgba(8, 12, 22, .92), rgba(10, 15, 28, .92)),
                url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=2000&q=60') center/cover no-repeat fixed;
            color: var(--text);
        }

        /* Light theme background - professional training center theme */
        body:not([data-theme="dark"]):not(.dark-theme) {
            background:
                linear-gradient(120deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95)),
                url('https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=2000&q=60') center/cover no-repeat fixed;
            color: #1a202c;
        }

        /* Chart sizing - lightweight and responsive */
        .chart-canvas { width: 100%; height: 200px; display: block; }
        @media (max-width: 768px) {
            .chart-canvas { height: 160px; }
        }

        /* Responsive button styling for filters container */
        .filters-container {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .modules-empty{ text-align:center; opacity:.75; }
        @media (max-width: 768px) {
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }
            .filters-container .filter-select,
            .filters-container .refresh-filters-btn {
                width: 100%;
                margin-bottom: 8px;
            }
        }
        @media (max-width: 480px) {
            .filters-container {
                gap: 8px;
            }
        }

        /* NC2 Submission Modal Styling */
        .nc2-modal {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        .modal-header {
            background: transparent;
            padding: 0 24px 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
        }
        
        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            background: #f1f5f9;
            border-radius: 8px;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: #e2e8f0;
            color: #475569;
            transform: scale(1.05);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #1e293b;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-input::placeholder {
            color: #9ca3af;
        }
        
        .form-hint {
            display: block;
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 6px;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
        }
        
        .btn-secondary {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #f1f5f9;
            color: #475569;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            border: 1px solid #2563eb;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        /* Dark Theme Support for NC2 Modal */
        body.dark-theme .nc2-modal,
        body[data-theme="dark"] .nc2-modal {
            background: #1e293b;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        
        body.dark-theme .modal-header,
        body[data-theme="dark"] .modal-header {
            border-bottom-color: #334155;
        }
        
        body.dark-theme .modal-title,
        body[data-theme="dark"] .modal-title {
            color: #f1f5f9;
        }
        
        body.dark-theme .modal-close,
        body[data-theme="dark"] .modal-close {
            background: #334155;
            color: #94a3b8;
        }
        
        body.dark-theme .modal-close:hover,
        body[data-theme="dark"] .modal-close:hover {
            background: #475569;
            color: #cbd5e1;
        }
        
        body.dark-theme .form-label,
        body[data-theme="dark"] .form-label {
            color: #e2e8f0;
        }
        
        body.dark-theme .form-input,
        body[data-theme="dark"] .form-input {
            background: #334155;
            border-color: #475569;
            color: #f1f5f9;
        }
        
        body.dark-theme .form-input:focus,
        body[data-theme="dark"] .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        body.dark-theme .form-input::placeholder,
        body[data-theme="dark"] .form-input::placeholder {
            color: #64748b;
        }
        
        body.dark-theme .form-hint,
        body[data-theme="dark"] .form-hint {
            color: #94a3b8;
        }
        
        body.dark-theme .btn-secondary,
        body[data-theme="dark"] .btn-secondary {
            background: #334155;
            color: #cbd5e1;
            border-color: #475569;
        }
        
        body.dark-theme .btn-secondary:hover,
        body[data-theme="dark"] .btn-secondary:hover {
            background: #475569;
            color: #f1f5f9;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Responsive modal styling */
        @media (max-width: 768px) {
            .nc2-modal {
                margin: 16px;
                max-width: none;
                width: calc(100% - 32px);
            }
            
            .modal-header {
                padding: 16px 20px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                padding: 12px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .modal-title {
                font-size: 1.125rem;
            }
            
            .form-input {
                padding: 14px 16px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }

        /* Account Settings Modal - Redesigned UI */
        #accountSettingsModal.modal-overlay { 
            align-items: center; 
            justify-content: center; 
            padding: 16px; 
            background: rgba(0, 0, 0, 0.5);
        }
        .settings-modal {
            position: relative;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 12px;
            overflow: hidden;
            color: #111827;
        }
        .settings-modal::before {
            content: none;
        }
        .settings-header {
            position: relative;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
        .settings-title { display:flex; gap:10px; align-items:center; margin:0; font-size: 18px; color:#111827; }
        .settings-title span { color:#111827; font-weight: 600; letter-spacing: .2px; }
        .settings-close {
            width: 36px; height: 36px; display:inline-flex; align-items:center; justify-content:center;
            border-radius: 8px; border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #111827; cursor: pointer;
            transition: transform .15s ease, background .2s ease, border-color .2s ease;
        }
        .settings-close:hover { transform: scale(1.03); background: #f3f4f6; border-color: #d1d5db; }
		.settings-close i { font-size: 20px; color: currentColor; }

		/* Dark theme override for settings-close button */
		body.dark-theme .settings-close,
		body[data-theme="dark"] .settings-close {
			background: #2b303b;
			border-color: #3f465a;
			color: #cbd5e1;
		}
		body.dark-theme .settings-close:hover,
		body[data-theme="dark"] .settings-close:hover {
			background: #323846;
			border-color: #50586b;
		}

        .settings-tabs { display:flex; gap: 8px; padding: 10px 12px; border-bottom: 1px solid #e5e7eb; background:#ffffff; justify-content: center; }
        .settings-tab { 
            appearance: none; border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #111827; padding: 8px 12px; border-radius: 999px; cursor: pointer;
            font-weight: 500; letter-spacing: .2px;
            transition: background .2s ease, border-color .2s ease, transform .15s ease;
            box-shadow: none;
        }
        .settings-tab:hover { background: #f3f4f6; transform: translateY(-1px); }
        .settings-tab.active { background:#f3f4f6; border-color: #cbd5e1; box-shadow: none; }

        .settings-body { 
            display: grid; grid-template-columns: 280px 1fr; gap: 20px; 
            padding: 16px; position: relative;
        }
        @media (max-width: 820px) { .settings-body { grid-template-columns: 1fr; } }

        .settings-profile { display:flex; flex-direction:column; align-items:center; gap: 0; margin-top: 10px; }
        .settings-avatar {
            width: 160px; height: 160px; border-radius: 50%; overflow: hidden; position: relative;
            display:flex; align-items:center; justify-content:center; font-size: 56px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            box-shadow: 0 6px 20px rgba(0,0,0,0.10);
        }
        .settings-avatar::after { content: none; }
        /* Attach the Change button to the avatar as a single card */
        #uploadAvatarForm { width: 160px; }
        .settings-change-btn { 
            appearance: none; border: 1px solid #1e40af;
            background: #1d4ed8; /* blue */
            color: #ffffff; padding: 10px 16px; border-radius: 10px; cursor: pointer; font-weight: 700;
            width: 100%;
            transition: transform .12s ease, box-shadow .2s ease, filter .2s ease, background .2s ease, border-color .2s ease;
            box-shadow: 0 4px 12px rgba(29,78,216,0.25);
            margin-top: 8px;
        }
        .settings-change-btn:hover { transform: translateY(-1px); filter: brightness(1.03); background:#1e40af; border-color:#1e3a8a; box-shadow: 0 10px 24px rgba(29,78,216,0.35); }
        /* Ensure readable hover contrast in light theme */
        body:not([data-theme="dark"]):not(.dark-theme) .settings-change-btn:hover {
            background: #1e40af;
            color: #ffffff;
        }
        .settings-helper { color:#6b7280; }
        .settings-profile .settings-helper { margin-top: 8px; }

        .settings-details { display:flex; flex-direction: column; gap: 16px; }
        .settings-list { display:flex; flex-direction: column; gap: 10px; }
        .settings-row {
            display:grid; grid-template-columns: 140px 1fr 24px; gap: 10px; align-items: center;
            background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px;
        }
        /* Give subsequent rows the same solid top border to avoid cut-off look */
        .settings-details > .settings-row + .settings-row { border-top: 1px solid #e5e7eb; }
        .settings-label { opacity: 1; font-weight: 600; color: #111827; }
        .settings-value { opacity: .95; color:#111827; }
        .settings-status { text-align:right; color: var(--success); }

        .settings-section { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; }
        .settings-section-title { 
            margin: 0 0 10px; font-size: 16px; font-weight: 700; letter-spacing: .3px;
            color: var(--foreground);
        }
        .form-group { display:flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
        .form-group label { font-size: 13px; color:#374151; }
        .settings-input-wrap { position: relative; display:flex; align-items: stretch; gap: 8px; }
        .form-input { 
            flex:1; padding: 10px 12px; border-radius: 10px; border: 1px solid #e5e7eb;
            background: #ffffff; color: #111827; outline: none; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .form-input:focus { border-color: #111827; box-shadow: 0 0 0 3px rgba(17,24,39,0.06); background: #ffffff; }
        .action-btn { 
            padding: 10px 12px; border-radius: 10px; border: 1px solid #e5e7eb; background: #ffffff;
            color: #111827; cursor: pointer; transition: background .2s ease, transform .12s ease, border-color .2s ease;
        }
        .action-btn:hover { background: #f3f4f6; transform: translateY(-1px); border-color: #d1d5db; }
        .settings-actions { display:flex; gap: 10px; justify-content: flex-end; margin-top: 6px; }
        .settings-save { 
            background: linear-gradient(135deg, #22c55e, #16a34a); border-color: rgba(34,197,94,0.6); color: #fff; font-weight: 700;
        }
        .settings-save:hover { filter: brightness(1.05); box-shadow: 0 10px 20px rgba(34,197,94,0.25); }
        /* Prevent generic .action-btn:hover from overriding Save button colors */
        .action-btn.settings-save:hover {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
            border-color: rgba(34,197,94,0.6);
        }

        .settings-strength { 
            width: 100%; height: 8px; border-radius: 8px; overflow: hidden; background: #f3f4f6;
            border: 1px solid #e5e7eb; margin: 6px 0;
        }
        #passwordStrengthFill { height: 100%; width: 0%; background: #ef4444; transition: width .25s ease, background .25s ease; border-radius: 8px; }
        .settings-hint { color:#6b7280; }
        .settings-feedback { font-size: 13px; margin-top: 6px; color:#374151; }
        /* Ensure password section labels and hints adapt to theme */
        #changePasswordForm .form-group label { color: var(--foreground); }
        #changePasswordForm .settings-hint { color: var(--foreground); }
    </style>
</head>

<body>
    <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        window.currentStudentNumber = <?php echo json_encode($_SESSION['student_number'] ?? ''); ?>;
        window.currentStudentCourse = <?php echo json_encode($currentStudentCourse); ?>;
        window.currentStudentAvatar = <?php echo json_encode($currentStudentAvatar); ?>;
    </script>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <!-- Header -->
                <div class="sidebar-header">
                    <div class="sidebar-brand">
                        <div class="brand-icon">
                            <img src="images/logo.png" alt="MMTVTC Logo" class="brand-logo-img">
                        </div>
                        <h2 class="brand-text">MMTVTC</h2>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="sidebar-nav">
                    <ul class="nav-menu">
                        <li>
                            <button class="nav-item active" data-section="dashboard">
                                <i class="fas fa-home"></i>
                                <span class="nav-text">Dashboard</span>
                            </button>
                        </li>
                        <li>
                            <button class="nav-item" data-section="grades">
                                <i class="fa-solid fa-book-open"></i>
                                <span class="nav-text">Grades</span>
                            </button>
                        </li>
                        <li>
                            <button class="nav-item" data-section="quizzes_exams">
                                <i class="fas fa-clipboard-list"></i>
                                <span class="nav-text">Quiz & Exams</span>
                            </button>
                        </li>
                        <li>
                            <button class="nav-item" data-section="jobs_posting">
                                <i class="fa fa-address-card"></i>
                                <span class="nav-text">Jobs Matching</span>
                            </button>
                        </li>
                        <li>
                            <button class="nav-item" data-section="Career_Analytics">
                                <i class="fas fa-chart-bar"></i>
                                <span class="nav-text">Career Analytics</span>
                            </button>
                        </li>
                        <li>
                            <button class="nav-item" data-section="about">
                                <i class="fas fa-info-circle"></i>
                                <span class="nav-text">About Us</span>
                            </button>
                        </li>
                    </ul>
                    <!-- Centered Sidebar Toggle Button -->
                    <div class="sidebar-toggle-center">
                        <button class="sidebar-toggle footer-btn" id="sidebarToggle">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </nav>

                <!-- Footer -->
                    <div class="sidebar-footer">
                    <button class="footer-btn" id="themeToggle">
                        <i class="fas fa-moon"></i>
                        <span class="nav-text">Theme</span>
                    </button>
                    <a href="logout.php" class="footer-btn logout-btn" id="logoutBtn" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="nav-text">Logout</span>
                    </a>

                    <!-- Logout Confirmation Modal -->
                    
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Header -->
            <header class="main-header">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle menu" aria-controls="sidebar" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="main-title">MMTVTC Student Dashboard</h1>
                <div class="notification-container" style="display:flex; align-items:center; gap:12px;">
                    <button class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationBadge" style="display:none">0</span>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button class="notification-close" id="notificationClose">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <!-- Dynamically populated -->
                        </div>
                    </div>

                    <!-- Profile Dropdown -->
                    <div class="profile-menu" style="position:relative;">
                        <button id="profileMenuButton" class="notification-bell" style="border-radius:50%; width:40px; height:40px; padding:0; line-height:0; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            <?php if ($currentStudentAvatar !== ''): ?>
                                <img src="<?php echo htmlspecialchars($currentStudentAvatar); ?>" alt="Avatar" style="display:block; width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </button>
                        <div id="profileDropdown" class="notification-dropdown" style="right:0; left:auto; display:none; min-width:220px;">
                            <div class="notification-header">
                                <h3>My Profile</h3>
                            </div>
                            <div class="notification-list">
                                <a href="#" id="openAccountSettings" class="notification-item" style="text-decoration:none;">
                                    <div class="notification-icon"><i class="fas fa-cog"></i></div>
                                    <div class="notification-content">
                                        <p class="notification-title">Account Settings</p>
                                        <p class="notification-message">Change password, update photo</p>
                                    </div>
                                </a>
                                <a href="#grades" class="notification-item" style="text-decoration:none;" onclick="document.querySelector('[data-section=grades]').click();">
                                    <div class="notification-icon"><i class="fas fa-award"></i></div>
                                    <div class="notification-content">
                                        <p class="notification-title">My Grades</p>
                                        <p class="notification-message">View detailed grades</p>
                                    </div>
                                </a>
                                <a href="#jobs_posting" class="notification-item" style="text-decoration:none;" onclick="document.querySelector('[data-section=jobs_posting]').click();">
                                    <div class="notification-icon"><i class="fas fa-briefcase"></i></div>
                                    <div class="notification-content">
                                        <p class="notification-title">Job Matching/Submit NC2</p>
                                        <p class="notification-message">Based on your course</p>
                                    </div>
                                </a>
                                <a href="#" class="notification-item" style="text-decoration:none;" id="helpCenterLink">
                                    <div class="notification-icon"><i class="fas fa-question-circle"></i></div>
                                    <div class="notification-content">
                                        <p class="notification-title">Help Center</p>
                                        <p class="notification-message">FAQs and contact</p>
                                    </div>
                                </a>
                                <div style="margin:0;">
                                    <button type="button" data-logout class="notification-item" style="width:100%; text-align:left; background:none; border:none; cursor:pointer;">
                                        <div class="notification-icon"><i class="fas fa-sign-out-alt"></i></div>
                                        <div class="notification-content">
                                            <p class="notification-title">Log Out</p>
                                            <p class="notification-message">Sign out of your account</p>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            document.addEventListener('DOMContentLoaded', function(){
                var btn = document.getElementById('profileMenuButton');
                var dd = document.getElementById('profileDropdown');
                if(btn && dd){
                    btn.addEventListener('click', function(){
                        // Ensure notification dropdown is closed before opening profile dropdown
                        try {
                            if (typeof window.hideStudentNotificationDropdown === 'function') {
                                // Wait for notification to hide before toggling profile menu
                                window.hideStudentNotificationDropdown().then(function(){
                                    var isOpen = dd.classList.contains('show') || dd.style.display === 'block';
                                    if (!isOpen) {
                                        dd.classList.remove('hide');
                                        dd.classList.add('show');
                                        dd.style.display = 'block';
                                    } else {
                                        dd.classList.remove('show');
                                        dd.classList.add('hide');
                                        function onAnimEnd() {
                                            dd.classList.remove('hide');
                                            dd.style.display = 'none';
                                            dd.removeEventListener('animationend', onAnimEnd);
                                        }
                                        dd.addEventListener('animationend', onAnimEnd);
                                    }
                                });
                            } else {
                                var notif = document.getElementById('notificationDropdown');
                                if (notif) { notif.classList.remove('show'); notif.style.display = 'none'; }
                                var isOpen2 = dd.classList.contains('show') || dd.style.display === 'block';
                                if (!isOpen2) {
                                    dd.classList.remove('hide');
                                    dd.classList.add('show');
                                    dd.style.display = 'block';
                                } else {
                                    dd.classList.remove('show');
                                    dd.classList.add('hide');
                                    function onAnimEnd2() {
                                        dd.classList.remove('hide');
                                        dd.style.display = 'none';
                                        dd.removeEventListener('animationend', onAnimEnd2);
                                    }
                                    dd.addEventListener('animationend', onAnimEnd2);
                                }
                            }
                        } catch(e) {}
                    });
                    // Do not auto-close on outside clicks; keep open until another button interaction
                }
                var settings = document.getElementById('openAccountSettings');
                if(settings){ settings.addEventListener('click', function(ev){ ev.preventDefault(); var m=document.getElementById('accountSettingsModal'); if(m){ m.style.display='flex'; var mc = m.querySelector('.modal-content'); if(mc){ mc.classList.remove('popOut'); mc.style.animation='scaleIn 0.25s'; } } }); }
                var help = document.getElementById('helpCenterLink');
                if(help){ help.addEventListener('click', function(ev){ 
                    ev.preventDefault(); 
                    var aboutBtn = document.querySelector('[data-section=about]');
                    if(aboutBtn){ 
                        aboutBtn.click();
                    } else if (window.dashboardFunctions && typeof window.dashboardFunctions.showSection === 'function') {
                        window.dashboardFunctions.showSection('about');
                        if (typeof window.dashboardFunctions.updateActiveNav === 'function') {
                            window.dashboardFunctions.updateActiveNav('about');
                        }
                    }
                    try { window.location.hash = '#about'; } catch(_) {}
                }); }
            });
            </script>

            <!-- NC2 Submission Modal -->
            <div id="nc2SubmissionModal" class="modal-overlay" style="display:none;">
                <div class="modal-content nc2-modal" style="max-width:500px; width:90%;">
                    <div class="modal-header">
                        <h2 class="modal-title">
                            <i class="fas fa-file-upload" style="color: #3b82f6; margin-right: 8px;"></i>
                            <span style="color: #3b82f6;">NC 2 Submission</span>
                        </h2>
                        <button id="closeNc2Modal" class="modal-close" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="nc2StatusNote" style="display:none; margin-bottom:10px; font-size:0.9rem;"></div>
                        <form id="nc2SubmissionForm">
                            <div class="form-group">
                                <label for="nc2Link" class="form-label">NC2 Submission Link</label>
                                <input type="url" id="nc2Link" name="nc2_link" class="form-input" placeholder="Enter Link here..." required>
                                <small class="form-hint">Please enter a valid URL for your NC2 submission</small>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" id="cancelNc2Btn">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit NC2</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Account Settings Modal -->
            <div id="accountSettingsModal" class="modal-overlay" style="display:none;">
                <div class="modal-content settings-modal" style="max-width:820px; width:90%;">
                    <div class="settings-header">
                        <h2 class="settings-title"><i class="fas fa-cog" style="color: var(--blue-500);"></i><span>Account Settings</span></h2>
                        <button id="closeAccountSettings" class="settings-close" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="settings-tabs" role="tablist">
                        <span class="settings-tab" aria-current="true" style="pointer-events:none; border:none; background:transparent; box-shadow:none;">My Profile</span>
                    </div>
                    <div class="settings-body">
                        <aside class="settings-profile">
                            <div id="avatarPreview" class="settings-avatar">
                                <?php if (!empty($currentStudentAvatar)): ?>
                                    <img src="<?php echo htmlspecialchars($currentStudentAvatar); ?>" alt="Avatar" style="display:block; width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <form id="uploadAvatarForm" enctype="multipart/form-data" style="margin:0;">
                                <input type="file" name="avatar" id="avatarFileInput" accept="image/*" style="display:none;">
                                <button type="button" id="triggerAvatarSelect" class="settings-change-btn">Upload New</button>
                                <small id="avatarFilename" class="settings-helper"></small>
                            </form>
                            <div id="avatarFeedback" class="settings-feedback"></div>
                        </aside>
                        <section class="settings-details">
                                <div class="settings-row">
                                    <div class="settings-label">Name</div>
                                    <div class="settings-value"><?php echo htmlspecialchars($currentStudentName !== '' ? $currentStudentName : '—'); ?></div>
                                    <div class="settings-status"><i class="fas fa-check"></i></div>
                                </div>
                                <div class="settings-row">
                                    <div class="settings-label">Email</div>
                                    <div class="settings-value"><?php echo htmlspecialchars($_SESSION['email'] ?? '—'); ?></div>
                                    <div class="settings-status"><i class="fas fa-check"></i></div>
                                </div>
                                <div class="settings-row">
                                    <div class="settings-label">Phone</div>
                                    <div class="settings-value"><?php echo htmlspecialchars($_SESSION['phone'] ?? '—'); ?></div>
                                    <div class="settings-status"><i class="fas fa-check" style="opacity:0.4;"></i></div>
                                </div>
                                

                            <div class="settings-section">
                                <h3 class="settings-section-title">Password</h3>
                                <form id="changePasswordForm">
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <div class="settings-input-wrap">
                                            <input type="password" name="current_password" class="form-input" required>
                                            <button type="button" class="action-btn" data-toggle-password>Show</button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <div class="settings-input-wrap">
                                            <input type="password" name="new_password" id="newPassword" class="form-input" minlength="8" required>
                                            <button type="button" class="action-btn" data-toggle-password>Show</button>
                                        </div>
                                        <small class="settings-hint" style="display:block; margin-top:6px;">Password Strength</small>
                                        <div id="passwordStrength" class="settings-strength">
                                            <div id="passwordStrengthFill"></div>
                                        </div>
                                        <small id="passwordHint" class="settings-hint">Use at least 8 characters, with letters and numbers</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <div class="settings-input-wrap">
                                            <input type="password" name="confirm_password" class="form-input" minlength="8" required>
                                            <button type="button" class="action-btn" data-toggle-password>Show</button>
                                        </div>
                                    </div>
                                    <div id="passwordFeedback" class="settings-feedback"></div>
                                    <div class="settings-actions">
                                        <button type="button" class="action-btn" id="cancelPasswordBtn">Clear Entries</button>
                                        <button type="submit" class="action-btn settings-save">Save</button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            document.addEventListener('DOMContentLoaded', function(){
                var modal = document.getElementById('accountSettingsModal');
                var closeBtn = document.getElementById('closeAccountSettings');
                
                // Function to update password strength indicator
                function updateStrength(val){
                    var score = 0; if(val.length>=8) score++; if(/[A-Z]/.test(val)) score++; if(/[0-9]/.test(val)) score++; if(/[^A-Za-z0-9]/.test(val)) score++;
                    var pct = (score/4)*100; 
                    var strengthFill = document.getElementById('passwordStrengthFill');
                    if(strengthFill){ 
                        strengthFill.style.width=pct+'%'; 
                        strengthFill.style.background = pct<50? '#ef4444' : (pct<75? '#f59e0b' : '#22c55e'); 
                    }
                }
                
                // Function to clear password form and feedback
                function clearPasswordForm() {
                    var pwdForm = document.getElementById('changePasswordForm');
                    var passwordFeedback = document.getElementById('passwordFeedback');
                    var avatarFeedback = document.getElementById('avatarFeedback');
                    if(pwdForm){ 
                        pwdForm.reset(); 
                        updateStrength(''); 
                    }
                    if(passwordFeedback){ passwordFeedback.textContent = ''; }
                    if(avatarFeedback){ avatarFeedback.textContent = ''; }
                }
                
                if(closeBtn){ 
                    closeBtn.addEventListener('click', function(){ 
                        clearPasswordForm();
                        var modalContent = modal ? modal.querySelector('.modal-content') : null;
                        if(modalContent){
                            modalContent.style.animation = 'popOut 0.25s';
                            modalContent.classList.add('popOut');
                            modalContent.addEventListener('animationend', function handler(){
                                modal.style.display='none';
                                modalContent.classList.remove('popOut');
                                modalContent.removeEventListener('animationend', handler);
                            });
                        } else if(modal){
                            modal.style.display='none';
                        }
                    }); 
                }
                if(modal){ 
                    modal.addEventListener('click', function(e){ 
                        if(e.target===modal){ 
                            clearPasswordForm();
                            var modalContent = modal.querySelector('.modal-content');
                            if(modalContent){
                                modalContent.style.animation = 'popOut 0.25s';
                                modalContent.classList.add('popOut');
                                modalContent.addEventListener('animationend', function handler(){
                                    modal.style.display='none';
                                    modalContent.classList.remove('popOut');
                                    modalContent.removeEventListener('animationend', handler);
                                });
                            } else {
                                modal.style.display='none';
                            }
                        } 
                    }); 
                }

                var pwdForm = document.getElementById('changePasswordForm');
                if(pwdForm){
                    pwdForm.addEventListener('submit', function(e){
                        e.preventDefault();
                        var fd = new FormData(pwdForm);
                        if(fd.get('new_password') !== fd.get('confirm_password')){ alert('New passwords do not match'); return; }
                        fd.append('action','update_password');
                        var csrfEl = document.getElementById('csrf_token');
                        var csrf = csrfEl && csrfEl.value ? String(csrfEl.value) : '';
                        if (csrf) { fd.append('csrf_token', csrf); }
                        fetch('apis/student_profile.php', { method:'POST', body: fd, credentials:'same-origin', headers: csrf? { 'X-CSRF-Token': csrf } : {} })
                            .then(function(r){
                                if (!r.ok) {
                                    return r.text().then(function(t){
                                        try { return { ok:false, body: JSON.parse(t) }; } catch(_){ return { ok:false, body: { success:false, message: t||('HTTP '+r.status) } }; }
                                    });
                                }
                                return r.json().then(function(j){ return { ok:true, body:j }; });
                            })
                            .then(function(res){
                                var j = res && res.body ? res.body : {};
                                var fb=document.getElementById('passwordFeedback');
                                if(!j || j.success!==true){ if(fb){ fb.style.color='#ef4444'; fb.textContent=(j&&j.message)||'Failed to update password'; } return; }
                                if(fb){ fb.style.color='#16a34a'; fb.textContent='Password updated successfully'; }
                                pwdForm.reset(); updateStrength('');
                            })
                            .catch(function(err){ console.error('Password change error', err); alert('Network error'); });
                    });
                    var toggles = pwdForm.querySelectorAll('[data-toggle-password]');
                    toggles.forEach(function(btn){ btn.addEventListener('click', function(){ var inp = btn.previousElementSibling; if(inp && inp.type==='password'){ inp.type='text'; btn.textContent='Hide'; } else if(inp){ inp.type='password'; btn.textContent='Show'; } }); });
                    var newPwd = document.getElementById('newPassword');
                    if(newPwd){ newPwd.addEventListener('input', function(){ updateStrength(newPwd.value); }); }
                    var cancelBtn = document.getElementById('cancelPasswordBtn'); if(cancelBtn){ cancelBtn.addEventListener('click', function(){ pwdForm.reset(); updateStrength(''); }); }
                }

                var avatarForm = document.getElementById('uploadAvatarForm');
                var avatarInput = document.getElementById('avatarFileInput');
                var triggerBtn = document.getElementById('triggerAvatarSelect');
                if(triggerBtn && avatarInput){
                    triggerBtn.addEventListener('click', function(){ avatarInput.click(); });
                    avatarInput.addEventListener('change', function(){
                        if(!avatarInput.files || !avatarInput.files[0]) return;
                        var file = avatarInput.files[0];
                        var name = file.name || '';
                        var nameEl = document.getElementById('avatarFilename'); if(nameEl){ nameEl.textContent = name; }
                        var reader = new FileReader();
                        reader.onload = function(ev){
                            var prev=document.getElementById('avatarPreview'); if(prev){ prev.innerHTML='<img src="'+ ev.target.result +'" alt="Avatar" style="display:block; width:100%; height:100%; object-fit:cover;">'; }
                        };
                        reader.readAsDataURL(file);
                        var fd = new FormData(avatarForm);
                        fd.append('action','upload_avatar');
                        var csrfEl2 = document.getElementById('csrf_token');
                        var csrf2 = csrfEl2 && csrfEl2.value ? String(csrfEl2.value) : '';
                        if (csrf2) { fd.append('csrf_token', csrf2); }
                        fetch('apis/student_profile.php', { method:'POST', body: fd, credentials:'same-origin', headers: csrf2? { 'X-CSRF-Token': csrf2 } : {} })
                            .then(function(r){ return r.json(); })
                            .then(function(j){ var fb=document.getElementById('avatarFeedback'); if(!j || !j.success){ if(fb){ fb.style.color='#ef4444'; fb.textContent=(j&&j.message)||'Failed to upload'; } return; } if(fb){ fb.style.color='#16a34a'; fb.textContent='Profile picture updated'; }
                                var btn=document.getElementById('profileMenuButton'); if(btn){ var img=btn.querySelector('img'); if(!img){ btn.innerHTML=''; img=document.createElement('img'); img.style.width='100%'; img.style.height='100%'; img.style.objectFit='cover'; btn.appendChild(img);} img.src=j.path; }
                            })
                            .catch(function(){ alert('Network error'); });
                    });
                }
            });
            </script>

            <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            // NC2 Modal functionality
            document.addEventListener('DOMContentLoaded', function(){
                var nc2Modal = document.getElementById('nc2SubmissionModal');
                var closeNc2Btn = document.getElementById('closeNc2Modal');
                var cancelNc2Btn = document.getElementById('cancelNc2Btn');
                var nc2Form = document.getElementById('nc2SubmissionForm');
                var nc2StatusNote = document.getElementById('nc2StatusNote');
                var nc2Input = document.getElementById('nc2Link');
                var nc2SubmitBtn = nc2Form ? nc2Form.querySelector('button[type="submit"]') : null;

                function setNc2ModalState(status){
                    if(!nc2StatusNote || !nc2Input || !nc2SubmitBtn) return;
                    var s = String(status||'').toLowerCase();
                    nc2StatusNote.style.display = 'block';
                    nc2StatusNote.style.padding = '8px 10px';
                    nc2StatusNote.style.borderRadius = '6px';
                    if(s === 'confirmed'){
                        nc2StatusNote.textContent = 'Already Approved';
                        nc2StatusNote.style.color = '#166534';
                        nc2StatusNote.style.background = '#dcfce7';
                        nc2Input.disabled = true;
                        nc2SubmitBtn.disabled = true;
                        nc2SubmitBtn.textContent = 'Approved';
                    } else if(s === 'rejected'){
                        nc2StatusNote.textContent = 'Rejected NC2 Link';
                        nc2StatusNote.style.color = '#991b1b';
                        nc2StatusNote.style.background = '#fee2e2';
                        nc2Input.disabled = false;
                        nc2SubmitBtn.disabled = false;
                        nc2SubmitBtn.textContent = 'Submit NC2';
                    } else if(s === 'pending'){
                        nc2StatusNote.textContent = 'Pending review.';
                        nc2StatusNote.style.color = '#1e3a8a';
                        nc2StatusNote.style.background = '#dbeafe';
                        nc2Input.disabled = false;
                        nc2SubmitBtn.disabled = false;
                        nc2SubmitBtn.textContent = 'Submit NC2';
                    } else {
                        nc2StatusNote.style.display = 'none';
                        nc2Input.disabled = false;
                        nc2SubmitBtn.disabled = false;
                        nc2SubmitBtn.textContent = 'Submit NC2';
                    }
                }
                
                // Close modal functions
                function closeNc2Modal() {
                    if(nc2Modal) {
                        var modalContent = nc2Modal.querySelector('.nc2-modal');
                        if(modalContent) {
                            // Add pop-out animation class
                            modalContent.classList.add('pop-out');
                            
                            // Wait for animation to complete before hiding
                            setTimeout(function() {
                                nc2Modal.style.display = 'none';
                                modalContent.classList.remove('pop-out');
                                // Reset form
                                if(nc2Form) nc2Form.reset();
                            }, 250); // Match animation duration
                        } else {
                            // Fallback if modal content not found
                            nc2Modal.style.display = 'none';
                            if(nc2Form) nc2Form.reset();
                        }
                    }
                }
                
                // Close button
                if(closeNc2Btn) {
                    closeNc2Btn.addEventListener('click', closeNc2Modal);
                }
                
                // Cancel button
                if(cancelNc2Btn) {
                    cancelNc2Btn.addEventListener('click', closeNc2Modal);
                }
                
                // Close on backdrop click
                if(nc2Modal) {
                    nc2Modal.addEventListener('click', function(e) {
                        if(e.target === nc2Modal) {
                            closeNc2Modal();
                        }
                    });
                }
                
                // Form submission
                if(nc2Form) {
                    nc2Form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var linkInput = document.getElementById('nc2Link');
                        var link = linkInput ? linkInput.value.trim() : '';
                        if(!link){ alert('Please enter a valid NC2 submission link.'); return; }

                        // CSRF token helper if present on page
                        var csrfToken = (typeof getCSRFToken === 'function') ? getCSRFToken() : (window.CSRF_TOKEN || '');
                        var params = new URLSearchParams();
                        params.set('action','submit');
                        params.set('nc2_link', link);
                        // Provide course if the page exposes it
                        var course = (window.currentStudentCourse || '').toString();
                        if(course) params.set('course', course);
                        if(csrfToken) params.set('csrf_token', csrfToken);

                        fetch('apis/nc2_validation.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            credentials: 'same-origin',
                            body: params.toString()
                        })
                        .then(function(r){ return r.json(); })
                        .then(function(j){
                            if(j && j.success){
                                alert('NC2 link submitted successfully. Awaiting validation by admin.');
                                closeNc2Modal();
                            } else {
                                alert('Failed to submit NC2 link' + (j && j.message ? ': '+ j.message : '.'));
                            }
                        })
                        .catch(function(){
                            alert('Network error. Please try again.');
                        });
                    });
                }

                // When opening the modal, check current status and adjust UI
                document.addEventListener('click', function(e){
                    var t = e.target;
                    if(t && (t.id === 'nc2SubmissionBtn' || (t.closest && t.closest('#nc2SubmissionBtn')))){
                        // Give modal a moment to render then fetch status
                        setTimeout(function(){
                            fetch('apis/nc2_validation.php?action=status', { credentials: 'same-origin' })
                                .then(function(r){ return r.json(); })
                                .then(function(s){ setNc2ModalState(s && s.status ? s.status : ''); })
                                .catch(function(){ setNc2ModalState(''); });
                        }, 50);
                    }
                });
                
                // Close modal with Escape key
                document.addEventListener('keydown', function(e) {
                    if(e.key === 'Escape' && nc2Modal && nc2Modal.style.display === 'flex') {
                        closeNc2Modal();
                    }
                });
            });
            </script>

            <!-- Dashboard Section -->
            <section id="dashboard" class="page-section active">
                <div class="content-area">
                    <!-- Welcome Message -->
                    <div class="welcome-message" style="padding: 10px 20px 20px 20px; margin-bottom: 24px;">
                        <h1 style="margin: 0; font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-hand-wave" style="font-size: 1.5rem;"></i>
                            Welcome, <?php echo htmlspecialchars($currentStudentName !== '' ? $currentStudentName : 'Student'); ?>!
                        </h1>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="analytics-card">
                            <div class="analytics-icon blue">
                                <i class="fas fa-book"></i>
                            </div>
                            <h3 class="analytics-label">My Course</h3>
                            <p class="analytics-value"><?php 
                                $courseText = ($currentStudentCourse !== '' ? $currentStudentCourse : '—');
                                $done = (strtolower((string)$currentEnrollmentStatus) === 'completed');
                                echo htmlspecialchars($courseText);
                                if ($done) { echo ' \u2713'; }
                            ?></p>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-icon green">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="analytics-label">Completed Courses</h3>
                            <p class="analytics-value" id="completedCoursesCount">0</p>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-icon green">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="analytics-label">Overall Average</h3>
                            <p class="analytics-value"><?php echo number_format((float)$overallAverage, 1); ?>%</p>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-icon purple">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="analytics-label">Completed Levels</h3>
                            <p class="analytics-value"><?php $completed=0; foreach($gradeAverages as $g=>$v){ if($v>0){$completed++;} } echo (int)$completed; ?>/<?php echo count($gradeAverages); ?></p>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-icon orange">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <h3 class="analytics-label">Matching Jobs</h3>
                            <p class="analytics-value" id="studentJobsCount">—</p>
                        </div>
                    </div>

                    <!-- Quick Grade Overview -->
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">My Grade Averages</h3>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items:start;">
                                <table class="data-table" style="margin:0;">
                                    <thead>
                                        <tr>
                                            <th>Level</th>
                                            <th style="width:160px; text-align:right;">Average</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gradeAverages as $level => $avg): ?>
                                        <tr>
                                            <td>Grade <?php echo (int)$level; ?></td>
                                            <td style="text-align:right;"><span class="grade-badge" style="display:inline-block; min-width:72px; text-align:center;">
                                                <?php echo number_format((float)$avg, 1); ?>%
                                            </span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div>
                                    <canvas id="studentAvgSparkline" height="100"></canvas>
                                    <div style="margin-top:8px; opacity:0.8; font-size:0.9rem;">Progress over levels</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    document.addEventListener('DOMContentLoaded', function(){
                        try{
                            if(window.Chart && document.getElementById('studentAvgSparkline')){
                                var ctx = document.getElementById('studentAvgSparkline').getContext('2d');
                                var labels = [<?php foreach($gradeAverages as $level=>$avg){ echo json_encode('Grade '.$level).','; } ?>];
                                var data = [<?php foreach($gradeAverages as $level=>$avg){ echo json_encode((float)$avg).','; } ?>];
                                
                                // Color array for each grade level
                                var colors = [
                                    '#3b82f6', // Blue for Grade 1
                                    '#10b981', // Green for Grade 2  
                                    '#f59e0b', // Orange for Grade 3
                                    '#ef4444'  // Red for Grade 4
                                ];
                                
                                // Create individual datasets for each grade with different colors
                                var datasets = data.map(function(value, index) {
                                    return {
                                        label: labels[index],
                                        data: [value],
                                        backgroundColor: colors[index] || '#6b7280',
                                        borderColor: colors[index] || '#6b7280',
                                        borderWidth: 2,
                                        borderRadius: 6,
                                        borderSkipped: false
                                    };
                                });
                                
                                new Chart(ctx, {
                                    type: 'bar',
                                    data: { 
                                        labels: ['Grade Averages'],
                                        datasets: datasets
                                    },
                                    options: { 
                                        plugins:{ 
                                            legend:{ 
                                                display: true,
                                                position: 'top',
                                                labels: {
                                                    usePointStyle: true,
                                                    padding: 15,
                                                    font: {
                                                        size: 12,
                                                        weight: '500'
                                                    }
                                                }
                                            } 
                                        }, 
                                        scales:{ 
                                            x:{ 
                                                display: true,
                                                grid: {
                                                    display: false
                                                },
                                                ticks: {
                                                    font: {
                                                        size: 12,
                                                        weight: '500'
                                                    }
                                                }
                                            }, 
                                            y:{ 
                                                display: true,
                                                suggestedMin: 0, 
                                                suggestedMax: 100,
                                                grid: {
                                                    color: 'rgba(0,0,0,0.1)',
                                                    drawBorder: false
                                                },
                                                ticks: {
                                                    font: {
                                                        size: 11
                                                    },
                                                    callback: function(value) {
                                                        return value + '%';
                                                    }
                                                }
                                            } 
                                        }, 
                                        responsive: true, 
                                        maintainAspectRatio: false,
                                        animation: {
                                            duration: 1000,
                                            easing: 'easeInOutQuart'
                                        }
                                    }
                                });
                            }
                        }catch(e){}
                    });
                    </script>

                    <!-- Recommended Jobs Preview -->
                    <div class="recent-activity" style="margin-top:16px;">
                        <div class="jobs-section-header">
                            <h3 class="section-subtitle">Recommended Jobs (Top 3)</h3>
                            <a href="#jobs_posting" class="action-btn jobs-view-all-btn" onclick="document.querySelector('[data-section=jobs_posting]').click();">View All</a>
                        </div>
                        <div class="job-cards-grid" id="studentDashboardJobPreview"></div>
                    </div>
                    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    document.addEventListener('DOMContentLoaded', function(){
                        var course = (window.currentStudentCourse || '').toString().trim().toLowerCase();
                        // Require NC2 confirmed before showing preview
                        fetch('apis/nc2_validation.php?action=status', {credentials:'same-origin'})
                            .then(function(r){ return r.json(); })
                            .then(function(s){
                                if(!s || s.status !== 'confirmed'){
                                    var grid = document.getElementById('studentDashboardJobPreview');
                                    var countEl = document.getElementById('studentJobsCount');
                                    if(countEl){ countEl.textContent = '0'; }
                                    if(grid){ grid.innerHTML = '<div style="text-align:center; padding:1rem; color:#666;">Awaiting NC2 confirmation.</div>'; }
                                    return Promise.reject('nc2_not_confirmed');
                                }
                                return fetch('apis/jobs_handler.php', {credentials:'same-origin'});
                            })
                            .then(function(r){ return r.json(); })
                            .then(function(j){
                                if(!j || !j.success) return;
                                var jobs = j.data || [];
                                if(course){
                                    jobs = jobs.filter(function(job){
                                        var jc = (job.course || job.title || '').toString().toLowerCase();
                                        return jc.indexOf(course) !== -1 || course.indexOf(jc) !== -1;
                                    });
                                }
                                var grid = document.getElementById('studentDashboardJobPreview');
                                var countEl = document.getElementById('studentJobsCount');
                                if(countEl){ countEl.textContent = String(jobs.length); }
                                if(!grid) return;
                                var top = jobs.slice(0,3);
                                grid.innerHTML = top.map(function(job){
                                    return (
                                        '<div class="job-card">'
                                      + '  <div class="job-header">'
                                      + '    <h3 class="job-title">' + escapeHtml(job.title) + '</h3>'
                                      + '  </div>'
                                      + '  <div class="job-details">'
                                      + (job.course ? ('    <p><strong>Course:</strong> ' + escapeHtml(job.course) + '</p>') : '')
                                      + '    <p><strong>Company:</strong> ' + escapeHtml(job.company) + '</p>'
                                      + '    <div class="job-info">'
                                      + '      <div class="job-info-item"><i class="fas fa-map-marker-alt"></i><span>' + escapeHtml(job.location) + '</span></div>'
                                      + '      <div class="job-info-item"><i class="fas fa-dollar-sign"></i><span>' + escapeHtml(job.salary || '—') + '</span></div>'
                                      + '      <div class="job-info-item"><i class="fas fa-clock"></i><span>' + escapeHtml(job.experience || '—') + '</span></div>'
                                      + '    </div>'
                                      + '  </div>'
                                      + '</div>'
                                    );
                                }).join('');

                                function escapeHtml(s){
                                    s = String(s==null?'':s);
                                    return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); });
                                }
                            })
                            .catch(function(){});
                    });
                    </script>

                    
            </section>
            <!-- Grades Section (current student only) -->
            <section id="grades" class="page-section">
                <div class="content-area">
                    <div class="section-header">
                        <h2 class="section-title">Career Tracking Records</h2>
                        <p class="section-description">Track your performance and progress</p>
                    </div>
                    <?php
                    // Map of modules per course (same mapping used on instructor dashboard)
                    $courseModules = [
                        'Automotive Servicing' => [
                            'Introduction to Automotive Servicing',
                            'Performing Periodic Maintenance of the Automotive Engine',
                            'Diagnosing and Repairing Engine Cooling and Lubricating System',
                            'Diagnosing and Repairing Intake and Exhaust Systems',
                            'Diagnosing and Overhauling Engine Mechanical Systems',
                        ],
                        'Basic Computer Literacy' => [ 'Module 1', 'Module 2', 'Module 3' ],
                        'Beauty Care (Nail Care)' => [ 'Beauty Care Services (Nail Care) NCII' ],
                        'Bread and Pastry Production' => [ 'Preparing Cakes' ],
                        'Computer Systems Servicing' => [
                            'Introduction to CSS',
                            'Installing and Configuring Computer Systems',
                            'Setting Up Computer Networks',
                            'Setting Up Computer Servers',
                            'Maintaining Computer Systems and Networks',
                        ],
                        'Dressmaking' => [ 'Module 1', 'Module 2', 'Module 3' ],
                        'Electrical Installation and Maintenance' => [
                            'Introduction to Electrical Installation and Maintenance',
                            'Performing Roughing-In Activities, Wiring and Cabling Works for Single-Phase Distribution, Power, Lighting and Auxiliary Systems',
                            'Installing Electrical Protective Devices for Distribution, Power, Lightning Protection and Grounding Systems',
                            'Installing Wiring Devices for Floor and Wall Mounted Outlets, Lighting Fixtures, Switches and Auxiliary Outlets',
                        ],
                        'Electronic Products and Assembly Servicing' => [ /* none provided */ ],
                        'Events Management Services' => [ 'Module 1', 'Module 2', 'Module 3' ],
                        'Food and Beverage Services' => [
                            'Introduction to Food and Beverage Services',
                            'Providing Room Service',
                            'Providing Table Service',
                        ],
                        'Food Processing' => [
                            'Introduction to Food Processing',
                            'Processing Food by Drying and Dehydration',
                            'Processing Food by Fermentation and Pickling',
                            'Processing Food by Salting, Curing, and Smoking',
                            'Processing Food by Sugar Concentration',
                            'Processing Food by Thermal Application',
                        ],
                        'Hairdressing' => [ 'Module 1', 'Module 2', 'Module 3' ],
                        'Housekeeping' => [
                            'Providing Housekeeping Services',
                            'Deal with Intoxicated Guests',
                            'Providing Guest Room Services',
                            'Providing Laundry Services to Guests',
                            'Providing Public Area Services',
                            'Providing Valet Services',
                        ],
                        'Massage Therapy' => [
                            'Foundations of Massage Practice',
                            'Fundamentals of Massage Therapy',
                            'Performing Shiatsu',
                            'Performing Swedish Massage',
                        ],
                        'RAC Servicing' => [ 'Packaged Air Conditioner Unit Servicing' ],
                        'Shielded Metal Arc Welding' => [ 'Module 1', 'Module 2', 'Module 3' ],
                    ];
                    $studentModules = [];
                    if ($currentStudentCourse !== '') { $studentModules = $courseModules[$currentStudentCourse] ?? []; }
                    ?>

                    <!-- Course Stack -->
                    <div id="courseStack" class="modules-card" style="margin-bottom:12px;">
                        <div class="modules-card-header">
                            <h3 class="modules-title">My Courses</h3>
                        </div>
                        <div class="modules-table-container">
                            <table class="modules-table" role="table" aria-label="Student courses">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">Status</th>
                                        <th>Course</th>
                                        <th style="width:140px;">Start Date</th>
                                        <th style="width:140px;">End Date</th>
                                        <th style="width:120px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="coursesTableBody">
                                    <tr><td colspan="5" class="modules-empty">Loading courses...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="courseContent" style="display:none;">
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="modules-card">
                            <div class="modules-card-header">
                                <h3 id="modulesTitle" class="modules-title">Current Modules for <?php echo htmlspecialchars($currentStudentCourse !== '' ? $currentStudentCourse : '—'); ?></h3>
                            </div>
                            <div class="modules-table-container">
                                <table class="modules-table" role="table" aria-label="Modules for my course">
                                    <thead>
                                        <tr>
                                            <th style="width:120px;">Module #</th>
                                            <th>Title</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modulesTableBody">
                                        <?php if (!empty($studentModules)) { $i=1; foreach ($studentModules as $m) { ?>
                                            <tr>
                                                <td class="text-center" data-label="Module #"><span class="modules-chip"><?php echo $i++; ?></span></td>
                                                <td data-label="Title"><?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php } } else { ?>
                                            <tr>
                                                <td colspan="2" class="modules-empty">No modules listed yet for this course.</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="grades-container">
                        <div class="grade-details-section">
                            <h3 class="section-subtitle">Detailed Entries</h3>
                            <div id="studentGradeDetails"></div>
                        </div>
                    </div>
                    </div>
                    <script>
                    (function(){
                        // Load all courses (current + history) and render in stacked table
                        var tbody = document.getElementById('coursesTableBody');
                        var countEl = document.getElementById('completedCoursesCount');
                        var sn = <?php echo json_encode($currentStudentNumber); ?>;
                        var currentCourse = <?php echo json_encode($currentStudentCourse); ?>;
                        var currentStatus = <?php echo json_encode($currentEnrollmentStatus); ?>;
                        var currentStart = <?php echo json_encode(''); ?>;
                        var currentEnd = <?php echo json_encode(''); ?>;
                        
                        function renderCourses(){
                            if(!tbody) return;
                            tbody.innerHTML = '<tr><td colspan="5" class="modules-empty">Loading courses...</td></tr>';
                            
                            // Fetch history
                            fetch('apis/history_list.php?student_number=' + encodeURIComponent(sn), {credentials:'same-origin'})
                                .then(function(r){return r.json();})
                                .then(function(j){
                                    if(!j||!j.success){ j = {data:[]}; }
                                    var history = j.data||[];
                                    var allCourses = [];
                                    
                                    // Add current course if exists
                                    var isEnrolled = (String(currentStatus||'').toLowerCase() === 'enrolled');
                                    if(currentCourse && currentCourse !== '' && isEnrolled){
                                        allCourses.push({
                                            course: currentCourse,
                                            status: 'enrolled',
                                            start_date: currentStart,
                                            end_date: currentEnd,
                                            is_current: true
                                        });
                                    }
                                    
                                    // Add history courses
                                    history.forEach(function(h){
                                        allCourses.push({
                                            course: h.course,
                                            status: h.status,
                                            start_date: h.start_date,
                                            end_date: h.end_date,
                                            is_current: false
                                        });
                                    });
                                    
                                    // Update completed count
                                    if(countEl){
                                        countEl.textContent = allCourses.filter(function(c){return c.status==='completed';}).length;
                                    }
                                    
                                    // Render table
                                    if(allCourses.length === 0){
                                        tbody.innerHTML = '<tr><td colspan="5" class="modules-empty">No courses found</td></tr>';
                                        return;
                                    }
                                    
                                    tbody.innerHTML = allCourses.map(function(c){
                                        var statusIcon = '';
                                        var statusText = '';
                                        var actionBtn = '';
                                        
                                        if(c.status === 'completed'){
                                            statusIcon = '<i class="fas fa-check-circle" style="color:#22c55e"></i>';
                                            statusText = 'Completed';
                                        } else if(c.status === 'withdrawn'){
                                            statusIcon = '<i class="fas fa-minus-circle" style="color:#f59e0b"></i>';
                                            statusText = 'Withdrawn';
                                        } else {
                                            statusIcon = '<i class="fas fa-clock" style="color:#3b82f6"></i>';
                                            statusText = 'In Progress';
                                        }
                                        
                                        if(c.is_current){
                                            actionBtn = '<button class="view-course-btn" data-course="'+encodeURIComponent(c.course)+'">View Modules</button>';
                                        } else {
                                            actionBtn = '<span style="opacity:0.6;">Completed</span>';
                                        }
                                        
                                        return '<tr>'+
                                            '<td>'+statusIcon+'</td>'+
                                            '<td><strong>'+String(c.course||'')+'</strong></td>'+
                                            '<td>'+(c.start_date||'—')+'</td>'+
                                            '<td>'+(c.end_date||'—')+'</td>'+
                                            '<td>'+actionBtn+'</td>'+
                                        '</tr>';
                                    }).join('');
                                })
                                .catch(function(){
                                    if(tbody){ tbody.innerHTML = '<tr><td colspan="5" class="modules-empty">Failed to load courses</td></tr>'; }
                                });
                        }
                        
                        // Handle view details click
                        document.addEventListener('click', function(e){
                            var btn = e.target.closest('.view-course-btn');
                            if(!btn) return;
                            e.preventDefault();
                            var course = decodeURIComponent(btn.getAttribute('data-course')||'');
                            if(!course) return;
                            
                            // Toggle course content visibility
                            var content = document.getElementById('courseContent');
                            if(content){
                                var isHidden = (content.style.display === 'none' || content.style.display === '');
                                content.style.display = isHidden ? 'block' : 'none';
                                if(isHidden){
                                    content.scrollIntoView({behavior:'smooth'});
                                }
                            }
                            // Fetch modules for selected course
                            var modulesTitle = document.getElementById('modulesTitle');
                            if(modulesTitle){ modulesTitle.textContent = 'Current Modules for ' + course; }
                            var modulesBody = document.getElementById('modulesTableBody');
                            if(modulesBody){ modulesBody.innerHTML = '<tr><td colspan="2" class="modules-empty">Loading modules...</td></tr>'; }
                            fetch('apis/course_modules.php?course=' + encodeURIComponent(course), {credentials:'same-origin'})
                                .then(function(r){ return r.json(); })
                                .then(function(j){
                                    if(!modulesBody) return;
                                    if(!j || !j.success){ modulesBody.innerHTML = '<tr><td colspan="2" class="modules-empty">Failed to load modules</td></tr>'; return; }
                                    var mods = j.modules||[];
                                    if(mods.length===0){ modulesBody.innerHTML = '<tr><td colspan="2" class="modules-empty">No modules listed yet for this course.</td></tr>'; return; }
                                    modulesBody.innerHTML = mods.map(function(m){
                                        return '<tr>'+
                                            '<td class="text-center" data-label="Module #"><span class="modules-chip">'+ (m.id||'') +'</span></td>'+
                                            '<td data-label="Title">'+ String(m.name||'') +'</td>'+
                                        '</tr>';
                                    }).join('');
                                })
                                .catch(function(){ if(modulesBody){ modulesBody.innerHTML = '<tr><td colspan="2" class="modules-empty">Failed to load modules</td></tr>'; }});
                        });
                        
                        // Initial load
                        renderCourses();
                    })();
                    </script>
                </div>
            </section>
            <script>
            (function(){
                function showAssessmentDetail(data){
                    var panel = document.getElementById('assessmentDetails');
                    if(!panel){ return; }
                    var a = data.assessment || {};
                    var qs = data.questions || [];
                    var type = (data.type === 'exam') ? 'Exam' : 'Quiz';
                    var assessmentId = a.id;
                    var assessmentType = data.type;
                    
                    var html = '';
                    html += '<div class="modules-card" style="margin-top:12px;">';
                    html += '<div class="modules-card-header">';
                    html += '<h3 class="modules-title">'+ type +': '+ String(a.title||'') +'</h3>';
                    html += '<div style="margin-top:8px;">';
                    html += '<button class="btn btn-primary" onclick="startAssessment('+assessmentId+', \''+assessmentType+'\')" style="margin-right:8px;">';
                    html += '<i class="fas fa-play"></i> Start '+type;
                    html += '</button>';
                    html += '<button class="btn btn-secondary" onclick="closeAssessmentDetail()">';
                    html += '<i class="fas fa-times"></i> Close';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="modules-table-container">';
                    if(qs.length === 0){
                        html += '<div class="modules-empty" style="padding:12px;">No questions found.</div>';
                    } else {
                        html += '<div style="padding:12px; background:#f8f9fa; border-radius:4px; margin-bottom:12px;">';
                        html += '<strong>Preview:</strong> This '+type.toLowerCase()+' has '+qs.length+' question(s). Click "Start '+type+'" to begin.';
                        html += '</div>';
                        html += '<table class="modules-table" role="table" aria-label="Assessment details"><thead><tr><th style="width:80px;">#</th><th>Question</th><th style="width:160px;">Type</th></tr></thead><tbody>';
                        html += qs.map(function(q, idx){
                            var t;
                            switch(q.question_type){
                                case 'multiple_choice': t = 'Multiple Choice'; break;
                                case 'checkbox': t = 'Checkboxes'; break;
                                case 'paragraph': t = 'Paragraph'; break;
                                case 'short_answer': t = 'Short Answer'; break;
                                default: t = 'Unknown';
                            }
                            var preview = '';
                            if(q.question_type === 'multiple_choice'){
                                preview = (q.options && q.options.length)
                                    ? '<div style="opacity:.8;margin-top:6px;">'+ q.options.map(function(o,i){ return '<div>('+(i+1)+') '+ String(o.option_text||'') +'</div>'; }).join('') +'</div>'
                                    : '';
                            } else if(q.question_type === 'checkbox'){
                                preview = (q.options && q.options.length)
                                    ? '<div style="opacity:.8;margin-top:6px;">'+ q.options.map(function(o,i){ return '<div>[ ] '+ String(o.option_text||'') +'</div>'; }).join('') +'</div>'
                                    : '';
                            } else if(q.question_type === 'short_answer'){
                                preview = '<div style="opacity:.8;margin-top:6px;"><input type="text" disabled placeholder="Short answer..." style="width:100%;max-width:360px;" /></div>';
                            } else if(q.question_type === 'paragraph'){
                                preview = '<div style="opacity:.8;margin-top:6px;"><textarea disabled placeholder="Paragraph answer..." rows="3" style="width:100%;max-width:480px;"></textarea></div>';
                            }
                            return '<tr>'+
                                   '<td>'+(idx+1)+'</td>'+
                                   '<td>'+ String(q.question_text||'') + preview +'</td>'+
                                   '<td>'+ t +'</td>'+
                                   '</tr>';
                        }).join('');
                        html += '</tbody></table>';
                    }
                    html += '</div></div>';
                    panel.innerHTML = html;
                    panel.style.display = '';
                    panel.scrollIntoView({behavior:'smooth'});
                }
                
                function closeAssessmentDetail(){
                    var panel = document.getElementById('assessmentDetails');
                    if(panel){ panel.style.display = 'none'; }
                }
                
                // Make closeAssessmentDetail globally accessible
                window.closeAssessmentDetail = closeAssessmentDetail;
                
                function startAssessment(assessmentId, assessmentType){
                    // Hide the detail panel
                    closeAssessmentDetail();
                    
                    // Show the taking interface
                    showAssessmentTakingInterface(assessmentId, assessmentType);
                }
                
                // Make startAssessment globally accessible
                window.startAssessment = startAssessment;
                
                function showAssessmentTakingInterface(assessmentId, assessmentType){
                    var panel = document.getElementById('assessmentDetails');
                    if(!panel){ return; }
                    
                    // Fetch the full assessment data
                    var url = 'apis/published_assessments.php?action=detail&type=' + encodeURIComponent(assessmentType) + '&id=' + encodeURIComponent(assessmentId) + '&_t=' + Date.now();
                    fetch(url, {credentials:'same-origin'})
                        .then(function(r){ return r.json(); })
                        .then(function(j){ 
                            if(j && j.success){ 
                                renderAssessmentTakingInterface(j); 
                            } else {
                                alert('Failed to load assessment details');
                            }
                        })
                        .catch(function(){
                            alert('Failed to load assessment details');
                        });
                }
                
                function renderAssessmentTakingInterface(data){
                    var panel = document.getElementById('assessmentDetails');
                    if(!panel){ return; }
                    
                    var a = data.assessment || {};
                    var qs = data.questions || [];
                    var type = (data.type === 'exam') ? 'Exam' : 'Quiz';
                    var assessmentId = a.id;
                    var assessmentType = data.type;
                    
                    var html = '';
                    html += '<div class="modules-card" style="margin-top:12px;">';
                    html += '<div class="modules-card-header">';
                    html += '<h3 class="modules-title">Taking '+type+': '+ String(a.title||'') +'</h3>';
                    html += '<div style="margin-top:8px;">';
                    html += '<button class="btn btn-success" onclick="submitAssessment('+assessmentId+', \''+assessmentType+'\')" style="margin-right:8px;">';
                    html += '<i class="fas fa-check"></i> Submit '+type;
                    html += '</button>';
                    html += '<button class="btn btn-secondary" onclick="closeAssessmentDetail()">';
                    html += '<i class="fas fa-times"></i> Cancel';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="modules-table-container">';
                    html += '<form id="assessmentForm">';
                    
                    if(qs.length === 0){
                        html += '<div class="modules-empty" style="padding:12px;">No questions found.</div>';
                    } else {
                        qs.forEach(function(q, idx){
                            html += '<div class="question-card" style="border:1px solid #ddd; border-radius:8px; padding:16px; margin-bottom:16px; background:#fff;">';
                            html += '<div class="question-header" style="margin-bottom:12px;">';
                            html += '<h4 style="margin:0; color:#333;">Question '+(idx+1)+'</h4>';
                            html += '<span class="question-type-badge" style="background:#007bff; color:white; padding:2px 8px; border-radius:12px; font-size:12px; margin-left:8px;">';
                            switch(q.question_type){
                                case 'multiple_choice': html += 'Multiple Choice'; break;
                                case 'checkbox': html += 'Checkboxes'; break;
                                case 'paragraph': html += 'Paragraph'; break;
                                case 'short_answer': html += 'Short Answer'; break;
                                default: html += 'Unknown';
                            }
                            html += '</span>';
                            html += '</div>';
                            html += '<div class="question-text" style="margin-bottom:12px; font-size:16px;">';
                            html += String(q.question_text||'');
                            html += '</div>';
                            html += '<div class="question-answer">';
                            
                            if(q.question_type === 'multiple_choice'){
                                if(q.options && q.options.length){
                                    q.options.forEach(function(option, optIdx){
                                        html += '<div style="margin-bottom:8px;">';
                                        html += '<label style="display:flex; align-items:center; cursor:pointer;">';
                                        html += '<input type="radio" name="question_'+q.id+'" value="'+option.id+'" style="margin-right:8px;">';
                                        html += '<span>('+(optIdx+1)+') '+String(option.option_text||'')+'</span>';
                                        html += '</label>';
                                        html += '</div>';
                                    });
                                }
                            } else if(q.question_type === 'checkbox'){
                                if(q.options && q.options.length){
                                    q.options.forEach(function(option, optIdx){
                                        html += '<div style="margin-bottom:8px;">';
                                        html += '<label style="display:flex; align-items:center; cursor:pointer;">';
                                        html += '<input type="checkbox" name="question_'+q.id+'[]" value="'+option.id+'" style="margin-right:8px;">';
                                        html += '<span>('+(optIdx+1)+') '+String(option.option_text||'')+'</span>';
                                        html += '</label>';
                                        html += '</div>';
                                    });
                                }
                            } else if(q.question_type === 'short_answer'){
                                html += '<input type="text" name="question_'+q.id+'" placeholder="Enter your answer..." style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">';
                            } else if(q.question_type === 'paragraph'){
                                html += '<textarea name="question_'+q.id+'" placeholder="Enter your answer..." rows="4" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; resize:vertical;"></textarea>';
                            }
                            
                            html += '</div>';
                            html += '</div>';
                        });
                    }
                    
                    html += '</form>';
                    html += '</div></div>';
                    panel.innerHTML = html;
                    panel.style.display = '';
                    panel.scrollIntoView({behavior:'smooth'});
                }
                
                function submitAssessment(assessmentId, assessmentType){
                    var form = document.getElementById('assessmentForm');
                    if(!form){ 
                        alert('No form found');
                        return; 
                    }
                    
                    var formData = new FormData(form);
                    var answers = {};
                    
                    // Collect all form data
                    for(var pair of formData.entries()){
                        var questionId = pair[0];
                        var answer = pair[1];
                        
                        if(!answers[questionId]){
                            answers[questionId] = [];
                        }
                        answers[questionId].push(answer);
                    }
                    
                    // Convert to proper format
                    var submissionData = {
                        assessment_id: assessmentId,
                        assessment_type: assessmentType,
                        student_number: <?php echo json_encode($currentStudentNumber); ?>,
                        answers: answers
                    };
                    
                    // Debug logging
                    console.log('Submitting assessment with data:', submissionData);
                    
                    // Submit the assessment
                    fetch('apis/assessment_submission.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(submissionData)
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(j){
                        if(j && j.success){
                            var score = j.score || 0;
                            var totalQuestions = j.total_questions || 0;
                            var correctAnswers = j.correct_answers || 0;
                            alert('Assessment submitted successfully!\n\nScore: ' + score.toFixed(1) + '%\nCorrect Answers: ' + correctAnswers + '/' + totalQuestions);
                            closeAssessmentDetail();
                            fetchAssessments(); // Refresh the list
                        } else {
                            alert('Failed to submit assessment: ' + (j.message || 'Unknown error'));
                        }
                    })
                    .catch(function(){
                        alert('Failed to submit assessment');
                    });
                }
                
                // Make submitAssessment globally accessible
                window.submitAssessment = submitAssessment;

                function renderAssessments(rows){
                    var tbody = document.getElementById('assessmentsTableBody');
                    if(!tbody){ return; }
                    if(!rows || rows.length === 0){
                        tbody.innerHTML = '<tr><td colspan="5" class="modules-empty">No quizzes or exams published yet.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = rows.map(function(r){
                        var type = r.type === 'exam' ? 'Exam' : 'Quiz';
                        var title = String(r.title||'');
                        
                        // Convert database status to user-friendly status
                        var status = 'Not started';
                        var statusClass = '';
                        if(r.submission_status){
                            switch(r.submission_status){
                                case 'submitted':
                                    status = 'Completed';
                                    if(r.score !== undefined && r.score !== null){
                                        status += ' (' + parseFloat(r.score).toFixed(1) + '%)';
                                    }
                                    statusClass = 'status-completed';
                                    break;
                                case 'graded':
                                    status = 'Graded';
                                    if(r.score !== undefined && r.score !== null){
                                        status += ' (' + parseFloat(r.score).toFixed(1) + '%)';
                                    }
                                    statusClass = 'status-graded';
                                    break;
                                case 'in_progress':
                                    status = 'In Progress';
                                    statusClass = 'status-progress';
                                    break;
                                default:
                                    status = 'Not started';
                                    statusClass = 'status-not-started';
                            }
                        } else {
                            statusClass = 'status-not-started';
                        }
                        
                        var action = '<button class="view-assessment-btn" data-type="'+(r.type||'')+'" data-id="'+(r.id||'')+'">View</button>';
                        return '<tr>'+
                            '<td>'+ type +'</td>'+
                            '<td>'+ title +'</td>'+
                            '<td>—</td>'+
                            '<td class="'+statusClass+'">'+ status +'</td>'+
                            '<td>'+ action +'</td>'+
                        '</tr>';
                    }).join('');
                }

                function fetchAssessments(){
                    var courseSel = document.getElementById('quizCourseFilter');
                    var typeSel = document.getElementById('assessmentTypeFilter');
                    var course = courseSel ? courseSel.value : '';
                    var type = typeSel ? typeSel.value : '';
                    var studentNum = <?php echo json_encode($currentStudentNumber); ?>;
                    var url = 'apis/published_assessments.php?student_number=' + encodeURIComponent(studentNum);
                    if(type){ url += '&type=' + encodeURIComponent(type); }
                    if(course){ url += '&course=' + encodeURIComponent(course); }
                    // Add cache-busting parameter to ensure fresh data
                    url += '&_t=' + Date.now();
                    var tbody = document.getElementById('assessmentsTableBody');
                    if(tbody){ tbody.innerHTML = '<tr><td colspan="5" class="modules-empty">Loading assessments...</td></tr>'; }
                    fetch(url, {credentials:'same-origin'})
                        .then(function(r){ return r.json(); })
                        .then(function(j){ if(j && j.success){ renderAssessments(j.data||[]); } else { renderAssessments([]); } })
                        .catch(function(){ renderAssessments([]); });
                }

                document.addEventListener('DOMContentLoaded', function(){
                    var refreshBtn = document.getElementById('refreshAssessmentsBtn');
                    if(refreshBtn){ 
                        refreshBtn.addEventListener('click', function(e){ 
                            e.preventDefault(); 
                            // Add loading state to refresh button
                            var icon = refreshBtn.querySelector('i');
                            if(icon) icon.style.animation = 'spin 1s linear infinite';
                            fetchAssessments().finally(function(){
                                if(icon) icon.style.animation = '';
                            });
                        }); 
                    }
                    var typeSel = document.getElementById('assessmentTypeFilter');
                    if(typeSel){ typeSel.addEventListener('change', fetchAssessments); }
                    var courseSel = document.getElementById('quizCourseFilter');
                    if(courseSel){ courseSel.addEventListener('change', fetchAssessments); }
                    
                    // Initial load
                    fetchAssessments();
                    
                    // Auto-refresh every 30 seconds to keep status updated
                    setInterval(function(){
                        fetchAssessments();
                    }, 30000);
                });

                document.addEventListener('click', function(e){
                    var btn = e.target && e.target.closest ? e.target.closest('.view-assessment-btn') : null;
                    if(!btn){ return; }
                    e.preventDefault();
                    var type = btn.getAttribute('data-type') || '';
                    var id = btn.getAttribute('data-id') || '';
                    if(!type || !id){ return; }
                    var url = 'apis/published_assessments.php?action=detail&type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id) + '&_t=' + Date.now();
                    fetch(url, {credentials:'same-origin'})
                        .then(function(r){ return r.json(); })
                        .then(function(j){ if(j && j.success){ showAssessmentDetail(j); } })
                        .catch(function(){});
                });
            })();
            </script>
            
            <!-- Quizzes & Exams Section -->
            <section id="quizzes_exams" class="page-section">
                <div class="content-area">
                    <div class="section-header">
                        <h2 class="section-title">Quiz & Exams</h2>
                        <p class="section-description">View and take quizzes and exams published by your instructors.</p>
                    </div>

                    <div class="filters-container" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <select class="filter-select" id="quizCourseFilter" name="course">
                            <option value="">All Courses</option>
                        </select>
                        <select class="filter-select" id="assessmentTypeFilter" name="type">
                            <option value="">All Types</option>
                            <option value="quiz">Quiz</option>
                            <option value="exam">Exam</option>
                        </select>
                        <button class="refresh-filters-btn" id="refreshAssessmentsBtn" title="Refresh list">
                            <i class="fas fa-rotate-right" aria-hidden="true"></i>
                            <span>Refresh</span>
                        </button>
                    </div>

                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="modules-card">
                            <div class="modules-card-header">
                                <h3 class="modules-title">Available Assessments</h3>
                            </div>
                            <div class="modules-table-container">
                                <table class="modules-table" role="table" aria-label="Available quizzes and exams">
                                    <thead>
                                        <tr>
                                            <th style="width:140px;">Type</th>
                                            <th>Title</th>
                                            <th style="width:140px;">Due Date</th>
                                            <th style="width:140px;">Status</th>
                                            <th style="width:140px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="assessmentsTableBody">
                                        <tr>
                                            <td colspan="5" class="modules-empty">Loading assessments...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="assessmentDetails" style="display:none"></div>
                </div>
            </section>

            <!-- Jobs Posting Section -->
            <section id="jobs_posting" class="page-section">
                <div class="content-area">
                    <div class="section-header">
                        <h2 class="section-title">Job Matching</h2>
                        <p class="section-description">Available job opportunities</p>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filters-container">
                        <select class="filter-select" id="studentLocationFilter" name="location">
                            <option value="">All Locations</option>
                            <!-- Options will be populated dynamically based on available jobs -->
                        </select>
                        <select class="filter-select" id="studentExperienceFilter" name="experience">
                            <option value="">All Experience Levels</option>
                            <!-- Options will be populated dynamically based on available jobs -->
                        </select>
                        <button class="refresh-filters-btn" id="studentRefreshJobsBtn" title="Refresh jobs">
                            <i class="fas fa-rotate-right" aria-hidden="true"></i>
                            <span>Refresh</span>
                        </button>
                        <button class="refresh-filters-btn" id="nc2SubmissionBtn" title="NC2 Submission;">
                            <i class="fas fa-file-upload" aria-hidden="true"></i>
                            <span>NC2 Submission</span>
                        </button>
                    </div>

                    <!-- Job Count and Cards -->
                    <div class="job-results-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div class="job-count" style="color: #666; font-size: 0.9rem;">Loading jobs...</div>
                    </div>
                    <div class="job-cards-grid" id="studentJobCardsGrid">
                        <!-- Job cards will be populated dynamically -->
                    </div>

                </div>
            </section>
            
            
            <!-- Career Analytics Section -->
            <section id="Career_Analytics" class="page-section">
                <div class="content-area">
                    <div class="section-header">
                        <h2 class="section-title">Career Analytics</h2>
                        <p class="section-description">Prediction Visualization and Results for Course and Job trends for the next 6 months</p>
                    </div>
                    <div class="filters-container" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <select class="filter-select" id="analyticsCourseSelect">
                            <option value="__ALL__">All Courses</option>
                        </select>
                        <span id="analyticsInfo" style="opacity:0.8;font-size:0.9rem"></span>
                    </div>

					<?php
					// Compute career analytics (Total Graduates and YoY trend) from CSV (mirrors instructor)
					$careerAnalytics = [
						'totalGraduates' => 0,
						'trendText' => ''
					];
					$csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'Graduates_.csv';
					if (is_readable($csvPath)) {
						$handle = fopen($csvPath, 'r');
						if ($handle !== false) {
							// Skip header
							fgetcsv($handle);
							$total = 0;
							$perYear = [];
							while (($row = fgetcsv($handle)) !== false) {
								if (count($row) < 4) {
									continue;
								}
								$year = (int)$row[2];
								$count = (int)$row[3];
								$total += $count;
								if (!isset($perYear[$year])) {
									$perYear[$year] = 0;
								}
								$perYear[$year] += $count;
							}
							fclose($handle);

							$careerAnalytics['totalGraduates'] = $total;

							if (!empty($perYear)) {
								ksort($perYear);
								$years = array_keys($perYear);
								$latestYear = end($years);
								$prevYear = prev($years);
								if ($prevYear !== false) {
									$latest = (int)$perYear[$latestYear];
									$prev = (int)$perYear[$prevYear];
									if ($prev > 0) {
										$change = (($latest - $prev) / $prev) * 100;
										$careerAnalytics['trendText'] = ($change > 0 ? '+' : '') . round($change, 1) . '% from ' . $prevYear;
									}
								}
							}
						}
					}

					// Employment Rate Prediction summary from data/mmtvtc_employment_rates.csv (mirrors instructor)
					$employmentData = null;
					$overallEmploymentRate = 89; // Default fallback
					$employmentTrend = '+5% from last month'; // Default fallback
					$employmentCsv = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mmtvtc_employment_rates.csv';
					if (is_readable($employmentCsv)) {
						$h = fopen($employmentCsv, 'r');
						if ($h !== false) {
							$header = fgetcsv($h);
							$idx = [ 'course_name' => -1, 'course_code' => -1, 'year' => -1, 'employment_rate' => -1 ];
							if (is_array($header)) {
								foreach ($header as $i => $col) {
									$c = strtolower(trim((string)$col));
									if ($c === 'course_name') { $idx['course_name'] = $i; }
									elseif ($c === 'course_code') { $idx['course_code'] = $i; }
									elseif ($c === 'year') { $idx['year'] = $i; }
									elseif ($c === 'employment_rate') { $idx['employment_rate'] = $i; }
								}
							}

							if ($idx['course_code'] >= 0 && $idx['year'] >= 0 && $idx['employment_rate'] >= 0) {
								$predictions = [];
								while (($row = fgetcsv($h)) !== false) {
									if (count($row) <= max($idx['course_code'], $idx['year'], $idx['employment_rate'])) continue;
									$code = trim((string)$row[$idx['course_code']]);
									$name = $idx['course_name'] >= 0 ? trim((string)$row[$idx['course_name']]) : $code;
									$year = (int)$row[$idx['year']];
									$rate = (float)$row[$idx['employment_rate']];
									if ($code && $year > 0 && $rate >= 0) {
										// Build simple per-course sequence for trend
										if (!isset($courseData)) { $courseData = []; }
										if (!isset($courseData[$code])) {
											$courseData[$code] = ['course_name' => $name, 'course_code' => $code, 'years' => [], 'rates' => []];
										}
										$courseData[$code]['years'][] = $year;
										$courseData[$code]['rates'][] = $rate;
									}
								}
								// Calculate predictions and pick top (simple linear trend per instructor logic)
								if (!empty($courseData)) {
									foreach ($courseData as $code => $data) {
										$years = $data['years'];
										$rates = $data['rates'];
										if (count($years) >= 2) {
											array_multisort($years, $rates);
											$n = count($years);
											$sumX = array_sum($years);
											$sumY = array_sum($rates);
											$sumXY = 0;
											$sumX2 = 0;
											for ($i = 0; $i < $n; $i++) {
												$sumXY += $years[$i] * $rates[$i];
												$sumX2 += $years[$i] * $years[$i];
											}
											$slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
											$latestRate = end($rates);
											$latestYear = end($years);
											$prediction2026 = max(0, min(100, $latestRate + $slope * (2026 - $latestYear)));
											$change = $prediction2026 - $latestRate;
											$predictions[] = [
												'course_code' => $code,
												'course_name' => $data['course_name'],
												'prediction_2026' => round($prediction2026, 1),
												'latest_rate' => round($latestRate, 1),
												'change' => round($change, 1)
											];
										}
									}
									usort($predictions, function($a, $b) { return $b['prediction_2026'] <=> $a['prediction_2026']; });
									if (!empty($predictions)) {
										$overallEmploymentRate = round($predictions[0]['prediction_2026'], 0);
										$change = $predictions[0]['change'];
										$employmentTrend = ($change > 0 ? '+' : '') . round($change, 1) . '% from 2025';
									}
								}
							}
						}
					}
					?>

					<!-- Copied Instructor KPI Content Area -->
					<div class="content-area">
						<div class="analytics-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 20px; max-width: 1080px; margin: 0 auto;">
							<div class="analytics-card">
								<div class="analytics-icon blue">
									<i class="fas fa-users"></i>
								</div>
								<h3 class="analytics-label">Total Graduates</h3>
								<p class="analytics-value"><?php echo number_format((int)$careerAnalytics['totalGraduates']); ?></p>
								<p class="analytics-trend"><?php echo htmlspecialchars($careerAnalytics['trendText'] !== '' ? $careerAnalytics['trendText'] : ''); ?></p>
							</div>
							<div class="analytics-card">
								<div class="analytics-icon green">
									<i class="fas fa-briefcase"></i>
								</div>
								<h3 class="analytics-label">Employment Rate</h3>
								<p class="analytics-value"><?php echo $overallEmploymentRate; ?>%</p>
								<p class="analytics-trend"><?php echo htmlspecialchars($employmentTrend ?? ''); ?></p>
							</div>
							<div class="analytics-card completion-card" aria-label="Graduates Completion Rate 100%">
								<div class="analytics-icon purple"> 
									<i class="fas fa-check-circle"></i>
								</div>
								<h3 class="analytics-label">Graduates Completion Rate</h3>
								<div class="completion-ring" role="img" aria-hidden="true">
									<svg viewBox="0 0 100 100" focusable="false">
										<circle class="ring-bg" cx="50" cy="50" r="40"></circle>
										<circle class="ring-progress" cx="50" cy="50" r="40"></circle>
									</svg>
									<span class="completion-percent">100%</span>
								</div>
								<p class="analytics-trend">MMTVTC GRADUATE RATES</p>
							</div>
						</div>
					</div>

					<div class="analytics-grid" style="display:grid;grid-template-columns:1fr;gap:16px;">
                    </div>

					<!-- 2025 Course Popularity (CSV-driven) -->
					<div class="analytics-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
						<div class="analytics-card" style="padding:16px;">
							<h3 class="analytics-label" style="margin-bottom:8px;">Course Popularity for Year 2025 (Enrollment)</h3>
							<p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">This visualization shows the Course Popularity for Year 2025 indicating which courses is the most popular down to the least most popular. The bar chart shows the total number of students who signed up for each course in 2025. It shows that Shielded Metal Arc Welding and Dressmaking NC II are the most popular courses.</p>
							<canvas id="coursePopularity2025Bar" class="chart-canvas"></canvas>
						</div>
						<div class="analytics-card" style="padding:16px;">
							<h3 class="analytics-label" style="margin-bottom:8px;">Top 10 Distribution (2025)</h3>
							<p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">The pie chart, on the other hand, shows how the top 10 courses are split up by percentage. This gives a quick look at what students like in different vocational fields.</p>
							<canvas id="coursePopularity2025Pie" class="chart-canvas"></canvas>
						</div>
						<div class="analytics-card" style="padding:16px;grid-column:1 / -1;">
							<h3 class="analytics-label" style="margin-bottom:8px;">Summary (2025)</h3>
							<p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">Summarizes the most popular course and also the least popular courses in detailed and with also offering a total courses offered and total students enrolled in 2025.</p>
							<div id="coursePopularity2025Summary" style="font-size:0.95rem; line-height:1.5;"></div>
						</div>
					</div>

<?php
    // Industry employment prediction (conservative, memory-friendly) from data/industry_data.csv
    // Output: window.__industryBarData { title, year, labels[], values[] } and a single chart below
    $industryBarData = null;
    $industryCsv = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'industry_data.csv';
    if (is_readable($industryCsv)) {
        $h = fopen($industryCsv, 'r');
        if ($h !== false) {
            $header = fgetcsv($h);
            $idx = [ 'industry_id' => -1, 'year' => -1, 'batch' => -1, 'student_count' => -1 ];
            if (is_array($header)) {
                foreach ($header as $i => $col) {
                    $c = strtolower(trim((string)$col));
                    if ($c === 'industry_id') { $idx['industry_id'] = $i; }
                    elseif ($c === 'year') { $idx['year'] = $i; }
                    elseif ($c === 'batch') { $idx['batch'] = $i; }
                    elseif ($c === 'student_count') { $idx['student_count'] = $i; }
                }
            }

            if ($idx['industry_id'] >= 0 && $idx['year'] >= 0 && $idx['student_count'] >= 0) {
                $perIndustryPerYear = [];
                while (($row = fgetcsv($h)) !== false) {
                    if (!is_array($row)) { continue; }
                    $industry = (string)($row[$idx['industry_id']] ?? '');
                    if ($industry === '') { continue; }
                    $year = (int)($row[$idx['year']] ?? 0);
                    if ($year <= 0) { continue; }
                    $count = (int)($row[$idx['student_count']] ?? 0);
                    if (!isset($perIndustryPerYear[$industry])) { $perIndustryPerYear[$industry] = []; }
                    if (!isset($perIndustryPerYear[$industry][$year])) { $perIndustryPerYear[$industry][$year] = 0; }
                    $perIndustryPerYear[$industry][$year] += max(0, $count);
                }

                $targetYear = 2026;
                $predictions = [];
                foreach ($perIndustryPerYear as $industry => $map) {
                    ksort($map);
                    $years = array_keys($map);
                    $values = array_values($map);
                    $n = count($years);
                    if ($n < 2) { continue; }

                    $recent = (float)$values[$n - 1];

                    // Average of recent year-over-year differences (up to last 3 deltas)
                    $diffs = [];
                    for ($i = max(0, $n - 4); $i < $n - 1; $i++) {
                        $diffs[] = (float)$values[$i + 1] - (float)$values[$i];
                    }
                    $avgDiff = count($diffs) > 0 ? (array_sum($diffs) / count($diffs)) : 0.0;

                    // Conservative prediction: recent value + average growth
                    $step = $avgDiff;
                    $maxStep = $recent * 0.15; // Cap at 15% growth
                    if ($step > $maxStep) $step = $maxStep;
                    if ($step < -$maxStep) $step = -$maxStep;

                    $predVal = max(0.0, $recent + $step);

                    $predictions[] = [
                        'industry' => $industry,
                        'prediction_2026' => (int)round($predVal),
                        '_years' => $years,
                        '_values' => $values
                    ];
                }

                usort($predictions, function($a,$b){ return $b['prediction_2026'] <=> $a['prediction_2026']; });
                $top10 = array_slice($predictions, 0, 10);
                $topItem = isset($top10[0]) ? $top10[0] : null;

                $labels = array_map(function($r){ $s = (string)$r['industry']; return mb_substr($s, 0, 24); }, $top10);
                $values = array_map(function($r){ return (int)$r['prediction_2026']; }, $top10);

                $topTrend = null;
                $topList = [];
                if ($topItem) {
                    $ty = $topItem['_years'];
                    $tv = $topItem['_values'];
                    $n  = count($ty);
                    $recent = $n ? (float)$tv[$n-1] : 0.0;
                    $pred = (int)$topItem['prediction_2026'];
                    $lower = (int)max(0, round($pred * 0.9));
                    $upper = (int)round($pred * 1.1);
                    $topTrend = [
                        'name' => (string)$topItem['industry'],
                        'years' => array_map('intval', $ty),
                        'totals' => array_map('intval', $tv),
                        'pred' => (int)$pred,
                        'lower' => (int)$lower,
                        'upper' => (int)$upper
                    ];

                    // Build dropdown list from top10
                    foreach ($top10 as $ti) {
                        $ty2 = $ti['_years'];
                        $tv2 = $ti['_values'];
                        $pred2 = (int)$ti['prediction_2026'];
                        $lower2 = (int)max(0, round($pred2 * 0.9));
                        $upper2 = (int)round($pred2 * 1.1);
                        $topList[] = [
                            'name' => (string)$ti['industry'],
                            'years' => array_map('intval', $ty2),
                            'totals' => array_map('intval', $tv2),
                            'pred' => (int)$pred2,
                            'lower' => (int)$lower2,
                            'upper' => (int)$upper2
                        ];
                    }
                }

                $industryBarData = [
                    'title' => 'Top 10 Companies absorbing MMTVTC Graduates (2025)',
                    'year' => $targetYear,
                    'labels' => $labels,
                    'values' => $values,
                    'top' => $topTrend,
                    'topList' => $topList
                ];
            }
            fclose($h);
        }
    }
?>

                    <?php if (is_array($industryBarData)) { ?>
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">Top 10 Companies absorbing MMTVTC Graduates (2025)</h3>
                            <p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">These bar charts shows how the system keeps track of and analyzes hiring trends across different agencies and industries to find out which companies always hire MMTVTC graduates.</p>
                            <canvas id="industryEmploymentChart" class="chart-canvas"></canvas>
                        </div>
                    </div>

                    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    window.__industryBarData = <?php echo json_encode($industryBarData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    </script>
                    <?php } else { ?>
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">Top 10 Companies absorbing MMTVTC Graduates (2025)</h3>
                            <div style="opacity:0.8;">Upload <code>data/industry_data.csv</code> to display this chart.</div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php
                    // Employment Rate Prediction (memory-efficient) from data/mmtvtc_employment_rates.csv
                    $employmentData = null;
                    $overallEmploymentRate = 89; // Default fallback
                    $employmentTrend = '+5% from last month'; // Default fallback
                    $employmentCsv = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mmtvtc_employment_rates.csv';
                    if (is_readable($employmentCsv)) {
                        $h = fopen($employmentCsv, 'r');
                        if ($h !== false) {
                            $header = fgetcsv($h);
                            $idx = [ 'course_name' => -1, 'course_code' => -1, 'year' => -1, 'employment_rate' => -1 ];
                            if (is_array($header)) {
                                foreach ($header as $i => $col) {
                                    $c = strtolower(trim((string)$col));
                                    if ($c === 'course_name') { $idx['course_name'] = $i; }
                                    elseif ($c === 'course_code') { $idx['course_code'] = $i; }
                                    elseif ($c === 'year') { $idx['year'] = $i; }
                                    elseif ($c === 'employment_rate') { $idx['employment_rate'] = $i; }
                                }
                            }

                            if ($idx['course_code'] >= 0 && $idx['year'] >= 0 && $idx['employment_rate'] >= 0) {
                                $courseData = [];
                                $allCoursesData = [];
                                while (($row = fgetcsv($h)) !== false) {
                                    if (count($row) <= max($idx['course_code'], $idx['year'], $idx['employment_rate'])) continue;
                                    $code = trim((string)$row[$idx['course_code']]);
                                    $name = $idx['course_name'] >= 0 ? trim((string)$row[$idx['course_name']]) : $code;
                                    $year = (int)$row[$idx['year']];
                                    $rate = (float)$row[$idx['employment_rate']];
                                    
                                    if ($code && $year > 0 && $rate >= 0) {
                                        if (!isset($courseData[$code])) {
                                            $courseData[$code] = ['course_name' => $name, 'course_code' => $code, 'years' => [], 'rates' => []];
                                        }
                                        $courseData[$code]['years'][] = $year;
                                        $courseData[$code]['rates'][] = $rate;
                                        
                                        if (!isset($allCoursesData[$year])) $allCoursesData[$year] = [];
                                        $allCoursesData[$year][] = $rate;
                                    }
                                }
                                fclose($h);

                                // Calculate predictions and trends
                                $predictions = [];
                                foreach ($courseData as $code => $data) {
                                    $years = $data['years'];
                                    $rates = $data['rates'];
                                    if (count($years) >= 2) {
                                        array_multisort($years, $rates);
                                        $latestRate = end($rates);
                                        $latestYear = end($years);
                                        
                                        // Simple linear trend prediction
                                        $n = count($years);
                                        $sumX = array_sum($years);
                                        $sumY = array_sum($rates);
                                        $sumXY = 0;
                                        $sumX2 = 0;
                                        for ($i = 0; $i < $n; $i++) {
                                            $sumXY += $years[$i] * $rates[$i];
                                            $sumX2 += $years[$i] * $years[$i];
                                        }
                                        
                                        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
                                        $prediction2026 = max(0, min(100, $latestRate + $slope * (2026 - $latestYear)));
                                        $change = $prediction2026 - $latestRate;
                                        
                                        $predictions[] = [
                                            'course_code' => $code,
                                            'course_name' => $data['course_name'],
                                            'prediction_2026' => round($prediction2026, 1),
                                            'latest_rate' => round($latestRate, 1),
                                            'change' => round($change, 1),
                                            'years' => $years,
                                            'rates' => $rates
                                        ];
                                    }
                                }

                                // Add "All Courses" prediction
                                if (!empty($allCoursesData)) {
                                    $allYears = array_keys($allCoursesData);
                                    sort($allYears);
                                    $allRates = [];
                                    foreach ($allYears as $year) {
                                        $allRates[] = array_sum($allCoursesData[$year]) / count($allCoursesData[$year]);
                                    }
                                    
                                    if (count($allYears) >= 2) {
                                        $latestRate = end($allRates);
                                        $latestYear = end($allYears);
                                        
                                        $n = count($allYears);
                                        $sumX = array_sum($allYears);
                                        $sumY = array_sum($allRates);
                                        $sumXY = 0;
                                        $sumX2 = 0;
                                        for ($i = 0; $i < $n; $i++) {
                                            $sumXY += $allYears[$i] * $allRates[$i];
                                            $sumX2 += $allYears[$i] * $allYears[$i];
                                        }
                                        
                                        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
                                        $prediction2026 = max(0, min(100, $latestRate + $slope * (2026 - $latestYear)));
                                        $change = $prediction2026 - $latestRate;
                                        
                                        array_unshift($predictions, [
                                            'course_code' => 'ALL',
                                            'course_name' => 'All Courses Combined',
                                            'prediction_2026' => round($prediction2026, 1),
                                            'latest_rate' => round($latestRate, 1),
                                            'change' => round($change, 1),
                                            'years' => $allYears,
                                            'rates' => $allRates
                                        ]);
                                    }
                                }

                                // Sort by prediction (descending)
                                usort($predictions, function($a, $b) {
                                    return $b['prediction_2026'] <=> $a['prediction_2026'];
                                });

                                $employmentData = [
                                    'predictions' => $predictions,
                                    'top_course' => $predictions[0] ?? null
                                ];

                                // Update overall stats
                                if (!empty($predictions)) {
                                    $overallEmploymentRate = round($predictions[0]['prediction_2026'], 0);
                                    $change = $predictions[0]['change'];
                                    $employmentTrend = ($change > 0 ? '+' : '') . round($change, 1) . '% from 2025';
                                }
                            }
                        }
                    }
                    ?>

                    <?php if (is_array($employmentData) && !empty($employmentData['predictions'])) { ?>
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">Course Effectiveness through Employment Rate (2025)</h3>
                            <p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">This section describes how the system helps MMTVTC use quantifiable data on course effectiveness and graduate employability to improve its program structures and course offerings. It compares the expected employment rates for different training programs to show the course effectiveness.</p>
                            <canvas id="employmentRateChart" class="chart-canvas"></canvas>
                        </div>
                    </div>

                    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    window.__employmentData = <?php echo json_encode($employmentData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    </script>
                    <?php } else { ?>
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">Course Effectiveness through Employment Rate (2025)</h3>
                            <div style="opacity:0.8;">Upload <code>data/mmtvtc_employment_rates.csv</code> to display this chart.</div>
                        </div>
                    </div>
                    <?php } ?>

                    <!-- Employment Rate Trend Analysis -->
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">Employment Rate Trend Analysis (6-Month Intervals)</h3>
                            <p style="margin-bottom:12px;color:#666;font-size:0.9rem;">This chart tracks the percentage of graduates who successfully find employment every 6 months. The blue line shows historical employment rates, while the orange dashed line predicts future employment rates for 2026. Use the filters above to analyze specific courses, years, or time periods. Higher percentages indicate better job placement success for graduates.</p>
                            <div class="filters-container" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                <select class="filter-select" id="employmentTrendCourseSelect">
                                    <option value="__ALL__">All Courses</option>
                                </select>
                                <select class="filter-select" id="employmentTrendYearSelect">
                                    <option value="__ALL__">All Years</option>
                                </select>
                                <select class="filter-select" id="employmentTrendHalfSelect">
                                    <option value="__ALL__">All Periods</option>
                                    <option value="1">First Half (H1) - Jan-Jun</option>
                                    <option value="2">Second Half (H2) - Jul-Dec</option>
                                </select>
                                <span id="employmentTrendInfo" style="opacity:0.8;font-size:0.9rem"></span>
                            </div>
                            <canvas id="employmentTrendAnalysisChart" class="chart-canvas"></canvas>
                        </div>
                    </div>

                    <!-- Course Trends Visualization -->
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">Course Trends Visualization (6-Month Periods)</h3>
                            <p style="margin-bottom:12px;color:#666;font-size:0.9rem;">This visualization shows student enrollment trends for different courses over 6-month periods. The blue line displays historical enrollment numbers, while the orange dashed line forecasts expected enrollment for 2026. Use the filters to focus on specific courses, years, or time periods. This helps identify which courses are growing in popularity and plan for future capacity needs.</p>
                            <div class="filters-container" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                <select class="filter-select" id="courseTrendsCourseSelect">
                                    <option value="__ALL__">All Courses</option>
                                </select>
                                <select class="filter-select" id="courseTrendsYearSelect">
                                    <option value="__ALL__">All Years</option>
                                </select>
                                <select class="filter-select" id="courseTrendsHalfSelect">
                                    <option value="__ALL__">All Periods</option>
                                    <option value="1">First Half (H1) - Jan-Jun</option>
                                    <option value="2">Second Half (H2) - Jul-Dec</option>
                                </select>
                                <span id="courseTrendsInfo" style="opacity:0.8;font-size:0.9rem"></span>
                            </div>
                            <canvas id="courseTrendsChart" class="chart-canvas"></canvas>
                        </div>
                    </div>

                    <!-- Job Trends Visualization -->
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        <div class="analytics-card" style="padding:16px;">
                            <h3 class="analytics-label" style="margin-bottom:8px;">Job Trends Visualization (6-Month Periods)</h3>
                            <p style="margin-bottom:12px;color:#666;font-size:0.9rem;">This chart analyzes job market trends by showing which industries and job types are most popular among graduates over 6-month periods. The blue line represents historical job placement data, while the orange dashed line predicts future job market trends for 2026. Use the filters to examine specific industries, years, or time periods. This information helps graduates understand which career paths are in demand and growing.</p>
                            <div class="filters-container" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                <select class="filter-select" id="jobTrendsJobSelect">
                                    <option value="__ALL__">All Jobs/Industries</option>
                                </select>
                                <select class="filter-select" id="jobTrendsYearSelect">
                                    <option value="__ALL__">All Years</option>
                                </select>
                                <select class="filter-select" id="jobTrendsHalfSelect">
                                    <option value="__ALL__">All Periods</option>
                                    <option value="1">First Half (H1) - Jan-Jun</option>
                                    <option value="2">Second Half (H2) - Jul-Dec</option>
                                </select>
                                <span id="jobTrendsInfo" style="opacity:0.8;font-size:0.9rem"></span>
                            </div>
                            <canvas id="jobTrendsChart" class="chart-canvas"></canvas>
                        </div>
                    </div>
            
                </div>
            </section>


            <!-- About Section -->
            <!-- Replacement note: Standardized About Us copied from instructor dashboard to ensure parity across dashboards. -->
            <section id="about" class="page-section">
                <!-- Hero Section (standardized) -->
                <div class="about-hero-modern">
                    <div class="hero-background">
                        <div class="hero-pattern"></div>
                        <div class="hero-gradient"></div>
                    </div>
                    <div class="hero-content">
                        <div class="hero-logo-container">
                            <img src="images/logo.png" alt="MMTVTC Logo" class="hero-logo">
                            <div class="hero-logo-rings">
                                <div class="ring ring-1"></div>
                                <div class="ring ring-2"></div>
                            </div>
                        </div>
                        <h1 class="hero-title">
                            <span class="title-main">MMTVTC</span>
                            <span class="title-sub">Manpower Mandaluyong and Technical Vocational Training Center</span>
                        </h1>
                        <p class="hero-tagline">Empowering Futures Through Excellence in Technical Education</p>
                    </div>
                </div>

                <!-- Vision & Mission Section (standardized) -->
                <div class="vision-mission-section">
                    <div class="section-container">
                        <div class="vision-card">
                            <div class="card-header vision-header">
                                <div class="header-icon"><i class="fas fa-eye"></i></div>
                                <h2 class="card-title">Our Vision</h2>
                            </div>
                            <div class="card-content">
                                <p class="vision-quote">"TO BE THE CENTER OF WHOLE LEARNING EXPERIENCE FOR GREAT ADVANCEMENT."</p>
                            </div>
                        </div>

                        <div class="mission-card">
                            <div class="card-header mission-header">
                                <div class="header-icon"><i class="fas fa-compass"></i></div>
                                <h2 class="card-title">Our Mission</h2>
                            </div>
                            <div class="card-content">
                                <p class="mission-statement">"WE, THE MMTVTC FAMILY, WORKING AS A COMMUNITY, COMMIT OURSELVES TO PROMOTE LIFELONG TECHNICAL - VOCATIONAL TRAINING EXPERIENCE TO DEVELOP PRACTICAL LIFE SKILLS FOR GREAT ADVANCEMENT."</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Section (standardized) -->
                <div class="contact-section-modern">
                    <div class="contact-container">
                        <div class="contact-header">
                            <h3 class="contact-title-modern">Connect With Us</h3>
                            <p class="contact-description-modern">Join our community and stay updated with the latest programs and opportunities</p>
                        </div>
                        <div class="contact-actions">
                            <a href="https://www.facebook.com/manpowermanda.tesda/" target="_blank" class="contact-btn-modern primary">
                                <div class="btn-icon"><i class="fab fa-facebook-f"></i></div>
                                <div class="btn-content">
                                    <span class="btn-title">Follow Us</span>
                                    <span class="btn-subtitle">Facebook Page</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/Student.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/js/Student.js")); ?>"></script>
    <script src="js/employment_charts.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/js/employment_charts.js")); ?>"></script>
    <script src="js/employment_trend_analysis.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/js/employment_trend_analysis.js")); ?>"></script>
    <script src="js/course_trends_visualization.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/js/course_trends_visualization.js")); ?>"></script>
    <script src="js/job_trends_visualization.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/js/job_trends_visualization.js")); ?>"></script>
    <script src="js/graduates_course_popularity_2025.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/js/graduates_course_popularity_2025.js")); ?>"></script>
    <script src="js/cross-tab-logout.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/js/cross-tab-logout.js")); ?>"></script>
    
    <!-- Student Job Matching Live Updates -->
    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    (function(){
        // Live data container for jobs
        var allJobs = [];
        var filteredJobs = [];

        function escapeHtml(s){
            s = String(s==null?'':s);
            return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); });
        }

        function loadJobs(){
            // Gate by NC2 confirmation
            fetch('apis/nc2_validation.php?action=status', {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(s){
                    if(!s || s.status !== 'confirmed'){
                        allJobs = [];
                        filteredJobs = [];
                        var grid = document.getElementById('studentJobCardsGrid');
                        if(grid){ grid.innerHTML = '<div style="text-align:center; padding:2rem; color:#666;">Awaiting NC2 confirmation.</div>'; }
                        updateJobCount();
                        populateFilterDropdowns();
                        throw new Error('nc2_not_confirmed');
                    }
                    return fetch('apis/jobs_handler.php', {credentials:'same-origin'});
                })
                .then(function(r){ return r.json(); })
                .then(function(j){ 
                    if(j && j.success && Array.isArray(j.data)) { 
                        allJobs = j.data; 
                        // Filter jobs based on student's course
                        var course = (window.currentStudentCourse || '').toString().trim().toLowerCase();
                        if(course){
                            filteredJobs = allJobs.filter(function(job){
                                var jc = (job.course || job.title || '').toString().toLowerCase();
                                return jc.indexOf(course) !== -1 || course.indexOf(jc) !== -1;
                            });
                        } else {
                            filteredJobs = allJobs;
                        }
                    } else {
                        allJobs = [];
                        filteredJobs = [];
                    }
                    renderJobs(filteredJobs);
                    updateJobCount();
                    populateFilterDropdowns();
                })
                .catch(function(err){
                    if(String(err && err.message) === 'nc2_not_confirmed') return;
                    allJobs = [];
                    filteredJobs = [];
                    renderJobs([]);
                    updateJobCount();
                });
        }

        function renderJobs(jobs){
            var grid = document.getElementById('studentJobCardsGrid');
            if(!grid) return;
            
            if(jobs.length === 0){
                grid.innerHTML = '<div style="text-align:center; padding:2rem; color:#666;">No jobs found matching your criteria.</div>';
                return;
            }

            grid.innerHTML = jobs.map(function(job){
                return (
                    '<div class="job-card">' +
                    '  <div class="job-header">' +
                    '    <h3 class="job-title">' + escapeHtml(job.title) + '</h3>' +
                    '  </div>' +
                    '  <div class="job-details">' +
                    (job.course ? ('    <p><strong>Course:</strong> ' + escapeHtml(job.course) + '</p>') : '') +
                    '    <p><strong>Company:</strong> ' + escapeHtml(job.company) + '</p>' +
                    '    <div class="job-info">' +
                    '      <div class="job-info-item"><i class="fas fa-map-marker-alt"></i><span>' + escapeHtml(job.location) + '</span></div>' +
                    '      <div class="job-info-item"><i class="fas fa-dollar-sign"></i><span>' + escapeHtml(job.salary || '—') + '</span></div>' +
                    '      <div class="job-info-item"><i class="fas fa-clock"></i><span>' + escapeHtml(job.experience || '—') + '</span></div>' +
                    '    </div>' +
                    (job.description ? ('    <p class="job-description">' + escapeHtml(job.description) + '</p>') : '') +
                    '  </div>' +
                    '</div>'
                );
            }).join('');
        }

        function updateJobCount(){
            var countEl = document.querySelector('.job-count');
            if(countEl){
                countEl.textContent = filteredJobs.length + ' job' + (filteredJobs.length !== 1 ? 's' : '') + ' found';
            }
        }

        function populateFilterDropdowns(){
            var locationFilter = document.getElementById('studentLocationFilter');
            var experienceFilter = document.getElementById('studentExperienceFilter');
            
            if(!locationFilter || !experienceFilter) return;

            // Get unique locations and experiences from filtered jobs
            var locations = [...new Set(filteredJobs.map(job => job.location).filter(Boolean))].sort();
            var experiences = [...new Set(filteredJobs.map(job => job.experience).filter(Boolean))].sort();

            // Populate location dropdown
            locationFilter.innerHTML = '<option value="">All Locations</option>' + 
                locations.map(loc => '<option value="' + escapeHtml(loc) + '">' + escapeHtml(loc) + '</option>').join('');

            // Populate experience dropdown
            experienceFilter.innerHTML = '<option value="">All Experience Levels</option>' + 
                experiences.map(exp => '<option value="' + escapeHtml(exp) + '">' + escapeHtml(exp) + '</option>').join('');
        }

        function applyFilters(){
            var locationFilter = document.getElementById('studentLocationFilter');
            var experienceFilter = document.getElementById('studentExperienceFilter');
            
            if(!locationFilter || !experienceFilter) return;

            var selectedLocation = locationFilter.value;
            var selectedExperience = experienceFilter.value;

            filteredJobs = allJobs.filter(function(job){
                var course = (window.currentStudentCourse || '').toString().trim().toLowerCase();
                var courseMatch = !course || (job.course && job.course.toLowerCase().indexOf(course) !== -1) || 
                                 (job.title && job.title.toLowerCase().indexOf(course) !== -1);
                var locationMatch = !selectedLocation || job.location === selectedLocation;
                var experienceMatch = !selectedExperience || job.experience === selectedExperience;
                
                return courseMatch && locationMatch && experienceMatch;
            });

            renderJobs(filteredJobs);
            updateJobCount();
        }

        // Bind event listeners
        function bindEventListeners(){
            var locationFilter = document.getElementById('studentLocationFilter');
            var experienceFilter = document.getElementById('studentExperienceFilter');
            var refreshBtn = document.getElementById('studentRefreshJobsBtn');
            var nc2Btn = document.getElementById('nc2SubmissionBtn');

            if(locationFilter) locationFilter.addEventListener('change', applyFilters);
            if(experienceFilter) experienceFilter.addEventListener('change', applyFilters);
            if(refreshBtn) refreshBtn.addEventListener('click', function(){
                try {
                    refreshBtn.classList.add('is-spinning');
                    setTimeout(function(){ refreshBtn.classList.remove('is-spinning'); }, 1000);
                } catch(_) {}
                loadJobs();
            });
            if(nc2Btn) nc2Btn.addEventListener('click', function(){
                var modal = document.getElementById('nc2SubmissionModal');
                if(modal) {
                    modal.style.display = 'flex';
                    // Focus on the input field when modal opens
                    setTimeout(function() {
                        var input = document.getElementById('nc2Link');
                        if(input) input.focus();
                    }, 100);
                }
            });

        }

        // Initialize
        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', function(){
                bindEventListeners();
                loadJobs();
                // Refresh every 30 seconds to get new jobs
                setInterval(loadJobs, 30000);
            });
        } else {
            bindEventListeners();
            loadJobs();
            setInterval(loadJobs, 30000);
        }
    })();
    </script>
</body>
</html>
