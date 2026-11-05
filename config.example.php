<?php
// local: cp config.example.php config.php ; production: Railway variables (never commit credentials).
declare(strict_types=1);
$host=getenv('DB_HOST')?:'127.0.0.1';
$port=getenv('DB_PORT')?:'3307';
$name=getenv('DB_NAME')?:'smartrecruit';
$user=getenv('DB_USER')?:'root';
$pass=getenv('DB_PASS')?:'';
define('AI_URL',getenv('AI_URL')?:'http://127.0.0.1:8000');
try {
  $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[
     PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES=>false,
     PDO::ATTR_TIMEOUT=>5,
  ]);
} catch(PDOException $e) {
  error_log('SmartRecruit MySQL connection error: '.$e->getCode());
  http_response_code(503);
  exit('Không thể kết nối MySQL. Kiểm tra biến môi trường và cổng database.');
}
