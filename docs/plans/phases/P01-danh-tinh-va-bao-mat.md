# P01 — Hạ tầng test · Danh tính · Core bảo mật · Đăng nhập

| Thuộc tính | Giá trị |
|---|---|
| Slice | **S1.0** (mới — hạ tầng test DB/HTTP), S1.1, S1.2, S1.3 — kế hoạch tổng §9 G1, §5.3 |
| Phụ thuộc | Không (chạy song song P00) |
| Mở khoá | P02 |
| Quyết định áp dụng | O5 (chỉ admin tạo tài khoản, lần đầu buộc đổi mật khẩu), N16 |
| Cỡ | M + S + M + M |

## 1. Mục tiêu
1. Có **hạ tầng test tích hợp** (DB test dựng lại sạch, HTTP test qua `php -S`) để mọi phase sau test được thật, không chỉ test hàm thuần.
2. Có bảng `users` thống nhất (admin cũ chuyển sang), **Core bảo mật** (`lib/security/`) và **đăng nhập chung** `login.php` + AJAX gateway `ajax_khoi_dong()`.

Kết thúc phase: đăng nhập/đăng xuất được bằng tài khoản admin cũ; một endpoint thử nghiệm qua gateway chứng minh đủ chuỗi bảo vệ.

## 2. Điều kiện bắt đầu
- [ ] MySQL/MariaDB local có quyền tạo DB `bigwin_test`.
- [ ] `config.sample.php` được phép thêm khoá mới (không đụng `config.php`).

## 3. Phạm vi — slice & tệp
| Slice | Tệp tạo/sửa | Ghi chú |
|---|---|---|
| S1.0 | `tests/_db.php`, `tests/_http.php`, `config.sample.php` (+ `db_test_*`), `tools/test.php` (báo FAIL khi thiếu `db_test`) | `_db.php`: tạo `bigwin_test`, nạp `schema.sql`, cung cấp `db_test()`, dọn bảng giữa các test. `_http.php`: bật `php -S 127.0.0.1:8765 -t src/webapp` với config trỏ DB test, client cURL có cookie jar, hàm `http_post_json()` gắn/không gắn CSRF/Origin |
| S1.1 | `migrations/2026-11-xx_users_rbac_bao_mat.sql`, `schema.sql`, `tools/create_admin.php` (`--role`, `--phai-doi-mat-khau`) | Bảng `users`, `user_login_fails`, `phien_dang_nhap`, `gioi_han_tan_suat`; copy `admins` → `users(role='admin')` giữ `password_hash`; `admins` giữ nguyên (chỉ đọc) |
| S1.2 | `lib/security/PhienBaoMat.php`, `Csrf.php`, `TieuDeBaoMat.php`, `GioiHanTanSuat.php`, `.claude/rules/security-core.md` | Theo §5.3 a, b, d; rate limit dùng APCu nếu có, không thì bảng `gioi_han_tan_suat` |
| S1.3 | `lib/auth.php`, `login.php`, `logout.php`, `lib/security/AjaxGateway.php`, `app/ajax/_ping.php` (endpoint thử nghiệm, xoá ở P03) | `require_user/require_admin`, argon2id, khoá tăng dần, đổi mật khẩu thu hồi phiên khác; gateway theo §5.3c (bước bản quyền để stub `doc` luôn qua — P02 nối thật) |

