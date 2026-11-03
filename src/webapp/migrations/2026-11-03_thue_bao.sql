-- Migration P02 S1.5 — Bản quyền SaaS cho web BigWIN
-- (docs/plans/2026-11-02-ke-hoach-chuyen-doi-python-php.md §1.4, §7.2; quyết định O8, Q-P02-1..4).
--   giay_phep_he_thong : giấy phép NỀN TẢNG, đúng 1 dòng (id = 1) — số đại lý (role=user) đang hoạt động tối đa.
--   goi_dich_vu        : gói & hạn mức (NULL = không giới hạn). Dùng chung, không có user_id.
--   thue_bao           : 1 dòng / đại lý. Trạng thái (dung_thu / hieu_luc / an_han / het_han / tam_khoa) TÍNH RA từ ngày,
--                        không lưu cứng (lib/bigwin/GiayPhepGuard.php).
--   thue_bao_lich_su   : append-only — mọi thay đổi thuê bao (ai / khi nào / trước–sau); tu_dong = cron ghi chuyển trạng thái.
--   su_dung_ngay       : bộ đếm hạn mức theo ngày (khoá dòng FOR UPDATE khi tăng).
-- Chỉ truy cập qua lib/bigwin/repo/ThueBaoRepo.php (Q-P02-1). Không dính API app Desktop (api/* không đọc các bảng này).
-- Dữ liệu khởi tạo (Q-P02-3): giấy phép không giới hạn số đại lý; gói `unlimited`, `dung_thu`.
-- KHÔNG tự cấp thuê bao cho tài khoản đại lý có sẵn (Q-P02-2) — chưa có thuê bao = bị chặn 402 tới khi admin cấp.
-- Chạy lại vẫn an toàn (IF NOT EXISTS, INSERT IGNORE). Chạy trên DB ĐÃ CÓ (cài mới thì schema.sql đã gồm sẵn):
--   mysql -u <user> -p <db> < migrations/2026-11-03_thue_bao.sql

CREATE TABLE IF NOT EXISTS giay_phep_he_thong (
  id                TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  so_dai_ly_toi_da  INT UNSIGNED     NULL,
  ghi_chu           VARCHAR(255)     NOT NULL DEFAULT '',
  cap_nhat_luc      DATETIME         NOT NULL,
  CONSTRAINT ck_giay_phep_mot_dong CHECK (id = 1)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goi_dich_vu (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma                       VARCHAR(30)  NOT NULL,
  ten                      VARCHAR(100) NOT NULL,
  gia_thang                BIGINT       NOT NULL DEFAULT 0,
  so_khach_toi_da          INT UNSIGNED NULL,
  so_tin_ngay_toi_da       INT UNSIGNED NULL,
  cho_phep_telegram        TINYINT(1)   NOT NULL DEFAULT 1,
  so_ngay_tra_cuu_lich_su  INT UNSIGNED NULL,
  so_ngay_an_han           INT UNSIGNED NOT NULL DEFAULT 3,
  dang_ban                 TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_goi_ma (ma)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS thue_bao (
  user_id       INT UNSIGNED NOT NULL PRIMARY KEY,
  goi_id        INT UNSIGNED NOT NULL,
  bat_dau       DATE         NOT NULL,
  het_han       DATE         NOT NULL,
  tam_khoa      TINYINT(1)   NOT NULL DEFAULT 0,
  ly_do_khoa    VARCHAR(255) NULL,
  cap_nhat_boi  INT UNSIGNED NULL,
  cap_nhat_luc  DATETIME     NOT NULL,
  KEY idx_thue_bao_goi (goi_id),
  KEY idx_thue_bao_het_han (het_han),
  CONSTRAINT fk_thue_bao_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_thue_bao_goi FOREIGN KEY (goi_id) REFERENCES goi_dich_vu (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS thue_bao_lich_su (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  hanh_dong   ENUM('tao','gia_han','doi_goi','tam_khoa','mo_khoa','tu_dong') NOT NULL,
  truoc_json  TEXT         NULL,
  sau_json    TEXT         NOT NULL,
  boi         INT UNSIGNED NULL,
  luc         DATETIME     NOT NULL,
  KEY idx_tbls_user_id (user_id, id),
  CONSTRAINT fk_tbls_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS su_dung_ngay (
  user_id           INT UNSIGNED NOT NULL,
  ngay              DATE         NOT NULL,
  so_tin_doi_soat   INT UNSIGNED NOT NULL DEFAULT 0,
  so_lan_phan_tich  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, ngay),
  CONSTRAINT fk_sdn_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO giay_phep_he_thong (id, so_dai_ly_toi_da, ghi_chu, cap_nhat_luc)
VALUES (1, NULL, 'Khởi tạo P02 — không giới hạn số đại lý', NOW());

INSERT IGNORE INTO goi_dich_vu (ma, ten, gia_thang, so_ngay_an_han, dang_ban)
VALUES ('unlimited', 'Không giới hạn', 0, 3, 1),
       ('dung_thu', 'Dùng thử', 0, 3, 1);
