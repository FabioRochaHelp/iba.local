-- Evolução do atleta (avaliações, medidas, observações, metas) e perfis de portal (atleta/responsável)

ALTER TABLE users
    MODIFY role ENUM('admin','professor','atleta','responsavel') NOT NULL DEFAULT 'professor',
    ADD COLUMN athlete_id  INT UNSIGNED NULL AFTER role,
    ADD COLUMN guardian_id INT UNSIGNED NULL AFTER athlete_id,
    ADD UNIQUE KEY uq_users_athlete (athlete_id),
    ADD UNIQUE KEY uq_users_guardian (guardian_id),
    ADD CONSTRAINT fk_users_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_users_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE CASCADE;

CREATE TABLE evaluation_criteria (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(60) NOT NULL,
    category   ENUM('tecnico','tatico','fisico','comportamental') NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    active     TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_criteria_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluations (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id         INT UNSIGNED NOT NULL,
    evaluated_by       INT UNSIGNED NULL,
    evaluation_date    DATE NOT NULL,
    general_comment    TEXT NULL,
    visible_to_athlete TINYINT(1) NOT NULL DEFAULT 1,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_eval_athlete (athlete_id, evaluation_date),
    CONSTRAINT fk_eval_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_user FOREIGN KEY (evaluated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_scores (
    evaluation_id INT UNSIGNED NOT NULL,
    criterion_id  SMALLINT UNSIGNED NOT NULL,
    score         TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (evaluation_id, criterion_id),
    CONSTRAINT fk_score_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations (id) ON DELETE CASCADE,
    CONSTRAINT fk_score_criterion FOREIGN KEY (criterion_id) REFERENCES evaluation_criteria (id) ON DELETE RESTRICT,
    CONSTRAINT chk_score_range CHECK (score BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE physical_measurements (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id       INT UNSIGNED NOT NULL,
    measured_at      DATE NOT NULL,
    height_cm        DECIMAL(5,1) NULL,
    weight_kg        DECIMAL(5,1) NULL,
    sprint_20m_s     DECIMAL(4,2) NULL COMMENT 'tempo em segundos (menor = melhor)',
    vertical_jump_cm DECIMAL(5,1) NULL,
    endurance_m      SMALLINT UNSIGNED NULL COMMENT 'distância em teste de resistência (ex.: Cooper)',
    notes            VARCHAR(255) NULL,
    recorded_by      INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_meas_athlete (athlete_id, measured_at),
    CONSTRAINT fk_meas_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    CONSTRAINT fk_meas_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_meas_values CHECK (
        (height_cm IS NULL OR height_cm BETWEEN 50 AND 230) AND (weight_kg IS NULL OR weight_kg BETWEEN 10 AND 200)
        AND (sprint_20m_s IS NULL OR sprint_20m_s BETWEEN 1 AND 20) AND (vertical_jump_cm IS NULL OR vertical_jump_cm BETWEEN 0 AND 150)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coach_notes (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id         INT UNSIGNED NOT NULL,
    author_id          INT UNSIGNED NULL,
    note_date          DATE NOT NULL,
    type               ENUM('ponto_forte','a_melhorar','comportamento','geral') NOT NULL DEFAULT 'geral',
    content            TEXT NOT NULL,
    visible_to_athlete TINYINT(1) NOT NULL DEFAULT 0,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notes_athlete (athlete_id, note_date),
    CONSTRAINT fk_note_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    CONSTRAINT fk_note_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE athlete_goals (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id  INT UNSIGNED NOT NULL,
    title       VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    target_date DATE NULL,
    status      ENUM('em_andamento','atingida','cancelada') NOT NULL DEFAULT 'em_andamento',
    achieved_at DATE NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_goals_athlete (athlete_id, status),
    CONSTRAINT fk_goal_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    CONSTRAINT fk_goal_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