## 4. Thứ tự thực hiện
1. S1.0 → viết `tests/db_smoke_test.php`, `tests/http_smoke_test.php` (P01-T01..T03) trước, cho xanh.
2. S1.1 → migration + `schema.sql` qua `/db-migration`; test P01-T04..T07.
3. S1.2 → từng lớp kèm test đơn vị; tạo rule `security-core.md`.
4. S1.3 → `lib/auth.php`, `login.php`, gateway, `_ping.php`; test HTTP P01-T08..T24.
5. Chạy toàn bộ bộ test + checklist thủ công.

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Tệp test | Kiểm tra | Kỳ vọng |
|---|---|---|---|
| P01-T01 | `db_smoke_test.php` | Dựng `bigwin_test` từ `schema.sql` | Tạo đủ bảng, chạy 2 lần liên tiếp không lỗi |
| P01-T02 | `db_smoke_test.php` | *(định nghĩa lại — Q-P01-3)* DB dựng từ **`schema.sql@0533c33` + migration mới (chưa có ở `0533c33`)** so với DB dựng từ `schema.sql` hiện tại: cột, chỉ mục, khoá ngoại | Trùng khớp (bảo đảm `schema.sql` đồng bộ migration) |
| P01-T03 | `http_smoke_test.php` | *(định nghĩa lại — B03)* Bật `php -S`: server dùng đúng DB test; `/admin/login.php` 200 có CSRF; cookie jar giữ phiên; client gắn/bỏ CSRF & Origin | Đúng cả 4 ý. (`/login.php` + meta CSRF kiểm ở P01-T08) |
| P01-T04 | `db_users_test.php` | Migration copy admin cũ | Số admin cũ = số `users` role admin; `password_hash` giữ nguyên |
| P01-T05 | `db_users_test.php` | `password_verify` mật khẩu cũ trên bản ghi mới | Đúng |
| P01-T06 | `db_users_test.php` | `username` UNIQUE | Chèn trùng → lỗi |
| P01-T07 | `db_users_test.php` | `create_admin.php --role=user --phai-doi-mat-khau` | Tạo đúng role, cờ = 1 |
| P01-T08 | `http_auth_test.php` | Đăng nhập đúng | 302 → app; cookie `bigwin_web` |
| P01-T09 | `http_auth_test.php` | Cờ cookie | Có `HttpOnly`, `SameSite=Lax`; `Secure` khi `cookie_secure=true` |
| P01-T10 | `http_auth_test.php` | Session ID trước và sau đăng nhập | Khác nhau (chống fixation) |
| P01-T11 | `http_auth_test.php` | Sai mật khẩu / sai username | Cùng một thông báo lỗi chung |
| P01-T12 | `http_auth_test.php` | Sai 5 lần liên tiếp | Lần 6 bị chặn 15 phút kể cả mật khẩu đúng |
| P01-T13 | `http_auth_test.php` | Tài khoản `locked` | Không đăng nhập được |
| P01-T14 | `http_auth_test.php` | Đang đăng nhập, admin khoá tài khoản | Request kế → 401 / về login |
| P01-T15 | `http_auth_test.php` | `phai_doi_mat_khau=1` | Mọi trang app chuyển tới màn đổi mật khẩu; đổi xong cờ = 0 |
| P01-T16 | `http_auth_test.php` | Đổi mật khẩu ở phiên A | Phiên B (cookie khác) bị thu hồi |
| P01-T17 | `http_auth_test.php` | Nhàn rỗi > `phien_nhan_roi_phut` (test đặt 1 phút, giả lập thời gian) | Về login |
| P01-T18 | `http_auth_test.php` | Vượt `phien_tuyet_doi_gio` | Về login |
| P01-T19 | `http_auth_test.php` | Đổi User-Agent giữa phiên | Phải đăng nhập lại |
| P01-T20 | `http_gateway_test.php` | POST `_ping.php` không CSRF | 403 JSON `{ok:false, ma:403}` |
| P01-T21 | `http_gateway_test.php` | CSRF đúng nhưng `Origin` khác host | 403 |
| P01-T22 | `http_gateway_test.php` | GET vào endpoint chỉ POST | 405 |
| P01-T23 | `http_gateway_test.php` | Body > giới hạn / sai `Content-Type` | 422 |
| P01-T24 | `http_gateway_test.php` | Endpoint ném exception | 500 JSON không có stack trace, có mã tham chiếu; `ghi_loi()` có bản ghi |
| P01-T25 | `http_gateway_test.php` | Tiêu đề bảo mật | Có `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `frame-ancestors 'none'` trong CSP, `Cache-Control: no-store` cho JSON |
| P01-T26 | `http_gateway_test.php` | Gọi dồn > ngưỡng rate limit | 429 |
| P01-T27 | `security_scan_test.php` | Mọi `app/ajax/*.php` (trừ tiền tố `_`) gọi `ajax_khoi_dong(` ở câu lệnh đầu | Không vi phạm |
| P01-T28 | Hồi quy | `helpers_test`, `smoke_test`, test P00 | Xanh |

### 5.2 Thủ công (trình duyệt)
- [ ] P01-M01: Đăng nhập bằng tài khoản admin cũ qua `/login.php`; DevTools → cookie `bigwin_web` đủ cờ.
- [ ] P01-M02: Admin cũ vẫn vào `/admin/` bình thường (không vỡ trang quản trị hiện có).
- [ ] P01-M03: Đăng nhập sai 5 lần → thông báo khoá, chờ hết hạn đăng nhập lại được.
- [ ] P01-M04: Tài khoản `phai_doi_mat_khau` → bị chuyển sang đổi mật khẩu; mật khẩu < 10 ký tự bị từ chối.
- [ ] P01-M05: Mở 2 trình duyệt cùng tài khoản, đổi mật khẩu ở 1 → trình duyệt kia bị đăng xuất.
- [ ] P01-M06: `/api/check.php` của app Desktop hoạt động như trước (gọi thử bằng script Desktop hoặc request mẫu).

### 5.3 Lệnh
```
php src/webapp/tools/test.php            # cả bộ (bắt buộc ở cổng)
php src/webapp/tools/test.php http_auth  # khi đang làm S1.3
```

## 6. Cổng nghiệm thu
README §2 **và**: P01-T01..T28 xanh; P01-M01..M06 tick; `/audit-api` AN TOÀN (vì đụng `lib/` dùng chung); rule `security-core.md` tồn tại.

## 7. Rủi ro & rollback
| Rủi ro | Xử lý |
|---|---|
| Migration làm hỏng đăng nhập admin hiện tại | Không xoá/sửa bảng `admins`; `admin/_auth.php` vẫn dùng `admins` cho đến khi P03 chuyển hẳn. Rollback = bỏ qua bảng mới |
| `PASSWORD_ARGON2ID` không có trên hosting | Fallback `PASSWORD_BCRYPT` (đã thiết kế), test P01-T05 chạy cả 2 |
| Session file-based trên Windows dev khác Linux prod | Test HTTP chạy trên `php -S` thật, không giả lập session |

## 8. Ngoài phạm vi
Bản quyền thật (P02), TenantRepo (P02), giao diện app/sidebar (P03), trang quản trị tài khoản (P03).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| 2026-11-03 | Claude (thực thi) · Chủ dự án (duyệt) | S1.0–S1.3: hạ tầng test DB/HTTP, `users`/RBAC, core bảo mật (`lib/security/`), đăng nhập + gateway `ajax_khoi_dong()`. Hồi quy 80/80, `/healthcheck` OK, `/audit-api` AN TOÀN, checklist M01–M06 do chủ dự án kiểm. Chi tiết + khác biệt B01–B18: `TASK_LOG.md` mục P01 | ✅ **PASS** — mở khoá P02 |
