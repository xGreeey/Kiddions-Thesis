<?php
require_once '../security/session_config.php';
require_once '../security/csp.php';
require_once '../security/csrf.php';
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once '../security/db_connect.php';

// Handle logout and redirect to login_student.php
if (isset($_POST['logout'])) {
    if (function_exists('clearRememberMe')) { clearRememberMe(); }
    if (function_exists('destroySession')) { destroySession(); } else { session_unset(); session_destroy(); }
    header('Location: EKtJkWrAVAsyyA4fbj1KOrcYulJ2Wu');
    exit();
}

// Function to log security events
function logSecurityEvent($event, $details) {
    $logDir = '../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('Y-m-d H:i:s') . " - " . $event . " - " . json_encode($details) . "\n";
    file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);
}

// Enhanced session validation for admin access
function validateAdminSession() {
    // Check if user is authenticated/verified
    if (!isset($_SESSION['user_verified']) || !$_SESSION['user_verified']) {
        return false;
    }
    
    // Check if user has admin role (role = 2)
    if (!isset($_SESSION['is_role']) || $_SESSION['is_role'] != 2) {
        return false;
    }
    
    // Check if essential session data exists (admin may not have student_number)
    if (!isset($_SESSION['email'])) {
        return false;
    }
    
    // Session timeout is handled by session_config.php (2 hours)
    // Removed redundant 30-minute timeout check for consistency
    
    return true;
}

// Validate admin session
if (!validateAdminSession()) {
    // Log unauthorized access attempt
    $logDetails = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'attempted_access' => 'admin_dashboard',
        'session_data' => [
            'user_verified' => isset($_SESSION['user_verified']) ? $_SESSION['user_verified'] : 'not_set',
            'user_role' => isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not_set',
            'email' => isset($_SESSION['email']) ? $_SESSION['email'] : 'not_set',
            'authenticated' => isset($_SESSION['authenticated']) ? $_SESSION['authenticated'] : 'not_set'
        ]
    ];
    
    logSecurityEvent('UNAUTHORIZED_ADMIN_ACCESS', $logDetails);
    
    // Clear potentially invalid session and redirect to login
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// If we reach here, user is properly authenticated as admin
// Log successful admin access
logSecurityEvent('ADMIN_DASHBOARD_ACCESS', [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'email' => $_SESSION['email'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);


$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM mmtvtc_users WHERE is_role = 0");
    $stmt->execute();
    $totalTrainees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];


$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM mmtvtc_users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
    $stmt->execute();
    $monthlyTrainee = $stmt->fetch(PDO::FETCH_ASSOC)['total'];


// Fetch students with reflected total grades (average of all transmuted entries)
$studentsWithGrades = [];
try {
    $stmt = $pdo->prepare(
        "SELECT s.student_number, s.first_name, s.last_name, s.course,
                COALESCE(AVG(g.transmuted), 0) AS final_grade
         FROM students s
         LEFT JOIN grade_details g ON g.student_number = s.student_number
         WHERE s.course IS NOT NULL AND s.course != ''
         GROUP BY s.student_number, s.first_name, s.last_name, s.course
         ORDER BY s.created_at DESC"
    );
    $stmt->execute();
    $studentsWithGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Failed to fetch students with grades for admin trainee table: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MMTVTC Admin Dashboard</title>
    <link rel="icon" href="../images/logo.png" type="image/png">
    <link rel="stylesheet" href="../CSS/admin.css?v=<?php echo urlencode((string)@filemtime(__DIR__."/../CSS/admin.css")); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Removed external Chart.js CDN for stricter CSP. Use a local bundle if needed. -->
    <style>
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

        /* Single Course Management Styles */
        .single-course-management {
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .course-selection-row {
            margin-bottom: 20px;
        }
        
        .course-selection-row .form-group {
            margin-bottom: 0;
        }
        
        .course-selection-dropdown {
            font-size: 16px;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: #fff;
            transition: all 0.3s ease;
        }
        
        .course-selection-dropdown:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .course-details-row {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }
        
        .course-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .detail-item .form-input,
        .detail-item .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .detail-item .form-input:focus,
        .detail-item .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.1);
        }
        
        .detail-item .form-input[readonly] {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .actions-item {
            grid-column: 1 / -1;
            margin-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .course-details-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .action-buttons {
                justify-content: center;
            }
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
    </style>
</head>

<body>    
<?= csrfMetaTag(); ?>
<?= csrfInputField(); ?>
<input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars(csrfGetToken(), ENT_QUOTES, 'UTF-8'); ?>">

<script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
// Debug CSRF token on page load (can be removed in production)
// console.log('Admin Dashboard - CSRF Token:', '<?php echo $_SESSION['csrf_token'] ?? 'NOT_SET'; ?>');
// console.log('Admin Dashboard - Session ID:', '<?php echo session_id(); ?>');
// console.log('Admin Dashboard - CSRF Token Length:', <?php echo strlen($_SESSION['csrf_token'] ?? ''); ?>);
// console.log('Admin Dashboard - CSRF Input Element:', document.getElementById('csrf_token'));
// console.log('Admin Dashboard - CSRF Input Value:', document.getElementById('csrf_token')?.value);
</script>

    <div class="dashboard-container">
        <!-- Sidebar -->
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

<!-- Sidebar Navigations -->
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
            <button class="nav-item" data-section="graduates">
                <i class="fas fa-user-graduate"></i>
                <span class="nav-text">Graduates</span>
            </button>
        </li>
        <li>
            <button class="nav-item" data-section="course_settings">
                <i class="fas fa-cogs"></i>
                <span class="nav-text">Enrollment settings</span>
            </button>
        </li>
        <li>
            <button class="nav-item" data-section="job-matching">
                <i class="fas fa-briefcase"></i>
                <span class="nav-text">Job Matching</span>
            </button>
        </li>
        <li>
            <button class="nav-item" data-section="career">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Career Analytics</span>
            </button>
        </li>
        <li>
            <button class="nav-item" data-section="add-trainees">
                <i class="fas fa-user-plus"></i>
                <span class="nav-text">Add Trainees/Instructors</span>
            </button>
        </li>
        <li>
            <button class="nav-item" data-section="add-announcement">
                <i class="fas fa-bullhorn"></i>
                <span class="nav-text">Add Announcement</span>
            </button>
        </li>
        <li>
            <button class="nav-item" data-section="edit-notifications">
                <i class="fas fa-bell"></i>
                <span class="nav-text">Edit/Add Notifications</span>
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
                    <a href="../auth/logout.php" class="footer-btn logout-btn" id="logoutBtn" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="nav-text">Logout</span>
                    </a>

                    
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Header -->
            <header class="main-header">
                <h1 class="main-title">MMTVTC Admin Dashboard</h1>
                <div class="notification-container">
                    <button class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button class="notification-close" id="notificationClose">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item">
                                <div class="notification-icon">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-title">System Update</p>
                                    <p class="notification-message">New features added to job matching algorithm</p>
                                    <p class="notification-time">5 min ago</p>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-title">Database Backup Complete</p>
                                    <p class="notification-message">All trainee records successfully backed up</p>
                                    <p class="notification-time">1 hour ago</p>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon">
                                    <i class="fas fa-file-text"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-title">Pending Reviews</p>
                                    <p class="notification-message">5 trainee applications awaiting approval</p>
                                    <p class="notification-time">2 hours ago</p>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Dashboard Section -->
				<section class="page-section active" id="dashboard">
					<!-- Welcome Message (position aligned with instructor dashboard) -->
					<div class="welcome-message" style="padding: 10px 20px 20px 20px; margin-bottom: 24px;">
						<h1 class="welcome-title" style="font-size: 1.8rem;">Welcome, Admin</h1>
					</div>

                    <!-- Stats Cards -->
                    <?php
                        // Dashboard quick metrics
                        $totalStudents = 0; $activeJobs = 0; $numCourses = 0; $totalGraduatesAllTime = 0;
                        try {
                            // Get count from students table (live data)
                            $rs = $pdo->query("SELECT COUNT(*) AS c FROM students");
                            if ($rs) { $totalStudents = (int)($rs->fetch(PDO::FETCH_ASSOC)['c'] ?? 0); }
                        } catch (Throwable $e) { error_log('Dash metric totalStudents: ' . $e->getMessage()); }
                        try {
                            $rs = $pdo->query("SELECT COUNT(*) AS c FROM jobs WHERE is_active = 1");
                            if ($rs) { $activeJobs = (int)($rs->fetch(PDO::FETCH_ASSOC)['c'] ?? 0); }
                        } catch (Throwable $e) { error_log('Dash metric activeJobs: ' . $e->getMessage()); }
                        try {
                            // Prefer dedicated courses table when available
                            $rs = $pdo->query("SELECT COUNT(*) AS c FROM courses WHERE is_active = 1");
                            if ($rs) { $numCourses = (int)($rs->fetch(PDO::FETCH_ASSOC)['c'] ?? 0); }
                            if ($numCourses === 0) {
                                // Fallback to distinct user courses, then students
                                $rs = $pdo->query("SELECT COUNT(DISTINCT course) AS c FROM mmtvtc_users WHERE is_role = 0 AND course IS NOT NULL AND course <> ''");
                                if ($rs) { $numCourses = (int)($rs->fetch(PDO::FETCH_ASSOC)['c'] ?? 0); }
                                if ($numCourses === 0) {
                                    $rs = $pdo->query("SELECT COUNT(DISTINCT course) AS c FROM students WHERE course IS NOT NULL AND course <> ''");
                                    if ($rs) { $numCourses = (int)($rs->fetch(PDO::FETCH_ASSOC)['c'] ?? 0); }
                                }
                            }
                        } catch (Throwable $e) { error_log('Dash metric numCourses: ' . $e->getMessage()); }
                        // Graduates from CSV (all-time)
                        $csvPathDash = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'Graduates_.csv';
                        if (is_readable($csvPathDash)) {
                            if ($h = fopen($csvPathDash, 'r')) {
                                fgetcsv($h); // header
                                while (($row = fgetcsv($h)) !== false) {
                                    if (count($row) >= 4) { $totalGraduatesAllTime += (int)$row[3]; }
                                }
                                fclose($h);
                            }
                        }
                    ?>
                    <div class="stats-grid">
                        <div class="analytics-card">
                            <div class="analytics-icon blue">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="analytics-label">Total Students</h3>
                            <p class="analytics-value" id="totalStudentsCount"><?php echo number_format($totalStudents); ?></p>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-icon green">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <h3 class="analytics-label">Active Jobs</h3>
                            <p class="analytics-value"><?php echo number_format($activeJobs); ?></p>
                        </div>
                        <div class="analytics-card" id="coursesCard" style="cursor:pointer;">
                            <div class="analytics-icon purple">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <h3 class="analytics-label">Courses</h3>
                            <p class="analytics-value"><?php echo number_format($numCourses); ?></p>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-icon orange">
                                <i class="fas fa-award"></i>
                            </div>
                            <h3 class="analytics-label">Total Graduates</h3>
                            <p class="analytics-value"><?php echo number_format($totalGraduatesAllTime); ?></p>
                        </div>
                    </div>

					<!-- Courses Modal (replaces Course Overview section) -->
					<div id="coursesCountsModal" class="modal-overlay" style="display:none;">
						<div class="modal-content enhanced-modal" style="max-width:560px; width:90%; max-height:70vh; overflow:auto;">
							<h2 style="margin-bottom:12px;">Courses</h2>
							<div class="table-container">
								<table class="data-table">
									<thead>
										<tr>
											<th style="width:160px; text-align:left;">Active Students</th>
											<th style="border-left: 1px solid var(--border); text-align:left; padding-left:12px;">Course</th>
										</tr>
									</thead>
									<tbody id="coursesCountsBody"></tbody>
								</table>
							</div>
							<div style="margin-top:16px; text-align:right;">
								<button id="closeCoursesCountsModal" class="action-btn">Close</button>
							</div>
						</div>
					</div>

					<script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
					document.addEventListener('DOMContentLoaded', function(){
						var openBtn = document.getElementById('coursesCard');
						var modal = document.getElementById('coursesCountsModal');
						var closeBtn = document.getElementById('closeCoursesCountsModal');
						function escapeHtml(s){return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);});}
						function renderCourses(rows){
							var tbody = document.getElementById('coursesCountsBody');
							if(!tbody) return;
							var data = Array.isArray(rows) ? rows : [];
							var html = '';
							for(var i=0;i<data.length;i++){
								var r = data[i]||{};
								var name = r.course || '';
								var cnt = parseInt(r.cnt||0,10)||0;
								html += '<tr>'
									+ '<td>'+cnt.toLocaleString()+'</td>'
									+ '<td style="border-left: 1px solid var(--border); padding-left:12px;">'+escapeHtml(name)+'</td>'
									+ '</tr>';
							}
							if(html==='') html = '<tr><td colspan="2" style="opacity:.6;">No active courses</td></tr>';
							tbody.innerHTML = html;
						}
						function refresh(){
							fetch('../apis/course_overview.php',{credentials:'same-origin',cache:'no-store'})
								.then(function(r){return r.json();})
								.then(function(j){ if(j && j.success){ renderCourses(j.data||[]); } })
								.catch(function(){});
						}
						if(openBtn){ openBtn.addEventListener('click', function(){ if(modal){ modal.style.display = 'flex'; refresh(); } }); }
						if(closeBtn){ closeBtn.addEventListener('click', function(){ if(modal) modal.style.display = 'none'; }); }
						if(modal){ modal.addEventListener('click', function(e){ if(e.target === modal){ modal.style.display = 'none'; } }); }
					});
					</script>

                    <!-- Recent Registrations -->
                    <div class="recent-activity">
                        <h3 class="section-subtitle">Recent Registrations</h3>
                        <div class="analytics-card" style="padding:0; overflow:auto;">
                            <table class="data-table" style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>Student #</th>
                                        <th>Name</th>
                                        <th>Course</th>
                                        <th style=\"width:200px;\">Created</th>
                                    </tr>
                                </thead>
                                <tbody id="recentRegsBody">
                                    <tr><td colspan="4" style="text-align:center;opacity:.6;">Loading...</td></tr>
                                </tbody>
                            </table>
                            <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            (function(){
                                function renderRows(rows){
                                    var tbody = document.getElementById('recentRegsBody');
                                    if(!tbody) return;
                                    if(!Array.isArray(rows)) rows = [];
                                    var html = '';
                                    for(var i=0;i<rows.length;i++){
                                        var r = rows[i]||{};
                                        var sn = (r.student_number||'');
                                        var nm = ((r.first_name||'') + ' ' + (r.last_name||'')).trim();
                                        var cs = (r.course||'');
                                        var cr = (r.created_at||'');
                                        html += '<tr>'
                                            + '<td>'+escapeHtml(sn)+'</td>'
                                            + '<td>'+(nm?escapeHtml(nm):'—')+'</td>'
                                            + '<td>'+(cs?escapeHtml(cs):'—')+'</td>'
                                            + '<td>'+(cr?escapeHtml(cr):'—')+'</td>'
                                            + '</tr>';
                                    }
                                    if(html==='') html = '<tr><td colspan="4" style="text-align:center;opacity:.6;">No registrations</td></tr>';
                                    tbody.innerHTML = html;
                                }
                                function escapeHtml(s){return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);});}
                                function refreshRecent(){
                                    fetch('../apis/recent_registrations.php',{credentials:'same-origin',cache:'no-store'})
                                        .then(function(r){return r.json();})
                                        .then(function(j){ if(j && j.success){ renderRows(j.data||[]); } })
                                        .catch(function(){});
                                }
                                document.addEventListener('DOMContentLoaded', function(){
                                    refreshRecent();
                                    setInterval(refreshRecent, 10000);
                                });
                            })();
                            </script>
                        </div>
                    </div>
                </section>

                <!-- Trainee Record Section -->
<section class="page-section" id="trainee">
    <div class="section-header">
        <h2 class="section-title">Trainee Record Management</h2>
        <p class="section-description">Manage and view all trainee information, grades, and performance</p>
    </div>

                    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3 class="section-subtitle">Quick Actions</h3>
        <div class="action-buttons">
            <button class="action-btn green" id="qaExportData">
                <i class="fas fa-download"></i>
                <span>Export Data</span>
            </button>
            <button class="action-btn" id="exportTraineesBtn"><i class="fas fa-users"></i><span>Export Trainees</span></button>
            <button class="action-btn" id="exportJobsBtn"><i class="fas fa-briefcase"></i><span>Export Jobs</span></button>
            <button class="action-btn" id="exportAnnouncementsBtn"><i class="fas fa-bullhorn"></i><span>Export Announcements</span></button>
            <button class="action-btn" id="exportNotificationsBtn"><i class="fas fa-bell"></i><span>Export Notifications</span></button>
            <button class="action-btn purple" id="exportAllBtn"><i class="fas fa-archive"></i><span>Export All (ZIP)</span></button>
        </div>
    </div>

                    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab active" data-tab="grades">
                <i class="fas fa-award"></i>
                <span>Course Grades</span>
            </button>
            <button id="refreshGradesBtn" class="action-btn grades-refresh-btn">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
        </div>

        <div class="tab-content">
            <!-- Grades Navigation -->
            <div class="grades-navigation">
                <button id="backToCoursesBtn" class="action-btn" style="display: none; margin-bottom: 1rem;">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Courses</span>
                </button>
            </div>

                            <!-- Search -->
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <label for="gradesSearchInput" class="sr-only">Search grades</label>
                    <input type="text" id="gradesSearchInput" placeholder="Search..." class="search-input">
                </div>
            </div>

            <!-- Courses View -->
            <div id="coursesView" class="grades-view">
                <div class="courses-grid" id="coursesGrid">
                    <!-- Courses will be populated dynamically -->
                </div>
            </div>

            <!-- Students View -->
            <div id="studentsView" class="grades-view" style="display: none;">
            <div class="table-container">
                <table class="data-table" id="adminGradesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Average Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                        <tbody id="gradesTableBody">
                            <!-- Students will be populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Graduates Section -->
