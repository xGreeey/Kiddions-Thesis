<?php
require_once __DIR__ . '/../../security/db_connect.php';

echo "<h2>Current Batch Distribution</h2>";
echo "<pre>";

try {
    // Check overall batch distribution
    echo "Overall batch distribution:\n";
    $batchCount = $pdo->query("SELECT batch, COUNT(*) as count FROM students GROUP BY batch ORDER BY batch");
    while ($row = $batchCount->fetch(PDO::FETCH_ASSOC)) {
        echo "Batch {$row['batch']}: {$row['count']} students\n";
    }
    
    echo "\nBatch distribution by course:\n";
    $courseBatchCount = $pdo->query("SELECT course, batch, COUNT(*) as count FROM students GROUP BY course, batch ORDER BY course, batch");
    $currentCourse = '';
    while ($row = $courseBatchCount->fetch(PDO::FETCH_ASSOC)) {
        if ($currentCourse !== $row['course']) {
            echo "\nCourse: {$row['course']}\n";
            $currentCourse = $row['course'];
        }
        echo "  Batch {$row['batch']}: {$row['count']} students\n";
    }
    
    echo "\nSample students from each batch:\n";
    for ($batch = 1; $batch <= 4; $batch++) {
        echo "\nBatch $batch students:\n";
        $sampleStudents = $pdo->prepare("SELECT student_number, first_name, last_name, course FROM students WHERE batch = ? LIMIT 3");
        $sampleStudents->execute([$batch]);
        $students = $sampleStudents->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students)) {
            echo "  No students in this batch\n";
        } else {
            foreach ($students as $student) {
                echo "  {$student['student_number']} - {$student['first_name']} {$student['last_name']} ({$student['course']})\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
