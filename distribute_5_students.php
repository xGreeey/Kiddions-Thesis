<?php
require_once 'security/db_connect.php';

echo "<h2>Distribute 5 Students Across 4 Batches</h2>";
echo "<pre>";

try {
    // Get all students
    $students = $pdo->query("SELECT id, student_number, first_name, last_name, course FROM students ORDER BY id");
    $studentList = $students->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($studentList) . " students to distribute\n\n";
    
    // Assign batches to the 5 students
    $batchAssignments = [
        1 => '1', // First student → Batch 1
        2 => '2', // Second student → Batch 2  
        3 => '3', // Third student → Batch 3
        4 => '4', // Fourth student → Batch 4
        5 => '1'  // Fifth student → Batch 1 (back to start)
    ];
    
    echo "Assigning batches:\n";
    foreach ($studentList as $index => $student) {
        $batch = $batchAssignments[$index + 1] ?? '1';
        
        // Update the student's batch
        $updateStmt = $pdo->prepare("UPDATE students SET batch = ? WHERE id = ?");
        $updateStmt->execute([$batch, $student['id']]);
        
        echo "Student {$student['student_number']} ({$student['first_name']} {$student['last_name']}) → Batch $batch\n";
    }
    
    echo "\n✅ Batch assignments completed!\n\n";
    
    // Show the new distribution
    echo "New batch distribution:\n";
    $batchCount = $pdo->query("SELECT batch, COUNT(*) as count FROM students GROUP BY batch ORDER BY batch");
    while ($row = $batchCount->fetch(PDO::FETCH_ASSOC)) {
        echo "Batch {$row['batch']}: {$row['count']} students\n";
    }
    
    echo "\nStudents in each batch:\n";
    for ($batch = 1; $batch <= 4; $batch++) {
        echo "\nBatch $batch:\n";
        $batchStudents = $pdo->prepare("SELECT student_number, first_name, last_name, course FROM students WHERE batch = ?");
        $batchStudents->execute([$batch]);
        $students = $batchStudents->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students)) {
            echo "  No students\n";
        } else {
            foreach ($students as $student) {
                echo "  {$student['student_number']} - {$student['first_name']} {$student['last_name']} ({$student['course']})\n";
            }
        }
    }
    
    echo "\n🎯 Now test the instructor dashboard:\n";
    echo "1. Go to instructor dashboard\n";
    echo "2. Select a course (RAC Servicing)\n";
    echo "3. Switch between Batch 1, 2, 3, 4\n";
    echo "4. You should see different students in each batch!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
