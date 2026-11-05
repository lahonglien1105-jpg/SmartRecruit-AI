<?php
/** SmartRecruit email events via Resend HTTPS. No SMTP connectivity required. */
declare(strict_types=1);
function sr_env(string $key,string $default=''):string {
    $v=getenv($key);return $v===false?$default:(string)$v;
}
function sr_env_yes(string $key,bool $fallback=false):bool {
    $v=sr_env($key);return $v===''?$fallback:in_array(strtolower($v),['1','yes','true','on'],true);
}
function sr_mail_ready():bool {
    return sr_env('RESEND_API_KEY')!=='' && filter_var(sr_env('MAIL_FROM'),FILTER_VALIDATE_EMAIL)!==false;
}
function sr_public_url(string $page,array $params=[]):string {
    $base=trim(sr_env('APP_URL'));
    if ($base==='') {
        $railway=sr_env('RAILWAY_PUBLIC_DOMAIN');
        $base=$railway!==''?'https://'.$railway:'http://localhost/SmartRecruit';
    }
    if (!preg_match('~^https?://~i',$base)) throw new RuntimeException('APP_URL cần là URL http(s).');
    return rtrim($base,'/').'/'.sr_url($page,$params);
}
function sr_email_html(string $title,string $body,string $ctaUrl='',string $ctaText='Mở SmartRecruit'):string {
    $header='<div style="max-width:600px;margin:auto;padding:32px;font-family:Arial,sans-serif;color:#172844;line-height:1.7">'
      .'<h2 style="margin:0 0 18px;color:#235ee8">SmartRecruit AI</h2><h1 style="font-size:22px">'.h($title).'</h1>';
    $button=$ctaUrl!==''?'<p style="margin-top:24px"><a href="'.h($ctaUrl).'" style="display:inline-block;background:#245ee8;color:white;text-decoration:none;border-radius:9px;padding:12px 22px">'.h($ctaText).'</a></p>':'';
    return '<!doctype html><html lang="vi"><head><meta charset="utf-8"></head><body style="background:#f5f7fc">'.$header.$body.$button
       .'<hr style="border:0;border-top:1px solid #e6ecf5;margin:26px 0"><small>Email giao dịch tự động từ SmartRecruit AI. Vui lòng không chia sẻ liên kết xác minh.</small></div></body></html>';
}
function sr_email_verified(int $userId):bool {
    $record=sr_one('SELECT verified_at FROM user_emails WHERE user_id=?',[$userId]);
    return $record && $record['verified_at']!==null;
}
function sr_mail_enqueue(?int $uid,string $to,string $subject,string $html,string $text,string $event):?int {
    if (!filter_var($to,FILTER_VALIDATE_EMAIL)) return null;
    $key=substr($event,0,140);
    $sql='INSERT INTO email_outbox(user_id,recipient_email,subject,body_html,body_text,event_key) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)';
    db()->prepare($sql)->execute([$uid,strtolower($to),mb_substr($subject,0,240),$html,$text,$key]);
    $id=(int)db()->lastInsertId();
    if (sr_mail_ready()) sr_mail_dispatch($id); // Event persisted; failure remains retryable.
    return $id;
}
function sr_mail_dispatch(int $id):bool {
    if (!sr_mail_ready() || !extension_loaded('curl')) return false;
    $claim=db()->prepare("UPDATE email_outbox SET status='sending',attempts=attempts+1 WHERE id=? AND status='pending' AND next_attempt_at<=NOW() AND attempts<5");
    $claim->execute([$id]);if(!$claim->rowCount())return false;
    $job=sr_one('SELECT * FROM email_outbox WHERE id=?',[$id]);if(!$job)return false;
    $payload=['from'=>sr_env('MAIL_FROM_NAME','SmartRecruit AI').' <'.sr_env('MAIL_FROM').'>',
      'to'=>[$job['recipient_email']],'subject'=>$job['subject'],'html'=>$job['body_html'],'text'=>$job['body_text']?:'Thông báo SmartRecruit AI'];
    $ch=curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_HTTPHEADER=>['Authorization: Bearer '.sr_env('RESEND_API_KEY'),'Content-Type: application/json','User-Agent: SmartRecruitAI/5.0','Idempotency-Key: smartrecruit-'.$id],
      CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
      CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>10]);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $curlError=curl_error($ch);curl_close($ch);
    $result=is_string($raw)?json_decode($raw,true):null;
    if ($status>=200 && $status<300 && is_array($result) && isset($result['id'])) {
        db()->prepare("UPDATE email_outbox SET status='sent',provider_message_id=?,sent_at=NOW(),last_error=NULL,body_html='',body_text='' WHERE id=?")
          ->execute([substr((string)$result['id'],0,128),$id]);
        return true;
    }
    $detail=is_array($result)?(string)($result['message']??$result['error']??'API lỗi'):($curlError?:'Không nhận được phản hồi');
    $error=mb_substr('HTTP '.$status.' '.$detail,0,340);
    $wait=min(3600,60*(2**min(5,(int)$job['attempts'])));
    $newStatus=(int)$job['attempts']>=5?'failed':'pending';
    db()->prepare('UPDATE email_outbox SET status=?,next_attempt_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_error=? WHERE id=?')
      ->execute([$newStatus,$wait,$error,$id]);
    error_log('SmartRecruit mail '.$id.' failed: '.$error);
    return false;
}
function sr_email_event(int $uid,string $pref,string $subject,string $paragraph,string $event,string $page='notifications',array $args=[]):void {
    if(!sr_mail_ready() || !sr_email_verified($uid))return;
    $allowed=['email_application','email_status','email_interview'];
    if(!in_array($pref,$allowed,true))return;
    $p=sr_one('SELECT '.$pref.' AS val FROM notification_preferences WHERE user_id=?',[$uid]);
    if ($p && (int)$p['val']!==1)return;
    $account=sr_one('SELECT name,email FROM users WHERE id=?',[$uid]);if(!$account)return;
    $url=sr_public_url($page,$args);
    $safe='<p>Xin chào '.h($account['name']).',</p><p>'.h($paragraph).'</p>';
    $html=sr_email_html($subject,$safe,$url,'Xem chi tiết');
    sr_mail_enqueue($uid,$account['email'],$subject,$html,$paragraph,$event);
}
function sr_send_verification(int $uid,string $email,string $name,bool $throttle=true):bool {
    if (!sr_mail_ready()) return false;
    $row=sr_one('SELECT verified_at,last_sent_at, (last_sent_at IS NULL OR DATE_ADD(last_sent_at, INTERVAL 90 SECOND)<=NOW()) AS can_resend FROM user_emails WHERE user_id=?',[$uid]);
    if ($row && $row['verified_at']!==null)return false;
    if ($throttle && $row && (int)$row['can_resend']!==1)return false;
    // Retire queued verification links when issuing a new token.
    db()->prepare("UPDATE email_outbox SET status='failed',last_error='Đã thay thế bằng liên kết mới' WHERE event_key LIKE ? AND status='pending'")
      ->execute(['verify:'.$uid.':%']);
    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
    db()->prepare('INSERT INTO user_emails(user_id,token_hash,token_expires_at,last_sent_at) VALUES(?, ?,DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_expires_at=VALUES(token_expires_at),last_sent_at=VALUES(last_sent_at)')
        ->execute([$uid,$hash]);
    $url=sr_public_url('verify_email',['uid'=>$uid,'token'=>$token]);
    $html=sr_email_html('Xác minh email','<p>Xin chào '.h($name).',</p><p>Nhấn nút bên dưới để xác nhận địa chỉ email bạn dùng đăng ký SmartRecruit. Liên kết hết hạn sau 24 giờ.</p>',$url,'Xác minh email');
    sr_mail_enqueue($uid,$email,'Xác minh email SmartRecruit AI',$html,"Để xác minh email, mở: $url",'verify:'.$uid.':'.substr($hash,0,28));
    return true;
}
function sr_verify_token(int $uid,string $token):bool {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token) || $uid<1)return false;
    $row=sr_one('SELECT token_hash,verified_at,(token_expires_at IS NOT NULL AND token_expires_at>=NOW()) AS token_valid FROM user_emails WHERE user_id=?',[$uid]);
    if (!$row || $row['verified_at']!==null || !$row['token_hash'] || (int)$row['token_valid']!==1)return false;
    if (!hash_equals((string)$row['token_hash'],hash('sha256',$token)))return false;
    db()->prepare('UPDATE user_emails SET verified_at=NOW(),token_hash=NULL,token_expires_at=NULL WHERE user_id=?')->execute([$uid]);
    return true;
}
