<?php
declare(strict_types=1);

// P03 bước 9: đăng xuất dùng chung phiên web (POST + CSRF + nguồn) — như /logout.php. GET → về trang đăng nhập.
// Rollback: khôi phục admin/logout.php từ commit c2044c0.

require __DIR__ . '/../lib/auth.php';

PhienBaoMat::batDau(); // token CSRF nằm trong phiên
$token = $_POST['csrf'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && Csrf::nguonHopLe($_SERVER) && Csrf::hopLe(is_string($token) ? $token : null)) {
    dang_xuat();
}
chuyen_huong('login.php');
