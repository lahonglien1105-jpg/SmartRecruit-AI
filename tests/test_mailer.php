<?php
// No network and no database. Smoke-test link generation and HTML escaping.
declare(strict_types=1);
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function sr_url(string $page,array $args=[]):string{return 'index.php?'.http_build_query(['page'=>$page]+$args);}
require dirname(__DIR__).'/app/mailer.php';
putenv('APP_URL=https://demo.example');
$url=sr_public_url('verify_email',['uid'=>42,'token'=>'safe']);
if ($url!=='https://demo.example/index.php?page=verify_email&uid=42&token=safe') throw new RuntimeException('bad url');
$html=sr_email_html('Xác minh','<p>'.h('<script>alert(1)</script>').'</p>');
if(strpos($html,'<script>')!==false || strpos($html,'&lt;script&gt;')===false) throw new RuntimeException('escaping failed');
if(sr_mail_ready())throw new RuntimeException('unexpected API key');
echo "Email helper smoke OK\n";
