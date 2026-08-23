<?php
/**
 * order-status.php — public polling endpoint for a single order's status.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/qpay.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid token']);
  exit;
}

try {
  $pdo = naf_db();
  // Exclude this request's own order from the global sweep: without this, a
  // sweep call whose expires_at just passed could flip THIS order's row to
  // 'expired' a moment before the re-verify below runs for it, permanently
  // hiding a payment that actually went through. (Other traffic can still
  // sweep this order via slots.php/order-create.php between polls — that's
  // the case the widened re-verify condition below recovers from.)
  $pdo->prepare("UPDATE orders SET status='expired' WHERE status='pending' AND expires_at < NOW() AND public_token != ?")->execute([$token]);

  $stmt = $pdo->prepare('SELECT id, status, qpay_invoice_id, total, delivery_date, delivery_slot, paid_at, qr_image, qr_text FROM orders WHERE public_token = ?');
  $stmt->execute([$token]);
  $order = $stmt->fetch();

  if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
  }

  // An order can reach here already 'expired' (via a sweep from unrelated
  // traffic) despite having actually been paid — recoverable as long as
  // paid_at was never set. Treat that the same as 'pending' for re-verify
  // purposes so it isn't permanently lost.
  $wasExpiredUnpaid = ($order['status'] === 'expired' && $order['paid_at'] === null);

  // Polling is the fallback path for a missed webhook, so — same as
  // qpay-callback.php — re-verify via QPay's own check endpoint rather than
  // trusting the cached status column. Unlike the webhook, a QPay failure here
  // must NOT error out: just fall back to the cached (still-pending) status,
  // since the browser will poll again in a few seconds regardless.
  if ($order['status'] === 'pending' || $wasExpiredUnpaid) {
    try {
      $qpayToken = qpay_token();
      $check = qpay_check_payment($qpayToken, $order['qpay_invoice_id']);
      // count:0 (no payment row on file) is definitely not-paid and leaves
      // $order['status'] untouched below. A count > 0 alone does NOT prove a
      // settled full payment — see qpay.php's qpay_paid_amount() docblock:
      // this amount check was implemented from researched (not
      // live-verified-against-a-real-payment) QPay response field names, and
      // MUST be re-confirmed against a real completed sandbox payment before
      // switching config.php to production QPay credentials.
      if (($check['count'] ?? 0) > 0 && qpay_paid_amount($check) >= (float)$order['total']) {
        $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE id=? AND status IN ('pending','expired')")->execute([$order['id']]);
        if ($wasExpiredUnpaid) {
          error_log("order-status.php: recovered order id={$order['id']} token={$token} from expired to paid via re-verify");
        }
        $order['status'] = 'paid';
      }
    } catch (Throwable $e) {
      // QPay unreachable/erroring — leave $order['status'] as the cached value.
    }
  }

  if ($order['status'] !== 'pending') {
    unset($order['qr_image'], $order['qr_text']);
  }
  unset($order['id'], $order['qpay_invoice_id'], $order['paid_at']);
  echo json_encode($order);
} catch (\Throwable $e) {
  // Never leak exception details (DB host/name, stack trace) to this
  // endpoint's anonymous, unauthenticated visitors.
  http_response_code(500);
  echo json_encode(['error' => 'Internal server error']);
}
