<?php
session_start();
require_once '../security/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

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

    // Build the query to get graduates from enrollments table
    $query = "
        SELECT 
            e.student_number,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            e.course,
            e.end_date as graduation_date,
            'Completed' as status,
            e.end_date as completion_date
        FROM enrollments e
        LEFT JOIN students s ON e.student_number = s.student_number
        WHERE e.status = 'completed'
    ";

    $params = [];
    $conditions = [];

    // Apply filters
    if (!empty($nameFilter)) {
        $conditions[] = "CONCAT(s.first_name, ' ', s.last_name) LIKE ?";
        $params[] = "%$nameFilter%";
    }

    if (!empty($idFilter)) {
        $conditions[] = "e.student_number LIKE ?";
        $params[] = "%$idFilter%";
    }

    if (!empty($courseFilter)) {
        $conditions[] = "e.course = ?";
        $params[] = $courseFilter;
    }

    if (!empty($monthFilter) || !empty($yearFilter)) {
        if (!empty($monthFilter) && !empty($yearFilter)) {
            $conditions[] = "MONTH(e.end_date) = ? AND YEAR(e.end_date) = ?";
            $params[] = $monthFilter;
            $params[] = $yearFilter;
        } elseif (!empty($monthFilter)) {
            $conditions[] = "MONTH(e.end_date) = ?";
            $params[] = $monthFilter;
        } elseif (!empty($yearFilter)) {
            $conditions[] = "YEAR(e.end_date) = ?";
            $params[] = $yearFilter;
        }
    }

    // Add conditions to query
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    // Add ordering and pagination
    $query .= " ORDER BY e.end_date DESC, e.student_number ASC";
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $graduates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) as total
        FROM enrollments e
        LEFT JOIN students s ON e.student_number = s.student_number
        WHERE e.status = 'completed'
    ";
    
    if (!empty($conditions)) {
        $countQuery .= " AND " . implode(" AND ", $conditions);
    }

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute(array_slice($params, 0, -2)); // Remove limit and offset params
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get available courses for filter dropdown
    $coursesQuery = "SELECT DISTINCT course FROM enrollments WHERE status = 'completed' AND course IS NOT NULL ORDER BY course";
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
    error_log("Graduates Enrollments API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
}
?>
