<?php
/**
 * config.example.php — template for config.php.
 *
 * config.php holds real secrets (admin password hash, and soon QPay + MySQL
 * credentials) and is gitignored — it never enters version control. Copy this
 * file to config.php on each environment and fill in real values there.
 *
 *     cp config.example.php config.php
 *
 * To generate a password hash:
 *
 *     php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
 */

define('NAF_ADMIN_PASSWORD_HASH', 'REPLACE_WITH_BCRYPT_HASH');

// MySQL — shop feature (products, variants, orders). Create the database via
// cPanel's MySQL Database Wizard, then fill in the values it gives you.
define('NAF_DB_HOST', 'localhost');
define('NAF_DB_NAME', 'REPLACE_WITH_DB_NAME');
define('NAF_DB_USER', 'REPLACE_WITH_DB_USER');
define('NAF_DB_PASS', 'REPLACE_WITH_DB_PASSWORD');

// QPay — get credentials from https://developer.qpay.mn (Merchant V2)
define('NAF_QPAY_BASE_URL', 'https://merchant-sandbox.qpay.mn');
define('NAF_QPAY_USERNAME', 'REPLACE_WITH_QPAY_USERNAME');
define('NAF_QPAY_PASSWORD', 'REPLACE_WITH_QPAY_PASSWORD');
define('NAF_QPAY_INVOICE_CODE', 'REPLACE_WITH_QPAY_INVOICE_CODE');
