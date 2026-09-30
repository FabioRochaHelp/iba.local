-- Cancelamento de mensalidades e motivo de estorno (trilha financeira completa)

ALTER TABLE invoices
    ADD COLUMN cancelled_at  DATETIME NULL AFTER notes,
    ADD COLUMN cancelled_by  INT UNSIGNED NULL AFTER cancelled_at,
    ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_by,
    ADD CONSTRAINT fk_invoice_canceller FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL;

ALTER TABLE payments
    ADD COLUMN reversal_reason VARCHAR(255) NULL AFTER reversed_by;
