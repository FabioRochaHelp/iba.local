-- Usuários, sessões, tentativas de login, rate limit e auditoria

CREATE TABLE users (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                 VARCHAR(120) NOT NULL,
    email                VARCHAR(190) NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('admin','professor') NOT NULL DEFAULT 'professor',
    active               TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    password_changed_at  DATETIME NULL,
    last_login_at        DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id            CHAR(64) NOT NULL PRIMARY KEY COMMENT 'SHA-256 do ID da sessão',
    user_id       INT UNSIGNED NULL,
    payload       MEDIUMBLOB NOT NULL,
    ip            VARCHAR(45) NOT NULL DEFAULT '',
    user_agent    VARCHAR(255) NOT NULL DEFAULT '',
    last_activity INT UNSIGNED NOT NULL,
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(190) NOT NULL,
    ip           VARCHAR(45) NOT NULL,
    success      TINYINT(1) NOT NULL,
    attempted_at DATETIME NOT NULL,
    KEY idx_login_email (email, attempted_at),
    KEY idx_login_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    key_hash     CHAR(64) NOT NULL PRIMARY KEY,
    hits         INT UNSIGNED NOT NULL,
    window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(60) NOT NULL,
    entity     VARCHAR(60) NOT NULL DEFAULT '',
    entity_id  BIGINT UNSIGNED NULL,
    payload    JSON NULL,
    ip         VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    KEY idx_audit_entity (entity, entity_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
