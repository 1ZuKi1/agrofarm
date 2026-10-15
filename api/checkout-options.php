<?php
/**
 * checkout-options.php — public, read-only: what the checkout page may offer
 * right now. Same shape shop-checkout.php embeds in the page:
 *
 *   {"delivery": [day…], "pickup": [day…], "delivery_fee": 8000,
 *    "pickup_address": "…", "map_url": "…", "office_hours": "…"}
 *   day = {"date": "2026-10-15", "word": "Маргааш", "date_label": "10-р сарын 15, Пүрэв"}
 *
 * js/checkout.js asks again when the buyer comes back to a tab left open,
 * since "Өнөөдөр" disappears at 13:00 and every day moves at midnight. The
 * rules themselves live in inc/fulfillment.php.
 */

require dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/fulfillment.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  echo json_encode(fulfillment_options(naf_db(), fulfillment_now()), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
  error_log('checkout-options: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Серверийн алдаа гарлаа — дахин оролдоно уу'], JSON_UNESCAPED_UNICODE);
}
