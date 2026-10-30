---
description: Tạo migration SQL mới và đồng bộ schema.sql
argument-hint: "<mô tả ngắn thay đổi DB>"
---
Tạo migration cho: $ARGUMENTS

1. Đọc `src/webapp/schema.sql` và các tệp trong `src/webapp/migrations/` để nắm cấu trúc và phong cách hiện tại.
2. Tạo `src/webapp/migrations/<YYYY-MM-DD>_<ten_ngan_snake_case>.sql` (ngày hôm nay). Câu lệnh nên chạy lại an toàn khi có thể (`IF NOT EXISTS`...), có chú thích tiếng Việt mục đích thay đổi.
3. Cập nhật `src/webapp/schema.sql` để phản ánh trạng thái cuối cùng (schema.sql = DB cài mới; migration = nâng cấp DB cũ). Hai bên phải cho cùng kết quả.
4. Nếu bảng liên quan tới API, đối chiếu `.claude/rules/api-contract.md` — không đổi/xoá cột mà endpoint đang trả về.
5. Không chạy migration trên DB thật; in lệnh để người dùng tự chạy.
