<?php
require_once '../security/db_connect.php';
require_once '../security/session_config.php';
require_once '../security/csrf.php';
require_once '../security/auth_functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Ensure table exists (first-run safety)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS nc2_validations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course VARCHAR(150) DEFAULT NULL,
        nc2_link TEXT NOT NULL,
        status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
        admin_id INT DEFAULT NULL,
        confirmed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) {
    // Ignore if cannot create; subsequent queries will fail with clear message
}

function json_ok($data = []) { echo json_encode(['success' => true] + $data); exit(); }
function json_err($message, $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $message]); exit(); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Resolve current student's numeric id from session
function resolveStudentId(PDO $pdo) {
    // Direct student_id in session
    $sid = (int)($_SESSION['user']['student_id'] ?? $_SESSION['student_id'] ?? 0);
    if ($sid > 0) return $sid;
    // Try from logged-in user id
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['id'] ?? 0);
    if ($userId > 0) {
        try {
            $s = $pdo->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
            if ($s->execute([$userId])) {
                $r = $s->fetch(PDO::FETCH_ASSOC);
                if ($r && !empty($r['id'])) return (int)$r['id'];
            }
        } catch (Throwable $_e) { /* ignore */ }
    }
    // Try from student_number in session
    $studentNumber = trim((string)($_SESSION['user']['student_number'] ?? $_SESSION['student_number'] ?? ''));
    if ($studentNumber !== '') {
        try {
            $s = $pdo->prepare('SELECT id FROM students WHERE student_number = ? LIMIT 1');
            if ($s->execute([$studentNumber])) {
                $r = $s->fetch(PDO::FETCH_ASSOC);
                if ($r && !empty($r['id'])) return (int)$r['id'];
            }
        } catch (Throwable $_e) { /* ignore */ }
    }
    return 0;
}

