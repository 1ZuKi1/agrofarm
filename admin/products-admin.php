<?php
/**
 * products-admin.php — admin CRUD for the shop's products/variants/ingredients.
 *
 * Auth matches publish.php exactly: PHP session + CSRF token issued at login.
 * Unlike publish.php (plain text), this returns JSON — the data here is
 * structured (nested products/variants/ingredients), not a single string.
 */

require dirname(__DIR__) . '/inc/db.php';

require_once dirname(__DIR__) . '/inc/admin-auth.php';

// Auth: a logged-in account (inc/admin-auth.php) and this session's CSRF token.
admin_session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Метод зөвшөөрөгдөөгүй']);
  exit;
}
$me = admin_require(naf_db(), 'staff', true);
admin_check_csrf(true);

$action = $_POST['action'] ?? '';
$pdo = naf_db();

if ($action === 'list') {
  $products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
  $variantStmt = $pdo->prepare('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order, id');
  $ingredientStmt = $pdo->prepare('SELECT * FROM variant_ingredients WHERE variant_id = ? ORDER BY sort_order, id');
  // True availability is stock minus everything already reserved by an order
  // that hasn't been cancelled/expired — staff need to see both the raw
  // stock count and true availability. Reservation-set literal
  // ('pending','paid','fulfilled') must stay in sync with the same literal
  // in order-create.php and shop-data.php.
  $reservedStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.variant_id = ? AND o.status IN ('pending','paid','fulfilled')"
  );
  // Orders not finished yet (awaiting payment, or paid and not delivered):
  // an option in one of these can't be deleted (see delete_variant).
  $openStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.id) FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE oi.variant_id = ? AND o.status IN ('pending','paid')"
  );

  foreach ($products as &$product) {
    $variantStmt->execute([$product['id']]);
    $variants = $variantStmt->fetchAll();
    foreach ($variants as &$variant) {
      $ingredientStmt->execute([$variant['id']]);
      $variant['ingredients'] = $ingredientStmt->fetchAll();
      $reservedStmt->execute([$variant['id']]);
      $reserved = (int)$reservedStmt->fetchColumn();
      $variant['available'] = max(0, (int)$variant['stock'] - $reserved);
      $openStmt->execute([$variant['id']]);
      $variant['open_orders'] = (int)$openStmt->fetchColumn();
    }
    unset($variant);
    $product['variants'] = $variants;
  }
  unset($product);

  // For the product form's "Ангилал" dropdown.
  $categories = $pdo->query('SELECT id, name FROM categories WHERE active = 1 AND parent_id IS NULL ORDER BY sort_order, id')->fetchAll();

  echo json_encode(['products' => $products, 'categories' => $categories]);
  exit;
}

if ($action === 'save_product') {
  $id = $_POST['id'] ?? '';
  $slug = trim($_POST['slug'] ?? '');
  $nameMn = trim($_POST['name_mn'] ?? '');
  $nameEn = trim($_POST['name_en'] ?? '') ?: null;
  $descMn = trim($_POST['description_mn'] ?? '') ?: null;
  $descEn = trim($_POST['description_en'] ?? '') ?: null;
  // The editor no longer has English fields (the shop is Mongolian only), so
  // a save that doesn't send them keeps whatever is stored.
  if ($id !== '' && !array_key_exists('name_en', $_POST)) {
    $keep = $pdo->prepare('SELECT name_en, description_en FROM products WHERE id = ?');
    $keep->execute([(int)$id]);
    if ($row = $keep->fetch()) { $nameEn = $row['name_en']; $descEn = $row['description_en']; }
  }
  $active = ($_POST['active'] ?? '1') === '1' ? 1 : 0;
  // Which /shop section the product appears in ('' = none → "Бусад").
  // Only changed when the form sends the field: an admin.js from before the
  // category dropdown (cached in a staff browser after a deploy) doesn't, and
  // must not quietly move every product it saves into "Бусад".
  $setCategory = array_key_exists('category_id', $_POST);
  $categoryRaw = trim((string)($_POST['category_id'] ?? ''));
  $categoryId = null;
  if ($setCategory && $categoryRaw !== '') {
    $found = false;
    if (ctype_digit($categoryRaw)) {
      $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
      $catCheck->execute([(int)$categoryRaw]);
      $found = $catCheck->fetchColumn();
    }
    if ($found === false) {
      http_response_code(400);
      echo json_encode(['error' => 'Ангилал олдсонгүй']);
      exit;
    }
    $categoryId = (int)$found;
  }

  if ($nameMn === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Бүтээгдэхүүний нэр (Монгол) заавал шаардлагатай']);
    exit;
  }
  if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Slug зөвхөн жижиг үсэг, тоо, зураас агуулна (жишээ нь: erdest-doloots)']);
    exit;
  }

  try {
    if ($id !== '') {
      $stmt = $pdo->prepare(
        'UPDATE products SET slug=?, category_id=IF(?, ?, category_id), name_mn=?, name_en=?, description_mn=?, description_en=?, active=? WHERE id=?'
      );
      $stmt->execute([$slug, $setCategory ? 1 : 0, $categoryId, $nameMn, $nameEn, $descMn, $descEn, $active, (int)$id]);
      admin_log($pdo, $me, 'product_save', 'product', (int)$id, $nameMn);
      echo json_encode(['id' => (int)$id]);
    } else {
      $stmt = $pdo->prepare(
        'INSERT INTO products (slug, category_id, name_mn, name_en, description_mn, description_en, active) VALUES (?, ?, ?, ?, ?, ?, ?)'
      );
      $stmt->execute([$slug, $categoryId, $nameMn, $nameEn, $descMn, $descEn, $active]);
      $newId = (int)$pdo->lastInsertId();
      admin_log($pdo, $me, 'product_save', 'product', $newId, $nameMn . ' (шинэ)');
      echo json_encode(['id' => $newId]);
    }
  } catch (PDOException $e) {
    http_response_code(400);
    echo json_encode(['error' => strpos($e->getMessage(), 'Duplicate entry') !== false
      ? 'Энэ slug-ийг өөр бүтээгдэхүүн ашиглаж байна'
      : 'Бүтээгдэхүүн хадгалж чадсангүй']);
  }
  exit;
}

