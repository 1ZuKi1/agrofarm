<?php
/**
 * products-admin.php — admin CRUD for the shop's products/variants/ingredients.
 *
 * Auth matches publish.php exactly: PHP session + CSRF token issued at login.
 * Unlike publish.php (plain text), this returns JSON — the data here is
 * structured (nested products/variants/ingredients), not a single string.
 */

require __DIR__ . '/db.php';

session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

if (empty($_SESSION['naf_admin'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Session expired — please log in again.']);
  exit;
}

$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['naf_csrf_token']) || !hash_equals($_SESSION['naf_csrf_token'], $csrf)) {
  http_response_code(403);
  echo json_encode(['error' => 'Invalid or missing security token — please log in again.']);
  exit;
}

$action = $_POST['action'] ?? '';
$pdo = naf_db();

if ($action === 'list') {
  $products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
  $variantStmt = $pdo->prepare('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order, id');
  $ingredientStmt = $pdo->prepare('SELECT * FROM variant_ingredients WHERE variant_id = ? ORDER BY sort_order, id');

  foreach ($products as &$product) {
    $variantStmt->execute([$product['id']]);
    $variants = $variantStmt->fetchAll();
    foreach ($variants as &$variant) {
      $ingredientStmt->execute([$variant['id']]);
      $variant['ingredients'] = $ingredientStmt->fetchAll();
    }
    unset($variant);
    $product['variants'] = $variants;
  }
  unset($product);

  echo json_encode(['products' => $products]);
  exit;
}

if ($action === 'save_product') {
  $id = $_POST['id'] ?? '';
  $slug = trim($_POST['slug'] ?? '');
  $nameMn = trim($_POST['name_mn'] ?? '');
  $nameEn = trim($_POST['name_en'] ?? '') ?: null;
  $descMn = trim($_POST['description_mn'] ?? '') ?: null;
  $descEn = trim($_POST['description_en'] ?? '') ?: null;
  $active = ($_POST['active'] ?? '1') === '1' ? 1 : 0;

  if ($nameMn === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Product name (Mongolian) is required']);
    exit;
  }
  if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Slug must be lowercase letters, numbers, and hyphens only (e.g. erdest-doloots)']);
    exit;
  }

  try {
    if ($id !== '') {
      $stmt = $pdo->prepare(
        'UPDATE products SET slug=?, name_mn=?, name_en=?, description_mn=?, description_en=?, active=? WHERE id=?'
      );
      $stmt->execute([$slug, $nameMn, $nameEn, $descMn, $descEn, $active, (int)$id]);
      echo json_encode(['id' => (int)$id]);
    } else {
      $stmt = $pdo->prepare(
        'INSERT INTO products (slug, name_mn, name_en, description_mn, description_en, active) VALUES (?, ?, ?, ?, ?, ?)'
      );
      $stmt->execute([$slug, $nameMn, $nameEn, $descMn, $descEn, $active]);
      echo json_encode(['id' => (int)$pdo->lastInsertId()]);
    }
  } catch (PDOException $e) {
    http_response_code(400);
    echo json_encode(['error' => str_contains($e->getMessage(), 'Duplicate entry')
      ? 'That slug is already in use by another product'
      : 'Could not save product']);
  }
  exit;
}

if ($action === 'save_variant') {
  $id = $_POST['id'] ?? '';
  $productId = (int)($_POST['product_id'] ?? 0);
  $nameMn = trim($_POST['name_mn'] ?? '');
  $nameEn = trim($_POST['name_en'] ?? '') ?: null;
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
    echo json_encode(['error' => 'product_id is required']);
    exit;
  }
  if ($nameMn === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Variant name (Mongolian) is required']);
    exit;
  }
  if ($price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Price must be zero or a positive whole number']);
    exit;
  }
  if ($stock < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Stock must be zero or a positive whole number']);
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
      echo json_encode(['id' => (int)$id]);
    } else {
      $stmt = $pdo->prepare(
        'INSERT INTO product_variants (product_id, name_mn, name_en, price, stock, weight_label,
         standard_code, storage_text_mn, benefits_text_mn,
         usage_text_mn, image_path, active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      $stmt->execute($fields);
      echo json_encode(['id' => (int)$pdo->lastInsertId()]);
    }
  } catch (PDOException $e) {
    http_response_code(400);
    echo json_encode(['error' => str_contains($e->getMessage(), 'foreign key')
      ? 'Could not save variant — check that the product still exists'
      : 'Could not save variant']);
  }
  exit;
}

if ($action === 'save_ingredients') {
  $variantId = (int)($_POST['variant_id'] ?? 0);
  $ingredientsJson = $_POST['ingredients'] ?? '[]';
  $ingredients = json_decode($ingredientsJson, true);

  if ($variantId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'variant_id is required']);
    exit;
  }
  if (!is_array($ingredients)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ingredients format']);
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
    echo json_encode(['error' => str_contains($e->getMessage(), 'foreign key')
      ? 'Could not save ingredients — check that the variant still exists'
      : 'Could not save ingredients']);
  }
  exit;
}

if ($action === 'delete_variant') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id is required']);
    exit;
  }
  $pdo->prepare('DELETE FROM product_variants WHERE id = ?')->execute([$id]);
  echo json_encode(['ok' => true]);
  exit;
}

if ($action === 'upload_image') {
  if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file received']);
    exit;
  }

  $file = $_FILES['image'];

  if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 5 MB)']);
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
    echo json_encode(['error' => 'Unsupported file — use JPG, PNG, WEBP or GIF']);
    exit;
  }
  $ext = $extByMime[$info['mime']];

  $dir = __DIR__ . '/img/products';
  if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create img/products folder — check permissions']);
    exit;
  }

  $name = 'product-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
  if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save image — check img/products folder permissions']);
    exit;
  }

  echo json_encode(['path' => 'img/products/' . $name]);
  exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
