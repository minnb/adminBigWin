# Bảo mật trang quản trị

- **Trang admin cũ** (`index`, `owners`, `backups`, `backup_download`, `usage`): mở đầu bằng `require __DIR__ . '/_auth.php';` rồi `require_login();` trước bất kỳ xử lý/output nào. Từ P03 (bước 9) `_auth.php` dùng phiên CHUNG web BigWIN (`PhienBaoMat`, bảng `users`, `require_admin()`); `admin/login.php`, `admin/logout.php` chỉ chuyển tiếp về `/login.php`, `/logout.php`. Không còn phiên `bigwin_admin`.
- **Trang quản trị mới** (`users.php`, `goi_thue_bao.php` — P03 QĐ1): `require __DIR__ . '/_quan_tri.php';` rồi `$ctx = khung_bat_dau('quan_tri', true);` (khung app, user thường → 403); form POST qua `quan_tri_xu_ly_post()` (CSRF + nguồn → 403). Đọc ô form bằng `o_post()` / `o_post_id()` / `o_post_ngay()`; luật nghiệp vụ nằm trong repo (`UserRepo`, `ThueBaoRepo`, `GoiDichVuRepo`) — trang không viết SQL.
- SQL: luôn dùng prepared statement trên PDO từ `db()` (`lib/bootstrap.php`); không nối chuỗi giá trị người dùng vào SQL. LIKE dùng `mau_like()`.
- Output HTML: escape mọi dữ liệu động bằng `h()`; giá trị trong JS/thuộc tính Alpine cũng phải escape phù hợp (`json_encode` + các cờ `JSON_HEX_*` rồi `h()`).
- Thao tác thay đổi dữ liệu chỉ qua POST (trang cũ đọc input bằng `post_text()`).
- Kiểm tra quyền sở hữu theo ID (IDOR) khi truy cập tài nguyên theo `id`/`machine_id`.
- Mật khẩu tạm (tạo / đặt lại tài khoản — QĐ6) chỉ hiện trên phản hồi của chính thao tác đó: không đưa vào URL, `bao()`, log.
- Giới hạn giấy phép nền tảng (`so_dai_ly_toi_da`) KHÔNG có trên UI — chỉ CLI `tools/giay_phep_nen_tang.php` (QĐ7). Gói dịch vụ không xoá, chỉ ngừng bán.