<section class="page-section" id="graduates">
    <div class="section-header">
    </div>

    <!-- Filter System -->
    <div class="filter-container graduates-filter-container">
        <div class="graduates-filter-header">
            <h3 class="section-subtitle graduates-filter-title">
                <i class="fas fa-filter"></i>
                Filter Graduates
            </h3>
            </div>
            
        <div class="graduates-filter-content">
            <!-- First Row -->
            <div class="graduates-filter-row">
                <div class="graduates-filter-group">
                    <label for="graduateNameFilter" class="graduates-filter-label">
                        <i class="fas fa-user"></i>
                        Student Name
                    </label>
                    <input type="text" id="graduateNameFilter" placeholder="Search by name..." class="graduates-filter-input">
            </div>
            
                <div class="graduates-filter-group">
                    <label for="graduateIdFilter" class="graduates-filter-label">
                        <i class="fas fa-id-card"></i>
                        Student ID
                    </label>
                    <input type="text" id="graduateIdFilter" placeholder="Search by ID..." class="graduates-filter-input">
                </div>
                
                <div class="graduates-filter-group">
                    <label for="graduateCourseFilter" class="graduates-filter-label">
                        <i class="fas fa-graduation-cap"></i>
                        Course
                    </label>
                <select id="graduateCourseFilter" class="graduates-filter-select">
                    <option value="">All Courses</option>
                </select>
                </div>
            </div>
            
            <!-- Second Row -->
            <div class="graduates-filter-row">
                <div class="graduates-filter-group">
                    <label for="graduateMonthFilter" class="graduates-filter-label">
                        <i class="fas fa-calendar-alt"></i>
                        Month
                    </label>
                <select id="graduateMonthFilter" class="graduates-filter-select">
                    <option value="">All Months</option>
                    <option value="01">January</option>
                    <option value="02">February</option>
                    <option value="03">March</option>
                    <option value="04">April</option>
                    <option value="05">May</option>
                    <option value="06">June</option>
                    <option value="07">July</option>
                    <option value="08">August</option>
                    <option value="09">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            
                <div class="graduates-filter-group graduates-year-clear-group">
                    <div class="graduates-filter-field">
                        <label for="graduateYearFilter" class="graduates-filter-label">
                            <i class="fas fa-calendar"></i>
                            Year
                        </label>
                <select id="graduateYearFilter" class="graduates-filter-select">
                    <option value="">All Years</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                    <option value="2021">2021</option>
                    <option value="2020">2020</option>
                </select>
            </div>
            
            <!-- Clear Filters Button -->
                    <button id="clearGraduateFilters" class="graduates-clear-btn">
                        <i class="fas fa-times"></i>
                        <span>Clear Filters</span>
                </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Graduates Table -->
    <div class="table-container">
        <div class="graduates-table-header">
            <div class="graduates-header-content">
                <div class="graduates-title-section">
                    <h3 class="graduates-title">
                        <i class="fas fa-graduation-cap"></i>
                        Graduated Students
                    </h3>
                </div>
                <div class="graduates-actions-section">
                    <div class="graduates-action-buttons">
                        <button class="graduates-action-btn refresh-btn" id="refreshGraduatesBtn">
                            <i class="fas fa-sync-alt"></i>
                            <span>Refresh</span>
                        </button>
                        <button class="graduates-action-btn export-btn" id="exportGraduatesBtn">
                            <i class="fas fa-download"></i>
                            <span>Export Graduates</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="data-table" id="graduatesTable">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Course Completed</th>
                        <th>Graduation Date</th>
                        <th>Status</th>
                                </tr>
                </thead>
                <tbody id="graduatesTableBody">
                    <!-- Data will be loaded dynamically from database -->
                            <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-spinner fa-spin"></i> Loading graduates...
                        </td>
                            </tr>
                    </tbody>
                </table>
        </div>
        
        <!-- Pagination -->
        <div class="pagination-container" style="display: flex; justify-content: center; margin-top: 1.5rem;">
            <div class="pagination">
                <button class="pagination-btn" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="pagination-info" style="margin: 0 1rem; color: #666; font-size: 0.9rem;">
                    Showing 1-3 of 3 graduates
                </span>
                <button class="pagination-btn" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Admin: Grade Details Modal -->
<div id="adminGradeDetailsModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:780px; width:94%;">
        <h2 style="display:flex;align-items:center;gap:8px;margin-top:0;">
            <i class="fas fa-list"></i>
            Student Grade Details
        </h2>
        <p id="adminGradeDetailsHeader" style="margin:.25rem 0 1rem;opacity:.8;"></p>
        <div class="table-container">
            <table class="data-table" id="adminGradeDetailsTable">
                <thead>
                    <tr>
                        <th>Grade #</th>
                        <th>Component</th>
                        <th>Date Given</th>
                        <th>Raw Score</th>
                        <th>Total Items</th>
                        <th>Transmuted</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:1rem;gap:.5rem;">
            <button id="adminGradeDetailsClose" class="modal-btn cancel">
                <i class="fas fa-times"></i>
                Close
            </button>
        </div>
    </div>
    <style>
        /* simple modal fallback using existing admin.css variables */
			#adminGradeDetailsModal.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;}
			#adminGradeDetailsModal .modal-content{background:var(--card-background, #fff);color:var(--card-text-color, #111);margin:0;padding:20px;border-radius:14px;width:100%;max-width:min(780px, 94vw) !important;max-height:80vh;overflow:hidden;border:1px solid var(--table-border-color);box-shadow:0 20px 60px rgba(0,0,0,.2);}           

			/* Theme variables for this modal */
			body[data-theme="light"]{--card-background:#f8f9fa;--card-text-color:#2B2B2B;--table-border-color:#e0e0e0;}
			body[data-theme="dark"]{--card-background:#2B2B2B;--card-text-color:#ffffff;--table-border-color:#444444;}

			/* Ensure table inside modal follows theme */
			#adminGradeDetailsModal .table-container{max-height:65vh;overflow:auto;border-radius:12px;border:1px solid var(--table-border-color);background:var(--card-background);}           
			#adminGradeDetailsModal table.data-table{background:var(--card-background);color:var(--card-text-color);border-collapse:collapse;width:100%;}
			#adminGradeDetailsModal table.data-table th,
			#adminGradeDetailsModal table.data-table td{border-bottom:1px solid var(--table-border-color);color:var(--card-text-color);} 

			/* Light mode: show full grid lines for clarity */
			body[data-theme="light"] #adminGradeDetailsModal table.data-table {
				border: 1px solid var(--table-border-color);
			}
			body[data-theme="light"] #adminGradeDetailsModal table.data-table th,
			body[data-theme="light"] #adminGradeDetailsModal table.data-table td {
				border-right: 1px solid var(--table-border-color);
			}
			body[data-theme="light"] #adminGradeDetailsModal table.data-table tr:last-child td {
				border-bottom: 1px solid var(--table-border-color);
			}
			#adminGradeDetailsModal table.data-table th{background:transparent;}
			#adminGradeDetailsModal h2{color:var(--card-text-color);margin:0 0 8px 0;padding-bottom:8px;border-bottom:1px solid var(--table-border-color);}           
			#adminGradeDetailsModal p{color:var(--card-text-color);}
        .modules-empty{ text-align:center; opacity:.75; }

			/* Responsive padding for small screens */
			@media (max-width: 640px){
				#adminGradeDetailsModal .modal-content{padding:16px;border-radius:12px;max-width:min(92vw, 720px) !important;}
			}
    </style>
</div>

                <!-- Job Matching Section -->
                <section class="page-section" id="job-matching">
                    <div class="section-header">
                        <h2 class="section-title">Job Matching</h2>
                        <p class="section-description">AI-powered job matching system to connect trainees with suitable
                            opportunities</p>
                    </div>

                    <!-- Filters -->
                    <div class="filters-container">
                        <select class="filter-select" id="locationFilter" name="location">
                            <option value="">All Locations</option>
                            <!-- Options will be populated dynamically based on available jobs -->
                        </select>
                        <select class="filter-select" id="experienceFilter" name="experience">
                            <option value="">All Experience Levels</option>
                            <!-- Options will be populated dynamically based on available jobs -->
                        </select>
                        
                        <!-- New Add Jobs Button -->
                        <button class="add-jobs-btn" id="addJobsBtn">
                            <i class="fas fa-plus"></i>
                            <span>Add Jobs</span>
                        </button>
                        
                        <!-- New Confirm/Reject NC2 Validation Button -->
                        <button class="validate-nc2-btn" id="validateNc2Btn">
                            <i class="fas fa-check-circle"></i>
                            <span>Confirm/Reject NC2 Validation</span>
                        </button>
                    </div>

                    <!-- Add Jobs Modal with External Icons -->
                    <div id="addJobsModal" class="modal-overlay" style="display:none;">
                        <div class="modal-content add-jobs-modal-content">
                            <h2><i class="fas fa-plus" style="color: var(--green-500); margin-right: 0.5rem;"></i>Add
                                New Job</h2>
                            <form id="addJobsForm" autocomplete="off">
                                <!-- Course Dropdown -->
                                <div class="add-input-with-external-icon">
                                    <i class="fas fa-book-open add-external-icon" style="color: var(--blue-600);"></i>
                                    <select name="course" required>
                                        <option value="" disabled selected>Select Course</option>
                                        <option value="AUTOMOTIVE SERVICING (ATS)">AUTOMOTIVE SERVICING (ATS)</option>
                                        <option value="BASIC COMPUTER LITERACY (BCL)">BASIC COMPUTER LITERACY (BCL)</option>
                                        <option value="BEAUTY CARE (NAIL CARE) (BEC)">BEAUTY CARE (NAIL CARE) (BEC)</option>
                                        <option value="BREAD AND PASTRY PRODUCTION (BPP)">BREAD AND PASTRY PRODUCTION (BPP)</option>
                                        <option value="COMPUTER SYSTEMS SERVICING (CSS)">COMPUTER SYSTEMS SERVICING (CSS)</option>
                                        <option value="DRESSMAKING (DRM)">DRESSMAKING (DRM)</option>
                                        <option value="ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)">ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)</option>
                                        <option value="ELECTRONIC PRODUCTS AND ASSEMBLY SERVICING (EPAS)">ELECTRONIC PRODUCTS AND ASSEMBLY SERVICING (EPAS)</option>
                                        <option value="EVENTS MANAGEMENT SERVICES (EVM)">EVENTS MANAGEMENT SERVICES (EVM)</option>
                                        <option value="FOOD AND BEVERAGE SERVICES (FBS)">FOOD AND BEVERAGE SERVICES (FBS)</option>
                                        <option value="FOOD PROCESSING (FOP)">FOOD PROCESSING (FOP)</option>
                                        <option value="HAIRDRESSING (HDR)">HAIRDRESSING (HDR)</option>
                                        <option value="HOUSEKEEPING (HSK)">HOUSEKEEPING (HSK)</option>
                                        <option value="MASSAGE THERAPY (MAT)">MASSAGE THERAPY (MAT)</option>
                                        <option value="RAC SERVICING (RAC)">RAC SERVICING (RAC)</option>
                                        <option value="SHIELDED METAL ARC WELDING (SMAW)">SHIELDED METAL ARC WELDING (SMAW)</option>
                                    </select>
                                </div>

                                <!-- Job Title Input -->
                                <div class="add-input-with-external-icon">
                                    <i class="fas fa-briefcase add-external-icon" style="color: var(--blue-500);"></i>
                                    <label for="addJobTitle" class="sr-only">Job Position/Title</label>
                                    <input type="text" id="addJobTitle" name="jobTitle" placeholder="Job Position/Title" required>
                                </div>

                                <!-- Company Name Input -->
                                <div class="add-input-with-external-icon">
                                    <i class="fas fa-building add-external-icon" style="color: var(--purple-500);"></i>
                                    <label for="addCompanyName" class="sr-only">Company Name</label>
                                    <input type="text" id="addCompanyName" name="companyName" placeholder="Company Name" required>
                                </div>

                                <!-- Location Input -->
                                <div class="add-input-with-external-icon">
                                    <i class="fas fa-map-marker-alt add-external-icon"
                                        style="color: var(--green-500);"></i>
                                    <label for="addLocation" class="sr-only">Location</label>
                                    <input type="text" id="addLocation" name="location" placeholder="Location" required>
                                </div>

                                <!-- Salary Input -->
                                <div class="add-input-with-external-icon">
                                    <i class="fas fa-dollar-sign add-external-icon"
                                        style="color: var(--yellow-500);"></i>
                                    <label for="addSalary" class="sr-only">Salary Range</label>
                                    <input type="text" id="addSalary" name="salary"
                                        placeholder="Salary Range (e.g., ₱25,000 - ₱35,000/month)" required>
                                </div>

                                <!-- Experience Dropdown -->
                                <div class="add-input-with-external-icon">
                                    <i class="fas fa-clock add-external-icon" style="color: var(--orange-500);"></i>
                                    <select name="experience" required>
                                        <option value="" disabled selected>Experience Required</option>
                                        <option value="No experience needed">No experience needed</option>
                                        <option value="1-2 years experience needed">1-2 years experience needed</option>
                                        <option value="3 or more years experience needed">3 or more years experience needed</option>
                                    </select>
                                </div>

                                <!-- Description Textarea -->
                                <div class="add-textarea-with-external-icon">
                                    <i class="fas fa-align-left add-external-icon" style="color: var(--blue-600);"></i>
                                    <label for="addDescription" class="sr-only">Additional Description</label>
                                    <textarea id="addDescription" name="description" placeholder="Additional Description"
                                        rows="4"></textarea>
                                </div>

                                <div style="display:flex; justify-content:center; gap:1rem; margin-top:1rem;">
                                    <button type="submit" class="modal-btn confirm add-btn">
                                        <i class="fas fa-plus" style="margin-right: 0.5rem;"></i>Add Job
                                    </button>
                                    <button type="button" class="modal-btn cancel" id="cancelAddJobs">
                                        <i class="fas fa-times" style="margin-right: 0.5rem;"></i>Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- NC2 Validation Requests Modal -->
                    <div id="nc2ValidationModal" class="modal-overlay" style="display:none;">
                        <div class="modal-content" style="max-width:720px; width:94%; max-height:80vh; display:flex; flex-direction:column;">
                            <h2 style="display:flex;align-items:center;justify-content:center;gap:.5rem;margin:0 0 12px 0;">
                                <i class="fas fa-check-circle" style="color: var(--blue-600);"></i>
                                <span id="nc2ModalHeadingText" style="color: var(--blue-600);">Confirm/Reject NC2 Validation</span>
                            </h2>
                            <!-- List container: scroll if too long -->
                            <div id="nc2RequestsList" class="table-container" style="flex:1; overflow:auto; border:1px solid var(--border); border-radius:12px;">
                                <!-- Requests will be rendered here dynamically -->
                                <div style="padding:16px; color: var(--muted-foreground);">Loading pending requests…</div>
                            </div>
                            <div style="display:flex; justify-content:flex-end; gap:.5rem; margin-top:12px;">
                                <button type="button" class="modal-btn" id="viewNc2Pending">
                                    <i class="fas fa-list-check" style="margin-right:.5rem;"></i>Confirm/Reject
                                </button>
                                <button type="button" class="modal-btn" id="viewNc2History">
                                    <i class="fas fa-clock-rotate-left" style="margin-right:.5rem;"></i>History
                                </button>
                                <button type="button" class="modal-btn cancel" id="closeNc2Validation">
                                    <i class="fas fa-times" style="margin-right:.5rem;"></i>Close
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Job Modal with External Icons (Fixed) -->
                    <div id="editJobModal" class="modal-overlay" style="display:none;">
                        <div class="modal-content edit-job-modal-content">
                            <h2><i class="fas fa-edit" style="color: var(--orange-500); margin-right: 0.5rem;"></i>Edit
                                Job Details</h2>
                            <form id="editJobForm" autocomplete="off">
                                <!-- Job Title Input -->
                                <div class="edit-input-with-external-icon">
                                    <i class="fas fa-briefcase edit-external-icon" style="color: var(--blue-500);"></i>
                                    <label for="editJobTitle" class="sr-only">Job Position/Title</label>
                                    <input type="text" id="editJobTitle" name="jobTitle"
                                        placeholder="Job Position/Title" required>
                                </div>

                                <!-- Company Name Input -->
                                <div class="edit-input-with-external-icon">
                                    <i class="fas fa-building edit-external-icon" style="color: var(--purple-500);"></i>
                                    <label for="editCompanyName" class="sr-only">Company Name</label>
                                    <input type="text" id="editCompanyName" name="companyName"
                                        placeholder="Company Name" required>
                                </div>

                                <!-- Location Input -->
                                <div class="edit-input-with-external-icon">
                                    <i class="fas fa-map-marker-alt edit-external-icon"
                                        style="color: var(--green-500);"></i>
                                    <label for="editLocation" class="sr-only">Location</label>
                                    <input type="text" id="editLocation" name="location" placeholder="Location"
                                        required>
                                </div>

                                <!-- Salary Input -->
                                <div class="edit-input-with-external-icon">
                                    <i class="fas fa-dollar-sign edit-external-icon"
                                        style="color: var(--yellow-500);"></i>
                                    <label for="editSalary" class="sr-only">Salary Range</label>
                                    <input type="text" id="editSalary" name="salary"
                                        placeholder="Salary Range (e.g., ₱25,000 - ₱35,000/month)" required>
                                </div>

                                <!-- Experience Input -->
                                <div class="edit-input-with-external-icon">
                                    <i class="fas fa-clock edit-external-icon" style="color: var(--orange-500);"></i>
                                    <select id="editExperience" name="experience" required>
                                        <option value="">Experience Required</option>
                                        <option value="No experience needed">No experience needed</option>
                                        <option value="1-2 years experience needed">1-2 years experience needed</option>
                                        <option value="3 or more years experience needed">3 or more years experience needed</option>
                                    </select>
                                </div>

                                <!-- Description Textarea -->
                                <div class="edit-textarea-with-external-icon">
                                    <i class="fas fa-align-left edit-external-icon" style="color: var(--blue-600);"></i>
                                    <label for="editDescription" class="sr-only">Additional Description</label>
                                    <textarea id="editDescription" name="description"
                                        placeholder="Additional Description" rows="4"></textarea>
                                </div>

                                <div style="display:flex; justify-content:center; gap:1rem; margin-top:1rem;">
                                    <button type="submit" class="modal-btn confirm save-btn">
                                        <i class="fas fa-save" style="margin-right: 0.5rem;"></i>Save Changes
                                    </button>
                                    <button type="button" class="modal-btn cancel" id="cancelEditJob">
                                        <i class="fas fa-times" style="margin-right: 0.5rem;"></i>Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Job Card Delete Confirmation Modal -->
                    <div id="deleteJobModal" class="modal-overlay" style="display:none;">
                        <div class="modal-content">
                            <h2>Confirm Delete</h2>
                            <p>Are you sure you want to delete this job card?</p>
                            <div style="margin-top:2rem;">
                                <button type="button" class="modal-btn confirm" id="confirmDeleteJobBtn">Yes,
                                    Delete</button>
                                <button type="button" class="modal-btn cancel" id="cancelDeleteJobBtn">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <!-- Job Count and Cards -->
                    <div class="job-results-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div class="job-count" style="color: #666; font-size: 0.9rem;">Loading jobs...</div>
                    </div>
                    <div class="job-cards-grid"></div>
                </section>

                <!-- Course & Enrollment Settings -->
                <section class="page-section" id="course_settings">
                    <div class="section-header">
                        <div class="section-header-content">
                            <div class="section-title-wrapper">
                                <i class="fas fa-cogs section-icon"></i>
                                <h2 class="section-title">Enrollment Settings</h2>
                            </div>
                            <p class="section-description">Manage course configurations, enrollment status, and student assignments</p>
                        </div>
                    </div>
                    
                    <div class="enrollment-dashboard">
                        <!-- Courses Management Card -->
                        <div class="enrollment-card courses-card">
                            <div class="card-header">
                                <div class="card-title-wrapper">
                                    <i class="fas fa-graduation-cap card-icon"></i>
                                    <h3 class="card-title">Course Management</h3>
                                </div>
                                <div class="card-header-actions">
                                    <button id="deleteCourseBtn" class="delete-course-btn">
                                        <i class="fas fa-trash"></i>
                                        <span>Delete Course</span>
                                    </button>
                                    <button id="addCourseBtn" class="add-course-btn">
                                        <i class="fas fa-plus"></i>
                                        <span>Add New Course</span>
                                    </button>
                                    <div class="card-stats">
                                        <span class="stat-item">
                                            <i class="fas fa-book"></i>
                                            <span id="totalCourses">-</span> Courses
                                        </span>
                                        <span class="stat-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span id="activeCourses">-</span> Active
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="table-wrapper">
                                    <div class="table-container">
                                        <div class="single-course-management">
                                            <div class="course-selection-row">
                                                <div class="form-group">
                                                    <label for="courseSelection" class="form-label">
                                                        <i class="fas fa-graduation-cap"></i>
                                                        Select Course to Manage
                                                    </label>
                                                    <select id="courseSelection" class="form-select course-selection-dropdown">
                                                        <option value="">Choose a course to manage...</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="course-details-row" id="courseDetailsRow" style="display: none;">
                                                <div class="course-details-grid">
                                                    <div class="detail-item">
                                                        <label class="detail-label" for="selectedCourseCode">Course Code</label>
                                                        <input type="text" id="selectedCourseCode" class="form-input" readonly>
                                                    </div>
                                                    
                                                    <div class="detail-item">
                                                        <label class="detail-label" for="selectedCourseStatus">Status</label>
                                                        <select id="selectedCourseStatus" class="form-select">
                                                            <option value="upcoming">Upcoming</option>
                                                            <option value="ongoing">Ongoing</option>
                                                            <option value="completed">Completed</option>
                                                            <option value="cancelled">Cancelled</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="detail-item">
                                                        <label class="detail-label" for="selectedCourseStartDate">Start Date</label>
                                                        <input type="date" id="selectedCourseStartDate" class="form-input">
                                                    </div>
                                                    
                                                    <div class="detail-item">
                                                        <label class="detail-label" for="selectedCourseEndDate">End Date</label>
                                                        <input type="date" id="selectedCourseEndDate" class="form-input">
                                                    </div>
                                                    
                                                    <div class="detail-item">
                                                        <label class="detail-label" for="selectedCourseDuration">Duration (Days)</label>
                                                        <input type="number" id="selectedCourseDuration" class="form-input" min="1" placeholder="90">
                                                    </div>
                                                    
                                                    <div class="detail-item">
                                                        <label class="detail-label" for="selectedCourseActive">Active</label>
                                                        <select id="selectedCourseActive" class="form-select">
                                                            <option value="1">Yes</option>
                                                            <option value="0">No</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="detail-item actions-item">
                                                        <span class="detail-label">Actions</span>
                                                        <div class="action-buttons">
                                                            <button id="saveCourseChanges" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-save"></i> Save Changes
                                                            </button>
                                                            <button id="resetCourseForm" class="btn btn-secondary btn-sm">
                                                                <i class="fas fa-undo"></i> Reset
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enrollments Management Card -->
                        <div class="enrollment-card enrollments-card">
                            <div class="card-header">
                                <div class="card-title-wrapper">
                                    <i class="fas fa-users card-icon"></i>
                                    <h3 class="card-title">Student Enrollments</h3>
                                </div>
                                <div class="card-stats">
                                    <span class="stat-item">
                                        <i class="fas fa-user-graduate"></i>
                                        <span id="totalEnrollments">-</span> Enrolled
                                    </span>
                                    <span class="stat-item">
                                        <i class="fas fa-clock"></i>
                                        <span id="pendingEnrollments">-</span> Pending
                                    </span>
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="table-wrapper">
                                    <div class="table-header">
                                        <div class="table-actions">
                                            <!-- Separate Filter Controls -->
                                            <div class="separate-filters-container">
                                                <!-- Search Box -->
                                                <div class="filter-control search-control">
                                                    <label for="enrollmentSearch" class="filter-label">
                                                        <i class="fas fa-search"></i> Search
                                                    </label>
                                                    <div class="search-input-wrapper">
                                                        <input type="text" id="enrollmentSearch" class="filter-input search-input" placeholder="Search by student name, number, or course...">
                                                        <button type="button" id="clearSearchBtn" class="search-clear-btn" style="display: none;">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status Filter -->
                                                <div class="filter-control">
                                                    <label for="enrollmentStatusFilter" class="filter-label">
                                                        <i class="fas fa-tag"></i> Status
                                                    </label>
                                                    <select id="enrollmentStatusFilter" class="filter-select">
                                                        <option value="">All Status</option>
                                                        <option value="enrolled">Enrolled</option>
                                                        <option value="pending">Pending</option>
                                                        <option value="completed">Completed</option>
                                                        <option value="withdrawn">Withdrawn</option>
                                                    </select>
                                                </div>
                                                
                                                <!-- Course Filter -->
                                                <div class="filter-control">
                                                    <label for="enrollmentCourseFilter" class="filter-label">
                                                        <i class="fas fa-graduation-cap"></i> Course
                                                    </label>
                                                    <select id="enrollmentCourseFilter" class="filter-select">
                                                        <option value="">All Courses</option>
                                                    </select>
                                                </div>
                                                
                                                <!-- Start Date From Filter -->
                                                <div class="filter-control">
                                                    <label for="enrollmentDateFrom" class="filter-label">
                                                        <i class="fas fa-calendar-alt"></i> Start Date From
                                                    </label>
                                                    <input type="date" id="enrollmentDateFrom" class="filter-input">
                                                </div>
                                                
                                                <!-- Start Date To Filter -->
                                                <div class="filter-control">
                                                    <label for="enrollmentDateTo" class="filter-label">
                                                        <i class="fas fa-calendar-alt"></i> Start Date To
                                                    </label>
                                                    <input type="date" id="enrollmentDateTo" class="filter-input">
                                                </div>
                                                
                                                <!-- Filter Actions -->
                                                <div class="filter-actions">
                                                    <button type="button" id="clearAllFiltersBtn" class="btn btn-secondary btn-sm">
                                                        <i class="fas fa-times"></i> Clear All
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Active Filters Display -->
                                            <div class="active-filters" id="activeFiltersDisplay" style="display: none;">
                                                <span class="active-filters-label">Active Filters:</span>
                                                <div class="active-filters-list" id="activeFiltersList"></div>
                                            </div>
                                            
                                        </div>
                                    </div>
                                    <div class="table-container scrollable-table-container">
                                        <!-- Scroll indicator -->
                                        <div class="scroll-indicator" id="scrollIndicator" style="display: none;">
                                            <i class="fas fa-chevron-down"></i>
                                            <span>Scroll to see more</span>
                                        </div>
                                        
                                        <div class="table-scroll-wrapper" id="enrollmentsScrollWrapper">
                                            <table class="modern-table" role="table" aria-label="Enrollments">
                                                <thead class="sticky-header">
                                                    <tr>
                                                        <th class="col-student">Student #</th>
                                                        <th class="col-name">Student Name</th>
                                                        <th class="col-course">Course</th>
                                                        <th class="col-status">Status</th>
                                                        <th class="col-date">Start Date</th>
                                                        <th class="col-date">End Date</th>
                                                        <th class="col-actions">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="enrollmentsTableBody">
                                                    <tr class="loading-row">
                                                        <td colspan="7" class="loading-cell">
                                                            <div class="loading-spinner">
                                                                <i class="fas fa-spinner fa-spin"></i>
                                                                <span>Loading enrollments...</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Table info -->
                                        <div class="table-info">
                                            <span class="table-info-text" id="enrollmentsTableInfo">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Enrollment Form -->
                        <div class="enrollment-card quick-enroll-card">
                            <div class="card-header">
                                <div class="card-title-wrapper">
                                    <i class="fas fa-user-plus card-icon"></i>
                                    <h3 class="card-title">Quick Enrollment</h3>
                                </div>
                            </div>
                            <div class="card-content">
                                <form class="enrollment-form" id="quickEnrollForm">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="enrollStudentNumber" class="form-label">
                                                <i class="fas fa-id-card"></i>
                                                Student Number
                                            </label>
                                            <input type="text" id="enrollStudentNumber" class="form-input" placeholder="Enter student number" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="enrollCourseCode" class="form-label">
                                                <i class="fas fa-graduation-cap"></i>
                                                Course Code
                                            </label>
                                            <select id="enrollCourseCode" class="form-select" required>
                                                <option value="">Select Course Code</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="enrollStartDate" class="form-label">
                                                <i class="fas fa-calendar-alt"></i>
                                                Start Date
                                            </label>
                                            <input type="date" id="enrollStartDate" class="form-input" required>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" id="enrollStudentBtn" class="btn btn-primary btn-enroll">
                                            <i class="fas fa-user-plus"></i>
                                            <span>Enroll Student</span>
                                        </button>
                                        <button type="button" id="clearEnrollForm" class="btn btn-secondary">
                                            <i class="fas fa-times"></i>
                                            <span>Clear</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Career Analytics Section -->
                <section class="page-section" id="career">
                    <div class="section-header">
                        <h2 class="section-title">Career Analytics</h2>
                        <p class="section-description">Comprehensive analytics on trainee career progression and
                            employment outcomes</p>
                        <div style="margin-top: 10px; display: flex; gap: 10px;">
                            <button id="importCsvBtn" class="btn btn-success">
                                <i class="fas fa-file-import"></i> Import CSV Data
                            </button>
                            <button id="deleteCsvBtn" class="btn btn-warning">
                                <i class="fas fa-trash-alt"></i> Clear All Data
                            </button>
                        </div>
                        <div style="margin-top: 8px; font-size: 0.9rem; color: #666;">
                            <i class="fas fa-info-circle"></i> Import CSV files for: Employment Trends, Course Trends, Job Trends, Graduates Analytics, and Industry Analytics
                        </div>
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
                        $pct = (($latest - $prev) / $prev) * 100.0;
                        $sign = $pct >= 0 ? '+' : '';
                        $careerAnalytics['trendText'] = $sign . number_format($pct, 1) . '% vs last year';
                    } else {
                        $careerAnalytics['trendText'] = 'N/A vs last year';
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
                $perCoursePerYear = [];
                $allCoursesData = [];
                
                while (($row = fgetcsv($h)) !== false) {
                    if (!is_array($row)) { continue; }
                    $courseCode = (string)($row[$idx['course_code']] ?? '');
                    $courseName = (string)($row[$idx['course_name']] ?? '');
                    if ($courseCode === '') { continue; }
                    $year = (int)($row[$idx['year']] ?? 0);
                    if ($year <= 0) { continue; }
                    $rate = (float)($row[$idx['employment_rate']] ?? 0);
                    
                    if (!isset($perCoursePerYear[$courseCode])) { 
                        $perCoursePerYear[$courseCode] = [
                            'name' => $courseName,
                            'data' => []
                        ]; 
                    }
                    $perCoursePerYear[$courseCode]['data'][$year] = $rate;
                    
                    // For combined analysis
                    if (!isset($allCoursesData[$year])) { $allCoursesData[$year] = 0; }
                    $allCoursesData[$year] += $rate;
                }

                $targetYear = 2026;
                $predictions = [];
                
                // Add combined courses prediction
                
                // Individual course predictions
                foreach ($perCoursePerYear as $courseCode => $courseInfo) {
                    $map = $courseInfo['data'];
                    ksort($map);
                    $years = array_keys($map);
                    $values = array_values($map);
                    $n = count($years);
                    
                    if ($n >= 2) {
                        $recent = (float)$values[$n - 1];
                        
                        // Linear regression for prediction
                        $x0 = $years[0];
                        $sumX = 0.0; $sumY = 0.0; $sumXX = 0.0; $sumXY = 0.0;
                        for ($i = 0; $i < $n; $i++) {
                            $xi = (float)($years[$i] - $x0); $yi = (float)$values[$i];
                            $sumX += $xi; $sumY += $yi; $sumXX += $xi*$xi; $sumXY += $xi*$yi;
                        }
                        $den = ($n * $sumXX - $sumX * $sumX);
                        $slope = $den != 0.0 ? (($n * $sumXY - $sumX * $sumY) / $den) : 0.0;
                        
                        $predVal = $recent + $slope;
                        // Apply realistic bounds (30-100%)
                        $predVal = max(30.0, min(100.0, $predVal));
                        
                        $predictions[] = [
                            'course_code' => $courseCode,
                            'course_name' => $courseInfo['name'],
                            'prediction_2026' => (float)round($predVal, 1),
                            'years' => array_map('intval', $years),
                            'rates' => array_map('floatval', $values),
                            'latest_rate' => (float)$recent,
                            'change' => (float)round($predVal - $recent, 1)
                        ];
                    }
                }
                
                // Sort by prediction (descending)
                usort($predictions, function($a,$b){ return $b['prediction_2026'] <=> $a['prediction_2026']; });
                
                // Calculate overall employment rate and trend from top course
                if (!empty($predictions)) {
                    $topCourse = $predictions[0];
                    $overallEmploymentRate = round($topCourse['prediction_2026'], 0);
                    $change = $topCourse['change'];
                    $trendDirection = $change >= 0 ? '+' : '';
                    $employmentTrend = $trendDirection . $change . '% from 2025';
                }
                
                $employmentData = [
                    'predictions' => $predictions,
                    'top_course' => isset($predictions[0]) ? $predictions[0] : null
                ];
            }
            fclose($h);
        }
    }
