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

- **PHP thuần 100%:** PHP 8.3 + ext có sẵn (PDO, intl, sodium). KHÔNG Composer, KHÔNG framework, KHÔNG thư viện ngoài trong `src/webapp/`; không thêm `composer.json`/`vendor/`.
- **Cô lập tenant:** truy vấn bảng tenant CHỈ qua lớp `extends TenantRepo` với `TenantContext` (tự chèn `user_id`; SQL tự viết đánh dấu `{tenant:bang alias}`). Cấm viết tay `user_id = ?`, cấm nhận `$userId` từ request. Bảng dùng chung qua `SharedRepo`; `UserRepo`/`ThueBaoRepo` chỉ trong ngữ cảnh admin/hệ thống. Chi tiết: `.claude/rules/tenant-isolation.md`; khoá bằng `tenant_scan_test` + `idor_test`.
- **Golden Fixture:** lõi `lib/bigwin/` phải khớp `===` với `tests/fixtures/*.json` (sinh từ Python tại tag `port-baseline`). Fixture lệch = BUG PHP — CẤM sửa fixture. Test viết TRƯỚC (đỏ) rồi mới port; không "sửa mã cho khớp test sai" — khi nghi ngờ, lấy giá trị thật bằng cách chạy Python.
- **Xong = có bằng chứng:** chỉ báo xong khi `php src/webapp/tools/test.php` (không lọc) xanh 100% và ghi dòng tổng kết thật vào `docs/plans/phases/TASK_LOG.md` (quy trình `docs/plans/phases/EXECUTION_PIPELINE.md`). Runner cần `BIGWIN_CONFIG_FILE` trỏ tới cấu hình có `db_test_*`.
- **Git:** chủ dự án tự `git add/commit/push/tag` ở cả 2 repo — không tự chạy khi chưa có lệnh cụ thể.
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
| `security-core.md` | `src/webapp/app/**`, `lib/security/**`, `lib/auth.php`, `login.php`, `logout.php` | Phiên/CSRF/tiêu đề/rate limit chỉ qua `lib/security/`, gateway `ajax_khoi_dong()` |
| `tenant-isolation.md` | `src/webapp/app/**`, `lib/bigwin/repo/**`, `lib/bigwin/*Service.php` | `TenantContext` + `TenantRepo` tự chèn `user_id`, xoá mềm & tiền bất biến, bảng dùng chung, test IDOR |

Kế hoạch chuyển đổi Desktop → Web: `WEB_MIGRATION_PLAN.md`. Test: `php src/webapp/tools/test.php [lọc]` (fixture sinh bởi `D:\Projects\bigwin\scripts\export_golden.py`).

## Slash commands (`.claude/commands/`)

| Lệnh | Việc làm |
|---|---|
| `/healthcheck` | `php -l` toàn bộ + kiểm tra `.htaccess` và đường dẫn `require` |
| `/audit-api [ref]` | Rà `git diff` API/crypto xem có phá hợp đồng app |
| `/db-migration <mô tả>` | Tạo migration + đồng bộ `schema.sql` |
| `/phpstan [đường dẫn]` | PHPStan level 5 (`phpstan.neon`; `.phar` ngoài repo tại `~/.local/bin`, không Composer) — phân loại lỗi, không baseline/ignore |

## Harness (`.claude/settings.json`)

- Chặn đọc/sửa `src/webapp/config.php`, `git push --force`, `rm -rf`.
- Hook `PostToolUse` (Edit|Write) chạy `.claude/hooks/php-lint.php` → `php -l` tệp `.php` vừa sửa; lỗi cú pháp trả về để sửa ngay.
- Plugin `security-guidance@claude-plugins-official` (bật trong `enabledPlugins`; mỗi máy cài 1 lần bằng `/plugin install security-guidance@claude-plugins-official` — VS Code: `/plugins`; cần Python ≥ 3.10): quét mẫu mỗi lần sửa, review bảo mật cuối mỗi lượt và mỗi commit. Mô hình đe doạ dự án: `.claude/claude-security-guidance.md`; luật mẫu riêng: `.claude/security-patterns.json` (JSON — máy không có PyYAML). Plugin chỉ NHẮC, không chặn — chốt chặn cứng vẫn là test quét mã (`*_scan_test.php`).
