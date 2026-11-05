<?php
// php bin/mail_worker.php --once | --loop   ; executes only from CLI.
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$loop=in_array('--loop',$argv,true);
do {
    if (sr_mail_ready()) {
        // Recover claims after worker/container crashes. Resend idempotency key avoids quick duplicates.
        db()->exec("UPDATE email_outbox SET status='pending',next_attempt_at=NOW() WHERE status='sending' AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND attempts<5");
        $jobs=sr_all("SELECT id FROM email_outbox WHERE status='pending' AND next_attempt_at<=NOW() AND attempts<5 ORDER BY id ASC LIMIT 30");
        foreach($jobs as $j) sr_mail_dispatch((int)$j['id']);
    }
    if ($loop) sleep(30);
} while ($loop);