?>

                    <!-- Analytics Cards -->
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
                    for ($i = max(1, $n - 3); $i < $n; $i++) { $diffs[] = (float)$values[$i] - (float)$values[$i - 1]; }
                    $avgDiff = !empty($diffs) ? array_sum($diffs) / count($diffs) : 0.0;

                    // Linear slope over years (normalized), then blend with avgDiff for robustness
                    $x0 = $years[0];
                    $sumX = 0.0; $sumY = 0.0; $sumXX = 0.0; $sumXY = 0.0;
                    for ($i = 0; $i < $n; $i++) {
                        $xi = (float)($years[$i] - $x0); $yi = (float)$values[$i];
                        $sumX += $xi; $sumY += $yi; $sumXX += $xi*$xi; $sumXY += $xi*$yi;
                    }
                    $den = ($n * $sumXX - $sumX * $sumX);
                    $slope = $den != 0.0 ? (($n * $sumXY - $sumX * $sumY) / $den) : 0.0;

                    // Blend: 60% regression slope step, 40% recent average diff step
                    $step = 0.6 * $slope + 0.4 * $avgDiff;
                    // Clamp extreme steps relative to recent level to avoid spikes
                    $maxStep = max(5.0, 0.5 * max(1.0, $recent));
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
                                    <option value="1">First Half (H1)</option>
                                    <option value="2">Second Half (H2)</option>
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

                    
                    <!-- 2025 Course Popularity (CSV-driven) -->
                    <div class="analytics-grid" style="grid-template-columns:1fr;gap:16px;">
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

                </section>

                <!-- CSV Import Modal -->
                <div id="csvImportModal" class="modal" style="display: none;">
                    <div class="modal-content" style="max-width: 500px;">
                        <div class="modal-header">
                            <h3 class="modal-title">
                                <i class="fas fa-file-csv"></i> Import CSV Data for Analytics
                            </h3>
                            <button class="modal-close" id="closeCsvModal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="csvImportForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="chartTypeSelect" class="form-label">
                                        <i class="fas fa-chart-bar"></i> Select Chart Type
                                    </label>
                                    <select id="chartTypeSelect" name="chart_type" class="form-input" required>
                                        <option value="">Choose chart type...</option>
                                        <option value="employment">Employment Rate Charts</option>
                                        <option value="employment_trend">Employment Trend Analysis (6-Month)</option>
                                        <option value="course_trends">Course Trends Visualization (6-Month)</option>
                                        <option value="job_trends">Job Trends Visualization (6-Month)</option>
                                        <option value="graduates">Graduates Analytics</option>
                                        <option value="graduates_course_popularity">Course Popularity 2025</option>
                                        <option value="industry">Industry Analytics</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="csvFileInput" class="form-label">
                                        <i class="fas fa-upload"></i> Select CSV File
                                    </label>
                                    <input type="file" id="csvFileInput" name="csv_file" accept=".csv" class="form-input" required>
                                    <small class="form-help">Supported format: CSV files only</small>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" class="btn btn-secondary" id="cancelCsvImport">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="submitCsvImport">
                                        <i class="fas fa-upload"></i> Import CSV
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Data Modal -->
                <div id="deleteDataModal" class="modal" style="display: none;">
                    <div class="modal-content" style="max-width: 400px;">
                        <div class="modal-header">
                            <h3 class="modal-title">
                                <i class="fas fa-exclamation-triangle"></i> Delete Data
                            </h3>
                            <button class="modal-close" id="closeDeleteModal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="deleteChartTypeSelect" class="form-label">
                                    <i class="fas fa-chart-bar"></i> Select Data to Delete
                                </label>
                                <select id="deleteChartTypeSelect" class="form-input" required>
                                    <option value="">Choose data type...</option>
                                    <option value="employment">Employment Data</option>
                                    <option value="graduates">Graduates Data</option>
                                    <option value="graduates_course_popularity">Graduates Course Popularity Data</option>
                                    <option value="industry">Industry Data</option>
                                    <option value="all">All Data (All CSV files)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <div class="delete-warning" style="background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 6px; color: #dc2626;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Warning:</strong> This action will permanently delete the selected data files. This cannot be undone.
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmDelete">
                                    <i class="fas fa-trash"></i> Delete Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
