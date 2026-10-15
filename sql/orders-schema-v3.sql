-- Orders schema v3 — adds qpay_urls/qpay_checked_at to the orders table.
-- Run once via phpMyAdmin's SQL tab, same as sql/orders-schema-v2.sql was.
-- RUN THIS BEFORE deploying the matching order-create.php / order-status.php,
-- or checkout will fail on the missing columns.
--
-- qpay_urls: the bank-app deep links QPay returns with each invoice
--   ([{name, logo, link}], trimmed in order-create.php), so phone buyers can
--   open their bank app directly — they can't scan a QR on their own screen.
-- qpay_checked_at: when order-status.php last asked QPay about this order.
--   QPay forbids frequent payment checks, so a check only runs if this is
--   NULL or older than 10 seconds (claimed atomically with one UPDATE).

ALTER TABLE orders
  ADD COLUMN qpay_urls MEDIUMTEXT NULL AFTER qr_text,
  ADD COLUMN qpay_checked_at DATETIME NULL AFTER qpay_urls;