if ($action === 'save_variant') {
  $id = $_POST['id'] ?? '';
  $productId = (int)($_POST['product_id'] ?? 0);
  $nameMn = trim($_POST['name_mn'] ?? '');
  $nameEn = trim($_POST['name_en'] ?? '') ?: null;
  if ($id !== '' && !array_key_exists('name_en', $_POST)) { // no English field in the editor: keep it
    $keep = $pdo->prepare('SELECT name_en FROM product_variants WHERE id = ?');
    $keep->execute([(int)$id]);
    $nameEn = $keep->fetchColumn() ?: null;
  }
  $price = (int)($_POST['price'] ?? -1);
  $stock = (int)($_POST['stock'] ?? -1);
  $weightLabel = trim($_POST['weight_label'] ?? '') ?: null;
  $standardCode = trim($_POST['standard_code'] ?? '') ?: null;
  $storageTextMn = trim($_POST['storage_text_mn'] ?? '') ?: null;
  $benefitsTextMn = trim($_POST['benefits_text_mn'] ?? '') ?: null;
  $usageTextMn = trim($_POST['usage_text_mn'] ?? '') ?: null;
  $imagePath = trim($_POST['image_path'] ?? '') ?: null;
  $active = ($_POST['active'] ?? '1') === '1' ? 1 : 0;
  $sortOrder = (int)($_POST['sort_order'] ?? 0);

  if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id заавал шаардлагатай']);
    exit;
  }
  if ($nameMn === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Төрлийн нэр (Монгол) заавал шаардлагатай']);
    exit;
  }
  if ($price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Үнэ 0 буюу түүнээс их бүхэл тоо байх ёстой']);
    exit;
  }
  if ($stock < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Нөөц 0 буюу түүнээс их бүхэл тоо байх ёстой']);
    exit;
  }

  $fields = [
    $productId, $nameMn, $nameEn, $price, $stock, $weightLabel, $standardCode,
    $storageTextMn, $benefitsTextMn, $usageTextMn, $imagePath, $active, $sortOrder,
  ];

  try {
    if ($id !== '') {
      $stmt = $pdo->prepare(
        'UPDATE product_variants SET product_id=?, name_mn=?, name_en=?, price=?, stock=?, weight_label=?,
         standard_code=?, storage_text_mn=?, benefits_text_mn=?,
         usage_text_mn=?, image_path=?, active=?, sort_order=? WHERE id=?'
      );
      $stmt->execute([...$fields, (int)$id]);
      admin_log($pdo, $me, 'variant_save', 'variant', (int)$id, (string)$fields[1]);
      echo json_encode(['id' => (int)$id]);
    } else {
      $stmt = $pdo->prepare(
        'INSERT INTO product_variants (product_id, name_mn, name_en, price, stock, weight_label,
         standard_code, storage_text_mn, benefits_text_mn,
         usage_text_mn, image_path, active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      $stmt->execute($fields);
      $newId = (int)$pdo->lastInsertId();
      admin_log($pdo, $me, 'variant_save', 'variant', $newId, $fields[1] . ' (шинэ)');
      echo json_encode(['id' => $newId]);
    }
  } catch (PDOException $e) {
    http_response_code(400);
    echo json_encode(['error' => strpos($e->getMessage(), 'foreign key') !== false
      ? 'Төрөл хадгалж чадсангүй — бүтээгдэхүүн байгаа эсэхийг шалгана уу'
      : 'Төрөл хадгалж чадсангүй']);
  }
  exit;
}