<!-- Add Trainees Section -->
<section class="page-section" id="add-trainees">
    <div class="section-header">
        <h2 class="section-title">Add New Trainee</h2>
        <p class="section-description">Register new trainees into the system with their basic information</p>
    </div>

    <!-- Trainee Registration Form -->
    <div class="trainee-form-container">
        <form id="traineeForm" class="trainee-form">
            <!-- Surname -->
            <div class="form-group">
                <label for="traineeSurname" class="form-label">
                    <i class="fas fa-user"></i>
                    Surname
                </label>
                <input 
                    type="text" 
                    id="traineeSurname" 
                    name="surname" 
                    class="form-input" 
                    placeholder="Enter trainee's surname..."
                    required
                >
            </div>

            <!-- First Name -->
            <div class="form-group">
                <label for="traineeFirstname" class="form-label">
                    <i class="fas fa-user-circle"></i>
                    First Name
                </label>
                <input 
                    type="text" 
                    id="traineeFirstname" 
                    name="firstname" 
                    class="form-input" 
                    placeholder="Enter trainee's first name..."
                    required
                >
            </div>

            <!-- Middle Name -->
            <div class="form-group">
                <label for="traineeMiddlename" class="form-label">
                    <i class="fas fa-user"></i>
                    Middle Name (Optional)
                </label>
                <input 
                    type="text" 
                    id="traineeMiddlename" 
                    name="middlename" 
                    class="form-input" 
                    placeholder="Enter trainee's middle name..."
                >
            </div>

            <!-- Student Number -->
            <div class="form-group">
                <label for="traineeStudentNumber" class="form-label">
                    <i class="fas fa-id-card"></i>
                    Student Number
                </label>
                <input 
                    type="text" 
                    id="traineeStudentNumber" 
                    name="student_number" 
                    class="form-input" 
                    placeholder="DJR-90-402-14011-001"
                    pattern="[A-Z]{3}-[0-9]{2}-[0-9]{3}-[0-9]{5}-[0-9]{3}"
                    maxlength="23"
                    oninput="formatStudentNumber(this)"
                    required
                >
                <small class="form-help">This will also be used for account creation</small>
                <div id="studentNumberCaution" class="caution-message" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>This student number already exists in the system</span>
                </div>
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <label for="traineeEmail" class="form-label">
                    <i class="fas fa-envelope"></i>
                    Email Address
                </label>
                <input 
                    type="email" 
                    id="traineeEmail" 
                    name="email" 
                    class="form-input" 
                    placeholder="Enter email address..."
                    required
                >
                <small class="form-help">They will receive a link to set their password</small>
                <div id="emailCaution" class="caution-message" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>This email address already exists in the system</span>
                </div>
            </div>

            <!-- Registration Role -->
            <div class="form-group">
                <div class="form-label">
                    <i class="fas fa-users-cog"></i>
                    Registration Type
                </div>
                <div class="form-input" style="display:flex; gap:16px; align-items:center; padding:8px 12px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="radio" name="role" value="0" checked>
                        <span>Student</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="radio" name="role" value="1">
                        <span>Instructor</span>
                    </label>
                </div>
                <small class="form-help">Choose whether this registration is for a student or an instructor</small>
            </div>

            <!-- Course -->
            <div class="form-group">
                <label for="traineeCourse" class="form-label">
                    <i class="fas fa-book"></i>
                    Course
                </label>
                <select id="traineeCourse" name="course" class="form-select" required>
                    <option value="">Select Course</option>
                </select>
            </div>

            <!-- Batch Selection -->
            <div class="form-group">
                <label for="traineeBatch" class="form-label">
                    <i class="fas fa-layer-group"></i>
                    Batch
                </label>
                <select id="traineeBatch" name="batch" class="form-select" required>
                    <option value="">Select Batch</option>
                    <option value="1">Batch 1 - January to March</option>
                    <option value="2">Batch 2 - April to June</option>
                    <option value="3">Batch 3 - July to September</option>
                    <option value="4">Batch 4 - October to December</option>
                </select>
                <small class="form-help">Select the batch for this trainee</small>
            </div>

            <!-- Date Enrolled -->
            <div class="form-group">
                <label for="traineeEnrollDate" class="form-label">
                    <i class="fas fa-calendar-alt"></i>
                    Date Enrolled
                </label>
                <input 
                    type="date" 
                    id="traineeEnrollDate" 
                    name="enrollDate" 
                    class="form-input"
                    required
                >
                <small class="form-help">Select the date when the trainee enrolled</small>
            </div>

            <!-- Additional Information (Optional) -->
            <div class="form-group">
                <label for="traineeNotes" class="form-label">
                    <i class="fas fa-sticky-note"></i>
                    Additional Notes (Optional)
                </label>
                <textarea 
                    id="traineeNotes" 
                    name="notes" 
                    class="form-textarea" 
                    rows="4"
                    placeholder="Enter any additional information about the trainee..."
                ></textarea>
                <div class="character-count">
                    <span id="notesCharCount">0</span>/500 characters
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="action-btn publish" id="addTraineeBtn">
                    <i class="fas fa-user-plus"></i>
                    Add Trainee
                </button>
                <button type="button" class="action-btn draft" id="saveTraineeDraftBtn">
                    <i class="fas fa-save"></i>
                    Save as Draft
                </button>
                <button type="reset" class="action-btn reset">
                    <i class="fas fa-undo"></i>
                    Clear Form
                </button>
            </div>
        </form>
    </div>

    <!-- Recent Trainees section removed as per LMS requirement -->
</section>

<!-- Edit Trainee Modal -->
<div id="editTraineeModal" class="modal-overlay" style="display:none;">
    <div class="modal-content edit-trainee-modal-content">
        <h2><i class="fas fa-edit" style="color: var(--blue-500); margin-right: 0.5rem;"></i>Edit Trainee Information</h2>
        <form id="editTraineeForm" autocomplete="off">
            <!-- Hidden field for trainee ID -->
            <input type="hidden" id="editTraineeId" name="traineeId">
            
            <!-- Surname -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-user edit-external-icon" style="color: var(--blue-500);"></i>
                <input type="text" id="editTraineeSurname" name="surname" placeholder="Enter trainee's surname..." required>
            </div>

            <!-- First Name -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-user-circle edit-external-icon" style="color: var(--purple-500);"></i>
                <input type="text" id="editTraineeFirstname" name="firstname" placeholder="Enter trainee's first name..." required>
            </div>

            <!-- Middle Name -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-user edit-external-icon" style="color: var(--purple-400);"></i>
                <input type="text" id="editTraineeMiddlename" name="middlename" placeholder="Enter trainee's middle name...">
            </div>

            <!-- Contact Number -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-phone edit-external-icon" style="color: var(--green-500);"></i>
                <input type="tel" id="editTraineeContact" name="contact" placeholder="Enter contact number..." required>
            </div>

            <!-- Course -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-book edit-external-icon" style="color: var(--orange-500);"></i>
                <select id="editTraineeCourse" name="course" required>
                    <option value="">Select Course</option>
                    <option value="Shielded Metal Arc Welding">Shielded Metal Arc Welding (SMAW)</option>
                    <option value="Electrical Installation and Maintenance">Electrical Installation and Maintenance (EIM)</option>
                    <option value="Automotive Servicing">Automotive Servicing (ATS)</option>
                    <option value="Refrigeration and Air Conditioning">Refrigeration and Air Conditioning (RAC)</option>
                    <option value="Computer Systems Servicing">Computer Systems Servicing (CSS)</option>
                    <option value="Plumbing">Plumbing</option>
                    <option value="Masonry">Masonry</option>
                    <option value="Carpentry">Carpentry</option>
                </select>
            </div>

            <!-- Batch Selection -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-layer-group edit-external-icon" style="color: var(--indigo-500);"></i>
                <select id="editTraineeBatch" name="batch" required>
                    <option value="">Select Batch</option>
                    <option value="1">Batch 1 - January to March</option>
                    <option value="2">Batch 2 - April to June</option>
                    <option value="3">Batch 3 - July to September</option>
                    <option value="4">Batch 4 - October to December</option>
                </select>
            </div>

            <!-- Date Enrolled -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-calendar-alt edit-external-icon" style="color: var(--yellow-500);"></i>
                <input type="date" id="editTraineeEnrollDate" name="enrollDate" required>
            </div>

            <!-- Status -->
            <div class="edit-input-with-external-icon">
                <i class="fas fa-flag edit-external-icon" style="color: var(--red-500);"></i>
                <select id="editTraineeStatus" name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="graduated">Graduated</option>
                    <option value="dropped">Dropped</option>
                </select>
            </div>

            <!-- Additional Notes -->
            <div class="edit-textarea-with-external-icon">
                <i class="fas fa-sticky-note edit-external-icon" style="color: var(--blue-600);"></i>
                <textarea id="editTraineeNotes" name="notes" placeholder="Enter any additional information..." rows="4"></textarea>
            </div>

            <div style="display:flex; justify-content:center; gap:1rem; margin-top:1rem;">
                <button type="submit" class="modal-btn confirm save-btn">
                    <i class="fas fa-save" style="margin-right: 0.5rem;"></i>Save Changes
                </button>
                <button type="button" class="modal-btn cancel" id="cancelEditTrainee">
                    <i class="fas fa-times" style="margin-right: 0.5rem;"></i>Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notification Container -->
