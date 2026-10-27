-- Orders schema v10 — shop photos for the first three priced products (2026-10).
-- Run via phpMyAdmin's SQL tab AFTER the git push that deploys img/shop/
-- (otherwise the shop shows broken images until the push lands).
-- Safe to re-run. Sets the photo on every option of each product; a photo
-- uploaded later in /admin/ → Бүтээгдэхүүн simply replaces it.

UPDATE product_variants v JOIN products p ON p.id = v.product_id
   SET v.image_path = 'img/shop/erdest-doloots.jpg'
 WHERE p.slug = 'erdest-doloots';

UPDATE product_variants v JOIN products p ON p.id = v.product_id
   SET v.image_path = 'img/shop/uneenii-barimal.jpg'
 WHERE p.slug = 'uneenii-barimal';

UPDATE product_variants v JOIN products p ON p.id = v.product_id
   SET v.image_path = 'img/shop/nom.jpg'
 WHERE p.slug = 'nom';
