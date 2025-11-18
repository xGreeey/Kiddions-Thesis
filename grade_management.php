<?php
session_start();
error_reporting(E_ALL);
// Unified error display via security/error_handler.php
require_once 'security/csp.php';
require_once 'security/db_connect.php'; 
require_once 'security/session_config.php';

// Grade Management Class
class GradeManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Get all students
    public function getStudents() {
        $stmt = $this->pdo->query("SELECT * FROM students ORDER BY last_name, first_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get assessment types
    public function getAssessmentTypes() {
        $stmt = $this->pdo->query("
            SELECT at.*, gc.category_name, gc.weight_percentage 
            FROM assessment_types at 
            JOIN grade_categories gc ON at.category_id = gc.id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Create new assessment
    public function createAssessment($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO assessments (assessment_name, assessment_type_id, total_items, date_given, quarter, subject, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['assessment_name'],
            $data['assessment_type_id'],
            $data['total_items'],
            $data['date_given'],
            $data['quarter'] ?? 1,
            $data['subject'] ?? '',
            $data['created_by'] ?? 'instructor'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    // Save student grades
    public function saveGrades($grades) {
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO student_grades (student_id, assessment_id, raw_score, transmuted_grade) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                raw_score = VALUES(raw_score), 
                transmuted_grade = VALUES(transmuted_grade),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            foreach ($grades as $grade) {
                $stmt->execute([
                    $grade['student_id'],
                    $grade['assessment_id'],
                    $grade['raw_score'],
                    $grade['transmuted_grade']
                ]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    // Get transmuted grade from lookup table
    public function getTransmutedGrade($rawScore, $totalItems, $scale = 100) {
        $tableName = $scale == 95 ? 'transmutation_95' : 'transmutation_100';
        
        $stmt = $this->pdo->prepare("
            SELECT transmuted_grade FROM {$tableName} 
            WHERE total_items = ? AND raw_score = ?
        ");
        
        $stmt->execute([$totalItems, $rawScore]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['transmuted_grade'];
        }
        
        // Fallback calculation if not in lookup table
        $percentage = ($rawScore / $totalItems) * 100;
        if ($scale == 95) {
            return max(50, ($percentage * 0.45) + 50);
        } else {
            return max(50, ($percentage * 0.50) + 50);
        }
    }
    
    // Populate transmutation tables (run once)
    public function populateTransmutationTables() {
        // Transmutation data for 100-point scale
        $transmutation100 = [
            5 => [50, 60, 70, 80, 90, 100],
            10 => [50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100],
            15 => [50, 51, 55, 60, 65, 70, 75, 79, 83, 87, 92, 95, 97, 98, 99, 100],
            20 => [50, 51, 53, 56, 58, 61, 64, 66, 69, 72, 75, 78, 80, 83, 85, 88, 90, 93, 95, 98, 100],
            25 => [50, 51, 52, 54, 56, 58, 60, 63, 65, 67, 69, 71, 73, 75, 77, 80, 82, 84, 86, 89, 92, 94, 95, 98, 99, 100],
            30 => [50, 51, 52, 54, 55, 57, 59, 61, 62, 64, 66, 68, 69, 71, 73, 75, 76, 78, 80, 82, 83, 85, 87, 89, 90, 94, 95, 97, 99, 99, 100],
            // Add more as needed...
        ];
        
        // Transmutation data for 95-point scale
        $transmutation95 = [
            5 => [50, 59, 68, 77, 86, 95],
            10 => [50, 55, 59, 64, 68, 73, 77, 82, 86, 91, 95],
            15 => [50, 53, 56, 59, 62, 65, 68, 71, 74, 77, 80, 83, 86, 89, 92, 95],
            // Add more as needed...
        ];
        
        // Insert 100-point scale data
        $stmt100 = $this->pdo->prepare("
            INSERT IGNORE INTO transmutation_100 (total_items, raw_score, transmuted_grade) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($transmutation100 as $totalItems => $grades) {
            foreach ($grades as $rawScore => $transmutedGrade) {
                $stmt100->execute([$totalItems, $rawScore, $transmutedGrade]);
            }
        }
        
        // Insert 95-point scale data
        $stmt95 = $this->pdo->prepare("
            INSERT IGNORE INTO transmutation_95 (total_items, raw_score, transmuted_grade) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($transmutation95 as $totalItems => $grades) {
            foreach ($grades as $rawScore => $transmutedGrade) {
                $stmt95->execute([$totalItems, $rawScore, $transmutedGrade]);
            }
        }
    }
    
    // Get grades for a specific assessment
    public function getAssessmentGrades($assessmentId) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sg.raw_score, sg.transmuted_grade, a.assessment_name, a.total_items
            FROM students s
            LEFT JOIN student_grades sg ON s.id = sg.student_id AND sg.assessment_id = ?
            LEFT JOIN assessments a ON sg.assessment_id = a.id
            ORDER BY s.last_name, s.first_name
        ");
        
        $stmt->execute([$assessmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Calculate quarterly grades
    public function calculateQuarterlyGrades($studentId, $quarter = 1) {
        // Get Written Work average (25%)
        $writtenWork = $this->getAverageByCategory($studentId, 1, $quarter);
        
        // Get Performance Task average (50%) 
        $performanceTask = $this->getAverageByCategory($studentId, 2, $quarter);
        
        // Get Quarterly Assessment average (25%)
        $quarterlyAssessment = $this->getAverageByCategory($studentId, 3, $quarter);
        
        // Calculate final grade
        $finalGrade = ($writtenWork * 0.25) + ($performanceTask * 0.50) + ($quarterlyAssessment * 0.25);
        
        // Determine remarks
        $remarks = 'INCOMPLETE';
        if ($finalGrade >= 75) {
            $remarks = 'PASSED';
        } elseif ($finalGrade > 0 && $finalGrade < 75) {
            $remarks = 'FAILED';
        }
        
        // Save computed grades
        $stmt = $this->pdo->prepare("
            INSERT INTO computed_grades 
            (student_id, quarter, written_work_avg, performance_task_avg, quarterly_assessment_avg, final_grade, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            written_work_avg = VALUES(written_work_avg),
            performance_task_avg = VALUES(performance_task_avg),
            quarterly_assessment_avg = VALUES(quarterly_assessment_avg),
            final_grade = VALUES(final_grade),
            remarks = VALUES(remarks),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([$studentId, $quarter, $writtenWork, $performanceTask, $quarterlyAssessment, $finalGrade, $remarks]);
        
        return [
            'written_work_avg' => $writtenWork,
            'performance_task_avg' => $performanceTask,
            'quarterly_assessment_avg' => $quarterlyAssessment,
            'final_grade' => $finalGrade,
            'remarks' => $remarks
        ];
    }
    
    // Get average by category
    private function getAverageByCategory($studentId, $categoryId, $quarter) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(sg.transmuted_grade) as avg_grade
            FROM student_grades sg
            JOIN assessments a ON sg.assessment_id = a.id
            JOIN assessment_types at ON a.assessment_type_id = at.id
            WHERE sg.student_id = ? AND at.category_id = ? AND a.quarter = ?
        ");
        
        $stmt->execute([$studentId, $categoryId, $quarter]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['avg_grade'] ?? 0;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gradeManager = new GradeManager($pdo);
    $response = [];
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_assessment':
                $assessmentId = $gradeManager->createAssessment($_POST);
                $response = ['success' => true, 'assessment_id' => $assessmentId];
                break;
                
            case 'save_grades':
                $grades = json_decode($_POST['grades'], true);
                $gradeManager->saveGrades($grades);
                $response = ['success' => true, 'message' => 'Grades saved successfully'];
                break;
                
            case 'get_transmuted_grade':
                $transmutedGrade = $gradeManager->getTransmutedGrade(
                    $_POST['raw_score'],
                    $_POST['total_items'],
                    $_POST['scale'] ?? 100
                );
                $response = ['success' => true, 'transmuted_grade' => $transmutedGrade];
                break;
                
            case 'calculate_quarterly':
                $quarterlyGrades = $gradeManager->calculateQuarterlyGrades(
                    $_POST['student_id'],
                    $_POST['quarter'] ?? 1
                );
                $response = ['success' => true, 'quarterly_grades' => $quarterlyGrades];
                break;
                
            case 'populate_transmutation':
                $gradeManager->populateTransmutationTables();
                $response = ['success' => true, 'message' => 'Transmutation tables populated'];
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle GET requests for data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gradeManager = new GradeManager($pdo);
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_students':
            $students = $gradeManager->getStudents();
            header('Content-Type: application/json');
            echo json_encode($students);
            break;
            
        case 'get_assessment_types':
            $types = $gradeManager->getAssessmentTypes();
            header('Content-Type: application/json');
            echo json_encode($types);
            break;
            
        case 'get_assessment_grades':
            $assessmentId = $_GET['assessment_id'] ?? 0;
            $grades = $gradeManager->getAssessmentGrades($assessmentId);
            header('Content-Type: application/json');
            echo json_encode($grades);
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Management System</title>
    <!-- Removed external Bootstrap CSS for stricter CSP. -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/instructor.css">
    <style>
        /* Additional CSS for grade management */
        .grade-management-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .assessment-form {
            background: var(--secondary-bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .grade-input-table {
            overflow-x: auto;
        }
        
        .grade-input-table table {
            min-width: 800px;
        }
        
        .student-name-cell {
            background: var(--secondary-bg);
            font-weight: 600;
            min-width: 200px;
        }
        
        .grade-cell input {
            width: 70px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 5px;
        }
        
        .transmuted-display {
            font-weight: 600;
            margin-top: 5px;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        
        .grade-excellent { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .grade-good { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .grade-passing { color: #f59e0b; background: rgba(245, 158, 11, 0.1); }
        .grade-failing { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        
        .assessment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .stat-value {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.85em;
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- This would be integrated into your existing instructor dashboard -->
    <div class="grade-management-section">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-calculator me-2"></i>Grade Management System</h3>
            <button class="btn btn-success" onclick="exportGrades()">
                <i class="fas fa-download me-1"></i>Export Grades
            </button>
        </div>
        
        <!-- Assessment Creation Form -->
        <div class="assessment-form">
            <h5 class="mb-3">Create New Assessment</h5>
            <form id="assessmentForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Assessment Type</label>
                        <select class="form-select" id="assessmentType" required>
                            <option value="">Select Type</option>
                            <option value="1">Quiz (Written Work)</option>
                            <option value="2">Homework (Written Work)</option>
                            <option value="3">Activity (Performance Task)</option>
                            <option value="4">Exam (Quarterly Assessment)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Assessment Name</label>
                        <input type="text" class="form-control" id="assessmentName" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" id="assessmentDate" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total Items</label>
                        <input type="number" class="form-control" id="totalItems" min="1" max="100" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Transmutation Base</label>
                        <select class="form-select" id="transmutationBase" required>
                            <option value="45">Based 45</option>
                            <option value="50" selected>Based 50</option>
                            <option value="55">Based 55</option>
                            <option value="60">Based 60</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-plus me-1"></i>Create
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <small class="text-muted">Scale: <span id="scaleDisplay">100-point</span></small>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Transmutation: raw/total × <span id="baseDisplay">50</span> + 50</small>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Current Assessment Display -->
        <div id="currentAssessment" style="display: none;">
            <div class="alert alert-info">
                <strong>Current Assessment:</strong> <span id="currentAssessmentName"></span> |
                <strong>Total Items:</strong> <span id="currentTotalItems"></span> |
                <strong>Scale:</strong> <span id="currentScale"></span> |
                <strong>Base:</strong> <span id="currentBase"></span>
            </div>
        </div>
        
        <!-- Grade Input Table -->
        <div class="grade-input-table">
            <table class="table table-bordered table-hover" id="gradeTable">
                <thead class="table-primary">
                    <tr>
                        <th>Student</th>
                        <th>Raw Score</th>
                        <th>Transmuted Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="gradeTableBody">
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>
        
        <!-- Assessment Statistics -->
        <div class="assessment-stats" id="assessmentStats" style="display: none;">
            <div class="stat-card">
                <div class="stat-value" id="totalStudents">0</div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="averageRaw">0.0</div>
                <div class="stat-label">Average Raw Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="averageTransmuted">0.0</div>
                <div class="stat-label">Average Transmuted</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="passingRate">0%</div>
                <div class="stat-label">Passing Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="completionRate">0%</div>
                <div class="stat-label">Completion Rate</div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-flex gap-2 mt-3" id="actionButtons" style="display: none;">
            <button class="btn btn-success" onclick="saveAllGrades()">
                <i class="fas fa-save me-1"></i>Save All Grades
            </button>
            <button class="btn btn-info" onclick="calculateQuarterlyGrades()">
                <i class="fas fa-calculator me-1"></i>Calculate Quarterly Grades
            </button>
            <button class="btn btn-secondary" onclick="resetForm()">
                <i class="fas fa-refresh me-1"></i>Reset
            </button>
        </div>
    </div>

    <!-- Removed external Bootstrap JS CDN for stricter CSP. -->
    <script nonce="<?php echo htmlspecialchars($_SESSION['_csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        // Grade Management JavaScript
        let currentAssessmentData = null;
        let studentsData = [];
        let assessmentTypes = [];
        
        // Initialize the system
        document.addEventListener('DOMContentLoaded', function() {
            loadStudents();
            loadAssessmentTypes();
            
            // Set default date
            document.getElementById('assessmentDate').value = new Date().toISOString().split('T')[0];
            
            // Assessment type change handler
            document.getElementById('assessmentType').addEventListener('change', updateScale);
            // Transmutation base change handler
            document.getElementById('transmutationBase').addEventListener('change', updateBaseDisplay);
            
            // Form submission
            document.getElementById('assessmentForm').addEventListener('submit', createAssessment);
        });
        
        // Load students from database
        async function loadStudents() {
            try {
                const response = await fetch('grade_management.php?action=get_students');
                studentsData = await response.json();
            } catch (error) {
                console.error('Error loading students:', error);
                showAlert('Error loading students', 'danger');
            }
        }
        
        // Load assessment types
        async function loadAssessmentTypes() {
            try {
                const response = await fetch('grade_management.php?action=get_assessment_types');
                assessmentTypes = await response.json();
            } catch (error) {
                console.error('Error loading assessment types:', error);
            }
        }
        
        // Update scale display based on assessment type
        function updateScale() {
            const typeId = document.getElementById('assessmentType').value;
            const scaleDisplay = document.getElementById('scaleDisplay');
            
            // Type 3 (Activity) uses 95-point scale, others use 100-point
            if (typeId === '3') {
                scaleDisplay.textContent = '95-point (Performance Task)';
            } else {
                scaleDisplay.textContent = '100-point';
            }
            updateBaseDisplay();
        }
        
        function updateBaseDisplay() {
            const base = parseInt(document.getElementById('transmutationBase').value) || 50;
            document.getElementById('baseDisplay').textContent = String(base);
        }
        
        // Create new assessment
        async function createAssessment(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'create_assessment');
            formData.append('assessment_name', document.getElementById('assessmentName').value);
            formData.append('assessment_type_id', document.getElementById('assessmentType').value);
            formData.append('total_items', document.getElementById('totalItems').value);
            formData.append('date_given', document.getElementById('assessmentDate').value);
            formData.append('quarter', 1); // Default to quarter 1
            
            try {
                const response = await fetch('grade_management.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    currentAssessmentData = {
                        id: result.assessment_id,
                        name: document.getElementById('assessmentName').value,
                        type_id: document.getElementById('assessmentType').value,
                        total_items: parseInt(document.getElementById('totalItems').value),
                        scale: document.getElementById('assessmentType').value === '3' ? 95 : 100,
                        base: parseInt(document.getElementById('transmutationBase').value) || 50
                    };
                    
                    showCurrentAssessment();
                    generateGradeTable();
                    showAlert('Assessment created successfully!', 'success');
                } else {
                    showAlert(result.message || 'Error creating assessment', 'danger');
                }
            } catch (error) {
                console.error('Error creating assessment:', error);
                showAlert('Error creating assessment', 'danger');
            }
        }
        
        // Show current assessment info
        function showCurrentAssessment() {
            document.getElementById('currentAssessment').style.display = 'block';
            document.getElementById('currentAssessmentName').textContent = currentAssessmentData.name;
            document.getElementById('currentTotalItems').textContent = currentAssessmentData.total_items;
            document.getElementById('currentScale').textContent = currentAssessmentData.scale + '-point';
            document.getElementById('currentBase').textContent = (currentAssessmentData.base || 50);
            document.getElementById('actionButtons').style.display = 'flex';
        }
        
        // Generate grade input table
        function generateGradeTable() {
            const tbody = document.getElementById('gradeTableBody');
            tbody.innerHTML = '';
            
            studentsData.forEach(student => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="student-name-cell">
                        <strong>${student.last_name}, ${student.first_name} ${student.middle_name || ''}</strong><br>
                        <small class="text-muted">${student.student_number}</small>
                    </td>
                    <td class="grade-cell">
                        <input type="number" 
                               class="form-control grade-input" 
                               id="raw_${student.id}"
                               min="0" 
                               max="${currentAssessmentData.total_items}"
                               placeholder="0"
                               onchange="calculateTransmutation(${student.id})"
                               onkeyup="calculateTransmutation(${student.id})">
                    </td>
                    <td id="transmuted_${student.id}">
                        <div class="transmuted-display">--</div>
                    </td>
                    <td id="status_${student.id}">
                        <span class="badge bg-secondary">Not Graded</span>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            document.getElementById('assessmentStats').style.display = 'grid';
            updateStatistics();
        }
        
        // Calculate transmutation for a student
        async function calculateTransmutation(studentId) {
            const rawInput = document.getElementById(`raw_${studentId}`);
            const transmutedCell = document.getElementById(`transmuted_${studentId}`);
            const statusCell = document.getElementById(`status_${studentId}`);
            
            const rawScore = parseInt(rawInput.value) || 0;
            
            if (rawScore < 0 || rawScore > currentAssessmentData.total_items) {
                transmutedCell.innerHTML = '<div class="transmuted-display grade-failing">Invalid</div>';
                statusCell.innerHTML = '<span class="badge bg-danger">Invalid</span>';
                return;
            }
            
            try {
                const base = currentAssessmentData.base || 50;
                const total = currentAssessmentData.total_items;
                const transmutedRaw = (total > 0) ? (rawScore / total) * base + 50 : 50;
                const transmuted = Math.min(100, Math.max(50, transmutedRaw));

                // Determine grade class and status
                let gradeClass = 'grade-failing';
                let statusBadge = 'bg-danger';
                let statusText = 'Failed';
                
                if (transmuted >= 90) {
                    gradeClass = 'grade-excellent';
                    statusBadge = 'bg-success';
                    statusText = 'Excellent';
                } else if (transmuted >= 80) {
                    gradeClass = 'grade-good';
                    statusBadge = 'bg-info';
                    statusText = 'Good';
                } else if (transmuted >= 75) {
                    gradeClass = 'grade-passing';
                    statusBadge = 'bg-warning';
                    statusText = 'Passed';
                }
                
                transmutedCell.innerHTML = `<div class="transmuted-display ${gradeClass}">${transmuted.toFixed(1)}</div>`;
                statusCell.innerHTML = `<span class="badge ${statusBadge}">${statusText}</span>`;
                
                updateStatistics();
            } catch (error) {
                console.error('Error calculating transmutation:', error);
            }
        }
        
        // Update statistics
        function updateStatistics() {
            let totalStudents = studentsData.length;
            let gradedStudents = 0;
            let totalRaw = 0;
            let totalTransmuted = 0;
            let passingStudents = 0;
            
            studentsData.forEach(student => {
                const rawInput = document.getElementById(`raw_${student.id}`);
                const transmutedDiv = document.querySelector(`#transmuted_${student.id} .transmuted-display`);
                
                if (rawInput && rawInput.value && transmutedDiv && transmutedDiv.textContent !== '--') {
                    gradedStudents++;
                    totalRaw += parseInt(rawInput.value);
                    const transmuted = parseFloat(transmutedDiv.textContent);
                    totalTransmuted += transmuted;
                    
                    if (transmuted >= 75) {
                        passingStudents++;
                    }
                }
            });
            
            document.getElementById('totalStudents').textContent = totalStudents;
            document.getElementById('averageRaw').textContent = gradedStudents > 0 ? (totalRaw / gradedStudents).toFixed(1) : '0.0';
            document.getElementById('averageTransmuted').textContent = gradedStudents > 0 ? (totalTransmuted / gradedStudents).toFixed(1) : '0.0';
            document.getElementById('passingRate').textContent = gradedStudents > 0 ? ((passingStudents / gradedStudents) * 100).toFixed(1) + '%' : '0%';
            document.getElementById('completionRate').textContent = totalStudents > 0 ? ((gradedStudents / totalStudents) * 100).toFixed(1) + '%' : '0%';
        }
        
        // Save all grades
        async function saveAllGrades() {
            if (!currentAssessmentData) {
                showAlert('No assessment selected', 'warning');
                return;
            }
            
            const grades = [];
            
            studentsData.forEach(student => {
                const rawInput = document.getElementById(`raw_${student.id}`);
                const transmutedDiv = document.querySelector(`#transmuted_${student.id} .transmuted-display`);
                
                if (rawInput && rawInput.value && transmutedDiv && transmutedDiv.textContent !== '--' && transmutedDiv.textContent !== 'Invalid') {
                    grades.push({
                        student_id: student.id,
                        assessment_id: currentAssessmentData.id,
                        raw_score: parseInt(rawInput.value),
                        transmuted_grade: parseFloat(transmutedDiv.textContent)
                    });
                }
            });
            
            if (grades.length === 0) {
                showAlert('No grades to save', 'warning');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'save_grades');
                formData.append('grades', JSON.stringify(grades));
                
                const response = await fetch('grade_management.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(`Successfully saved ${grades.length} grades!`, 'success');
                } else {
                    showAlert(result.message || 'Error saving grades', 'danger');
                }
            } catch (error) {
                console.error('Error saving grades:', error);
                showAlert('Error saving grades', 'danger');
            }
        }
        
        // Calculate quarterly grades for all students
        async function calculateQuarterlyGrades() {
            for (let student of studentsData) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'calculate_quarterly');
                    formData.append('student_id', student.id);
                    formData.append('quarter', 1);
                    
                    await fetch('grade_management.php', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Error calculating quarterly grades for student:', student.id);
                }
            }
            
            showAlert('Quarterly grades calculated successfully!', 'success');
        }
        
        // Reset form
        function resetForm() {
            document.getElementById('assessmentForm').reset();
            document.getElementById('currentAssessment').style.display = 'none';
            document.getElementById('assessmentStats').style.display = 'none';
            document.getElementById('actionButtons').style.display = 'none';
            document.getElementById('gradeTableBody').innerHTML = '';
            currentAssessmentData = null;
            updateScale();
        }
        
        // Export grades (placeholder)
        function exportGrades() {
            showAlert('Export feature will be implemented', 'info');
        }
        
        // Show alert messages
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('.grade-management-section').insertBefore(alertDiv, document.querySelector('.grade-management-section').firstChild);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>