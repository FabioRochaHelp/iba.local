-- Planos, mensalidades e pagamentos

CREATE TABLE plans (
    id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(80) NOT NULL,
    description   VARCHAR(255) NULL,
    days_per_week TINYINT UNSIGNED NOT NULL,
    monthly_fee   DECIMAL(10,2) NOT NULL,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plans_name (name),
    CONSTRAINT chk_plans_fee CHECK (monthly_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE athlete_plans (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id     INT UNSIGNED NOT NULL,
    plan_id        SMALLINT UNSIGNED NOT NULL,
    start_date     DATE NOT NULL,
    end_date       DATE NULL,
    discount_type  ENUM('nenhum','cortesia','percentual','valor') NOT NULL DEFAULT 'nenhum',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_athlete_plans_current (athlete_id, end_date),
    CONSTRAINT fk_apl_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    CONSTRAINT fk_apl_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT,
    CONSTRAINT chk_apl_discount CHECK (discount_value >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id      INT UNSIGNED NOT NULL,
    athlete_plan_id INT UNSIGNED NULL,
    reference_month DATE NOT NULL COMMENT 'sempre dia 01',
    amount          DECIMAL(10,2) NOT NULL,
    discount        DECIMAL(10,2) NOT NULL DEFAULT 0,
    final_amount    DECIMAL(10,2) NOT NULL,
    paid_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
    due_date        DATE NOT NULL,
    status          ENUM('aberta','paga','parcial','cortesia','cancelada') NOT NULL DEFAULT 'aberta',
    notes           VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_athlete_month (athlete_id, reference_month),
    KEY idx_invoices_month_status (reference_month, status),
    KEY idx_invoices_due (status, due_date),
    CONSTRAINT fk_invoice_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoice_plan FOREIGN KEY (athlete_plan_id) REFERENCES athlete_plans (id) ON DELETE SET NULL,
    CONSTRAINT chk_invoice_values CHECK (amount >= 0 AND discount >= 0 AND final_amount >= 0 AND paid_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
