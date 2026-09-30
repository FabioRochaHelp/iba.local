-- Uniformes, pagamentos e patrocínios

CREATE TABLE uniform_items (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(80) NOT NULL,
    price      DECIMAL(10,2) NOT NULL,
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_uniform_items_name (name),
    CONSTRAINT chk_uniform_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE uniform_orders (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id  INT UNSIGNED NOT NULL,
    item_id     SMALLINT UNSIGNED NOT NULL,
    size        VARCHAR(10) NULL,
    quantity    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    total       DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status      ENUM('pendente','pago_parcial','pago','entregue','cancelado') NOT NULL DEFAULT 'pendente',
    delivered   TINYINT(1) NOT NULL DEFAULT 0,
    ordered_at  DATE NOT NULL,
    notes       VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_uniform_orders_athlete (athlete_id),
    KEY idx_uniform_orders_status (status),
    CONSTRAINT fk_uo_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_uo_item FOREIGN KEY (item_id) REFERENCES uniform_items (id) ON DELETE RESTRICT,
    CONSTRAINT chk_uo_values CHECK (quantity > 0 AND total >= 0 AND paid_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id       INT UNSIGNED NULL,
    uniform_order_id INT UNSIGNED NULL,
    amount           DECIMAL(10,2) NOT NULL,
    paid_at          DATE NOT NULL,
    method           ENUM('pix','dinheiro','cartao','transferencia') NOT NULL DEFAULT 'pix',
    received_by      INT UNSIGNED NULL,
    notes            VARCHAR(255) NULL,
    reversed_at      DATETIME NULL,
    reversed_by      INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payments_invoice (invoice_id),
    KEY idx_payments_uniform (uniform_order_id),
    KEY idx_payments_paid_at (paid_at),
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payment_uniform FOREIGN KEY (uniform_order_id) REFERENCES uniform_orders (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payment_user FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_reverser FOREIGN KEY (reversed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_payment_amount CHECK (amount > 0),
    CONSTRAINT chk_payment_target CHECK ((invoice_id IS NULL) <> (uniform_order_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sponsors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    document   VARCHAR(14) NULL COMMENT 'CPF/CNPJ só dígitos',
    contact    VARCHAR(120) NULL,
    phone      VARCHAR(13) NULL,
    email      VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sponsors_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sponsorships (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sponsor_id  INT UNSIGNED NULL,
    athlete_id  INT UNSIGNED NULL COMMENT 'patrocínio destinado a um atleta',
    amount      DECIMAL(10,2) NOT NULL,
    received_at DATE NOT NULL,
    description VARCHAR(255) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sponsorships_date (received_at),
    CONSTRAINT fk_sp_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors (id) ON DELETE SET NULL,
    CONSTRAINT fk_sp_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE SET NULL,
    CONSTRAINT fk_sp_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_sp_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
