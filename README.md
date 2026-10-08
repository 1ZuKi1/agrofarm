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
├── shop.php            # /shop — priced products + equipment preview
├── shop-product.php    # /shop/<slug> — one priced product
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
│   ├── shop-lib.php    #   priced products query
│   ├── catalog-lib.php #   equipment queries + sidebar, crumbs, cards
│   └── config.example.php
├── lang/strings.php    # every multilingual text, EN / MN / 中文 side by side
├── api/                # JSON/form endpoints: shop-data, slots, order-create,
│                         order-status, inquiry-create
├── admin/              # /admin/ — panel (index.html, admin.js) + its endpoints
├── css/style.css       # all styles
├── js/                 # script.js (every page), shop.js (basket, checkout,
│                         QPay), catalog.js (equipment quote list, gallery)
├── img/
├── .htaccess           # clean URLs, redirects from old addresses, security
├── .cpanel.yml         # deploy: git push copies root pages + the folders
└── sql/                # schema and migrations, run by hand in phpMyAdmin
```

Old addresses keep working: `/index.html`, `/news.html`, `/shop.html`,
`/shop?product=…`, `/equipment/…` and `/admin` redirect to the new ones, and
files that moved into folders (`/style.css`, `/shop-data.php`, `/login.php`…)
are still served at their old paths.

To change a text on the home page (in any language), edit `lang/strings.php`.
Every other page is Mongolian only; its text is in the page file itself.

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
