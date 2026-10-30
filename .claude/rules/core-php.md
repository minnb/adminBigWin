---
paths:
  - "src/webapp/**/*.php"
---
# PHP lõi

- PHP thuần: không Composer, không framework, không thư viện ngoài. Dùng ext có sẵn (PDO, sodium).
- `declare(strict_types=1);` ở đầu tệp như code hiện tại.
- `require`/`require_once` luôn dạng `__DIR__ . '/...'` theo quan hệ thư mục: `admin|api|tools` → `../lib/`, `lib/bootstrap.php` → `../config.php`.
- Cấu hình đọc qua `cfg('key')`; không đọc/sửa `config.php` (chứa bí mật) — thêm khoá mới vào `config.sample.php`.
- Lỗi cấu hình ném `LoiCauHinh`; ghi log bằng `ghi_loi()`. Đặt tên hàm/biến tiếng Việt không dấu như code hiện có.
