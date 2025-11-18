<?php
require_once '../security/session_config.php';
require_once '../security/csp.php';
require_once '../security/db_connect.php';
require_once '../security/cookies.php';
// Ensure or update a row in students table for a given user
if (!function_exists('ensureStudentRecord')) {
    function ensureStudentRecord(PDO $pdo, array $userRow) {
        try {
            $uid = (int)($userRow['id'] ?? 0);
            $studNo = (string)($userRow['student_number'] ?? '');
            $email = (string)($userRow['email'] ?? '');
            $first = (string)($userRow['first_name'] ?? '');
            $last = (string)($userRow['last_name'] ?? '');
            $middle = (string)($userRow['middle_name'] ?? '');

            // If names are missing, try to source from add_trainees
            if ($first === '' || $last === '') {
                try {
                    $q = $pdo->prepare("SELECT firstname, surname FROM add_trainees WHERE student_number = ? OR email = ? ORDER BY created_at DESC LIMIT 1");
                    $q->execute([$studNo, $email]);
                    if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                        if ($first === '' && !empty($row['firstname'])) { $first = $row['firstname']; }
                        if ($last === '' && !empty($row['surname'])) { $last = $row['surname']; }
                    }
                } catch (Throwable $e) { /* ignore name enrichment */ }
            }

            // Course from add_trainees if present
            $course = null;
            try {
                $c = $pdo->prepare("SELECT course FROM add_trainees WHERE student_number = ? OR email = ? ORDER BY created_at DESC LIMIT 1");
                $c->execute([$studNo, $email]);
                if ($cr = $c->fetch(PDO::FETCH_ASSOC)) { $course = $cr['course'] ?? null; }
            } catch (Throwable $e) { /* ignore */ }

            // Use empty strings for NOT NULL columns
            if ($first === null) { $first = ''; }
            if ($last === null) { $last = ''; }
            if ($middle === null) { $middle = ''; }

            // Check if student already exists by user_id, student_number, or email
            $checkStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? OR student_number = ? OR email = ? LIMIT 1");
            $checkStmt->execute([$uid, $studNo, $email]);
            $existingStudent = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingStudent) {
                // Update existing record
                $updateStmt = $pdo->prepare("UPDATE students SET 
                    first_name = ?, 
                    last_name = ?, 
                    middle_name = ?, 
                    email = ?, 
                    course = COALESCE(?, course),
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?");
                $ok = $updateStmt->execute([$first, $last, $middle, $email, $course, $existingStudent['id']]);
            } else {
                // Insert new record (let AUTO_INCREMENT handle the id)
                $insertStmt = $pdo->prepare("INSERT INTO students (user_id, student_number, first_name, last_name, middle_name, email, course, created_at)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $ok = $insertStmt->execute([$uid, $studNo, $first, $last, $middle, $email, $course]);
            }

            // Log minimal info for diagnostics
            @file_put_contents(__DIR__ . '/../logs/security.log', date('c') . ' ENSURE_STUDENT ok=' . ($ok ? '1' : '0') . ' uid=' . $uid . ' sn=' . $studNo . ' email=' . $email . "\n", FILE_APPEND | LOCK_EX);
            return $ok;
        } catch (Throwable $e) {
            @file_put_contents(__DIR__ . '/../logs/security.log', date('c') . ' ENSURE_STUDENT_ERR ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            return false;
        }
    }
}

// For AJAX requests, suppress display errors to avoid corrupting JSON response
if (isset($_POST['ajax_verify'])) {
	ini_set('display_errors', 0);
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
	ini_set('display_errors', 1);
	error_reporting(E_ALL);
}

// Function to get role-based redirect URL
function getRoleBasedRedirect($role) {
	switch ($role) {
		case 0:
			return 'dashboard/student_dashboard.php';
		case 1:
			return 'dashboard/instructors_dashboard.php';
		case 2:
			return 'dashboard/admin_dashboard.php';
		default:
			return 'dashboard/student_dashboard.php';
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_verify'])) {
	// Suppress any output before JSON response
	ob_clean();
	
	$response = array('success' => false, 'message' => '');
	
	// Debug session data
	error_log("DEBUG Session Data: " . json_encode([
		'email' => $_SESSION['email'] ?? 'NOT_SET',
		'user_role' => $_SESSION['user_role'] ?? 'NOT_SET',
		'session_id' => session_id()
	]));

	if (!isset($_SESSION['email']) || !isset($_SESSION['user_role'])) {
		$response['message'] = "Session expired. Please log in again.";
		error_log("DEBUG: Session validation failed");
	} else {
		$email = $_SESSION['email'];
		$user_otp = trim($_POST['otp']); // Trim whitespace
		$user_role = $_SESSION['user_role']; // Get role from session

		try {
			// Using prepared statement with PDO
			$stmt = $pdo->prepare("SELECT otp_code, otp_expires_at, student_number, is_role FROM mmtvtc_users WHERE email = ? LIMIT 1");
			$stmt->execute([$email]);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);

			// Debug logging
			error_log("DEBUG OTP Verification - Email: $email");
			error_log("DEBUG OTP Verification - User OTP: $user_otp");
			error_log("DEBUG OTP Verification - DB OTP: " . ($result['otp_code'] ?? 'NULL'));
			error_log("DEBUG OTP Verification - Expires: " . ($result['otp_expires_at'] ?? 'NULL'));
			error_log("DEBUG OTP Verification - Current time: " . date('Y-m-d H:i:s'));
			error_log("DEBUG OTP Verification - OTP Match: " . ($user_otp === $result['otp_code'] ? 'YES' : 'NO'));
			error_log("DEBUG OTP Verification - OTP Match (loose): " . ($user_otp == $result['otp_code'] ? 'YES' : 'NO'));
			error_log("DEBUG OTP Verification - Not Expired: " . ($result['otp_expires_at'] && strtotime($result['otp_expires_at']) > time() ? 'YES' : 'NO'));

			// More robust comparison - handle both string and integer OTPs
			$otp_matches = ($user_otp === $result['otp_code']) || 
						  ($user_otp === (string)$result['otp_code']) || 
						  ((string)$user_otp === $result['otp_code']);

            if ($result && $result['otp_code'] && $result['otp_expires_at'] && 
				$otp_matches && strtotime($result['otp_expires_at']) > time()) {

				// Clear OTP from database using prepared statement
				$clear_stmt = $pdo->prepare("UPDATE mmtvtc_users SET otp_code = NULL, otp_expires_at = NULL WHERE email = ?");
				$clear_stmt->execute([$email]);

                // Set the correct session variables based on your admin dashboard requirements
				$_SESSION['user_verified'] = true; // This is what admin_dashboard.php checks for
				$_SESSION['authenticated'] = true; // Keep this for backward compatibility
				$_SESSION['email'] = $email;
				$_SESSION['student_number'] = $result['student_number'];
				$_SESSION['is_role'] = $result['is_role']; // Set the correct role field
				$_SESSION['user_role'] = $result['is_role']; // Keep for backward compatibility

				// If role is student (0), ensure a record exists in students table
				try {
					if ((int)$result['is_role'] === 0) {
						// Fetch full user details
						$uStmt = $pdo->prepare("SELECT id, student_number, first_name, last_name, middle_name, email FROM mmtvtc_users WHERE email = ? LIMIT 1");
						$uStmt->execute([$email]);
						$userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

                        if ($userRow) {
                            // Ensure session id is set for consistency
                            $_SESSION['id'] = $userRow['id'];
                            $_SESSION['user_id'] = $userRow['id'];
                            ensureStudentRecord($pdo, $userRow);
						}
					}
				} catch (PDOException $e) {
					error_log('Failed ensuring student record: ' . $e->getMessage());
					// Continue; do not block login on this
				}

				// Log successful verification
				$logDir = '../logs';
				if (!is_dir($logDir)) {
					mkdir($logDir, 0755, true);
				}
				$log = date('Y-m-d H:i:s') . " - OTP_VERIFICATION_SUCCESS - " . 
				      json_encode([
					  'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
					  'email' => $email,
					  'role' => $result['is_role'],
					  'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
				      ]) . "\n";
				file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);

				// Set a durable remember-me cookie (90 days)
				if (function_exists('setRememberMe')) {
					setRememberMe($_SESSION['id'] ?? ($userRow['id'] ?? null), 90);
				}

				$response['success'] = true;
				// Use clean routes compatible with .htaccess rules
				$response['redirect'] = getRoleBasedRedirect($result['is_role']);
			} else {
				$response['message'] = "Invalid or expired OTP.";
				
				// Log failed OTP attempt
				$logDir = '../logs';
				if (!is_dir($logDir)) {
					mkdir($logDir, 0755, true);
				}
				$log = date('Y-m-d H:i:s') . " - OTP_VERIFICATION_FAILED - " . 
				      json_encode([
					  'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
					  'email' => $email,
					  'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
				      ]) . "\n";
				file_put_contents($logDir . '/security.log', $log, FILE_APPEND | LOCK_EX);
			}
		} catch (PDOException $e) {
			$response['message'] = "Database error occurred.";
			error_log("PDO Error in AJAX verify: " . $e->getMessage());
		}
	}
	
	header('Content-Type: application/json');
	echo json_encode($response);
	exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_verify'])) {
	if (!isset($_SESSION['email']) || (!isset($_SESSION['is_role']) && !isset($_SESSION['user_role']))) {
		$_SESSION['otp_error'] = "Session expired. Please log in again.";
		header("Location: EKtJkWrAVAsyyA4fbj1KOrcYulJ2Wu");
		exit();
	}

	$email = $_SESSION['email'];
	$user_otp = $_POST['otp'];
	$user_role = $_SESSION['is_role'] ?? $_SESSION['user_role'] ?? 0;

	try {
		// Using prepared statement with PDO
		$stmt = $pdo->prepare("SELECT otp_code, otp_expires_at, student_number, is_role FROM mmtvtc_users WHERE email = ? LIMIT 1");
		$stmt->execute([$email]);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($result && $result['otp_code'] && $result['otp_expires_at'] && 
			$user_otp === $result['otp_code'] && strtotime($result['otp_expires_at']) > time()) {
			
			// Clear OTP from database using prepared statement
			$clear_stmt = $pdo->prepare("UPDATE mmtvtc_users SET otp_code = NULL, otp_expires_at = NULL WHERE email = ?");
			$clear_stmt->execute([$email]);

			// Set the correct session variables
			$_SESSION['user_verified'] = true; // This is what admin_dashboard.php checks for
			$_SESSION['authenticated'] = true;
			$_SESSION['email'] = $email;
			$_SESSION['student_number'] = $result['student_number'];
			$_SESSION['is_role'] = $result['is_role']; // Set the correct role field
			$_SESSION['user_role'] = $result['is_role']; // Keep for backward compatibility
			
			// Get user ID for complete session data
			$idStmt = $pdo->prepare("SELECT id FROM mmtvtc_users WHERE email = ? LIMIT 1");
			$idStmt->execute([$email]);
			$userData = $idStmt->fetch(PDO::FETCH_ASSOC);
			if ($userData) {
				$_SESSION['id'] = $userData['id'];
				$_SESSION['user_id'] = $userData['id']; // Alternative key for compatibility
			}

            // If role is student (0), ensure a record exists in students table
			try {
				if ((int)$result['is_role'] === 0) {
					// Fetch full user details
					$uStmt = $pdo->prepare("SELECT id, student_number, first_name, last_name, middle_name, email FROM mmtvtc_users WHERE email = ? LIMIT 1");
					$uStmt->execute([$email]);
					$userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

					if ($userRow) {
                        // Ensure session id is set for consistency
                        $_SESSION['id'] = $userRow['id'];
                        $_SESSION['user_id'] = $userRow['id'];
						// Determine course from add_trainees by student_number or email
						$course = null;
						$cStmt = $pdo->prepare("SELECT course FROM add_trainees WHERE student_number = ? OR email = ? ORDER BY created_at DESC LIMIT 1");
						$cStmt->execute([$userRow['student_number'], $userRow['email']]);
						if ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) {
							$course = $cRow['course'];
						}

                        // Check if student already exists and either update or insert
                        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? OR student_number = ? OR email = ? LIMIT 1");
                        $checkStmt->execute([$userRow['id'], $userRow['student_number'], $userRow['email']]);
                        $existingStudent = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingStudent) {
                            // Update existing record
                            $updateStmt = $pdo->prepare("UPDATE students SET 
                                first_name = ?, 
                                last_name = ?, 
                                middle_name = ?, 
                                email = ?, 
                                course = COALESCE(?, course),
                                updated_at = CURRENT_TIMESTAMP 
                                WHERE id = ?");
                            $updateStmt->execute([
                                $userRow['first_name'] ?? '',
                                $userRow['last_name'] ?? '',
                                $userRow['middle_name'] ?? '',
                                $userRow['email'],
                                $course,
                                $existingStudent['id']
                            ]);
                        } else {
                            // Insert new record (let AUTO_INCREMENT handle the id)
                            $insertStmt = $pdo->prepare("INSERT INTO students (user_id, student_number, first_name, last_name, middle_name, email, course, created_at) 
                                                         VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                            $insertStmt->execute([
                                $userRow['id'],
                                $userRow['student_number'],
                                $userRow['first_name'] ?? '',
                                $userRow['last_name'] ?? '',
                                $userRow['middle_name'] ?? '',
                                $userRow['email'],
                                $course
                            ]);
                        }
					}
				}
			} catch (PDOException $e) {
				error_log('Failed ensuring student record (form submit): ' . $e->getMessage());
				// Continue; do not block login on this
			}

			// Set a durable remember-me cookie (90 days)
			if (function_exists('setRememberMe')) {
				setRememberMe($_SESSION['id'] ?? null, 90);
			}

			// Use role-based redirect
			$redirect_url = getRoleBasedRedirect($result['is_role']);
			header("Location: " . $redirect_url);
			exit();
		} else {
			$_SESSION['otp_error'] = "Invalid or expired OTP.";
			header("Location: otp");
			exit();
		}
	} catch (PDOException $e) {
		$_SESSION['otp_error'] = "Database error occurred.";
		error_log("PDO Error in form submit: " . $e->getMessage());
		header("Location: otp");
		exit();
	}
}

