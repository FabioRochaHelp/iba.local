-- Turmas e chamada

CREATE TABLE classes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(80) NOT NULL,
    weekday        TINYINT UNSIGNED NOT NULL COMMENT '1=segunda ... 7=domingo (ISO-8601)',
    start_time     TIME NOT NULL,
    end_time       TIME NOT NULL,
    category       VARCHAR(20) NULL COMMENT 'ex.: Sub-11',
    min_birth_year SMALLINT UNSIGNED NULL,
    max_birth_year SMALLINT UNSIGNED NULL,
    coach_id       INT UNSIGNED NULL,
    active         TINYINT(1) NOT NULL DEFAULT 1,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_classes_coach (coach_id),
    CONSTRAINT fk_class_coach FOREIGN KEY (coach_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_class_weekday CHECK (weekday BETWEEN 1 AND 7),
    CONSTRAINT chk_class_time CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_athlete (
    class_id   INT UNSIGNED NOT NULL,
    athlete_id INT UNSIGNED NOT NULL,
    joined_at  DATE NOT NULL,
    PRIMARY KEY (class_id, athlete_id),
    KEY idx_ca_athlete (athlete_id),
    CONSTRAINT fk_ca_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_sessions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id     INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    coach_id     INT UNSIGNED NULL,
    notes        VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_class_date (class_id, session_date),
    CONSTRAINT fk_as_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
    CONSTRAINT fk_as_coach FOREIGN KEY (coach_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendances (
    session_id INT UNSIGNED NOT NULL,
    athlete_id INT UNSIGNED NOT NULL,
    status     ENUM('presente','falta','justificada') NOT NULL,
    note       VARCHAR(255) NULL,
    PRIMARY KEY (session_id, athlete_id),
    KEY idx_att_athlete (athlete_id),
    CONSTRAINT fk_att_session FOREIGN KEY (session_id) REFERENCES attendance_sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_att_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
