-- hoof-care-seed.sql — the hoof-care range (туурай арчилгаа): 5 shop
-- sections, 31 product pages, 53 photos in img/equipment/naf-ta-*.jpg.
--
-- Run once via phpMyAdmin's SQL tab, after sql/equipment-schema.sql. Safe to
-- run again: categories and products are keyed on their unique slug / sku and
-- only refresh their text; the photos of these products are replaced.
-- Plain INSERT … ON DUPLICATE KEY UPDATE col = VALUES(col), which both MySQL
-- and MariaDB accept.
--
-- Like the rest of the equipment catalogue these have no price: buyers send
-- "Үнийн санал авах" and staff answer from /admin/quotes. One row per product
-- page; the supplier's individual models are rows of spec_table, under our
-- own codes (NAF-TA-…). The supplier's codes are kept in the internal mapping,
-- never here.

INSERT INTO categories (slug, name, parent_id, sort_order) VALUES
  ('tuurai-zasah-mashin', 'Туурай засах машин', NULL, 3),
  ('tuurai-zasah-disk', 'Туурай засах диск', NULL, 4),
  ('tuurain-hutga-bagaj', 'Туурайн хутга, багаж', NULL, 5),
  ('tuurain-devsger-tsavuu', 'Туурайн дэвсгэр, цавуу', NULL, 6),
  ('tuurain-archilgaa', 'Туурайн арчилгаа, ванн', NULL, 7)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-mashin'),
  'tuurai-zasah-mashin-4-irtei', 'NAF-TA-101', '4 иртэй туурай засах машин',
  '125 мм-ийн өнцөгт тээрэм дээр 4 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үхрийн туурайн илүүдэл эврийг хурдан, жигд авна. Дискний 3 загвараас сонгоно.',
  '["125 мм өнцөгт тээрэм, 850 Вт, 11 800 эрг/мин", "4 иртэй туурай засах диск суусан", "Ир нь солигддог хайлш ир"]',
  '{"columns": ["Код", "Суусан диск", "Дискний голч", "Дискний жин"], "rows": [["NAF-TA-101-01", "4 иртэй диск, загвар 1", "Ø120 мм", "310 г"], ["NAF-TA-101-02", "4 иртэй диск, загвар 2", "Ø100 мм", "300 г"], ["NAF-TA-101-03", "4 иртэй диск, загвар 3", "Ø100 мм", "180 г"]]}',
  '4 иртэй туурай засах машин — Нүүдэлчин Агро Ферм',
  '125 мм-ийн өнцөгт тээрэм дээр 4 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  1
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-mashin'),
  'tuurai-zasah-mashin-6-irtei', 'NAF-TA-102', '6 иртэй туурай засах машин',
  '125 мм-ийн өнцөгт тээрэм дээр 6 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үхрийн туурайн илүүдэл эврийг хурдан, жигд авна.',
  '["125 мм өнцөгт тээрэм, 850 Вт, 11 800 эрг/мин", "6 иртэй туурай засах диск суусан", "Ир нь солигддог хайлш ир"]',
  '{"columns": ["Код", "Суусан диск", "Дискний голч", "Дискний жин"], "rows": [["NAF-TA-102-01", "6 иртэй диск", "Ø120 мм", "330 г"]]}',
  '6 иртэй туурай засах машин — Нүүдэлчин Агро Ферм',
  '125 мм-ийн өнцөгт тээрэм дээр 6 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  2
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-mashin'),
  'tuurai-zasah-mashin-7-irtei', 'NAF-TA-103', '7 иртэй туурай засах машин',
  '125 мм-ийн өнцөгт тээрэм дээр 7 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үхрийн туурайн илүүдэл эврийг хурдан, жигд авна. Дискний 2 загвараас сонгоно.',
  '["125 мм өнцөгт тээрэм, 850 Вт, 11 800 эрг/мин", "7 иртэй туурай засах диск суусан", "Ир нь солигддог хайлш ир"]',
  '{"columns": ["Код", "Суусан диск", "Дискний голч", "Дискний жин"], "rows": [["NAF-TA-103-01", "7 иртэй диск, загвар 1", "Ø100 мм", "290 г"], ["NAF-TA-103-02", "7 иртэй диск, загвар 2", "Ø100 мм", "250 г"]]}',
  '7 иртэй туурай засах машин — Нүүдэлчин Агро Ферм',
  '125 мм-ийн өнцөгт тээрэм дээр 7 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  3
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-mashin'),
  'tuurai-zasah-mashin-8-irtei', 'NAF-TA-104', '8 иртэй туурай засах машин',
  '125 мм-ийн өнцөгт тээрэм дээр 8 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үхрийн туурайн илүүдэл эврийг хурдан, жигд авна. Дискний 2 загвараас сонгоно.',
  '["125 мм өнцөгт тээрэм, 850 Вт, 11 800 эрг/мин", "8 иртэй туурай засах диск суусан", "Ир нь солигддог хайлш ир"]',
  '{"columns": ["Код", "Суусан диск", "Дискний голч", "Дискний жин"], "rows": [["NAF-TA-104-01", "8 иртэй диск, загвар 1", "Ø100 мм", "260 г"], ["NAF-TA-104-02", "8 иртэй диск, загвар 2", "Ø100 мм", "254 г"]]}',
  '8 иртэй туурай засах машин — Нүүдэлчин Агро Ферм',
  '125 мм-ийн өнцөгт тээрэм дээр 8 иртэй туурай засах диск суурилуулсан бэлэн иж бүрдэл. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  4
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-mashin'),
  'ontsogt-teerem-125', 'NAF-TA-105', 'Өнцөгт тээрэм 125 мм',
  'Туурай засах диск, нунтаглах дискийг суурилуулах 125 мм-ийн өнцөгт тээрэм. Монголын цахилгааны сүлжээнд 220 В-ийн загварыг сонгоно.',
  '["Дискний голч 125 мм, төв нүх Ø22.2 мм", "Чадал 850 Вт", "11 800 эрг/мин", "Цэвэр жин 2.03 кг"]',
  '{"columns": ["Код", "Хүчдэл", "Давтамж", "Чадал", "Эргэлт", "Жин"], "rows": [["NAF-TA-105-01", "220 В", "50 Гц", "850 Вт", "11 800 эрг/мин", "2.03 кг"], ["NAF-TA-105-02", "110 В", "50 Гц", "850 Вт", "11 800 эрг/мин", "2.03 кг"]]}',
  'Өнцөгт тээрэм 125 мм — Нүүдэлчин Агро Ферм',
  'Туурай засах диск, нунтаглах дискийг суурилуулах 125 мм-ийн өнцөгт тээрэм. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  5
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-mashin'),
  'tsenegleh-ontsogt-teerem-100', 'NAF-TA-106', 'Цэнэглэдэг өнцөгт тээрэм 100 мм',
  'Утасгүй, батарейтай өнцөгт тээрэм. Цахилгаангүй хашаа, бэлчээрт туурай засахад тохиромжтой. 125 мм-ийн туурай засах диск суулгахад тээрмийн шилжүүлэгч жийрэг хэрэгтэй.',
  '["Батарей 20 В, 6 Ач", "8 500 эрг/мин", "Тэнхлэгийн нүх 16 мм", "Жин 2.4 кг"]',
  '{"columns": ["Код", "Тэжээл", "Эргэлт", "Тэнхлэгийн нүх", "Жин"], "rows": [["NAF-TA-106-01", "Батарей 20 В, 6 Ач", "8 500 эрг/мин", "16 мм", "2.4 кг"]]}',
  'Цэнэглэдэг өнцөгт тээрэм 100 мм — Нүүдэлчин Агро Ферм',
  'Утасгүй, батарейтай өнцөгт тээрэм. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  6
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-mashin'),
  'teermiin-jiireg-100-125', 'NAF-TA-107', 'Тээрмийн шилжүүлэгч жийрэг 100→125',
  '100 мм-ийн өнцөгт тээрэм дээр 125 мм-ийн тээрэмд зориулсан диск суулгах жийрэг, түлхүүрийн хамт.',
  '["100 мм-ийн тээрмийг 125 мм-ийн дисктэй ашиглана", "Хоёр жийрэг, түлхүүртэй"]',
  '{"columns": ["Код", "Бүрдэл"], "rows": [["NAF-TA-107-01", "2 жийрэг, түлхүүр"]]}',
  'Тээрмийн шилжүүлэгч жийрэг 100→125 — Нүүдэлчин Агро Ферм',
  '100 мм-ийн өнцөгт тээрэм дээр 125 мм-ийн тээрэмд зориулсан диск суулгах жийрэг, түлхүүрийн хамт. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  7
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-disk'),
  'disk-4-irtei', 'NAF-TA-108', '4 иртэй туурай засах диск',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 4 иртэй диск. Хайлш ир нь бүрэн бүрээстэй, хурц өнцөгтэй тул удаан хурц байна. 125 мм-ийн өнцөгт тээрэмд тохирно.',
  '["125 мм-ийн өнцөгт тээрэмд, төв нүх Ø22.2 мм", "Импортын хайлш ир, бүрэн бүрээстэй", "Ир нь солигддог"]',
  '{"columns": ["Код", "Загвар", "Дискний голч", "Төв нүх", "Тээрэм", "Жин"], "rows": [["NAF-TA-108-01", "Загвар 1", "Ø120 мм", "Ø22.2 мм", "125 мм", "310 г"], ["NAF-TA-108-02", "Загвар 2", "Ø100 мм", "Ø22.2 мм", "125 мм", "300 г"], ["NAF-TA-108-03", "Загвар 3", "Ø100 мм", "Ø22.2 мм", "125 мм", "180 г"]]}',
  '4 иртэй туурай засах диск — Нүүдэлчин Агро Ферм',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 4 иртэй диск. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  8
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-disk'),
  'disk-6-irtei', 'NAF-TA-109', '6 иртэй туурай засах диск',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 6 иртэй диск. Хайлш ир нь бүрэн бүрээстэй, хурц өнцөгтэй тул удаан хурц байна. 125 мм-ийн өнцөгт тээрэмд тохирно.',
  '["125 мм-ийн өнцөгт тээрэмд, төв нүх Ø22.2 мм", "Импортын хайлш ир, бүрэн бүрээстэй", "Ир нь солигддог"]',
  '{"columns": ["Код", "Загвар", "Дискний голч", "Төв нүх", "Тээрэм", "Жин"], "rows": [["NAF-TA-109-01", "Загвар 1", "Ø120 мм", "Ø22.2 мм", "125 мм", "330 г"]]}',
  '6 иртэй туурай засах диск — Нүүдэлчин Агро Ферм',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 6 иртэй диск. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  9
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-disk'),
  'disk-7-irtei', 'NAF-TA-110', '7 иртэй туурай засах диск',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 7 иртэй диск. Хайлш ир нь бүрэн бүрээстэй, хурц өнцөгтэй тул удаан хурц байна. 125 мм-ийн өнцөгт тээрэмд тохирно.',
  '["125 мм-ийн өнцөгт тээрэмд, төв нүх Ø22.2 мм", "Импортын хайлш ир, бүрэн бүрээстэй", "Ир нь солигддог"]',
  '{"columns": ["Код", "Загвар", "Дискний голч", "Төв нүх", "Тээрэм", "Жин"], "rows": [["NAF-TA-110-01", "Загвар 1", "Ø100 мм", "Ø22.2 мм", "125 мм", "290 г"], ["NAF-TA-110-02", "Загвар 2", "Ø100 мм", "Ø22.2 мм", "125 мм", "250 г"]]}',
  '7 иртэй туурай засах диск — Нүүдэлчин Агро Ферм',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 7 иртэй диск. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  10
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-disk'),
  'disk-8-irtei', 'NAF-TA-111', '8 иртэй туурай засах диск',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 8 иртэй диск. Хайлш ир нь бүрэн бүрээстэй, хурц өнцөгтэй тул удаан хурц байна. 125 мм-ийн өнцөгт тээрэмд тохирно.',
  '["125 мм-ийн өнцөгт тээрэмд, төв нүх Ø22.2 мм", "Импортын хайлш ир, бүрэн бүрээстэй", "Ир нь солигддог"]',
  '{"columns": ["Код", "Загвар", "Дискний голч", "Төв нүх", "Тээрэм", "Жин"], "rows": [["NAF-TA-111-01", "Загвар 1", "Ø100 мм", "Ø22.2 мм", "125 мм", "260 г"], ["NAF-TA-111-02", "Загвар 2", "Ø100 мм", "Ø22.2 мм", "125 мм", "254 г"]]}',
  '8 иртэй туурай засах диск — Нүүдэлчин Агро Ферм',
  'Туурайн илүүдэл эврийг авах, тэгшлэх 8 иртэй диск. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  11
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-disk'),
  'nuntaglah-disk', 'NAF-TA-112', 'Туурай нунтаглах диск',
  'Засаж дууссан туурайн гадаргууг тэгшилж өнгөлөх нунтаглах диск. 125 мм-ийн өнцөгт тээрэмд тохирно.',
  '["Дискний голч Ø100 мм, төв нүх Ø22.2 мм", "125 мм-ийн өнцөгт тээрэмд", "Жин 200 г"]',
  '{"columns": ["Код", "Загвар", "Дискний голч", "Төв нүх", "Тээрэм", "Жин"], "rows": [["NAF-TA-112-01", "Нунтаглах диск", "Ø100 мм", "Ø22.2 мм", "125 мм", "200 г"]]}',
  'Туурай нунтаглах диск — Нүүдэлчин Агро Ферм',
  'Засаж дууссан туурайн гадаргууг тэгшилж өнгөлөх нунтаглах диск. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  12
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurai-zasah-disk'),
  'disknii-solih-ir', 'NAF-TA-113', 'Дискний солих ир',
  'Туурай засах дискний солих ир. Импортын хайлш, бүрэн бүрээстэй, хурц өнцөгт иртэй тул дискээ шинээр авалгүй ирийг нь л солино.',
  '["Импортын хайлш", "Бүрэн бүрээстэй", "Хурц өнцөгт ир"]',
  '{"columns": ["Код", "Материал", "Боловсруулалт"], "rows": [["NAF-TA-113-01", "Импортын хайлш", "Бүрэн бүрээс, хурц өнцөг"]]}',
  'Дискний солих ир — Нүүдэлчин Агро Ферм',
  'Туурай засах дискний солих ир. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  13
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-hutga-bagaj'),
  'tuurain-hutga', 'NAF-TA-114', 'Туурайн хутга',
  'Туурайн ул, ховилыг цэвэрлэж засах гар хутга. Модон бариултай, зэвэрдэггүй ган иртэй. Зүүн гар, баруун гарт зориулсан хоёр хувилбартай.',
  '["Зэвэрдэггүй ган ир", "Модон бариул", "Урт 210 мм"]',
  '{"columns": ["Код", "Хувилбар", "Ир", "Урт", "Жин"], "rows": [["NAF-TA-114-01", "Зүүн гарт", "Зэвэрдэггүй ган", "210 мм", "80 г"], ["NAF-TA-114-02", "Баруун гарт", "Зэвэрдэггүй ган", "210 мм", "80 г"]]}',
  'Туурайн хутга — Нүүдэлчин Агро Ферм',
  'Туурайн ул, ховилыг цэвэрлэж засах гар хутга. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  14
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-hutga-bagaj'),
  'l-helbertei-hutga', 'NAF-TA-115', 'L хэлбэрийн туурайн хутга',
  'Өргөн, L хэлбэрийн иртэй туурайн хутга. Туурайн улыг тэгш авахад тохиромжтой. Зүүн гар, баруун гарт зориулсан хоёр хувилбартай.',
  '["Зэвэрдэггүй ган ир", "Модон бариул", "Урт 215 мм"]',
  '{"columns": ["Код", "Хувилбар", "Ир", "Урт", "Жин"], "rows": [["NAF-TA-115-01", "Зүүн гарт", "Зэвэрдэггүй ган", "215 мм", "110 г"], ["NAF-TA-115-02", "Баруун гарт", "Зэвэрдэггүй ган", "215 мм", "110 г"]]}',
  'L хэлбэрийн туурайн хутга — Нүүдэлчин Агро Ферм',
  'Өргөн, L хэлбэрийн иртэй туурайн хутга. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  15
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-hutga-bagaj'),
  'hutga-irlegch', 'NAF-TA-116', 'Туурайн хутганы ирлэгч',
  'Туурайн хутганы ирийг ирлэх ирлэгч. Хутгаа хурц байлгаснаар туурай засах ажил хурдан, аюулгүй болно.',
  '["Урт 250 мм", "Жин 110 г"]',
  '{"columns": ["Код", "Урт", "Жин"], "rows": [["NAF-TA-116-01", "250 мм", "110 г"]]}',
  'Туурайн хутганы ирлэгч — Нүүдэлчин Агро Ферм',
  'Туурайн хутганы ирийг ирлэх ирлэгч. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  16
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-hutga-bagaj'),
  'tuurai-zasah-hemjuur', 'NAF-TA-117', 'Туурай засах хэмжүүр',
  'Туурайн урт, өнцгийг шалгаж зөв хэмжээнд засахад туслах хуванцар хэмжүүр.',
  '["Голч Ø12 см", "Хуванцар"]',
  '{"columns": ["Код", "Хувилбар", "Хэмжээ"], "rows": [["NAF-TA-117-01", "Загвар 1", "Ø12 см"], ["NAF-TA-117-02", "Загвар 2", "Ø12 см"]]}',
  'Туурай засах хэмжүүр — Нүүдэлчин Агро Ферм',
  'Туурайн урт, өнцгийг шалгаж зөв хэмжээнд засахад туслах хуванцар хэмжүүр. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  17
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-hutga-bagaj'),
  'yamaanii-tuurai-havchaar', 'NAF-TA-118', 'Ямааны туурай засах хавчаар',
  'Ямаа, хонины туурайн илүүдэл эврийг тайрах хавчаар. SS304 зэвэрдэггүй ган.',
  '["SS304 зэвэрдэггүй ган", "Урт 40 см", "Жин 1.2 кг"]',
  '{"columns": ["Код", "Материал", "Урт", "Жин"], "rows": [["NAF-TA-118-01", "SS304 зэвэрдэггүй ган", "40 см", "1.2 кг"]]}',
  'Ямааны туурай засах хавчаар — Нүүдэлчин Агро Ферм',
  'Ямаа, хонины туурайн илүүдэл эврийг тайрах хавчаар. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  18
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-hutga-bagaj'),
  'olon-zoriulalttai-havchaar', 'NAF-TA-119', 'Олон зориулалттай туурайн хавчаар',
  'Туурайн илүүдэл эврийг тайрах олон зориулалттай хавчаар. Урт бариултай тул бага хүчээр хэрэглэнэ.',
  '["Урт 42 см", "Жин 1.2 кг"]',
  '{"columns": ["Код", "Урт", "Жин"], "rows": [["NAF-TA-119-01", "42 см", "1.2 кг"]]}',
  'Олон зориулалттай туурайн хавчаар — Нүүдэлчин Агро Ферм',
  'Туурайн илүүдэл эврийг тайрах олон зориулалттай хавчаар. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  19
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-hutga-bagaj'),
  'hol-bekhleh-degee', 'NAF-TA-120', 'Туурай засах дэгээ',
  'Туурай засах үед малын хөлийг өргөж бэхлэх оосортой дэгээ.',
  '["Хөлийг өргөж бэхэлнэ", "Жин 338 г"]',
  '{"columns": ["Код", "Зориулалт", "Жин"], "rows": [["NAF-TA-120-01", "Туурай засах үед хөл бэхлэх", "338 г"]]}',
  'Туурай засах дэгээ — Нүүдэлчин Агро Ферм',
  'Туурай засах үед малын хөлийг өргөж бэхлэх оосортой дэгээ. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  20
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-devsger-tsavuu'),
  'tuurain-devsger', 'NAF-TA-121', 'Туурайн дэвсгэр',
  'Өвчтэй хурууны ачааллыг бууруулахын тулд эрүүл хурууны ул дээр наадаг дэвсгэр. Модон, резин, EVA гэсэн гурван материалтай.',
  '["Модон, резин, EVA", "Туурайн цавуугаар наана"]',
  '{"columns": ["Код", "Материал", "Жин"], "rows": [["NAF-TA-121-01", "Модон", "77 г"], ["NAF-TA-121-02", "Резин", "132 г"], ["NAF-TA-121-03", "EVA", "25 г"]]}',
  'Туурайн дэвсгэр — Нүүдэлчин Агро Ферм',
  'Өвчтэй хурууны ачааллыг бууруулахын тулд эрүүл хурууны ул дээр наадаг дэвсгэр. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  21
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-devsger-tsavuu'),
  'tuurain-tsavuu', 'NAF-TA-122', 'Туурайн дэвсгэрийн цавуу',
  'Туурайн дэвсгэрийг наах хоёр бүрэлдэхүүнт цавуу. Цавууны буу, холих хошуугаар шахаж хэрэглэнэ. Бага хэмжээнд 4 мл-ийн нэг удаагийн савлагаатай.',
  '["200 мл, 210 мл, 4 мл", "Хоёр бүрэлдэхүүнт", "Цавууны буугаар хэрэглэнэ"]',
  '{"columns": ["Код", "Хувилбар", "Багтаамж", "Жин"], "rows": [["NAF-TA-122-01", "Ногоон сав", "200 мл", "312 г"], ["NAF-TA-122-02", "Ногоон сав", "210 мл", "353 г"], ["NAF-TA-122-03", "Шаргал сав", "210 мл", "317 г"], ["NAF-TA-122-04", "Нэг удаагийн", "4 мл", "—"]]}',
  'Туурайн дэвсгэрийн цавуу — Нүүдэлчин Агро Ферм',
  'Туурайн дэвсгэрийг наах хоёр бүрэлдэхүүнт цавуу. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  22
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-devsger-tsavuu'),
  'tsavuunii-buu', 'NAF-TA-123', 'Цавууны буу',
  'Хоёр бүрэлдэхүүнт туурайн цавууг холих хошуугаар жигд шахах буу.',
  '["Хоёр бүрэлдэхүүнт цавуунд", "Хоёр хэмжээтэй"]',
  '{"columns": ["Код", "Хувилбар", "Хэмжээ", "Жин"], "rows": [["NAF-TA-123-01", "Загвар 1", "17.5 см", "1.23 кг"], ["NAF-TA-123-02", "Загвар 2", "28 см", "1 кг"]]}',
  'Цавууны буу — Нүүдэлчин Агро Ферм',
  'Хоёр бүрэлдэхүүнт туурайн цавууг холих хошуугаар жигд шахах буу. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  23
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-devsger-tsavuu'),
  'tsavuu-holih-hoshuu', 'NAF-TA-124', 'Цавуу холих хошуу',
  'Хоёр бүрэлдэхүүнт цавууг шахах явцад холих хошуу. Нэг удаа хэрэглэнэ.',
  '["13.5 см ба 15.5 см", "Жин 9 г"]',
  '{"columns": ["Код", "Урт", "Жин"], "rows": [["NAF-TA-124-01", "13.5 см", "9 г"], ["NAF-TA-124-02", "15.5 см", "9 г"]]}',
  'Цавуу холих хошуу — Нүүдэлчин Агро Ферм',
  'Хоёр бүрэлдэхүүнт цавууг шахах явцад холих хошуу. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  24
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-devsger-tsavuu'),
  'tsavuu-halaah-tsuunkh', 'NAF-TA-125', 'Цавуу халаах цүнх',
  'Хүйтэн үед туурайн цавууг ажлын дулаанд байлгах халаагууртай цүнх.',
  '["Халаагууртай", "Цавууны савнууд багтана"]',
  '{"columns": ["Код", "Зориулалт"], "rows": [["NAF-TA-125-01", "Туурайн цавуу халаах"]]}',
  'Цавуу халаах цүнх — Нүүдэлчин Агро Ферм',
  'Хүйтэн үед туурайн цавууг ажлын дулаанд байлгах халаагууртай цүнх. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  25
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-devsger-tsavuu'),
  'naaldag-boolt', 'NAF-TA-126', 'Наалддаг боолт',
  'Туурай, хөлийг боох нэхмэл бус наалддаг боолт. Өөр дээрээ наалддаг тул хавчаар, тууз хэрэггүй.',
  '["Нэхмэл бус даавуу", "Ороомог 4.5 м", "Хайрцагт 12 ороомог"]',
  '{"columns": ["Код", "Өргөн", "Урт", "Савлагаа"], "rows": [["NAF-TA-126-01", "7.5 см", "4.5 м", "12 ороомог/хайрцаг"], ["NAF-TA-126-02", "10 см", "4.5 м", "12 ороомог/хайрцаг"]]}',
  'Наалддаг боолт — Нүүдэлчин Агро Ферм',
  'Туурай, хөлийг боох нэхмэл бус наалддаг боолт. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  26
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-archilgaa'),
  'tuurain-vann-3-hemjee', 'NAF-TA-127', 'Үнээний туурайн ванн',
  'Саалийн танхимын гарц дээр тавьж үнээг туурайн уусмалаар дамжуулах ванн. Импортын полиэтилен, удаан эдэлгээтэй. Гурван хэмжээтэй.',
  '["Импортын полиэтилен", "1230–2800 мм урттай", "Хос ванн хувилбартай"]',
  '{"columns": ["Код", "Хэмжээ (У×Ө×Ө)", "Жин"], "rows": [["NAF-TA-127-01", "1850×800×170 мм", "13.5 кг"], ["NAF-TA-127-02", "2800×900×190 мм", "20 кг"], ["NAF-TA-127-03", "1230×870×180 мм, хос", "14.2 кг"]]}',
  'Үнээний туурайн ванн — Нүүдэлчин Агро Ферм',
  'Саалийн танхимын гарц дээр тавьж үнээг туурайн уусмалаар дамжуулах ванн. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  27
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-archilgaa'),
  'tuurain-vannii-uusmal', 'NAF-TA-128', 'Туурайн ванны уусмал',
  'Туурайн ваннд шингэлж хэрэглэх уусмал. Зориулалтаас хамааран 2%, 10% эсвэл 30% шингэлнэ.',
  '["Багтаамж 5 л", "Шингэлэлт 2%, 10%, 30%"]',
  '{"columns": ["Код", "Багтаамж", "Жин", "Савны хэмжээ", "Шингэлэлт"], "rows": [["NAF-TA-128-01", "5 л", "8 кг", "20×29 см", "2%, 10%, 30%"]]}',
  'Туурайн ванны уусмал — Нүүдэлчин Агро Ферм',
  'Туурайн ваннд шингэлж хэрэглэх уусмал. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  28
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-archilgaa'),
  'tuurain-tos', 'NAF-TA-129', 'Туурайн арчилгааны тос',
  'Туурай засах, арчлах үед хэрэглэх тос.',
  '["Сав 450 г"]',
  '{"columns": ["Код", "Савлагаа"], "rows": [["NAF-TA-129-01", "450 г/сав"]]}',
  'Туурайн арчилгааны тос — Нүүдэлчин Агро Ферм',
  'Туурай засах, арчлах үед хэрэглэх тос. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  29
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-archilgaa'),
  'tuurai-shurshigch', 'NAF-TA-130', 'Туурай засах, эвэр авах шүршигч',
  'Туурай засах болон эвэр авсны дараа гадна талд шүршиж хэрэглэх шүршигч.',
  '["Сав 350 мл"]',
  '{"columns": ["Код", "Савлагаа"], "rows": [["NAF-TA-130-01", "350 мл"]]}',
  'Туурай засах, эвэр авах шүршигч — Нүүдэлчин Агро Ферм',
  'Туурай засах болон эвэр авсны дараа гадна талд шүршиж хэрэглэх шүршигч. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  30
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

