-- Orders schema v4 — delivery or pick-up, chosen by day (2026-10 checkout).
-- Run once via phpMyAdmin's SQL tab, same as sql/orders-schema-v3.sql was.
-- RUN THIS BEFORE deploying the matching order-create.php / shop-checkout.php,
-- or checkout will fail on the missing columns and table.
--
-- fulfillment:   'delivery' (Хүргүүлэх) or 'pickup' (Очиж авах). Old orders
--                were all deliveries, hence the default.
-- delivery_fee:  what the buyer paid for delivery, part of `total`
--                (`subtotal` stays the goods only). 0 for pick-up.
-- delivery_slot: the old 10–12 / 14–16 / 16–18 time slots are gone — the
--                buyer picks a day and staff phone to agree the time. Old
--                orders keep their slot; new ones store NULL.
-- buyer_address: pick-up orders have none.
-- settings:      values staff change in /admin/ → Тохиргоо: the delivery fee
--                and the closed days (a JSON list of 'YYYY-MM-DD' dates,
--                e.g. Цагаан сар, Наадам), which are skipped like weekends.

ALTER TABLE orders
  ADD COLUMN fulfillment ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery' AFTER buyer_note,
  ADD COLUMN delivery_fee INT UNSIGNED NOT NULL DEFAULT 0 AFTER subtotal,
  MODIFY delivery_slot VARCHAR(20) NULL,
  MODIFY buyer_address VARCHAR(500) NULL;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v) VALUES ('delivery_fee', '8000'), ('closed_days', '[]');
