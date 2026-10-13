# Nuudelchin Agro Farm LLC

**Нүүдэлчин Агро Ферм ХХК** — Mongolia's pioneer of cluster dairy farming. A bilingual (English / Mongolian) company website showcasing the farm's story, operations, and services.

## Overview

- **URL**: [agrofarm.mn](https://agrofarm.mn)
- **Type**: Static site — plain HTML, CSS, and vanilla JavaScript
- **Languages**: English (default) + Mongolian (Монгол), toggled via the **MN / EN** button
- **Design**: Premium editorial aesthetic — Cormorant Garamond serif + Inter sans-serif, gold-accent palette on a warm cream background

## Sections

| Section | Description |
|---|---|
| **Hero** | Full-viewport parallax scene with a staggered headline reveal |
| **Stats** | Animated counters — 1,120 cows, 9M liters of milk, 30 herder households |
| **About** | Mission, vision, values, and the founding story |
| **Journey** | Alternating timeline (2013 → 2030) threaded on a self-drawing gold spine |
| **Farms** | Four cluster farm cards (Bayandelger, Argalant, Bayankhangai, Ugtaaltsaidam) |
| **Services** | Six service cards — construction, consulting, training, feed, processing, value chain |
| **Products** | Three editorial product cards — equipment, raw milk, breeding livestock |
| **Recognition** | Presidential visit feature, director quotes, herder testimonial, partner list |
| **Contact** | Email, phone, address, Facebook link, and footer |

## Project Structure

Plain PHP on cPanel shared hosting (PHP 7.4) — no framework, no build step.
Every public page is built from one shared shell, `inc/layout.php`.

```
new-naf/
├── index.php           # home — the only page in EN / MN / 中文
├── news.php            # /news
├── shop.php            # /shop — one section per category, one card style
├── shop-product.php    # /shop/<slug> — one priced product
├── shop-checkout.php   # /shop/checkout — delivery or pick-up, day, QPay
├── equipment.php       # /shop/equipment
├── category.php        # /shop/equipment/<category>
├── product.php         # /shop/equipment/<category>/<product>
├── qpay-callback.php   # QPay webhook — must stay at this address (it is
│                         inside every invoice already sent to QPay)
├── inc/                # included by pages, refused over HTTP
│   ├── layout.php      #   page_start()/page_end(): head, header + menu,
│   │                       footer, basket drawer, scripts
│   ├── i18n.php        #   ta()/tx()/tmn() — read lang/strings.php
│   ├── db.php          #   MySQL (PDO); finds config.php above public_html
│   ├── qpay.php        #   QPay Merchant V2 client
│   ├── orders.php      #   paid / expired order changes, staff email
│   ├── fulfillment.php #   delivery / pick-up day rules, fee
│   ├── settings.php    #   values edited in /admin/ → Тохиргоо
│   ├── shop-lib.php    #   priced products, /shop sections, card prices
│   ├── catalog-lib.php #   equipment queries, sidebar, crumbs, shop_card()
│   └── config.example.php
├── lang/strings.php    # every multilingual text, EN / MN / 中文 side by side
├── api/                # JSON/form endpoints: shop-data, checkout-options,
│                         order-create, order-status, inquiry-create
├── admin/              # /admin/ — panel (index.html, admin.js) + its endpoints
├── css/style.css       # all styles
├── js/                 # script.js (every page), shop.js (basket drawer,
│                         product page), checkout.js (checkout page, QPay QR),
│                         catalog.js (equipment quote list, gallery)
├── img/
├── .htaccess           # clean URLs, redirects from old addresses, security
├── .cpanel.yml         # deploy: git push copies root pages + the folders
├── sql/                # schema and migrations, run by hand in phpMyAdmin
└── tests/              # php tests/<name>.php — fulfillment_test (order days),
                          shop_test (shop sections), php74_functions_test
                          (no PHP 8-only calls); not deployed
```

Old addresses keep working: `/index.html`, `/news.html`, `/shop.html`,
`/shop?product=…`, `/shop?order=…`, `/equipment/…` and `/admin` redirect to the new ones, and
files that moved into folders (`/style.css`, `/shop-data.php`, `/login.php`…)
are still served at their old paths.

To change a text on the home page (in any language), edit `lang/strings.php`.
Every other page is Mongolian only; its text is in the page file itself.

## Shop categories

Every item for sale sits in one category list (the `categories` table):
QPay products and price-on-request equipment alike. /shop shows one section
per category that has something in it, in the categories' order, and every
item uses the same card (`shop_card()`). A product's category is chosen in
/admin/ → Бүтээгдэхүүн → Ангилал; a product with no category appears in a
last section, "Бусад". An item sold in one form (one option named like the
product) is shown by its name alone in the basket, on the QPay invoice and in
the staff email.

## Order days (delivery or pick-up)

At checkout the buyer chooses **Хүргүүлэх** (delivery, Ulaanbaatar, with a fee)
or **Очиж авах** (pick-up at the office), then a **day** — not a time; staff
phone the buyer to agree the time.

- Delivery: the next two working days, never today.
- Pick-up: today (working days, before 13:00) plus the next two working days.
- Working days are Monday–Friday minus the closed days in /admin/ → Тохиргоо,
  where the delivery fee is set too. Add Цагаан сар and Наадам there ahead of
  time.

All of these rules live in `inc/fulfillment.php`, and every decision uses
Ulaanbaatar time whatever the server's own clock says.

## How to Run Locally

Needs Apache with `mod_rewrite` (the clean URLs are `.htaccess` rules), PHP 7.4+
and MySQL/MariaDB with the tables from `sql/`. Put a filled-in `config.php`
(see `inc/config.example.php`) one folder above the site root.

## Deploy

`git push` to the cPanel repository runs `.cpanel.yml`, which copies every
`*.php` page in the root plus the `inc lang admin api css js` folders into
`public_html`. New pages and new files inside those folders deploy with no
change to `.cpanel.yml`; a new top-level folder must be added to it.
Database changes in `sql/` are run by hand in phpMyAdmin before deploying the
code that needs them, in order: `orders-schema-v3.sql`, then
`orders-schema-v4.sql` (delivery / pick-up, the `settings` table), then
`orders-schema-v5.sql` (product categories, the cow statue and the book). Each
is run once. Secrets live in `config.php`, outside `public_html`.

## Browser Support

All modern browsers (Chrome, Firefox, Safari, Edge). Responsive down to 320px-wide devices with mobile-specific enhancements:

- Horizontal swipe cards for Farms, Services, and Products sections on mobile
- Sticky bottom action bar (Call / Get a quote)
- Burger menu with animated open/close
- Touch-friendly tap targets (≥44px)

## Design Credits

- **Typefaces**: [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) (headlines) + [Inter](https://fonts.google.com/specimen/Inter) (body)
- **Color palette**: Forest green (`#1a5e2a`) + warm cream (`#fbf6ed`) + charcoal (`#2c2c2c`) + metallic gold (`#d4a54a`)
