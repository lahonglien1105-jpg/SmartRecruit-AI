<?php
declare(strict_types=1);
$u=sr_auth();$candidateId=(int)($_GET['candidate']??$u['id']);
if ($candidateId!==(int)$u['id']) {
    if ($u['role']!=='recruiter') {http_response_code(403);exit('Không có quyền tải CV này.');}
    $allowed=sr_one('SELECT a.id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.candidate_id=? AND j.recruiter_id=? LIMIT 1',[$candidateId,$u['id']]);
    if (!$allowed) {http_response_code(403);exit('Không có quyền truy cập CV.');}
}
$r=sr_one('SELECT * FROM resumes WHERE user_id=?',[$candidateId]);
if (!$r) {http_response_code(404);exit('Không tìm thấy CV.');}
$path=dirname(__DIR__).'/uploads/'.basename($r['filename']);
if (!is_file($path)) {http_response_code(404);exit('Không tìm thấy tệp CV trên máy chủ.');}
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
if (!in_array($ext,['pdf','docx','txt'],true)) {http_response_code(403);exit('Định dạng không được hỗ trợ.');}
$types=['pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','txt'=>'text/plain'];
header('Content-Type: '.$types[$ext]);header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="resume.'.$ext.'"');header('Content-Length: '.filesize($path));
readfile($path);
