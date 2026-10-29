<?php
// Copy thành config.php rồi sửa. config.php KHÔNG commit (đã có trong .gitignore).
return [
    'db_dsn'  => 'mysql:host=127.0.0.1;dbname=bigwin_license;charset=utf8mb4',
    'db_user' => 'root',
    'db_pass' => '',

    // Khoá bí mật Ed25519 — BẮT BUỘC nằm NGOÀI thư mục web (htdocs/public_html).
    // Sinh bằng: php tools/keygen.php
    'secret_key_file' => 'C:/xampp/bigwin_keys/license_secret.key',

    // Khoá secretbox mã hoá bản backup trên server — NGOÀI webroot, mất file = mất mọi bản backup.
    // Sinh bằng: php tools/keygen.php (cùng lúc với khoá ký). SAO LƯU cùng khoá ký.
    'backup_key_file' => 'C:/xampp/bigwin_keys/backup_secretbox.key',

    // Số giây client được chạy offline sau lần kiểm tra online gần nhất.
    'offline_ttl' => 7 * 86400,

    // Máy mới chưa duyệt vẫn được dùng thử bấy nhiêu giây kể từ lần đầu API lưu máy.
    'trial_ttl' => 7 * 86400,

    // Giới hạn số request /api/check.php mỗi IP mỗi phút.
    'rate_limit_per_min' => 30,

    // Số ngày giữ check_logs (nhật ký gọi API). Dọn ngẫu nhiên trong api/check.php.
    'log_luu_ngay' => 90,

    // Khoá công khai nhúng trong app (core/license_service.py::LICENSE_PUBLIC_KEY_B64). Trang admin so với
    // khoá ký ở trên — lệch (lỡ sinh khoá mới) thì cảnh báo ngay, vì mọi app sẽ từ chối server.
    'app_public_key' => 'gUaiauzt/UejLDWom4yjz/VJc7lJGe9FdymVxKXNc+E=',

    // true = trang lỗi hiện nguyên văn lỗi PHP/MySQL cả khi CHƯA đăng nhập (trang đăng nhập là công khai).
    // Chỉ bật tạm lúc cài đặt, xong TẮT. Admin đã đăng nhập luôn thấy chi tiết.
    'debug' => false,
];
