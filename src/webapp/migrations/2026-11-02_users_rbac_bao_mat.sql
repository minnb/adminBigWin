-- Migration P01 S1.1 — Danh tính & RBAC + hạ tầng bảo mật cho web BigWIN
-- (docs/plans/2026-11-02-ke-hoach-chuyen-doi-python-php.md §5.3, §7.2; quyết định D2, O5).
--   users             : tài khoản đăng nhập chung (đại lý role=user, quản trị role=admin). Không xoá — chỉ locked.
--   user_login_fails  : đếm đăng nhập sai theo username + IP (khoá tăng dần).
--   phien_dang_nhap   : sổ phiên để THU HỒI ngay (khoá tài khoản, đổi mật khẩu, "đăng xuất mọi nơi").
--   gioi_han_tan_suat : bộ đếm rate limit khi máy chủ không có APCu.
-- Chép admin cũ (bảng admins) sang users role=admin, GIỮ NGUYÊN password_hash. Bảng admins KHÔNG đổi —
-- admin/_auth.php còn dùng tới P03.
-- Chạy lại vẫn an toàn (IF NOT EXISTS; chỉ chép admin chưa có trong users). Chạy trên DB ĐÃ CÓ
-- (cài mới thì schema.sql đã gồm sẵn):
--   mysql -u <user> -p <db> < migrations/2026-11-02_users_rbac_bao_mat.sql

CREATE TABLE IF NOT EXISTS users (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username           VARCHAR(50)  NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,
  role               ENUM('user','admin')     NOT NULL DEFAULT 'user',
  trang_thai         ENUM('active','locked')  NOT NULL DEFAULT 'active',
  ten_hien_thi       VARCHAR(100) NOT NULL DEFAULT '',
  phai_doi_mat_khau  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL,
  last_login_at      DATETIME     NULL,
  created_by         INT UNSIGNED NULL,
  UNIQUE KEY uq_username (username),
  KEY idx_role_trang_thai (role, trang_thai),
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_login_fails (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip          VARCHAR(45) NOT NULL,
  username    VARCHAR(50) NOT NULL DEFAULT '',
  created_at  DATETIME    NOT NULL,
  KEY idx_ip_time (ip, created_at),
  KEY idx_user_time (username, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS phien_dang_nhap (
  id_hash        CHAR(64)     NOT NULL PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  ip             VARCHAR(45)  NOT NULL,
  ua_hash        CHAR(64)     NOT NULL,
  tao_luc        DATETIME     NOT NULL,
  hoat_dong_luc  DATETIME     NOT NULL,
  thu_hoi_luc    DATETIME     NULL,
  KEY idx_user_thu_hoi (user_id, thu_hoi_luc),
  CONSTRAINT fk_phien_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gioi_han_tan_suat (
  khoa    VARCHAR(120) NOT NULL PRIMARY KEY,
  so_lan  INT UNSIGNED NOT NULL,
  het_luc DATETIME     NOT NULL,
  KEY idx_het_luc (het_luc)
) ENGINE=InnoDB;

INSERT INTO users (username, password_hash, role, trang_thai, ten_hien_thi, phai_doi_mat_khau, created_at)
SELECT a.username, a.password_hash, 'admin', 'active', a.username, 0, a.created_at
  FROM admins a
 WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.username = a.username);