// ---- Prices and stock of one product's options, from the product editor ----
// prices = JSON [{"id": 5, "price": 120000, "stock": 10}, …]; only options of
// that product, whole numbers ≥ 0. Each change is logged as variant_save.
if ($action === 'save_prices') {
  $productId = (int)($_POST['product_id'] ?? 0);
  $rows = json_decode((string)($_POST['prices'] ?? ''), true);
  if ($productId <= 0 || !is_array($rows)) {
    http_response_code(400);
    echo json_encode(['error' => 'Үнийн мэдээлэл буруу байна']);
    exit;
  }
  try {
    $own = $pdo->prepare('SELECT id, name_mn, price, stock FROM product_variants WHERE product_id = ?');
    $own->execute([$productId]);
    $byId = [];
    foreach ($own->fetchAll() as $v) $byId[(int)$v['id']] = $v;
    $clean = [];
    foreach ($rows as $r) {
      $vid = (int)($r['id'] ?? 0);
      $price = filter_var($r['price'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
      $stock = filter_var($r['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
      if (!isset($byId[$vid])) { http_response_code(400); echo json_encode(['error' => 'Төрөл олдсонгүй']); exit; }
      if ($price === false || $stock === false) {
        http_response_code(400);
        echo json_encode(['error' => $byId[$vid]['name_mn'] . ': үнэ, нөөц 0 буюу түүнээс их бүхэл тоо байна']);
        exit;
      }
      $clean[$vid] = [$price, $stock];
    }
    $up = $pdo->prepare('UPDATE product_variants SET price = ?, stock = ? WHERE id = ? AND product_id = ?');
    foreach ($clean as $vid => [$price, $stock]) {
      $old = $byId[$vid];
      if ((int)$old['price'] === $price && (int)$old['stock'] === $stock) continue;
      $up->execute([$price, $stock, $vid, $productId]);
      admin_log($pdo, $me, 'variant_save', 'variant', $vid,
        $old['name_mn'] . ': үнэ ' . (int)$old['price'] . ' → ' . $price . ', нөөц ' . (int)$old['stock'] . ' → ' . $stock);
    }
    echo json_encode(['ok' => true]);
  } catch (PDOException $e) {
    error_log('products-admin save_prices: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Серверийн алдаа гарлаа']);
  }
  exit;
}

if ($action === 'save_ingredients') {
  $variantId = (int)($_POST['variant_id'] ?? 0);
  $ingredientsJson = $_POST['ingredients'] ?? '[]';
  $ingredients = json_decode($ingredientsJson, true);

  if ($variantId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'variant_id заавал шаардлагатай']);
    exit;
  }
  if (!is_array($ingredients)) {
    http_response_code(400);
    echo json_encode(['error' => 'Найрлагын формат буруу байна']);
    exit;
  }

  try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM variant_ingredients WHERE variant_id = ?')->execute([$variantId]);
    $insert = $pdo->prepare('INSERT INTO variant_ingredients (variant_id, name, percentage, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($ingredients as $i => $ing) {
      $name = trim($ing['name'] ?? '');
      $percentage = trim($ing['percentage'] ?? '');
      if ($name === '' || $percentage === '') continue;
      $insert->execute([$variantId, $name, $percentage, $i]);
    }
    $pdo->commit();

    echo json_encode(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollback();
    http_response_code(400);
    echo json_encode(['error' => strpos($e->getMessage(), 'foreign key') !== false
      ? 'Найрлага хадгалж чадсангүй — төрөл байгаа эсэхийг шалгана уу'
      : 'Найрлага хадгалж чадсангүй']);
  }
  exit;
}

/**
 * Orders not finished yet that contain these options. Deleting is refused
 * while there are any: staff still have to deliver them, or the buyer may
 * still pay. Returns the order numbers ("#000128").
 */
function open_orders_for(PDO $pdo, array $variantIds): array {
  if (!$variantIds) return [];
  $in = implode(',', array_map('intval', $variantIds));
  $ids = $pdo->query("SELECT DISTINCT o.id FROM order_items oi JOIN orders o ON o.id = oi.order_id
                      WHERE oi.variant_id IN ($in) AND o.status IN ('pending','paid') ORDER BY o.id")->fetchAll(PDO::FETCH_COLUMN);
  return array_map(function ($id) { return '#' . str_pad((string)$id, 6, '0', STR_PAD_LEFT); }, $ids);
}

/**
 * Delete a product (with all its options) or one option. Past orders keep
 * their own copy of the name and price, so they and the Борлуулалт report
 * read the same afterwards. Until sql/orders-schema-v8.sql has been run, an
 * option that was ever ordered can't be deleted (the database refuses).
 */
if ($action === 'delete_variant' || $action === 'delete_product') {
  $id = (int)($_POST['id'] ?? 0);
  $isProduct = $action === 'delete_product';
  $row = $pdo->prepare($isProduct ? 'SELECT id, name_mn FROM products WHERE id = ?'
                                  : 'SELECT v.id, v.name_mn, p.name_mn AS product FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?');
  $row->execute([$id]);
  $item = $row->fetch();
  if (!$item) {
    http_response_code(404);
    echo json_encode(['error' => $isProduct ? 'Бүтээгдэхүүн олдсонгүй' : 'Төрөл олдсонгүй']);
    exit;
  }
  if ($isProduct) {
    $v = $pdo->prepare('SELECT id FROM product_variants WHERE product_id = ?');
    $v->execute([$id]);
    $variantIds = $v->fetchAll(PDO::FETCH_COLUMN);
  } else {
    $variantIds = [$id];
  }
  $open = open_orders_for($pdo, $variantIds);
  if ($open) {
    http_response_code(409);
    echo json_encode(['error' => 'Дуусаагүй захиалгад байгаа тул одоо устгах боломжгүй: ' . implode(', ', $open)
      . '. Захиалгыг хүргэсний (эсвэл цуцалсны) дараа устгана уу.']);
    exit;
  }
  $name = $isProduct ? $item['name_mn'] : $item['product'] . ' — ' . $item['name_mn'];
  try {
    $pdo->prepare($isProduct ? 'DELETE FROM products WHERE id = ?' : 'DELETE FROM product_variants WHERE id = ?')->execute([$id]);
    admin_log($pdo, $me, $isProduct ? 'product_delete' : 'variant_delete', $isProduct ? 'product' : 'variant', $id, $name);
    echo json_encode(['ok' => true]);
  } catch (PDOException $e) {
    error_log('products-admin ' . $action . ': ' . $e->getMessage());
    http_response_code(409);
    echo json_encode(['error' => strpos($e->getMessage(), 'foreign key') !== false
      ? 'Өмнө нь зарагдсан тул устгаж чадсангүй. phpMyAdmin дээр sql/orders-schema-v8.sql-ийг нэг удаа ажиллуулсны дараа устгах боломжтой. Түр зуур: «Засах» → «Дэлгүүрт харуулах»-ыг унтраавал дэлгүүрээс нуугдана.'
      : 'Устгаж чадсангүй']);
  }
  exit;
}

if ($action === 'upload_image') {
  if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Файл ирсэнгүй']);
    exit;
  }

  $file = $_FILES['image'];

  if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Файл хэт том байна (дээд тал нь 5 MB)']);
    exit;
  }

  $info = @getimagesize($file['tmp_name']);
  $extByMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
  ];
  if ($info === false || !isset($extByMime[$info['mime']])) {
    http_response_code(400);
    echo json_encode(['error' => 'Дэмжигдэхгүй файл — JPG, PNG, WEBP эсвэл GIF ашиглана уу']);
    exit;
  }
  $ext = $extByMime[$info['mime']];

  $dir = dirname(__DIR__) . '/img/products'; // site root, not admin/
  if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'img/products фолдер үүсгэж чадсангүй — зөвшөөрлийг шалгана уу']);
    exit;
  }

  $name = 'product-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
  if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
    http_response_code(500);
    echo json_encode(['error' => 'Зураг хадгалж чадсангүй — img/products фолдерийн зөвшөөрлийг шалгана уу']);
    exit;
  }

  echo json_encode(['path' => 'img/products/' . $name]);
  exit;
}

http_response_code(400);
echo json_encode(['error' => 'Тодорхойгүй үйлдэл']);
