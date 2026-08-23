<?php
/**
 * slots.php — public, read-only delivery slot availability for the next 7 days.
 */

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = naf_db();
  $pdo->exec("UPDATE orders SET status='expired' WHERE status='pending' AND expires_at < NOW()");

  $slotDefs = ['10:00-12:00', '14:00-16:00', '16:00-18:00'];
  $capacity = 5;
  // Reservation-set literal ('pending','paid','fulfilled') — must stay in
  // sync with the same literal in order-create.php, shop-data.php, and
  // products-admin.php.
  $countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE delivery_date = ? AND delivery_slot = ? AND status IN ('pending','paid','fulfilled')"
  );

  $days = [];
  $today = new DateTime('today');
  for ($i = 0; $i < 7; $i++) {
    $date = (clone $today)->modify("+{$i} day")->format('Y-m-d');
    $slots = [];
    foreach ($slotDefs as $slot) {
      $countStmt->execute([$date, $slot]);
      $count = (int)$countStmt->fetchColumn();
      $slots[] = ['slot' => $slot, 'available' => $count < $capacity];
    }
    $days[] = ['date' => $date, 'slots' => $slots];
  }

  echo json_encode(['days' => $days]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Internal server error']);
}
