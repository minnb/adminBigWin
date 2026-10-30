---
paths:
  - "src/webapp/app/**"
  - "src/webapp/lib/bigwin/repo/**"
---
# Cô lập dữ liệu theo user (multi-tenant) — chống IDOR

## Nguồn `user_id`
- `user_id` CHỈ lấy từ phiên đăng nhập: `$u = require_user();` → `$u['id']`. CẤM nhận `user_id` từ `$_GET`/`$_POST`/JSON/cookie.
- Admin dùng app như một user bình thường với `user_id` của chính mình; KHÔNG có "chế độ xem hộ" trong `app/`.

## Repository (`lib/bigwin/repo/`)
- Mọi hàm đọc/ghi bảng nghiệp vụ có tham số đầu tiên `int $userId` (bắt buộc, không mặc định).
- Mọi câu SQL trên bảng nghiệp vụ có `user_id = ?` trong `WHERE` (SELECT/UPDATE/DELETE) và gán `user_id` khi INSERT. Truy theo id luôn là `WHERE id = ? AND user_id = ?`.
- Tham chiếu chéo (vd `khach_hang_id` khi ghi `lich_su_doi_soat`/`tin_nhan_log`, `tin_nhan_id`) phải được xác minh thuộc cùng `$userId` TRƯỚC khi ghi; không thuộc → coi như không tồn tại (404), không tiết lộ là của người khác.
- `he_so_khach_hang` không có cột `user_id` → luôn JOIN `khach_hang` với `khach_hang.user_id = ?`.
- Không trả về/không nhận ID tuần tự để "đoán" — nhưng an toàn KHÔNG dựa vào việc ID khó đoán; dựa vào điều kiện `user_id`.
- PDO prepared statement 100%; tên bảng/cột không bao giờ đến từ input.

## Dữ liệu dùng chung
- `ket_qua_xo_so` (KQXS), `cau_hinh_mac_dinh`, `parser_*_mac_dinh`, `parser_ma_loi`, `scraper_log`: KHÔNG có `user_id`. User chỉ ĐỌC; chỉ scraper/admin ghi.
- Không JOIN bảng dùng chung theo cách làm rò dữ liệu tenant khác (vd thống kê "số user đã đánh số X").

## Hệ số (3 tầng)
`he_so_khach_hang` (khách của user) → `he_so_user` (user) → `cau_hinh_mac_dinh` (hệ thống). Khách phải thuộc user trước khi đọc tầng 1.

## Trang `app/`
- Đầu mỗi trang/endpoint: `require_user()`; POST phải kiểm CSRF; output escape `h()`.
- `app/ajax/*` trả JSON lỗi chung cho "không có quyền" và "không tồn tại" (cùng mã 404).
- Mỗi slice đụng repo phải có test IDOR: user A không đọc/sửa/xoá được bản ghi của user B.
