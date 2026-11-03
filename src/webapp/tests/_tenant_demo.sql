-- Bảng DEMO cho test TenantScope (P02 S1.4) — CHỈ nạp vào DB test (tests/_db.php sau schema.sql).
-- KHÔNG BAO GIỜ đưa vào schema.sql / migrations (P02-T18). Mô phỏng đúng mẫu bảng tenant thật (§7.1):
-- user_id NOT NULL, xoá mềm, UNIQUE(id, user_id) ở bảng cha + FK kép (cha_id, user_id) ở bảng con, ON DELETE RESTRICT.

CREATE TABLE IF NOT EXISTS demo_cha (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  ten         VARCHAR(100) NOT NULL DEFAULT '',
  so_tien     BIGINT       NOT NULL DEFAULT 0,
  deleted_at  DATETIME     NULL,
  deleted_by  INT UNSIGNED NULL,
  ly_do_xoa   VARCHAR(255) NULL,
  UNIQUE KEY uq_demo_cha_id_user (id, user_id),
  KEY idx_demo_cha_user (user_id, deleted_at),
  CONSTRAINT fk_demo_cha_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demo_con (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  cha_id      INT UNSIGNED NOT NULL,
  ten         VARCHAR(100) NOT NULL DEFAULT '',
  deleted_at  DATETIME     NULL,
  deleted_by  INT UNSIGNED NULL,
  ly_do_xoa   VARCHAR(255) NULL,
  KEY idx_demo_con_cha (cha_id, user_id),
  CONSTRAINT fk_demo_con_cha FOREIGN KEY (cha_id, user_id) REFERENCES demo_cha (id, user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Bảng dùng chung demo (SharedRepo): không có user_id.
CREATE TABLE IF NOT EXISTS demo_dung_chung (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ten  VARCHAR(100) NOT NULL
) ENGINE=InnoDB;
