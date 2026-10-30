---
description: Kiểm tra cú pháp PHP và quan hệ đường dẫn của src/webapp
---
Chạy kiểm tra sức khoẻ dự án, KHÔNG sửa tệp nào, KHÔNG đọc `src/webapp/config.php`:

1. Chạy `php -l` cho mọi tệp `.php` trong `src/webapp/`; liệt kê tệp lỗi.
2. Kiểm tra `.htaccess` gốc vẫn rewrite nội bộ vào `src/webapp/` và chặn `/.git`; `src/webapp/.htaccess` vẫn chặn `config.php`, `*.sql`, `lib|tools|migrations`.
3. Grep mọi `require|include` trong `src/webapp/`: mỗi đường dẫn phải dạng `__DIR__ . '...'` và tệp đích phải tồn tại (giải theo vị trí tệp nguồn). `config.php` chỉ báo có/không tồn tại.
4. Báo cáo bảng: hạng mục · OK/LỖI · chi tiết.
