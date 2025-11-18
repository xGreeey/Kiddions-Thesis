<?php
// Manual sync script to sync existing quiz/exam submissions to grade_details
require_once 'security/db_connect.php';

echo "<h2>Sync Quiz/Exam Submissions to Grade Details</h2>";
echo "<pre>";

try {
    // Sync quiz submissions
    echo "1. Syncing quiz submissions...\n";
    $quizStmt = $pdo->prepare("
        SELECT qs.id, qs.quiz_id, qs.student_id, qs.score, qs.correct_answers, qs.total_questions, qs.submitted_at,
               q.title as quiz_title, s.student_number
        FROM quiz_submissions qs
        JOIN quizzes q ON q.id = qs.quiz_id
        JOIN students s ON s.id = qs.student_id
        WHERE qs.status = 'submitted'
        ORDER BY qs.submitted_at DESC
    ");
    $quizStmt->execute();
    $quizSubmissions = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $quizProcessed = 0;
    foreach ($quizSubmissions as $submission) {
        // Check if grade already exists
        $existingStmt = $pdo->prepare("SELECT id FROM grade_details WHERE student_number = ? AND component = ? AND grade_number = 1");
        $existingStmt->execute([$submission['student_number'], $submission['quiz_title']]);
        
        if (!$existingStmt->fetch()) {
            // Insert new grade
            $insertStmt = $pdo->prepare("INSERT INTO grade_details (student_number, grade_number, component, date_given, raw_score, total_items, transmuted) VALUES (?, 1, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $submission['student_number'],
                $submission['quiz_title'],
                $submission['submitted_at'],
                $submission['correct_answers'],
                $submission['total_questions'],
                $submission['score']
            ]);
            $quizProcessed++;
            echo "   Synced quiz: {$submission['student_number']} - {$submission['quiz_title']} ({$submission['correct_answers']}/{$submission['total_questions']} = {$submission['score']}%)\n";
        }
    }
    
    echo "   Processed $quizProcessed new quiz grades\n\n";
    
    // Sync exam submissions
    echo "2. Syncing exam submissions...\n";
    $examStmt = $pdo->prepare("
        SELECT es.id, es.exam_id, es.student_id, es.score, es.correct_answers, es.total_questions, es.submitted_at,
               e.title as exam_title, s.student_number
        FROM exam_submissions es
        JOIN exams e ON e.id = es.exam_id
        JOIN students s ON s.id = es.student_id
        WHERE es.status = 'submitted'
        ORDER BY es.submitted_at DESC
    ");
    $examStmt->execute();
    $examSubmissions = $examStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $examProcessed = 0;
    foreach ($examSubmissions as $submission) {
        // Check if grade already exists
        $existingStmt = $pdo->prepare("SELECT id FROM grade_details WHERE student_number = ? AND component = ? AND grade_number = 1");
        $existingStmt->execute([$submission['student_number'], $submission['exam_title']]);
        
        if (!$existingStmt->fetch()) {
            // Insert new grade
            $insertStmt = $pdo->prepare("INSERT INTO grade_details (student_number, grade_number, component, date_given, raw_score, total_items, transmuted) VALUES (?, 1, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $submission['student_number'],
                $submission['exam_title'],
                $submission['submitted_at'],
                $submission['correct_answers'],
                $submission['total_questions'],
                $submission['score']
            ]);
            $examProcessed++;
            echo "   Synced exam: {$submission['student_number']} - {$submission['exam_title']} ({$submission['correct_answers']}/{$submission['total_questions']} = {$submission['score']}%)\n";
        }
    }
    
    echo "   Processed $examProcessed new exam grades\n\n";
    
    // Show summary
    echo "3. Summary:\n";
    $totalQuizStmt = $pdo->prepare("SELECT COUNT(*) as count FROM quiz_submissions WHERE status = 'submitted'");
    $totalQuizStmt->execute();
    $totalQuizzes = $totalQuizStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $totalExamStmt = $pdo->prepare("SELECT COUNT(*) as count FROM exam_submissions WHERE status = 'submitted'");
    $totalExamStmt->execute();
    $totalExams = $totalExamStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $totalGradeStmt = $pdo->prepare("SELECT COUNT(*) as count FROM grade_details WHERE grade_number = 1");
    $totalGradeStmt->execute();
    $totalGrades = $totalGradeStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "   Total quiz submissions: $totalQuizzes\n";
    echo "   Total exam submissions: $totalExams\n";
    echo "   Total grades in Grade 1: $totalGrades\n";
    echo "   New grades synced: " . ($quizProcessed + $examProcessed) . "\n";
    
    echo "\n✅ Sync completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><strong>Note:</strong> This sync script should be removed after use.</p>";
?>
