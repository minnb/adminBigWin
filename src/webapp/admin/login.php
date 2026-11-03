<?php
declare(strict_types=1);

// P03 bước 9: admin đăng nhập ở trang CHUNG /login.php (bảng users, role admin). Giữ tệp này để link / bookmark cũ vẫn
// dẫn đúng chỗ. Tạo tài khoản admin: php tools/create_admin.php <username> <password> (P01-B08).
// Rollback: khôi phục admin/login.php từ commit c2044c0.

require __DIR__ . '/../lib/auth.php';

chuyen_huong('login.php');
