<?php
/**
 * publish.php — writes news to the live server.
 *
 * Two actions, both requiring an active admin session (see login.php):
 *   • default        → writes the received JSON to news-data.json
 *   • action=upload  → saves an uploaded image into img/news/ and returns its path
 *
 * Auth is via the PHP session cookie, so no password travels in the request body.
 * Every request must also carry the CSRF token issued at login (see login.php);
 * requests without a matching token are rejected with 403.
 *
 * cPanel hosts run PHP by default — no setup needed.
 */

session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
header('Content-Type: text/plain; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method not allowed';
  exit;
}

// Must be logged in
if (empty($_SESSION['naf_admin'])) {
  http_response_code(401);
  echo 'Session expired — please log in again.';
  exit;
}

// CSRF: the token issued at login must accompany every state-changing request.
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['naf_csrf_token']) || !hash_equals($_SESSION['naf_csrf_token'], $csrf)) {
  http_response_code(403);
  echo 'Invalid or missing security token — please log in again.';
  exit;
}

$action = $_POST['action'] ?? 'publish';

/* ---------- Image upload ---------- */
if ($action === 'upload') {
  if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'No file received';
    exit;
  }

  $file = $_FILES['image'];

  if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo 'File too large (max 5 MB)';
    exit;
  }

  // Verify it is a real image and map to an extension
  $info = @getimagesize($file['tmp_name']);
  $extByMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
  ];
  if ($info === false || !isset($extByMime[$info['mime']])) {
    http_response_code(400);
    echo 'Unsupported file — use JPG, PNG, WEBP or GIF';
    exit;
  }
  $ext = $extByMime[$info['mime']];

  $dir = __DIR__ . '/img/news';
  if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    http_response_code(500);
    echo 'Could not create img/news folder — check permissions';
    exit;
  }

  $name = 'news-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
  if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
    http_response_code(500);
    echo 'Failed to save image — check img/news folder permissions';
    exit;
  }

  // Return the relative path for admin.js to store in the post
  echo 'img/news/' . $name;
  exit;
}

/* ---------- Publish news JSON ---------- */
$data = $_POST['data'] ?? '';

$decoded = json_decode($data);
if ($decoded === null || !isset($decoded->posts) || !is_array($decoded->posts)) {
  http_response_code(400);
  echo 'Invalid JSON format — expected { "posts": [...] }';
  exit;
}

if (file_put_contents(__DIR__ . '/news-data.json', $data) === false) {
  http_response_code(500);
  echo 'Failed to write news-data.json — check file permissions';
  exit;
}

echo 'ok';
