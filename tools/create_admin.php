<?php
declare(strict_types=1);

// Tạo / đổi mật khẩu admin: php tools/create_admin.php <username> <password>

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../lib/bootstrap.php';

[$_, $user, $pass] = array_pad($argv, 3, '');
if ($user === '' || strlen($pass) < 8) {
    fwrite(STDERR, "Dung: php tools/create_admin.php <username> <password (>=8 ky tu)>\n");
    exit(1);
}

db()->prepare(
    'INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
)->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);

echo "OK: admin '$user'\n";
