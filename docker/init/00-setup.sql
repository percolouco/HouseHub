-- Initialisation HouseHub — Meta DB + permissions
-- Exécuté automatiquement au premier démarrage MariaDB

USE househub_meta;

CREATE TABLE IF NOT EXISTS families (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  db_name     VARCHAR(64) NOT NULL UNIQUE,
  invite_code VARCHAR(32) NOT NULL UNIQUE,
  is_active        TINYINT(1) DEFAULT 1,
  enabled_modules  JSON DEFAULT NULL,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(100) NOT NULL,
  family_id     INT DEFAULT NULL,
  is_admin      TINYINT(1) DEFAULT 0,
  is_active     TINYINT(1) DEFAULT 1,
  lang          VARCHAR(5) DEFAULT 'fr',
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (family_id) REFERENCES families(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_calendar_integrations (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  provider         VARCHAR(50) NOT NULL DEFAULT 'icloud_caldav',
  username         VARCHAR(255) NOT NULL,
  secret_encrypted TEXT NOT NULL,
  dav_principal_url VARCHAR(1024) DEFAULT NULL,
  calendar_url     VARCHAR(1024) DEFAULT NULL,
  status           VARCHAR(30) DEFAULT 'connected',
  last_sync_at     DATETIME DEFAULT NULL,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_provider (user_id, provider),
  CONSTRAINT fk_calendar_integration_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Donner au user applicatif le droit de créer des DBs famille
GRANT ALL PRIVILEGES ON `househub_f%`.* TO 'househub'@'%';
FLUSH PRIVILEGES;
