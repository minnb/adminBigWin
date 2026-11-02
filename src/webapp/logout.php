<?php
declare(strict_types=1);

// Đăng xuất web BigWIN — chỉ POST + CSRF (link GET không được đăng xuất người khác hộ). Thu hồi phiên trong sổ phiên.

require __DIR__ . '/lib/auth.php';

PhienBaoMat::batDau();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && Csrf::nguonHopLe($_SERVER)
    && Csrf::hopLe(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) {
    dang_xuat();
    chuyen_huong('login.php');
}
chuyen_huong('app/');
