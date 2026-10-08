-- Run once (phpMyAdmin). Take a DB backup first.
-- Append-only evidence log: one row per fact about an order. Rows are hash-chained per order,
-- so any later edit/deletion of a row is detectable.
CREATE TABLE IF NOT EXISTS order_evidence (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT NOT NULL,
  event_type  VARCHAR(40) NOT NULL,
  actor       VARCHAR(20) NOT NULL DEFAULT 'system',   -- customer | admin | system | gateway
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  details     TEXT NULL,                                -- JSON
  prev_hash   CHAR(64) NOT NULL,
  row_hash    CHAR(64) NOT NULL,
  created_at  DATETIME NOT NULL,
  KEY idx_order (order_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Token for the customer's private "view your UPC" link (proof the customer accessed delivery).
ALTER TABLE order_items ADD COLUMN upc_view_token CHAR(32) NULL;
ALTER TABLE order_items ADD COLUMN upc_delivered_at DATETIME NULL;
