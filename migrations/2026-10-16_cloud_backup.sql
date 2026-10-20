-- Migration cloud backup (D:\Projects\bigwin\docs\copyright\BACKUP_DU_LIEU_CLOUD.md §7.1)
-- Chạy 1 lần trên DB ĐÃ CÓ (cài mới thì schema.sql đã gồm sẵn):
--   mysql -u <user> -p <db> < migrations/2026-10-16_cloud_backup.sql

CREATE TABLE IF NOT EXISTS owners (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ten         VARCHAR(150) NOT NULL,
  ghi_chu     VARCHAR(500) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB;

ALTER TABLE machines
  ADD COLUMN IF NOT EXISTS owner_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS device_secret_hash CHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS secret_mismatch_at DATETIME NULL,
  ADD INDEX IF NOT EXISTS idx_owner (owner_id);

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
  KEY idx_ngay (ngay)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_access_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id  CHAR(64)        NOT NULL,
  version_id  BIGINT UNSIGNED NOT NULL,
  ip          VARCHAR(45)     NOT NULL,
  created_at  DATETIME        NOT NULL,
  KEY idx_version (version_id)
) ENGINE=InnoDB;
