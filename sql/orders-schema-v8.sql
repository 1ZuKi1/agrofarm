-- Orders schema v8 — lets staff delete a product or one of its options
-- (төрөл) that has already been sold (2026-10).
-- Run once via phpMyAdmin's SQL tab, after sql/orders-schema-v7.sql. Safe to
-- run before or after deploying the matching code: until it is run, only
-- items that were never ordered can be deleted, and the admin says so.
--
-- A sold option used to be undeletable: each order line pointed at it.
-- Order lines keep their own copy of the name and price
-- (variant_name_snapshot, unit_price_snapshot), and the order pages and the
-- Борлуулалт report read those copies, so the link can simply be emptied:
-- deleting an option now sets variant_id to NULL on its old order lines.

ALTER TABLE order_items DROP FOREIGN KEY fk_orderitem_variant;
ALTER TABLE order_items MODIFY variant_id INT UNSIGNED NULL;
ALTER TABLE order_items
  ADD CONSTRAINT fk_orderitem_variant FOREIGN KEY (variant_id)
    REFERENCES product_variants(id) ON DELETE SET NULL;
