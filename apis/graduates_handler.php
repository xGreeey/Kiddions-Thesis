<?php
session_start();
require_once '../security/db_connect.php';
require_once '../security/csrf.php';
require_once '../security/request_guard.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in', 'debug' => ['session' => $_SESSION]]);
    exit;
}

// Allow admin or instructor access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'instructor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions', 'debug' => [
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'] ?? 'not_set'
    ]]);
    exit;
}

// Validate CSRF token (only for POST requests, GET requests are safe)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    // Get filter parameters
    $nameFilter = $_GET['name'] ?? '';
    $idFilter = $_GET['id'] ?? '';
    $courseFilter = $_GET['course'] ?? '';
    $monthFilter = $_GET['month'] ?? '';
    $yearFilter = $_GET['year'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;

    // Build the query to get graduates (students who have completed courses)
    $query = "
        SELECT DISTINCT
            s.student_number,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            s.course,
            s.created_at as enrollment_date,
            COALESCE(cg.final_grade, 0) as final_grade,
            CASE 
                WHEN cg.final_grade >= 75 THEN 'Completed'
                ELSE 'In Progress'
            END as status,
            cg.updated_at as completion_date
        FROM students s
        LEFT JOIN computed_grades cg ON s.id = cg.student_id
        WHERE s.course IS NOT NULL
    ";

    $params = [];
    $conditions = [];

    // Apply filters
    if (!empty($nameFilter)) {
        $conditions[] = "CONCAT(s.first_name, ' ', s.last_name) LIKE ?";
        $params[] = "%$nameFilter%";
    }

    if (!empty($idFilter)) {
        $conditions[] = "s.student_number LIKE ?";
        $params[] = "%$idFilter%";
    }

    if (!empty($courseFilter)) {
        $conditions[] = "s.course = ?";
        $params[] = $courseFilter;
    }

    if (!empty($monthFilter) || !empty($yearFilter)) {
        if (!empty($monthFilter) && !empty($yearFilter)) {
            $conditions[] = "MONTH(cg.updated_at) = ? AND YEAR(cg.updated_at) = ?";
            $params[] = $monthFilter;
            $params[] = $yearFilter;
        } elseif (!empty($monthFilter)) {
            $conditions[] = "MONTH(cg.updated_at) = ?";
            $params[] = $monthFilter;
        } elseif (!empty($yearFilter)) {
            $conditions[] = "YEAR(cg.updated_at) = ?";
            $params[] = $yearFilter;
        }
    }

    // Add conditions to query
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    // Add ordering and pagination
    $query .= " ORDER BY cg.updated_at DESC, s.student_number ASC";
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $graduates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(DISTINCT s.id) as total
        FROM students s
        LEFT JOIN computed_grades cg ON s.id = cg.student_id
        WHERE s.course IS NOT NULL
    ";
    
    if (!empty($conditions)) {
        $countQuery .= " AND " . implode(" AND ", $conditions);
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute(array_slice($params, 0, -2)); // Remove limit and offset params
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get available courses for filter dropdown
    $coursesQuery = "SELECT DISTINCT course FROM students WHERE course IS NOT NULL ORDER BY course";
    $coursesStmt = $pdo->prepare($coursesQuery);
    $coursesStmt->execute();
    $courses = $coursesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Format the response
    $response = [
        'success' => true,
        'data' => $graduates,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ],
        'filters' => [
            'courses' => $courses
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Graduates API Error: " . $e->getMessage());
    error_log("Graduates API Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
