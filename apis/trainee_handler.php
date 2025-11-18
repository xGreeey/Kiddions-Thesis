<?php
require_once '../security/session_config.php';
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/auth_functions.php';

error_reporting(E_ALL);
// Unified error display via security/error_handler.php

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Session already started at the top

// Centralized auth: reads require auth; mutations require admin or instructor
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    requireAuth();
} else {
    requireAnyRole(['admin','instructor']);
}
    // Debug session data (can be removed in production)
    // error_log("Trainee Auth Check - Session data: " . json_encode([
    //     'user_role' => $_SESSION['user_role'] ?? 'not_set',
    //     'id' => $_SESSION['id'] ?? 'not_set',
    //     'user_id' => $_SESSION['user_id'] ?? 'not_set',
    //     'student_number' => $_SESSION['student_number'] ?? 'not_set',
    //     'email' => $_SESSION['email'] ?? 'not_set',
    //     'session_id' => session_id()
    // ]));
    
// legacy ad-hoc checks removed in favor of centralized auth above

// CSRF check will be applied to mutating actions only (below)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequireValid();
	$action = $_POST['action'] ?? '';
	
	try {
		switch ($action) {
            case 'add_trainee':
                // CSRF already enforced globally for POST via csrfRequireValid()
				// Get and validate input data
				$role = isset($_POST['role']) ? intval($_POST['role']) : 0; // 0 student, 1 instructor
				$surname = trim($_POST['surname'] ?? '');
				$firstname = trim($_POST['firstname'] ?? '');
				$middlename = trim($_POST['middlename'] ?? '');
				$contactMethod = trim($_POST['contact_method'] ?? '');
				$contact = trim($_POST['contact'] ?? '');
				$email = trim($_POST['email'] ?? '');
				$studentNumber = trim($_POST['student_number'] ?? '');
				$course = trim($_POST['course'] ?? '');
				$batch = trim($_POST['batch'] ?? '1'); // Default to batch 1
				$enrollDate = trim($_POST['enrollDate'] ?? '');
				$notes = trim($_POST['notes'] ?? '');
				
				// Infer contact method if missing
				if ($contactMethod === '') {
					if (!empty($email) && empty($contact)) {
						$contactMethod = 'email';
					} else {
						$contactMethod = 'phone';
					}
				}
				
				// Validation
				if (empty($surname)) {
					throw new Exception('Surname is required');
				}
				if (empty($firstname)) {
					throw new Exception('First name is required');
				}
				if (empty($studentNumber)) {
					throw new Exception('Student number is required');
				}
				if ($role !== 0 && $role !== 1) { $role = 0; }
				if ($contactMethod === 'email') {
					if (empty($email)) {
						throw new Exception('Email is required');
					}
					if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
						throw new Exception('Invalid email format');
					}
				} else {
					if (empty($contact)) {
						throw new Exception('Contact number is required');
					}
				}
				// Course is required for student registrations; optional for instructors
				if ($role === 0 && empty($course)) { throw new Exception('Course is required'); }
				if (empty($enrollDate)) {
					throw new Exception('Enrollment date is required');
				}
				
				// Validate batch selection
				if (!in_array($batch, ['1', '2', '3', '4'])) {
					$batch = '1'; // Default to batch 1 if invalid
				}
				
				// Validate course selection for student only
				if ($role === 0) {
					$valid_courses = ['AUTOMOTIVE SERVICING (ATS)', 'BASIC COMPUTER LITERACY (BCL)', 'BEAUTY CARE (NAIL CARE) (BEC)', 'BREAD AND PASTRY PRODUCTION (BPP)', 'COMPUTER SYSTEMS SERVICING (CSS)', 'DRESSMAKING (DRM)', 'ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)', 'ELECTRONIC PRODUCTS AND ASSEMBLY SERVICING (EPAS)', 'EVENTS MANAGEMENT SERVICES (EVM)', 'FOOD AND BEVERAGE SERVICES (FBS)', 'FOOD PROCESSING (FOP)', 'HAIRDRESSING (HDR)', 'HOUSEKEEPING (HSK)', 'MASSAGE THERAPY (MAT)', 'RAC SERVICING (RAC)', 'SHIELDED METAL ARC WELDING (SMAW)', 'Consultation Manager (CM)', 'Project Manager (PM)', 'Sample Course (SC)'];
					
					// Case-insensitive validation with flexible matching
					$courseFound = false;
					foreach ($valid_courses as $valid_course) {
						// Direct match (case-insensitive)
						if (strcasecmp($course, $valid_course) === 0) {
							$courseFound = true;
							break;
						}
						
						// Flexible match: remove parentheses and compare base names
						$courseBase = preg_replace('/\s*\([^)]*\)\s*/', '', $course);
						$validBase = preg_replace('/\s*\([^)]*\)\s*/', '', $valid_course);
						if (strcasecmp($courseBase, $validBase) === 0) {
							$courseFound = true;
							break;
						}
					}
					
					if (!$courseFound) {
						throw new Exception('Invalid course selection');
					}
				}
				
				// Normalize course name for consistent storage (remove parentheses and convert to title case)
				if ($role === 0 && !empty($course)) {
					$course = preg_replace('/\s*\([^)]*\)\s*/', '', $course); // Remove parentheses and their contents
					$course = ucwords(strtolower($course)); // Convert to title case
					
					// Handle specific typos and variations
					if (strpos($course, 'Electronic Products And Assembly Servicng') !== false) {
						$course = 'Electronic Products And Assembly Servicing';
					}
				}
				
				// Log the data being inserted for debugging
				error_log("Inserting trainee: SN=$studentNumber, Name=$firstname $surname, Course=$course, Method=$contactMethod");
				error_log("Trainee data: " . json_encode([
					'surname' => $surname,
					'firstname' => $firstname,
					'middlename' => $middlename,
					'student_number' => $studentNumber,
					'contact' => $contact,
					'email' => $email,
					'contact_method' => $contactMethod,
					'course' => $course,
					'date_enrolled' => $enrollDate,
					'notes' => $notes
				]));
				
				// Check if student number already exists
				$checkStudentNumber = $pdo->prepare("SELECT id, firstname, surname FROM add_trainees WHERE student_number = ? LIMIT 1");
				$checkStudentNumber->execute([$studentNumber]);
				$existingTrainee = $checkStudentNumber->fetch(PDO::FETCH_ASSOC);
				if ($existingTrainee) {
					throw new Exception('You cannot add this trainee, this ULI already exists.');
				}

				// If using email as contact method, ensure email is unique among trainees
				if ($contactMethod === 'email') {
					$checkStmt = $pdo->prepare("SELECT id FROM add_trainees WHERE email = ? LIMIT 1");
					$checkStmt->execute([$email]);
					if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
						throw new Exception('Email already exists in trainees. Please use a different email.');
					}
				}

				// Insert trainee including student number, email, contact_method, and batch
				$stmt = $pdo->prepare("
					INSERT INTO add_trainees (surname, firstname, middlename, student_number, contact_number, email, contact_method, course, batch, date_enrolled, additional_notes, status, created_at) 
					VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
				");
				
				$contactValue = ($contactMethod === 'email') ? '' : $contact; // keep empty string if NOT NULL
				$emailValue = ($contactMethod === 'email') ? $email : null;
				
				$result = $stmt->execute([
					$surname,
					$firstname,
					$middlename,
					$studentNumber,
					$contactValue,
					$emailValue,
					$contactMethod,
					$course,
					$batch,
					$enrollDate,
					$notes
				]);
				
				if ($result) {
					$traineeId = $pdo->lastInsertId();
					error_log("Trainee inserted successfully with ID: $traineeId");

					// Ensure user exists/updated with chosen role (0 student, 1 instructor)
					try {
						// Update role for existing user, or create placeholder user
						$updateUser = $pdo->prepare("UPDATE mmtvtc_users SET is_role = ?, first_name = COALESCE(NULLIF(first_name,''), ?), last_name = COALESCE(NULLIF(last_name,''), ?), student_number = COALESCE(NULLIF(student_number,''), ?), email = COALESCE(NULLIF(email,''), ?) WHERE email = ? OR student_number = ?");
						$updateUser->execute([$role, $firstname, $surname, $studentNumber, $email, $email, $studentNumber]);
						if ($updateUser->rowCount() === 0) {
							$insUser = $pdo->prepare("INSERT INTO mmtvtc_users (student_number, first_name, middle_name, last_name, email, is_role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
							$insUser->execute([$studentNumber, $firstname, ($middlename ?? ''), $surname, $email, $role]);
						}

						// If student (role 0), also add to students table for attendance system
						if ($role === 0) {
							try {
								// Create students table if it doesn't exist
								$createStudentsTable = $pdo->prepare("CREATE TABLE IF NOT EXISTS students (
									id INT(11) NOT NULL AUTO_INCREMENT,
									student_number VARCHAR(20) NOT NULL,
									first_name VARCHAR(100) NOT NULL,
									last_name VARCHAR(100) NOT NULL,
									middle_name VARCHAR(100) DEFAULT NULL,
									course VARCHAR(100) NOT NULL,
									batch VARCHAR(10) DEFAULT '1',
									created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
									PRIMARY KEY(id),
									UNIQUE KEY unique_student (student_number)
								) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
								$createStudentsTable->execute();
								
								// Insert or update student record
								$insertStudent = $pdo->prepare("INSERT INTO students (student_number, first_name, last_name, middle_name, course, batch) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), middle_name = VALUES(middle_name), course = VALUES(course), batch = VALUES(batch)");
								$insertStudent->execute([$studentNumber, $firstname, $surname, ($middlename ?? ''), $course, $batch]);
								
								error_log("Student synced to students table: $studentNumber");
							} catch (Exception $se) {
								error_log('Failed to sync student to students table: ' . $se->getMessage());
							}
						}

						// If instructor, ensure instructors table record
						if ($role === 1) {
							try {
								// Find user id
								$uidStmt = $pdo->prepare("SELECT id FROM mmtvtc_users WHERE email = ? LIMIT 1");
								$uidStmt->execute([$email]);
								$userRow = $uidStmt->fetch(PDO::FETCH_ASSOC);
								$userId = $userRow ? intval($userRow['id']) : null;
								if ($userId) {
									$createTbl = $pdo->prepare("CREATE TABLE IF NOT EXISTS instructors (
										id INT(11) NOT NULL AUTO_INCREMENT,
										user_id INT(11) NOT NULL,
										instructor_number VARCHAR(20) DEFAULT NULL,
										first_name VARCHAR(50) DEFAULT NULL,
										last_name VARCHAR(50) DEFAULT NULL,
										middle_name VARCHAR(50) DEFAULT NULL,
										email VARCHAR(255) DEFAULT NULL,
										primary_course VARCHAR(100) DEFAULT NULL,
										created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
										updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
										PRIMARY KEY(id), UNIQUE KEY uniq_user (user_id)
									) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
									$createTbl->execute();
									$insInstr = $pdo->prepare("INSERT IGNORE INTO instructors (user_id, instructor_number, first_name, last_name, middle_name, email, primary_course) VALUES (?, ?, ?, ?, ?, ?, ?)");
									$insInstr->execute([$userId, $studentNumber, $firstname, $surname, ($middlename ?? ''), $email, ($course ?: null)]);
								}
							} catch (Throwable $ie) {
								error_log('Ensure instructor record failed: ' . $ie->getMessage());
							}
						}
					} catch (Throwable $ue) {
						error_log('Ensure user/instructor failed: ' . $ue->getMessage());
					}
					
					// If method is email, create password setup flow via reset link
					if ($contactMethod === 'email') {
						try {
							$token = bin2hex(random_bytes(32));
							$expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
							
							// Update existing user or insert minimal user record
							$updateStmt = $pdo->prepare("UPDATE mmtvtc_users SET reset_token = ?, reset_token_expires_at = ? WHERE email = ?");
							$updateStmt->execute([$token, $expiresAt, $email]);
							if ($updateStmt->rowCount() === 0) {
								// Try schema with middle_name; fallback to legacy if not present
								try {
									$insertStmt = $pdo->prepare("INSERT INTO mmtvtc_users (student_number, first_name, middle_name, last_name, email, is_role, created_at, reset_token, reset_token_expires_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?, ?)");
									$insertStmt->execute([$studentNumber, $firstname, ($middlename ?? ''), $surname, $email, $token, $expiresAt]);
								} catch (Throwable $schemaErr) {
									$insertStmt = $pdo->prepare("INSERT INTO mmtvtc_users (student_number, first_name, last_name, email, is_role, created_at, reset_token, reset_token_expires_at) VALUES (?, ?, ?, ?, 0, NOW(), ?, ?)");
									$insertStmt->execute([$studentNumber, $firstname, $surname, $email, $token, $expiresAt]);
								}
							}
							
							$resetUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/nsZLoj1b49kcshf6JhimM3Tvdn1rLK?token=' . urlencode($token) . '&email=' . urlencode($email);
							
							// Send email
							require_once __DIR__ . '/../vendor/autoload.php';
							$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = SMTP_HOST;
                                $mail->SMTPAuth = true;
                                $mail->Username = SMTP_USERNAME;
                                $mail->Password = SMTP_PASSWORD;
                                $mail->SMTPSecure = SMTP_SECURE;
                                $mail->Port = SMTP_PORT;
                                
                                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
								$mail->addAddress($email);
								$mail->isHTML(true);
								$mail->Subject = 'Set up your MMTVTC account password';
								$mail->Body = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>\n  <h2 style='color:#003366;'>Welcome to MMTVTC</h2>\n  <p>Hi " . htmlspecialchars($firstname . ' ' . $surname) . ",</p>\n  <p>Your trainee profile has been created. Click the button below to set your password:</p>\n  <div style='text-align:center; margin:25px 0;'><a href='" . $resetUrl . "' style='background-color:#ffcc00; color:#00366 6; padding:12px 25px; text-decoration:none; border-radius:5px; font-weight:bold;'>Create Password</a></div>\n  <p>If the button doesn't work, use this link:</p>\n  <p><a href='" . $resetUrl . "'>" . $resetUrl . "</a></p>\n  <p>This link will expire in 10 minutes.</p>\n</div>";
								$mail->AltBody = 'Set your password: ' . $resetUrl;
								$mail->send();
							} catch (\PHPMailer\PHPMailer\Exception $me) {
								error_log('Mailer error (trainee add): ' . $me->getMessage());
							}
						} catch (Exception $ie) {
							error_log('Error preparing password setup email: ' . $ie->getMessage());
						}
					}
					
					echo json_encode([
						'success' => true, 
						'message' => 'Trainee added successfully',
						'trainee_id' => $traineeId
					]);
				} else {
					throw new Exception('Failed to insert trainee into database');
				}
				break;
				
			case 'get_trainees':
				// Same pattern as get_announcements
				$stmt = $pdo->prepare("
					SELECT id, surname, firstname, middlename, contact_number, course, batch, date_enrolled, additional_notes, status, created_at,
					       DATEDIFF(CURDATE(), date_enrolled) as days_enrolled
					FROM add_trainees 
					WHERE status IN ('active', 'inactive', 'graduated')
					ORDER BY created_at DESC 
					LIMIT 20
				");
				$stmt->execute();
				$trainees = $stmt->fetchAll(PDO::FETCH_ASSOC);
				
				error_log("Retrieved " . count($trainees) . " trainees from database");
				
				// Debug: Log the actual trainee data
				foreach ($trainees as $trainee) {
					error_log("Trainee: ID=" . $trainee['id'] . ", Name=" . $trainee['firstname'] . " " . $trainee['surname'] . ", Course=" . $trainee['course'] . ", Created=" . $trainee['created_at']);
				}
				
				echo json_encode([
					'success' => true,
					'trainees' => $trainees
				]);
				break;
				
            case 'update_trainee':
                // CSRF already enforced globally for POST via csrfRequireValid()
				$traineeId = intval($_POST['traineeId'] ?? 0);
				$surname = trim($_POST['surname'] ?? '');
				$firstname = trim($_POST['firstname'] ?? '');
				$middlename = trim($_POST['middlename'] ?? '');
				$contact = trim($_POST['contact'] ?? '');
				$course = trim($_POST['course'] ?? '');
				$batch = trim($_POST['batch'] ?? '1'); // Default to batch 1
				$enrollDate = trim($_POST['enrollDate'] ?? '');
				$notes = trim($_POST['notes'] ?? '');
				$status = trim($_POST['status'] ?? 'active');
				
				if ($traineeId <= 0) {
					throw new Exception('Invalid trainee ID');
				}
				
				// Validation
				if (empty($surname) || empty($firstname) || empty($contact) || empty($course) || empty($enrollDate)) {
					throw new Exception('All required fields must be filled');
				}
				
				// Validate course
				$valid_courses = ['AUTOMOTIVE SERVICING (ATS)', 'BASIC COMPUTER LITERACY (BCL)', 'BEAUTY CARE (NAIL CARE) (BEC)', 'BREAD AND PASTRY PRODUCTION (BPP)', 'COMPUTER SYSTEMS SERVICING (CSS)', 'DRESSMAKING (DRM)', 'ELECTRICAL INSTALLATION AND MAINTENANCE (EIM)', 'ELECTRONIC PRODUCTS AND ASSEMBLY SERVICING (EPAS)', 'EVENTS MANAGEMENT SERVICES (EVM)', 'FOOD AND BEVERAGE SERVICES (FBS)', 'FOOD PROCESSING (FOP)', 'HAIRDRESSING (HDR)', 'HOUSEKEEPING (HSK)', 'MASSAGE THERAPY (MAT)', 'RAC SERVICING (RAC)', 'SHIELDED METAL ARC WELDING (SMAW)', 'Consultation Manager (CM)', 'Project Manager (PM)', 'Sample Course (SC)'];
				
				// Case-insensitive validation with flexible matching
				$courseFound = false;
				foreach ($valid_courses as $valid_course) {
					// Direct match (case-insensitive)
					if (strcasecmp($course, $valid_course) === 0) {
						$courseFound = true;
						break;
					}
					
					// Flexible match: remove parentheses and compare base names
					$courseBase = preg_replace('/\s*\([^)]*\)\s*/', '', $course);
					$validBase = preg_replace('/\s*\([^)]*\)\s*/', '', $valid_course);
					if (strcasecmp($courseBase, $validBase) === 0) {
						$courseFound = true;
						break;
					}
				}
				
				if (!$courseFound) {
					throw new Exception('Invalid course selection');
				}
				
				// Normalize course name for consistent storage (remove parentheses and convert to title case)
				if (!empty($course)) {
					$course = preg_replace('/\s*\([^)]*\)\s*/', '', $course); // Remove parentheses and their contents
					$course = ucwords(strtolower($course)); // Convert to title case
					
					// Handle specific typos and variations
					if (strpos($course, 'Electronic Products And Assembly Servicng') !== false) {
						$course = 'Electronic Products And Assembly Servicing';
					}
				}
				
				error_log("Updating trainee ID: $traineeId with name: $firstname $surname");
				
				$stmt = $pdo->prepare("
					UPDATE add_trainees SET 
						surname = ?, firstname = ?, middlename = ?, contact_number = ?, course = ?, batch = ?,
						date_enrolled = ?, additional_notes = ?, status = ?, updated_at = NOW()
					WHERE id = ?
				");
				
				$result = $stmt->execute([$surname, $firstname, $middlename, $contact, $course, $batch, $enrollDate, $notes, $status, $traineeId]);
				
				if ($result) {
					// Also update the students table if this is a student
					try {
						$getTrainee = $pdo->prepare("SELECT student_number FROM add_trainees WHERE id = ?");
						$getTrainee->execute([$traineeId]);
						$traineeData = $getTrainee->fetch(PDO::FETCH_ASSOC);
						
						if ($traineeData) {
							$updateStudent = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ?, middle_name = ?, course = ?, batch = ? WHERE student_number = ?");
							$updateStudent->execute([$firstname, $surname, $middlename, $course, $batch, $traineeData['student_number']]);
							error_log("Student record updated in students table: " . $traineeData['student_number']);
						}
					} catch (Exception $se) {
						error_log('Failed to update student in students table: ' . $se->getMessage());
					}
					
					echo json_encode([
						'success' => true, 
						'message' => 'Trainee updated successfully'
					]);
				} else {
					throw new Exception('Failed to update trainee');
				}
				break;
				
            case 'delete_trainee':
                // CSRF already enforced globally for POST via csrfRequireValid()
				$id = intval($_POST['id'] ?? 0);
				
				if ($id <= 0) {
					throw new Exception('Invalid trainee ID');
				}
				
				error_log("Attempting to delete trainee with ID: $id");
				
				// HARD DELETE - actually remove from database
				$stmt = $pdo->prepare("DELETE FROM add_trainees WHERE id = ?");
				$result = $stmt->execute([$id]);
				
				if ($result && $stmt->rowCount() > 0) {
					error_log("Successfully deleted trainee with ID: $id");
					echo json_encode(['success' => true, 'message' => 'Trainee deleted successfully']);
				} else {
					error_log("Failed to delete trainee with ID: $id - rowCount: " . $stmt->rowCount());
					throw new Exception('Failed to delete trainee or trainee not found');
				}
				break;
				
			default:
				throw new Exception('Invalid action: ' . $action);
		}
		
	} catch (Exception $e) {
		$message = $e->getMessage();
		// Normalize duplicate key errors to friendly message
		if (stripos($message, 'duplicate') !== false && stripos($message, 'email') !== false) {
			$message = 'Email already exists in trainees. Please use a different email.';
		}
		error_log("Trainee handler error: " . $message);
		echo json_encode([
			'success' => false, 
			'message' => $message
		]);
	}
} else {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>