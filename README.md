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

```
new-naf/
├── index.html          # Main HTML — all sections, bilingual via data-mn/data-en
├── style.css           # All styles — responsive, scroll-reveal, parallax, dark sections
├── script.js           # Interactions — language toggle, reveal observer, counters,
│                         progress bar, cursor glow, parallax, timeline spine, nav
├── img/                # Images and SVG assets
│   ├── naf_logo_full.svg
│   ├── farm-barn.jpg
│   ├── opening-ceremony.jpg
│   ├── proj-farm.jpg
│   ├── cow-holstein.jpg
│   └── calves.png
└── README.md
```

## How to Run Locally

No build tools or dependencies required — just serve the folder:

```bash
# Python 3
python -m http.server 8080

# Node.js
npx serve .

# VS Code
# Install "Live Server" extension and click "Go Live"
```

Then open `http://localhost:8080` (or whichever port your server uses).

## Deploy

Any static host works:

```bash
# Vercel
npx vercel

# Netlify
npx netlify-cli deploy --prod
```

## Browser Support

All modern browsers (Chrome, Firefox, Safari, Edge). Responsive down to 320px-wide devices with mobile-specific enhancements:

- Horizontal swipe cards for Farms, Services, and Products sections on mobile
- Sticky bottom action bar (Call / Get a quote)
- Burger menu with animated open/close
- Touch-friendly tap targets (≥44px)

## Design Credits

- **Typefaces**: [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) (headlines) + [Inter](https://fonts.google.com/specimen/Inter) (body)
- **Color palette**: Forest green (`#1a5e2a`) + warm cream (`#fbf6ed`) + charcoal (`#2c2c2c`) + metallic gold (`#d4a54a`)