<div id="notificationContainer" class="notification-container"></div>
                <!-- Add Announcement Section -->
                <section class="page-section" id="add-announcement">
                    <div class="section-header">
                        <h2 class="section-title">Add New Announcement</h2>
                        <p class="section-description">Create and publish announcements for trainees and staff members</p>
                    </div>

                    <!-- Announcement Form -->
                    <div class="announcement-form-container">
                        <form id="announcementForm" class="announcement-form">
                            <!-- Announcement Title -->
                            <div class="form-group">
                                <label for="announcementTitle" class="form-label">
                                    <i class="fas fa-heading"></i>
                                    Announcement Title
                                </label>
                                <input 
                                    type="text" 
                                    id="announcementTitle" 
                                    name="title" 
                                    class="form-input" 
                                    placeholder="Enter announcement title..."
                                    required
                                >
                            </div>

                            <!-- Announcement Type -->
                            <div class="form-group">
                                <label for="announcementType" class="form-label">
                                    <i class="fas fa-tag"></i>
                                    Announcement Type
                                </label>
                                <select id="announcementType" name="type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="general">General Information</option>
                                    <option value="urgent">Urgent Notice</option>
                                    <option value="event">Event/Activity</option>
                                    <option value="academic">Academic Update</option>
                                    <option value="job">Job Opportunity</option>
                                    <option value="maintenance">Maintenance Notice</option>
                                </select>
                            </div>

                            <!-- Priority Level -->
                            <div class="form-group">
                                <label for="priorityLevel" class="form-label">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Priority Level
                                </label>
                                <select id="priorityLevel" name="priority" class="form-select" required>
                                    <option value="">Select Priority</option>
                                    <option value="low">Low Priority</option>
                                    <option value="normal">Normal Priority</option>
                                    <option value="high">High Priority</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>

                            <!-- Target Audience -->
                            <div class="form-group">
                                <label for="targetAudience" class="form-label">
                                    <i class="fas fa-users"></i>
                                    Target Audience
                                </label>
                                <select id="targetAudience" name="audience" class="form-select" required>
                                    <option value="">Select Audience</option>
                                    <option value="all">All Users</option>
                                    <option value="trainees">Trainees Only</option>
                                    <option value="staff">Staff Only</option>
                                    <option value="smaw">SMAW Students</option>
                                    <option value="eim">EIM Students</option>
                                    <option value="ats">ATS Students</option>
                                </select>
                            </div>

                            <!-- Announcement Content -->
                            <div class="form-group">
                                <label for="announcementContent" class="form-label">
                                    <i class="fas fa-align-left"></i>
                                    Announcement Content
                                </label>
                                <textarea 
                                    id="announcementContent" 
                                    name="content" 
                                    class="form-textarea" 
                                    rows="8"
                                    placeholder="Write your announcement content here..."
                                    required
                                ></textarea>
                                <div class="character-count">
                                    <span id="charCount">0</span>/1000 characters
                                </div>
                            </div>

                            

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="submit" class="action-btn publish">
                                    <i class="fas fa-paper-plane"></i>
                                    Publish Announcement
                                </button>
                                <button type="button" class="action-btn draft" id="saveDraftBtn">
                                    <i class="fas fa-save"></i>
                                    Save as Draft
                                </button>
                                <button type="reset" class="action-btn reset">
                                    <i class="fas fa-undo"></i>
                                    Clear Form
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Recent Announcements -->
                    <div class="recent-announcements">
                        <div class="recent-announcements-header" style="display:flex; align-items:center; justify-content: space-between; gap: 12px;">
                            <h3 class="section-subtitle" style="margin: 0;">Recent Announcements</h3>
                            <button id="showAllAnnouncementsBtn" class="action-btn reset" type="button">
                                Show more
                            </button>
                        </div>
                        <div class="announcements-list">
                            <div class="announcement-card">
                                <div class="announcement-header">
                                    <div class="announcement-meta">
                                        <span class="announcement-type general">General</span>
                                        <span class="announcement-priority normal">Normal</span>
                                        <span class="announcement-date">2 hours ago</span>
                                    </div>
                                    <div class="announcement-actions">
                                        <button class="edit-announcement-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="delete-announcement-btn" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <h4 class="announcement-title">Welcome New Batch of SMAW Trainees</h4>
                                <p class="announcement-preview">We are excited to welcome our new batch of SMAW trainees starting this Monday. Orientation will begin at 8:00 AM...</p>
                                <div class="announcement-stats">
                                    <span><i class="fas fa-eye"></i> 156 views</span>
                                    <span><i class="fas fa-users"></i> All Users</span>
                                </div>
                            </div>

                            <div class="announcement-card">
                                <div class="announcement-header">
                                    <div class="announcement-meta">
                                        <span class="announcement-type urgent">Urgent</span>
                                        <span class="announcement-priority high">High</span>
                                        <span class="announcement-date">1 day ago</span>
                                    </div>
                                    <div class="announcement-actions">
                                        <button class="edit-announcement-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="delete-announcement-btn" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <h4 class="announcement-title">Class Schedule Changes</h4>
                                <p class="announcement-preview">Due to equipment maintenance, EIM classes scheduled for tomorrow will be moved to Thursday...</p>
                                <div class="announcement-stats">
                                    <span><i class="fas fa-eye"></i> 89 views</span>
                                    <span><i class="fas fa-users"></i> EIM Students</span>
                                </div>
                            </div>

                            <div class="announcement-card">
                                <div class="announcement-header">
                                    <div class="announcement-meta">
                                        <span class="announcement-type event">Event</span>
                                        <span class="announcement-priority normal">Normal</span>
                                        <span class="announcement-date">3 days ago</span>
                                    </div>
                                    <div class="announcement-actions">
                                        <button class="edit-announcement-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="delete-announcement-btn" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <h4 class="announcement-title">Skills Competition Registration Open</h4>
                                <p class="announcement-preview">Registration is now open for the inter-department skills competition. Prizes and certificates await the winners...</p>
                                <div class="announcement-stats">
                                    <span><i class="fas fa-eye"></i> 203 views</span>
                                    <span><i class="fas fa-users"></i> All Users</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Edit/Add Notifications Section -->
                <section class="page-section" id="edit-notifications">
                    <div class="section-header">
                        <h2 class="section-title">Edit/Add Notifications</h2>
                        <p class="section-description">Create and manage system notifications for the dashboard</p>
                    </div>

                    <!-- Notification Form -->
                    <div class="notification-form-container">
                        <form id="notificationForm" class="notification-form">
                            <!-- Notification Title -->
                            <div class="form-group">
                                <label for="notificationTitle" class="form-label">
                                    <i class="fas fa-heading"></i>
                                    Notification Title
                                </label>
                                <input 
                                    type="text" 
                                    id="notificationTitle" 
                                    name="title" 
                                    class="form-input" 
                                    placeholder="Enter notification title..."
                                    required
                                >
                            </div>

                            <!-- Notification Icon Selection -->
                            <div class="form-group">
                                <label form="notificationIcon" class="form-label">
                                    <i class="fas fa-icons"></i>
                                    Notification Icon
                                </label>
                                <div class="icon-selection-grid">
                                    <div class="icon-option" data-icon="cog">
                                        <i class="fas fa-cog"></i>
                                        <span>System</span>
                                    </div>
                                    <div class="icon-option" data-icon="database">
                                        <i class="fas fa-database"></i>
                                        <span>Database</span>
                                    </div>
                                    <div class="icon-option" data-icon="file-text">
                                        <i class="fas fa-file-text"></i>
                                        <span>Document</span>
                                    </div>
                                    <div class="icon-option" data-icon="user-plus">
                                        <i class="fas fa-user-plus"></i>
                                        <span>User</span>
                                    </div>
                                    <div class="icon-option" data-icon="exclamation-triangle">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Warning</span>
                                    </div>
                                    <div class="icon-option" data-icon="check-circle">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Success</span>
                                    </div>
                                    <div class="icon-option" data-icon="info-circle">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Info</span>
                                    </div>
                                    <div class="icon-option" data-icon="bullhorn">
                                        <i class="fas fa-bullhorn"></i>
                                        <span>Announcement</span>
                                    </div>
                                    <div class="icon-option" data-icon="calendar">
                                        <i class="fas fa-calendar"></i>
                                        <span>Event</span>
                                    </div>
                                    <div class="icon-option" data-icon="bell">
                                        <i class="fas fa-bell"></i>
                                        <span>Alert</span>
                                    </div>
                                    <div class="icon-option" data-icon="graduation-cap">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span>Education</span>
                                    </div>
                                    <div class="icon-option" data-icon="briefcase">
                                        <i class="fas fa-briefcase"></i>
                                        <span>Job</span>
                                    </div>
                                </div>
                                <input type="hidden" id="selectedIcon" name="icon" required>
                            </div>

                            <!-- Notification Message/Description -->
                            <div class="form-group">
                                <label for="notificationMessage" class="form-label">
                                    <i class="fas fa-align-left"></i>
                                    Notification Message
                                </label>
                                <textarea 
                                    id="notificationMessage" 
                                    name="message" 
                                    class="form-textarea" 
                                    rows="4"
                                    placeholder="Enter notification message/description..."
                                    required
                                ></textarea>
                                <div class="character-count">
                                    <span id="messageCharCount">0</span>/200 characters
                                </div>
                            </div>

                            <!-- Notification Type -->
                            <div class="form-group">
                                <label for="notificationType" class="form-label">
                                    <i class="fas fa-tag"></i>
                                    Notification Type
                                </label>
                                <select id="notificationType" name="type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="info">Information</option>
                                    <option value="success">Success</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                </select>
                            </div>

                            

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="submit" class="action-btn publish">
                                    <i class="fas fa-plus"></i>
                                    Add Notification
                                </button>
                                <button type="reset" class="action-btn reset" id="clearNotificationForm">
                                    <i class="fas fa-undo"></i>
                                    Clear Form
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Current Notifications -->
                    <div class="current-notifications">
                        <div class="current-notifications-header" style="display:flex; align-items:center; justify-content: space-between; gap: 12px;">
                            <h3 class="section-subtitle" style="margin: 0;">Current Notifications</h3>
                            <button id="showAllNotificationsBtn" class="action-btn reset">
                                Show more
                            </button>
                        </div>
                        <div class="notifications-list" id="currentNotificationsList"></div>
                    </div>
                </section>

                <!-- About Us Section -->
                <!-- Replacement note: Standardized About Us copied from instructor dashboard to ensure visual parity across dashboards. -->
                <section class="page-section" id="about">
                    <!-- Hero Section (standardized) -->
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
                                    <div class="header-icon">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <h2 class="card-title">Our Vision</h2>
                                </div>
                                <div class="card-content">
                                    <p class="vision-quote">"TO BE THE CENTER OF WHOLE LEARNING EXPERIENCE FOR GREAT ADVANCEMENT."</p>
                                </div>
                            </div>

                            <div class="mission-card">
                                <div class="card-header mission-header">
                                    <div class="header-icon">
                                        <i class="fas fa-compass"></i>
                                    </div>
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
            </div>
        </main>
    </div>

        <!-- All Notifications Modal (pre-rendered, hidden by default) -->
        <div id="allNotificationsModalOverlay" class="modal-overlay">
            <div class="modal-content notifications-modal">
                <div class="notifications-modal-header">
                    <h3 class="notifications-modal-title">All Notifications</h3>
                </div>
                <div id="allNotificationsContainer" class="notifications-modal-list"></div>
            </div>
        </div>

        <!-- All Announcements Modal (pre-rendered, hidden by default) -->
        <div id="allAnnouncementsModalOverlay" class="modal-overlay">
            <div class="modal-content announcements-modal">
                <div class="notifications-modal-header">
                    <h3 class="notifications-modal-title">All Announcements</h3>
                </div>
                <div id="allAnnouncementsContainer" class="notifications-modal-list"></div>
            </div>
        </div>

                    <!-- Delete Course Confirmation Modal -->
                    <div id="deleteCourseModal" class="modal-overlay" style="display:none;">
                        <div class="modal-content delete-course-modal-content">
                            <div class="modal-header">
                                <div class="header-content">
                                    <div class="warning-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="header-text">
                                        <h2>Delete Course</h2>
                                        <p>This action cannot be undone</p>
                                    </div>
                                </div>
                                <button class="modal-close" id="deleteCourseModalClose">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="modal-body">
                                <div class="course-info">
                                    <div class="course-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div class="course-details">
                                        <h3 id="deleteCourseName">Course Name</h3>
                                        <p>Code: <span id="deleteCourseCode">-</span></p>
                                    </div>
                                </div>
                                
                                <div class="deletion-impact">
                                    <div class="preservation-notice">
                                        <i class="fas fa-shield-alt"></i>
                                        <span><strong>Safe deletion:</strong> Only the course will be removed. All student data will be preserved.</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-cancel" id="cancelDeleteBtn">
                                    Cancel
                                </button>
                                <button type="button" class="btn btn-delete" id="confirmDeleteBtn">
                                    Delete Course
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Add Course Modal -->
        <div id="addCourseModal" class="modal-overlay" style="display:none;">
            <div class="modal-content add-course-modal-content">
                <h2><i class="fas fa-plus" style="color: var(--green-500); margin-right: 0.5rem;"></i>Add New Course</h2>
                <form id="addCourseForm" autocomplete="off">
                    <!-- Course Name Input -->
                    <div class="add-input-with-external-icon">
                        <i class="fas fa-graduation-cap add-external-icon" style="color: var(--blue-600);"></i>
                        <input type="text" name="courseName" id="courseName" placeholder="Enter course name" required>
                    </div>
                    
                    <!-- Course Code Input -->
                    <div class="add-input-with-external-icon">
                        <i class="fas fa-code add-external-icon" style="color: var(--purple-600);"></i>
                        <input type="text" name="courseCode" id="courseCode" placeholder="Enter course code (e.g., ATS, BCL)" required maxlength="10">
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="modal-btn cancel" id="cancelAddCourseBtn">Cancel</button>
                        <button type="submit" class="modal-btn confirm" id="saveCourseBtn">
                            <i class="fas fa-save"></i> Save Course
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="../js/admin.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/admin.js")); ?>"></script>
    <script src="../js/graduates_charts.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/graduates_charts.js")); ?>"></script>
    <script src="../js/industry_charts.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/industry_charts.js")); ?>"></script>
    <script src="../js/employment_charts.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/employment_charts.js")); ?>"></script>
    <script src="../js/employment_trend_analysis.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/employment_trend_analysis.js")); ?>"></script>
    <script src="../js/course_trends_visualization.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/course_trends_visualization.js")); ?>"></script>
    <script src="../js/job_trends_visualization.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/job_trends_visualization.js")); ?>"></script>
    <script src="../js/graduates_course_popularity_2025.js?v=<?php echo urlencode((string)@filemtime(__DIR__."/../js/graduates_course_popularity_2025.js")); ?>"></script>
    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    (function(){
        function exportCurrentGradesCSV(){
            var table = document.getElementById('adminGradesTable');
            if(!table) return;
            var rows = table.querySelectorAll('tbody tr');
            var data = [];
            // Header
            data.push(['Student ID','Name','Course','Average Grade']);
            rows.forEach(function(r){
                var id = (r.children[0] && r.children[0].textContent.trim()) || '';
                var name = (r.children[1] && r.children[1].textContent.trim()) || '';
                var course = (r.children[2] && r.children[2].textContent.trim()) || '';
                var gradeCell = r.children[3];
                var gradeText = '';
                if(gradeCell){
                    var badge = gradeCell.querySelector('.grade-badge');
                    gradeText = (badge ? badge.textContent.trim() : gradeCell.textContent.trim()) || '';
                }
                data.push([id, name, course, gradeText]);
            });

            var csv = data.map(function(row){
                return row.map(function(field){
                    var val = String(field==null?'':field);
                    // Escape quotes and wrap in quotes if needed
                    var needsQuote = /[",\n]/.test(val);
                    val = val.replace(/"/g, '""');
                    return needsQuote ? '"' + val + '"' : val;
                }).join(',');
            }).join('\n');

            var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            var now = new Date();
            var ts = now.toISOString().replace(/[:\.-]/g, '').slice(0,15);
            a.href = url;
            a.download = 'trainee_grades_' + ts + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        // Course settings & enrollments
        function fetchCourses(){
            var tbody = document.getElementById('coursesTableBody');
            if(tbody){ tbody.innerHTML = '<tr><td colspan="8" class="modules-empty">Loading courses...</td></tr>'; }
            fetch('../apis/course_admin.php?action=list',{credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(j){
                    if(!tbody) return;
                    if(!j||!j.success){ tbody.innerHTML = '<tr><td colspan="8" class="modules-empty">Failed to load</td></tr>'; return; }
                    var rows = j.data||[];
                    if(rows.length===0){ tbody.innerHTML = '<tr><td colspan="8" class="modules-empty">No courses</td></tr>'; return; }
                    tbody.innerHTML = rows.map(function(c){
                        var statusSel = '<select data-id="'+c.id+'" class="course-status">'+
                            ['upcoming','ongoing','completed','cancelled'].map(function(s){ return '<option value="'+s+'"'+(String(c.status)===s?' selected':'')+'>'+s+'</option>'; }).join('')+
                            '</select>';
                        var start = '<input type="date" value="'+(c.start_date||'')+'" class="course-start" data-id="'+c.id+'">';
                        var end = '<input type="date" value="'+(c.end_date||'')+'" class="course-end" data-id="'+c.id+'">';
                        var dur = '<input type="number" min="1" value="'+(c.default_duration_days||90)+'" class="course-dur" data-id="'+c.id+'" style="max-width:120px;">';
                        var activeSel = '<select data-id="'+c.id+'" class="course-active"><option value="1"'+(c.is_active?' selected':'')+'>Yes</option><option value="0"'+(!c.is_active?' selected':'')+'>No</option></select>';
                        var saveBtn = '<button class="save-course" data-id="'+c.id+'">Save</button>';
                        return '<tr>'+
                          '<td>'+c.code+'</td>'+
                          '<td>'+c.name+'</td>'+
                          '<td>'+statusSel+'</td>'+
                          '<td>'+start+'</td>'+
                          '<td>'+end+'</td>'+
                          '<td>'+dur+'</td>'+
                          '<td>'+activeSel+'</td>'+
                          '<td>'+saveBtn+'</td>'+
                        '</tr>';
                    }).join('');
                })
                .catch(function(){ if(tbody){ tbody.innerHTML = '<tr><td colspan="8" class="modules-empty">Failed to load</td></tr>'; }});
        }
        function updateCourse(id, payload){
            var fd = new FormData();
            fd.append('action','update');
            fd.append('id', id);
            Object.keys(payload).forEach(function(k){ if(payload[k]!==undefined){ fd.append(k, payload[k]); }});
            return fetch('../apis/course_admin.php',{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();});
        }
        function fetchEnrollments(){
            // Use the enhanced filtering system instead
            loadEnrollmentData();
        }

        // Grades Section Functionality
        let currentSelectedCourse = null;
        let allStudentsData = <?php echo json_encode($studentsWithGrades); ?>;
        
        function initializeGradesSection() {
            populateCoursesGrid();
            setupGradesEventListeners();
        }
        
        function populateCoursesGrid() {
            const coursesGrid = document.getElementById('coursesGrid');
            if (!coursesGrid) return;
            
            // Function to normalize course names (remove parentheses and convert to title case)
            function normalizeCourseName(courseName) {
                if (!courseName) return '';
                // Remove parentheses and their contents
                let normalized = courseName.replace(/\s*\([^)]*\)\s*/g, '');
                // Convert to title case (first letter of each word capitalized)
                normalized = normalized.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
                
                // Handle specific typos and variations
                if (normalized.includes('Electronic Products And Assembly Servicng')) {
                    normalized = 'Electronic Products And Assembly Servicing';
                }
                
                return normalized;
            }
            
            // Function to check if two course names are the same (normalized)
            function isSameCourse(course1, course2) {
                return normalizeCourseName(course1) === normalizeCourseName(course2);
            }
            
            // Get all courses from students data
            const allCourses = allStudentsData.map(s => s.course).filter(c => c && c !== '');
            
            if (allCourses.length === 0) {
                coursesGrid.innerHTML = '<div class="no-courses">No courses found</div>';
                return;
            }
            
            // Group courses by normalized names to avoid duplicates
            const courseGroups = {};
            allCourses.forEach(course => {
                const normalized = normalizeCourseName(course);
                if (!courseGroups[normalized]) {
                    courseGroups[normalized] = {
                        displayName: normalized,
                        originalNames: [],
                        students: []
                    };
                }
                if (!courseGroups[normalized].originalNames.includes(course)) {
                    courseGroups[normalized].originalNames.push(course);
                }
            });
            
            // Collect all students for each normalized course
            Object.keys(courseGroups).forEach(normalizedName => {
                const group = courseGroups[normalizedName];
                group.students = allStudentsData.filter(s => 
                    group.originalNames.some(originalName => s.course === originalName)
                );
            });
            
            // Generate course cards
            coursesGrid.innerHTML = Object.keys(courseGroups).map(normalizedName => {
                const group = courseGroups[normalizedName];
                const studentsInCourse = group.students;
                const avgGrade = studentsInCourse.length > 0 
                    ? (studentsInCourse.reduce((sum, s) => sum + parseFloat(s.final_grade || 0), 0) / studentsInCourse.length).toFixed(1)
                    : '0.0';
                
                // Use the first original name as the data-course attribute for compatibility
                const dataCourse = group.originalNames[0];
                
                return `
                    <div class="course-card grades-course-card" data-course="${dataCourse}">
                        <div class="course-header">
                            <i class="fas fa-graduation-cap course-icon"></i>
                            <h3 class="course-title">${normalizedName}</h3>
                        </div>
                        <div class="course-stats">
                            <div class="stat-item">
                                <span class="stat-value">${studentsInCourse.length}</span>
                                <span class="stat-label">Students</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value">${avgGrade}%</span>
                                <span class="stat-label">Avg Grade</span>
                            </div>
                        </div>
                        <div class="course-actions">
                            <button class="view-course-btn" data-course="${dataCourse}">
                                <i class="fas fa-eye"></i>
                                View Students
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function setupGradesEventListeners() {
            // Course card click handler
            document.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-course-btn');
                if (viewBtn) {
                    const course = viewBtn.getAttribute('data-course');
                    showStudentsForCourse(course);
                }
                
                // Back to courses button
                if (e.target.closest('#backToCoursesBtn')) {
                    showCoursesView();
                }
                
                // Refresh grades button
                if (e.target.closest('#refreshGradesBtn')) {
                    refreshGradesContent();
                }
            });
            
            // Search functionality
            const searchInput = document.getElementById('gradesSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    filterGradesContent(searchTerm);
                });
            }
        }
        
        function showStudentsForCourse(course) {
            currentSelectedCourse = course;
            
            // Hide courses view, show students view
            document.getElementById('coursesView').style.display = 'none';
            document.getElementById('studentsView').style.display = 'block';
            document.getElementById('backToCoursesBtn').style.display = 'block';
            
            // Function to normalize course names (same as in populateCoursesGrid)
            function normalizeCourseName(courseName) {
                if (!courseName) return '';
                let normalized = courseName.replace(/\s*\([^)]*\)\s*/g, '');
                normalized = normalized.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
                
                // Handle specific typos and variations
                if (normalized.includes('Electronic Products And Assembly Servicng')) {
                    normalized = 'Electronic Products And Assembly Servicing';
                }
                
                return normalized;
            }
            
            // Filter students for this course (including all variations)
            const normalizedCourse = normalizeCourseName(course);
            const studentsInCourse = allStudentsData.filter(s => {
                const normalizedStudentCourse = normalizeCourseName(s.course);
                return normalizedStudentCourse === normalizedCourse;
            });
            
            // Populate students table
            const tableBody = document.getElementById('gradesTableBody');
            if (!tableBody) return;
            
            if (studentsInCourse.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;opacity:.6;">No students found for this course.</td></tr>';
                return;
            }
            
            tableBody.innerHTML = studentsInCourse.map(student => {
                const final = parseFloat(student.final_grade || 0).toFixed(1);
                return `
                    <tr>
                        <td>${student.student_number}</td>
                        <td>${student.first_name} ${student.last_name}</td>
                        <td>${student.course}</td>
                        <td><span class="grade-badge">${final}%</span></td>
                        <td>
                            <button class="view-btn" data-student="${student.student_number}">View Details</button>
                        </td>
                    </tr>
                `;
            }).join('');
            
            // Update search placeholder
            const searchInput = document.getElementById('gradesSearchInput');
            if (searchInput) {
                searchInput.placeholder = `Search students in ${normalizedCourse}...`;
            }
        }
        
        function showCoursesView() {
            currentSelectedCourse = null;
            
            // Show courses view, hide students view
            document.getElementById('coursesView').style.display = 'block';
            document.getElementById('studentsView').style.display = 'none';
            document.getElementById('backToCoursesBtn').style.display = 'none';
            
            // Reset search placeholder
            const searchInput = document.getElementById('gradesSearchInput');
            if (searchInput) {
                searchInput.placeholder = 'Search courses...';
                searchInput.value = '';
            }
            
            // Repopulate courses
            populateCoursesGrid();
        }
        
        function filterGradesContent(searchTerm) {
            if (currentSelectedCourse) {
                // Filter students in current course
                const tableBody = document.getElementById('gradesTableBody');
                if (!tableBody) return;
                
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            } else {
                // Filter courses
                const courseCards = document.querySelectorAll('.grades-course-card');
                courseCards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
        }
        
        function refreshGradesContent() {
            // Prevent multiple simultaneous refresh operations
            if (window.isRefreshingGrades) {
                return;
            }
            
            // Set flag to prevent multiple operations
            window.isRefreshingGrades = true;
            
            // Show loading state
            const coursesGrid = document.getElementById('coursesGrid');
            if (coursesGrid) {
                coursesGrid.innerHTML = '<div class="no-courses"><i class="fas fa-spinner fa-spin"></i> Refreshing grades...</div>';
            }
            
            // Add visual feedback to refresh button
            const refreshBtn = document.getElementById('refreshGradesBtn');
            if (refreshBtn) {
                refreshBtn.classList.add('is-spinning');
                refreshBtn.disabled = true;
            }
            
            // Refresh main dashboard cards first
            refreshStudentCount();
            refreshAdminAverages();
            
            // Get all student numbers from current data
            const studentNumbers = allStudentsData.map(student => student.student_number).filter(sn => sn);
            
            if (studentNumbers.length === 0) {
                // No students to refresh, just repopulate
                populateCoursesGrid();
                if (refreshBtn) {
                    refreshBtn.classList.remove('is-spinning');
                    refreshBtn.disabled = false;
                }
                window.isRefreshingGrades = false;
                return;
            }
            
            // Refresh student data from server with student numbers
            const studentNumbersParam = studentNumbers.join(',');
            fetch(`../apis/grade_details.php?action=averages&student_numbers=${encodeURIComponent(studentNumbersParam)}`, {credentials: 'same-origin'})
                .then(response => response.json())
                .then(data => {
                    if (data && data.success && data.averages) {
                        // Update the allStudentsData with fresh data
                        allStudentsData.forEach(student => {
                            if (data.averages[student.student_number]) {
                                student.final_grade = data.averages[student.student_number];
                            }
                        });
                    }
                    
                    // Repopulate the courses grid with updated data
                    populateCoursesGrid();
                    
                    // If we're currently viewing students for a course, refresh that view too
                    if (currentSelectedCourse) {
                        showStudentsForCourse(currentSelectedCourse);
                    }
                    
                    // Show success notification only once
                    if (!window.refreshNotificationShown) {
                        showNotification('All data refreshed successfully!', 'success');
                        window.refreshNotificationShown = true;
                        // Reset the flag after a delay to allow future notifications
                        setTimeout(() => {
                            window.refreshNotificationShown = false;
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing data:', error);
                    // Still repopulate with existing data
                    populateCoursesGrid();
                    showNotification('Error refreshing data. Showing cached data.', 'error');
                })
                .finally(() => {
                    // Remove visual feedback from refresh button
                    if (refreshBtn) {
                        refreshBtn.classList.remove('is-spinning');
                        refreshBtn.disabled = false;
                    }
                    // Reset the refresh flag
                    window.isRefreshingGrades = false;
                });
        }

        document.addEventListener('DOMContentLoaded', function(){
            // fetchCourses(); // Removed - using new single-row interface instead
            fetchEnrollments();
            initializeGradesSection();
            
            // Load graduates data after enrollments are fetched
            setTimeout(function() {
                if (typeof loadGraduatesData === 'function') {
                    loadGraduatesData();
                }
            }, 1000);
            document.body.addEventListener('click', function(e){
                var saveBtn = e.target.closest && e.target.closest('.save-course');
                if(saveBtn){
                    var id = saveBtn.getAttribute('data-id');
                    var status = document.querySelector('.course-status[data-id="'+id+'"]');
                    var start = document.querySelector('.course-start[data-id="'+id+'"]');
                    var end = document.querySelector('.course-end[data-id="'+id+'"]');
                    var dur = document.querySelector('.course-dur[data-id="'+id+'"]');
                    var active = document.querySelector('.course-active[data-id="'+id+'"]');
                    updateCourse(id, {
                        status: status?status.value:undefined,
                        start_date: start?start.value:undefined,
                        end_date: end?end.value:undefined,
                        default_duration_days: dur?dur.value:undefined,
                        is_active: active?active.value:undefined
                    }).then(function(){ 
                        // fetchCourses(); // Removed - using new single-row interface instead
                        // The new system will handle refreshing via loadCoursesFromDatabase()
                    });
                }
                var enBtn = e.target.closest && e.target.closest('#enrollStudentBtn');
                if(enBtn){
                    e.preventDefault(); // Prevent form submission and page refresh
                    var sn = document.getElementById('enrollStudentNumber');
                    var code = document.getElementById('enrollCourseCode');
                    var startDate = document.getElementById('enrollStartDate');
                    
                    // Validate inputs
                    if (!sn || !sn.value.trim()) {
                        showNotification('Please enter a student number.', 'error');
                        return;
                    }
                    if (!code || !code.value.trim()) {
                        showNotification('Please select a course code.', 'error');
                        return;
                    }
                    if (!startDate || !startDate.value) {
                        showNotification('Please select a start date.', 'error');
                        return;
                    }
                    // Ensure start date is today or in the future
                    var todayStr = new Date().toISOString().slice(0,10);
                    if (startDate.value < todayStr) {
                        showNotification('Start date cannot be in the past.', 'error');
                        return;
                    }
                    
                    // Check for existing enrollment before submitting
                    checkExistingEnrollment(sn.value.trim(), code.value.trim(), function(canEnroll, message) {
                        if (!canEnroll) {
                            showNotification(message, 'error');
                            return;
                        }
                        
                        // Proceed with enrollment
                        var fd = new FormData();
                        fd.append('action','enroll');
                        fd.append('student_number', sn.value.trim());
                        fd.append('course_code', code.value.trim());
                        fd.append('start_date', startDate.value);
                        fetch('../apis/enrollment_admin.php',{method:'POST',credentials:'same-origin',body:fd})
                            .then(function(r){return r.json();})
                            .then(function(response){
                                if(response && response.success) {
                                    // Show success notification
                                    showNotification('Student Enrolled Successfully', 'success');
                                    // Clear the form
                                    document.getElementById('quickEnrollForm').reset();
                                } else {
                                    // Show server-provided message when available
                                    var msg = (response && response.message) ? response.message : 'Enrollment failed. Please try again.';
                                    showNotification(msg, 'error');
                                }
                                fetchEnrollments();
                            })
                            .catch(function(error){
                                showNotification('Enrollment failed. Please try again.', 'error');
                            });
                    });
                }
                var clearBtn = e.target.closest && e.target.closest('#clearEnrollForm');
                if(clearBtn){
                    e.preventDefault();
                    document.getElementById('quickEnrollForm').reset();
                    showNotification('Form cleared', 'info');
                }
                var statusBtn = e.target.closest && e.target.closest('.en-status');
                if(statusBtn){
                    var id = statusBtn.getAttribute('data-id');
                    var st = statusBtn.getAttribute('data-status');
                    var fd = new FormData(); fd.append('action','update_status'); fd.append('id', id); fd.append('status', st);
                    fetch('../apis/enrollment_admin.php',{method:'POST',credentials:'same-origin',body:fd})
                        .then(function(r){return r.json();})
                        .then(function(){ 
                            fetchEnrollments(); 
                            // Also refresh graduates table after a short delay to ensure enrollments table is updated
                            if (typeof loadGraduatesData === 'function') {
                                setTimeout(function() {
                                    loadGraduatesData();
                                }, 500);
                            }
                        });
                }
                var adjBtn = e.target.closest && e.target.closest('.en-adjust');
                if(adjBtn){
                    var id = adjBtn.getAttribute('data-id');
                    var start = prompt('New start date (YYYY-MM-DD) or blank to keep');
                    var end = prompt('New end date (YYYY-MM-DD) or blank to keep');
                    var fd = new FormData(); fd.append('action','adjust_dates'); fd.append('id', id);
                    if(start!==null){ fd.append('start_date', start); }
                    if(end!==null){ fd.append('end_date', end); }
                    fetch('../apis/enrollment_admin.php',{method:'POST',credentials:'same-origin',body:fd})
                        .then(function(r){return r.json();})
                        .then(function(){ fetchEnrollments(); });
                }
            });
        });
        
        // Course Management Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            const addCourseBtn = document.getElementById('addCourseBtn');
            const deleteCourseBtn = document.getElementById('deleteCourseBtn');
            const addCourseModal = document.getElementById('addCourseModal');
            const deleteCourseModal = document.getElementById('deleteCourseModal');
            const addCourseForm = document.getElementById('addCourseForm');
            
            // Add Course Modal
            if (addCourseBtn) {
                addCourseBtn.addEventListener('click', function() {
                    addCourseModal.style.display = 'flex';
                });
            }
            
            // Delete Course Modal
            if (deleteCourseBtn) {
                deleteCourseBtn.addEventListener('click', function() {
                    console.log('Delete course button clicked');
                    
                    // Get currently selected course
                    const selectedCourse = getSelectedCourse();
                    console.log('Selected course:', selectedCourse);
                    
                    if (!selectedCourse) {
                        showNotification('Please select a course to delete', 'warning');
                        return;
                    }
                    
                    // Populate modal with course details
                    document.getElementById('deleteCourseName').textContent = selectedCourse.name;
                    document.getElementById('deleteCourseCode').textContent = selectedCourse.code;
                    
                    // Show modal
                    deleteCourseModal.style.display = 'flex';
                    console.log('Delete course modal shown');
                });
            }
            
            // Close modals
            const closeModal = (modal) => {
                modal.style.display = 'none';
            };
            
            // Add Course Modal close
            const addCourseModalClose = document.getElementById('addCourseModalClose');
            if (addCourseModalClose) {
                addCourseModalClose.addEventListener('click', () => closeModal(addCourseModal));
            }
            
            // Delete Course Modal close
            const deleteCourseModalClose = document.getElementById('deleteCourseModalClose');
            if (deleteCourseModalClose) {
                deleteCourseModalClose.addEventListener('click', () => closeModal(deleteCourseModal));
            }
            
            // Cancel delete button
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', () => closeModal(deleteCourseModal));
            }
            
            // Confirm delete button
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    const selectedCourse = getSelectedCourse();
                    if (!selectedCourse) {
                        showNotification('No course selected', 'error');
                        return;
                    }
                    
                    // Disable button and show loading state
                    confirmDeleteBtn.disabled = true;
                    confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                    
                    // Call delete API
                    deleteCourse(selectedCourse.id, selectedCourse.name, selectedCourse.code);
                });
            }
            
            // Function to get currently selected course
            function getSelectedCourse() {
                const courseSelect = document.getElementById('courseSelection');
                console.log('Course select element:', courseSelect);
                
                if (!courseSelect || !courseSelect.value) {
                    console.log('No course selected or course select not found');
                    return null;
                }
                
                // Get course data from the selected option
                const selectedOption = courseSelect.options[courseSelect.selectedIndex];
                console.log('Selected option:', selectedOption);
                
                if (!selectedOption || !selectedOption.value) {
                    console.log('No valid option selected');
                    return null;
                }
                
                const courseData = {
                    id: selectedOption.value,
                    name: selectedOption.textContent,
                    code: selectedOption.getAttribute('data-code') || ''
                };
                
                console.log('Course data:', courseData);
                return courseData;
            }
            
            // Function to populate course selection dropdown
            function populateCourseSelection() {
                const courseSelect = document.getElementById('courseSelection');
                if (!courseSelect) {
                    console.error('Course selection element not found');
                    return;
                }
                
                // Clear existing options except the first one
                courseSelect.innerHTML = '<option value="">Choose a course to manage...</option>';
                
                // Fetch courses from API
                fetch('../apis/course_admin.php?action=list', { credentials: 'same-origin' })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.success && data.data) {
                            data.data.forEach(course => {
                                const option = document.createElement('option');
                                option.value = course.id;
                                option.textContent = course.name;
                                option.setAttribute('data-code', course.code);
                                courseSelect.appendChild(option);
                            });
                            console.log(`Populated course selection with ${data.data.length} courses`);
                        } else {
                            console.error('Failed to load courses for selection');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading courses for selection:', error);
                    });
            }
            
            // Populate course selection on page load
            // populateCourseSelection(); // Removed - using loadCoursesFromDatabase() instead
            
            // Test API connectivity
            function testAPI() {
                console.log('Testing API connectivity...');
                fetch('../apis/course_admin.php?action=test', { credentials: 'same-origin' })
                    .then(response => response.json())
                    .then(data => {
                        console.log('API Test Result:', data);
                    })
                    .catch(error => {
                        console.error('API Test Error:', error);
                    });
            }
            
            // Run API test
            testAPI();
            
            // Function to delete course
            function deleteCourse(courseId, courseName, courseCode) {
                console.log('deleteCourse called with:', { courseId, courseName, courseCode });
                
                // Validate course ID
                if (!courseId || courseId <= 0) {
                    console.error('Invalid course ID:', courseId);
                    showNotification('Invalid course ID', 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('course_id', courseId);
                
                console.log('FormData contents:');
                for (let [key, value] of formData.entries()) {
                    console.log(key, value);
                }
                
                fetch('../apis/course_admin.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response ok:', response.ok);
                    
                    if (!response.ok) {
                        // Try to get the response text to see the actual error
                        return response.text().then(text => {
                            console.error('API Error Response:', text);
                            throw new Error(`HTTP error! status: ${response.status} - ${text}`);
                        });
                    }
                    
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        showNotification(`Course "${courseName}" deleted successfully`, 'success');
                        closeModal(deleteCourseModal);
                        
                        // Refresh courses list
                        // fetchCourses(); // Removed - using new single-row interface instead
                        
                        // Refresh course selection dropdown
                        if (typeof loadCoursesFromDatabase === 'function') {
                            loadCoursesFromDatabase();
                        }
                        // Removed populateCourseSelection() fallback - using loadCoursesFromDatabase() only
                        
                        // Clear course selection
                        const courseSelect = document.getElementById('courseSelection');
                        if (courseSelect) {
                            courseSelect.value = '';
                            courseSelect.dispatchEvent(new Event('change'));
                        }
                    } else {
                        console.error('API returned error:', data);
                        showNotification(data.message || 'Failed to delete course', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting course:', error);
                    showNotification('Failed to delete course: ' + error.message, 'error');
                })
                .finally(() => {
                    // Re-enable button
                    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
                    if (confirmDeleteBtn) {
                        confirmDeleteBtn.disabled = false;
                        confirmDeleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Course';
                    }
                });
            }
        });
        
        function refreshAdminAverages(){
            var table = document.getElementById('adminGradesTable');
            if(!table) return;
            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            var sns = [];
            rows.forEach(function(r){
                var btn = r.querySelector('button.view-btn');
                var sn = btn ? btn.getAttribute('data-student') : null;
                if(sn) sns.push(sn);
            });
            if(sns.length === 0) return;
            var url = '../apis/grade_details.php?action=averages&student_numbers=' + encodeURIComponent(sns.join(','));
            fetch(url, {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(j){
                    if(!j || j.success !== true) return;
                    var map = j.averages || {};
                    rows.forEach(function(r){
                        var btn = r.querySelector('button.view-btn');
                        var sn = btn ? btn.getAttribute('data-student') : null;
                        if(!sn) return;
                        var avg = map[sn];
                        if(typeof avg === 'number'){
                            var cell = r.children[3];
                            if(cell){
                                var badge = cell.querySelector('.grade-badge');
                                if(badge){ badge.textContent = (avg.toFixed(1)) + '%'; }
                            }
                        }
                    });
                })
                .catch(function(){ /* silent */ });
        }

        function openAdminGradesModal(studentNumber, headerText){
            var modal = document.getElementById('adminGradeDetailsModal');
            var tbody = modal.querySelector('tbody');
            var headerEl = document.getElementById('adminGradeDetailsHeader');
            headerEl.textContent = headerText + ' (' + studentNumber + ')';
			tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;opacity:.7;">Loading...</td></tr>';
			modal.style.display = 'flex';

            var requests = [1,2,3,4].map(function(gn){
                var url = '../apis/grade_details.php?action=list&student_number=' + encodeURIComponent(studentNumber) + '&grade_number=' + gn;
                return fetch(url, {credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
                    if(!j || j.success !== true) return [];
                    return (j.data||[]).map(function(row){ row.__grade_number = gn; return row; });
                }).catch(function(){ return []; });
            });

            Promise.all(requests).then(function(all){
                var rows = ([]).concat.apply([], all);
                if(rows.length === 0){
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;opacity:.7;">No grades found.</td></tr>';
                    return;
                }
                rows.sort(function(a,b){
                    var ad = a.date_given || '';
                    var bd = b.date_given || '';
                    if(ad === bd) return (a.id||0) - (b.id||0);
                    if(!ad) return 1; if(!bd) return -1;
                    return ad.localeCompare(bd);
                });
                tbody.innerHTML = rows.map(function(r){
                    var date = r.date_given ? r.date_given : '—';
                    var raw = (r.raw_score!=null?r.raw_score:'—');
                    var total = (r.total_items!=null?r.total_items:'—');
                    var trans = (r.transmuted!=null? Number(r.transmuted).toFixed(2)+'%':'—');
                    var comp = r.component || '—';
                    return '<tr>'+
                        '<td>'+ r.__grade_number +'</td>'+
                        '<td>'+ comp +'</td>'+
                        '<td>'+ date +'</td>'+
                        '<td>'+ raw +'</td>'+
                        '<td>'+ total +'</td>'+
                        '<td>'+ trans +'</td>'+
                    '</tr>';
                }).join('');
            });

            // Also load published quizzes & exams summary for this trainee
            var assessUrl = '../apis/published_assessments.php?student_number=' + encodeURIComponent(studentNumber);
            fetch(assessUrl, {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(j){
                    if(!j || !j.success){ return; }
                    var data = j.data||[];
                    if(data.length === 0){ return; }
                    var container = document.createElement('div');
                    container.style.marginTop = '12px';
                    container.innerHTML = '<p style="margin:8px 0 6px 0;opacity:.8;">Published Quiz & Exams for this trainee</p>'+
                        '<div class="table-container"><table class="data-table"><thead><tr>'+
                        '<th>Type</th><th>Title</th><th>Instructor</th><th>Status</th>'+
                        '</tr></thead><tbody>'+ data.map(function(a){
                            return '<tr>'+
                                '<td>'+ (a.type==='exam'?'Exam':'Quiz') +'</td>'+
                                '<td>'+ String(a.title||'') +'</td>'+
                                '<td>'+ String(a.instructor||'') +'</td>'+
                                '<td>'+ (a.submission_status||'Not started') +'</td>'+
                            '</tr>';
                        }).join('') + '</tbody></table></div>';
                    modal.querySelector('.modal-content').appendChild(container);
                })
                .catch(function(){});
        }

        document.addEventListener('click', function(e){
            var btn = e.target.closest('button.view-btn');
            if(btn && btn.closest('#adminGradesTable')){
                e.preventDefault();
                var sn = btn.getAttribute('data-student') || '';
                var row = btn.closest('tr');
                var nameCell = row ? row.children[1] : null;
                var headerText = nameCell ? nameCell.textContent.trim() : 'Student';
                if(sn){ openAdminGradesModal(sn, headerText); }
            }
            if(e.target.id === 'adminGradeDetailsClose' || (e.target.closest && e.target.closest('#adminGradeDetailsClose'))){
                var modal = document.getElementById('adminGradeDetailsModal');
                if(modal) modal.style.display = 'none';
            }
            var modalRoot = document.getElementById('adminGradeDetailsModal');
            if(modalRoot && e.target === modalRoot){ modalRoot.style.display = 'none'; }
            if(e.target.closest && e.target.closest('#qaExportData')){
                exportCurrentGradesCSV();
            }
            function triggerDownload(url){ var a=document.createElement('a'); a.href=url; a.style.display='none'; document.body.appendChild(a); a.click(); setTimeout(function(){ document.body.removeChild(a); }, 300); }
            if(e.target.closest && e.target.closest('#exportTraineesBtn')){ triggerDownload('../apis/backup_export.php?type=trainees'); }
            if(e.target.closest && e.target.closest('#exportJobsBtn')){ triggerDownload('../apis/backup_export.php?type=jobs'); }
            if(e.target.closest && e.target.closest('#exportAnnouncementsBtn')){ triggerDownload('../apis/backup_export.php?type=announcements'); }
            if(e.target.closest && e.target.closest('#exportNotificationsBtn')){ triggerDownload('../apis/backup_export.php?type=notifications'); }
            if(e.target.closest && e.target.closest('#exportAllBtn')){ triggerDownload('../apis/backup_export.php?type=all'); }
        });

        // Function to refresh student count
        function refreshStudentCount(){
            fetch('../apis/student_count.php', {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(j){
                    if(j && j.success && j.data){
                        var countEl = document.getElementById('totalStudentsCount');
                        if(countEl){
                            countEl.textContent = new Intl.NumberFormat().format(j.data.total_students);
                        }
                    }
                })
                .catch(function(e){
                    console.log('Failed to refresh student count:', e);
                });
        }

        // Initial and periodic refresh (live reflection)
        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', function(){
                refreshAdminAverages();
                refreshStudentCount();
                setInterval(refreshAdminAverages, 10000); // every 10s
                setInterval(refreshStudentCount, 30000); // every 30s
            });
        } else {
            refreshAdminAverages();
            refreshStudentCount();
            setInterval(refreshAdminAverages, 10000);
            setInterval(refreshStudentCount, 30000);
        }
    })();

    // Graduates Section Functionality
    let currentGraduatesData = [];
    let currentFilters = {};
    
    // Student data for name lookup
    const studentsData = <?php echo json_encode($studentsWithGrades); ?>;
    
    // Function to get student name by student number
    function getStudentName(studentNumber) {
        const student = studentsData.find(s => s.student_number === studentNumber);
        if (student) {
            return `${student.first_name} ${student.last_name}`.trim();
        }
        return 'Student Name'; // Fallback
    }

    // Notification function
    function showNotification(message, type = 'success') {
        const container = document.getElementById('notificationContainer');
        if (!container) return;

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(notification);

        // Trigger slide-in animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        // Auto-remove after 4 seconds
        setTimeout(() => {
            closeNotification(notification.querySelector('.notification-close'));
        }, 4000);
    }

    // Close notification function
    function closeNotification(closeBtn) {
        const notification = closeBtn.closest('.notification');
        if (notification) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }

    // Check existing enrollment function
    function checkExistingEnrollment(studentNumber, courseCode, callback) {
        // Resolve the human-readable course name from the current selection for accurate comparisons
        var courseName = (function(){
            var el = document.getElementById('enrollCourseCode');
            if (el && el.options && el.selectedIndex >= 0) {
                var opt = el.options[el.selectedIndex];
                return (opt && opt.textContent) ? opt.textContent.trim() : courseCode;
            }
            return courseCode;
        })();
        // First check if student exists by fetching from server
        fetch('../apis/test_enrollment.php?student_number=' + encodeURIComponent(studentNumber), {credentials: 'same-origin'})
            .then(response => {
                console.log('Student check response status:', response.status);
                console.log('Student check response headers:', response.headers);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (!data || !data.success || !data.data || data.data.length === 0) {
                    callback(false, 'Student not found. Please check the student number.');
                    return;
                }
                
                const student = data.data[0];
                
                // Check if student is already enrolled in this course
                if (student.course === courseName) {
                    callback(false, 'Student is already enrolled in this course.');
                    return;
                }
                
                // Fetch current enrollments to check for any existing enrollment (enrolled, completed, or withdrawn)
                fetch('apis/enrollment_admin.php?action=list', {credentials: 'same-origin'})
                    .then(response => response.json())
                    .then(enrollmentData => {
                        if (!enrollmentData || !enrollmentData.success || !enrollmentData.data) {
                            // If we can't fetch enrollments, allow enrollment but show warning
                            callback(true, '');
                            return;
                        }
                        
                        const enrollments = enrollmentData.data;
                        const existingEnrollment = enrollments.find(e => 
                            e.student_number === studentNumber && e.course === courseName
                        );
                        
                        if (existingEnrollment) {
                            const status = existingEnrollment.enrollment_status || existingEnrollment.status;
                            if (status === 'enrolled') {
                                callback(false, 'Student is already enrolled in this course.');
                            } else if (status === 'completed') {
                                callback(false, 'Cannot Enroll on the previous Course');
                            } else if (status === 'withdrawn') {
                                callback(false, 'Student was previously withdrawn from this course.');
                            } else {
                                callback(false, 'Student has an existing record for this course.');
                            }
                        } else {
                            // No existing enrollment found, allow enrollment
                            callback(true, '');
                        }
                    })
                    .catch(error => {
                        console.error('Error checking existing enrollment:', error);
                        // If there's an error checking, allow enrollment but show warning
                        callback(true, '');
                    });
            })
            .catch(error => {
                console.error('Error checking student:', error);
                callback(false, 'Error checking student. Please try again.');
            });
    }

    function initializeGraduatesSection() {
        const nameFilter = document.getElementById('graduateNameFilter');
        const idFilter = document.getElementById('graduateIdFilter');
        const courseFilter = document.getElementById('graduateCourseFilter');
        const monthFilter = document.getElementById('graduateMonthFilter');
        const yearFilter = document.getElementById('graduateYearFilter');
        const clearFiltersBtn = document.getElementById('clearGraduateFilters');
        const tableBody = document.getElementById('graduatesTableBody');

        // Initial data will be loaded after enrollments are fetched

        // Filter function for local data
        function filterGraduates() {
            const nameValue = nameFilter.value.toLowerCase();
            const idValue = idFilter.value.toLowerCase();
            const courseValue = courseFilter.value; // Now works with dropdown selection
            const monthValue = monthFilter.value;
            const yearValue = yearFilter.value;

            console.log('Filtering graduates with:', { nameValue, idValue, courseValue, monthValue, yearValue });

            // Get all graduates from enrollments table
            const enrollmentsTable = document.getElementById('enrollmentsTableBody');
            if (!enrollmentsTable) return;

            const rows = enrollmentsTable.querySelectorAll('tr');
            const filteredGraduates = [];

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    const studentNumber = cells[0].textContent.trim();
                    const course = cells[2].textContent.trim();
                    const status = cells[3].textContent.trim();
                    const endDate = cells[5] ? cells[5].textContent.trim() : '';

                    if (status === 'completed') {
                        let showRow = true;

                        // Apply filters
                        if (nameValue) {
                            const studentName = getStudentName(studentNumber).toLowerCase();
                            console.log(`Checking name filter: "${studentName}" includes "${nameValue}"?`, studentName.includes(nameValue));
                            if (!studentName.includes(nameValue)) {
                                showRow = false;
                            }
                        }
                        if (idValue && !studentNumber.toLowerCase().includes(idValue)) {
                            showRow = false;
                        }
                        if (courseValue && course !== courseValue) {
                            showRow = false;
                        }
                        if (monthValue || yearValue) {
                            const date = new Date(endDate);
                            if (monthValue && date.getMonth() + 1 != monthValue) {
                                showRow = false;
                            }
                            if (yearValue && date.getFullYear() != yearValue) {
                                showRow = false;
                            }
                        }

                        if (showRow) {
                            filteredGraduates.push({
                                student_number: studentNumber,
                                full_name: getStudentName(studentNumber),
                                course: course,
                                graduation_date: endDate,
                                status: 'Completed'
                            });
                        }
                    }
                }
            });

            currentGraduatesData = filteredGraduates;
            renderGraduatesTable(filteredGraduates);
            updatePagination({ current_page: 1, per_page: filteredGraduates.length, total: filteredGraduates.length });
        }

        // Populate course filter dropdown
        populateGraduateCourseFilter();

        // Event listeners
        if (nameFilter) nameFilter.addEventListener('input', filterGraduates);
        if (idFilter) idFilter.addEventListener('input', filterGraduates);
        if (courseFilter) courseFilter.addEventListener('change', filterGraduates);
        if (monthFilter) monthFilter.addEventListener('change', filterGraduates);
        if (yearFilter) yearFilter.addEventListener('change', filterGraduates);

        // Clear filters
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                if (nameFilter) nameFilter.value = '';
                if (idFilter) idFilter.value = '';
                if (courseFilter) courseFilter.value = '';
                if (monthFilter) monthFilter.value = '';
                if (yearFilter) yearFilter.value = '';
                filterGraduates();
            });
        }

        // Refresh graduates functionality
        const refreshBtn = document.getElementById('refreshGraduatesBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                // Add spinning animation
                refreshBtn.classList.add('spinning');
                
                // Load data
                loadGraduatesData();
                
                // Remove spinning animation after a short delay
                setTimeout(function() {
                    refreshBtn.classList.remove('spinning');
                }, 1000);
            });
        }

        // Export graduates functionality
        const exportBtn = document.getElementById('exportGraduatesBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                exportGraduatesCSV();
            });
        }
    }

    // Populate graduate course filter dropdown
    function populateGraduateCourseFilter() {
        const courseFilter = document.getElementById('graduateCourseFilter');
        if (!courseFilter) {
            console.error('Graduate course filter element not found');
            return;
        }

        console.log('Populating graduate course filter with predefined course list');

        // Predefined course list
        const predefinedCourses = [
            'Automotive Servicing',
            'Basic Computer Literacy',
            'Beauty Care (Nail Care)',
            'Bread and Pastry Production',
            'Computer Systems Servicing',
            'Dressmaking',
            'Electrical Installation and Maintenance',
            'Electronic Products and Assembly Servicing',
            'Events Management Services',
            'Food and Beverage Services',
            'Food Processing',
            'Hairdressing',
            'Housekeeping',
            'Massage Therapy',
            'RAC Servicing',
            'Shielded Metal Arc Welding'
        ];
        
        console.log('Using predefined courses for graduates:', predefinedCourses);
        
        // Clear existing options except "All Courses"
        courseFilter.innerHTML = '<option value="">All Courses</option>';
        
        // Add course options
        predefinedCourses.forEach(course => {
            const option = document.createElement('option');
            option.value = course;
            option.textContent = course;
            courseFilter.appendChild(option);
        });
        
        console.log(`Added ${predefinedCourses.length} course options to graduate filter`);
    }

    // Load graduates data from existing enrollments table
    function loadGraduatesData() {
        const tableBody = document.getElementById('graduatesTableBody');
        
        // Get data from the existing enrollments table
        const enrollmentsTable = document.getElementById('enrollmentsTableBody');
        if (!enrollmentsTable) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: #dc3545;">
                            <i class="fas fa-exclamation-triangle"></i> Enrollments table not found
                        </td>
                    </tr>
                `;
            return;
        }

        const rows = enrollmentsTable.querySelectorAll('tr');
        const graduates = [];

        console.log('Scanning enrollments table for completed students...');
        console.log('Found', rows.length, 'rows in enrollments table');

        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                const studentNumber = cells[0].textContent.trim();
                const course = cells[2].textContent.trim();
                const status = cells[3].textContent.trim();
                const endDate = cells[5] ? cells[5].textContent.trim() : '';

                console.log(`Row ${index}: Student=${studentNumber}, Course=${course}, Status=${status}, EndDate=${endDate}`);

                if (status === 'completed') {
                    console.log('Found completed student:', studentNumber);
                    graduates.push({
                        student_number: studentNumber,
                        full_name: getStudentName(studentNumber),
                        course: course,
                        graduation_date: endDate,
                        status: 'Completed'
                    });
                }
            }
        });

        console.log('Total graduates found:', graduates.length);
        currentGraduatesData = graduates;
        renderGraduatesTable(graduates);
        updatePagination({ current_page: 1, per_page: graduates.length, total: graduates.length });
    }


    // Render graduates table
    function renderGraduatesTable(graduates) {
        const tableBody = document.getElementById('graduatesTableBody');
        
        if (graduates.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #666;">
                        <i class="fas fa-graduation-cap"></i> No graduates found
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = graduates.map(graduate => {
            const graduationDate = graduate.graduation_date ? 
                new Date(graduate.graduation_date).toLocaleDateString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                }) : 'N/A';
            
            const statusClass = graduate.status === 'Completed' ? 'completed' : 'in-progress';
            
            return `
                <tr>
                    <td>${graduate.student_number}</td>
                    <td>${graduate.full_name}</td>
                    <td>${graduate.course || 'N/A'}</td>
                    <td>${graduationDate}</td>
                    <td><span class="status-badge ${statusClass}">${graduate.status}</span></td>
                </tr>
            `;
        }).join('');
    }

    // Update pagination
    function updatePagination(pagination) {
        const paginationInfo = document.querySelector('.pagination-info');
        if (paginationInfo) {
            const start = ((pagination.current_page - 1) * pagination.per_page) + 1;
            const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
            paginationInfo.textContent = `Showing ${start}-${end} of ${pagination.total} graduates`;
        }
    }

    // Export graduates to CSV
    function exportGraduatesCSV() {
        if (currentGraduatesData.length === 0) {
            alert('No graduates data to export');
            return;
        }

            let csv = 'Student ID,Full Name,Course Completed,Graduation Date,Status\n';
        
        currentGraduatesData.forEach(graduate => {
            const graduationDate = graduate.completion_date ? 
                new Date(graduate.completion_date).toLocaleDateString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                }) : 'N/A';
            
            const row = [
                `"${graduate.student_number}"`,
                `"${graduate.full_name}"`,
                `"${graduate.course || 'N/A'}"`,
                `"${graduationDate}"`,
                `"${graduate.status}"`
            ].join(',');
            
            csv += row + '\n';
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'graduates_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // View graduate details function
    function viewGraduateDetails(studentId) {
        // This would open a modal or navigate to a detailed view
        alert('Viewing details for student: ' + studentId);
        // You can implement a modal or redirect to a detailed page here
    }

    // Initialize graduates section when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeGraduatesSection();
        
        // Initialize duplicate checking for trainee form
        initializeDuplicateChecking();
        
        // Initialize enhanced enrollment filtering
        initializeEnrollmentFiltering();
    });
    
    // Function to initialize duplicate checking
    function initializeDuplicateChecking() {
        const studentNumberInput = document.getElementById('traineeStudentNumber');
        const emailInput = document.getElementById('traineeEmail');
        const studentNumberCaution = document.getElementById('studentNumberCaution');
        const emailCaution = document.getElementById('emailCaution');
        const addTraineeBtn = document.getElementById('addTraineeBtn');
        
        // Track duplicate states
        const duplicateStates = {
            studentNumber: false,
            email: false
        };
        
        // Function to update button state based on duplicate states
        function updateButtonState() {
            const hasDuplicates = Object.values(duplicateStates).some(state => state);
            if (addTraineeBtn) {
                addTraineeBtn.disabled = hasDuplicates;
                if (hasDuplicates) {
                    addTraineeBtn.classList.add('disabled');
                    addTraineeBtn.title = 'Cannot add trainee with duplicate information';
                } else {
                    addTraineeBtn.classList.remove('disabled');
                    addTraineeBtn.title = '';
                }
            }
        }
        
        // Make functions globally accessible for validation
        window.updateDuplicateState = function(field, isDuplicate) {
            duplicateStates[field] = isDuplicate;
            updateButtonState();
        };
        
        // Debounce function to limit API calls
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Check student number duplicates
        if (studentNumberInput && studentNumberCaution) {
            const checkStudentNumber = debounce(async (studentNumber) => {
                if (studentNumber.length < 3) {
                    hideCaution(studentNumberCaution, studentNumberInput);
                    return;
                }
                
                try {
                    const response = await fetch(`apis/duplicate_check.php?action=check_student_number&student_number=${encodeURIComponent(studentNumber)}`, {
                        credentials: 'same-origin'
                    });
                    const data = await response.json();
                    
                    if (data.success && data.exists) {
                        showCaution(studentNumberCaution, studentNumberInput, data.message);
                        duplicateStates.studentNumber = true;
                    } else {
                        hideCaution(studentNumberCaution, studentNumberInput);
                        duplicateStates.studentNumber = false;
                    }
                    updateButtonState();
                } catch (error) {
                    console.error('Error checking student number:', error);
                }
            }, 500);
            
            studentNumberInput.addEventListener('input', (e) => {
                checkStudentNumber(e.target.value.trim());
            });
        }
        
        // Check email duplicates
        if (emailInput && emailCaution) {
            const checkEmail = debounce(async (email) => {
                if (email.length < 5) {
                    hideCaution(emailCaution, emailInput);
                    return;
                }
                
                try {
                    const response = await fetch(`apis/duplicate_check.php?action=check_email&email=${encodeURIComponent(email)}`, {
                        credentials: 'same-origin'
                    });
                    const data = await response.json();
                    
                    if (data.success && data.exists) {
                        showCaution(emailCaution, emailInput, data.message);
                        duplicateStates.email = true;
                    } else {
                        hideCaution(emailCaution, emailInput);
                        duplicateStates.email = false;
                    }
                    updateButtonState();
                } catch (error) {
                    console.error('Error checking email:', error);
                }
            }, 500);
            
            emailInput.addEventListener('input', (e) => {
                checkEmail(e.target.value.trim());
            });
        }
    }
    
    // Function to show caution message
    function showCaution(cautionElement, inputElement, message) {
        if (cautionElement && inputElement) {
            cautionElement.style.display = 'flex';
            cautionElement.querySelector('span').textContent = message;
            inputElement.classList.add('has-caution');
        }
    }
    
    // Function to hide caution message
    function hideCaution(cautionElement, inputElement) {
        if (cautionElement && inputElement) {
            cautionElement.style.display = 'none';
            inputElement.classList.remove('has-caution');
        }
    }

    // Enhanced Enrollment Filtering System
    let enrollmentFilterState = {
        searchTerm: '',
        status: '',
        course: '',
        dateFrom: '',
        dateTo: '',
        activeFilters: []
    };

    let allEnrollmentsData = [];
    let filteredEnrollmentsData = [];

    // Initialize enhanced enrollment filtering
    function initializeEnrollmentFiltering() {
        console.log('Initializing enhanced enrollment filtering...');
        
        // Set up event listeners
        setupEnrollmentFilterListeners();
        
        // Setup scroll listener
        setupScrollListener();
        
        // Load initial data
        loadEnrollmentData();
    }

    // Set up all filter event listeners
    function setupEnrollmentFilterListeners() {
        // Search input
        const searchInput = document.getElementById('enrollmentSearch');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        
        if (searchInput) {
            searchInput.addEventListener('input', debounce(handleSearchInput, 300));
        }
        
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', clearSearch);
        }

        // No advanced filters panel needed for separate controls

        // Filter controls
        const statusFilter = document.getElementById('enrollmentStatusFilter');
        const courseFilter = document.getElementById('enrollmentCourseFilter');
        const dateFromFilter = document.getElementById('enrollmentDateFrom');
        const dateToFilter = document.getElementById('enrollmentDateTo');
        if (statusFilter) {
            statusFilter.addEventListener('change', handleFilterChange);
        }
        if (courseFilter) {
            courseFilter.addEventListener('change', handleFilterChange);
        }
        if (dateFromFilter) {
            dateFromFilter.addEventListener('change', handleFilterChange);
        }
        if (dateToFilter) {
            dateToFilter.addEventListener('change', handleFilterChange);
        }

        // Filter action buttons
        const clearAllBtn = document.getElementById('clearAllFiltersBtn');

        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', clearAllFilters);
        }
    }

    // Handle search input
    function handleSearchInput(e) {
        const searchTerm = e.target.value.trim();
        enrollmentFilterState.searchTerm = searchTerm;
        
        console.log('🔍 Enrollment Search Input:', searchTerm);
        console.log('📊 Total enrollments data:', allEnrollmentsData.length);
        console.log('🎯 Search input element:', e.target);
        
        // Show/hide clear button
        const clearBtn = document.getElementById('clearSearchBtn');
        if (clearBtn) {
            clearBtn.style.display = searchTerm ? 'block' : 'none';
        }
        
        // Update active filters display
        updateActiveFilters();
        
        // Apply filters immediately for search
        applyFilters();
    }

    // Handle filter changes
    function handleFilterChange(e) {
        const filterType = e.target.id.replace('enrollment', '').replace('Filter', '');
        const value = e.target.value;
        
        console.log(`Filter changed: ${filterType} = ${value}`);
        
        switch (filterType) {
            case 'Status':
                enrollmentFilterState.status = value;
                break;
            case 'Course':
                enrollmentFilterState.course = value;
                console.log('Course filter set to:', value);
                break;
            case 'DateFrom':
                enrollmentFilterState.dateFrom = value;
                break;
            case 'DateTo':
                enrollmentFilterState.dateTo = value;
                break;
        }
        
        updateActiveFilters();
        applyFilters(); // Automatically apply filters when changed
    }

    // Update active filters display
    function updateActiveFilters() {
        const activeFiltersDisplay = document.getElementById('activeFiltersDisplay');
        const activeFiltersList = document.getElementById('activeFiltersList');
        
        if (!activeFiltersDisplay || !activeFiltersList) return;

        const activeFilters = [];
        
        if (enrollmentFilterState.status) {
            activeFilters.push({
                type: 'Status',
                value: enrollmentFilterState.status,
                label: `Status: ${enrollmentFilterState.status}`
            });
        }
        
        if (enrollmentFilterState.course) {
            activeFilters.push({
                type: 'Course',
                value: enrollmentFilterState.course,
                label: `Course: ${enrollmentFilterState.course}`
            });
        }
        
        if (enrollmentFilterState.dateFrom) {
            activeFilters.push({
                type: 'DateFrom',
                value: enrollmentFilterState.dateFrom,
                label: `From: ${enrollmentFilterState.dateFrom}`
            });
        }
        
        if (enrollmentFilterState.dateTo) {
            activeFilters.push({
                type: 'DateTo',
                value: enrollmentFilterState.dateTo,
                label: `To: ${enrollmentFilterState.dateTo}`
            });
        }
        
        if (enrollmentFilterState.searchTerm) {
            activeFilters.push({
                type: 'Search',
                value: enrollmentFilterState.searchTerm,
                label: `Search: "${enrollmentFilterState.searchTerm}"`
            });
        }
        
        // Student type filter removed for simplified interface

        enrollmentFilterState.activeFilters = activeFilters;

        if (activeFilters.length > 0) {
            activeFiltersDisplay.style.display = 'block';
            activeFiltersList.innerHTML = activeFilters.map(filter => `
                <span class="filter-tag">
                    ${filter.label}
                    <span class="remove-tag" onclick="removeFilter('${filter.type}')">×</span>
                </span>
            `).join('');
        } else {
            activeFiltersDisplay.style.display = 'none';
        }
    }

    // Remove specific filter
    function removeFilter(filterType) {
        switch (filterType) {
            case 'Status':
                enrollmentFilterState.status = '';
                document.getElementById('enrollmentStatusFilter').value = '';
                break;
            case 'Course':
                enrollmentFilterState.course = '';
                document.getElementById('enrollmentCourseFilter').value = '';
                break;
            case 'DateFrom':
                enrollmentFilterState.dateFrom = '';
                document.getElementById('enrollmentDateFrom').value = '';
                break;
            case 'DateTo':
                enrollmentFilterState.dateTo = '';
                document.getElementById('enrollmentDateTo').value = '';
                break;
            case 'Search':
                enrollmentFilterState.searchTerm = '';
                const searchInput = document.getElementById('enrollmentSearch');
                const clearBtn = document.getElementById('clearSearchBtn');
                if (searchInput) searchInput.value = '';
                if (clearBtn) clearBtn.style.display = 'none';
                break;
            // Student type filter removed
        }
        
        updateActiveFilters();
        applyFilters();
    }

    // Apply all filters
    function applyFilters() {
        console.log('🔄 Applying enrollment filters...', enrollmentFilterState);
        
        if (allEnrollmentsData.length === 0) {
            console.log('❌ No enrollment data available');
            return;
        }

        filteredEnrollmentsData = allEnrollmentsData.filter(enrollment => {
            // Search filter
            if (enrollmentFilterState.searchTerm) {
                const searchTerm = enrollmentFilterState.searchTerm.toLowerCase();
                const matchesSearch = 
                    enrollment.student_number.toLowerCase().includes(searchTerm) ||
                    (enrollment.student_name && enrollment.student_name.toLowerCase().includes(searchTerm)) ||
                    enrollment.course.toLowerCase().includes(searchTerm);
                
                console.log(`🔍 Searching for "${searchTerm}" in:`, {
                    student_number: enrollment.student_number,
                    student_name: enrollment.student_name,
                    course: enrollment.course,
                    matches: matchesSearch
                });
                
                if (!matchesSearch) return false;
            }

            // Status filter
            if (enrollmentFilterState.status && enrollment.enrollment_status !== enrollmentFilterState.status) {
                return false;
            }

            // Course filter
            if (enrollmentFilterState.course && enrollment.course !== enrollmentFilterState.course) {
                console.log(`Course filter: ${enrollment.course} !== ${enrollmentFilterState.course}`);
                return false;
            }

            // Date range filters
            if (enrollmentFilterState.dateFrom) {
                const enrollmentDate = new Date(enrollment.enrollment_start_date);
                const fromDate = new Date(enrollmentFilterState.dateFrom);
                if (enrollmentDate < fromDate) return false;
            }

            if (enrollmentFilterState.dateTo) {
                const enrollmentDate = new Date(enrollment.enrollment_start_date);
                const toDate = new Date(enrollmentFilterState.dateTo);
                if (enrollmentDate > toDate) return false;
            }

            // Student type filter removed for simplified interface

            return true;
        });

        console.log(`✅ Filtered ${filteredEnrollmentsData.length} enrollments from ${allEnrollmentsData.length} total`);
        
        // Add visual indicator for search
        const searchInput = document.getElementById('enrollmentSearch');
        if (searchInput && enrollmentFilterState.searchTerm) {
            searchInput.style.backgroundColor = '#f0f9ff';
            searchInput.style.borderColor = '#3b82f6';
        } else if (searchInput) {
            searchInput.style.backgroundColor = '';
            searchInput.style.borderColor = '';
        }
        
        renderFilteredEnrollments();
        updateStats();
    }

    // Render filtered enrollments
    function renderFilteredEnrollments() {
        console.log('🎨 Rendering filtered enrollments...', filteredEnrollmentsData.length);
        const tbody = document.getElementById('enrollmentsTableBody');
        if (!tbody) {
            console.error('❌ Table body not found!');
            return;
        }

        if (filteredEnrollmentsData.length === 0) {
            tbody.innerHTML = '';
            return;
        }

        tbody.innerHTML = filteredEnrollmentsData.map(enrollment => {
            const actions = `
                <button class="en-status" data-id="${enrollment.id}" data-status="completed">Mark Completed</button>
                <button class="en-status" data-id="${enrollment.id}" data-status="withdrawn">Withdraw</button>
                <button class="en-adjust" data-id="${enrollment.id}">Adjust Dates</button>
            `;
            
            const studentName = enrollment.student_name || getStudentName(enrollment.student_number);
            
            return `
                <tr>
                    <td>${enrollment.student_number}</td>
                    <td>${studentName}</td>
                    <td>${enrollment.course}</td>
                    <td><span class="status-badge ${enrollment.enrollment_status || 'enrolled'}">${enrollment.enrollment_status || 'enrolled'}</span></td>
                    <td>${enrollment.enrollment_start_date || ''}</td>
                    <td>${enrollment.enrollment_end_date || ''}</td>
                    <td>${actions}</td>
                </tr>
            `;
        }).join('');
    }

    // Update statistics
    function updateStats() {
        const totalCount = document.getElementById('totalEnrollmentsCount');
        const filteredCount = document.getElementById('filteredEnrollmentsCount');
        const tableInfo = document.getElementById('enrollmentsTableInfo');
        
        if (totalCount) {
            totalCount.textContent = allEnrollmentsData.length;
        }
        
        if (filteredCount) {
            filteredCount.textContent = filteredEnrollmentsData.length;
        }
        
        // Update table info
        if (tableInfo) {
            if (filteredEnrollmentsData.length === 0) {
                tableInfo.textContent = 'No enrollments found';
            } else if (filteredEnrollmentsData.length === allEnrollmentsData.length) {
                tableInfo.textContent = `Showing all ${allEnrollmentsData.length} enrollments`;
            } else {
                tableInfo.textContent = `Showing ${filteredEnrollmentsData.length} of ${allEnrollmentsData.length} enrollments`;
            }
        }
        
        // Show/hide scroll indicator
        updateScrollIndicator();
    }
    
    // Update scroll indicator visibility
    function updateScrollIndicator() {
        const scrollWrapper = document.getElementById('enrollmentsScrollWrapper');
        const scrollIndicator = document.getElementById('scrollIndicator');
        const container = document.querySelector('.scrollable-table-container');
        
        if (scrollWrapper && scrollIndicator && container) {
            const hasScroll = scrollWrapper.scrollHeight > scrollWrapper.clientHeight;
            
            if (hasScroll && filteredEnrollmentsData.length > 5) {
                scrollIndicator.style.display = 'flex';
            } else {
                scrollIndicator.style.display = 'none';
            }
        }
    }
    
    // Setup scroll event listener
    function setupScrollListener() {
        const scrollWrapper = document.getElementById('enrollmentsScrollWrapper');
        const container = document.querySelector('.scrollable-table-container');
        
        if (scrollWrapper && container) {
            scrollWrapper.addEventListener('scroll', () => {
                const scrolled = scrollWrapper.scrollTop > 10;
                container.classList.toggle('scrolled', scrolled);
            });
        }
    }

    // Load enrollment data
    function loadEnrollmentData() {
        console.log('📥 Loading enrollment data...');
        
        fetch('../apis/enrollment_admin.php?action=list', { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                console.log('📡 API Response:', data);
                if (data && data.success && data.data) {
                    // Process the data to create student_name field for search
                    allEnrollmentsData = data.data.map(enrollment => ({
                        ...enrollment,
                        student_name: `${enrollment.first_name || ''} ${enrollment.last_name || ''}`.trim()
                    }));
                    filteredEnrollmentsData = [...allEnrollmentsData];
                    console.log(`✅ Loaded ${allEnrollmentsData.length} enrollments`);
                    console.log('📋 Sample enrollment data:', allEnrollmentsData[0]);
                    
                    // Populate course filter after data is loaded
                    populateCourseFilter();
                    
                    renderFilteredEnrollments();
                    updateStats();
                } else {
                    console.error('Failed to load enrollment data:', data);
                }
            })
            .catch(error => {
                console.error('Error loading enrollment data:', error);
            });
    }

    // Populate course filter options
    function populateCourseFilter() {
        const courseFilter = document.getElementById('enrollmentCourseFilter');
        if (!courseFilter) {
            console.error('Course filter element not found');
            return;
        }

        console.log('Populating course filter with predefined course list');

        // Predefined course list without abbreviations
        const predefinedCourses = [
            'Automotive Servicing',
            'Basic Computer Literacy',
            'Beauty Care (Nail Care)',
            'Bread and Pastry Production',
            'Computer Systems Servicing',
            'Dressmaking',
            'Electrical Installation and Maintenance',
            'Electronic Products and Assembly Servicing',
            'Events Management Services',
            'Food and Beverage Services',
            'Food Processing',
            'Hairdressing',
            'Housekeeping',
            'Massage Therapy',
            'RAC Servicing',
            'Shielded Metal Arc Welding'
        ];
        
        console.log('Using predefined courses:', predefinedCourses);
        
        // Clear existing options except "All Courses"
        courseFilter.innerHTML = '<option value="">All Courses</option>';
        
        // Add course options
        predefinedCourses.forEach(course => {
            const option = document.createElement('option');
            option.value = course;
            option.textContent = course;
            courseFilter.appendChild(option);
        });
        
        console.log(`Added ${predefinedCourses.length} course options to filter`);
    }

    // Clear search
    function clearSearch() {
        const searchInput = document.getElementById('enrollmentSearch');
        const clearBtn = document.getElementById('clearSearchBtn');
        
        if (searchInput) {
            searchInput.value = '';
            enrollmentFilterState.searchTerm = '';
        }
        
        if (clearBtn) {
            clearBtn.style.display = 'none';
        }
        
        applyFilters();
    }

    // Clear all filters
    function clearAllFilters() {
        enrollmentFilterState = {
            searchTerm: '',
            status: '',
            course: '',
            dateFrom: '',
            dateTo: '',
            activeFilters: []
        };

        // Reset form elements
        const searchInput = document.getElementById('enrollmentSearch');
        const statusFilter = document.getElementById('enrollmentStatusFilter');
        const courseFilter = document.getElementById('enrollmentCourseFilter');
        const dateFromFilter = document.getElementById('enrollmentDateFrom');
        const dateToFilter = document.getElementById('enrollmentDateTo');
        const clearBtn = document.getElementById('clearSearchBtn');

        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (courseFilter) courseFilter.value = '';
        if (dateFromFilter) dateFromFilter.value = '';
        if (dateToFilter) dateToFilter.value = '';
        if (clearBtn) clearBtn.style.display = 'none';

        updateActiveFilters();
        applyFilters();
    }

    // Debounce function for search input
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    </script>

    <style>
    /* Enhanced Enrollment Filtering Styles */
    .enhanced-search {
        position: relative !important;
        display: flex !important;
        align-items: center !important;
        background: #fff !important;
        border: 2px solid #e1e5e9 !important;
        border-radius: 8px !important;
        padding: 8px 12px !important;
        transition: all 0.3s ease !important;
        min-width: 300px !important;
        max-width: 100% !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
    }
    
    @media (max-width: 768px) {
        .enhanced-search {
            min-width: 250px;
            padding: 6px 10px;
        }
    }
    
    .enhanced-search:focus-within {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    
    .enhanced-search .search-input {
        border: none !important;
        outline: none !important;
        flex: 1 !important;
        padding: 0 !important;
        background: transparent !important;
        font-size: 14px !important;
        color: #495057 !important;
        width: auto !important;
    }
    
    .enhanced-search .search-input::placeholder {
        color: #6c757d;
        font-style: italic;
    }
    
    .enhanced-search i.fa-search {
        color: #6c757d !important;
        margin-right: 8px !important;
        font-size: 14px !important;
        transition: color 0.2s ease !important;
        position: static !important;
        left: auto !important;
        top: auto !important;
        transform: none !important;
        z-index: auto !important;
    }
    
    .enhanced-search:focus-within i.fa-search {
        color: #007bff;
    }
    
    /* Dark theme support */
    body.dark-theme .enhanced-search {
        background: #2d3748;
        border-color: #4a5568;
        color: #e2e8f0;
    }
    
    body.dark-theme .enhanced-search .search-input {
        color: #e2e8f0;
    }
    
    body.dark-theme .enhanced-search .search-input::placeholder {
        color: #a0aec0;
    }
    
    body.dark-theme .enhanced-search i.fa-search {
        color: #a0aec0;
    }
    
    body.dark-theme .enhanced-search:focus-within {
        border-color: #3182ce;
        box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
    }
    
    body.dark-theme .enhanced-search:focus-within i.fa-search {
        color: #3182ce;
    }
    
    body.dark-theme .clear-search-btn {
        color: #a0aec0;
    }
    
    body.dark-theme .clear-search-btn:hover {
        background: #4a5568;
        color: #fc8181;
    }
    
    /* Table Actions Layout */
    .table-actions {
        display: flex;
        flex-direction: column;
        gap: 16px;
        width: 100%;
    }
    
    /* Modern Search Box Styles */
    .search-container {
        margin-bottom: 20px;
        width: 100%;
        display: flex;
        justify-content: flex-start;
    }
    
    .search-box {
        position: relative;
        display: flex;
        align-items: center;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 12px 16px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        min-width: 400px;
        max-width: 600px;
        width: 100%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    }
    
    .search-box:hover {
        border-color: #9ca3af;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
    }
    
    .search-box:focus-within {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .search-box .search-input {
        border: none;
        outline: none;
        flex: 1;
        padding: 0;
        margin-left: 24px;
        background: transparent;
        font-size: 15px;
        color: #111827;
        width: 100%;
        min-width: 0;
        font-weight: 400;
    }
    
    .search-box .search-input::placeholder {
        color: #9ca3af;
        font-weight: 400;
    }
    
    .search-box i.fa-search {
        color: #6b7280;
        font-size: 16px;
        transition: color 0.2s ease;
        flex-shrink: 0;
        width: 20px;
        text-align: center;
    }
    
    .search-box:focus-within i.fa-search {
        color: #3b82f6;
    }
    
    .clear-search-btn {
        background: #f3f4f6;
        border: none;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        border-radius: 6px;
        transition: all 0.2s ease;
        margin-left: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        opacity: 0.8;
        position: relative;
    }
    
    .clear-search-btn:hover {
        background: #e5e7eb;
        color: #dc2626;
        opacity: 1;
        transform: scale(1.05);
    }
    
    .clear-search-btn i {
        font-size: 12px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    
    /* Dark theme support for search box */
    body.dark-theme .search-box {
        background: #1f2937;
        border-color: #374151;
        color: #f9fafb;
    }
    
    body.dark-theme .search-box:hover {
        border-color: #6b7280;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3), 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    body.dark-theme .search-box:focus-within {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2), 0 4px 6px rgba(0, 0, 0, 0.3);
    }
    
    body.dark-theme .search-box .search-input {
        color: #f9fafb;
    }
    
    body.dark-theme .search-box .search-input::placeholder {
        color: #9ca3af;
    }
    
    body.dark-theme .search-box i.fa-search {
        color: #9ca3af;
    }
    
    body.dark-theme .search-box:focus-within i.fa-search {
        color: #3b82f6;
    }
    
    body.dark-theme .clear-search-btn {
        background: #374151;
        color: #9ca3af;
    }
    
    body.dark-theme .clear-search-btn:hover {
        background: #4b5563;
        color: #f87171;
    }
    
    body.dark-theme .clear-search-btn i {
        font-size: 12px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    
    /* Responsive design for search box */
    @media (max-width: 768px) {
        .search-container {
            margin-bottom: 16px;
            justify-content: center;
        }
        
        .search-box {
            min-width: 320px;
            max-width: 100%;
            padding: 10px 14px;
        }
        
        .search-box .search-input {
            font-size: 16px; /* Prevents zoom on iOS */
        }
    }
    
    @media (max-width: 480px) {
        .search-box {
            min-width: 280px;
            padding: 8px 12px;
        }
        
        .search-box .search-input {
            font-size: 16px;
        }
    }
    
    
    .separate-filters-container {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: end;
        margin-top: 12px;
        padding: 16px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e1e5e9;
    }
    
    .filter-control {
        display: flex;
        flex-direction: column;
        min-width: 180px;
        flex: 1;
    }
    
    .filter-control .filter-label {
        margin-bottom: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filter-control .filter-select,
    .filter-control .filter-input {
        padding: 8px 12px;
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.2s ease;
        background: #fff;
    }
    
    .filter-control .filter-select:focus,
    .filter-control .filter-input:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
    }
    
    .separate-filters-container .filter-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 8px;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filter-label i {
        color: #007bff;
        font-size: 11px;
    }
    
    .filter-select, .filter-input {
        padding: 8px 12px;
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.2s ease;
        background: #fff;
    }
    
    .filter-select:focus, .filter-input:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        padding-top: 16px;
        border-top: 1px solid #e1e5e9;
    }
    
    .active-filters {
        margin-top: 8px;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 6px;
        border: 1px solid #e1e5e9;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .active-filters-label {
        font-size: 11px;
        font-weight: 600;
        color: #495057;
        margin: 0;
        white-space: nowrap;
    }
    
    .active-filters-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }
    
    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #e3f2fd;
        color: #1976d2;
        padding: 3px 6px;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 500;
        white-space: nowrap;
    }
    
    .filter-tag .remove-tag {
        cursor: pointer;
        color: #1976d2;
        font-weight: bold;
        margin-left: 4px;
    }
    
    .filter-tag .remove-tag:hover {
        color: #d32f2f;
    }
    
    
    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        background: #f8f9fa;
        color: #495057;
        border: 1px solid #e1e5e9;
    }
    
    .stat-badge:first-child {
        background: #e3f2fd;
        color: #1976d2;
        border-color: #bbdefb;
    }
    
    .stat-badge:last-child {
        background: #f3e5f5;
        color: #7b1fa2;
        border-color: #ce93d8;
    }
    
    .filter-toggle-icon {
        transition: transform 0.3s ease;
        margin-left: 4px;
    }
    
    .advanced-filters-panel.show .filter-toggle-icon {
        transform: rotate(180deg);
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .separate-filters-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-control {
            min-width: 100%;
            margin-bottom: 12px;
        }
        
        .enhanced-search {
            min-width: 200px;
        }
        
        .separate-filters-container .filter-actions {
            justify-content: center;
            flex-wrap: wrap;
        }
    }
    
    /* Loading States */
    .filter-loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .filter-loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Enhanced Table Filtering */
    .table-row-hidden {
        display: none !important;
    }
    
    .table-row-filtered {
        background-color: #f8f9fa;
    }
    
    
    /* Scrollable Table Styles */
    .scrollable-table-container {
        max-height: 500px;
        overflow: hidden;
        border-radius: 8px;
        border: 1px solid #e1e5e9;
    }
    
    .table-scroll-wrapper {
        max-height: 500px;
        overflow-y: auto;
        overflow-x: auto;
        position: relative;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
        transition: background 0.3s ease;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    .sticky-header {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .sticky-header th {
        background: #f8f9fa;
        border-bottom: 2px solid #e1e5e9;
        font-weight: 600;
        color: #495057;
        padding: 12px 8px;
        white-space: nowrap;
    }
    
    /* Enhanced table styling for scrollable container */
    .scrollable-table-container .modern-table {
        margin: 0;
        border: none;
    }
    
    .scrollable-table-container .modern-table tbody tr {
        border-bottom: 1px solid #f0f0f0;
    }
    
    .scrollable-table-container .modern-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .scrollable-table-container .modern-table tbody tr:last-child {
        border-bottom: none;
    }
    
    /* Responsive scrollable table */
    @media (max-width: 768px) {
        .scrollable-table-container {
            max-height: 400px;
        }
        
        .table-scroll-wrapper {
            max-height: 400px;
        }
        
        .scrollable-table-container .modern-table {
            font-size: 13px;
        }
        
        .scrollable-table-container .modern-table th,
        .scrollable-table-container .modern-table td {
            padding: 8px 6px;
        }
    }
    
    /* Loading state for scrollable table */
    .scrollable-table-container .loading-cell {
        position: sticky;
        top: 50%;
        transform: translateY(-50%);
    }
    
    
    /* Scroll indicator */
    .scroll-indicator {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: rgba(0, 123, 255, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        z-index: 20;
        animation: bounce 2s infinite;
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
    }
    
    .scroll-indicator i {
        font-size: 10px;
    }
    
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-5px);
        }
        60% {
            transform: translateY(-3px);
        }
    }
    
    /* Table info */
    .table-info {
        padding: 12px;
        background: #f8f9fa;
        border-top: 1px solid #e1e5e9;
        font-size: 14px;
        color: #6c757d;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
    }
    
    .table-info-text {
        font-weight: 500;
        margin: 0;
    }
    
    /* Hide scroll indicator when scrolled */
    .scrollable-table-container.scrolled .scroll-indicator {
        display: none !important;
    }
    
    /* Preservation notice styling */
    .preservation-notice {
        margin-top: 15px;
        padding: 12px;
        background: rgba(34, 197, 94, 0.1);
        border: 1px solid rgba(34, 197, 94, 0.3);
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #166534;
        font-size: 14px;
    }
    
    .preservation-notice i {
        color: #16a34a;
        font-size: 16px;
    }
    </style>

</body>

    <script src="js/cross-tab-logout.js"></script>
</html>