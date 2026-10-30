# CLAUDE.md

## Cấu trúc

Toàn bộ ứng dụng web PHP (không framework, không Composer) nằm trong `src/webapp/`:

- `admin/` — trang quản trị (đăng nhập, `_auth.php` chứa layout + `require_login()`).
- `api/` — endpoint JSON cho app desktop BigWIN (`check.php`, `backup_*.php`, `usage_push.php`). URL công khai là `/api/...` — KHÔNG đổi, app đã phát hành gọi cố định.
- `lib/` — `bootstrap.php` (nạp `../config.php`, `cfg()`, `db()`, `h()`), `api.php`, `crypto.php`.
- `tools/` — script CLI; `migrations/`, `schema.sql` — SQL.
- `config.php` — không commit, KHÔNG đọc/sửa (bí mật); khoá ký/backup ở đường dẫn tuyệt đối ngoài repo.

Gốc repo chỉ chứa tệp cấp repository. `.htaccess` gốc rewrite nội bộ mọi request vào `src/webapp/`; production nên trỏ document root thẳng vào `src/webapp/`.

Mọi `require` dùng `__DIR__` tương đối trong `src/webapp/` — giữ nguyên quan hệ thư mục giữa `admin`, `api`, `lib`, `tools` và `config.php`.

App desktop BigWIN (Python) nằm ở `D:\Projects\bigwin` — chỉ tham khảo; không đọc `*.session`, `backups/`.

## Quy tắc bắt buộc

- **Database:** mọi thay đổi cấu trúc DB phải có tệp mới trong `src/webapp/migrations/` VÀ cập nhật `src/webapp/schema.sql` (dùng `/db-migration`). Không sửa migration đã có.
- **API:** chỉ thay đổi tương thích ngược; chạy `/audit-api` trước khi commit thay đổi `api/` hoặc `lib/crypto.php`.
- **Khoá:** không chạy `tools/keygen.php` khi chưa được yêu cầu — sinh khoá mới vô hiệu hoá mọi app đã phát hành.

## Index quy tắc (`.claude/rules/`, tự nạp theo đường dẫn)

| Tệp | Áp dụng cho | Nội dung |
|---|---|---|
| `api-contract.md` | `src/webapp/api/**`, `lib/api.php` | Hợp đồng cố định với app BigWIN |
| `admin-security.md` | `src/webapp/admin/**` | `require_login()`, prepared statement, `h()` |
| `core-php.md` | `src/webapp/**/*.php` | PHP thuần, `__DIR__`, `cfg()`, quy ước đặt tên |
| `bigwin-core.md` | `src/webapp/lib/bigwin/**` | Lõi port từ Python: PURE, `bw_re`, offset ký tự, ép kiểu tiền |
| `tenant-isolation.md` | `src/webapp/app/**`, `lib/bigwin/repo/**` | `$userId` từ session, chống IDOR, bảng dùng chung |

Kế hoạch chuyển đổi Desktop → Web: `WEB_MIGRATION_PLAN.md`. Test: `php src/webapp/tools/test.php [lọc]` (fixture sinh bởi `D:\Projects\bigwin\scripts\export_golden.py`).

## Slash commands (`.claude/commands/`)

| Lệnh | Việc làm |
|---|---|
| `/healthcheck` | `php -l` toàn bộ + kiểm tra `.htaccess` và đường dẫn `require` |
| `/audit-api [ref]` | Rà `git diff` API/crypto xem có phá hợp đồng app |
| `/db-migration <mô tả>` | Tạo migration + đồng bộ `schema.sql` |

## Harness (`.claude/settings.json`)

- Chặn đọc/sửa `src/webapp/config.php`, `git push --force`, `rm -rf`.
- Hook `PostToolUse` (Edit|Write) chạy `.claude/hooks/php-lint.php` → `php -l` tệp `.php` vừa sửa; lỗi cú pháp trả về để sửa ngay.
