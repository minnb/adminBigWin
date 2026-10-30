---
paths:
  - "src/webapp/admin/**/*.php"
---
# Bảo mật trang quản trị

- Mọi trang (trừ `login.php`, `logout.php`) phải mở đầu bằng `require __DIR__ . '/_auth.php';` rồi `require_login();` trước bất kỳ xử lý/output nào.
- SQL: luôn dùng prepared statement trên PDO từ `db()` (`lib/bootstrap.php`); không nối chuỗi giá trị người dùng vào SQL. LIKE dùng `mau_like()`.
- Output HTML: escape mọi dữ liệu động bằng `h()`; giá trị trong JS/thuộc tính Alpine cũng phải escape phù hợp.
- Thao tác thay đổi dữ liệu chỉ qua POST, đọc input bằng `post_text()`.
- Kiểm tra quyền sở hữu theo ID (IDOR) khi truy cập tài nguyên theo `id`/`machine_id`.
