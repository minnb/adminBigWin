# CLAUDE.md

## Cấu trúc

Toàn bộ ứng dụng web PHP (không framework, không Composer) nằm trong `src/webapp/`:

- `admin/` — trang quản trị (đăng nhập, `_auth.php` chứa layout + `require_login()`).
- `api/` — endpoint JSON cho app desktop BigWIN (`check.php`, `backup_*.php`, `usage_push.php`). URL công khai là `/api/...` — KHÔNG đổi, app đã phát hành gọi cố định.
- `lib/` — `bootstrap.php` (nạp `../config.php`, `cfg()`, DB), `api.php`, `crypto.php`.
- `tools/` — script CLI; `migrations/`, `schema.sql` — SQL.
- `config.php` — không commit; khoá ký/backup ở đường dẫn tuyệt đối ngoài repo.

Gốc repo chỉ chứa tệp cấp repository. `.htaccess` gốc rewrite nội bộ mọi request vào `src/webapp/`; production nên trỏ document root thẳng vào `src/webapp/`.

Mọi `require` dùng `__DIR__` tương đối trong `src/webapp/` — giữ nguyên quan hệ thư mục giữa `admin`, `api`, `lib`, `tools` và `config.php`.
