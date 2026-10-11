-- Reservation-Signage スキーマ（MySQL 8 正本）。SQLite へは tools/install.php が自動変換する
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  login_id VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'staff' COMMENT 'admin=管理者 staff=スタッフ',
  is_active TINYINT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='利用者';

CREATE TABLE IF NOT EXISTS login_attempts (
  login_id VARCHAR(50) PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ログイン失敗回数';

CREATE TABLE IF NOT EXISTS shops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NULL COMMENT 'TableCheck の店舗スラッグ',
  sort_no INT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='店舗';

CREATE TABLE IF NOT EXISTS venues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  name VARCHAR(100) NOT NULL COMMENT '会場名（テーブル名）',
  signage_name VARCHAR(100) NULL COMMENT 'サイネージ表示名（空なら会場名）',
  building VARCHAR(50) NULL COMMENT '館・階 例: 本館3F',
  report_name VARCHAR(100) NULL COMMENT '帳票の会場名（空なら帳票に出さない）',
  cap_min INT NULL,
  cap_max INT NULL,
  sort_no INT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会場・テーブル';

CREATE TABLE IF NOT EXISTS flags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  color VARCHAR(20) NULL,
  hide_on_signage TINYINT NOT NULL DEFAULT 0 COMMENT '1=このフラグの予約はサイネージに出さない',
  sort_no INT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='予約フラグ';

CREATE TABLE IF NOT EXISTS options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(30) NOT NULL COMMENT 'purpose=用途 source=来店経路 trigger=きっかけ customer_tag=顧客タグ',
  name VARCHAR(100) NOT NULL,
  sort_no INT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='選択肢';

CREATE TABLE IF NOT EXISTS menu_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort_no INT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='メニューカテゴリ';

CREATE TABLE IF NOT EXISTS menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  price INT NOT NULL DEFAULT 0 COMMENT '税込単価',
  sort_no INT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='メニュー';

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  last_kana VARCHAR(50) NULL,
  first_kana VARCHAR(50) NULL,
  last_name VARCHAR(50) NULL,
  first_name VARCHAR(50) NULL,
  company VARCHAR(200) NULL,
  department VARCHAR(100) NULL,
  title VARCHAR(100) NULL,
  tel VARCHAR(30) NULL,
  email VARCHAR(200) NULL,
  gender VARCHAR(10) NULL,
  memo TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='顧客';

CREATE TABLE IF NOT EXISTS customer_tags (
  customer_id INT NOT NULL,
  option_id INT NOT NULL,
  PRIMARY KEY (customer_id, option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='顧客タグ';

CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL UNIQUE COMMENT '予約ID',
  shop_id INT NOT NULL,
  rsv_date DATE NOT NULL,
  start_time VARCHAR(5) NOT NULL,
  end_time VARCHAR(5) NOT NULL,
  adults INT NOT NULL DEFAULT 0,
  children INT NOT NULL DEFAULT 0,
  infants INT NOT NULL DEFAULT 0,
  customer_id INT NULL,
  group_name VARCHAR(255) NULL,
  sign_name VARCHAR(255) NULL COMMENT '看板名（サイネージ・帳票の表示名）',
  show_on_signage TINYINT NOT NULL DEFAULT 1,
  purpose_id INT NULL,
  source_id INT NULL,
  trigger_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
  memo1 TEXT NULL,
  memo2 TEXT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='予約';
CREATE INDEX idx_rsv_date ON reservations (rsv_date, start_time);

CREATE TABLE IF NOT EXISTS reservation_venues (
  reservation_id INT NOT NULL,
  venue_id INT NOT NULL,
  PRIMARY KEY (reservation_id, venue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='予約の会場';

CREATE TABLE IF NOT EXISTS reservation_flags (
  reservation_id INT NOT NULL,
  flag_id INT NOT NULL,
  PRIMARY KEY (reservation_id, flag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='予約のフラグ';

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT NOT NULL,
  menu_item_id INT NULL,
  name VARCHAR(200) NOT NULL,
  unit_price INT NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 1,
  sort_no INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='注文明細';
CREATE INDEX idx_order_rsv ON order_items (reservation_id);

CREATE TABLE IF NOT EXISTS histories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target VARCHAR(20) NOT NULL COMMENT 'reservation / customer / master',
  target_id INT NULL,
  user_id INT NULL,
  action VARCHAR(20) NOT NULL,
  detail TEXT NULL COMMENT '変更内容（JSON）',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作履歴';
CREATE INDEX idx_hist_target ON histories (target, target_id);

CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(50) PRIMARY KEY,
  value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='設定（サイネージ表示など）';
