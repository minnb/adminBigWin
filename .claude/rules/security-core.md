---
paths:
  - "src/webapp/app/**"
  - "src/webapp/lib/security/**"
  - "src/webapp/lib/auth.php"
  - "src/webapp/login.php"
  - "src/webapp/logout.php"
---
# Core bảo mật web BigWIN (`lib/security/`)

Thiết kế: `docs/plans/2026-11-02-ke-hoach-chuyen-doi-python-php.md` §5.3. Không framework → mọi bảo vệ đi qua lớp dùng chung này; KHÔNG tự làm lại ở từng trang.

## Phiên — `PhienBaoMat`
- Mở phiên CHỈ bằng `PhienBaoMat::batDau()`. CẤM gọi `session_start()`, `session_set_cookie_params()`, `ini_set('session.*')` ở nơi khác.
- Cookie `bigwin_web`: `HttpOnly`, `SameSite=Lax`, `Path=/`, không `Domain`, lifetime 0; `Secure` khi HTTPS hoặc `cfg('cookie_secure')`. ini bắt buộc: `use_strict_mode`, `use_only_cookies`, không `trans_sid`.
- Sau khi xác thực mật khẩu: `PhienBaoMat::dangNhap($userId)` (đổi session ID + xoay CSRF + ghi sổ phiên). CẤM tự ghi `$_SESSION['uid']`.
- Sổ phiên `phien_dang_nhap` là nguồn DUY NHẤT về hạn phiên (nhàn rỗi 30 phút, tuyệt đối 12 giờ), User-Agent, thu hồi. Hết hạn / thu hồi / đổi UA quyết định bằng `PhienBaoMat::kiemTra()` — không viết lại điều kiện thời gian ở nơi khác.
- Khoá tài khoản, đổi mật khẩu, "đăng xuất mọi nơi" → thu hồi trong sổ phiên (có hiệu lực ngay ở request kế), không chỉ xoá `$_SESSION`.

## CSRF — `Csrf`
- Mọi request ĐỔI dữ liệu: `POST` + `Csrf::hopLe(<token gửi lên>)` + `Csrf::nguonHopLe($_SERVER)`. GET đổi dữ liệu = 405.
- AJAX gửi token qua header `X-CSRF-Token` (trang in `<meta name="csrf-token">`); JS chỉ gọi server qua `goiApi()` trong `app/assets/app.js`, CẤM `fetch` trực tiếp.
- Form HTML thường dùng field `csrf`. So khớp luôn bằng `hash_equals` (đã có trong `Csrf::hopLe`).

## Tiêu đề — `TieuDeBaoMat`
- Mọi trang/endpoint gọi `TieuDeBaoMat::gui($laJson)` trước khi xuất nội dung (gateway/layout làm sẵn). JSON luôn `Cache-Control: no-store`.
- Không thêm nguồn ngoài vào CSP ngoài CDN đã khoá phiên bản; CSP chặt bật bằng `cfg('csp_chat')` ở S9.3.

## Rate limit — `GioiHanTanSuat`
- Dùng `GioiHanTanSuat::cho("<loại>:<định danh>", $toiDa, $cuaSoGiay)`; định danh lấy từ `client_ip()` hoặc user_id từ phiên — KHÔNG từ header client tự đặt (`X-Forwarded-For`).
- Đăng nhập KHÔNG dùng `GioiHanTanSuat`: `dang_nhap()` (`lib/auth.php`) đếm sai trong bảng `user_login_fails` theo username HOẶC IP (5 lần / 15 phút → khoá, giữ dấu vết để tra cứu); đăng nhập đúng → xoá đếm của username và IP đó.

## Endpoint & lỗi
- `app/ajax/*.php`: dòng đầu là `ajax_khoi_dong(CheDo)` (S1.3) — gateway làm phiên, CSRF, nguồn, tiêu đề, bản quyền (`GiayPhepGuard`, P02 → 402), rate limit, lỗi JSON. Không bỏ bước nào. Trả `['user', 'tenant' => TenantContext, 'giay_phep', 'hom_nay', 'thue_bao_repo', 'du_lieu']`.
- Thao tác có hạn mức gói (vd tin đối soát/ngày) gọi `ajax_dem_han_muc($ctx, $cot)` trong transaction của thao tác ghi → hết hạn mức = 402 `vuot_han_muc`.
- Lỗi trả JSON `{ok:false, ma, thong_diep}`; KHÔNG lộ stack trace, câu SQL, đường dẫn tệp; chi tiết ghi `ghi_loi()`.
- Thông báo đăng nhập sai luôn chung một câu (không phân biệt sai username hay sai mật khẩu).

## Kiểm chứng
`tests/security_test.php` (đơn vị + HTTP qua `tests/_router.php`); S1.3 thêm `http_auth_test.php`, `http_gateway_test.php`, `security_scan_test.php`.
