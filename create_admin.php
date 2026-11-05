<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/app/bootstrap.php';
$email = strtolower(trim($argv[1] ?? ''));
$password = $argv[2] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    exit("Usage: php create_admin.php admin@example.com MatKhauToiThieu10KyTu\n");
}
$stmt = db()->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');
$stmt->execute(['Administrator', $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
echo "Admin created.\n";
