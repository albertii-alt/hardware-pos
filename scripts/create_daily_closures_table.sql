CREATE TABLE IF NOT EXISTS daily_closures (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    business_date           DATE NOT NULL,
    summary_json            LONGTEXT NOT NULL,
    payment_breakdown_json  LONGTEXT NOT NULL,
    best_sellers_json       LONGTEXT NOT NULL,
    low_stock_json          LONGTEXT NOT NULL,
    closed_by_user_id       INT NULL,
    closing_notes           TEXT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_date (business_date),
    INDEX idx_closed_by (closed_by_user_id),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_closure_user FOREIGN KEY (closed_by_user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
