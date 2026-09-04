-- equipment-schema.sql — the equipment catalogue's own tables.
--
-- Run once against the agrofhui_shop database via phpMyAdmin's SQL tab, the
-- same way sql/schema.sql and sql/orders-schema.sql were.
--
-- Deliberately separate from the dairy shop's products/product_variants/orders
-- tables. Equipment has no price, no stock, no cart and no delivery slot, so
-- sharing those tables would mean a nullable column for every difference and
-- two meanings for every row. Nothing in this file touches the dairy schema.
--
-- All product text is Mongolian only — the buyers are Mongolian and the site
-- shows no English for these. Adding English later is an ALTER TABLE, not a
-- redesign.

-- ---------------------------------------------------------------------------
-- Two-level category tree: parent_id NULL is a top-level category, otherwise
-- it names its parent. Nothing deeper — the catalogue's own index is two
-- levels, and a third would be structure nobody asked for.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  parent_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_category_parent FOREIGN KEY (parent_id)
    REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- One row per product PAGE, not per part number. The individual model codes
-- live inside spec_table as rows — that is what keeps ~1000 supplier codes
-- from becoming ~1000 records.
--
-- features    JSON array of strings:  ["Багтаамж 8 л", "PP материал"]
-- spec_table  JSON object:            {"columns":["Код","Нэр","Материал"],
--                                      "rows":[["NAF-TU-104-01","...","PP"]]}
--
-- Stored as LONGTEXT, not the JSON column type: this host runs PHP 7.4 on
-- cPanel and the MariaDB version is not guaranteed to accept JSON columns.
-- PHP validates on write with json_decode(); MySQL is only asked to hold it.
--
-- sku is our own code (NAF-SA-101). The supplier's code is never stored here
-- and never rendered — the mapping lives in the internal spreadsheet only.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipment_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sku VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  intro TEXT NULL,
  features LONGTEXT NULL,
  spec_table LONGTEXT NULL,
  meta_title VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eqproduct_category FOREIGN KEY (category_id)
    REFERENCES categories(id),
  INDEX idx_eqproduct_category (category_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Gallery. sort_order 0 is the card thumbnail used in the category grid.
-- path is relative to the web root, e.g. 'img/equipment/calf-hutch-01.jpg'.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipment_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  alt VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_eqimage_product FOREIGN KEY (product_id)
    REFERENCES equipment_products(id) ON DELETE CASCADE,
  INDEX idx_eqimage_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Inquiries. The row is written BEFORE the email is sent, so a mail() failure
-- on shared hosting loses a notification, never the customer.
--
-- public_token lets a buyer revisit their own inquiry by link without an
-- account, the same pattern orders.public_token already uses.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_token VARCHAR(64) NOT NULL UNIQUE,
  status ENUM('new','replied','closed') NOT NULL DEFAULT 'new',
  buyer_name VARCHAR(255) NOT NULL,
  buyer_phone VARCHAR(50) NOT NULL,
  buyer_email VARCHAR(255) NULL,
  buyer_company VARCHAR(255) NULL,
  message TEXT NULL,
  emailed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  replied_at DATETIME NULL,
  INDEX idx_inquiry_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- The name and SKU are snapshotted the way order_items snapshots its variant
-- name and price: renaming a product later must not rewrite what somebody
-- actually asked about.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inquiry_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name_snapshot VARCHAR(255) NOT NULL,
  sku_snapshot VARCHAR(40) NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_inqitem_inquiry FOREIGN KEY (inquiry_id)
    REFERENCES inquiries(id) ON DELETE CASCADE,
  CONSTRAINT fk_inqitem_product FOREIGN KEY (product_id)
    REFERENCES equipment_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- The five top-level categories. Sub-categories and products are loaded from
-- the reviewed spreadsheet, not hardcoded here.
-- ---------------------------------------------------------------------------
INSERT INTO categories (slug, name, parent_id, sort_order) VALUES
  ('milking-parts',   'Саалтуурын эд анги',              NULL, 1),
  ('calf-feeding',    'Тугал, хургыг тэжээх хэрэгсэл',   NULL, 2),
  ('marking',         'Тэмдэглэгээний хэрэгсэл',         NULL, 3),
  ('cooling-washing', 'Хөргөлт, цэвэрлэгээний хэрэгсэл', NULL, 4),
  ('poultry',         'Шувууны тэжээгч',                 NULL, 5)
-- Row alias instead of VALUES(col): VALUES(col) inside ON DUPLICATE KEY UPDATE
-- is deprecated as of MySQL 8.0.20 and warns on every run. Needs MySQL 8.0.19+
-- (MariaDB does not support this form).
AS newrow
ON DUPLICATE KEY UPDATE name = newrow.name, sort_order = newrow.sort_order;
