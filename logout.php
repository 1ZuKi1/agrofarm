<?php
/**
 * logout.php — ends the admin session.
 */

session_start();
$_SESSION = [];
session_destroy();
header('Content-Type: text/plain; charset=utf-8');
echo 'ok';
