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

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
