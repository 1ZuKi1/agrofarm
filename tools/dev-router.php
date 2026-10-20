<?php
// Local testing only (start-local.bat): PHP's built-in server ignores
// .htaccess, so this repeats its clean-URL rules. Not deployed — tools/ is
// not in .cpanel.yml.
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('#^/(inc|lang)(/|$)#', $path)) { http_response_code(403); exit; }
if (is_file($root . $path)) return false; // real files: served as they are

$routes = [
  '#^/news/?$#'                                     => 'news.php',
  '#^/shop/?$#'                                     => 'shop.php',
  '#^/shop/equipment/([a-z0-9-]+)/?$#'              => 'category.php?cat=$1',
  '#^/shop/equipment/([a-z0-9-]+)/([a-z0-9-]+)/?$#' => 'product.php?cat=$1&slug=$2',
  '#^/shop/checkout/?$#'                            => 'shop-checkout.php',
  '#^/shop/([a-z0-9-]+)/?$#'                        => 'shop-product.php?slug=$1',
  '#^/admin/(orders|sales|quotes|products|news|settings|account|help|login|logout)/?$#' => 'admin/$1.php',
  '#^/admin/orders/([0-9]+)/?$#'                    => 'admin/order.php?id=$1',
  '#^/admin/?$#'                                    => 'admin/index.php',
  '#^/(shop-data|order-create|order-status|inquiry-create)\.php$#' => 'api/$1.php',
  '#^/(login|logout|publish|products-admin)\.php$#' => 'admin/$1.php',
];
$redirects = [
  '#^/equipment(/.*)?$#'   => '/shop/equipment$1',
  '#^/shop/equipment/?$#'  => '/shop',
];
foreach ($redirects as $re => $to) {
  if (preg_match($re, $path)) { header('Location: ' . preg_replace($re, $to, $path), true, 301); exit; }
}
foreach ($routes as $re => $to) {
  if (!preg_match($re, $path)) continue;
  $target = preg_replace($re, $to, $path);
  [$file, $qs] = array_pad(explode('?', $target, 2), 2, '');
  if ($qs !== '') { parse_str($qs, $extra); $_GET = $extra + $_GET; $_REQUEST = $extra + $_REQUEST; }
  $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/' . $file;
  $_SERVER['SCRIPT_FILENAME'] = $root . '/' . $file;
  chdir(dirname($root . '/' . $file));
  require $root . '/' . $file;
  return true;
}
return false;
