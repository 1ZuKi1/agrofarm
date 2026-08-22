<?php
// Temporary utility — generates a bcrypt hash for a POSTed password.
// No secret is stored in this file; delete it immediately after use.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}
header('Content-Type: text/plain; charset=utf-8');
echo password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
