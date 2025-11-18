<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../security/db_connect.php';
require_once __DIR__ . '/../security/session_config.php';

$sn = isset($_GET['student_number']) ? trim((string)$_GET['student_number']) : '';
if($sn===''){ echo json_encode(['success'=>true,'data'=>[]]); exit; }

try{
    $st = $pdo->prepare('SELECT course, status, start_date, end_date FROM student_course_history WHERE student_number = ? ORDER BY end_date DESC, created_at DESC');
    $st->execute([$sn]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success'=>true,'data'=>$rows]);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Failed to load history']);
}
?>


