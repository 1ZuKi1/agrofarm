-- Orders schema v7 — remembers which address placed an order, so one visitor
-- cannot open unlimited unpaid orders and lock up the whole stock (2026-10).
-- Run once via phpMyAdmin's SQL tab. Safe to run before or after deploying
-- the matching code: until it is run, the checkout works but is not limited.
--
-- client_ip: the address the checkout request came from (IPv6 fits in 45).
-- The index serves the two checks in api/order-create.php: unpaid orders now
-- open from this address, and orders made from it in the last hour.

ALTER TABLE orders
  ADD COLUMN client_ip VARCHAR(45) NULL,
  ADD INDEX idx_orders_client_ip (client_ip, created_at);
