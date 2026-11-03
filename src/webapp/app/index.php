<?php
declare(strict_types=1);

// app/ → page mặc định (P03 S1.6). Kiểm đăng nhập TRƯỚC khi chuyển (chưa đăng nhập → login, còn buộc đổi mật khẩu →
// doi_mat_khau.php) để không lộ cấu trúc app.

require __DIR__ . '/../lib/auth.php';

require_user();
chuyen_huong('app/nhap_tin.php');
