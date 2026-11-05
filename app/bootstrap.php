<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (!is_file($root . '/config.php')) {
    http_response_code(503);
    exit('Chưa có config.php. Hãy sao chép config.example.php thành config.php và điền cấu hình MySQL.');
}
require_once $root . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly','1');
    ini_set('session.cookie_samesite','Lax');
    ini_set('session.use_strict_mode','1');
    if (str_starts_with((string)(getenv('APP_URL')?:''),'https://')) ini_set('session.cookie_secure','1');
    session_start();
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500); exit('Kết nối database chưa được khởi tạo. Kiểm tra config.php.');
}
// Compatibility: file config.php trong bản v1 đã khai báo các hàm h(), me(), csrf()...
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('me')) { function me(): ?array { return $_SESSION['user'] ?? null; } }
if (!function_exists('csrf')) { function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; } }
if (!function_exists('flash')) { function flash(string $message): void { $_SESSION['flash'] = $message; } }
if (!function_exists('go')) { function go(string $page): void { header('Location: index.php?page=' . rawurlencode($page)); exit; } }
function db(): PDO { global $pdo; return $pdo; }
function sr_current(): ?array { return $_SESSION['user'] ?? null; }
function sr_auth(?string $role = null): array {
    $user = sr_current();
    if (!$user) { header('Location: index.php?page=login'); exit; }
    if ($role !== null && $user['role'] !== $role) { http_response_code(403); exit('Bạn không có quyền truy cập chức năng này.'); }
    return $user;
}
function sr_csrf(): void {
    if (!hash_equals((string)csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Phiên biểu mẫu đã hết hạn. Tải lại trang rồi thử lại.');
    }
}
function sr_token(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function sr_url(string $page, array $params = []): string { return 'index.php?' . http_build_query(['page'=>$page] + $params); }
function sr_redirect(string $page, array $params = []): void { header('Location: ' . sr_url($page,$params)); exit; }
function sr_flash(string $message, string $type = 'info'): void { $_SESSION['sr_flash'] = ['message'=>$message,'type'=>$type]; }
function sr_one(string $sql, array $args = []): ?array {
    $s = db()->prepare($sql); $s->execute($args); $v = $s->fetch(PDO::FETCH_ASSOC); return $v === false ? null : $v;
}
function sr_all(string $sql, array $args = []): array { $s=db()->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC); }
function sr_count(string $sql,array $args=[]): int {$s=db()->prepare($sql);$s->execute($args);return (int)$s->fetchColumn();}
function sr_date($d): string {if (!$d) return '—';return date('d/m/Y',strtotime((string)$d));}
function sr_snip($value, int $length = 140): string {
    $value = trim(preg_replace('/\s+/u',' ',(string)$value) ?: (string)$value);
    return mb_strlen($value) > $length ? mb_substr($value,0,$length).'…' : $value;
}
function sr_skills(?string $value): array {return array_values(array_unique(array_filter(array_map('trim',preg_split('/[,;\n]+/u',(string)$value) ?: []))));}
function sr_initials(string $v):string {return mb_strtoupper(mb_substr(trim($v) ?: 'S',0,1));}
function sr_status(string $value): string {
    $labels = ['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối','submitted'=>'Mới ứng tuyển',
       'reviewing'=>'Đang xem xét','shortlisted'=>'Vào danh sách chọn','full_time'=>'Toàn thời gian','part_time'=>'Bán thời gian',
       'internship'=>'Thực tập','contract'=>'Hợp đồng','onsite'=>'Tại văn phòng','hybrid'=>'Linh hoạt','remote'=>'Từ xa',
       'intern'=>'Thực tập','junior'=>'Junior','mid'=>'Middle','senior'=>'Senior'];
    return $labels[$value] ?? $value;
}
function sr_state_class(string $v): string {return in_array($v,['approved','shortlisted'],true)?'success':(in_array($v,['rejected'],true)?'danger':(in_array($v,['pending','submitted'],true)?'warning':'neutral'));}
function sr_is_active(int $id): bool { $v=sr_one('SELECT is_active FROM user_flags WHERE user_id=?',[$id]);return !$v || (int)$v['is_active']===1; }
function sr_notice(int $userId,string $message,string $url='index.php?page=notifications'):void {
    db()->prepare('INSERT INTO notifications(user_id,content,url) VALUES(?,?,?)')->execute([$userId,mb_substr($message,0,255),$url]);
}
function sr_ai(string $endpoint, array $payload, int $timeout = 20): ?array {
    if (!extension_loaded('curl')) return null;
    $base = defined('AI_URL') ? AI_URL : 'http://127.0.0.1:8000';
    $ch = curl_init(rtrim($base,'/').$endpoint);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
       CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
       CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),
       CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>$timeout]);
    $response=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if ($http !== 200 || !is_string($response)) return null;
    $data=json_decode($response,true);return is_array($data)?$data:null;
}
function sr_apply_count(int $jobId): int {return sr_count('SELECT COUNT(*) FROM applications WHERE job_id=?',[$jobId]);}
function sr_job_owned(int $jobId,int $userId): ?array {return sr_one('SELECT * FROM jobs WHERE id=? AND recruiter_id=?',[$jobId,$userId]);}
function sr_validate_url(string $input): bool {return $input==='' || (bool)filter_var($input,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($input,PHP_URL_SCHEME)),['https','http'],true);}

// Non-sensitive audit log; never store CV text, passwords or notes.
function sr_audit(array $actor,string $action,string $entity,int $id):void {
    db()->prepare('INSERT INTO audit_events(actor_id,action,entity_type,entity_id) VALUES(?,?,?,?)')
       ->execute([(int)$actor['id'],mb_substr($action,0,70),mb_substr($entity,0,40),$id]);
}
function sr_resume_match_text(array $cv):string {
    // User-confirmed skills are unverified self-reported claims, included transparently.
    $manual=json_decode((string)($cv['parsed_json']??''),true);
    $text=(string)($cv['raw_text']??'');
    if (is_array($manual) && !empty($manual['manual_review']['skills_confirmed'])) {
        $text.="\nKỹ năng ứng viên tự xác nhận: ".(string)($cv['skills']??'');
    }
    return $text;
}

require_once __DIR__ . '/mailer.php';
