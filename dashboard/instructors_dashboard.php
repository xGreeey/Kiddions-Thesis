<?php
    require_once '../security/session_config.php';
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    error_reporting(E_ALL);
    // Unified error display via security/error_handler.php
    require_once '../security/db_connect.php';

    // Security logging helper
    function logSecurityEvent($event, $details) {
        $logDir = '../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $log = date('Y-m-d H:i:s') . " - " . $event . " - " . json_encode($details) . "\n";
        @file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);
    }

    // Session validation for instructor role (role = 1)
    function validateInstructorSession() {
        if (!isset($_SESSION['user_verified']) || !$_SESSION['user_verified']) {
            return false;
        }
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 1) {
            return false;
        }
        // Instructors may not have student_number; require email only
        if (!isset($_SESSION['email'])) {
            return false;
        }
        // Session timeout is handled by session_config.php (2 hours)
        // Removed redundant 30-minute timeout check to match admin dashboard behavior
        return true;
    }

    if (isset($_POST['logout'])) {
        if (function_exists('clearRememberMe')) { clearRememberMe(); }
        if (function_exists('destroySession')) { destroySession(); } else { session_unset(); session_destroy(); }
        header('Location: ../index.php');
        exit();
    }

    if (!validateInstructorSession()) {
        $logDetails = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'attempted_access' => 'instructors_dashboard',
            'session_data' => [
                'user_verified' => $_SESSION['user_verified'] ?? 'not_set',
                'user_role' => $_SESSION['user_role'] ?? 'not_set',
                'email' => $_SESSION['email'] ?? 'not_set',
                'authenticated' => $_SESSION['authenticated'] ?? 'not_set'
            ]
        ];
        logSecurityEvent('UNAUTHORIZED_INSTRUCTOR_ACCESS', $logDetails);
        session_unset();
        session_destroy();
        header('Location: ../index.php');
        exit();
    }

    logSecurityEvent('INSTRUCTOR_DASHBOARD_ACCESS', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'email' => $_SESSION['email'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    // Initialize empty arrays for client-side loading
    $instructorCourses = [];
    $students = [];
    $selectedCourse = null;
    $showStudents = false;

    // Get instructor's current course
    $instructorCourse = null;
    try {
        // Resolve current user id from session email
        $userEmail = $_SESSION['email'] ?? null;
        $userId = null;
        if ($userEmail) {
            $uStmt = $pdo->prepare("SELECT id FROM mmtvtc_users WHERE email = ? LIMIT 1");
            $uStmt->execute([$userEmail]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow && isset($uRow['id'])) { $userId = (int)$uRow['id']; }
        }

        // Look up instructor's assigned course (primary_course)
        if ($userId) {
            try {
                $cStmt = $pdo->prepare("SELECT primary_course FROM instructors WHERE user_id = ? LIMIT 1");
                $cStmt->execute([$userId]);
                $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($cRow && !empty($cRow['primary_course'])) {
                    $instructorCourse = $cRow['primary_course'];
                }
            } catch (Throwable $e) {
                // instructors table may not exist yet; fall back to showing nothing filtered
                error_log('instructors lookup failed: ' . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log('Failed to fetch instructor course: ' . $e->getMessage());
    }

    // Fetch KPI data for dashboard
    $kpiData = [
        'activeCourses' => 0,
        'totalStudents' => 0
    ];

    try {
        // Count distinct active courses (courses that have students)
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT course) as active_courses FROM students WHERE course IS NOT NULL AND course != ''");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $kpiData['activeCourses'] = (int)($result['active_courses'] ?? 0);
    } catch (Exception $e) {
        error_log('Failed to fetch active courses count: ' . $e->getMessage());
    }

    try {
        // Count total students
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_students FROM students");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $kpiData['totalStudents'] = (int)($result['total_students'] ?? 0);
    } catch (Exception $e) {
        error_log('Failed to fetch total students count: ' . $e->getMessage());
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Instructors Dashboard</title>
        <link rel="icon" href="../images/logo.png" type="image/png">

        <!-- External CSS -->
        <!-- Removed external CSS CDNs for stricter CSP; relying on local CSS only -->
        <link rel="stylesheet" href="../CSS/instructor.css?v=<?php echo urlencode((string)@filemtime(__DIR__."/../CSS/instructor.css")); ?>">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <!-- Copied from admin_dashboard: Chart.js is required for Career Analytics charts -->
        <!-- Removed external JS CDN (Chart.js). If charts are required, we can bundle a local copy. -->

        <!-- Scoped dark-mode refinements for Grades + percentage animation -->
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

            /* Grades dark-mode refinements (scoped to this page/table) */
            body[data-theme="dark"] .tab-panel[data-panel="grades"] .table-container {
                background: transparent;
                border: 1px solid rgba(148, 163, 184, 0.15);
                box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
                border-radius: 1.25rem;
            }

            body[data-theme="dark"] #gradesTable thead th {
                background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
                color: #e5e7eb;
            }

            body[data-theme="dark"] #gradesTable tbody tr {
                background: rgba(12, 18, 30, 0.85);
                border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            }

            body[data-theme="dark"] #gradesTable tbody tr:hover {
                background: rgba(22, 30, 44, 0.95);
            }

            body[data-theme="dark"] #gradesTable td,
            body[data-theme="dark"] #gradesTable th {
                border: none;
            }

            body[data-theme="dark"] #gradesTable .final-grade-cell {
                border-color: transparent;
                box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.03) inset, 0 8px 18px rgba(0, 0, 0, 0.35);
            }

            body[data-theme="dark"] #gradesTable .badge.bg-light {
                background: linear-gradient(135deg, #1f2937 0%, #0f172a 100%) !important;
                color: #d1d5db !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
            }

            /* Count-up pop animation for percentage */
            @keyframes gradePop {
                0% {
                    transform: scale(0.92);
                }

                60% {
                    transform: scale(1.02);
                }

                100% {
                    transform: scale(1);
                }
            }

            .final-grade-cell.animating {
                animation: gradePop .35s ease-out;
            }

            /* Bootstrap dark theming overrides when dark mode is active */
            body[data-theme="dark"] .table,
            body[data-theme="dark"] .modal-content,
            body[data-theme="dark"] .form-control,
            body[data-theme="dark"] .form-select {
                background-color: #0f172a !important; /* slate-900 */
                color: var(--text) !important;
                border-color: rgba(148,163,184,0.25) !important;
            }
            body[data-theme="dark"] .table thead th,
            body[data-theme="dark"] .table td,
            body[data-theme="dark"] .table th {
                border-color: rgba(148,163,184,0.15) !important;
            }
            body[data-theme="dark"] .btn.btn-light,
            body[data-theme="dark"] .bg-white {
                background-color: #111827 !important;
                color: var(--text) !important;
            }
        </style>
        
        <!-- Correct Answer Checkbox Styles -->
        <style>
        .gforms-correct-answer {
            display: flex;
            align-items: center;
            margin-left: 8px;
            font-size: 12px;
        }
        
        /* Enhanced Attendance Interface Styles */
        .attendance-container {
            padding: 0;
        }
        
        .attendance-course-selector {
            margin-bottom: 24px;
        }
        
        .course-selector-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }
        
        .attendance-course-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .attendance-course-title i {
            color: #007bff;
        }
        
        .course-selector-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .search-container {
            flex: 1;
            max-width: 300px;
        }
        
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-icon {
            position: absolute;
            left: 10px;
            color: #6b7280;
            z-index: 1;
        }
        
        .search-input {
            width: 100%;
            padding: 8px 12px 8px 35px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .course-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .course-card {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .course-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .course-card.loading-card {
            text-align: center;
            padding: 40px 16px;
            cursor: default;
        }
        
        .course-card.loading-card:hover {
            transform: none;
            box-shadow: none;
        }
        
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            color: #6c757d;
        }
        
        .loading-spinner i {
            font-size: 24px;
            color: #007bff;
        }
        
        /* Attendance Content Styles */
        .attendance-content {
            animation: fadeIn 0.3s ease;
        }
        
        .attendance-header-compact {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #e1e5e9;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .course-info h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .course-stats {
            font-size: 12px;
            color: #6c757d;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .date-selector {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .date-label {
            font-size: 12px;
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        /* Quick Stats */
        .attendance-quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px;
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-item.present {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .stat-item.absent {
            border-color: #dc3545;
            background: #fff8f8;
        }
        
        .stat-item.total {
            border-color: #007bff;
            background: #f8f9ff;
        }
        
        .stat-item.rate {
            border-color: #ffc107;
            background: #fffdf0;
        }
        
        .stat-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        
        .stat-item.present i { color: #28a745; }
        .stat-item.absent i { color: #dc3545; }
        .stat-item.total i { color: #007bff; }
        .stat-item.rate i { color: #ffc107; }
        
        .stat-number {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Attendance Table */
        .attendance-table-container {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .bulk-actions {
            display: flex;
            gap: 8px;
        }
        
        .table-info {
            font-size: 12px;
            color: #6c757d;
        }
        
        .table-wrapper {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .attendance-table {
            margin: 0;
        }
        
        .attendance-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #495057;
            border-bottom: 2px solid #e1e5e9;
            padding: 12px 8px;
        }
        
        .attendance-table td {
            padding: 12px 8px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .attendance-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .col-checkbox { width: 40px; }
        .col-id { width: 100px; }
        .col-name { min-width: 150px; }
        .col-present { width: 80px; text-align: center; }
        .col-absent { width: 80px; text-align: center; }
        .col-rate { width: 80px; text-align: center; }
        .col-actions { width: 120px; }
        
        /* No Data Message */
        .no-data-message {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .no-data-message i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .no-data-message p {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 500;
        }
        
        .no-data-message small {
            font-size: 12px;
            opacity: 0.7;
        }
        
        /* Attendance Actions */
        .attendance-actions {
            display: flex;
            gap: 4px;
        }
        
        .attendance-btn {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .attendance-btn.present {
            background: #d4edda;
            color: #155724;
        }
        
        .attendance-btn.present:hover {
            background: #c3e6cb;
        }
        
        .attendance-btn.absent {
            background: #f8d7da;
            color: #721c24;
        }
        
        .attendance-btn.absent:hover {
            background: #f5c6cb;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .attendance-header-compact {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }
            
            .header-left, .header-right {
                justify-content: space-between;
            }
            
            .course-cards-container {
                grid-template-columns: 1fr;
            }
            
            .attendance-quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-header-actions {
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }
            
            .bulk-actions {
                justify-content: center;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Course Card Styles */
        .course-card-header {
            margin-bottom: 12px;
        }
        
        .course-card-header h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .course-code {
            font-size: 12px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .course-card-stats {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        
        .course-card-stats .stat {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: #6c757d;
        }
        
        .course-card-stats .stat i {
            font-size: 10px;
            color: #007bff;
        }
        
        .no-courses-card {
            text-align: center;
            padding: 40px 16px;
            cursor: default;
        }
        
        .no-courses-card:hover {
            transform: none;
            box-shadow: none;
        }
        
        /* Attendance Rate Styling */
        .attendance-rate {
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }
        
        .attendance-rate.excellent {
            background: #d4edda;
            color: #155724;
        }
        
        .attendance-rate.good {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .attendance-rate.fair {
            background: #fff3cd;
            color: #856404;
        }
        
        .attendance-rate.poor {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Batch Selector Styles */
        .batch-selector {
            margin-bottom: 20px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }
        
        .batch-title {
            margin: 0 0 12px 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .batch-tabs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
        }
        
        .batch-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px;
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .batch-tab:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }
        
        .batch-tab.active {
            border-color: #007bff;
            background: #e3f2fd;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
        }
        
        .batch-label {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 4px;
        }
        
        .batch-period {
            font-size: 12px;
            color: #6c757d;
        }
        
        /* Course Batches Styles */
        .course-batches {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e1e5e9;
        }
        
        .batch-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 12px;
            color: #6c757d;
        }
        
        .batch-name {
            font-weight: 600;
            color: #495057;
        }
        
        .batch-period {
            font-style: italic;
        }
        
        .batch-count {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }
            color: #666;
        }
        
        .gforms-correct-answer input[type="checkbox"] {
            margin-right: 4px;
        }
        
        .gforms-option {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .gforms-option input[type="text"] {
            flex: 1;
            margin-right: 8px;
        }
        </style>

        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </head>

    <body>
        <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <!-- Dashboard Layout -->
        <div class="dashboard-container">
            <!-- Sidebar Navigation -->
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-content">
                    <!-- Header -->
                    <div class="sidebar-header">
                        <div class="sidebar-brand">
                            <div class="brand-icon">
                                <img src="../images/logo.png" alt="MMTVTC Logo" class="brand-logo-img">
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
                                <button class="nav-item" data-section="trainee">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span class="nav-text">Trainee Record</span>
                                </button>
                            </li>
                            <li>
                                <button class="nav-item" data-section="job-matching">
                                    <i class="fa-solid fa-list-check"></i>
                                    <span class="nav-text">Job Matching</span>
                                </button>
                            </li>
                            <!-- Copied from admin_dashboard: Career Analytics sidebar item for identical routing/navigation -->
                            <li>
                                <button class="nav-item" data-section="career">
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

                        
                    </div>
                </div>
            </aside>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <!-- Main Header -->
                <header class="main-header">
                    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle menu" aria-controls="sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="main-title">Dashboard</h1>
                    <div class="header-right-section">
                        <!-- Notification Container -->
                        <div class="notification-container">
                            <button class="notification-bell" id="notificationBell">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge" id="notificationCount">3</span>
                            </button>
                            <div class="notification-dropdown" id="notificationDropdown">
                                <div class="notification-header">
                                    <h3>System Notifications</h3>
                                    <button class="notification-close" id="notificationClose">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="notification-list" id="notificationList">
                                    <div class="notification-item">
                                        <div class="notification-icon">
                                            <i class="fas fa-cog"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">System Update</div>
                                            <div class="notification-message">New features added to job matching algorithm
                                            </div>
                                            <div class="notification-time">2 hours ago</div>
                                        </div>
                                    </div>
                                    <div class="notification-item">
                                        <div class="notification-icon">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">Database Backup Complete</div>
                                            <div class="notification-message">All trainee records successfully backed up
                                            </div>
                                            <div class="notification-time">Yesterday</div>
                                        </div>
                                    </div>
                                    <div class="notification-item">
                                        <div class="notification-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">Pending Reviews</div>
                                            <div class="notification-message">5 trainee applications awaiting approval</div>
                                            <div class="notification-time">3 days ago</div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Main Content Area -->
                <main class="main-content" id="mainContent">
                    <!-- Dashboard Section -->
                    <section id="dashboard" class="page-section active">
                        <!-- Welcome Message (position aligned with student dashboard) -->
                        <div class="welcome-message" style="padding: 10px 20px 20px 20px; margin-bottom: 24px;">
                            <h1 class="welcome-title" style="font-size: 1.8rem;">Welcome Instructor!</h1>
                        </div>
                        <!-- Dashboard Section -->
                        <!-- Content removed per request: Dashboard section inner content cleared to keep layout intact. -->
                        <!-- Recent Activities Section -->
                        <!-- Content removed per request: recent activities markup intentionally removed. -->
                        <!-- To-Do List Section -->
                        <!-- Content removed per request: to-do list markup intentionally removed. -->

                        <!-- New instructor dashboard content: structured, responsive layout inspired by the provided image.
                            - Top row: KPI summary cards (Active Courses, Students, To Grade, Attendance)
                            - Left column: Course Management and Student Progress snapshot
                            - Right column: Announcements, Quick Actions, Resources
                            All elements use Bootstrap grid/utilities for responsiveness; no external logic changed. -->

                        <!-- KPI Summary Row (using Admin stats-grid UI) -->
                        <div class="container-fluid" id="inst-kpi-row">
                            <div class="stats-grid">
                                <div class="analytics-card">
                                    <div class="analytics-icon blue"><i class="fas fa-book-open"></i></div>
                                    <h3 class="analytics-label">Active Courses</h3>
                                    <p class="analytics-value" id="kpiActiveCourses"><?php echo $kpiData['activeCourses']; ?></p>
                                </div>
                                <div class="analytics-card">
                                    <div class="analytics-icon green"><i class="fas fa-users"></i></div>
                                    <h3 class="analytics-label">Students</h3>
                                    <p class="analytics-value" id="kpiStudents"><?php echo $kpiData['totalStudents']; ?></p>
                                </div>
                                
                                <div class="analytics-card">
                                    <div class="analytics-icon orange"><i class="fas fa-calendar-check"></i></div>
                                    <h3 class="analytics-label">Attendance Today</h3>
                                    <p class="analytics-value" id="kpiAttendance">0%</p>
                                </div>
                            </div>
                        </div>

                        <!-- Main Grid: two columns on desktop, stacked on mobile -->
                        <div class="container-fluid mt-3" id="inst-main-grid">
                            <div class="row g-3">
                                

                                <!-- Single Column: Student Progress -->
                                <div class="col-12">
                                    <!-- Student Progress Snapshot -->
                                    <div class="card border-0 shadow-sm mb-3">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="fas fa-chart-line text-primary"></i>
                                                    <h5 class="mb-0">Student Progress</h5>

                                                </div>
                                            </div>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover align-middle mb-0" id="progressMiniTable" aria-label="Student progress snapshot">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th scope="col">Student</th>
                                                            <th scope="col" class="text-center">Course</th>
                                                            <th scope="col" class="text-center">Grade</th>
                                                            <th scope="col" class="text-end">Details</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($students)):
                                                            $limit = 5;
                                                            $idx = 0;
                                                            foreach ($students as $s):
                                                                if ($idx++ >= $limit)
                                                                    break; ?>
                                                                <?php
                                                                $sn = htmlspecialchars($s['student_number'] ?? '');
                                                                $fname = htmlspecialchars($s['first_name'] ?? '');
                                                                $lname = htmlspecialchars($s['last_name'] ?? '');
                                                                $course = htmlspecialchars($s['course'] ?? '');
                                                                ?>
                                                                <tr data-student-id="<?php echo $sn; ?>">
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="text-truncate" style="max-width: 220px;" title="<?php echo $fname . ' ' . $lname; ?>">
                                                                                <?php echo $fname . ' ' . $lname; ?>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <span class="text-truncate d-inline-block" style="max-width: 240px;" title="<?php echo $course !== '' ? $course : '—'; ?>">
                                                                            <?php echo $course !== '' ? $course : '—'; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <span class="badge bg-light mini-grade" id="miniGrade-<?php echo $sn; ?>">0.0%</span>
                                                                    </td>
                                                                    <td class="text-end">
                                                                        <span class="progress-remark" id="remark-<?php echo $sn; ?>">—</span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; else: ?>
                                                            <tr>
                                                                <td colspan="4" class="text-center text-muted">No students
                                                                    available.</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex align-items-center gap-3 mt-3 small text-muted">
                                                <span><span class="badge bg-success">&nbsp;</span> 75% and above</span>
                                                <span><span class="badge bg-danger">&nbsp;</span> 1% - 74.9%</span>
                                                <span><span class="badge bg-warning text-dark">&nbsp;</span> 0%</span>
                                    <button class="btn btn-primary btn-sm ms-auto" type="button" onclick="event.stopPropagation(); showSection('trainee'); updateActiveNav('trainee');"><i class="fas fa-eye me-1"></i>View All</button>
                                            </div>
                                            <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                (function(){
                                                    function fetchAverage(studentId, gradeNumber){
                                                        return fetch('../apis/grade_details.php?action=aggregate&student_number=' + encodeURIComponent(studentId) + '&grade_number=' + gradeNumber, { credentials: 'same-origin' })
                                                            .then(function(r){ return r.ok ? r.json() : Promise.reject(r.status); })
                                                            .then(function(j){ return (j && typeof j.average === 'number') ? j.average : 0; })
                                                            .catch(function(){ return 0; });
                                                    }
                                                    function updateMiniRow(studentId, finalVal){
                                                        var el = document.getElementById('miniGrade-' + studentId);
                                                        if(!el) return;
                                                        var fixed = Number((finalVal || 0).toFixed(1));
                                                        el.textContent = fixed.toFixed(1) + '%';
                                                        // simple color cue
                                                        el.classList.remove('bg-light','bg-success','bg-danger','bg-warning','text-dark');
                                                        if(fixed >= 75){ el.classList.add('bg-success'); }
                                                        else if(fixed > 0){ el.classList.add('bg-danger'); }
                                                        else { el.classList.add('bg-warning','text-dark'); }

                                                        // update remark column
                                                        var remarkEl = document.getElementById('remark-' + studentId);
                                                        if(remarkEl){
                                                            if(fixed >= 50){
                                                                remarkEl.textContent = 'Passing Remark';
                                                                remarkEl.classList.remove('text-danger');
                                                                remarkEl.classList.add('text-success');
                                                            } else {
                                                                remarkEl.textContent = 'Failing Remark';
                                                                remarkEl.classList.remove('text-success');
                                                                remarkEl.classList.add('text-danger');
                                                            }
                                                        }
                                                    }
                                                    function refreshMiniTableGrades(){
                                                        var rows = document.querySelectorAll('#progressMiniTable tbody tr[data-student-id]');
                                                        rows.forEach(function(row){
                                                            var id = row.getAttribute('data-student-id');
                                                            if(!id) return;
                                                            Promise.all([
                                                                fetchAverage(id,1),
                                                                fetchAverage(id,2),
                                                                fetchAverage(id,3),
                                                                fetchAverage(id,4)
                                                            ]).then(function(arr){
                                                                var g1=arr[0]||0, g2=arr[1]||0, g3=arr[2]||0, g4=arr[3]||0;
                                                                var finalAvg = (g1+g2+g3+g4)/4;
                                                                updateMiniRow(id, finalAvg);
                                                            }).catch(function(){});
                                                        });
                                                    }
                                                    if(document.readyState === 'loading'){
                                                        document.addEventListener('DOMContentLoaded', refreshMiniTableGrades);
                                                    } else { refreshMiniTableGrades(); }
                                                })();
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End instructor dashboard content -->
                    </section>

                    <!-- Trainee Record Section -->
                    <section id="trainee" class="page-section">
                        <div class="section-header">
                            <h1 class="section-title">Trainee Records Management</h1>
                            <p class="section-description">Monitor student progress and grades</p>
                        </div>

                        <!-- Tab Container -->
                        <div class="tabs-container" id="traineeTabsContainer">
                            <!-- Tab Navigation -->
                            <div class="tabs-nav">
                                <div class="tabs-left">
                                    <button class="tab active" data-tab="grades">
                                        <i class="fas fa-chart-line"></i>
                                        <span>Grades</span>
                                    </button>
                                    <button class="tab" data-tab="courses">
                                        <i class="fas fa-book-open"></i>
                                        <span>Courses</span>
                                    </button>
                                    <button class="tab" data-tab="attendance">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Attendance</span>
                                    </button>
                                    <button class="tab" data-tab="quizzes">
                                        <i class="fas fa-question-circle"></i>
                                        <span>Quizzes</span>
                                    </button>
                                    <button class="tab" data-tab="exam">
                                        <i class="fas fa-clipboard-check"></i>
                                        <span>Exam</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Tab Content -->
                            <div class="tab-content">
                                <!-- Grades Tab Panel -->
                                <div class="tab-panel active" data-panel="grades">
                                    
                                    <div class="table-container modern-table-container">
                                        <div class="table-header">
                                            <h3 class="table-title">
                                                <i class="fas fa-graduation-cap me-2"></i>
                                                Course Overview
                                            </h3>
                                            <p class="table-subtitle">Manage and monitor student progress across all courses</p>
                                        </div>
                                        <div class="table-wrapper">
                                            <table class="table modern-table" id="gradesTable" role="table" aria-label="Courses overview">
                                            <caption class="visually-hidden">
                                                Courses table showing course name, student count, and actions
                                            </caption>
                                            <thead>
                                                <tr role="row">
                                                        <th scope="col" class="course-name-header">
                                                            <i class="fas fa-book me-2"></i>Course Name
                                                        </th>
                                                        <th scope="col" class="student-count-header">
                                                            <i class="fas fa-users me-2"></i>Student Count
                                                        </th>
                                                        <th scope="col" class="actions-header">
                                                            <i class="fas fa-cog me-2"></i>Actions
                                                        </th>
                                                </tr>
                                            </thead>
                                            <tbody id="gradesTableBody">
                                                <tr>
                                                        <td colspan="3" class="text-center loading-cell">
                                                        <div class="d-flex justify-content-center align-items-center">
                                                            <div class="spinner-border text-primary me-2" role="status">
                                                                <span class="visually-hidden">Loading...</span>
                                                            </div>
                                                                <span class="loading-text">Loading courses...</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            </table>
                                        </div>
                                    </div>
                                            <script>
                                                // Initialize student grade data
                                                window.studentGradeData = window.studentGradeData || {};
                                                
                                                // Load courses on page load
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    if (typeof loadCoursesView === 'function') {
                                                        loadCoursesView();
                                                    }
                                                });
                                            </script>
                                        </table>
                                    </div>
                                </div>

                                <!-- Courses Tab Panel -->
                                <div class="tab-panel" data-panel="courses" style="display: none;">
                                    <div class="table-container">
                                        <table class="table" id="coursesTable" role="table" aria-label="Courses overview">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Course</th>
                                                    <th scope="col">Progress</th>
                                                    <th scope="col">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Map canonical course names from DB to display metadata
                                                $courseMeta = [
                                                    'Automotive Servicing' => ['code' => 'ATS', 'label' => 'Automotive Servicing', 'badge' => ['#fde68a','#92400e']],
                                                    'Basic Computer Literacy' => ['code' => 'BCL', 'label' => 'Basic Computer Literacy', 'badge' => ['#bbf7d0','#065f46']],
                                                    'Beauty Care (Nail Care)' => ['code' => 'BEC', 'label' => 'Beauty Care (Nail Care)', 'badge' => ['#fecaca','#7f1d1d']],
                                                    'Bread and Pastry Production' => ['code' => 'BPP', 'label' => 'Bread and Pastry Production', 'badge' => ['#fde68a','#92400e']],
                                                    'Computer Systems Servicing' => ['code' => 'CSS', 'label' => 'Computer Systems Servicing', 'badge' => ['#ddd6fe','#4c1d95']],
                                                    'Dressmaking' => ['code' => 'DRM', 'label' => 'Dressmaking', 'badge' => ['#fbcfe8','#831843']],
                                                    'Electrical Installation and Maintenance' => ['code' => 'EIM', 'label' => 'Electrical Installation and Maintenance', 'badge' => ['#bae6fd','#075985']],
                                                    'Electronic Products and Assembly Servicing' => ['code' => 'EPAS', 'label' => 'Electronic Products and Assembly Servicing', 'badge' => ['#bbf7d0','#065f46']],
                                                    'Events Management Services' => ['code' => 'EVM', 'label' => 'Events Management Services', 'badge' => ['#e9d5ff','#6d28d9']],
                                                    'Food and Beverage Services' => ['code' => 'FBS', 'label' => 'Food and Beverage Services', 'badge' => ['#fee2e2','#991b1b']],
                                                    'Food Processing' => ['code' => 'FOP', 'label' => 'Food Processing', 'badge' => ['#cffafe','#164e63']],
                                                    'Hairdressing' => ['code' => 'HDR', 'label' => 'Hairdressing', 'badge' => ['#fde68a','#92400e']],
                                                    'Housekeeping' => ['code' => 'HSK', 'label' => 'Housekeeping', 'badge' => ['#fef9c3','#78350f']],
                                                    'Massage Therapy' => ['code' => 'MAT', 'label' => 'Massage Therapy', 'badge' => ['#fde68a','#92400e']],
                                                    'RAC Servicing' => ['code' => 'RAC', 'label' => 'RAC Servicing', 'badge' => ['#bfdbfe','#1e3a8a']],
                                                    'Shielded Metal Arc Welding' => ['code' => 'SMAW', 'label' => 'Shielded Metal Arc Welding', 'badge' => ['#fecaca','#7f1d1d']],
                                                ];

                                                // Render only the instructor's current course; if none, show a friendly placeholder
                                                if (!empty($instructorCourse)) {
                                                    $meta = $courseMeta[$instructorCourse] ?? null;
                                                    $badgeBg = $meta ? $meta['badge'][0] : '#e5e7eb';
                                                    $badgeFg = $meta ? $meta['badge'][1] : '#111827';
                                                    $code = $meta['code'] ?? strtoupper(substr($instructorCourse, 0, 3));
                                                    $label = $meta['label'] ?? strtoupper($instructorCourse);
                                                ?>
                                                <tr class="clickable-row course-row" onclick="viewCourseModules('<?php echo htmlspecialchars($instructorCourse, ENT_QUOTES, 'UTF-8'); ?>')">
                                                    <td>
                                                        <div class="d-flex align-items-start gap-2">
                                                            <span class="badge rounded-pill" style="background:<?php echo $badgeBg; ?>;color:<?php echo $badgeFg; ?>;"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-semibold"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <small class="text-muted">Click to view modules</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="min-width:200px;">
                                                        <div class="d-flex flex-column align-items-start">
                                                            <div class="w-100 d-flex justify-content-end mb-1">
                                                                <small class="text-muted">0%</small>
                                                            </div>
                                                            <div class="progress w-100" style="height:6px;">
                                                                <div class="progress-bar bg-danger" style="width:0%"></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge rounded-pill bg-danger-subtle text-danger">• In Progress</span>
                                                    </td>
                                                </tr>
                                                <?php } else { ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">No course assigned</td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php
                                    // Modules per course (display only for current instructor course)
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

                                    if (!empty($instructorCourse)) {
                                        $modules = $courseModules[$instructorCourse] ?? [];
                                    ?>
                                    <div class="table-container" style="margin-top: 16px;">
                                        <table class="table" role="table" aria-label="Modules for course">
                                            <caption class="visually-hidden">Modules for the current course</caption>
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="width:80px;">Module #</th>
                                                    <th scope="col">Title</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($modules)) { $i = 1; foreach ($modules as $mod) { ?>
                                                    <tr>
                                                        <td class="text-center"><?php echo $i++; ?></td>
                                                        <td><?php echo htmlspecialchars($mod, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    </tr>
                                                <?php } } else { ?>
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted">No modules listed yet for this course.</td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php } ?>
                                </div>

                                <!-- Attendance Tab Panel -->
                                <div class="tab-panel" data-panel="attendance" style="display: none;">
                                    <div class="attendance-container">
                                        <!-- Enhanced Course Selection -->
                                        <div class="attendance-course-selector">
                                            <div class="course-selector-header">
                                                <h3 class="attendance-course-title">
                                                    <i class="fas fa-graduation-cap"></i>
                                                    Select Course for Attendance
                                                </h3>
                                                <div class="course-selector-actions">
                                                    <div class="search-container">
                                                        <div class="search-box">
                                                            <i class="fas fa-search search-icon"></i>
                                                            <input type="text" id="courseSearchInput" placeholder="Search courses..." class="search-input">
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-outline-primary btn-sm" id="refreshCoursesBtn">
                                                        <i class="fas fa-sync-alt"></i> Refresh
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Compact Course Cards -->
                                            <div class="course-cards-container" id="courseCardsContainer">
                                                <div class="course-card loading-card">
                                                    <div class="loading-spinner">
                                                        <i class="fas fa-spinner fa-spin"></i>
                                                        <span>Loading courses...</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Attendance Content (Hidden initially) -->
                                        <div class="attendance-content" id="attendanceContent" style="display: none;">
                                            <!-- Compact Header -->
                                            <div class="attendance-header-compact">
                                                <div class="header-left">
                                                    <button class="btn btn-outline-secondary btn-sm" onclick="backToAttendanceCourses()">
                                                        <i class="fas fa-arrow-left"></i> Back
                                                    </button>
                                                    <div class="course-info">
                                                        <h4 class="course-name" id="selectedCourseName">Course Name</h4>
                                                        <span class="course-stats" id="courseStats">0 students</span>
                                                    </div>
                                                </div>
                                                <div class="header-right">
                                                    <div class="date-selector">
                                                        <label for="attendanceDate" class="date-label">Date:</label>
                                                        <input type="date" id="attendanceDate" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                                                    </div>
                                                    <button class="btn btn-primary btn-sm" id="exportAttendanceBtn">
                                                        <i class="fas fa-download"></i> Export
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Batch Selection -->
                                            <div class="batch-selector">
                                                <h4 class="batch-title">Select Batch</h4>
                                                <div class="batch-tabs">
                                                    <button class="batch-tab active" data-batch="1" onclick="selectBatch(1)">
                                                        <span class="batch-label">Batch 1</span>
                                                        <span class="batch-period">January - March</span>
                                                    </button>
                                                    <button class="batch-tab" data-batch="2" onclick="selectBatch(2)">
                                                        <span class="batch-label">Batch 2</span>
                                                        <span class="batch-period">April - June</span>
                                                    </button>
                                                    <button class="batch-tab" data-batch="3" onclick="selectBatch(3)">
                                                        <span class="batch-label">Batch 3</span>
                                                        <span class="batch-period">July - September</span>
                                                    </button>
                                                    <button class="batch-tab" data-batch="4" onclick="selectBatch(4)">
                                                        <span class="batch-label">Batch 4</span>
                                                        <span class="batch-period">October - December</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Quick Stats -->
                                            <div class="attendance-quick-stats">
                                                <div class="stat-item present">
                                                    <i class="fas fa-check-circle"></i>
                                                    <span class="stat-number" id="totalPresent">0</span>
                                                    <span class="stat-label">Present</span>
                                                </div>
                                                <div class="stat-item absent">
                                                    <i class="fas fa-times-circle"></i>
                                                    <span class="stat-number" id="totalAbsent">0</span>
                                                    <span class="stat-label">Absent</span>
                                                </div>
                                                <div class="stat-item total">
                                                    <i class="fas fa-users"></i>
                                                    <span class="stat-number" id="totalStudents">0</span>
                                                    <span class="stat-label">Total</span>
                                                </div>
                                                <div class="stat-item rate">
                                                    <i class="fas fa-percentage"></i>
                                                    <span class="stat-number" id="attendanceRate">0%</span>
                                                    <span class="stat-label">Rate</span>
                                                </div>
                                            </div>

                                            <!-- Compact Attendance Table -->
                                            <div class="attendance-table-container">
                                                <div class="table-header-actions">
                                                    <div class="bulk-actions">
                                                        <button class="btn btn-success btn-sm" id="markAllPresentBtn">
                                                            <i class="fas fa-check"></i> Mark All Present
                                                        </button>
                                                        <button class="btn btn-warning btn-sm" id="markAllAbsentBtn">
                                                            <i class="fas fa-times"></i> Mark All Absent
                                                        </button>
                                                    </div>
                                                    <div class="table-info">
                                                        <span id="attendanceTableInfo">Select a course to view students</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="table-wrapper">
                                                    <table class="table attendance-table" id="attendanceTable" role="table" aria-label="Student attendance tracking">
                                                        <thead>
                                                            <tr>
                                                                <th scope="col" class="col-checkbox">
                                                                    <input type="checkbox" id="selectAllStudents" title="Select All">
                                                                </th>
                                                                <th scope="col" class="col-id">Student ID</th>
                                                                <th scope="col" class="col-name">Student Name</th>
                                                                <th scope="col" class="col-present">Present Days</th>
                                                                <th scope="col" class="col-absent">Absent Days</th>
                                                                <th scope="col" class="col-rate">Rate</th>
                                                                <th scope="col" class="col-actions">Mark Attendance</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="attendanceTableBody">
                                                            <tr class="no-data-row">
                                                                <td colspan="7" class="text-center">
                                                                    <div class="no-data-message">
                                                                        <i class="fas fa-users"></i>
                                                                        <p>No students found in this course</p>
                                                                        <small>Select a course to view student attendance</small>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    
                                </div>

                                <!-- Quizzes Tab Panel -->
                                <div class="tab-panel" data-panel="quizzes" style="display: none;">
                                    <div class="quizzes-container">
                                        <!-- Course Selection for Quizzes -->
                                        <div class="quizzes-course-selector">
                                            <h3 class="quizzes-course-title">Select Course for Quizzes</h3>
                                            <div class="table-container">
                                                <table class="table" id="quizzesCoursesTable" role="table" aria-label="Courses for quizzes">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Course Name</th>
                                                            <th scope="col">Student Count</th>
                                                            <th scope="col">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="quizzesCoursesTableBody">
                                                        <tr>
                                                            <td colspan="3" class="text-center">
                                                                <div class="d-flex justify-content-center align-items-center">
                                                                    <div class="spinner-border text-primary me-2" role="status">
                                                                        <span class="visually-hidden">Loading...</span>
                                                                    </div>
                                                                    Loading courses...
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Quizzes Content (Hidden initially) -->
                                        <div class="quizzes-content" id="quizzesContent" style="display: none;">
                                            <!-- Back to Courses Button -->
                                            <div class="mb-3">
                                                <button class="btn btn-secondary" onclick="backToQuizzesCourses()">
                                                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                                                </button>
                                                <h4 class="d-inline-block ms-3">Quizzes for: <span class="text-primary" id="selectedQuizzesCourseName">Course Name</span></h4>
                                            </div>

                                            <!-- Quiz Management Content -->
                                            <div class="quiz-management">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h5>Quiz Management</h5>
                                                    <button class="btn btn-primary" onclick="createNewQuiz()">
                                                        <i class="fas fa-plus me-2"></i>Create New Quiz
                                                    </button>
                                                </div>
                                                
                                                <!-- Quiz List -->
                                                <div class="table-container">
                                                    <table class="table" id="quizzesListTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Quiz Title</th>
                                                                <th>Date Created</th>
                                                                <th>Status</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="quizzesListTableBody">
                                                            <tr>
                                                                <td colspan="4" class="text-center text-muted">No quizzes created yet</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <!-- Quiz Submissions -->
                                                <div class="quiz-submissions mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <h5>Quiz Submissions</h5>
                                <div id="courseFilterDisplay" class="d-none">
                                    <span class="badge bg-primary">
                                        <i class="fas fa-filter me-1"></i>
                                        <span id="currentCourseFilter">All Courses</span>
                                    </span>
                                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="loadQuizSubmissions()" title="Show all courses">
                                        <i class="fas fa-times me-1"></i>Clear Filter
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-success" onclick="syncAllQuizGrades()" title="Sync all quiz grades to Grade 1">
                                    <i class="fas fa-magic me-2"></i>Sync All Grades
                                </button>
                                <button class="btn btn-outline-primary" onclick="refreshQuizSubmissions()">
                                    <i class="fas fa-refresh me-2"></i>Refresh
                                </button>
                            </div>
                        </div>
                                                    
                                                    <div class="table-container">
                                                        <table class="table" id="quizSubmissionsTable">
                                                            <thead>
                                                                <tr>
                                                                    <th>Student</th>
                                                                    <th>Quiz Title</th>
                                                                    <th>Score</th>
                                                                    <th>Submitted</th>
                                                                    <th>Status</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="quizSubmissionsTableBody">
                                                                <tr>
                                                                    <td colspan="6" class="text-center text-muted">No submissions yet</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Exam Tab Panel -->
                                <div class="tab-panel" data-panel="exam" style="display: none;">
                                    <div class="exam-container">
                                        <!-- Course Selection for Exams -->
                                        <div class="exam-course-selector">
                                            <h3 class="exam-course-title">Select Course for Exams</h3>
                                            <div class="table-container">
                                                <table class="table" id="examCoursesTable" role="table" aria-label="Courses for exams">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Course Name</th>
                                                            <th scope="col">Student Count</th>
                                                            <th scope="col">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="examCoursesTableBody">
                                                        <tr>
                                                            <td colspan="3" class="text-center">
                                                                <div class="d-flex justify-content-center align-items-center">
                                                                    <div class="spinner-border text-primary me-2" role="status">
                                                                        <span class="visually-hidden">Loading...</span>
                                                                    </div>
                                                                    Loading courses...
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Exam Content (Hidden initially) -->
                                        <div class="exam-content" id="examContent" style="display: none;">
                                            <!-- Back to Courses Button -->
                                            <div class="mb-3">
                                                <button class="btn btn-secondary" onclick="backToExamCourses()">
                                                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                                                </button>
                                                <h4 class="d-inline-block ms-3">Exams for: <span class="text-primary" id="selectedExamCourseName">Course Name</span></h4>
                                            </div>

                                            <!-- Exam Management Content -->
                                            <div class="exam-management">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h5>Exam Management</h5>
                                                    <button class="btn btn-primary" onclick="createNewExam()">
                                                        <i class="fas fa-plus me-2"></i>Create New Exam
                                                    </button>
                                                </div>
                                                
                                                <!-- Exam List -->
                                                <div class="table-container">
                                                    <table class="table" id="examListTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Exam Title</th>
                                                                <th>Date Created</th>
                                                                <th>Status</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="examListTableBody">
                                                            <tr>
                                                                <td colspan="4" class="text-center text-muted">No exams created yet</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <!-- Exam Submissions -->
                                                <div class="exam-submissions mt-4">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h5>Exam Submissions</h5>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-outline-success" onclick="syncAllExamGrades()" title="Sync all exam grades to Grade 1">
                                                                <i class="fas fa-magic me-2"></i>Sync All Grades
                                                            </button>
                                                            <button class="btn btn-outline-primary" onclick="loadExamSubmissions()">
                                                                <i class="fas fa-refresh me-2"></i>Refresh
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="table-container">
                                                        <table class="table" id="examSubmissionsTable">
                                                            <thead>
                                                                <tr>
                                                                    <th>Student</th>
                                                                    <th>Exam Title</th>
                                                                    <th>Score</th>
                                                                    <th>Submitted</th>
                                                                    <th>Status</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="examSubmissionsTableBody">
                                                                <tr>
                                                                    <td colspan="6" class="text-center text-muted">No submissions yet</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </section>

                <!-- Quiz Creation Popup Modal -->
                <div id="quizCreationModal" class="modal-overlay" style="display: none;">
                    <div class="modal-container quiz-modal gforms-style">
                        <!-- Header -->
                        <div class="gforms-header">
                            <div class="gforms-title-section">
                                <input type="text" id="quizTitle" class="gforms-title-input" placeholder="Untitled Quiz" value="Untitled Quiz">
                                <input type="text" id="quizDescription" class="gforms-description-input" placeholder="Quiz description">
                            </div>
                            <div class="gforms-header-actions">
                                <button class="gforms-btn gforms-btn-secondary" onclick="closeQuizModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="gforms-content">
                            <!-- Question Builder -->
                            <div class="gforms-question-builder" id="quizQuestionBuilder">
                                <!-- Questions will be dynamically added here -->
                            </div>

                            <!-- Add Question Button -->
                            <div class="gforms-add-question">
                                <button class="gforms-add-btn" onclick="addQuizQuestion()">
                                    <i class="fas fa-plus"></i>
                                    Add Question
                                </button>
                            </div>
                        </div>

                        <!-- Sidebar Tools -->
                        <div class="gforms-sidebar">
                            <div class="gforms-toolbar">
                                <button class="gforms-tool-btn" onclick="addQuizQuestion()" title="Add Question">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="importQuestions()" title="Import Questions">
                                    <i class="fas fa-file-import"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addTitleDescription()" title="Add Title and Description">
                                    <i class="fas fa-heading"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addImage()" title="Add Image">
                                    <i class="fas fa-image"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addVideo()" title="Add Video">
                                    <i class="fas fa-video"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addSection()" title="Add Section">
                                    <i class="fas fa-layer-group"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="gforms-footer">
                            <div class="gforms-footer-left">
                                <button class="gforms-btn gforms-btn-outline" onclick="previewQuiz()">
                                    <i class="fas fa-eye me-2"></i>Preview
                                </button>
                            </div>
                            <div class="gforms-footer-right">
                                <button class="gforms-btn gforms-btn-secondary" onclick="closeQuizModal()">
                                    Cancel
                                </button>
                                <button class="gforms-btn gforms-btn-primary" onclick="saveQuiz()">
                                    <i class="fas fa-save me-2"></i>Save Quiz
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quiz Save Confirmation Popup -->
                <div id="quizSaveConfirmationModal" class="quiz-save-confirmation-overlay">
                    <div class="quiz-save-confirmation-popup">
                        <div class="quiz-save-confirmation-header">
                            <div class="quiz-save-confirmation-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <h3 class="quiz-save-confirmation-title">Quiz Saved Successfully!</h3>
                            <p class="quiz-save-confirmation-subtitle">Your quiz has been created and is ready to use</p>
                        </div>
                        <div class="quiz-save-confirmation-body">
                            <div class="quiz-save-confirmation-details">
                                <div class="quiz-save-confirmation-detail-item">
                                    <span class="quiz-save-confirmation-detail-label">Quiz Title:</span>
                                    <span class="quiz-save-confirmation-detail-value" id="confirmationQuizTitle">-</span>
                                </div>
                                <div class="quiz-save-confirmation-detail-item">
                                    <span class="quiz-save-confirmation-detail-label">Questions:</span>
                                    <span class="quiz-save-confirmation-detail-value" id="confirmationQuestionCount">-</span>
                                </div>
                                <div class="quiz-save-confirmation-detail-item">
                                    <span class="quiz-save-confirmation-detail-label">Status:</span>
                                    <span class="quiz-save-confirmation-detail-value">Draft</span>
                                </div>
                            </div>
                            <div class="quiz-save-confirmation-actions">
                                <button class="quiz-save-confirmation-btn quiz-save-confirmation-btn-primary" onclick="closeQuizSaveConfirmation()">
                                    <i class="fas fa-check"></i>
                                    Continue
                                </button>
                                <button class="quiz-save-confirmation-btn quiz-save-confirmation-btn-secondary" onclick="viewQuizList()">
                                    <i class="fas fa-list"></i>
                                    View Quizzes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Save Confirmation Popup -->
                <div id="examSaveConfirmationModal" class="exam-save-confirmation-overlay">
                    <div class="exam-save-confirmation-popup">
                        <div class="exam-save-confirmation-header">
                            <div class="exam-save-confirmation-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <h3 class="exam-save-confirmation-title">Exam Saved Successfully!</h3>
                            <p class="exam-save-confirmation-subtitle">Your exam has been created and is ready to use</p>
                        </div>
                        <div class="exam-save-confirmation-body">
                            <div class="exam-save-confirmation-details">
                                <div class="exam-save-confirmation-detail-item">
                                    <span class="exam-save-confirmation-detail-label">Exam Title:</span>
                                    <span class="exam-save-confirmation-detail-value" id="confirmationExamTitle">-</span>
                                </div>
                                <div class="exam-save-confirmation-detail-item">
                                    <span class="exam-save-confirmation-detail-label">Course:</span>
                                    <span class="exam-save-confirmation-detail-value" id="confirmationExamCourse">-</span>
                                </div>
                                <div class="exam-save-confirmation-detail-item">
                                    <span class="exam-save-confirmation-detail-label">Status:</span>
                                    <span class="exam-save-confirmation-detail-value">Draft</span>
                                </div>
                            </div>
                            <div class="exam-save-confirmation-actions">
                                <button class="exam-save-confirmation-btn exam-save-confirmation-btn-primary" onclick="closeExamSaveConfirmation()">
                                    <i class="fas fa-check"></i>
                                    Continue
                                </button>
                                <button class="exam-save-confirmation-btn exam-save-confirmation-btn-secondary" onclick="viewExamList()">
                                    <i class="fas fa-list"></i>
                                    View Exams
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quiz Delete Confirmation Popup -->
                <div id="quizDeleteConfirmationModal" class="quiz-delete-confirmation-overlay">
                    <div class="quiz-delete-confirmation-popup">
                        <div class="quiz-delete-confirmation-header">
                            <div class="quiz-delete-confirmation-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h3 class="quiz-delete-confirmation-title">Delete Quiz</h3>
                            <p class="quiz-delete-confirmation-subtitle">This action cannot be undone</p>
                        </div>
                        <div class="quiz-delete-confirmation-body">
                            <div class="quiz-delete-confirmation-message">
                                <strong>Are you sure you want to delete this quiz?</strong><br>
                                This action cannot be undone and will permanently remove the quiz and all its questions.
                            </div>
                            <div class="quiz-delete-confirmation-warning">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>This action is irreversible</span>
                            </div>
                            <div class="quiz-delete-confirmation-actions">
                                <button class="quiz-delete-confirmation-btn quiz-delete-confirmation-btn-danger" onclick="confirmDeleteQuiz()">
                                    <i class="fas fa-trash"></i>
                                    Delete Quiz
                                </button>
                                <button class="quiz-delete-confirmation-btn quiz-delete-confirmation-btn-secondary" onclick="closeQuizDeleteConfirmation()">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Delete Confirmation Popup -->
                <div id="examDeleteConfirmationModal" class="exam-delete-confirmation-overlay">
                    <div class="exam-delete-confirmation-popup">
                        <div class="exam-delete-confirmation-header">
                            <div class="exam-delete-confirmation-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h3 class="exam-delete-confirmation-title">Delete Exam</h3>
                            <p class="exam-delete-confirmation-subtitle">This action cannot be undone</p>
                        </div>
                        <div class="exam-delete-confirmation-body">
                            <div class="exam-delete-confirmation-message">
                                <strong>Are you sure you want to delete this exam?</strong><br>
                                This action cannot be undone and will permanently remove the exam and all its questions.
                            </div>
                            <div class="exam-delete-confirmation-warning">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>This action is irreversible</span>
                            </div>
                            <div class="exam-delete-confirmation-actions">
                                <button class="exam-delete-confirmation-btn exam-delete-confirmation-btn-danger" onclick="confirmDeleteExam()">
                                    <i class="fas fa-trash"></i>
                                    Delete Exam
                                </button>
                                <button class="exam-delete-confirmation-btn exam-delete-confirmation-btn-secondary" onclick="closeExamDeleteConfirmation()">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quiz Delete Success Confirmation Popup -->
                <div id="quizDeleteSuccessModal" class="quiz-delete-success-overlay">
                    <div class="quiz-delete-success-popup">
                        <div class="quiz-delete-success-header">
                            <div class="quiz-delete-success-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="quiz-delete-success-title">Quiz Deleted Successfully!</h3>
                            <p class="quiz-delete-success-subtitle">The quiz has been permanently removed</p>
                        </div>
                        <div class="quiz-delete-success-body">
                            <div class="quiz-delete-success-message">
                                <strong>The quiz has been successfully deleted.</strong><br>
                                All questions and related data have been permanently removed from the system.
                            </div>
                            <div class="quiz-delete-success-info">
                                <i class="fas fa-info-circle"></i>
                                <span>Quiz list has been updated</span>
                            </div>
                            <div class="quiz-delete-success-actions">
                                <button class="quiz-delete-success-btn quiz-delete-success-btn-primary" onclick="closeQuizDeleteSuccess()">
                                    <i class="fas fa-check"></i>
                                    Continue
                                </button>
                                <button class="quiz-delete-success-btn quiz-delete-success-btn-secondary" onclick="viewQuizListFromDelete()">
                                    <i class="fas fa-list"></i>
                                    View Quizzes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Creation Popup Modal -->
                <div id="examCreationModal" class="modal-overlay" style="display: none;">
                    <div class="modal-container exam-modal gforms-style">
                        <!-- Header -->
                        <div class="gforms-header">
                            <div class="gforms-title-section">
                                <input type="text" id="examTitle" class="gforms-title-input" placeholder="Untitled Exam" value="Untitled Exam">
                                <input type="text" id="examDescription" class="gforms-description-input" placeholder="Exam description">
                            </div>
                            <div class="gforms-header-actions">
                                <button class="gforms-btn gforms-btn-secondary" onclick="closeExamModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="gforms-content">
                            <!-- Question Builder -->
                            <div class="gforms-question-builder" id="examQuestionBuilder">
                                <!-- Questions will be dynamically added here -->
                            </div>

                            <!-- Add Question Button -->
                            <div class="gforms-add-question">
                                <button class="gforms-add-btn" onclick="addExamQuestion()">
                                    <i class="fas fa-plus"></i>
                                    Add Question
                                </button>
                            </div>
                        </div>

                        <!-- Sidebar Tools -->
                        <div class="gforms-sidebar">
                            <div class="gforms-toolbar">
                                <button class="gforms-tool-btn" onclick="addExamQuestion()" title="Add Question">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="importQuestions()" title="Import Questions">
                                    <i class="fas fa-file-import"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addTitleDescription()" title="Add Title and Description">
                                    <i class="fas fa-heading"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addImage()" title="Add Image">
                                    <i class="fas fa-image"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addVideo()" title="Add Video">
                                    <i class="fas fa-video"></i>
                                </button>
                                <button class="gforms-tool-btn" onclick="addSection()" title="Add Section">
                                    <i class="fas fa-layer-group"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="gforms-footer">
                            <div class="gforms-footer-left">
                                <button class="gforms-btn gforms-btn-outline" onclick="previewExam()">
                                    <i class="fas fa-eye me-2"></i>Preview
                                </button>
                            </div>
                            <div class="gforms-footer-right">
                                <button class="gforms-btn gforms-btn-secondary" onclick="closeExamModal()">
                                    Cancel
                                </button>
                                <button class="gforms-btn gforms-btn-primary" onclick="saveExam()">
                                    <i class="fas fa-save me-2"></i>Save Exam
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Job Matching Section -->
                    <section id="job-matching" class="page-section">
                        <div class="content-area">
                            <div class="section-header">
                                <h2 class="section-title">Job Matching</h2>
                                <p class="section-description">Available job opportunities</p>
                            </div>

                            <!-- Filters -->
                            <div class="filters-container">
                                <select class="filter-select" id="instructorLocationFilter" name="location">
                                    <option value="">All Locations</option>
                                    <!-- Options will be populated dynamically based on available jobs -->
                                </select>
                                <select class="filter-select" id="instructorExperienceFilter" name="experience">
                                    <option value="">All Experience Levels</option>
                                    <!-- Options will be populated dynamically based on available jobs -->
                                </select>
                                <button class="refresh-filters-btn" id="instructorRefreshJobsBtn" title="Refresh jobs">
                                    <i class="fas fa-rotate-right" aria-hidden="true"></i>
                                    <span>Refresh</span>
                                </button>
                            </div>

                            <!-- Job Count and Cards -->
                            <div class="job-results-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <div class="job-count" style="color: #666; font-size: 0.9rem;">Loading jobs...</div>
                            </div>
                            <div class="job-cards-grid" id="instructorJobCardsGrid">
                                <!-- Job cards will be populated dynamically -->
                            </div>
                        </div>
                    </section>

                    <!-- Copied from admin_dashboard: Career Analytics Section (UI/UX, graphs, and functionality mirrored exactly) -->
                    <section class="page-section" id="career">
                        <div class="content-area">
                            <div class="section-header">
                                <h2 class="section-title">Career Analytics</h2>
                                <p class="section-description">Comprehensive analytics on trainee career progression and
                                    employment outcomes</p>
                            </div>

                        <div class="filters-container" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <select class="filter-select" id="adminAnalyticsCourseSelect">
                                <option value="__ALL__">All Courses</option>
                            </select>
                            <span id="adminAnalyticsInfo" style="opacity:0.8;font-size:0.9rem"></span>
                        </div>

    <?php
        // Compute career analytics (Total Graduates and YoY trend) from CSV
        $careerAnalytics = [
            'totalGraduates' => 0,
            'trendText' => ''
        ];
        $csvPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'Graduates_.csv';
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
                        $latest = $perYear[$latestYear];
                        $prev = $perYear[$prevYear];
                        if ($prev > 0) {
                            $change = (($latest - $prev) / $prev) * 100;
                            $careerAnalytics['trendText'] = ($change > 0 ? '+' : '') . round($change, 1) . '% from ' . $prevYear;
                        }
                    }
                }
            }
        }

        // Employment Rate Prediction (memory-efficient) from data/mmtvtc_employment_rates.csv
        $employmentData = null;
        $overallEmploymentRate = 89; // Default fallback
        $employmentTrend = '+5% from last month'; // Default fallback
        $employmentCsv = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mmtvtc_employment_rates.csv';
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

                        <!-- Analytics Cards -->
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
                                        <i class="fas a-check-circle"></i>
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

                        <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                        </div>

                        <!-- 2025 Course Popularity (CSV-driven) -->
                        <div class="analytics-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
                                <h3 class="analytics-label" style="margin-bottom:8px;">Course Popularity for Year 2025 (Enrollment)</h3>
                                <p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">This visualization shows the Course Popularity for Year 2025 indicating which courses is the most popular down to the least most popular. The bar chart shows the total number of students who signed up for each course in 2025. It shows that Shielded Metal Arc Welding and Dressmaking NC II are the most popular courses.</p>
                                <canvas id="coursePopularity2025Bar" class="chart-canvas" style="flex: 1; width: 100%;"></canvas>
                            </div>
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
                                <h3 class="analytics-label" style="margin-bottom:8px;">Top 10 Distribution (2025)</h3>
                                <p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">The pie chart, on the other hand, shows how the top 10 courses are split up by percentage. This gives a quick look at what students like in different vocational fields.</p>
                                <canvas id="coursePopularity2025Pie" class="chart-canvas" style="flex: 1; width: 100%;"></canvas>
                            </div>
                            <div class="analytics-card" style="padding:16px;grid-column:1 / -1; display: flex; flex-direction: column;">
                                <h3 class="analytics-label" style="margin-bottom:8px;">Summary (2025)</h3>
                                <p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">Summarizes the most popular course and also the least popular courses in detailed and with also offering a total courses offered and total students enrolled in 2025.</p>
                                <div id="coursePopularity2025Summary" style="font-size:0.95rem; line-height:1.5; flex: 1; width: 100%;"></div>
                            </div>
                        </div>


    <?php
        // Industry employment prediction (conservative, memory-friendly) from data/industry_data.csv
        // Output: window.__industryBarData { title, year, labels[], values[] } and a single chart below
        $industryBarData = null;
        $industryCsv = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'industry_data.csv';
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
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
                                <h3 class="analytics-label" style="margin-bottom:8px;">Top 10 Companies absorbing MMTVTC Graduates (2025)</h3>
                                <p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">These bar charts shows how the system keeps track of and analyzes hiring trends across different agencies and industries to find out which companies always hire MMTVTC graduates.</p>
                                <canvas id="industryEmploymentChart" class="chart-canvas" style="flex: 1; width: 100%;"></canvas>
                            </div>
                        </div>

                        <script>
                        window.__industryBarData = <?php echo json_encode($industryBarData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                        </script>
                        <?php } else { ?>
                        <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
                                <h3 class="analytics-label" style="margin-bottom:8px;">Top 10 Companies absorbing MMTVTC Graduates (2025)</h3>
                                <div style="opacity:0.8; flex: 1; width: 100%;">Upload <code>data/industry_data.csv</code> to display this chart.</div>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if (is_array($employmentData) && !empty($employmentData['predictions'])) { ?>
                        <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
                                <h3 class="analytics-label" style="margin-bottom:8px;">Course Effectiveness through Employment Rate (2025)</h3>
                                <p style="margin-bottom:12px; font-size:0.9rem; color:#666; line-height:1.4;">This section describes how the system helps MMTVTC use quantifiable data on course effectiveness and graduate employability to improve its program structures and course offerings. It compares the expected employment rates for different training programs to show the course effectiveness.</p>
                                <canvas id="employmentRateChart" class="chart-canvas" style="flex: 1; width: 100%;"></canvas>
                            </div>
                        </div>

                        <script>
                        window.__employmentData = <?php echo json_encode($employmentData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                        </script>
                        <script>
                        // Set instructor's course for quiz submissions filtering
                        window.__instructorCourse = <?php echo json_encode($instructorCourse ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                        </script>
                        <?php } else { ?>
                        <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
                                <h3 class="analytics-label" style="margin-bottom:8px;">Course Effectiveness through Employment Rate (2025)</h3>
                                <div style="opacity:0.8; flex: 1; width: 100%;">Upload <code>data/mmtvtc_employment_rates.csv</code> to display this chart.</div>
                            </div>
                        </div>
                        <?php } ?>

                        <!-- Employment Rate Trend Analysis -->
                        <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
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
                                <canvas id="employmentTrendAnalysisChart" class="chart-canvas" style="flex: 1; width: 100%;"></canvas>
                            </div>
                        </div>

                        <!-- Course Trends Visualization -->
                        <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
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
                                <canvas id="courseTrendsChart" class="chart-canvas" style="flex: 1; width: 100%;"></canvas>
                            </div>
                        </div>

                        <!-- Job Trends Visualization -->
                        <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
                            <div class="analytics-card" style="padding:16px; display: flex; flex-direction: column;">
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
                                <canvas id="jobTrendsChart" class="chart-canvas" style="flex: 1; width: 100%;"></canvas>
                            </div>
                        </div>
                        </div>
                    </section>

                    <!-- About Section -->
                    <section class="page-section" id="about">
                        <!-- Hero Section -->
                        <div class="about-hero-modern">
                            <div class="hero-background">
                                <div class="hero-pattern"></div>
                                <div class="hero-gradient"></div>
                            </div>
                            <div class="hero-content">
                                <div class="hero-logo-container">
                                    <img src="../images/logo.png" alt="MMTVTC Logo" class="hero-logo">
                                    <div class="hero-logo-rings">
                                        <div class="ring ring-1"></div>
                                        <div class="ring ring-2"></div>
                                    </div>
                                </div>
                                <h1 class="hero-title">
                                    <span class="title-main">MMTVTC</span>
                                    <span class="title-sub">Manpower Mandaluyong and Technical Vocational Training
                                        Center</span>
                                </h1>
                                <p class="hero-tagline">Empowering Futures Through Excellence in Technical Education</p>
                            </div>
                        </div>

                        <!-- Vision & Mission Section -->
                        <div class="vision-mission-section">
                            <div class="section-container">
                                <!-- Vision Quote -->
                                <div class="vision-card">
                                    <div class="card-header vision-header">
                                        <div class="header-icon">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                        <h2 class="card-title">Our Vision</h2>
                                    </div>
                                    <div class="card-content">
                                        <p class="vision-quote">
                                            "TO BE THE CENTER OF WHOLE LEARNING EXPERIENCE FOR GREAT ADVANCEMENT."
                                        </p>
                                    </div>
                                </div>

                                <!-- Mission Statement -->
                                <div class="mission-card">
                                    <div class="card-header mission-header">
                                        <div class="header-icon">
                                            <i class="fas fa-compass"></i>
                                        </div>
                                        <h2 class="card-title">Our Mission</h2>
                                    </div>
                                    <div class="card-content">
                                        <p class="mission-statement">
                                            "WE, THE MMTVTC FAMILY, WORKING AS A COMMUNITY, COMMIT OURSELVES TO PROMOTE
                                            LIFELONG TECHNICAL - VOCATIONAL TRAINING EXPERIENCE TO DEVELOP PRACTICAL LIFE
                                            SKILLS FOR GREAT ADVANCEMENT."
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Section -->
                        <div class="contact-section-modern">
                            <div class="contact-container">
                                <div class="contact-header">
                                    <h3 class="contact-title-modern">Connect With Us</h3>
                                    <p class="contact-description-modern">Join our community and stay updated with the
                                        latest programs and opportunities</p>
                                </div>

                                <div class="contact-actions">
                                    <a href="https://www.facebook.com/manpowermanda.tesda/" target="_blank"
                                        class="contact-btn-modern primary">
                                        <div class="btn-icon">
                                            <i class="fab fa-facebook-f"></i>
                                        </div>
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
        </div>

        <!-- Grade Edit Modal -->
        <div id="gradeModal" class="grade-modal-overlay">
            <div class="grade-modal-content">
                <!-- Modal Header -->
                <div class="grade-modal-header">
                    <h2 class="grade-modal-title" id="gradeModalTitle">
                        <i class="fas fa-chart-line"></i>
                        Grade Management
                    </h2>
                    <button class="grade-modal-close" id="gradeModalClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Modal Body with Tabs -->
                <div class="grade-modal-body">
                    <!-- Tab Navigation -->
                    <div class="grade-modal-tabs">
                        <div class="grade-modal-tab-nav">
                            <button class="grade-modal-tab active" data-tab="view-grades">
                                <i class="fas fa-eye"></i>
                                <span>View Grades</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="grade-modal-tab-content">
                        <!-- View Grades Tab Panel -->
                        <div class="grade-modal-tab-panel active" data-panel="view-grades">
                            <div class="grade-modal-table-container">
                                <table class="grade-modal-table">
                                    <thead>
                                        <tr>
                                            <th>Grade 1 - Quizzes & Exams</th>
                                            <th>Grade 2 - Attendance & Attitude</th>
                                            <th>Grade 3 - Practical Exercises</th>
                                            <th>Grade 4 - Institutional Assessment</th>
                                            <th>Final Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gradeBreakdownTableBody">
                                        <!-- Student grade data will be populated here by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Grade 1 Detail Modal -->
        <div id="gradeDetailModal1" class="grade-detail-modal-overlay">
            <div class="grade-detail-modal-content">
                <div class="grade-detail-modal-header">
                    <h2 class="grade-detail-modal-title" id="gradeDetailModalTitle1">
                        <i class="fas fa-chart-bar"></i>
                        Grade 1 Detail
                    </h2>
                    <button class="grade-detail-modal-close" id="gradeDetailModalClose1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grade-detail-modal-body">
                    <div class="grade-detail-table-container">
                        <table class="grade-detail-table">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>GRADE 1: WRITTEN ACTIVITIES/QUIZZES/ASSIGNMENT (25%)</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <button type="button" class="grade-form-btn secondary"
                                                    id="deleteGradeColumnBtn1">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                                <button type="button" class="grade-form-btn warning"
                                                    id="editGradeColumnBtn1">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                                <button type="button" class="grade-form-btn primary"
                                                    id="openAddGradePopupBtn1">
                                                    <i class="fas fa-plus"></i>
                                                    Add Grade
                                                </button>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="hidden" id="detailStudentId1" name="studentId">
                                        <input type="hidden" id="detailGradeNumber1" name="gradeNumber" value="1">
                                        <div class="grade-table-container mt-3">
                                            <table class="grade-table" id="gradeDetailGrid1">
                                                <thead>
                                                    <tr id="gridHeaderDates1"></tr>
                                                    <tr id="gridHeaderTypes1"></tr>
                                                </thead>
                                                <tbody>
                                                    <tr id="gridRowRaw1"></tr>
                                                    <tr id="gridRowTransmuted1"></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="gradeDetailAddPopup1"
                        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div
                            style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div
                                style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3
                                    style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-plus"></i> Add Grade
                                </h3>
                                <button id="closeAddGradePopup1" class="btn btn-sm"
                                    style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;"><i
                                        class="fas fa-times"></i></button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailAddForm1">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="gradeAssessmentType1" class="form-label">Component</label>
                                            <select id="gradeAssessmentType1" class="form-select">
                                                <option value="quiz">Quiz</option>
                                                <option value="homework">Homework</option>
                                                <option value="activity">Activity</option>
                                                <option value="exam">Exam</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentDate1" class="form-label">Date</label>
                                            <input type="date" id="gradeAssessmentDate1" class="form-control"
                                                min="2020-01-01" max="2030-12-31" required
                                                aria-describedby="gradeAssessmentDate1-error" aria-invalid="false">
                                            <div id="gradeAssessmentDate1-error" class="invalid-feedback" role="alert">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentTotal1" class="form-label">Total Items (once)</label>
                                            <input type="number" id="gradeAssessmentTotal1" class="form-control" min="1"
                                                max="100" placeholder="e.g., 20">
                                            <div class="form-text" id="gradeAssessmentTotalDisplay1"
                                                style="margin-top:6px;">Total Items: --</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentBase1" class="form-label">Base (×)</label>
                                            <input type="number" id="gradeAssessmentBase1" class="form-control" min="1" max="100" value="50" placeholder="e.g., 50">
                                            <div class="form-text" id="gradeAssessmentBaseDisplay1" style="margin-top:6px;">Uses (raw/total) × 50 + 50</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeDetailPopupRaw1" class="form-label">Raw Score</label>
                                            <input type="number" id="gradeDetailPopupRaw1" class="form-control" min="0"
                                                placeholder="e.g., 15">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-flex justify-content-between align-items-center">
                                                <span>Transmuted Grade</span>
                                                <small id="gradeDetailPopupTransmutedHint1" class="text-muted">Auto</small>
                                            </label>
                                            <input type="text" id="gradeDetailPopupTransmuted1" class="form-control"
                                                readonly placeholder="--%">
                                            <div class="form-text" id="convertedScoreDisplay1" style="margin-top:6px;">
                                                Converted Score: -- out of 100
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grade-form-actions"
                                        style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelAddGradePopup1">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn primary" id="saveAddGradePopupBtn1">
                                            <i class="fas fa-save"></i>
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Popup: Grade 1 -->
                    <div id="gradeDetailDeletePopup1" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3 style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-trash"></i> Delete Grades
                                </h3>
                                <button id="closeDeletePopup1" class="btn btn-sm" style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;"><i class="fas fa-times"></i></button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailDeleteForm1">
                                    <div id="deleteColumnsList1" class="list-group"></div>
                                    <div class="grade-form-actions" style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelDeletePopup1">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn secondary opacity-50" id="confirmDeleteColumnBtn1" disabled>
                                            <i class="fas fa-trash"></i>
                                            Delete Selected
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade 2 Detail Modal -->
        <div id="gradeDetailModal2" class="grade-detail-modal-overlay">
            <div class="grade-detail-modal-content">
                <div class="grade-detail-modal-header">
                    <h2 class="grade-detail-modal-title" id="gradeDetailModalTitle2">
                        <i class="fas fa-chart-bar"></i>
                        Grade 2 Detail
                    </h2>
                    <button class="grade-detail-modal-close" id="gradeDetailModalClose2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grade-detail-modal-body">
                    <div class="grade-detail-table-container">
                        <table class="grade-detail-table">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>GRADE 2: ATTENDANCE & ATTITUDE (25%)</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <button type="button" class="grade-form-btn secondary"
                                                    id="deleteGradeColumnBtn2">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                                <button type="button" class="grade-form-btn warning"
                                                    id="editGradeColumnBtn2">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                                <button type="button" class="grade-form-btn primary"
                                                    id="openAddGradePopupBtn2">
                                                    <i class="fas fa-plus"></i>
                                                    Add Grade
                                                </button>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="hidden" id="detailStudentId2" name="studentId">
                                        <input type="hidden" id="detailGradeNumber2" name="gradeNumber" value="2">
                                        <div class="grade-table-container mt-3">
                                            <table class="grade-table" id="gradeDetailGrid2">
                                                <thead>
                                                    <tr id="gridHeaderDates2"></tr>
                                                    <tr id="gridHeaderTypes2"></tr>
                                                </thead>
                                                <tbody>
                                                    <tr id="gridRowRaw2"></tr>
                                                    <tr id="gridRowTransmuted2"></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="gradeDetailAddPopup2"
                        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div
                            style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div
                                style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3
                                    style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-plus"></i> Add Grade
                                </h3>
                                <button id="closeAddGradePopup2" class="btn btn-sm"
                                    style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;"><i
                                        class="fas fa-times"></i></button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailAddForm2">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="gradeAssessmentType2" class="form-label">Attendance Status</label>
                                            <select id="gradeAssessmentType2" class="form-select">
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentDate2" class="form-label">Date</label>
                                            <input type="date" id="gradeAssessmentDate2" class="form-control"
                                                min="1000-01-01" max="9999-12-31">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeDetailPopupRaw2" class="form-label">Raw Score</label>
                                            <input type="number" id="gradeDetailPopupRaw2" class="form-control" min="0"
                                                placeholder="Auto based on status">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-flex justify-content-between align-items-center">
                                                <span>Transmuted Grade</span>
                                                <small id="gradeDetailPopupTransmutedHint2" class="text-muted">Auto</small>
                                            </label>
                                            <input type="text" id="gradeDetailPopupTransmuted2" class="form-control"
                                                readonly placeholder="--%">
                                            <div class="form-text" id="convertedScoreDisplay2" style="margin-top:6px; display:none;">
                                                Converted Score: -- out of 100
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grade-form-actions"
                                        style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelAddGradePopup2">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn primary" id="saveAddGradePopupBtn2">
                                            <i class="fas fa-save"></i>
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Popup: Grade 2 -->
                    <div id="gradeDetailDeletePopup2" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3 style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-trash"></i> Delete Grades
                                </h3>
                                <button id="closeDeletePopup2" class="btn btn-sm" style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;"><i class="fas fa-times"></i></button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailDeleteForm2">
                                    <div id="deleteColumnsList2" class="list-group"></div>
                                    <div class="grade-form-actions" style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelDeletePopup2">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn secondary opacity-50" id="confirmDeleteColumnBtn2" disabled>
                                            <i class="fas fa-trash"></i>
                                            Delete Selected
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Attendance Management Modal -->
        <div id="attendanceModal" class="grade-detail-modal-overlay">
            <div class="grade-detail-modal-content">
                <div class="grade-detail-modal-header">
                    <h2 class="grade-detail-modal-title" id="attendanceModalTitle">
                        <i class="fas fa-calendar-check"></i>
                        Attendance Management
                    </h2>
                    <button class="grade-detail-modal-close" id="attendanceModalClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grade-detail-modal-body">
                    <div class="attendance-modal-content">
                        <!-- Batch Selection in Modal -->
                        <div class="modal-batch-selector">
                            <h4>Select Batch</h4>
                            <div class="batch-tabs">
                                <button class="batch-tab active" data-batch="1">
                                    <span class="batch-label">Batch 1</span>
                                    <span class="batch-period">January - March</span>
                                </button>
                                <button class="batch-tab" data-batch="2">
                                    <span class="batch-label">Batch 2</span>
                                    <span class="batch-period">April - June</span>
                                </button>
                                <button class="batch-tab" data-batch="3">
                                    <span class="batch-label">Batch 3</span>
                                    <span class="batch-period">July - September</span>
                                </button>
                                <button class="batch-tab" data-batch="4">
                                    <span class="batch-label">Batch 4</span>
                                    <span class="batch-period">October - December</span>
                                </button>
                            </div>
                        </div>

                        <!-- Student Info -->
                        <div class="student-info">
                            <h4 id="attendanceStudentName">Student Name</h4>
                            <p id="attendanceStudentCourse">Course: -</p>
                        </div>

                        <!-- Attendance Form -->
                        <div class="attendance-form">
                            <form id="attendanceForm">
                                <input type="hidden" id="attendanceStudentId" name="studentId">
                                <input type="hidden" id="attendanceBatch" name="batch" value="1">
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="attendanceStatus" class="form-label">Attendance Status</label>
                                        <select id="attendanceStatus" class="form-select" required>
                                            <option value="">Select Status</option>
                                            <option value="present">Present</option>
                                            <option value="absent">Absent</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="attendanceDate" class="form-label">Date</label>
                                        <input type="date" id="attendanceDate" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="attendanceTime" class="form-label">Time</label>
                                        <input type="time" id="attendanceTime" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label for="attendanceNotes" class="form-label">Notes (Optional)</label>
                                        <textarea id="attendanceNotes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="attendance-form-actions">
                                    <button type="button" class="btn btn-secondary" id="cancelAttendanceBtn">
                                        <i class="fas fa-times"></i>
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="saveAttendanceBtn">
                                        <i class="fas fa-save"></i>
                                        Save Attendance
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Attendance History -->
                        <div class="attendance-history">
                            <h4>Attendance History</h4>
                            <div class="table-container">
                                <table class="table" id="attendanceHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendanceHistoryBody">
                                        <!-- Attendance records will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade 3 Detail Modal -->
        <div id="gradeDetailModal3" class="grade-detail-modal-overlay">
            <div class="grade-detail-modal-content">
                <div class="grade-detail-modal-header">
                    <h2 class="grade-detail-modal-title" id="gradeDetailModalTitle3">
                        <i class="fas fa-chart-bar"></i>
                        Grade 3 Detail
                    </h2>
                    <button class="grade-detail-modal-close" id="gradeDetailModalClose3">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grade-detail-modal-body">
                    <div class="grade-detail-table-container">
                        <table class="grade-detail-table">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>GRADE 3: PRACTICAL & MINOR ACTIVITIES (25%)</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <button type="button" class="grade-form-btn secondary"
                                                    id="deleteGradeColumnBtn3">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                                <button type="button" class="grade-form-btn warning"
                                                    id="editGradeColumnBtn3">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                                <button type="button" class="grade-form-btn primary"
                                                    id="openAddGradePopupBtn3">
                                                    <i class="fas fa-plus"></i>
                                                    Add Grade
                                                </button>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="hidden" id="detailStudentId3" name="studentId">
                                        <input type="hidden" id="detailGradeNumber3" name="gradeNumber" value="3">
                                        <div class="grade-table-container mt-3">
                                            <table class="grade-table" id="gradeDetailGrid3">
                                                <thead>
                                                    <tr id="gridHeaderDates3"></tr>
                                                    <tr id="gridHeaderTypes3"></tr>
                                                </thead>
                                                <tbody>
                                                    <tr id="gridRowRaw3"></tr>
                                                    <tr id="gridRowTransmuted3"></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="gradeDetailAddPopup3"
                        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div
                            style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div
                                style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3
                                    style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-plus"></i> Add Grade
                                </h3>
                                <button id="closeAddGradePopup3" class="btn btn-sm"
                                    style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;"><i
                                        class="fas fa-times"></i></button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailAddForm3">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="gradeAssessmentType3" class="form-label">Component</label>
                                            <select id="gradeAssessmentType3" class="form-select">
                                                <option value="quiz">Quiz</option>
                                                <option value="homework">Homework</option>
                                                <option value="activity">Activity</option>
                                                <option value="exam">Exam</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentDate3" class="form-label">Date</label>
                                            <input type="date" id="gradeAssessmentDate3" class="form-control"
                                                min="1000-01-01" max="9999-12-31">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentTotal3" class="form-label">Total Items (once)</label>
                                            <input type="number" id="gradeAssessmentTotal3" class="form-control" min="1"
                                                max="100" placeholder="e.g., 20">
                                            <div class="form-text" id="gradeAssessmentTotalDisplay3"
                                                style="margin-top:6px;">Total Items: --</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentBase3" class="form-label">Base (×)</label>
                                            <input type="number" id="gradeAssessmentBase3" class="form-control" min="1" max="100" value="50" placeholder="e.g., 50">
                                            <div class="form-text" id="gradeAssessmentBaseDisplay3" style="margin-top:6px;">Uses (raw/total) × 50 + 50</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeDetailPopupRaw3" class="form-label">Raw Score</label>
                                            <input type="number" id="gradeDetailPopupRaw3" class="form-control" min="0"
                                                placeholder="e.g., 15">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-flex justify-content-between align-items-center">
                                                <span>Transmuted Grade</span>
                                                <small id="gradeDetailPopupTransmutedHint3" class="text-muted">Auto</small>
                                            </label>
                                            <input type="text" id="gradeDetailPopupTransmuted3" class="form-control"
                                                readonly placeholder="--%">
                                            <div class="form-text" id="convertedScoreDisplay3" style="margin-top:6px;">
                                                Converted Score: -- out of 100
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grade-form-actions"
                                        style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelAddGradePopup3">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn primary" id="saveAddGradePopupBtn3">
                                            <i class="fas fa-save"></i>
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Popup: Grade 3 -->
                    <div id="gradeDetailDeletePopup3" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3 style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-trash"></i> Delete Grades
                                </h3>
                                <button id="closeDeletePopup3" class="btn btn-sm" style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;"><i class="fas fa-times"></i></button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailDeleteForm3">
                                    <div id="deleteColumnsList3" class="list-group"></div>
                                    <div class="grade-form-actions" style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelDeletePopup3">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn secondary opacity-50" id="confirmDeleteColumnBtn3" disabled>
                                            <i class="fas fa-trash"></i>
                                            Delete Selected
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade 4 Detail Modal -->
        <div id="gradeDetailModal4" class="grade-detail-modal-overlay">
            <div class="grade-detail-modal-content">
                <div class="grade-detail-modal-header">
                    <h2 class="grade-detail-modal-title" id="gradeDetailModalTitle4">
                        <i class="fas fa-chart-bar"></i>
                        Grade 4 Detail
                    </h2>
                    <button class="grade-detail-modal-close" id="gradeDetailModalClose4">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grade-detail-modal-body">
                    <div class="grade-detail-table-container">
                        <table class="grade-detail-table">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>GRADE 4: INST'L ASSESSMENT (25%)</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <button type="button" class="grade-form-btn secondary"
                                                    id="deleteGradeColumnBtn4">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                                <button type="button" class="grade-form-btn warning"
                                                    id="editGradeColumnBtn4">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                                <button type="button" class="grade-form-btn primary"
                                                    id="openAddGradePopupBtn4">
                                                    <i class="fas fa-plus"></i>
                                                    Add Grade
                                                </button>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="hidden" id="detailStudentId4" name="studentId">
                                        <input type="hidden" id="detailGradeNumber4" name="gradeNumber" value="4">
                                        <div class="grade-table-container mt-3">
                                            <table class="grade-table" id="gradeDetailGrid4">
                                                <thead>
                                                    <tr id="gridHeaderDates4"></tr>
                                                    <tr id="gridHeaderTypes4"></tr>
                                                </thead>
                                                <tbody>
                                                    <tr id="gridRowRaw4"></tr>
                                                    <tr id="gridRowTransmuted4"></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="gradeDetailAddPopup4"
                        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div
                            style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div
                                style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3
                                    style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-plus"></i> Add Grade
                                </h3>
                                <button id="closeAddGradePopup4" class="btn btn-sm"
                                    style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailAddForm4">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="gradeAssessmentType4" class="form-label">Component</label>
                                            <input type="text" id="gradeAssessmentType4" class="form-control"
                                                placeholder="Enter component type">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentDate4" class="form-label">Date</label>
                                            <input type="date" id="gradeAssessmentDate4" class="form-control"
                                                min="1000-01-01" max="9999-12-31">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentTotal4" class="form-label">Total Items (once)</label>
                                            <input type="number" id="gradeAssessmentTotal4" class="form-control" min="1"
                                                max="100" placeholder="e.g., 20">
                                            <div class="form-text" id="gradeAssessmentTotalDisplay4"
                                                style="margin-top:6px;">Total Items: --</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeAssessmentBase4" class="form-label">Base (×)</label>
                                            <input type="number" id="gradeAssessmentBase4" class="form-control" min="1" max="100" value="50" placeholder="e.g., 50">
                                            <div class="form-text" id="gradeAssessmentBaseDisplay4" style="margin-top:6px;">Uses (raw/total) × 50 + 50</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gradeDetailPopupRaw4" class="form-label">Raw Score</label>
                                            <input type="number" id="gradeDetailPopupRaw4" class="form-control" min="0"
                                                placeholder="e.g., 15">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-flex justify-content-between align-items-center">
                                                <span>Transmuted Grade</span>
                                                <small id="gradeDetailPopupTransmutedHint4" class="text-muted">Auto</small>
                                            </label>
                                            <input type="text" id="gradeDetailPopupTransmuted4" class="form-control"
                                                readonly placeholder="--%">
                                            <div class="form-text" id="convertedScoreDisplay4" style="margin-top:6px;">
                                                Converted Score: -- out of 100
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grade-form-actions"
                                        style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelAddGradePopup4">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn primary" id="saveAddGradePopupBtn4">
                                            <i class="fas fa-save"></i>
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Popup: Grade 4 -->
                    <div id="gradeDetailDeletePopup4" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index: 9999; align-items:center; justify-content:center;">
                        <div style="background:var(--background); width: min(520px, 90%); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                            <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                                <h3 style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px; color:var(--foreground);">
                                    <i class="fas fa-trash"></i> Delete Grades
                                </h3>
                                <button id="closeDeletePopup4" class="btn btn-sm" style="border:none; background:transparent; color:var(--foreground); padding:8px; border-radius:4px; transition:background-color 0.2s ease;"><i class="fas fa-times"></i></button>
                            </div>
                            <div style="padding:20px;">
                                <form id="gradeDetailDeleteForm4">
                                    <div id="deleteColumnsList4" class="list-group"></div>
                                    <div class="grade-form-actions" style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                                        <button type="button" class="grade-form-btn secondary" id="cancelDeletePopup4">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                        <button type="submit" class="grade-form-btn secondary opacity-50" id="confirmDeleteColumnBtn4" disabled>
                                            <i class="fas fa-trash"></i>
                                            Delete Selected
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal (global) -->
        <div id="deleteConfirmationModal" class="delete-confirmation-overlay">
            <div class="delete-confirmation-content">
                <div class="delete-confirmation-header">
                    <div class="delete-confirmation-icon"><i class="fas fa-trash"></i></div>
                    <h3 id="deleteConfirmationTitle" class="delete-confirmation-title">Delete</h3>
                </div>
                <div class="delete-confirmation-body">
                    <p id="deleteConfirmationMessage" class="delete-confirmation-message"></p>
                    <div id="deleteConfirmationDetails" class="delete-confirmation-details"></div>
                </div>
                <div class="delete-confirmation-footer">
                    <button id="cancelDeleteConfirmation" class="delete-confirmation-btn cancel"><i class="fas fa-times"></i><span>Cancel</span></button>
                    <button id="confirmDeleteConfirmation" class="delete-confirmation-btn confirm"><i class="fas fa-trash"></i><span>Delete</span></button>
                </div>
            </div>
        </div>

        <!-- Main JavaScript -->
        <script src="../js/instructor.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/instructor.js")); ?>"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="../js/employment_charts.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/employment_charts.js")); ?>"></script>
        <script src="../js/employment_trend_analysis.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/employment_trend_analysis.js")); ?>"></script>
        <script src="../js/course_trends_visualization.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/course_trends_visualization.js")); ?>"></script>
        <script src="../js/job_trends_visualization.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/job_trends_visualization.js")); ?>"></script>
        <script src="../js/graduates_course_popularity_2025.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/graduates_course_popularity_2025.js")); ?>"></script>
        <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        (function(){
            var key = 'mmtvtc_theme';
            try {
                var saved = localStorage.getItem(key);
                if (saved === 'dark') {
                    document.body.setAttribute('data-theme', 'dark');
                }
            } catch(e) {}
            var btn = document.getElementById('themeToggle');
            if (btn) {
                btn.addEventListener('click', function(){
                    var isDark = document.body.getAttribute('data-theme') === 'dark';
                    if (isDark) {
                        document.body.removeAttribute('data-theme');
                        try { localStorage.setItem(key, 'light'); } catch(e) {}
                    } else {
                        document.body.setAttribute('data-theme', 'dark');
                        try { localStorage.setItem(key, 'dark'); } catch(e) {}
                    }
                });
            }
        })();
        </script>
        <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                // Count-up animation for final grade percentages in this page
                (function () {
                    function animateNumber(el, target, duration) {
                        const start = 0;
                        const startTime = performance.now();
                        function step(now) {
                            const p = Math.min(1, (now - startTime) / duration);
                            const val = start + (target - start) * p;
                            el.textContent = val.toFixed(1) + '%';
                            if (p < 1) requestAnimationFrame(step); else el.closest('.final-grade-cell')?.classList.remove('animating');
                        }
                        requestAnimationFrame(step);
                    }

                    function runFinalGradeCountUp(scope) {
                        const root = scope || document;
                        const cells = root.querySelectorAll('#gradesTable .final-grade-cell strong');
                        cells.forEach(s => {
                            const wrap = s.closest('.final-grade-cell');
                            if (!wrap) return;
                            const target = parseFloat((s.textContent || '0').replace('%', '')) || 0;
                            s.textContent = '0.0%';
                            wrap.classList.add('animating');
                            animateNumber(s, target, 1100);
                        });
                    }

                    // Animation disabled since final grades are now calculated in PHP
                    // window.addEventListener('load', function () {
                    //     setTimeout(runFinalGradeCountUp, 150);
                    // });

                    // document.addEventListener('click', function (e) {
                    //     const tab = e.target.closest('.tab');
                    //     if (tab && tab.getAttribute('data-tab') === 'grades') {
                    //         setTimeout(runFinalGradeCountUp, 150);
                    //     }
                    // });
                })();
        </script>

        <!-- Notification fetching moved to js/instructor.js -->
        
        <!-- Instructor Job Matching Live Updates -->
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
                fetch('../apis/jobs_handler.php', {credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(j){ 
                        if(j && j.success && Array.isArray(j.data)) { 
                            allJobs = j.data; 
                            filteredJobs = allJobs; // Instructors see all jobs
                        } else {
                            allJobs = [];
                            filteredJobs = [];
                        }
                        renderJobs(filteredJobs);
                        updateJobCount();
                        populateFilterDropdowns();
                    })
                    .catch(function(){
                        allJobs = [];
                        filteredJobs = [];
                        renderJobs([]);
                        updateJobCount();
                    });
            }

            function renderJobs(jobs){
                var grid = document.getElementById('instructorJobCardsGrid');
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
                var locationFilter = document.getElementById('instructorLocationFilter');
                var experienceFilter = document.getElementById('instructorExperienceFilter');
                
                if(!locationFilter || !experienceFilter) return;

                // Get unique locations and experiences from all jobs
                var locations = [...new Set(allJobs.map(job => job.location).filter(Boolean))].sort();
                var experiences = [...new Set(allJobs.map(job => job.experience).filter(Boolean))].sort();

                // Populate location dropdown
                locationFilter.innerHTML = '<option value="">All Locations</option>' + 
                    locations.map(loc => '<option value="' + escapeHtml(loc) + '">' + escapeHtml(loc) + '</option>').join('');

                // Populate experience dropdown
                experienceFilter.innerHTML = '<option value="">All Experience Levels</option>' + 
                    experiences.map(exp => '<option value="' + escapeHtml(exp) + '">' + escapeHtml(exp) + '</option>').join('');
            }

            function applyFilters(){
                var locationFilter = document.getElementById('instructorLocationFilter');
                var experienceFilter = document.getElementById('instructorExperienceFilter');
                
                if(!locationFilter || !experienceFilter) return;

                var selectedLocation = locationFilter.value;
                var selectedExperience = experienceFilter.value;

                filteredJobs = allJobs.filter(function(job){
                    var locationMatch = !selectedLocation || job.location === selectedLocation;
                    var experienceMatch = !selectedExperience || job.experience === selectedExperience;
                    
                    return locationMatch && experienceMatch;
                });

                renderJobs(filteredJobs);
                updateJobCount();
            }

            // Bind event listeners
            function bindEventListeners(){
                var locationFilter = document.getElementById('instructorLocationFilter');
                var experienceFilter = document.getElementById('instructorExperienceFilter');
                var refreshBtn = document.getElementById('instructorRefreshJobsBtn');

                if(locationFilter) locationFilter.addEventListener('change', applyFilters);
                if(experienceFilter) experienceFilter.addEventListener('change', applyFilters);
                if(refreshBtn) refreshBtn.addEventListener('click', loadJobs);
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
        
        // Enhanced Attendance Functionality
        let attendanceData = {
            courses: [],
            selectedCourse: null,
            students: [],
            selectedBatch: 1,
            attendanceRecords: {}
        };
        
        // Initialize enhanced attendance functionality
        function initializeAttendance() {
            console.log('Initializing enhanced attendance functionality...');
            
            // Load courses for attendance
            loadAttendanceCourses();
            
            // Set up event listeners
            setupAttendanceEventListeners();
        }
        
        // Set up attendance event listeners
        function setupAttendanceEventListeners() {
            // Search functionality
            const searchInput = document.getElementById('courseSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterCourses(this.value);
                });
            }
            
            // Refresh courses button
            const refreshBtn = document.getElementById('refreshCoursesBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    loadAttendanceCourses();
                });
            }
            
            // Mark all present button
            const markAllPresentBtn = document.getElementById('markAllPresentBtn');
            if (markAllPresentBtn) {
                markAllPresentBtn.addEventListener('click', function() {
                    markAllStudentsAttendance('present');
                });
            }
            
            // Mark all absent button
            const markAllAbsentBtn = document.getElementById('markAllAbsentBtn');
            if (markAllAbsentBtn) {
                markAllAbsentBtn.addEventListener('click', function() {
                    markAllStudentsAttendance('absent');
                });
            }
            
            // Select all students checkbox
            const selectAllCheckbox = document.getElementById('selectAllStudents');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
                    studentCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }
        }
        
        // Mark all students attendance
        function markAllStudentsAttendance(status) {
            if (!attendanceData.selectedCourse) {
                alert('Please select a course first');
                return;
            }
            
            const date = document.getElementById('attendanceDate').value;
            if (!date) {
                alert('Please select a date first');
                return;
            }
            
            const selectedStudents = Array.from(document.querySelectorAll('.student-checkbox:checked'))
                .map(checkbox => checkbox.dataset.id);
            
            if (selectedStudents.length === 0) {
                alert('Please select students to mark attendance');
                return;
            }
            
            // Mark attendance for all selected students
            const bulkScore = status === 'present' ? 100 : 50;
            selectedStudents.forEach(studentId => {
                markAttendance(studentId, status, bulkScore);
            });
        }
        
        // Load attendance courses with enhanced UI
        function loadAttendanceCourses() {
            console.log('Loading attendance courses...');
            
            // Show loading state
            const container = document.getElementById('courseCardsContainer');
            if (container) {
                container.innerHTML = `
                    <div class="course-card loading-card">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading courses...</span>
                        </div>
                    </div>
                `;
            }
            
            // Try to fetch from API first
            fetch('../apis/attendance_handler.php?action=get_courses', { credentials: 'same-origin' })
                .then(response => {
                    console.log('API response status:', response.status);
                    if (!response.ok) {
                        throw new Error('API not available');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API response data:', data);
                    if (data && data.success && data.courses) {
                        attendanceData.courses = data.courses;
                        renderCourseCards(data.courses);
                    } else {
                        console.log('No courses from API, showing no courses message');
                        showNoCoursesMessage();
                    }
                })
                .catch(error => {
                    console.error('Error loading courses from API:', error);
                    // Fallback: try to get courses from existing data or show demo data
                    loadFallbackCourses();
                });
        }
        
        // Fallback function to load demo courses or existing data
        function loadFallbackCourses() {
            console.log('Loading fallback courses...');
            
            // Try to get courses from existing PHP data if available
            const instructorCourse = '<?php echo $instructorCourse ?? ""; ?>';
            
            if (instructorCourse && instructorCourse.trim() !== '') {
                // Use the instructor's assigned course
                const demoCourses = [{
                    id: '1',
                    name: instructorCourse,
                    code: instructorCourse.substring(0, 10).toUpperCase(),
                    student_count: 15,
                    attendance_rate: 85
                }];
                
                attendanceData.courses = demoCourses;
                renderCourseCards(demoCourses);
            } else {
                // Show demo data for testing
                const demoCourses = [
                    {
                        id: '1',
                        name: 'Computer Programming',
                        code: 'CP101',
                        student_count: 25,
                        attendance_rate: 92
                    },
                    {
                        id: '2',
                        name: 'Web Development',
                        code: 'WD201',
                        student_count: 18,
                        attendance_rate: 88
                    },
                    {
                        id: '3',
                        name: 'Database Management',
                        code: 'DB301',
                        student_count: 22,
                        attendance_rate: 95
                    }
                ];
                
                attendanceData.courses = demoCourses;
                renderCourseCards(demoCourses);
            }
        }
        
        // Render course cards instead of table
        function renderCourseCards(courses) {
            console.log('Rendering course cards:', courses);
            const container = document.getElementById('courseCardsContainer');
            if (!container) {
                console.error('Course cards container not found!');
                return;
            }
            
            if (courses.length === 0) {
                console.log('No courses to render, showing no courses message');
                showNoCoursesMessage();
                return;
            }
            
            const courseCardsHTML = courses.map(course => {
                const batchesHTML = course.batches ? course.batches.map(batch => `
                    <div class="batch-info">
                        <span class="batch-name">${batch.name}</span>
                        <span class="batch-period">${batch.period}</span>
                        <span class="batch-count">${batch.student_count} students</span>
                    </div>
                `).join('') : '';
                
                return `
                    <div class="course-card" onclick="selectCourseForAttendance('${course.id}', '${course.name}')">
                        <div class="course-card-header">
                            <h4 class="course-name">${course.name}</h4>
                            <span class="course-code">${course.code}</span>
                        </div>
                        <div class="course-card-stats">
                            <div class="stat">
                                <i class="fas fa-users"></i>
                                <span>${course.batches ? course.batches.reduce((total, batch) => total + batch.student_count, 0) : 0} total students</span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-calendar-check"></i>
                                <span>${Math.round(course.attendance_rate || 0)}% attendance</span>
                            </div>
                        </div>
                        <div class="course-batches">
                            ${batchesHTML}
                        </div>
                    </div>
                `;
            }).join('');
            
            console.log('Setting course cards HTML:', courseCardsHTML);
            container.innerHTML = courseCardsHTML;
            console.log('Course cards rendered successfully');
        }
        
        // Show no courses message
        function showNoCoursesMessage() {
            const container = document.getElementById('courseCardsContainer');
            if (container) {
                container.innerHTML = `
                    <div class="course-card no-courses-card">
                        <div class="no-data-message">
                            <i class="fas fa-graduation-cap"></i>
                            <p>No courses available</p>
                            <small>Contact administrator to assign courses</small>
                        </div>
                    </div>
                `;
            }
        }
        
        // Filter courses based on search input
        function filterCourses(searchTerm) {
            if (!attendanceData.courses || attendanceData.courses.length === 0) {
                return;
            }
            
            const filteredCourses = attendanceData.courses.filter(course => {
                const courseName = course.name.toLowerCase();
                const courseCode = (course.code || '').toLowerCase();
                const searchLower = searchTerm.toLowerCase();
                
                return courseName.includes(searchLower) || courseCode.includes(searchLower);
            });
            
            if (filteredCourses.length === 0 && searchTerm.trim() !== '') {
                // Show no results message
                const container = document.getElementById('courseCardsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="course-card no-courses-card">
                            <div class="no-data-message">
                                <i class="fas fa-search"></i>
                                <h4>No Courses Found</h4>
                                <p>No courses match your search: "${searchTerm}"</p>
                            </div>
                        </div>
                    `;
                }
            } else {
                // Render filtered courses
                renderCourseCards(filteredCourses);
            }
        }
        
        // Select course for attendance
        function selectCourseForAttendance(courseId, courseName) {
            console.log('Selected course:', courseId, courseName);
            
            attendanceData.selectedCourse = { id: courseId, name: courseName };
            
            // Hide course selector and show attendance content
            document.querySelector('.attendance-course-selector').style.display = 'none';
            document.getElementById('attendanceContent').style.display = 'block';
            
            // Update course info
            document.getElementById('selectedCourseName').textContent = courseName;
            
            // Load students for this course
            loadStudentsForAttendance(courseName);
        }
        
        // Load students for attendance
        function loadStudentsForAttendance(courseName, batchNumber = 1) {
            console.log('Loading students for course:', courseName, 'batch:', batchNumber);
            
            fetch(`apis/attendance_handler.php?action=get_students&course_id=${courseName}&batch=${batchNumber}`, { credentials: 'same-origin' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('API not available');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Students API response:', data);
                    if (data && data.success && data.students) {
                        if (data.debug) {
                            console.log('Debug info:', data.debug);
                        }
                        attendanceData.students = data.students;
                        renderAttendanceTable(data.students);
                        updateQuickStats();
                    } else {
                        showNoStudentsMessage();
                    }
                })
                .catch(error => {
                    console.error('Error loading students from API:', error);
                    // Fallback: show demo students
                    loadFallbackStudents(courseId);
                });
        }
        
        // Select batch
        function selectBatch(batchNumber) {
            console.log('Selected batch:', batchNumber);
            
            // Update batch tab UI
            document.querySelectorAll('.batch-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[data-batch="${batchNumber}"]`).classList.add('active');
            
            // Update selected batch
            attendanceData.selectedBatch = batchNumber;
            
            // Reload students for the selected batch
            if (attendanceData.selectedCourse) {
                loadStudentsForAttendance(attendanceData.selectedCourse.name, batchNumber);
            }
        }
        
        // Fallback function to load demo students
        function loadFallbackStudents(courseId) {
            console.log('Loading fallback students for course:', courseId);
            
            const demoStudents = [
                {
                    id: 1,
                    student_number: 'STU001',
                    full_name: 'John Doe',
                    present_days: 15,
                    absent_days: 2
                },
                {
                    id: 2,
                    student_number: 'STU002',
                    full_name: 'Jane Smith',
                    present_days: 16,
                    absent_days: 1
                },
                {
                    id: 3,
                    student_number: 'STU003',
                    full_name: 'Mike Johnson',
                    present_days: 14,
                    absent_days: 3
                },
                {
                    id: 4,
                    student_number: 'STU004',
                    full_name: 'Sarah Wilson',
                    present_days: 17,
                    absent_days: 0
                },
                {
                    id: 5,
                    student_number: 'STU005',
                    full_name: 'David Brown',
                    present_days: 13,
                    absent_days: 4
                }
            ];
            
            attendanceData.students = demoStudents;
            renderAttendanceTable(demoStudents);
            updateQuickStats();
        }
        
        // Render attendance table
        function renderAttendanceTable(students) {
            const tbody = document.getElementById('attendanceTableBody');
            if (!tbody) return;
            
            if (students.length === 0) {
                showNoStudentsMessage();
                return;
            }
            
            console.log('Rendering students:', students);
            
            tbody.innerHTML = students.map(student => {
                const attendanceRate = calculateAttendanceRate(student.present_days, student.absent_days);
                console.log('Student data:', student);
                return `
                    <tr>
                        <td class="col-checkbox">
                            <input type="checkbox" class="student-checkbox" data-id="${student.student_number}">
                        </td>
                        <td class="col-id">${student.student_number}</td>
                        <td class="col-name">${student.full_name}</td>
                        <td class="col-present">${student.present_days || 0}</td>
                        <td class="col-absent">${student.absent_days || 0}</td>
                        <td class="col-rate">
                            <span class="attendance-rate ${getAttendanceRateClass(attendanceRate)}">${attendanceRate}%</span>
                        </td>
                        <td class="col-actions">
                            <div class="attendance-actions">
                                <button class="attendance-btn present" onclick="markAttendance('${student.student_number}', 'present')">
                                    <i class="fas fa-check"></i> Present
                                </button>
                                <button class="attendance-btn absent" onclick="markAttendance('${student.student_number}', 'absent')">
                                    <i class="fas fa-times"></i> Absent
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            
            // Update table info
            document.getElementById('attendanceTableInfo').textContent = `Showing ${students.length} students`;
        }
        
        // Show no students message
        function showNoStudentsMessage() {
            const tbody = document.getElementById('attendanceTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr class="no-data-row">
                        <td colspan="7" class="text-center">
                            <div class="no-data-message">
                                <i class="fas fa-users"></i>
                                <p>No students found in this course</p>
                                <small>Students may not be enrolled yet</small>
                            </div>
                        </td>
                    </tr>
                `;
            }
        }
        
        // Calculate attendance rate
        function calculateAttendanceRate(presentDays, absentDays) {
            const total = (presentDays || 0) + (absentDays || 0);
            if (total === 0) return 0;
            return Math.round(((presentDays || 0) / total) * 100);
        }
        
        // Get attendance rate class for styling
        function getAttendanceRateClass(rate) {
            if (rate >= 90) return 'excellent';
            if (rate >= 80) return 'good';
            if (rate >= 70) return 'fair';
            return 'poor';
        }
        
        // Update quick stats
        function updateQuickStats() {
            const students = attendanceData.students;
            const totalStudents = students.length;
            const totalPresent = students.reduce((sum, student) => sum + (student.present_days || 0), 0);
            const totalAbsent = students.reduce((sum, student) => sum + (student.absent_days || 0), 0);
            const overallRate = totalStudents > 0 ? Math.round((totalPresent / (totalPresent + totalAbsent)) * 100) : 0;
            
            document.getElementById('totalStudents').textContent = totalStudents;
            document.getElementById('totalPresent').textContent = totalPresent;
            document.getElementById('totalAbsent').textContent = totalAbsent;
            document.getElementById('attendanceRate').textContent = overallRate + '%';
            document.getElementById('courseStats').textContent = `${totalStudents} students`;
        }
        
        // Mark attendance for a student
        function markAttendance(studentId, status) {
            console.log('Marking attendance:', studentId, status);
            console.log('Available students:', attendanceData.students);
            
            const date = document.getElementById('attendanceDate').value;
            if (!date) {
                alert('Please select a date first');
                return;
            }
            
            // Get the student data - studentId is now the student_number
            const student = attendanceData.students.find(s => s.student_number == studentId);
            
            if (!student) {
                console.error('Student not found:', studentId);
                console.error('Available student IDs:', attendanceData.students.map(s => ({ id: s.id, student_number: s.student_number })));
                return;
            }
            
            // Try API first, fallback to local update
            fetch('../apis/attendance_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'mark_attendance',
                    student_id: student.student_number,
                    status: status,
                    score: status === 'present' ? 100 : 0,
                    date: date,
                    batch: attendanceData.selectedBatch || 1
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('API not available');
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    updateLocalAttendance(studentId, status);
                    showNotification(`Attendance marked as ${status}`, 'success');
                } else {
                    throw new Error('API returned error');
                }
            })
            .catch(error => {
                console.error('Error marking attendance via API:', error);
                // Fallback: update locally
                updateLocalAttendance(studentId, status);
                showNotification(`Attendance marked as ${status} (demo mode)`, 'info');
            });
        }
        
        // Update local attendance data
        function updateLocalAttendance(studentId, status) {
            // studentId is now the student_number
            const student = attendanceData.students.find(s => s.student_number == studentId);
            if (student) {
                if (status === 'present') {
                    student.present_days = (student.present_days || 0) + 1;
                } else {
                    student.absent_days = (student.absent_days || 0) + 1;
                }
                
                // Update attendance rate
                student.attendance_rate = calculateAttendanceRate(student.present_days, student.absent_days);
            }
            
            // Update the specific row instead of re-rendering the entire table
            updateAttendanceRow(student);
            updateQuickStats();
        }
        
        // Update specific attendance row without re-rendering entire table
        function updateAttendanceRow(student) {
            const rows = document.querySelectorAll('#attendanceTableBody tr');
            rows.forEach(row => {
                const studentNumberCell = row.querySelector('.col-id');
                if (studentNumberCell && studentNumberCell.textContent === student.student_number) {
                    // Update present days
                    const presentCell = row.querySelector('.col-present');
                    if (presentCell) {
                        presentCell.textContent = student.present_days || 0;
                    }
                    
                    // Update absent days
                    const absentCell = row.querySelector('.col-absent');
                    if (absentCell) {
                        absentCell.textContent = student.absent_days || 0;
                    }
                    
                    // Update attendance rate
                    const rateCell = row.querySelector('.col-rate .attendance-rate');
                    if (rateCell) {
                        const attendanceRate = calculateAttendanceRate(student.present_days, student.absent_days);
                        rateCell.textContent = attendanceRate + '%';
                        rateCell.className = `attendance-rate ${getAttendanceRateClass(attendanceRate)}`;
                    }
                }
            });
        }
        
        // Back to courses
        function backToAttendanceCourses() {
            document.querySelector('.attendance-course-selector').style.display = 'block';
            document.getElementById('attendanceContent').style.display = 'none';
            attendanceData.selectedCourse = null;
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            // Simple notification - you can enhance this with a proper notification system
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }
        
        // Initialize attendance system once
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing attendance system...');
            
            // Initialize attendance system only once
            setTimeout(() => {
                console.log('Initializing attendance system...');
                initializeAttendance();
            }, 500);
        });
        </script>

    </body>

        <script src="js/cross-tab-logout.js"></script>
    </html>
    </html>