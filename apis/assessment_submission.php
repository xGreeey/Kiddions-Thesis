    <?php
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    require_once '../security/db_connect.php';

    // Test database connection
    if (!isset($pdo)) {
        error_log("Assessment Submission Error - Database connection not available");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection error']);
        exit();
    }

    // Test database query
    try {
        $testStmt = $pdo->prepare("SELECT COUNT(*) as count FROM students");
        $testStmt->execute();
        $studentCount = $testStmt->fetch(PDO::FETCH_ASSOC)['count'];
        error_log("Assessment Submission Debug - Database connection OK, found $studentCount students");
    } catch (Exception $e) {
        error_log("Assessment Submission Error - Database test failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database test failed']);
        exit();
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Debug logging
        error_log("Assessment Submission Debug - Raw input: " . json_encode($input));
        error_log("Assessment Submission Debug - Request method: " . $_SERVER['REQUEST_METHOD']);
        error_log("Assessment Submission Debug - Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        if (!$input || !isset($input['assessment_id']) || !isset($input['assessment_type']) || !isset($input['answers'])) {
            error_log("Assessment Submission Error - Invalid request data: " . json_encode($input));
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request data']);
            exit();
        }
        
        $assessmentId = (int)$input['assessment_id'];
        $assessmentType = $input['assessment_type'];
        $answers = $input['answers'];
        
        // Use student ID from request or find the first available student
        if (isset($input['student_id'])) {
            $studentId = (int)$input['student_id'];
            error_log("Assessment Submission Debug - Using provided student_id: " . $studentId);
        } else {
            // Try to get student ID from session or student number
            $studentNumber = '';
            if (isset($_SESSION['student_number'])) {
                $studentNumber = $_SESSION['student_number'];
            } elseif (isset($input['student_number'])) {
                $studentNumber = $input['student_number'];
            }
            
            if ($studentNumber) {
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_number = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$studentNumber]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $studentId = (int)$result['id'];
                    error_log("Assessment Submission Debug - Found student ID from student_number '$studentNumber': " . $studentId);
                }
            }
            
            // Fallback to existing logic if still no student ID
            if (!isset($studentId)) {
                // Find the first available student ID, prioritizing those with existing submissions
                $stmt = $pdo->prepare("SELECT DISTINCT student_id FROM quiz_submissions ORDER BY student_id ASC LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $studentId = (int)$result['student_id'];
                    error_log("Assessment Submission Debug - Using student ID from existing quiz submissions: " . $studentId);
                } else {
                    // If no quiz submissions, try exam submissions
                    $stmt = $pdo->prepare("SELECT DISTINCT student_id FROM exam_submissions ORDER BY student_id ASC LIMIT 1");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $studentId = (int)$result['student_id'];
                        error_log("Assessment Submission Debug - Using student ID from existing exam submissions: " . $studentId);
                    } else {
                        // Last resort: get any student ID
                        $stmt = $pdo->prepare("SELECT id FROM students ORDER BY id ASC LIMIT 1");
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $studentId = $result ? (int)$result['id'] : null;
                        error_log("Assessment Submission Debug - Using fallback student ID: " . ($studentId ?? 'null'));
                    }
                }
            }
        }
        
        // Verify the student exists before proceeding
        $verifyStmt = $pdo->prepare("SELECT id, student_number, first_name, last_name FROM students WHERE id = ?");
        $verifyStmt->execute([$studentId]);
        $student = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            if ($studentId === null) {
                error_log("Assessment Submission Error - No students found in database");
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "No students found in database"]);
            } else {
                error_log("Assessment Submission Error - Student ID $studentId does not exist in database");
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Student ID $studentId does not exist"]);
            }
            exit();
        }
        
        error_log("Assessment Submission Debug - Verified student: " . json_encode($student));
        error_log("Assessment Submission Debug - Using student_id: " . $studentId . " for submission");
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            if ($assessmentType === 'quiz') {
                // Check if quiz exists and is published
                $quizStmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE id = ? AND status = 'published' LIMIT 1");
                $quizStmt->execute([$assessmentId]);
                $quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$quiz) {
                    throw new Exception('Quiz not found or not published');
                }
                
                // Check if student already submitted this quiz
                $existingStmt = $pdo->prepare("SELECT id, status FROM quiz_submissions WHERE quiz_id = ? AND student_id = ? LIMIT 1");
                $existingStmt->execute([$assessmentId, $studentId]);
                $existingSubmission = $existingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingSubmission) {
                    if ($existingSubmission['status'] === 'submitted' || $existingSubmission['status'] === 'graded') {
                        throw new Exception('You have already submitted this quiz');
                    } else if ($existingSubmission['status'] === 'in_progress') {
                        // Update existing in_progress submission instead of creating new one
                        $submissionId = (int)$existingSubmission['id'];
                        error_log("Assessment Submission Debug - Updating existing in_progress submission ID: $submissionId");
                    }
                }
                
                // Get questions for this quiz
                $questionsStmt = $pdo->prepare("SELECT id, question_type FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order");
                $questionsStmt->execute([$assessmentId]);
                $questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalQuestions = count($questions);
                $correctAnswers = 0;
                
                // Create submission record only if it doesn't exist
                if (!isset($submissionId)) {
                    $submissionStmt = $pdo->prepare("INSERT INTO quiz_submissions (quiz_id, student_id, total_questions, status) VALUES (?, ?, ?, 'in_progress')");
                    $submissionStmt->execute([$assessmentId, $studentId, $totalQuestions]);
                    $submissionId = $pdo->lastInsertId();
                    error_log("Assessment Submission Debug - Created new submission ID: $submissionId");
                } else {
                    // Update existing submission with new question count if needed
                    $updateStmt = $pdo->prepare("UPDATE quiz_submissions SET total_questions = ?, status = 'in_progress' WHERE id = ?");
                    $updateStmt->execute([$totalQuestions, $submissionId]);
                    
                    // Clear existing answers for this submission
                    $clearAnswersStmt = $pdo->prepare("DELETE FROM quiz_answers WHERE submission_id = ?");
                    $clearAnswersStmt->execute([$submissionId]);
                    
                    error_log("Assessment Submission Debug - Updated existing submission ID: $submissionId with $totalQuestions questions and cleared old answers");
                }
                
                // Process each answer
                foreach ($questions as $question) {
                    $questionId = $question['id'];
                    $questionType = $question['question_type'];
                    $studentAnswer = $answers['question_' . $questionId] ?? null;
                    
                    if ($studentAnswer) {
                        $answerText = '';
                        $selectedOptionId = null;
                        $isCorrect = 0;
                        
                        if ($questionType === 'multiple_choice') {
                            // For multiple choice, student answer is the option ID
                            $selectedOptionId = is_array($studentAnswer) ? $studentAnswer[0] : $studentAnswer;
                            
                            // Check if this is the correct answer
                            $correctStmt = $pdo->prepare("SELECT id FROM quiz_question_options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
                            $correctStmt->execute([$questionId]);
                            $correctOption = $correctStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($correctOption && $selectedOptionId == $correctOption['id']) {
                                $isCorrect = 1;
                                $correctAnswers++;
                            }
                            
                            $answerText = $selectedOptionId;
                        } elseif ($questionType === 'checkbox') {
                            // For checkbox, student answer is array of option IDs
                            $selectedOptions = is_array($studentAnswer) ? $studentAnswer : [$studentAnswer];
                            $answerText = implode(',', $selectedOptions);
                            
                            // For checkbox questions, we'll mark as correct for now (manual grading needed)
                            $isCorrect = 1; // This should be manually graded later
                        } else {
                            // For text answers (short_answer, paragraph)
                            $answerText = is_array($studentAnswer) ? implode(',', $studentAnswer) : $studentAnswer;
                            $isCorrect = 1; // Manual grading needed
                        }
                        
                        // Insert answer record (skip if table doesn't exist or has issues)
                        try {
                            $answerStmt = $pdo->prepare("INSERT INTO quiz_answers (submission_id, question_id, answer_text, selected_option_id, is_correct) VALUES (?, ?, ?, ?, ?)");
                            $answerStmt->execute([$submissionId, $questionId, $answerText, $selectedOptionId, $isCorrect]);
                        } catch (Exception $e) {
                            error_log("Assessment Submission Warning - Could not save answer for question $questionId: " . $e->getMessage());
                        }
                    }
                }
                
                // Calculate score
                $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;
                
                // Update submission with score and final status
                $updateStmt = $pdo->prepare("UPDATE quiz_submissions SET score = ?, correct_answers = ?, status = 'submitted' WHERE id = ?");
                $updateStmt->execute([$score, $correctAnswers, $submissionId]);
                
                // Auto-sync to grade_details table
                syncQuizGradeToGradeDetails($assessmentId, $studentId, $submissionId, $score, $correctAnswers, $totalQuestions);
                
            } elseif ($assessmentType === 'exam') {
                // Check if exam exists and is published
                $examStmt = $pdo->prepare("SELECT id, title FROM exams WHERE id = ? AND status = 'published' LIMIT 1");
                $examStmt->execute([$assessmentId]);
                $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$exam) {
                    throw new Exception('Exam not found or not published');
                }
                
                // Check if student already submitted this exam
                $existingStmt = $pdo->prepare("SELECT id, status FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
                $existingStmt->execute([$assessmentId, $studentId]);
                $existingSubmission = $existingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingSubmission) {
                    if ($existingSubmission['status'] === 'submitted' || $existingSubmission['status'] === 'graded') {
                        throw new Exception('You have already submitted this exam');
                    } else if ($existingSubmission['status'] === 'in_progress') {
                        // Update existing in_progress submission instead of creating new one
                        $submissionId = (int)$existingSubmission['id'];
                        error_log("Assessment Submission Debug - Updating existing in_progress exam submission ID: $submissionId");
                    }
                }
                
                // Get questions for this exam
                $questionsStmt = $pdo->prepare("SELECT id, question_type FROM exam_questions WHERE exam_id = ? ORDER BY question_order");
                $questionsStmt->execute([$assessmentId]);
                $questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalQuestions = count($questions);
                $correctAnswers = 0;
                
                // Create submission record only if it doesn't exist
                if (!isset($submissionId)) {
                    $submissionStmt = $pdo->prepare("INSERT INTO exam_submissions (exam_id, student_id, total_questions, status) VALUES (?, ?, ?, 'in_progress')");
                    $submissionStmt->execute([$assessmentId, $studentId, $totalQuestions]);
                    $submissionId = $pdo->lastInsertId();
                    error_log("Assessment Submission Debug - Created new exam submission ID: $submissionId");
                } else {
                    // Update existing submission with new question count if needed
                    $updateStmt = $pdo->prepare("UPDATE exam_submissions SET total_questions = ?, status = 'in_progress' WHERE id = ?");
                    $updateStmt->execute([$totalQuestions, $submissionId]);
                    
                    // Clear existing answers for this submission
                    $clearAnswersStmt = $pdo->prepare("DELETE FROM exam_answers WHERE submission_id = ?");
                    $clearAnswersStmt->execute([$submissionId]);
                    
                    error_log("Assessment Submission Debug - Updated existing exam submission ID: $submissionId with $totalQuestions questions and cleared old answers");
                }
                
                // Process each answer
                foreach ($questions as $question) {
                    $questionId = $question['id'];
                    $questionType = $question['question_type'];
                    $studentAnswer = $answers['question_' . $questionId] ?? null;
                    
                    if ($studentAnswer) {
                        $answerText = '';
                        $selectedOptionId = null;
                        $isCorrect = 0;
                        
                        if ($questionType === 'multiple_choice') {
                            // For multiple choice, student answer is the option ID
                            $selectedOptionId = is_array($studentAnswer) ? $studentAnswer[0] : $studentAnswer;
                            
                            // Check if this is the correct answer
                            $correctStmt = $pdo->prepare("SELECT id FROM exam_question_options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
                            $correctStmt->execute([$questionId]);
                            $correctOption = $correctStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($correctOption && $selectedOptionId == $correctOption['id']) {
                                $isCorrect = 1;
                                $correctAnswers++;
                            }
                            
                            $answerText = $selectedOptionId;
                        } elseif ($questionType === 'checkbox') {
                            // For checkbox, student answer is array of option IDs
                            $selectedOptions = is_array($studentAnswer) ? $studentAnswer : [$studentAnswer];
                            $answerText = implode(',', $selectedOptions);
                            
                            // For checkbox questions, we'll mark as correct for now (manual grading needed)
                            $isCorrect = 1; // This should be manually graded later
                        } else {
                            // For text answers (short_answer, paragraph)
                            $answerText = is_array($studentAnswer) ? implode(',', $studentAnswer) : $studentAnswer;
                            $isCorrect = 1; // Manual grading needed
                        }
                        
                        // Insert answer record (skip if table doesn't exist or has issues)
                        try {
                            $answerStmt = $pdo->prepare("INSERT INTO exam_answers (submission_id, question_id, answer_text, selected_option_id, is_correct) VALUES (?, ?, ?, ?, ?)");
                            $answerStmt->execute([$submissionId, $questionId, $answerText, $selectedOptionId, $isCorrect]);
                        } catch (Exception $e) {
                            error_log("Assessment Submission Warning - Could not save exam answer for question $questionId: " . $e->getMessage());
                        }
                    }
                }
                
                // Calculate score
                $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;
                
                    // Update submission with score and final status
                $updateStmt = $pdo->prepare("UPDATE exam_submissions SET score = ?, correct_answers = ?, status = 'submitted' WHERE id = ?");
                $updateStmt->execute([$score, $correctAnswers, $submissionId]);
                
                // Auto-sync to grade_details table
                syncExamGradeToGradeDetails($assessmentId, $studentId, $submissionId, $score, $correctAnswers, $totalQuestions);
                
            } else {
                throw new Exception('Invalid assessment type');
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => ucfirst($assessmentType) . ' submitted successfully',
                'score' => $score ?? 0,
                'total_questions' => $totalQuestions ?? 0,
                'correct_answers' => $correctAnswers ?? 0
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    // Function to sync quiz grade to grade_details table
    function syncQuizGradeToGradeDetails($quizId, $studentId, $submissionId, $score, $correctAnswers, $totalQuestions) {
        global $pdo;
        
        try {
            // Get quiz title
            $quizStmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
            $quizStmt->execute([$quizId]);
            $quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);
            $quizTitle = $quiz ? $quiz['title'] : 'Quiz';
            
            // Get student number
            $studentStmt = $pdo->prepare("SELECT student_number FROM students WHERE id = ?");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            $studentNumber = $student ? $student['student_number'] : '';
            
            if (!$studentNumber) {
                error_log("Assessment Submission Error - Student number not found for student ID: $studentId");
                return;
            }
            
            // Check if grade already exists
            $existingStmt = $pdo->prepare("SELECT id FROM grade_details WHERE student_number = ? AND component = ? AND grade_number = 1");
            $existingStmt->execute([$studentNumber, $quizTitle]);
            
            if ($existingStmt->fetch()) {
                // Update existing grade
                $updateStmt = $pdo->prepare("UPDATE grade_details SET raw_score = ?, total_items = ?, transmuted = ?, date_given = NOW() WHERE student_number = ? AND component = ? AND grade_number = 1");
                $updateStmt->execute([$correctAnswers, $totalQuestions, $score, $studentNumber, $quizTitle]);
            } else {
                // Insert new grade
                $insertStmt = $pdo->prepare("INSERT INTO grade_details (student_number, grade_number, component, date_given, raw_score, total_items, transmuted) VALUES (?, 1, ?, NOW(), ?, ?, ?)");
                $insertStmt->execute([$studentNumber, $quizTitle, $correctAnswers, $totalQuestions, $score]);
            }
            
            error_log("Assessment Submission Debug - Synced quiz grade for student $studentNumber: $correctAnswers/$totalQuestions = $score%");
            
        } catch (Exception $e) {
            error_log("Assessment Submission Error - Failed to sync quiz grade: " . $e->getMessage());
        }
    }

    // Function to sync exam grade to grade_details table
    function syncExamGradeToGradeDetails($examId, $studentId, $submissionId, $score, $correctAnswers, $totalQuestions) {
        global $pdo;
        
        try {
            // Get exam title
            $examStmt = $pdo->prepare("SELECT title FROM exams WHERE id = ?");
            $examStmt->execute([$examId]);
            $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
            $examTitle = $exam ? $exam['title'] : 'Exam';
            
            // Get student number
            $studentStmt = $pdo->prepare("SELECT student_number FROM students WHERE id = ?");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            $studentNumber = $student ? $student['student_number'] : '';
            
            if (!$studentNumber) {
                error_log("Assessment Submission Error - Student number not found for student ID: $studentId");
                return;
            }
            
            // Check if grade already exists
            $existingStmt = $pdo->prepare("SELECT id FROM grade_details WHERE student_number = ? AND component = ? AND grade_number = 1");
            $existingStmt->execute([$studentNumber, $examTitle]);
            
            if ($existingStmt->fetch()) {
                // Update existing grade
                $updateStmt = $pdo->prepare("UPDATE grade_details SET raw_score = ?, total_items = ?, transmuted = ?, date_given = NOW() WHERE student_number = ? AND component = ? AND grade_number = 1");
                $updateStmt->execute([$correctAnswers, $totalQuestions, $score, $studentNumber, $examTitle]);
            } else {
                // Insert new grade
                $insertStmt = $pdo->prepare("INSERT INTO grade_details (student_number, grade_number, component, date_given, raw_score, total_items, transmuted) VALUES (?, 1, ?, NOW(), ?, ?, ?)");
                $insertStmt->execute([$studentNumber, $examTitle, $correctAnswers, $totalQuestions, $score]);
            }
            
            error_log("Assessment Submission Debug - Synced exam grade for student $studentNumber: $correctAnswers/$totalQuestions = $score%");
            
        } catch (Exception $e) {
            error_log("Assessment Submission Error - Failed to sync exam grade: " . $e->getMessage());
        }
    }
    ?>
