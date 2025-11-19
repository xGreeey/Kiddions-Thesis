<?php
require_once __DIR__ . '/../../security/db_connect.php';

echo "<h2>Assign Test Batches to Students</h2>";
echo "<pre>";

try {
    // Get all students
    $students = $pdo->query("SELECT id, student_number, first_name, last_name, course, batch FROM students ORDER BY id");
    $studentList = $students->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($studentList) . " students\n\n";
    
    // Group students by course first
    $studentsByCourse = [];
    foreach ($studentList as $student) {
        $course = $student['course'] ?: 'Unknown';
        if (!isset($studentsByCourse[$course])) {
            $studentsByCourse[$course] = [];
        }
        $studentsByCourse[$course][] = $student;
    }
    
    echo "Students grouped by course:\n";
    foreach ($studentsByCourse as $course => $students) {
        echo "- $course: " . count($students) . " students\n";
    }
    echo "\n";
    
    // Assign batches within each course
    $batches = ['1', '2', '3', '4'];
    
    foreach ($studentsByCourse as $course => $courseStudents) {
        echo "Assigning batches for course: $course\n";
        $batchIndex = 0;
        
        foreach ($courseStudents as $student) {
            $batch = $batches[$batchIndex % 4];
            $batchIndex++;
            
            // Update the student's batch
            $updateStmt = $pdo->prepare("UPDATE students SET batch = ? WHERE id = ?");
            $updateStmt->execute([$batch, $student['id']]);
            
            echo "  {$student['student_number']} ({$student['first_name']} {$student['last_name']}) → Batch $batch\n";
        }
        echo "\n";
    }
    
    echo "\n✅ Batch assignments completed!\n\n";
    
    // Show the new distribution
    echo "New batch distribution:\n";
    $batchCount = $pdo->query("SELECT batch, COUNT(*) as count FROM students GROUP BY batch ORDER BY batch");
    while ($row = $batchCount->fetch(PDO::FETCH_ASSOC)) {
        echo "Batch {$row['batch']}: {$row['count']} students\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
