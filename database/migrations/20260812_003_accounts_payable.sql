ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS outstanding_balance DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER approval_status;

CREATE TABLE IF NOT EXISTS supplier_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_document VARCHAR(100) NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NULL,
    goods_receipt_id BIGINT UNSIGNED NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','approved','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_invoice_due (due_date,status),
    INDEX idx_supplier_invoice_supplier (supplier_id),
    CONSTRAINT fk_si_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_si_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_si_grn FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE SET NULL,
    CONSTRAINT fk_si_approved FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_si_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_invoice_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('bank_transfer','card','cash','cheque') NOT NULL,
    reference_number VARCHAR(100) NULL,
    status ENUM('pending','cleared','failed','reversed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    paid_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_payment_invoice (supplier_invoice_id),
    CONSTRAINT fk_sp_invoice FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices(id),
    CONSTRAINT fk_sp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_sp_user FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO chart_of_accounts (account_code, account_name, account_type, status)
SELECT '1400', 'Input GST Credit', 'asset', 'active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='1400');

INSERT INTO chart_of_accounts (account_code, account_name, account_type, status)
SELECT '2050', 'Goods Received Not Invoiced', 'liability', 'active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='2050');

INSERT INTO chart_of_accounts (account_code, account_name, account_type, status)
SELECT '5200', 'Cost of Goods Sold', 'expense', 'active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='5200');
