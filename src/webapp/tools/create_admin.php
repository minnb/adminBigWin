<?php
declare(strict_types=1);

// Tạo / đặt lại tài khoản đăng nhập web (bảng users):
//   php tools/create_admin.php <username> <password> [--role=admin|user] [--phai-doi-mat-khau]
// Mặc định --role=admin. Chạy lại cùng username = đặt lại mật khẩu, quyền và cờ buộc đổi mật khẩu.
// Admin được ghi kèm bảng admins (dong_bo_admins) — cửa sổ rollback admin/_auth.php cũ (P03 QĐ3), bỏ khi hết cửa sổ (P01-B07).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../lib/auth.php';

$viTri = [];
$role = 'admin';
$phaiDoi = 0;
$loi = '';
foreach (array_slice($argv, 1) as $ts) {
    if (str_starts_with($ts, '--role=')) {
        $role = substr($ts, 7);
    } elseif ($ts === '--phai-doi-mat-khau') {
        $phaiDoi = 1;
    } elseif (str_starts_with($ts, '--')) {
        $loi = "Co khong ho tro: $ts\n";
    } else {
        $viTri[] = $ts;
    }
}
[$user, $pass] = array_pad($viTri, 2, '');
if ($loi !== '' || count($viTri) > 2 || $user === '' || mb_strlen($pass) < MAT_KHAU_TOI_THIEU || !in_array($role, ['admin', 'user'], true)) {
    fwrite(STDERR, $loi . "Dung: php tools/create_admin.php <username> <password (>=" . MAT_KHAU_TOI_THIEU . " ky tu)> [--role=admin|user] [--phai-doi-mat-khau]\n");
    exit(1);
}

$hash = bam_mat_khau($pass);
$pdo = db();
$pdo->beginTransaction();
$pdo->prepare(
    'INSERT INTO users (username, password_hash, role, phai_doi_mat_khau, created_at) VALUES (?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role), phai_doi_mat_khau = VALUES(phai_doi_mat_khau)'
)->execute([$user, $hash, $role, $phaiDoi]);
dong_bo_admins($user, $role === 'admin' ? $hash : null); // QĐ3 P03: cửa sổ rollback admin/_auth.php cũ
$pdo->commit();

echo "OK: $role '$user'" . ($phaiDoi ? ' (buoc doi mat khau lan dang nhap dau)' : '') . "\n";
