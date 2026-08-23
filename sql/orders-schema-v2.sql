-- Orders schema v2 — adds qr_image/qr_text columns to the orders table.
-- Run once via phpMyAdmin's SQL tab, same as sql/orders-schema.sql was.
--
-- Persists the QPay invoice's QR data (base64 PNG + the raw QR payload
-- string) at order-creation time, so a buyer who navigates away mid-payment
-- (or revisits their shop.html?order=<token> link) can be shown the same QR
-- again without creating a second QPay invoice for the same order. See
-- order-create.php (writes these), order-status.php (returns them only
-- while status is still 'pending'), and shop.js (renders them).

ALTER TABLE orders
  ADD COLUMN qr_image MEDIUMTEXT NULL AFTER qpay_invoice_id,
  ADD COLUMN qr_text VARCHAR(1000) NULL AFTER qr_image;
