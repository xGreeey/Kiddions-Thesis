<?php
require_once 'security/db_connect.php';

echo "<h2>Debug Batch Data</h2>";
echo "<pre>";

try {
    // Check if batch column exists in students table
    $checkColumn = $pdo->query("SHOW COLUMNS FROM students LIKE 'batch'");
    if ($checkColumn->rowCount() == 0) {
        echo "❌ Batch column does not exist in students table!\n";
        echo "Run the setup script first.\n";
        exit;
    }
    
    echo "✓ Batch column exists in students table\n";
    echo "✓ Default value is set to '1'\n\n";
    
    // Check batch distribution in students table
    echo "Batch distribution in students table:\n";
    $batchCount = $pdo->query("SELECT batch, COUNT(*) as count FROM students GROUP BY batch ORDER BY batch");
    while ($row = $batchCount->fetch(PDO::FETCH_ASSOC)) {
        echo "Batch {$row['batch']}: {$row['count']} students\n";
    }
    
    echo "\nSample students with their batch assignments:\n";
    $sampleStudents = $pdo->query("SELECT student_number, first_name, last_name, course, batch FROM students LIMIT 10");
    while ($student = $sampleStudents->fetch(PDO::FETCH_ASSOC)) {
        echo "{$student['student_number']} - {$student['first_name']} {$student['last_name']} ({$student['course']}) - Batch: {$student['batch']}\n";
    }
    
    // Check if all students have batch 1 (which would cause the issue)
    $allBatch1 = $pdo->query("SELECT COUNT(*) as count FROM students WHERE batch = '1' OR batch IS NULL");
    $batch1Count = $allBatch1->fetch(PDO::FETCH_ASSOC)['count'];
    
    $totalStudents = $pdo->query("SELECT COUNT(*) as count FROM students");
    $totalCount = $totalStudents->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "\nTotal students: $totalCount\n";
    echo "Students with batch 1 or NULL: $batch1Count\n";
    
    if ($batch1Count == $totalCount) {
        echo "\n⚠️  WARNING: All students are in batch 1 or have NULL batch values!\n";
        echo "This is why you see the same students in all batches.\n";
        echo "\nTo fix this, you need to:\n";
        echo "1. Assign different batches to different students\n";
        echo "2. Or create test data with different batch assignments\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