try {
    if ($method === 'GET') {
        if ($action === 'pending') {
            requireAnyRole(['admin','instructor']);
            $stmt = $pdo->prepare("SELECT nv.id, nv.student_id, nv.course, nv.nc2_link, nv.status, nv.created_at,
                                           COALESCE(NULLIF(CONCAT(TRIM(s.first_name),' ',TRIM(s.last_name)), ' '), s.student_number, s.email) AS student_name
                                      FROM nc2_validations nv
                                 LEFT JOIN students s ON s.id = nv.student_id
                                     WHERE nv.status = 'pending'
                                  ORDER BY nv.created_at DESC
                                  LIMIT 200");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            json_ok(['data' => $rows]);
        }
        if ($action === 'history') {
            requireAnyRole(['admin','instructor']);
            $stmt = $pdo->prepare("SELECT nv.id, nv.student_id, nv.course, nv.nc2_link, nv.status, nv.created_at, nv.confirmed_at,
                                           COALESCE(NULLIF(CONCAT(TRIM(s.first_name),' ',TRIM(s.last_name)), ' '), s.student_number, s.email) AS student_name
                                      FROM nc2_validations nv
                                 LEFT JOIN students s ON s.id = nv.student_id
                                  ORDER BY nv.created_at DESC
                                  LIMIT 200");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            json_ok(['data' => $rows]);
        }
        if ($action === 'status') {
            requireAuth();
            $studentId = resolveStudentId($pdo);
            if ($studentId <= 0) json_ok(['status' => 'unknown']);
            $stmt = $pdo->prepare("SELECT status, course FROM nc2_validations WHERE student_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$studentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            json_ok(['status' => $row['status'] ?? 'none', 'course' => $row['course'] ?? null]);
        }
        // Default: not found
        json_err('Invalid action', 404);
    }

    if ($method === 'POST') {
        $act = $action;
        if ($act === 'submit') {
            requireRole('student');
            csrfRequireValid();
            $studentId = resolveStudentId($pdo);
            $course = trim($_POST['course'] ?? ($_SESSION['user']['course'] ?? $_SESSION['course'] ?? ''));
            $link = trim($_POST['nc2_link'] ?? $_POST['link'] ?? '');
            if ($studentId <= 0) json_err('Student not identified', 401);
            if ($link === '') json_err('NC2 link is required');
            // Basic URL validation
            if (!preg_match('/^https?:\/\//i', $link)) json_err('Invalid URL');
            $stmt = $pdo->prepare("INSERT INTO nc2_validations (student_id, course, nc2_link, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$studentId, $course !== '' ? $course : null, $link]);
            json_ok(['id' => $pdo->lastInsertId()]);
        }

        if ($act === 'confirm' || $act === 'reject') {
            requireAnyRole(['admin','instructor']);
            csrfRequireValid();
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) json_err('Invalid id');
            $status = $act === 'confirm' ? 'confirmed' : 'rejected';
            $adminId = (int)($_SESSION['user']['id'] ?? $_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE nc2_validations SET status = ?, admin_id = ?, confirmed_at = CASE WHEN ? = 'confirmed' THEN NOW() ELSE confirmed_at END WHERE id = ?");
            $stmt->execute([$status, $adminId > 0 ? $adminId : null, $status, $id]);
            // If confirmed, email the student with current job recommendations
            if ($status === 'confirmed') {
                try {
                    // Get student and course associated with this validation
                    $s = $pdo->prepare("SELECT nv.student_id, COALESCE(nv.course, s.course) AS course, s.email, s.first_name, s.last_name FROM nc2_validations nv JOIN students s ON s.id = nv.student_id WHERE nv.id = ? LIMIT 1");
                    $s->execute([$id]);
                    $row = $s->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($row && !empty($row['email'])) {
                        // Fetch jobs (filter by course if available)
                        $jobs = [];
                        try {
                            if (!empty($row['course'])) {
                                // Fuzzy match both directions and include general jobs with no course
                                $sj = $pdo->prepare(
                                    "SELECT id, title, company, location, salary, experience, description, course
                                       FROM jobs
                                      WHERE is_active = 1
                                        AND (
                                             course IS NULL OR course = ''
                                          OR LOWER(course) LIKE LOWER(CONCAT('%', ?, '%'))
                                          OR LOWER(?) LIKE LOWER(CONCAT('%', course, '%'))
                                        )
                                   ORDER BY created_at DESC
                                      LIMIT 5"
                                );
                                $sj->execute([$row['course'], $row['course']]);
                            } else {
                                $sj = $pdo->query("SELECT id, title, company, location, salary, experience, description, course FROM jobs WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
                            }
                            $jobs = $sj->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        } catch (Throwable $_e) { /* ignore */ }

                        // Prepare mailer
                        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                            @require_once __DIR__ . '/../vendor/autoload.php';
                        }
                        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = SMTP_HOST;
                                $mail->SMTPAuth = true;
                                $mail->Username = SMTP_USERNAME;
                                $mail->Password = SMTP_PASSWORD;
                                $mail->SMTPSecure = SMTP_SECURE;
                                $mail->Port = SMTP_PORT;
                                $mail->CharSet = 'UTF-8';
                                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                                $mail->addAddress($row['email']);
                                $mail->isHTML(true);
                                $studentName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                $subject = 'NC2 Approved - Job Recommendations For You';
                                $listHtml = '';
                                foreach ($jobs as $j) {
                                    $listHtml .= "<li style='margin:8px 0;padding:8px;border:1px solid #e5e7eb;border-radius:6px;'>"
                                              . "<div style='font-weight:600;'>" . htmlspecialchars($j['title'] ?? '') . "</div>"
                                              . "<div style='color:#374151;'>" . htmlspecialchars($j['company'] ?? '') . "</div>"
                                              . (!empty($j['course']) ? ("<div style='color:#374151;'><strong>Course:</strong> " . htmlspecialchars($j['course']) . "</div>") : '')
                                              . "<div style='display:flex;gap:12px;color:#374151;'><span><strong>Location:</strong> " . htmlspecialchars($j['location'] ?? '') . "</span><span><strong>Salary:</strong> " . htmlspecialchars($j['salary'] ?? '—') . "</span><span><strong>Experience:</strong> " . htmlspecialchars($j['experience'] ?? '—') . "</span></div>"
                                              . "</li>";
                                }
                                if ($listHtml === '') {
                                    $listHtml = "<li>No current jobs found. Please check back soon.</li>";
                                }
                                $bodyHtml = "<div style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;'>"
                                          . "<h2 style='color:#0f5132;'>Your NC2 Was Approved</h2>"
                                          . "<p>Hi " . htmlspecialchars($studentName !== '' ? $studentName : 'Student') . ", your NC2 was approved. Here are recent job recommendations:</p>"
                                          . "<ul style='padding-left:18px;'>$listHtml</ul>"
                                          . "<p>Visit your student dashboard to view more and apply.</p>"
                                          . "</div>";
                                $bodyText = "Your NC2 was approved. Recent jobs:\n";
                                foreach ($jobs as $j) {
                                    $bodyText .= '- ' . ($j['title'] ?? '') . ' @ ' . ($j['company'] ?? '') . ' | ' . ($j['location'] ?? '') . "\n";
                                }
                                if (empty($jobs)) {
                                    $bodyText .= 'No current jobs found. Please check back soon.';
                                }
                                $mail->Subject = $subject;
                                $mail->Body = $bodyHtml;
                                $mail->AltBody = $bodyText;
                                $mail->send();
                            } catch (Throwable $_m) { /* ignore mail errors */ }
                        }
                    }
                } catch (Throwable $_e) { /* ignore notify errors */ }
            }
            json_ok();
        }

        json_err('Invalid action', 404);
    }

    json_err('Method not allowed', 405);
} catch (Throwable $e) {
    json_err($e->getMessage());
}

?>


