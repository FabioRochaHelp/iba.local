-- Entrega/cancelamento de pedidos de uniforme; status e observações de patrocinadores

ALTER TABLE uniform_orders
    ADD COLUMN delivered_at  DATETIME NULL AFTER delivered,
    ADD COLUMN delivered_by  INT UNSIGNED NULL AFTER delivered_at,
    ADD COLUMN cancelled_at  DATETIME NULL AFTER notes,
    ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_at,
    ADD CONSTRAINT fk_uo_deliverer FOREIGN KEY (delivered_by) REFERENCES users (id) ON DELETE SET NULL;

ALTER TABLE uniform_items
    ADD COLUMN sizes VARCHAR(255) NULL AFTER price;

ALTER TABLE sponsors
    ADD COLUMN notes  TEXT NULL AFTER email,
    ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER notes;

ALTER TABLE sponsorships
    ADD COLUMN method ENUM('pix','dinheiro','cartao','transferencia','produto') NOT NULL DEFAULT 'pix' AFTER received_at;
