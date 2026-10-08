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
├── inc/
│   ├── layout.php      # page_start()/page_end(): head, header + menu, footer,
│   │                     basket drawer, scripts. The menu lives here only.
│   └── shop-lib.php    # priced products query, shared by the shop pages + JSON
├── index.php           # home — the only page in EN / MN / 中文 (data-en/-mn/-zh)
├── news.php            # /news — Mongolian only
├── shop.php            # /shop — priced products + equipment preview
├── shop-product.php    # /shop/<slug> — one priced product
├── equipment.php       # /shop/equipment
├── category.php        # /shop/equipment/<category>
├── product.php         # /shop/equipment/<category>/<product>
├── catalog-lib.php     # equipment queries + page pieces (sidebar, crumbs, cards)
├── shop-data.php       # JSON for shop.js (live prices/stock for the basket)
├── order-*.php, qpay*.php, slots.php   # checkout and QPay
├── inquiry-create.php  # equipment quote requests
├── admin.html/.js, *-admin.php, login.php, publish.php   # /admin
├── style.css           # all styles
├── script.js           # every page: header, menu, reveals, basket badge,
│                         language toggle (home only)
├── shop.js             # shop pages: basket drawer, checkout, QPay
├── catalog.js          # shop pages: equipment quote list, gallery
├── .htaccess           # clean URLs, redirects from old addresses, security
├── .cpanel.yml         # deploy: git push copies the listed files to public_html
└── sql/                # schema and migrations, run by hand in phpMyAdmin
```

Old addresses keep working: `/index.html`, `/news.html`, `/shop.html`,
`/shop?product=…` and `/equipment/…` all redirect to the new ones.

## How to Run Locally

Needs Apache with `mod_rewrite` (the clean URLs are `.htaccess` rules), PHP 7.4+
and MySQL/MariaDB with the tables from `sql/`. Put a filled-in `config.php`
(see `config.example.php`) one folder above the site root.

## Deploy

`git push` to the cPanel repository runs `.cpanel.yml`, which copies the listed
files into `public_html`. A new top-level file must be added to that list.
Database changes in `sql/` are run by hand in phpMyAdmin before deploying the
code that needs them. Secrets live in `config.php`, outside `public_html`.

## Browser Support

All modern browsers (Chrome, Firefox, Safari, Edge). Responsive down to 320px-wide devices with mobile-specific enhancements:

- Horizontal swipe cards for Farms, Services, and Products sections on mobile
- Sticky bottom action bar (Call / Get a quote)
- Burger menu with animated open/close
- Touch-friendly tap targets (≥44px)

## Design Credits

- **Typefaces**: [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) (headlines) + [Inter](https://fonts.google.com/specimen/Inter) (body)
- **Color palette**: Forest green (`#1a5e2a`) + warm cream (`#fbf6ed`) + charcoal (`#2c2c2c`) + metallic gold (`#d4a54a`)
