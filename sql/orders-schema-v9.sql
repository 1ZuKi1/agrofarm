-- Orders schema v9 — remembers which address sent a quote request, so one
-- script cannot flood the request list and the shop's inbox (2026-10).
-- Run once via phpMyAdmin's SQL tab, after sql/orders-schema-v8.sql. Safe to
-- run before or after deploying the matching code: until it is run, quote
-- requests work but are not limited.
--
-- client_ip: the address the request came from (IPv6 fits in 45). The index
-- serves the check in api/inquiry-create.php: requests from this address in
-- the last hour.

ALTER TABLE inquiries
  ADD COLUMN client_ip VARCHAR(45) NULL,
  ADD INDEX idx_inquiries_client_ip (client_ip, created_at);