INSERT INTO equipment_products
  (category_id, slug, sku, name, intro, features, spec_table, meta_title, meta_description, sort_order)
VALUES (
  (SELECT id FROM categories WHERE slug = 'tuurain-archilgaa'),
  'tuurain-nuntag', 'NAF-TA-131', 'Туурайн нунтаг',
  'Туурай засах үед гадна талд хэрэглэх нунтаг. Антибиотикгүй.',
  '["Сав 56 г", "Антибиотикгүй"]',
  '{"columns": ["Код", "Савлагаа"], "rows": [["NAF-TA-131-01", "56 г"]]}',
  'Туурайн нунтаг — Нүүдэлчин Агро Ферм',
  'Туурай засах үед гадна талд хэрэглэх нунтаг. Үнийн санал авах бол хүсэлтээ илгээнэ үү.',
  31
)
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), slug = VALUES(slug), name = VALUES(name), intro = VALUES(intro),
  features = VALUES(features), spec_table = VALUES(spec_table), meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), sort_order = VALUES(sort_order);

-- Photos: this range's are replaced on every run.
DELETE FROM equipment_images WHERE product_id IN (SELECT id FROM equipment_products WHERE sku LIKE 'NAF-TA-%');
INSERT INTO equipment_images (product_id, path, alt, sort_order)
  SELECT id, 'img/equipment/naf-ta-101-1.jpg', '4 иртэй туурай засах машин', 0 FROM equipment_products WHERE sku = 'NAF-TA-101'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-101-2.jpg', '4 иртэй туурай засах машин', 1 FROM equipment_products WHERE sku = 'NAF-TA-101'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-101-3.jpg', '4 иртэй туурай засах машин', 2 FROM equipment_products WHERE sku = 'NAF-TA-101'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-102-1.jpg', '6 иртэй туурай засах машин', 0 FROM equipment_products WHERE sku = 'NAF-TA-102'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-102-2.jpg', '6 иртэй туурай засах машин', 1 FROM equipment_products WHERE sku = 'NAF-TA-102'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-103-1.jpg', '7 иртэй туурай засах машин', 0 FROM equipment_products WHERE sku = 'NAF-TA-103'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-103-2.jpg', '7 иртэй туурай засах машин', 1 FROM equipment_products WHERE sku = 'NAF-TA-103'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-104-1.jpg', '8 иртэй туурай засах машин', 0 FROM equipment_products WHERE sku = 'NAF-TA-104'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-104-2.jpg', '8 иртэй туурай засах машин', 1 FROM equipment_products WHERE sku = 'NAF-TA-104'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-105-1.jpg', 'Өнцөгт тээрэм 125 мм', 0 FROM equipment_products WHERE sku = 'NAF-TA-105'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-105-2.jpg', 'Өнцөгт тээрэм 125 мм', 1 FROM equipment_products WHERE sku = 'NAF-TA-105'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-106-1.jpg', 'Цэнэглэдэг өнцөгт тээрэм 100 мм', 0 FROM equipment_products WHERE sku = 'NAF-TA-106'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-107-1.jpg', 'Тээрмийн шилжүүлэгч жийрэг 100→125', 0 FROM equipment_products WHERE sku = 'NAF-TA-107'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-108-1.jpg', '4 иртэй туурай засах диск', 0 FROM equipment_products WHERE sku = 'NAF-TA-108'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-108-2.jpg', '4 иртэй туурай засах диск', 1 FROM equipment_products WHERE sku = 'NAF-TA-108'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-108-3.jpg', '4 иртэй туурай засах диск', 2 FROM equipment_products WHERE sku = 'NAF-TA-108'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-109-1.jpg', '6 иртэй туурай засах диск', 0 FROM equipment_products WHERE sku = 'NAF-TA-109'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-110-1.jpg', '7 иртэй туурай засах диск', 0 FROM equipment_products WHERE sku = 'NAF-TA-110'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-110-2.jpg', '7 иртэй туурай засах диск', 1 FROM equipment_products WHERE sku = 'NAF-TA-110'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-111-1.jpg', '8 иртэй туурай засах диск', 0 FROM equipment_products WHERE sku = 'NAF-TA-111'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-111-2.jpg', '8 иртэй туурай засах диск', 1 FROM equipment_products WHERE sku = 'NAF-TA-111'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-112-1.jpg', 'Туурай нунтаглах диск', 0 FROM equipment_products WHERE sku = 'NAF-TA-112'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-113-1.jpg', 'Дискний солих ир', 0 FROM equipment_products WHERE sku = 'NAF-TA-113'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-114-1.jpg', 'Туурайн хутга', 0 FROM equipment_products WHERE sku = 'NAF-TA-114'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-114-2.jpg', 'Туурайн хутга', 1 FROM equipment_products WHERE sku = 'NAF-TA-114'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-115-1.jpg', 'L хэлбэрийн туурайн хутга', 0 FROM equipment_products WHERE sku = 'NAF-TA-115'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-115-2.jpg', 'L хэлбэрийн туурайн хутга', 1 FROM equipment_products WHERE sku = 'NAF-TA-115'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-116-1.jpg', 'Туурайн хутганы ирлэгч', 0 FROM equipment_products WHERE sku = 'NAF-TA-116'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-117-1.jpg', 'Туурай засах хэмжүүр', 0 FROM equipment_products WHERE sku = 'NAF-TA-117'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-117-2.jpg', 'Туурай засах хэмжүүр', 1 FROM equipment_products WHERE sku = 'NAF-TA-117'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-118-1.jpg', 'Ямааны туурай засах хавчаар', 0 FROM equipment_products WHERE sku = 'NAF-TA-118'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-119-1.jpg', 'Олон зориулалттай туурайн хавчаар', 0 FROM equipment_products WHERE sku = 'NAF-TA-119'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-120-1.jpg', 'Туурай засах дэгээ', 0 FROM equipment_products WHERE sku = 'NAF-TA-120'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-121-1.jpg', 'Туурайн дэвсгэр', 0 FROM equipment_products WHERE sku = 'NAF-TA-121'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-121-2.jpg', 'Туурайн дэвсгэр', 1 FROM equipment_products WHERE sku = 'NAF-TA-121'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-121-3.jpg', 'Туурайн дэвсгэр', 2 FROM equipment_products WHERE sku = 'NAF-TA-121'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-122-1.jpg', 'Туурайн дэвсгэрийн цавуу', 0 FROM equipment_products WHERE sku = 'NAF-TA-122'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-122-2.jpg', 'Туурайн дэвсгэрийн цавуу', 1 FROM equipment_products WHERE sku = 'NAF-TA-122'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-122-3.jpg', 'Туурайн дэвсгэрийн цавуу', 2 FROM equipment_products WHERE sku = 'NAF-TA-122'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-122-4.jpg', 'Туурайн дэвсгэрийн цавуу', 3 FROM equipment_products WHERE sku = 'NAF-TA-122'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-123-1.jpg', 'Цавууны буу', 0 FROM equipment_products WHERE sku = 'NAF-TA-123'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-123-2.jpg', 'Цавууны буу', 1 FROM equipment_products WHERE sku = 'NAF-TA-123'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-124-1.jpg', 'Цавуу холих хошуу', 0 FROM equipment_products WHERE sku = 'NAF-TA-124'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-124-2.jpg', 'Цавуу холих хошуу', 1 FROM equipment_products WHERE sku = 'NAF-TA-124'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-125-1.jpg', 'Цавуу халаах цүнх', 0 FROM equipment_products WHERE sku = 'NAF-TA-125'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-126-1.jpg', 'Наалддаг боолт', 0 FROM equipment_products WHERE sku = 'NAF-TA-126'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-127-1.jpg', 'Үнээний туурайн ванн', 0 FROM equipment_products WHERE sku = 'NAF-TA-127'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-127-2.jpg', 'Үнээний туурайн ванн', 1 FROM equipment_products WHERE sku = 'NAF-TA-127'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-127-3.jpg', 'Үнээний туурайн ванн', 2 FROM equipment_products WHERE sku = 'NAF-TA-127'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-128-1.jpg', 'Туурайн ванны уусмал', 0 FROM equipment_products WHERE sku = 'NAF-TA-128'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-129-1.jpg', 'Туурайн арчилгааны тос', 0 FROM equipment_products WHERE sku = 'NAF-TA-129'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-130-1.jpg', 'Туурай засах, эвэр авах шүршигч', 0 FROM equipment_products WHERE sku = 'NAF-TA-130'
UNION ALL
  SELECT id, 'img/equipment/naf-ta-131-1.jpg', 'Туурайн нунтаг', 0 FROM equipment_products WHERE sku = 'NAF-TA-131';