// Get OTP expiry time for the timer
$otp_expires_at = null;
if (isset($_SESSION['email'])) {
	$email = $_SESSION['email'];
	try {
		// Using prepared statement with PDO
		$stmt = $pdo->prepare("SELECT otp_expires_at FROM mmtvtc_users WHERE email = ? LIMIT 1");
		$stmt->execute([$email]);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$otp_expires_at = $result ? $result['otp_expires_at'] : null;
	} catch (PDOException $e) {
		error_log("PDO Error in getting OTP expiry: " . $e->getMessage());
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title>Email OTP Verification</title>
	<link rel="icon" href="assets/mmtvtc.png" type="image/png">
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
	<style>
		* {
			box-sizing: border-box;
			-webkit-tap-highlight-color: transparent;
		}

		html, body {
			touch-action: manipulation;
			height: 100%;
			width: 100%;
			margin: 0;
			padding: 0;
		}

		body {
			color: white;
			font-family: 'Poppins', sans-serif;
			position: relative;
			min-height: 100vh;
			background:
				linear-gradient(120deg, rgba(8, 12, 22, .92), rgba(10, 15, 28, .92)),
				url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=2000&q=60') center/cover no-repeat fixed;
		}

		/* Glassmorphic Header to match login/index */
		header.navbar { position: fixed; top: 0; left:0; right:0; z-index: 1000; display:flex; align-items:center; justify-content:space-between; padding:14px 28px; margin:14px auto; max-width:1200px; background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.18); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-radius:16px; box-shadow: 0 10px 40px rgba(0,0,0,.35);} 
		.brand { display:flex; align-items:center; gap:10px; font-weight:700; letter-spacing:.2px; }
		.brand img { width:34px; height:34px; object-fit:cover; border-radius:8px; }
		.brand span { color:#ffcc00; }
		nav a { color:#e8ecf3; text-decoration:none; margin-left:22px; font-weight:500; opacity:.9; transition: opacity .2s ease, transform .2s ease; }
		nav a:hover { opacity:1; transform: translateY(-1px); }

		/* Ambient visuals */
		.mosaic { position: fixed; inset: 0; z-index: 0; opacity: .18; filter: saturate(85%) contrast(95%); display: grid; grid-template-columns: repeat(6, 1fr); grid-auto-rows: 18vh; gap: 6px; padding: 120px 6px 6px; pointer-events: none; }
		.mosaic div { border-radius: 10px; background-position: center; background-size: cover; }
		.mosaic div:nth-child(1) { background-image: url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
		.mosaic div:nth-child(2) { background-image: url('https://images.unsplash.com/photo-1591453089816-9a46f7aee49f?auto=format&fit=crop&w=900&q=60'); }
		.mosaic div:nth-child(3) { background-image: url('https://images.unsplash.com/photo-1580281657527-47dfa27d0f5a?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
		.mosaic div:nth-child(4) { background-image: url('https://images.unsplash.com/photo-1507537297725-24a1c029d3ca?auto=format&fit=crop&w=900&q=60'); }
		.mosaic div:nth-child(5) { background-image: url('https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=900&q=60'); grid-column: span 3; }
		.mosaic div:nth-child(6) { background-image: url('https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
		.mosaic div:nth-child(7) { background-image: url('https://images.unsplash.com/photo-1541976076758-347942db1970?auto=format&fit=crop&w=900&q=60'); }
		.mosaic div:nth-child(8) { background-image: url('https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=900&q=60'); grid-column: span 2; }
		.mosaic div:nth-child(9) { background-image: url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&w=900&q=60'); }

		.particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
		.particles span { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,.35); filter: blur(1px); animation: float 10s linear infinite; }
		.particles span:nth-child(2) { left: 10%; top: 30%; animation-duration: 12s; }
		.particles span:nth-child(3) { left: 75%; top: 20%; animation-duration: 11s; }
		.particles span:nth-child(4) { left: 60%; top: 70%; animation-duration: 13s; }
		.particles span:nth-child(5) { left: 25%; top: 65%; animation-duration: 9s; }
		@keyframes float { 0% { transform: translateY(0); opacity: .6; } 50% { transform: translateY(-20px); opacity: .9; } 100% { transform: translateY(0); opacity: .6; } }

		.overlay {
			position: absolute;
			z-index: 1;
			height: 100%;
			width: 100%;
			display: flex;
			justify-content: center;
			align-items: center;
			background: linear-gradient(to right, rgba(0,0,0,0.45), rgba(0,0,0,0.65));
		}

		.otp-container {
			background: rgba(255, 255, 255, 0.08);
			border: 1px solid rgba(255, 255, 255, 0.2);
			border-radius: 20px;
			backdrop-filter: blur(20px);
			-webkit-backdrop-filter: blur(20px);
			box-shadow: 
				0 8px 32px rgba(0, 0, 0, 0.3),
				0 0 0 1px rgba(255, 255, 255, 0.1),
				inset 0 1px 0 rgba(255, 255, 255, 0.2);
			padding: 40px 30px;
			width: 380px;
			max-width: 90%;
			position: relative;
			overflow: hidden;
		}

		.otp-container::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: linear-gradient(
				135deg,
				rgba(255, 255, 255, 0.1) 0%,
				rgba(255, 255, 255, 0.05) 50%,
				rgba(255, 255, 255, 0.02) 100%
			);
			pointer-events: none;
			z-index: -1;
		}

		.logo {
			display: block;
			margin: 0 auto 15px;
			width: 100px;
		}

		h2 {
			text-align: center;
			color: #ffcc00;
			font-weight: 600;
			margin-bottom: 25px;
			text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
		}

		.otp-inputs {
			display: flex;
			justify-content: space-between;
			gap: 8px;
			margin: 20px 0 30px;
			flex-wrap: wrap;
		}

		.otp-input {
			width: 45px;
			height: 50px;
			border: 2px solid rgba(255, 255, 255, 0.2);
			border-radius: 8px;
			background: rgba(255, 255, 255, 0.1);
			backdrop-filter: blur(10px);
			-webkit-backdrop-filter: blur(10px);
			outline: none;
			font-size: 20px;
			font-weight: 600;
			color: #fff;
			text-align: center;
			transition: all 0.3s ease;
			-webkit-appearance: none;
			-moz-appearance: none;
			appearance: none;
		}

		.otp-input:focus {
			border-color: #ffcc00;
			background: rgba(255, 255, 255, 0.15);
			box-shadow: 
				0 0 0 3px rgba(255, 204, 0, 0.2),
				0 0 20px rgba(255, 204, 0, 0.1);
			transform: scale(1.05);
		}

		.otp-input.filled {
			border-color: #00cc66;
			background: rgba(0, 204, 102, 0.1);
			box-shadow: 
				0 0 0 2px rgba(0, 204, 102, 0.2),
				0 0 15px rgba(0, 204, 102, 0.1);
		}

		.otp-input.error {
			border-color: #ff6b6b;
			background: rgba(255, 107, 107, 0.1);
			animation: shake 0.6s cubic-bezier(.36,.07,.19,.97) both;
		}

		.otp-input::-webkit-inner-spin-button,
		.otp-input::-webkit-outer-spin-button {
			-webkit-appearance: none;
			appearance: none;
			margin: 0;
		}

		.verifying {
			display: none;
			text-align: center;
			color: #ffcc00;
			font-size: 14px;
			margin: 15px 0;
			align-items: center;
			justify-content: center;
			gap: 10px;
			text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
		}

		.verifying.visible {
			display: flex;
		}

		.loader {
			width: 20px;
			height: 20px;
			border: 2px solid rgba(255, 204, 0, 0.3);
			border-radius: 50%;
			border-top-color: #ffcc00;
			animation: spin 1s linear infinite;
		}

		@keyframes spin {
			to { transform: rotate(360deg); }
		}

		.error {
			color: #ff6b6b;
			text-align: center;
			font-size: 14px;
			margin-bottom: 10px;
			opacity: 0;
			transition: opacity 0.3s ease;
			text-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
		}

		.error.visible {
			opacity: 1;
		}
		
		.resend-container {
			display: flex;
			flex-direction: column;
			align-items: center;
			margin-top: 5px;
		}
		
		.resend-link {
			color: #ffcc00;
			text-decoration: none;
			font-size: 14px;
			transition: all 0.3s ease;
			cursor: pointer;
			text-align: center;
			margin-bottom: 5px;
			padding: 8px 0;
			text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
		}
		
		.resend-link:hover {
			color: #fff;
			text-decoration: underline;
			text-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
		}
		
		.resend-link.disabled {
			pointer-events: none;
			opacity: 0.6;
		}
		
		.timer {
			color: #ffcc00;
			font-size: 14px;
			font-weight: 500;
			display: none;
			text-align: center;
			text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
		}
		
		.timer.visible {
			display: inline-block;
		}
		
		.timer.expiring {
			color: #ff6b6b;
			animation: pulse 1s infinite;
			text-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
		}
		
		@keyframes pulse {
			0% { opacity: 1; }
			50% { opacity: 0.5; }
			100% { opacity: 1; }
		}
		
		.success-message {
			background: linear-gradient(135deg, rgba(0, 204, 102, 0.2) 0%, rgba(0, 153, 68, 0.2) 100%);
			backdrop-filter: blur(10px);
			-webkit-backdrop-filter: blur(10px);
			border: 1px solid rgba(0, 204, 102, 0.3);
			color: #00ff88;
			text-align: center;
			padding: 10px;
			border-radius: 8px;
			margin: 10px 0;
			font-size: 14px;
			display: none;
			animation: fadeInUp 0.4s ease-out;
			text-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
		}
		
		@keyframes fadeInUp {
			from {
				opacity: 0;
				transform: translateY(10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		@keyframes shake {
			0%, 100% { transform: translateX(0); }
			10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
			20%, 40%, 60%, 80% { transform: translateX(10px); }
		}

		.otp-container.loading .otp-input {
			pointer-events: none;
			opacity: 0.7;
		}

		@media (max-width: 400px) {
			.otp-container {
				width: 90%;
				padding: 30px 20px;
			}
			
			.logo {
				width: 80px;
			}
			
			h2 {
				font-size: 20px;
			}
			
			.otp-inputs {
				gap: 6px;
			}
			
			.otp-input {
				width: 40px;
				height: 45px;
				font-size: 18px;
			}
		}
		
		@supports (-webkit-touch-callout: none) {
			.otp-input {
				border-radius: 8px !important;
			}
		}
	</style>
</head>
<body>
	<!-- Ambient visuals -->
	<div class="mosaic" aria-hidden="true">
		<div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div>
	</div>
	<div class="particles" aria-hidden="true">
		<span></span><span></span><span></span><span></span><span></span>
	</div>

	<header class="navbar">
		<div class="brand">
			<img src="../images/manpower logo.jpg" alt="MMTVTC logo" />
			<div>
				<div style="font-size:14px; line-height:1; color:#cfd7e6;"></div>
				<span>MMTVTC</span>
			</div>
		</div>
		<nav>
			<a href="../home?">Home</a>
		</nav>
	</header>

	<div class="overlay">
		<div class="otp-container">
			<img src="assets/mmtvtc.png" alt="MMTVTC Logo" class="logo">
			<h2>Email Verification</h2>
			
			<div class="otp-inputs">
				<input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="0">
				<input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="1">
				<input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="2">
				<input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="3">
				<input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="4">
				<input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="5">
			</div>
			
			<div class="verifying">
				<div class="loader"></div>
				<span>Verifying...</span>
			</div>
			
			<div class="error" id="error-message"></div>
			<div class="success-message" id="success-message">OTP verified successfully!</div>
			
			<div class="resend-container">
				<a href="#" class="resend-link" id="resend-link">Resend verification code</a>
				<div class="timer" id="timer">Resend in <span id="countdown">30</span>s</div>
			</div>
		</div>
	</div>

	<script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
		// Prevent zooming on double tap in iOS
		document.addEventListener('touchmove', function(e) {
			if (e.scale !== 1) {
				e.preventDefault();
			}
		}, { passive: false });
		
		// Disable zooming
		document.addEventListener('gesturestart', function(e) {
			e.preventDefault();
		}, { passive: false });
		
		class OTPVerification {
			constructor() {
				this.inputs = document.querySelectorAll('.otp-input');
				this.resendLink = document.getElementById('resend-link');
				this.timer = document.getElementById('timer');
				this.countdown = document.getElementById('countdown');
				this.errorMessage = document.getElementById('error-message');
				this.successMessage = document.getElementById('success-message');
				this.verifying = document.querySelector('.verifying');
				this.container = document.querySelector('.otp-container');
				
				this.resendTimer = null;
				this.resendTimeLeft = 30;
				this.timerInterval = null;
				this.expiryTime = null;
				
				this.init();
			}
			
			init() {
				this.setupInputHandlers();
				this.setupResendHandler();
				this.startResendTimer();
				
				// Focus handling for iOS devices to prevent zooming
				this.inputs.forEach(input => {
					input.addEventListener('focus', function() {
						// Some browsers zoom on input focus, this can help prevent it
						document.body.style.fontSize = '16px';
					});
				});
				
				// Focus first input
				this.inputs[0].focus();
			}
			
			setupInputHandlers() {
				this.inputs.forEach((input, index) => {
					input.addEventListener('input', (e) => this.handleInput(e, index));
					input.addEventListener('keydown', (e) => this.handleKeydown(e, index));
					input.addEventListener('paste', (e) => this.handlePaste(e, index));
					input.addEventListener('focus', () => this.clearErrors());
				});
			}
			
			handleInput(e, index) {
				const value = e.target.value;
				
				// Only allow numbers
				if (!/^\d$/.test(value)) {
					e.target.value = '';
					return;
				}
				
				// Add filled class
				e.target.classList.add('filled');
				
				// Move to next input
				if (index < this.inputs.length - 1) {
					this.inputs[index + 1].focus();
				}
				
				// Check if all inputs are filled
				this.checkComplete();
			}
			
			handleKeydown(e, index) {
				// Handle backspace
				if (e.key === 'Backspace') {
					if (e.target.value === '' && index > 0) {
						this.inputs[index - 1].focus();
						this.inputs[index - 1].value = '';
						this.inputs[index - 1].classList.remove('filled');
					} else {
						e.target.value = '';
						e.target.classList.remove('filled');
					}
				}
				
				// Handle arrow keys
				if (e.key === 'ArrowLeft' && index > 0) {
					this.inputs[index - 1].focus();
				}
				if (e.key === 'ArrowRight' && index < this.inputs.length - 1) {
					this.inputs[index + 1].focus();
				}
			}
			
			handlePaste(e, index) {
				e.preventDefault();
				const paste = e.clipboardData.getData('text').replace(/\D/g, '');
				
				if (paste.length === 6) {
					this.inputs.forEach((input, i) => {
						if (i < paste.length) {
							input.value = paste[i];
							input.classList.add('filled');
						}
					});
					this.checkComplete();
				}
			}
			
			checkComplete() {
				const otp = this.getOTP();
				if (otp.length === 6) {
					this.verifyOTP(otp);
				}
			}
			
			getOTP() {
				return Array.from(this.inputs).map(input => input.value).join('');
			}
			
			async verifyOTP(otp) {
				this.showVerifying();
				
				// Debug logging
				console.log('Verifying OTP:', otp);
				console.log('OTP length:', otp.length);
				console.log('OTP type:', typeof otp);
				
				try {
					// Create form data for the actual verification
					const formData = new FormData();
					formData.append('otp', otp);
					formData.append('ajax_verify', 'true');
					
					// Make the actual API call
					const response = await fetch('otp', {
						method: 'POST',
						body: formData
					});
					
					console.log('Response received:', response.status);
					const responseText = await response.text();
					console.log('Raw response:', responseText);
					
					const data = JSON.parse(responseText);
					console.log('Parsed data:', data);
					
					if (data.success) {
						this.showSuccess();
						setTimeout(() => {
							window.location.href = data.redirect;
						}, 1000);
					} else {
						this.showError(data.message || 'Invalid OTP. Please try again.');
					}
				} catch (error) {
					this.showError('Verification failed. Please try again.');
					console.error('Error:', error);
				}
			}
			
			showVerifying() {
				this.container.classList.add('loading');
				this.verifying.classList.add('visible');
				this.clearErrors();
			}
			
			hideVerifying() {
				this.container.classList.remove('loading');
				this.verifying.classList.remove('visible');
			}
			
			showSuccess() {
				this.hideVerifying();
				this.successMessage.style.display = 'block';
			}
			
			showError(message) {
				this.hideVerifying();
				this.errorMessage.textContent = message;
				this.errorMessage.classList.add('visible');
				
				// Add error animation to inputs
				this.inputs.forEach(input => {
					input.classList.add('error');
					input.value = '';
					input.classList.remove('filled');
				});
				
				// Add shake animation to container
				this.container.classList.add('shake');
				setTimeout(() => {
					this.container.classList.remove('shake');
				}, 600);
				
				// Remove error class after animation
				setTimeout(() => {
					this.inputs.forEach(input => {
						input.classList.remove('error');
					});
				}, 600);
				
				// Focus first input
				this.inputs[0].focus();
			}
			
			clearErrors() {
				this.errorMessage.classList.remove('visible');
				this.successMessage.style.display = 'none';
			}
			
			setupResendHandler() {
				this.resendLink.addEventListener('click', (e) => {
					e.preventDefault();
					this.resendOTP();
				});
			}
			
			async resendOTP() {
				if (this.resendLink.classList.contains('disabled')) return;
				
				this.clearErrors();
				this.resendLink.classList.add('disabled');
				
				try {
					const formData = new FormData();
					formData.append('ajax_resend', 'true');
					
					const response = await fetch('resend_otp.php', {
						method: 'POST',
						body: formData
					});
					
					const data = await response.json();
					
					if (data.success) {
						// Clear inputs
						this.inputs.forEach(input => {
							input.value = '';
							input.classList.remove('filled');
						});
						
						this.inputs[0].focus();
						
						// Show success message
						this.successMessage.textContent = 'New OTP sent successfully!';
						this.successMessage.style.display = 'block';
						
						// Start the timer (5 minutes)
						this.startOTPTimer();
						
						// Auto-hide success message after 5 seconds
						setTimeout(() => {
							this.successMessage.style.display = 'none';
							this.successMessage.textContent = 'OTP verified successfully!';
						}, 5000);
					} else {
						this.errorMessage.textContent = data.message || 'Failed to resend OTP. Please try again.';
						this.errorMessage.classList.add('visible');
						this.resendLink.classList.remove('disabled');
					}
				} catch (error) {
					this.errorMessage.textContent = 'An error occurred. Please try again.';
					this.errorMessage.classList.add('visible');
					this.resendLink.classList.remove('disabled');
					console.error('Error:', error);
				}
			}
			
			startOTPTimer() {
				// Clear any existing timer
				if (this.timerInterval) {
					clearInterval(this.timerInterval);
				}
				
				// Set new expiry time (5 minutes from now)
				this.expiryTime = new Date().getTime() + (5 * 60 * 1000);
				
				// Show the timer
				this.timer.classList.add('visible');
				this.timer.classList.remove('expiring');
				this.resendLink.style.display = 'none';
				
				// Initial update
				this.updateOTPTimer();
				
				// Start interval for updates
				this.timerInterval = setInterval(() => this.updateOTPTimer(), 1000);
			}
			
			updateOTPTimer() {
				const currentTime = new Date().getTime();
				const timeLeft = this.expiryTime - currentTime;
				
				if (timeLeft <= 0) {
					clearInterval(this.timerInterval);
					this.timer.textContent = "OTP expired";
					this.timer.classList.add('expiring');
					this.timer.classList.remove('visible');
					
					// Enable resend link when timer expires
					this.resendLink.classList.remove('disabled');
					this.resendLink.style.display = 'block';
					return;
				}
				
				const minutes = Math.floor(timeLeft / (1000 * 60));
				const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
				
				// Format the timer display
				this.timer.textContent = `OTP expires in ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
				
				// Add blinking effect when less than 1 minute remaining
				if (timeLeft < 60000) {
					this.timer.classList.add('expiring');
				} else {
					this.timer.classList.remove('expiring');
				}
			}
			
			startResendTimer() {
				this.resendTimeLeft = 30;
				this.resendLink.classList.add('disabled');
				this.timer.classList.add('visible');
				this.resendLink.style.display = 'none';
				
				this.resendTimer = setInterval(() => {
					this.resendTimeLeft--;
					this.countdown.textContent = this.resendTimeLeft;
					
					if (this.resendTimeLeft <= 10) {
						this.timer.classList.add('expiring');
					}
					
					if (this.resendTimeLeft <= 0) {
						clearInterval(this.resendTimer);
						this.resendLink.classList.remove('disabled');
						this.timer.classList.remove('visible', 'expiring');
						this.resendLink.style.display = 'block';
					}
				}, 1000);
			}
		}
		
		// Initialize when DOM is loaded
		document.addEventListener('DOMContentLoaded', () => {
			new OTPVerification();
		});
	</script>
</body>
</html>