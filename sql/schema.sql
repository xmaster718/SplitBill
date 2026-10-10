-- SplitBill — схема базы данных (MySQL 5.7+ / 8.0, MariaDB 10.4+)
-- Как использовать: создайте пустую БД (например, splitbill, кодировка utf8mb4_unicode_ci),
-- выберите её в phpMyAdmin и нажмите «Импорт» → выберите этот файл.

SET NAMES utf8mb4;

-- Пользователи (аккаунты). role: обычный пользователь или администратор.
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(60)  NOT NULL,
  email         VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Токены входа. В базе хранится только SHA-256 от токена, а не сам токен.
CREATE TABLE IF NOT EXISTS auth_tokens (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_tokens_user (user_id),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Неудачные попытки входа (защита от подбора пароля).
CREATE TABLE IF NOT EXISTS login_attempts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(150) NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts_email_time (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Группы (поездка, квартира, праздник...). invite_code — код для входа по приглашению.
CREATE TABLE IF NOT EXISTS split_groups (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80) NOT NULL,
  owner_id    INT UNSIGNED NOT NULL,
  invite_code CHAR(8) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_group_code (invite_code),
  KEY idx_groups_owner (owner_id),
  CONSTRAINT fk_groups_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Кто из зарегистрированных пользователей имеет доступ к группе.
CREATE TABLE IF NOT EXISTS group_users (
  group_id  INT UNSIGNED NOT NULL,
  user_id   INT UNSIGNED NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id),
  KEY idx_gu_user (user_id),
  CONSTRAINT fk_gu_group FOREIGN KEY (group_id) REFERENCES split_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_gu_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Участники делёжки. Это либо зарегистрированный пользователь (user_id),
-- либо «гость», добавленный просто по имени (user_id = NULL).
CREATE TABLE IF NOT EXISTS group_members (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  name       VARCHAR(60) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_name (group_id, name),
  KEY idx_members_user (user_id),
  CONSTRAINT fk_members_group FOREIGN KEY (group_id) REFERENCES split_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_members_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Расходы и переводы. Деньги хранятся в минимальных единицах (тиын/копейки) целым числом,
-- чтобы не было ошибок округления. kind='payment' — это перевод долга между участниками.
CREATE TABLE IF NOT EXISTS expenses (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id     INT UNSIGNED NOT NULL,
  kind         ENUM('expense','payment') NOT NULL DEFAULT 'expense',
  description  VARCHAR(120) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  paid_by      INT UNSIGNED NOT NULL,
  spent_on     DATE NOT NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exp_group_date (group_id, spent_on, id),
  KEY idx_exp_paid_by (paid_by),
  CONSTRAINT fk_exp_group   FOREIGN KEY (group_id)   REFERENCES split_groups(id)  ON DELETE CASCADE,
  CONSTRAINT fk_exp_payer   FOREIGN KEY (paid_by)    REFERENCES group_members(id) ON DELETE CASCADE,
  CONSTRAINT fk_exp_creator FOREIGN KEY (created_by) REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Доли: кто сколько должен за конкретный расход. Сумма долей всегда равна сумме расхода.
CREATE TABLE IF NOT EXISTS expense_shares (
  expense_id  INT UNSIGNED NOT NULL,
  member_id   INT UNSIGNED NOT NULL,
  share_cents BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (expense_id, member_id),
  KEY idx_shares_member (member_id),
  CONSTRAINT fk_shares_expense FOREIGN KEY (expense_id) REFERENCES expenses(id)      ON DELETE CASCADE,
  CONSTRAINT fk_shares_member  FOREIGN KEY (member_id)  REFERENCES group_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Как сделать пользователя администратором (выполнить один раз вручную в phpMyAdmin → SQL):
--   UPDATE users SET role = 'admin' WHERE email = 'ваш@email.com';
