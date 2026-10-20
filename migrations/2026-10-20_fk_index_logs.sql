-- Migration 2026-10-20: khoá ngoại chủ máy + index nhật ký + index dọn check_logs.
-- Chạy 1 lần trên DB ĐÃ CÓ (cài mới thì schema.sql đã gồm sẵn); chạy lại vẫn an toàn:
--   mysql -u <user> -p <db> < migrations/2026-10-20_fk_index_logs.sql
-- Cú pháp `IF [NOT] EXISTS` cho index/khoá ngoại là của MariaDB (XAMPP). Hosting MySQL 8: cài mới bằng schema.sql.

-- 1. owner_id quyết định máy nào xem được backup của nhau → ràng buộc ở tầng DB, không chỉ PHP.
--    Dọn dữ liệu mồ côi TRƯỚC, nếu không ALTER thất bại (lỗi 1452).
UPDATE machines SET owner_id = NULL
 WHERE owner_id IS NOT NULL AND owner_id NOT IN (SELECT id FROM owners);
ALTER TABLE machines
  ADD FOREIGN KEY IF NOT EXISTS fk_machines_owner (owner_id) REFERENCES owners (id) ON DELETE SET NULL;

-- 2. Trang Nhật ký: ORDER BY ngay DESC, xem_cuoi DESC LIMIT 300 — index ghép cho phép đi ngược
--    index rồi dừng sau 300 dòng thay vì sắp xếp cả khoảng ngày (có MEDIUMTEXT payload).
ALTER TABLE usage_summaries ADD INDEX IF NOT EXISTS idx_ngay_xem (ngay, xem_cuoi);
ALTER TABLE usage_summaries DROP INDEX IF EXISTS idx_ngay;

-- 3. Dọn check_logs cũ theo created_at (api/check.php) — cần index để DELETE không quét cả bảng.
ALTER TABLE check_logs ADD INDEX IF NOT EXISTS idx_created (created_at);
