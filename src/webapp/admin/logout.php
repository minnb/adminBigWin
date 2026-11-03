<?php
declare(strict_types=1);

// P03 bước 9: đăng xuất dùng chung phiên web (POST + CSRF + nguồn) — như /logout.php. GET → về trang đăng nhập.
// Rollback: khôi phục admin/logout.php từ commit c2044c0.

require __DIR__ . '/../lib/auth.php';

dang_xuat_tu_form('login.php');
