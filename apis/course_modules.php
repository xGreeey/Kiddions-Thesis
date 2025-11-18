<?php
require_once '../security/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get course parameter
    $course = $_GET['course'] ?? '';
    
    if (empty($course)) {
        echo json_encode(['success' => false, 'message' => 'Course parameter is required']);
        exit;
    }
    
    // Decode URL-encoded course name
    $course = urldecode($course);
    
    // Define modules per course
    $courseModules = [
        'Automotive Servicing' => [
            'Introduction to Automotive Servicing',
            'Performing Periodic Maintenance of the Automotive Engine',
            'Diagnosing and Repairing Engine Cooling and Lubricating System',
            'Diagnosing and Repairing Intake and Exhaust Systems',
            'Diagnosing and Overhauling Engine Mechanical Systems',
        ],
        'Basic Computer Literacy' => [
            'Introduction to Computer Systems',
            'Operating System Fundamentals',
            'Word Processing Applications',
            'Spreadsheet Applications',
            'Internet and Email Usage',
        ],
        'Beauty Care (Nail Care)' => [
            'Beauty Care Services (Nail Care) NCII',
            'Manicure and Pedicure Techniques',
            'Nail Art and Design',
            'Sanitation and Safety Procedures',
        ],
        'Bread and Pastry Production' => [
            'Preparing Cakes',
            'Preparing Cookies',
            'Preparing Pies and Pastries',
            'Preparing Breads and Rolls',
        ],
        'Computer Systems Servicing' => [
            'Introduction to CSS',
            'Installing and Configuring Computer Systems',
            'Setting Up Computer Networks',
            'Setting Up Computer Servers',
            'Maintaining Computer Systems and Networks',
        ],
        'Dressmaking' => [
            'Basic Sewing Techniques',
            'Pattern Making and Cutting',
            'Garment Construction',
            'Fitting and Alterations',
        ],
        'Electrical Installation and Maintenance' => [
            'Introduction to Electrical Installation and Maintenance',
            'Performing Roughing-In Activities, Wiring and Cabling Works for Single-Phase Distribution, Power, Lighting and Auxiliary Systems',
            'Installing Electrical Protective Devices for Distribution, Power, Lightning Protection and Grounding Systems',
            'Installing Wiring Devices for Floor and Wall Mounted Outlets, Lighting Fixtures, Switches and Auxiliary Outlets',
        ],
        'Electronic Products and Assembly Servicing' => [
            'Introduction to Electronics',
            'Electronic Components and Circuits',
            'Assembly and Testing Procedures',
            'Troubleshooting and Repair',
        ],
        'Events Management Services' => [
            'Introduction to Events Management',
            'Planning and Coordination',
            'Venue Setup and Management',
            'Client Relations and Service',
        ],
        'Food and Beverage Services' => [
            'Introduction to F&B Services',
            'Table Service Techniques',
            'Beverage Service',
            'Customer Service Excellence',
        ],
        'Food Processing' => [
            'Introduction to Food Processing',
            'Food Safety and Sanitation',
            'Processing Techniques',
            'Quality Control and Packaging',
        ],
        'Hairdressing' => [
            'Introduction to Hairdressing',
            'Hair Cutting Techniques',
            'Hair Styling and Coloring',
            'Client Consultation and Service',
        ],
        'Housekeeping' => [
            'Introduction to Housekeeping',
            'Cleaning Techniques and Procedures',
            'Room Preparation and Maintenance',
            'Guest Service Excellence',
        ],
        'Massage Therapy' => [
            'Introduction to Massage Therapy',
            'Basic Massage Techniques',
            'Anatomy and Physiology',
            'Client Assessment and Treatment',
        ],
        'RAC Servicing' => [
            'Packaged Air Conditioner Unit Servicing',
            'Refrigeration System Fundamentals',
            'Troubleshooting and Repair',
            'Installation and Maintenance',
        ],
        'Shielded Metal Arc Welding' => [
            'Introduction to SMAW',
            'Safety Procedures and Equipment',
            'Basic Welding Techniques',
            'Advanced Welding Applications',
        ],
    ];
    
    // Normalize incoming course to match keys regardless of case or parenthetical codes
    $normalize = function($s){
        $s = trim((string)$s);
        // remove parenthetical codes like (FBS)
        $s = preg_replace('/\s*\([^\)]*\)\s*/', ' ', $s);
        // collapse whitespace and lowercase for comparison
        $s = strtolower(preg_replace('/\s+/', ' ', $s));
        return $s;
    };

    // Build lookup map of normalized name -> canonical key
    $nameMap = [];
    foreach ($courseModules as $name => $_) {
        $nameMap[$normalize($name)] = $name;
        $nameMap[strtolower($name)] = $name; // direct lowercase fallback
    }

    // Map of common TESDA codes to canonical names
    $codeMap = [
        'ATS' => 'Automotive Servicing',
        'BCL' => 'Basic Computer Literacy',
        'BEC' => 'Beauty Care (Nail Care)',
        'BPP' => 'Bread and Pastry Production',
        'CSS' => 'Computer Systems Servicing',
        'DRM' => 'Dressmaking',
        'EIM' => 'Electrical Installation and Maintenance',
        'EPAS' => 'Electronic Products and Assembly Servicing',
        'EVM' => 'Events Management Services',
        'FBS' => 'Food and Beverage Services',
        'FOP' => 'Food Processing',
        'HDR' => 'Hairdressing',
        'HSK' => 'Housekeeping',
        'MAT' => 'Massage Therapy',
        'RAC' => 'RAC Servicing',
        'SMAW' => 'Shielded Metal Arc Welding',
    ];

    $canonical = null;
    $normInput = $normalize($course);
    if (isset($nameMap[$normInput])) {
        $canonical = $nameMap[$normInput];
    } else {
        // Try extract code from parentheses e.g. "FOOD AND BEVERAGE SERVICES (FBS)"
        if (preg_match('/\(([^\)]+)\)/', $course, $m)) {
            $code = strtoupper(trim($m[1]));
            if (isset($codeMap[$code])) { $canonical = $codeMap[$code]; }
        }
        // Try direct code match
        if (!$canonical && isset($codeMap[strtoupper($course)])) {
            $canonical = $codeMap[strtoupper($course)];
        }
    }

    if ($canonical === null) {
        // Last resort: attempt partial fuzzy match by startswith
        foreach ($nameMap as $k => $v) {
            if (strpos($k, $normInput) !== false || strpos($normInput, $k) !== false) { $canonical = $v; break; }
        }
    }

    // Get modules for the resolved course
    $modules = ($canonical && isset($courseModules[$canonical])) ? $courseModules[$canonical] : [];
    
    // Format modules with additional information
    $formattedModules = [];
    foreach ($modules as $index => $module) {
        $formattedModules[] = [
            'id' => $index + 1,
            'name' => $module,
            'status' => 'Not Started', // Default status
            'progress' => 0, // Default progress
        ];
    }
    
    echo json_encode([
        'success' => true,
        'course' => $canonical ?: $course,
        'modules' => $formattedModules,
        'count' => count($formattedModules)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching modules: ' . $e->getMessage()
    ]);
}
?>
