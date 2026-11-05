<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/actions.php';
require __DIR__.'/app/layout.php';
if (($_GET['page']??'')==='verify_email') {
    $ok=sr_verify_token((int)($_GET['uid']??0),(string)($_GET['token']??''));
    sr_flash($ok?'Email đã được xác minh. Bạn có thể đăng nhập.':'Liên kết xác minh không hợp lệ hoặc hết hạn. Hãy yêu cầu gửi lại email.',$ok?'success':'danger');
    sr_redirect($ok?'login':'verify_pending');
}
$page=(string)($_GET['page']??'home');
$pages=['home','job','login','register','verify_pending','dashboard','profile','resume','recommendations','saved','applications',
 'myjobs','job_form','applicants','applicant','admin_jobs','admin_users','admin_audit','admin_emails','notifications','download'];
if (!in_array($page,$pages,true)) {http_response_code(404);$page='not_found';}
$user=sr_current();
if ($user && !sr_is_active((int)$user['id'])) {$_SESSION=[];session_destroy();header('Location: index.php?page=login');exit;}
$rolePages=['dashboard'=>null,'profile'=>null,'resume'=>'candidate','recommendations'=>'candidate','saved'=>'candidate',
 'applications'=>'candidate','myjobs'=>'recruiter','job_form'=>'recruiter','applicants'=>'recruiter','applicant'=>'recruiter',
 'admin_jobs'=>'admin','admin_users'=>'admin','admin_audit'=>'admin','admin_emails'=>'admin','notifications'=>null,'download'=>null];
if (array_key_exists($page,$rolePages)) sr_auth($rolePages[$page]);
if ($page==='download') { require __DIR__.'/app/download.php';exit; }
$titles=['home'=>'Tìm việc làm','job'=>'Chi tiết việc làm','login'=>'Đăng nhập','register'=>'Đăng ký','verify_pending'=>'Xác minh email','dashboard'=>'Tổng quan',
 'profile'=>'Hồ sơ của tôi','resume'=>'Quản lý CV','recommendations'=>'Gợi ý việc làm','saved'=>'Việc đã lưu','applications'=>'Đơn ứng tuyển',
 'myjobs'=>'Quản lý tin','job_form'=>'Soạn tin tuyển dụng','applicants'=>'Danh sách ứng viên','applicant'=>'Hồ sơ ứng viên','admin_jobs'=>'Duyệt tin tuyển dụng',
 'admin_users'=>'Quản lý người dùng','admin_audit'=>'Nhật ký hệ thống','admin_emails'=>'Nhật ký gửi email','notifications'=>'Thông báo','not_found'=>'Không tìm thấy'];
sr_layout_start($titles[$page]??'SmartRecruit AI',$page);
require __DIR__.'/views/'.$page.'.php';
sr_layout_end();
