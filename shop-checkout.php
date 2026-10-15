<?php
/**
 * shop-checkout.php — /shop/checkout (via .htaccess): the buyer chooses
 * Хүргүүлэх (delivery) or Очиж авах (pick-up), a day, fills in their details
 * and pays with QPay — the QR, countdown and confirmation appear on this same
 * page. Mongolian only: the buyers are in Ulaanbaatar.
 *
 * The choices (which days, the fee, the pick-up address) come from
 * inc/fulfillment.php and are embedded in the page as JSON, so the first
 * paint needs no request. The basket itself lives in the browser
 * (localStorage, js/script.js), so js/checkout.js draws the order summary.
 *
 * /shop/checkout?order=<token> is the buyer's order link: it reopens that
 * order's QR (still unpaid) or its confirmation (paid).
 */

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/fulfillment.php';

$options = null;
try {
  $options = fulfillment_options(naf_db(), fulfillment_now());
} catch (\Throwable $e) {
  error_log('shop-checkout: ' . $e->getMessage());
}

header('Cache-Control: no-store'); // the day choices change at 13:00 and midnight

page_start([
  'title' => 'Захиалга баталгаажуулах — Нүүдэлчин Агро Ферм',
  'canonical' => '/shop/checkout',
  'active' => 'shop',
  'head' => '<meta name="robots" content="noindex">',
]);

// Inline icons (stroke = currentColor), same style as the header basket icon.
$iconTruck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 4h13v12H1z"/><path d="M14 8h4.5L22 11.5V16h-8z"/><circle cx="5.5" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/></svg>';
$iconShop = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/></svg>';
?>

<section class="shop checkout-page">
  <div class="checkout-page__head">
    <a class="checkout-page__back" href="/shop">← Дэлгүүр рүү буцах</a>
    <h1>Захиалга баталгаажуулах</h1>
  </div>

<?php if ($options === null): ?>
  <p class="checkout-page__fail">Захиалгын хуудсыг ачаалж чадсангүй — түр хүлээгээд дахин оролдоно уу.</p>
<?php else: ?>
  <script type="application/json" id="checkoutOptions"><?= json_encode($options, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

  <noscript><p class="checkout-page__fail">Захиалга өгөхөд хөтчийн JavaScript асаалттай байх шаардлагатай.</p></noscript>

  <div class="checkout" id="checkoutRoot" hidden>
    <form id="checkoutForm" class="checkout__form" novalidate>

      <fieldset class="checkout-step">
        <legend class="checkout-step__title"><span class="checkout-step__no">1</span> Хэрхэн авах вэ?</legend>
        <div class="checkout-choices">
          <label class="checkout-choice">
            <input type="radio" name="fulfillment" value="delivery">
            <span class="checkout-choice__body">
              <span class="checkout-choice__icon"><?= $iconTruck ?></span>
              <span class="checkout-choice__name">Хүргүүлэх</span>
              <span class="checkout-choice__meta">Улаанбаатар хот дотор · <?= ce(number_format($options['delivery_fee'])) ?>₮</span>
            </span>
          </label>
          <label class="checkout-choice">
            <input type="radio" name="fulfillment" value="pickup">
            <span class="checkout-choice__body">
              <span class="checkout-choice__icon"><?= $iconShop ?></span>
              <span class="checkout-choice__name">Очиж авах</span>
              <span class="checkout-choice__meta"><?= ce($options['pickup_address']) ?></span>
            </span>
          </label>
        </div>
        <a class="checkout-map" href="<?= ce($options['map_url']) ?>" target="_blank" rel="noopener">Оффисын байршил газрын зураг дээр<span class="sr-only"> (шинэ цонхонд)</span> ↗</a>
      </fieldset>

      <fieldset class="checkout-step" id="dayFieldset" hidden>
        <legend class="checkout-step__title"><span class="checkout-step__no">2</span> Хэзээ?</legend>
        <div class="checkout-days" id="dayList"></div>
        <p class="checkout-hint" id="dayHint"></p>
      </fieldset>

      <fieldset class="checkout-step">
        <legend class="checkout-step__title"><span class="checkout-step__no" id="detailsNo">2</span> Таны мэдээлэл</legend>
        <div class="checkout-field">
          <label for="checkoutName">Нэр</label>
          <input type="text" id="checkoutName" name="buyer_name" autocomplete="name" required>
        </div>
        <div class="checkout-field">
          <label for="checkoutPhone">Утас</label>
          <input type="tel" id="checkoutPhone" name="buyer_phone" inputmode="numeric" autocomplete="tel" placeholder="99112233" required aria-describedby="phoneHint">
          <span class="checkout-field__hint" id="phoneHint">8 оронтой дугаар. Манай ажилтан энэ дугаарт залгана.</span>
        </div>
        <div class="checkout-field" id="addressField" hidden>
          <label for="checkoutAddress">Хүргэлтийн хаяг</label>
          <textarea id="checkoutAddress" name="buyer_address" rows="3" autocomplete="street-address" aria-describedby="addressHint"></textarea>
          <span class="checkout-field__hint" id="addressHint">Дүүрэг, хороо, байр, орц, тоот</span>
        </div>
        <div class="checkout-field">
          <label for="checkoutNote">Тэмдэглэл <span class="checkout-field__opt">(заавал биш)</span></label>
          <textarea id="checkoutNote" name="buyer_note" rows="2"></textarea>
        </div>
      </fieldset>

      <p id="checkoutError" class="checkout-error" role="alert" hidden></p>
      <button type="submit" class="btn btn--gold checkout-submit" id="checkoutSubmitBtn">QPay-ээр төлөх</button>
      <p class="checkout-hint checkout-hint--center">Төлбөр төлсний дараа захиалга баталгаажна.</p>
    </form>

    <aside class="checkout-summary" aria-labelledby="summaryTitle">
      <details class="checkout-summary__box" open>
        <summary class="checkout-summary__toggle">
          <span id="summaryTitle">Таны захиалга</span>
          <span class="checkout-summary__mini" id="summaryMini"></span>
        </summary>
        <ul class="checkout-summary__lines" id="summaryLines"></ul>
        <dl class="checkout-summary__totals">
          <div><dt>Бүтээгдэхүүн</dt><dd id="sumSubtotal"></dd></div>
          <div class="checkout-summary__fee" id="sumFeeRow" hidden><dt>Хүргэлт</dt><dd id="sumFee"></dd></div>
          <div class="checkout-summary__total"><dt>Нийт</dt><dd id="sumTotal"></dd></div>
        </dl>
        <a class="checkout-summary__edit" href="/shop#cart">Сагсаа засах</a>
      </details>
    </aside>
  </div>

  <div class="checkout-pay" id="checkoutPay" hidden></div>

  <div class="checkout-empty" id="checkoutEmpty" hidden>
    <p>Сагс хоосон байна.</p>
    <a class="btn btn--gold" href="/shop">Дэлгүүр рүү буцах</a>
  </div>
<?php endif; ?>
</section>

<?php page_end([
  'shop' => true,
  'scripts' => '<script src="' . ce(asset('/js/checkout.js')) . '"></script>' . "\n",
]); ?>
