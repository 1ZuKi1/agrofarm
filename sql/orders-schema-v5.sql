-- Orders schema v5 — one category list for every item in the shop (2026-10).
-- Run once via phpMyAdmin's SQL tab, AFTER sql/orders-schema-v4.sql and
-- BEFORE deploying the matching shop.php, or /shop shows its "could not load"
-- block (product pages and checkout keep working either way).
--
-- products.category_id: priced (QPay) products now sit in the same
--   `categories` list as the equipment, so /shop shows one section per
--   category with one card style. NULL = no category (shown last, "Бусад").
-- Two new categories come first: Малын тэжээл, эрдэс and Бэлэг дурсгал; the
--   equipment groups keep their order after them (+10).
-- Эрдэст Долооц moves into Малын тэжээл, эрдэс.
-- Two products are added with placeholder text and stock 10 — staff edit the
--   text, stock and photos in /admin/ → Бүтээгдэхүүн.
--
-- Only the ALTER TABLE is run-once (a second run stops there, harmlessly).
-- Everything after it can be re-run without creating duplicates.

ALTER TABLE products
  ADD COLUMN category_id INT UNSIGNED NULL AFTER slug,
  ADD CONSTRAINT fk_product_category FOREIGN KEY (category_id)
    REFERENCES categories(id) ON DELETE SET NULL;

UPDATE categories SET sort_order = sort_order + 10
 WHERE slug NOT IN ('mal-tejeel-erdes', 'beleg-dursgal') AND sort_order < 10;

INSERT IGNORE INTO categories (slug, name, parent_id, sort_order) VALUES
  ('mal-tejeel-erdes', 'Малын тэжээл, эрдэс', NULL, 1),
  ('beleg-dursgal',    'Бэлэг дурсгал',       NULL, 2);

UPDATE products
   SET category_id = (SELECT id FROM categories WHERE slug = 'mal-tejeel-erdes')
 WHERE slug = 'erdest-doloots' AND category_id IS NULL;

INSERT IGNORE INTO products (slug, category_id, name_mn, description_mn) VALUES
  ('uneenii-barimal', (SELECT id FROM categories WHERE slug = 'beleg-dursgal'),
   'Үнээний баримал', 'Нүүдэлчин Агро Фермийн үнээний баримал — бэлэг дурсгалд тохиромжтой.'),
  ('nom', (SELECT id FROM categories WHERE slug = 'beleg-dursgal'),
   'Ном', 'Нүүдэлчин Агро Фермийн ном.');

-- One option each, named like the product (the basket and the QPay invoice
-- then show the name once). Added only if the product has no option yet.
INSERT INTO product_variants (product_id, name_mn, price, stock, sort_order)
SELECT p.id, 'Үнээний баримал', 120000, 10, 0 FROM products p
 WHERE p.slug = 'uneenii-barimal'
   AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id);

INSERT INTO product_variants (product_id, name_mn, price, stock, sort_order)
SELECT p.id, 'Ном', 150000, 10, 0 FROM products p
 WHERE p.slug = 'nom'
   AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id);
