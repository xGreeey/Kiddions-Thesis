<?php
require_once '../security/db_connect.php';
require_once '../security/session_config.php';
require_once '../security/csrf.php';
require_once '../security/auth_functions.php';

header('Content-Type: application/json');
// CORS and AJAX-friendly headers (help avoid hosting-level 403 redirects)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight quickly
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper: detect optional `course` column
$hasCourseColumn = null; // unknown initially

try {
    if ($method === 'GET') {
        requireAuth();
        // List jobs; attempt to include course if available
        try {
            $stmt = $pdo->prepare("SELECT id, title, company, location, salary, experience, description, is_active, created_at, course FROM jobs WHERE is_active = 1 ORDER BY created_at DESC LIMIT 100");
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $jobs]);
            exit();
        } catch (Throwable $e) {
            $stmt = $pdo->prepare("SELECT id, title, company, location, salary, experience, description, is_active, created_at FROM jobs WHERE is_active = 1 ORDER BY created_at DESC LIMIT 100");
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $jobs]);
            exit();
        }
    }

    if ($method === 'POST') {
        requireAnyRole(['admin','instructor']);
        csrfRequireValid();
        // Action-based POST (avoids CORS preflight on some hosts)
        $action = $_POST['action'] ?? $_POST['act'] ?? '';
        if ($action === 'delete' || $action === 'delete_job') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid job id');
            }
            $stmt = $pdo->prepare("UPDATE jobs SET is_active = 0 WHERE id = ? AND is_active = 1");
            $stmt->execute([$id]);
            if ($stmt->rowCount() < 1) {
                echo json_encode(['success' => false, 'message' => 'Job not found or already deleted', 'id' => $id]);
            } else {
                echo json_encode(['success' => true, 'id' => $id]);
            }
            exit();
        }

        // Add job (default)
        $title = trim($_POST['jobTitle'] ?? '');
        $company = trim($_POST['companyName'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $salary = trim($_POST['salary'] ?? '');
        $experience = trim($_POST['experience'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course = trim($_POST['course'] ?? '');

        if ($title === '' || $company === '' || $location === '') {
            throw new Exception('Title, company, and location are required');
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO jobs (title, company, location, salary, experience, description, course) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $company, $location, $salary, $experience, $description, $course]);
            $newJobId = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            $stmt = $pdo->prepare("INSERT INTO jobs (title, company, location, salary, experience, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $company, $location, $salary, $experience, $description]);
            $newJobId = (int)$pdo->lastInsertId();
        }

        // Attempt to notify relevant students via email about the new job
        try {
            // Load mailer
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                @require_once __DIR__ . '/../vendor/autoload.php';
            }
            $mailerAvailable = class_exists('PHPMailer\\PHPMailer\\PHPMailer');

            // Fetch the job just inserted (with course if present)
            $jobRow = null;
            try {
                $s = $pdo->prepare('SELECT id, title, company, location, salary, experience, description, course FROM jobs WHERE id = ?');
                $s->execute([$newJobId]);
                $jobRow = $s->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $_e) { /* ignore */ }

            if ($mailerAvailable && $jobRow) {
                // Determine recipient students: NC2 confirmed; optionally match course if available
                $params = [];
                // Only consider latest NC2 record per student and ensure it's confirmed
                if (!empty($jobRow['course'])) {
                    $q = "SELECT DISTINCT s.email
                            FROM students s
                            JOIN (
                                  SELECT nv1.*
                                    FROM nc2_validations nv1
                                   JOIN (
                                         SELECT student_id, MAX(id) AS max_id
                                           FROM nc2_validations
                                       GROUP BY student_id
                                   ) latest ON latest.max_id = nv1.id
                                   WHERE nv1.status = 'confirmed'
                                 ) nv ON nv.student_id = s.id
                           WHERE (
                                   s.course IS NULL OR s.course = ''
                                OR LOWER(s.course) LIKE LOWER(CONCAT('%', ?, '%'))
                                OR LOWER(?) LIKE LOWER(CONCAT('%', s.course, '%'))
                                OR LOWER(COALESCE(nv.course, '')) LIKE LOWER(CONCAT('%', ?, '%'))
                                OR LOWER(?) LIKE LOWER(CONCAT('%', COALESCE(nv.course, ''), '%'))
                                 )";
                    $st = $pdo->prepare($q);
                    $st->execute([$jobRow['course'], $jobRow['course'], $jobRow['course'], $jobRow['course']]);
                } else {
                    $q = "SELECT DISTINCT s.email
                            FROM students s
                            JOIN (
                                  SELECT nv1.*
                                    FROM nc2_validations nv1
                                   JOIN (
                                         SELECT student_id, MAX(id) AS max_id
                                           FROM nc2_validations
                                       GROUP BY student_id
                                   ) latest ON latest.max_id = nv1.id
                                   WHERE nv1.status = 'confirmed'
                                 ) nv ON nv.student_id = s.id";
                    $st = $pdo->query($q);
                }
                $emails = array_values(array_filter(array_map(function($r){ return trim((string)($r['email'] ?? '')); }, $st->fetchAll(PDO::FETCH_ASSOC) ?: [])));

                if (!empty($emails)) {
                    // Build mail content
                    $subject = 'New Job Recommendation: ' . $jobRow['title'];
                    $bodyHtml = "<div style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;'>"
                              . "<h2 style='color:#003366;margin:0 0 8px;'>New Job Available</h2>"
                              . "<p style='margin:0 0 12px;'>We found a new job that matches your profile.</p>"
                              . "<div style='border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:12px 0;'>"
                              . "<div style='font-size:18px;font-weight:600;'>" . htmlspecialchars($jobRow['title'] ?? '') . "</div>"
                              . "<div style='color:#374151;margin:4px 0;'>" . htmlspecialchars($jobRow['company'] ?? '') . "</div>"
                              . (!empty($jobRow['course']) ? ("<div style='color:#374151;margin:4px 0;'><strong>Course:</strong> " . htmlspecialchars($jobRow['course']) . "</div>") : '')
                              . "<div style='display:flex;gap:16px;color:#374151;margin:4px 0;'>"
                              . "<span><strong>Location:</strong> " . htmlspecialchars($jobRow['location'] ?? '') . "</span>"
                              . "<span><strong>Salary:</strong> " . htmlspecialchars($jobRow['salary'] ?? '—') . "</span>"
                              . "<span><strong>Experience:</strong> " . htmlspecialchars($jobRow['experience'] ?? '—') . "</span>"
                              . "</div>"
                              . (!empty($jobRow['description']) ? ("<p style='margin-top:8px;white-space:pre-wrap;'>" . nl2br(htmlspecialchars($jobRow['description'])) . "</p>") : '')
                              . "</div>"
                              . "<p style='margin-top:12px;'>Visit your student dashboard to view and apply.</p>"
                              . "</div>";
                    $bodyText = 'New Job Available: ' . ($jobRow['title'] ?? '') . "\n"
                              . ($jobRow['company'] ?? '') . "\n"
                              . (!empty($jobRow['course']) ? ('Course: ' . $jobRow['course'] . "\n") : '')
                              . 'Location: ' . ($jobRow['location'] ?? '') . "\n"
                              . 'Salary: ' . ($jobRow['salary'] ?? '—') . "\n"
                              . 'Experience: ' . ($jobRow['experience'] ?? '—') . "\n\n"
                              . 'See your student dashboard to view and apply.';

                    // Send in small batches to avoid large RCPT TO
                    $batchSize = 25;
                    for ($i = 0; $i < count($emails); $i += $batchSize) {
                        $chunk = array_slice($emails, $i, $batchSize);
                        try {
                            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
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
                            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                            foreach ($chunk as $em) { if (filter_var($em, FILTER_VALIDATE_EMAIL)) { $mail->addAddress($em); } }
                            $mail->isHTML(true);
                            $mail->Subject = $subject;
                            $mail->Body = $bodyHtml;
                            $mail->AltBody = $bodyText;
                            $mail->send();
                        } catch (Throwable $_e) { /* swallow mail errors */ }
                    }
                }
            }
        } catch (Throwable $_err) {
            // ignore mailer/notification errors to not block job creation
        }

        echo json_encode(['success' => true, 'id' => $newJobId]);
        exit();
    }

    if ($method === 'DELETE') {
        requireAnyRole(['admin','instructor']);
        csrfRequireValid();
        // Soft delete job (set is_active = 0)
        $raw = file_get_contents('php://input');
        parse_str($raw, $payload);
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($id <= 0) {
            throw new Exception('Invalid job id');
        }
        $stmt = $pdo->prepare("UPDATE jobs SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>

