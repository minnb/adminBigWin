<?php
declare(strict_types=1);

// Đăng xuất web BigWIN — chỉ POST + CSRF (link GET không được đăng xuất người khác hộ). Thu hồi phiên trong sổ phiên.

require __DIR__ . '/lib/auth.php';

dang_xuat_tu_form('app/');
