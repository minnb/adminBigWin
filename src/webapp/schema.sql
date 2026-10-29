-- BigWIN License Server — MySQL/MariaDB schema (CHỈ tạo bảng).
-- Tạo database trước (cPanel "MySQL Databases" hoặc lệnh dưới), rồi import file này VÀO database đó:
--   CREATE DATABASE bigwin_license CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u <user> -p bigwin_license < schema.sql
-- Không có CREATE DATABASE/USE ở đây vì shared hosting thường cấm và tên DB có tiền tố tài khoản.

-- owners tạo TRƯỚC machines (machines có khoá ngoại tới owners).
CREATE TABLE IF NOT EXISTS owners (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ten         VARCHAR(150) NOT NULL,
  ghi_chu     VARCHAR(500) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS machines (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id    CHAR(64)     NOT NULL,
  hostname      VARCHAR(100) NOT NULL DEFAULT '',
  customer_name VARCHAR(150) NOT NULL DEFAULT '',
  status        ENUM('pending','active','revoked') NOT NULL DEFAULT 'pending',
  tg_id         BIGINT       NULL,
  tg_username   VARCHAR(64)  NOT NULL DEFAULT '',
  tg_name       VARCHAR(128) NOT NULL DEFAULT '',
  tg_updated_at DATETIME     NULL,
  app_version   VARCHAR(20)  NOT NULL DEFAULT '',
  first_seen    DATETIME     NOT NULL,
  last_seen     DATETIME     NOT NULL,
  last_ip       VARCHAR(45)  NOT NULL DEFAULT '',
  note          VARCHAR(500) NOT NULL DEFAULT '',
  owner_id      INT UNSIGNED NULL,
  device_secret_hash CHAR(64) NULL,
  secret_mismatch_at DATETIME NULL,
  UNIQUE KEY uq_machine_id (machine_id),
  KEY idx_owner (owner_id),
  KEY idx_status (status),
  KEY idx_tg_id (tg_id),
  -- owner_id quyết định máy nào xem được backup của nhau → ràng buộc ở tầng DB.
  CONSTRAINT fk_machines_owner FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS check_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id  CHAR(64)    NOT NULL DEFAULT '',
  ip          VARCHAR(45) NOT NULL,
  result      VARCHAR(20) NOT NULL,
  created_at  DATETIME    NOT NULL,
  KEY idx_ip_time (ip, created_at),
  KEY idx_created (created_at),
  KEY idx_machine (machine_id)
) ENGINE=InnoDB;

-- Đếm đăng nhập admin sai theo IP (khoá 15 phút sau 5 lần). Tự dọn dòng > 1 ngày.
CREATE TABLE IF NOT EXISTS admin_login_fails (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip          VARCHAR(45) NOT NULL,
  username    VARCHAR(50) NOT NULL DEFAULT '',
  created_at  DATETIME    NOT NULL,
  KEY idx_ip_time (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB;

-- Cloud backup (BACKUP_DU_LIEU_CLOUD.md §7.1) — giống migrations/2026-10-16_cloud_backup.sql
CREATE TABLE IF NOT EXISTS backup_versions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id    CHAR(64)     NOT NULL,
  bo            VARCHAR(20)  NOT NULL,
  hash          CHAR(64)     NOT NULL,
  so_dong_json  VARCHAR(500) NOT NULL,
  kich_thuoc    INT UNSIGNED NOT NULL,
  du_lieu       MEDIUMBLOB   NOT NULL,
  app_version   VARCHAR(20)  NOT NULL DEFAULT '',
  created_at    DATETIME     NOT NULL,
  KEY idx_may_bo_thoigian (machine_id, bo, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usage_summaries (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id       CHAR(64)     NOT NULL,
  ngay             DATE         NOT NULL,
  khach_hang_id    INT          NOT NULL,
  ten_khach        VARCHAR(150) NOT NULL,
  payload_json     MEDIUMTEXT   NOT NULL,
  tong_thanh_tien  BIGINT       NOT NULL,
  so_lan_xem       INT UNSIGNED NOT NULL DEFAULT 1,
  xem_dau          DATETIME     NOT NULL,
  xem_cuoi         DATETIME     NOT NULL,
  UNIQUE KEY uq_may_ngay_khach (machine_id, ngay, khach_hang_id),
  KEY idx_ngay_xem (ngay, xem_cuoi)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_access_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id  CHAR(64)        NOT NULL,
  version_id  BIGINT UNSIGNED NOT NULL,
  ip          VARCHAR(45)     NOT NULL,
  created_at  DATETIME        NOT NULL,
  KEY idx_version (version_id)
) ENGINE=InnoDB;
