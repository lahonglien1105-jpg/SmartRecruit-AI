<?php
declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
sr_csrf();
$action=(string)($_POST['action']??'');
$u=sr_current();
try {
 switch ($action) {
 case 'register':
    if ($u) sr_redirect('dashboard');
    $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??'')));
    $password=(string)($_POST['password']??'');$role=(string)($_POST['role']??'candidate');
    if (mb_strlen($name)<2 || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<8 || !in_array($role,['candidate','recruiter'],true)) throw new RuntimeException('Vui lòng nhập họ tên, email hợp lệ và mật khẩu từ 8 ký tự.');
    if (sr_one('SELECT id FROM users WHERE email=?',[$email])) throw new RuntimeException('Email đã được đăng ký.');
    if (sr_env_yes('MAIL_VERIFY_REQUIRED',sr_mail_ready()) && !sr_mail_ready()) throw new RuntimeException('Máy chủ chưa cấu hình dịch vụ gửi email xác minh.');
    db()->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
    $uid=(int)db()->lastInsertId();
    if (sr_mail_ready()) {
        sr_send_verification($uid,$email,$name,false);
        sr_flash('Đã tạo tài khoản. Vui lòng kiểm tra email (cả mục Spam) để xác minh.','success');sr_redirect('verify_pending');
    }
    sr_flash('Đã tạo tài khoản ở chế độ localhost. Chưa cấu hình Resend nên chưa gửi email xác minh.','info');sr_redirect('login');
 case 'verification_resend':
    $email=strtolower(trim((string)($_POST['email']??'')));
    if (filter_var($email,FILTER_VALIDATE_EMAIL) && sr_mail_ready()) {
        $target=sr_one('SELECT id,name,email FROM users WHERE email=?',[$email]);
        if ($target) sr_send_verification((int)$target['id'],$target['email'],$target['name']);
    }
    sr_flash('Nếu tài khoản tồn tại và chưa xác minh, email mới sẽ được gửi (tối đa 1 lần/90 giây).','info');sr_redirect('verify_pending');
 case 'email_resend_profile':
    $u=sr_auth();$record=sr_one('SELECT name,email FROM users WHERE id=?',[$u['id']]);
    if (!sr_mail_ready()) throw new RuntimeException('Chưa cấu hình Resend API và MAIL_FROM.');
    $sent=sr_send_verification((int)$u['id'],$record['email'],$record['name']);
    sr_flash($sent?'Đã yêu cầu gửi thư xác minh.':'Email đã xác minh hoặc cần đợi 90 giây trước khi gửi lại.','info');sr_redirect('profile');
 case 'email_preferences':
    $u=sr_auth();$vals=[];
    foreach(['email_application','email_status','email_interview'] as $key) $vals[]=isset($_POST[$key])?1:0;
    db()->prepare('INSERT INTO notification_preferences(user_id,email_application,email_status,email_interview) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE email_application=VALUES(email_application),email_status=VALUES(email_status),email_interview=VALUES(email_interview)')
       ->execute([$u['id'],...$vals]);
    sr_flash('Đã cập nhật tùy chọn nhận email.','success');sr_redirect('profile');
 case 'login':
    if ($u) sr_redirect('dashboard');
    $tries=(int)($_SESSION['sr_failed_login']??0);
    if ($tries>=8) throw new RuntimeException('Bạn đã thử đăng nhập quá nhiều lần trong phiên này. Hãy đóng trình duyệt và thử lại sau.');
    $email=strtolower(trim((string)($_POST['email']??'')));
    $record=sr_one('SELECT id,name,email,role,password FROM users WHERE email=?',[$email]);
    if (!$record || !password_verify((string)($_POST['password']??''),$record['password'])) {
        $_SESSION['sr_failed_login']=$tries+1;throw new RuntimeException('Email hoặc mật khẩu không đúng.');
    }
    if (!sr_is_active((int)$record['id'])) throw new RuntimeException('Tài khoản đã bị khóa.');
    if (sr_env_yes('MAIL_VERIFY_REQUIRED',sr_mail_ready())) {
        $verification=sr_one('SELECT verified_at FROM user_emails WHERE user_id=?',[(int)$record['id']]);
        if ($verification && $verification['verified_at']===null) throw new RuntimeException('Hãy xác minh email trước khi đăng nhập. Có thể yêu cầu gửi lại email tại trang xác minh.');
    }
    session_regenerate_id(true);unset($_SESSION['sr_failed_login']);
    $_SESSION['user']=['id'=>(int)$record['id'],'name'=>$record['name'],'role'=>$record['role']];
    sr_redirect('dashboard');
 case 'logout':
    $_SESSION=[];if (ini_get('session.use_cookies')) { $p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
    session_destroy();sr_redirect('home');
 case 'profile_save':
    $u=sr_auth();$name=trim((string)($_POST['name']??''));
    if (mb_strlen($name)<2 || mb_strlen($name)>120) throw new RuntimeException('Tên phải có từ 2 đến 120 ký tự.');
    db()->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name,$u['id']]);
    $_SESSION['user']['name']=$name;
    if ($u['role']==='candidate') {
        $phone=trim((string)($_POST['phone']??''));$portfolio=trim((string)($_POST['portfolio']??''));
        if ($phone!=='' && !preg_match('/^[+\d().\s-]{9,22}$/',$phone)) throw new RuntimeException('Số điện thoại không hợp lệ.');
        if (!sr_validate_url($portfolio)) throw new RuntimeException('Liên kết portfolio phải bắt đầu bằng http:// hoặc https://.');
        $data=[mb_substr(trim((string)($_POST['headline']??'')),0,200),$phone,mb_substr(trim((string)($_POST['location']??'')),0,150),
          trim((string)($_POST['bio']??'')),trim((string)($_POST['education']??'')),trim((string)($_POST['experience']??'')),$portfolio,$u['id']];
        db()->prepare('INSERT INTO candidate_profiles(user_id,headline,phone,location,bio,education,experience,portfolio) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE headline=VALUES(headline),phone=VALUES(phone),location=VALUES(location),bio=VALUES(bio),education=VALUES(education),experience=VALUES(experience),portfolio=VALUES(portfolio)')
            ->execute([$u['id'],...array_slice($data,0,7)]);
    } elseif ($u['role']==='recruiter') {
        $website=trim((string)($_POST['website']??''));
        if (!sr_validate_url($website)) throw new RuntimeException('Website công ty không hợp lệ.');
        db()->prepare('INSERT INTO recruiter_profiles(user_id,company_name,website,company_size,about) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE company_name=VALUES(company_name),website=VALUES(website),company_size=VALUES(company_size),about=VALUES(about)')
            ->execute([$u['id'],mb_substr(trim((string)($_POST['company_name']??'')),0,160),$website,mb_substr(trim((string)($_POST['company_size']??'')),0,50),trim((string)($_POST['about']??''))]);
    }
    sr_flash('Đã cập nhật hồ sơ.','success');sr_redirect('profile');
 case 'change_password':
    $u=sr_auth();$old=(string)($_POST['old_password']??'');$new=(string)($_POST['new_password']??'');
    $saved=sr_one('SELECT password FROM users WHERE id=?',[$u['id']]);
    if (!$saved || !password_verify($old,$saved['password'])) throw new RuntimeException('Mật khẩu hiện tại không đúng.');
    if (strlen($new)<8) throw new RuntimeException('Mật khẩu mới tối thiểu 8 ký tự.');
    db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$u['id']]);
    sr_flash('Đổi mật khẩu thành công.','success');sr_redirect('profile');
 case 'resume_upload':
    $u=sr_auth('candidate');$f=$_FILES['resume']??null;
    if (!is_array($f) || ($f['error']??1)!==UPLOAD_ERR_OK || !isset($f['tmp_name'],$f['name'],$f['size']) || (int)$f['size']>5*1024*1024 || (int)$f['size']<30) throw new RuntimeException('Chọn CV PDF/DOCX/TXT dung lượng từ 30 byte đến 5 MB.');
    $ext=strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION));
    if (!in_array($ext,['pdf','docx','txt'],true)) throw new RuntimeException('Chỉ hỗ trợ PDF, DOCX và TXT.');
    // A PDF can contain an optional prefix before its %PDF header.
    $head=file_get_contents($f['tmp_name'],false,null,0,1024);
    if ($head===false) throw new RuntimeException('Không thể đọc dữ liệu CV tải lên.');
    if ($ext==='pdf' && strpos($head,'%PDF-')===false) throw new RuntimeException('Tệp không có chữ ký PDF hợp lệ.');
    if ($ext==='docx' && !str_starts_with($head,'PK')) throw new RuntimeException('Tệp DOCX không hợp lệ.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $valid=['pdf'=>['application/pdf','application/x-pdf','application/octet-stream'],
            'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'],
            'txt'=>['text/plain','application/octet-stream']];
    if (!in_array($mime,$valid[$ext],true)) throw new RuntimeException('Nội dung file không phù hợp định dạng '.strtoupper($ext).' ('.$mime.').');
    if (empty($_POST['cv_consent'])) throw new RuntimeException('Bạn cần xác nhận đồng ý xử lý CV để sử dụng phân tích AI.');
    if ($ext==='docx' && class_exists('ZipArchive')) {
        $z=new ZipArchive();if ($z->open($f['tmp_name'])!==true) throw new RuntimeException('DOCX bị lỗi.');
        $validDoc=$z->locateName('word/document.xml')!==false;$z->close();
        if (!$validDoc) throw new RuntimeException('Không tìm thấy nội dung Word trong DOCX.');
    }
    $base=dirname(__DIR__).'/uploads';if (!is_dir($base)) mkdir($base,0700,true);
    $new=bin2hex(random_bytes(20)).'.'.$ext;$dest=$base.'/'.$new;
    if (!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('Không thể lưu file CV.');
    if (!extension_loaded('curl')) {unlink($dest);throw new RuntimeException('PHP chưa bật tiện ích curl. Hãy bật extension=curl trong php.ini.');}
    $ch=curl_init((defined('AI_URL') ? rtrim(AI_URL,'/') : 'http://127.0.0.1:8000').'/parse');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>['file'=>new CURLFile($dest,$mime,basename((string)$f['name']))],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>40]);
    // Release PHP session lock during potentially long OCR/model inference.
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $response=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $curlError=curl_error($ch);curl_close($ch);
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    $parsed=is_string($response)?json_decode($response,true):null;
    if ($response===false) {
        unlink($dest);throw new RuntimeException('Không kết nối được AI Server ở cổng 8000: '.$curlError);
    }
    if ($status!==200) {
        unlink($dest);
        $detail=is_array($parsed)?($parsed['detail']??'Không có chi tiết'):null;
        throw new RuntimeException('FastAPI HTTP '.$status.': '.(is_string($detail)?mb_substr($detail,0,320):'Yêu cầu phân tích không hợp lệ'));
    }
    if (!is_array($parsed) || !isset($parsed['text']) || trim((string)$parsed['text'])==='') {
        unlink($dest);throw new RuntimeException('API trả về không có văn bản CV. Hãy kiểm tra PDF có lớp văn bản hoặc bật OCR.');
    }
    $old=sr_one('SELECT filename FROM resumes WHERE user_id=?',[$u['id']]);
    try {
        db()->prepare('INSERT INTO resumes(user_id,filename,original_name,raw_text,skills,parsed_json) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),original_name=VALUES(original_name),raw_text=VALUES(raw_text),skills=VALUES(skills),parsed_json=VALUES(parsed_json)')
          ->execute([$u['id'],$new,mb_substr(basename((string)$f['name']),0,255),$parsed['text'],implode(', ',(array)($parsed['skills']??[])),json_encode($parsed,JSON_UNESCAPED_UNICODE)]);
    } catch(Throwable $e) {unlink($dest);throw $e;}
    db()->prepare('INSERT INTO cv_consents(user_id,consent_version,consented_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE consent_version=VALUES(consent_version),consented_at=VALUES(consented_at)')->execute([$u['id'],'v1-2026']);
    sr_audit($u,'cv.upload','resume',(int)$u['id']);
    if ($old && is_file($base.'/'.$old['filename'])) unlink($base.'/'.$old['filename']);
    sr_flash('CV đã được đọc và phân tích thành công.','success');sr_redirect('resume');
 case 'resume_delete':
    $u=sr_auth('candidate');$old=sr_one('SELECT filename FROM resumes WHERE user_id=?',[$u['id']]);
    db()->prepare('DELETE FROM resumes WHERE user_id=?')->execute([$u['id']]);
    if ($old && is_file(dirname(__DIR__).'/uploads/'.$old['filename'])) unlink(dirname(__DIR__).'/uploads/'.$old['filename']);
    db()->prepare('DELETE FROM cv_consents WHERE user_id=?')->execute([$u['id']]);
    sr_audit($u,'cv.delete','resume',(int)$u['id']);
    sr_flash('Đã xóa CV khỏi hồ sơ. Các đơn ứng tuyển đã gửi vẫn được giữ lại.','success');sr_redirect('resume');
 case 'resume_correct':
    $u=sr_auth('candidate');$cv=sr_one('SELECT * FROM resumes WHERE user_id=?',[$u['id']]);
    if (!$cv) throw new RuntimeException('Bạn cần tải CV trước khi xác nhận kỹ năng.');
    $skills=trim((string)($_POST['skills']??''));
    if (mb_strlen($skills)>1000) throw new RuntimeException('Danh sách kỹ năng tối đa 1000 ký tự.');
    $list=sr_skills($skills);
    if (count($list)>50) throw new RuntimeException('Chỉ nhập tối đa 50 kỹ năng.');
    $parsed=json_decode((string)$cv['parsed_json'],true);if(!is_array($parsed))$parsed=[];
    $parsed['manual_review']=['skills_confirmed'=>true,'updated_at'=>date('c')];
    $parsed['skills']=$list;
    db()->prepare('UPDATE resumes SET skills=?,parsed_json=? WHERE user_id=?')->execute([implode(', ',$list),json_encode($parsed,JSON_UNESCAPED_UNICODE),$u['id']]);
    sr_audit($u,'cv.skills_confirm','resume',(int)$u['id']);
    sr_flash('Đã lưu kỹ năng bạn xác nhận. Đây là thông tin tự khai, không phải chứng nhận năng lực.','success');sr_redirect('resume');
 case 'job_save':
    $u=sr_auth('recruiter');$id=(int)($_POST['id']??0);
    if ($id && !sr_job_owned($id,(int)$u['id'])) throw new RuntimeException('Bạn không có quyền sửa tin này.');
    $title=trim((string)($_POST['title']??''));$company=trim((string)($_POST['company']??''));
    $location=trim((string)($_POST['location']??''));$salary=trim((string)($_POST['salary']??''));
    $skills=trim((string)($_POST['skills']??''));$description=trim((string)($_POST['description']??''));
    $jobType=(string)($_POST['job_type']??'full_time');$mode=(string)($_POST['work_mode']??'onsite');
    $level=(string)($_POST['level']??'junior');$deadline=trim((string)($_POST['deadline']??''));
    if (mb_strlen($title)<4 || mb_strlen($title)>200 || $company==='' || mb_strlen($company)>160 || mb_strlen($description)<30 || $skills==='') throw new RuntimeException('Điền đầy đủ vị trí, công ty, kỹ năng và mô tả ít nhất 30 ký tự.');
    if (!in_array($jobType,['full_time','part_time','internship','contract'],true)||!in_array($mode,['onsite','hybrid','remote'],true)||!in_array($level,['intern','junior','mid','senior'],true)) throw new RuntimeException('Thông tin hình thức làm việc không hợp lệ.');
    if ($deadline!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$deadline) || !checkdate((int)substr($deadline,5,2),(int)substr($deadline,8,2),(int)substr($deadline,0,4)))) throw new RuntimeException('Hạn nộp không hợp lệ.');
    db()->beginTransaction();
    try {
        if ($id) db()->prepare("UPDATE jobs SET title=?,company=?,location=?,salary=?,description=?,skills=?,status='pending' WHERE id=? AND recruiter_id=?")
            ->execute([$title,$company,$location,$salary,$description,$skills,$id,$u['id']]);
        else {db()->prepare('INSERT INTO jobs(recruiter_id,title,company,location,salary,description,skills,status) VALUES(?,?,?,?,?,?,?,\'pending\')')
            ->execute([$u['id'],$title,$company,$location,$salary,$description,$skills]);$id=(int)db()->lastInsertId();}
        db()->prepare('INSERT INTO job_details(job_id,job_type,work_mode,level,deadline,benefits) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE job_type=VALUES(job_type),work_mode=VALUES(work_mode),level=VALUES(level),deadline=VALUES(deadline),benefits=VALUES(benefits)')
            ->execute([$id,$jobType,$mode,$level,$deadline?:null,trim((string)($_POST['benefits']??''))]);
        db()->commit();
    } catch(Throwable $e){db()->rollBack();throw $e;}
    sr_audit($u,'job.save','job',$id);
    sr_flash('Đã lưu tin tuyển dụng. Tin cần được duyệt lại trước khi hiển thị.','success');sr_redirect('myjobs');
 case 'job_delete':
    $u=sr_auth('recruiter');$id=(int)($_POST['id']??0);
    if (!sr_job_owned($id,(int)$u['id'])) throw new RuntimeException('Không tìm thấy tin tuyển dụng của bạn.');
    if (sr_apply_count($id)>0) throw new RuntimeException('Tin đã có ứng viên nên không thể xóa. Bạn có thể đóng tin bằng cách gửi yêu cầu quản trị viên từ chối hiển thị.');
    db()->prepare('DELETE FROM jobs WHERE id=? AND recruiter_id=?')->execute([$id,$u['id']]);
    sr_audit($u,'job.delete','job',$id);
    sr_flash('Đã xóa tin tuyển dụng.','success');sr_redirect('myjobs');
 case 'job_close':
    $u=sr_auth('recruiter');$id=(int)($_POST['id']??0);
    if (!sr_job_owned($id,(int)$u['id'])) throw new RuntimeException('Không tìm thấy tin tuyển dụng.');
    db()->prepare("UPDATE jobs SET status='rejected' WHERE id=? AND recruiter_id=?")->execute([$id,$u['id']]);
    sr_audit($u,'job.close','job',$id);
    sr_flash('Đã ẩn tin khỏi danh sách tuyển dụng.','success');sr_redirect('myjobs');
 case 'job_review':
    $u=sr_auth('admin');$id=(int)($_POST['id']??0);$st=(string)($_POST['status']??'');
    if (!in_array($st,['approved','rejected'],true)) throw new RuntimeException('Trạng thái duyệt không hợp lệ.');
    $job=sr_one('SELECT * FROM jobs WHERE id=?',[$id]);if (!$job) throw new RuntimeException('Không tìm thấy tin.');
    db()->prepare('UPDATE jobs SET status=? WHERE id=?')->execute([$st,$id]);
    sr_notice((int)$job['recruiter_id'],'Tin "'.$job['title'].'" '.($st==='approved'?'đã được duyệt.':'đã bị từ chối.'),sr_url('myjobs'));
    sr_audit($u,'job.'.$st,'job',$id);
    sr_flash('Đã cập nhật trạng thái tin tuyển dụng.','success');sr_redirect('admin_jobs');
 case 'save_job':
    $u=sr_auth('candidate');$id=(int)($_POST['job_id']??0);
    if (!sr_one("SELECT id FROM jobs WHERE id=? AND status='approved'",[$id])) throw new RuntimeException('Tin tuyển dụng không còn khả dụng.');
    $saved=sr_one('SELECT job_id FROM saved_jobs WHERE user_id=? AND job_id=?',[$u['id'],$id]);
    if ($saved) db()->prepare('DELETE FROM saved_jobs WHERE user_id=? AND job_id=?')->execute([$u['id'],$id]);
    else db()->prepare('INSERT INTO saved_jobs(user_id,job_id) VALUES(?,?)')->execute([$u['id'],$id]);
    sr_flash($saved?'Đã bỏ lưu công việc.':'Đã lưu công việc.','success');
    $return=(string)($_POST['return_page']??'job');
    sr_redirect(in_array($return,['home','saved','job'],true)?$return:'job',$return==='job'?['id'=>$id]:[]);
 case 'apply':
    $u=sr_auth('candidate');$id=(int)($_POST['job_id']??0);
    $job=sr_one("SELECT * FROM jobs WHERE id=? AND status='approved'",[$id]);
    if (!$job) throw new RuntimeException('Tin tuyển dụng không còn khả dụng.');
    if (sr_one('SELECT id FROM applications WHERE job_id=? AND candidate_id=?',[$id,$u['id']])) throw new RuntimeException('Bạn đã ứng tuyển vị trí này rồi.');
    $cv=sr_one('SELECT * FROM resumes WHERE user_id=?',[$u['id']]);
    if (!$cv) throw new RuntimeException('Hãy tải CV lên trước khi ứng tuyển.');
    $match=sr_ai('/match',['resume_text'=>sr_resume_match_text($cv),'job_text'=>$job['title']."\n".$job['description']."\n".$job['skills'],'required_skills'=>$job['skills']],18);
    db()->prepare('INSERT INTO applications(job_id,candidate_id,score,semantic_score,skill_score,matched_skills,missing_skills) VALUES(?,?,?,?,?,?,?)')->execute([
       $id,$u['id'],$match['score']??null,$match['semantic_score']??null,$match['skill_score']??null,
       implode(', ',(array)($match['matched_skills']??[])),implode(', ',(array)($match['missing_skills']??[]))]);
    $newApplicationId=(int)db()->lastInsertId();
    sr_notice((int)$job['recruiter_id'],$u['name'].' vừa ứng tuyển vị trí '.$job['title'].'.',sr_url('applicants',['job_id'=>$id]));
    sr_email_event((int)$job['recruiter_id'],'email_application','Ứng viên mới: '.$job['title'],
      $u['name'].' vừa ứng tuyển vào vị trí '.$job['title'].'. Hãy đăng nhập để xem CV và hồ sơ ứng viên.',
      'application.new.'.$newApplicationId,'applicants',['job_id'=>$id]);
    sr_audit($u,'application.submit','application',$newApplicationId);
    sr_flash($match?'Đã ứng tuyển thành công. AI đã phân tích mức độ phù hợp.':'Đã ứng tuyển thành công. AI đang không khả dụng nên chưa có điểm so khớp.','success');sr_redirect('applications');
 case 'application_status':
    $u=sr_auth('recruiter');$id=(int)($_POST['id']??0);$st=(string)($_POST['status']??'');
    if (!in_array($st,['submitted','reviewing','shortlisted','rejected'],true)) throw new RuntimeException('Trạng thái không hợp lệ.');
    $row=sr_one('SELECT a.*,j.title,j.recruiter_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.recruiter_id=?',[$id,$u['id']]);
    if (!$row) throw new RuntimeException('Không tìm thấy đơn ứng tuyển thuộc tài khoản của bạn.');
    db()->prepare('UPDATE applications SET status=? WHERE id=?')->execute([$st,$id]);
    if ($row['status']!==$st) sr_notice((int)$row['candidate_id'],'Đơn ứng tuyển "'.$row['title'].'" được cập nhật: '.sr_status($st).'.',sr_url('applications'));
    if ($row['status']!==$st) sr_email_event((int)$row['candidate_id'],'email_status','Cập nhật đơn ứng tuyển: '.$row['title'],
      'Hồ sơ ứng tuyển vị trí '.$row['title'].' được chuyển sang trạng thái: '.sr_status($st).'.',
      'application.status.'.$id.'.'.$st.'.'.bin2hex(random_bytes(5)),'applications');
    sr_audit($u,'application.'.$st,'application',$id);
    sr_flash('Đã cập nhật ứng viên.','success');sr_redirect('applicants',['job_id'=>$row['job_id']]);
 case 'application_note':
    $u=sr_auth('recruiter');$id=(int)($_POST['id']??0);
    $row=sr_one('SELECT a.id,a.job_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.recruiter_id=?',[$id,$u['id']]);
    if (!$row) throw new RuntimeException('Bạn không có quyền ghi chú ứng viên này.');
    $note=mb_substr(trim((string)($_POST['note']??'')),0,5000);
    db()->prepare('INSERT INTO application_notes(application_id,note) VALUES(?,?) ON DUPLICATE KEY UPDATE note=VALUES(note)')->execute([$id,$note]);
    sr_flash('Đã lưu ghi chú nội bộ.','success');sr_redirect('applicant',['id'=>$id]);
 case 'interview_save':
    $u=sr_auth('recruiter');$appId=(int)($_POST['application_id']??0);
    $row=sr_one('SELECT a.id,a.candidate_id,j.title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.recruiter_id=?',[$appId,$u['id']]);
    if (!$row) throw new RuntimeException('Không tìm thấy ứng viên thuộc tin tuyển dụng của bạn.');
    $date=trim((string)($_POST['scheduled_at']??''));$meeting=trim((string)($_POST['meeting_url']??''));
    $location=mb_substr(trim((string)($_POST['location']??'')),0,255);
    $status=(string)($_POST['status']??'scheduled');
    if (!in_array($status,['scheduled','completed','cancelled'],true)) throw new RuntimeException('Trạng thái lịch phỏng vấn không hợp lệ.');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date);
    if (!$parsed || $parsed->format('Y-m-d\TH:i')!==$date) throw new RuntimeException('Vui lòng nhập ngày giờ phỏng vấn hợp lệ.');
    if (!sr_validate_url($meeting)) throw new RuntimeException('Liên kết họp phải bắt đầu bằng https:// hoặc http://.');
    if ($status==='scheduled' && $location==='' && $meeting==='') throw new RuntimeException('Cần có địa điểm hoặc đường dẫn phỏng vấn.');
    $note=mb_substr(trim((string)($_POST['note']??'')),0,3000);
    $previous=sr_one('SELECT scheduled_at,location,meeting_url,status FROM interviews WHERE application_id=?',[$appId]);
    db()->prepare('INSERT INTO interviews(application_id,scheduled_at,location,meeting_url,note,status) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE scheduled_at=VALUES(scheduled_at),location=VALUES(location),meeting_url=VALUES(meeting_url),note=VALUES(note),status=VALUES(status)')
      ->execute([$appId,$parsed->format('Y-m-d H:i:s'),$location,$meeting,$note,$status]);
    $interviewChanged=!$previous || ($previous['scheduled_at']!==$parsed->format('Y-m-d H:i:s') || $previous['location']!==$location || $previous['meeting_url']!==$meeting || $previous['status']!==$status);
    if ($previous && $interviewChanged) {
        // An earlier confirmation cannot be assumed valid for a changed appointment.
        db()->prepare('DELETE FROM interview_responses WHERE application_id=?')->execute([$appId]);
    }
    if ($interviewChanged) sr_notice((int)$row['candidate_id'],'Lịch phỏng vấn cho "'.$row['title'].'" đã cập nhật: '.($status==='scheduled'?$parsed->format('d/m/Y H:i'):$status).'.',sr_url('applications'));
    $details=$status==='scheduled'?'Thời gian: '.$parsed->format('d/m/Y H:i').'. '.($location?'Địa điểm: '.$location.'. ':'').($meeting?'Link: '.$meeting.'.':''):'Trạng thái: '.$status.'.';
    if ($interviewChanged) sr_email_event((int)$row['candidate_id'],'email_interview',
      ($status==='scheduled'?'Thư mời phỏng vấn: ':'Cập nhật lịch phỏng vấn: ').$row['title'],
      'Lịch phỏng vấn vị trí '.$row['title'].' đã được cập nhật. '.$details.' Vui lòng đăng nhập để xác nhận hoặc đề nghị đổi lịch.',
      'interview.'.$appId.'.'.bin2hex(random_bytes(7)),'applications');
    sr_audit($u,'interview.'.$status,'application',$appId);
    sr_flash('Đã lưu lịch phỏng vấn và gửi thông báo cho ứng viên.','success');sr_redirect('applicant',['id'=>$appId]);
 case 'interview_response':
    $u=sr_auth('candidate');$id=(int)($_POST['application_id']??0);
    $reply=(string)($_POST['response']??'');$note=mb_substr(trim((string)($_POST['response_note']??'')),0,1000);
    if (!in_array($reply,['accepted','reschedule'],true)) throw new RuntimeException('Phản hồi không hợp lệ.');
    if ($reply==='reschedule' && mb_strlen($note)<5) throw new RuntimeException('Vui lòng ghi lý do hoặc thời gian đề nghị đổi lịch (ít nhất 5 ký tự).');
    $row=sr_one('SELECT a.id,j.title,j.recruiter_id,i.status AS interview_status,i.scheduled_at FROM applications a JOIN jobs j ON j.id=a.job_id JOIN interviews i ON i.application_id=a.id WHERE a.id=? AND a.candidate_id=?',[$id,$u['id']]);
    if (!$row || $row['interview_status']!=='scheduled') throw new RuntimeException('Không có lịch phỏng vấn đang hiệu lực.');
    db()->prepare('INSERT INTO interview_responses(application_id,response,note) VALUES(?,?,?) ON DUPLICATE KEY UPDATE response=VALUES(response),note=VALUES(note),responded_at=NOW()')
       ->execute([$id,$reply,$note]);
    $meaning=$reply==='accepted'?'xác nhận tham gia':'đề nghị đổi lịch';
    sr_notice((int)$row['recruiter_id'],$u['name'].' đã '.$meaning.' phỏng vấn vị trí '.$row['title'].'.',sr_url('applicant',['id'=>$id]));
    sr_email_event((int)$row['recruiter_id'],'email_interview','Phản hồi phỏng vấn: '.$row['title'],
       'Ứng viên '.$u['name'].' đã '.$meaning.' vị trí '.$row['title'].'. '.($note!==''?'Ghi chú: '.$note:''),
       'interview.response.'.$id.'.'.hash('sha256',$reply.'|'.$note.'|'.$row['scheduled_at']),'applicant',['id'=>$id]);
    sr_audit($u,'interview.response.'.$reply,'application',$id);
    sr_flash('Đã gửi phản hồi cho nhà tuyển dụng.','success');sr_redirect('applications');
 case 'user_toggle':
    $u=sr_auth('admin');$id=(int)($_POST['id']??0);$active=(int)($_POST['active']??-1);
    $target=sr_one('SELECT id,role FROM users WHERE id=?',[$id]);
    if (!$target || $target['role']==='admin' || !in_array($active,[0,1],true)) throw new RuntimeException('Không thể thay đổi tài khoản này.');
    db()->prepare('INSERT INTO user_flags(user_id,is_active) VALUES(?,?) ON DUPLICATE KEY UPDATE is_active=VALUES(is_active)')->execute([$id,$active]);
    sr_audit($u,'user.'.($active?'activate':'deactivate'),'user',$id);
    sr_flash('Đã cập nhật trạng thái tài khoản.','success');sr_redirect('admin_users');
 case 'notification_read':
    $u=sr_auth();
    if (($_POST['all']??'')==='1') db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$u['id']]);
    else db()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int)($_POST['id']??0),$u['id']]);
    sr_redirect('notifications');
 default:throw new RuntimeException('Thao tác không được hỗ trợ.');
 }
} catch (Throwable $e) {
    if (!($e instanceof RuntimeException)) error_log('SmartRecruit: '.$e->getMessage());
    sr_flash($e instanceof RuntimeException ? $e->getMessage() : 'Có lỗi hệ thống. Kiểm tra dữ liệu nhập hoặc log PHP.','danger');
    // Chỉ điều hướng về trang cho phép, không dùng HTTP_REFERER.
    $back=(string)($_POST['back']??'dashboard');
    if (!in_array($back,['login','register','verify_pending','profile','resume','myjobs','job_form','job','applications','applicants','applicant','admin_jobs','admin_users','saved','notifications'],true)) $back='dashboard';
    $id=(int)($_POST['back_id']??0);sr_redirect($back,$id>0?['id'=>$id]:[]);
}
