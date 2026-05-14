-- HouseHub — Schéma MySQL
-- Créer la base : CREATE DATABASE househub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Utilisateurs ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Personnes ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_people (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Calendrier familial ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_events (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  event_date DATE NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  person_id  INT NOT NULL,
  duration   DECIMAL(4,2) DEFAULT 1.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_leaves (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  person_id  INT NOT NULL,
  leave_type VARCHAR(50) NOT NULL,
  leave_date DATE NOT NULL,
  duration   DECIMAL(4,2) DEFAULT 1.0,
  UNIQUE KEY uq_leave (person_id, leave_type, leave_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_leave_balances (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  person_id       INT NOT NULL,
  leave_type      VARCHAR(50) NOT NULL,
  initial_balance DECIMAL(6,2) DEFAULT 0,
  balance_year    INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_leave_snapshots (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  person_id         INT NOT NULL,
  leave_type        VARCHAR(50) NOT NULL,
  snapshot_date     DATE NOT NULL,
  remaining_balance DECIMAL(6,2) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_person_leave_meta (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  person_id        INT NOT NULL UNIQUE,
  anniversary_date DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_calendar_weeks (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  year            INT NOT NULL,
  week_iso_year   INT NOT NULL,
  week_iso_number INT NOT NULL,
  week_label      VARCHAR(20),
  month           INT,
  month_name      VARCHAR(20),
  week_start_date DATE,
  mon_date DATE, tue_date DATE, wed_date DATE,
  thu_date DATE, fri_date DATE, sat_date DATE, sun_date DATE,
  UNIQUE KEY uq_week (year, week_iso_year, week_iso_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Budget ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_budget_items (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(255) NOT NULL,
  amount           DECIMAL(10,2) DEFAULT 0,
  category         VARCHAR(100),
  type             VARCHAR(50),
  payment_day      INT DEFAULT NULL,
  is_estimate      TINYINT(1) DEFAULT 0,
  reg_month        VARCHAR(7) DEFAULT NULL,
  mapping_keywords TEXT DEFAULT NULL,
  holiday_id       INT DEFAULT NULL,
  is_checked       TINYINT(1) DEFAULT 0,
  sort_order       INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_expenses (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  date_exp       DATE NOT NULL,
  gestion_month  VARCHAR(10) NOT NULL,
  category       VARCHAR(100),
  label          VARCHAR(255),
  amount         DECIMAL(10,2) DEFAULT 0,
  import_ref     VARCHAR(255) DEFAULT NULL,
  budget_item_id INT DEFAULT NULL,
  holiday_id     INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_alloc_categories (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  target     DECIMAL(10,2) DEFAULT 0,
  holiday_id INT DEFAULT NULL,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_alloc_values (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  month_date   VARCHAR(10) NOT NULL,
  cat_id       INT NOT NULL,
  amount_alex  DECIMAL(10,2) DEFAULT 0,
  amount_laia  DECIMAL(10,2) DEFAULT 0,
  UNIQUE KEY uq_alloc (month_date, cat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_savings (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  owner      VARCHAR(50) NOT NULL,
  month_date VARCHAR(10) NOT NULL,
  category   VARCHAR(100) NOT NULL,
  amount     DECIMAL(10,2) DEFAULT 0,
  holiday_id INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_bank_snapshots (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  snapshot_date DATE NOT NULL,
  amount        DECIMAL(12,2) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_salary_config (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  year        INT NOT NULL,
  person      VARCHAR(50) NOT NULL,
  salary      DECIMAL(10,2) DEFAULT 0,
  mensualite  DECIMAL(10,2) DEFAULT 0,
  frais_func  DECIMAL(10,2) DEFAULT 0,
  eco_perso   DECIMAL(10,2) DEFAULT 0,
  eco_family  DECIMAL(10,2) DEFAULT 0,
  UNIQUE KEY uq_salary (year, person)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_import_rules (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  keyword  VARCHAR(255) NOT NULL UNIQUE,
  category VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_notes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  note_type    VARCHAR(100) NOT NULL,
  reference_id VARCHAR(100) NOT NULL,
  content      TEXT,
  UNIQUE KEY uq_note (note_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Voyages / Holidays ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_holidays (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  period_hint  VARCHAR(100) DEFAULT NULL,
  start_date   DATE DEFAULT NULL,
  end_date     DATE DEFAULT NULL,
  status       VARCHAR(50) DEFAULT 'draft',
  budget_food  DECIMAL(10,2) DEFAULT 0,
  budget_extra DECIMAL(10,2) DEFAULT 0,
  notes        TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_holidays_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  holiday_id      INT NOT NULL,
  category        VARCHAR(100),
  name            VARCHAR(255),
  amount          DECIMAL(10,2) DEFAULT 0,
  is_paid         TINYINT(1) DEFAULT 0,
  location_name   VARCHAR(255) DEFAULT NULL,
  lat             DECIMAL(10,7) DEFAULT NULL,
  lng             DECIMAL(10,7) DEFAULT NULL,
  sort_order      INT DEFAULT 0,
  notes           TEXT DEFAULT NULL,
  item_date       DATE DEFAULT NULL,
  item_time       TIME DEFAULT NULL,
  step_start_date DATE DEFAULT NULL,
  step_end_date   DATE DEFAULT NULL,
  duration        INT DEFAULT NULL,
  is_return       TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_holidays_ideas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  period_hint  VARCHAR(100) DEFAULT NULL,
  start_date   DATE DEFAULT NULL,
  end_date     DATE DEFAULT NULL,
  status       VARCHAR(50) DEFAULT 'idea',
  budget_food  DECIMAL(10,2) DEFAULT 0,
  budget_extra DECIMAL(10,2) DEFAULT 0,
  notes        TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_geocode_cache (
  q_hash       CHAR(64) PRIMARY KEY,
  q            VARCHAR(255),
  lat          DECIMAL(10,7),
  lng          DECIMAL(10,7),
  display_name TEXT,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Cadeaux ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_gifts (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  year             INT NOT NULL,
  adult_name       VARCHAR(100) NOT NULL,
  payer_name       VARCHAR(100),
  child_name       VARCHAR(100) NOT NULL,
  occasion         VARCHAR(50) NOT NULL,
  gift_description TEXT NOT NULL,
  product_link     VARCHAR(500) DEFAULT NULL,
  amount           DECIMAL(8,2) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Données initiales ────────────────────────────────────────────────────────
-- Utilisateur admin (mot de passe : changeme → à modifier !)
INSERT IGNORE INTO pf_users (id, username, password_hash, display_name)
VALUES (1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');

-- Personnes (IDs fixes correspondant aux constantes dans config.php)
INSERT IGNORE INTO pf_people (id, name) VALUES (2, 'Alex');
INSERT IGNORE INTO pf_people (id, name) VALUES (3, 'Laia');

-- ─── Garage Manager ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  brand VARCHAR(100) NOT NULL,
  model VARCHAR(100) NOT NULL,
  year INT DEFAULT NULL,
  license_plate VARCHAR(50) DEFAULT NULL,
  vin VARCHAR(100) DEFAULT NULL,
  fuel_type VARCHAR(50) DEFAULT 'Essence',
  color VARCHAR(50) DEFAULT NULL,
  purchase_date DATE DEFAULT NULL,
  purchase_price DECIMAL(10,2) DEFAULT NULL,
  current_km INT DEFAULT 0,
  photo VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_maintenances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  type VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL,
  date DATE NOT NULL,
  km INT DEFAULT NULL,
  cost DECIMAL(10,2) DEFAULT 0,
  mechanic VARCHAR(100) DEFAULT NULL,
  garage_name VARCHAR(100) DEFAULT NULL,
  next_km INT DEFAULT NULL,
  next_date DATE DEFAULT NULL,
  invoice_photo VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES pf_vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_parts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT DEFAULT NULL,
  maintenance_id INT DEFAULT NULL,
  brand VARCHAR(100) DEFAULT NULL,
  reference VARCHAR(100) DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT 'Autre',
  price DECIMAL(10,2) DEFAULT 0,
  quantity INT DEFAULT 1,
  unit VARCHAR(50) DEFAULT 'pièce',
  supplier VARCHAR(100) DEFAULT NULL,
  purchase_date DATE DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES pf_vehicles(id) ON DELETE SET NULL,
  FOREIGN KEY (maintenance_id) REFERENCES pf_maintenances(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Notes / Memo ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_memo_notes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(255) NOT NULL,
  content    LONGTEXT DEFAULT '',
  tags       VARCHAR(1000) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT KEY ft_notes (title, content, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_memo_attachments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  note_id       INT NOT NULL,
  type          ENUM('image','file','url') NOT NULL DEFAULT 'file',
  filename      VARCHAR(255) DEFAULT NULL,
  original_name VARCHAR(255) DEFAULT NULL,
  url           TEXT DEFAULT NULL,
  label         VARCHAR(255) DEFAULT NULL,
  size          INT DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (note_id) REFERENCES pf_memo_notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Todo ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_todo_lists (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255) NOT NULL,
  color      VARCHAR(20) DEFAULT '#3b82f6',
  icon       VARCHAR(10) DEFAULT '📋',
  position   INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_todos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  list_id    INT DEFAULT NULL,
  title      VARCHAR(500) NOT NULL,
  notes      TEXT DEFAULT NULL,
  due_date   DATE DEFAULT NULL,
  due_time   TIME DEFAULT NULL,
  notified   TINYINT(1) DEFAULT 0,
  notified_date DATE DEFAULT NULL,
  priority   ENUM('none','low','medium','high') DEFAULT 'none',
  done       TINYINT(1) DEFAULT 0,
  done_at    DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (list_id) REFERENCES pf_todo_lists(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Liste de courses ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_grocery_items (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  label      VARCHAR(500) NOT NULL,
  in_cart    TINYINT(1) NOT NULL DEFAULT 0,
  position   INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Calendar iOS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pf_calendar_events (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  family_id          INT NOT NULL,
  created_by_user_id INT NOT NULL,
  title              VARCHAR(255) NOT NULL,
  description        TEXT DEFAULT NULL,
  location           VARCHAR(255) DEFAULT NULL,
  start_at           DATETIME NOT NULL,
  end_at             DATETIME NOT NULL,
  is_all_day         TINYINT(1) DEFAULT 0,
  timezone           VARCHAR(64) DEFAULT 'Europe/Paris',
  rrule              VARCHAR(500) DEFAULT NULL,
  status             VARCHAR(50) DEFAULT 'confirmed',
  external_uid       VARCHAR(255) DEFAULT NULL,
  sync_state         VARCHAR(30) DEFAULT 'pending_push',
  deleted_at         DATETIME DEFAULT NULL,
  created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_external_uid (external_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pf_calendar_event_links (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  calendar_event_id INT NOT NULL,
  external_uid      VARCHAR(255) NOT NULL,
  external_etag     VARCHAR(255) DEFAULT NULL,
  calendar_url      VARCHAR(1024) DEFAULT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_calendar_event (calendar_event_id),
  UNIQUE KEY uq_external_link (external_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
