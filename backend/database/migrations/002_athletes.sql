-- Responsáveis, atletas e posições

CREATE TABLE guardians (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    cpf        CHAR(11) NULL,
    email      VARCHAR(190) NULL,
    notes      TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_guardians_cpf (cpf),
    KEY idx_guardians_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guardian_phones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guardian_id INT UNSIGNED NOT NULL,
    phone       VARCHAR(13) NOT NULL COMMENT 'somente dígitos, com DDD',
    is_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
    label       VARCHAR(40) NULL,
    UNIQUE KEY uq_guardian_phone (guardian_id, phone),
    KEY idx_phone (phone),
    CONSTRAINT fk_phone_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE positions (
    id         TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(40) NOT NULL,
    short_name VARCHAR(4) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_positions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE athletes (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                 VARCHAR(120) NOT NULL,
    birth_date           DATE NULL,
    guardian_id          INT UNSIGNED NOT NULL,
    has_health_condition TINYINT(1) NOT NULL DEFAULT 0,
    health_condition     TEXT NULL,
    status               ENUM('ativo','inativo','trancado') NOT NULL DEFAULT 'ativo',
    enrollment_date      DATE NOT NULL,
    notes                TEXT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at           DATETIME NULL,
    KEY idx_athletes_name (name),
    KEY idx_athletes_status (status, deleted_at),
    KEY idx_athletes_birth (birth_date),
    CONSTRAINT fk_athlete_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE athlete_position (
    athlete_id  INT UNSIGNED NOT NULL,
    position_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (athlete_id, position_id),
    CONSTRAINT fk_ap_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_position FOREIGN KEY (position_id) REFERENCES positions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
