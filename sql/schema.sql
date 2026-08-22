-- Shop feature schema — products, variants, ingredients.
-- Orders/order_items tables are added later, in the checkout/payment plan.
--
-- Run once against the agrofhui_shop database (via phpMyAdmin's SQL tab,
-- or `mysql agrofhui_shop < sql/schema.sql` if you have shell access).

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name_mn VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NULL,
  description_mn TEXT NULL,
  description_en TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  name_mn VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NULL,
  price INT UNSIGNED NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  weight_label VARCHAR(50) NULL,
  standard_code VARCHAR(50) NULL,
  storage_text_mn TEXT NULL,
  storage_text_en TEXT NULL,
  benefits_text_mn TEXT NULL,
  benefits_text_en TEXT NULL,
  usage_text_mn TEXT NULL,
  usage_text_en TEXT NULL,
  image_path VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS variant_ingredients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  percentage VARCHAR(20) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_ingredient_variant FOREIGN KEY (variant_id)
    REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
