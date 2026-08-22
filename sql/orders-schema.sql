-- Orders schema — adds to the agrofhui_shop database Plan 1 created.
-- Run once via phpMyAdmin's SQL tab, same as sql/schema.sql was.

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_token VARCHAR(64) NOT NULL UNIQUE,
  status ENUM('pending','paid','fulfilled','expired','cancelled') NOT NULL DEFAULT 'pending',
  buyer_name VARCHAR(255) NOT NULL,
  buyer_phone VARCHAR(50) NOT NULL,
  buyer_address VARCHAR(500) NOT NULL,
  buyer_note TEXT NULL,
  delivery_date DATE NOT NULL,
  delivery_slot VARCHAR(20) NOT NULL,
  subtotal INT UNSIGNED NOT NULL,
  total INT UNSIGNED NOT NULL,
  qpay_invoice_id VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  fulfilled_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  variant_name_snapshot VARCHAR(255) NOT NULL,
  unit_price_snapshot INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total INT UNSIGNED NOT NULL,
  CONSTRAINT fk_orderitem_order FOREIGN KEY (order_id)
    REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_orderitem_variant FOREIGN KEY (variant_id)
    REFERENCES product_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
