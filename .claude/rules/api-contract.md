---
paths:
  - "src/webapp/api/**/*.php"
  - "src/webapp/lib/api.php"
---
# Hợp đồng API với app Desktop BigWIN

App BigWIN đã phát hành gọi cố định các endpoint này — không thể cập nhật app cũ.

- KHÔNG đổi tên/di chuyển tệp, KHÔNG đổi URL: `/api/check.php`, `/api/backup_get.php`, `/api/backup_list.php`, `/api/backup_push.php`, `/api/usage_push.php`.
- KHÔNG xoá/đổi tên/đổi kiểu trường JSON request hay response hiện có; KHÔNG đổi ý nghĩa giá trị (vd `status`: `active|trial|pending|revoked`).
- Chỉ được THÊM trường mới (app cũ bỏ qua trường lạ) và trường request mới phải là tuỳ chọn.
- Giữ nguyên HTTP status, định dạng lỗi `fail()`/`ok()`, cách ký Ed25519 (`payload` + `sig`) và mã hoá backup trong `lib/crypto.php`.
- Mọi thay đổi phải tương thích ngược 100% — chạy `/audit-api` trước khi commit.
