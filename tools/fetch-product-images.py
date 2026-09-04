#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
fetch-product-images.py — pulls product photography from the supplier's public
catalogue into img/equipment/_source/.

Two stages, because a product page carries far more images than the product's
own photos.

  Stage 1 — build the index (downloads no images):

      python tools/fetch-product-images.py --index

    Walks the listing pages (/products, /products_2 … until they run out),
    visits every product page once, and records its title and image URLs into
    _index.json plus a readable catalog-index.csv.

  Stage 2 — download:

      python tools/fetch-product-images.py --only wanted.txt

    wanted.txt is one slug per line (# comments and blank lines ignored).
    Without --only it downloads every product in the index.

WHICH IMAGES COUNT AS THE PRODUCT'S OWN
A product page lays its images out in a fixed order:

    [site chrome] [gallery] [long description images] [related products]

Those groups are distinguishable without parsing the site's markup:
  * site chrome lives on a different CDN host entirely           -> dropped
  * gallery images are    sc04.alicdn.com/kf/<id>.<ext>          -> KEPT
  * description images are sc04.alicdn.com/kf/<id>/<sellerid>/…  -> dropped
    (they repeat across every product in a family)
  * the related-products strip uses the gallery URL shape too, but those same
    files appear on many other product pages, so anything seen on more than
    one page is dropped as well.

That leaves 6–11 photos per product, in the order the page shows them.

No pip packages required. Pillow is used only if installed, to strip metadata.
"""

import argparse
import csv
import json
import os
import re
import sys
import time
import unicodedata
import urllib.parse
import urllib.request
from collections import Counter

BASE = "https://www.yuhailivestock.com"
UA = "Mozilla/5.0 (compatible; agrofarm.mn catalogue import)"
DELAY = 1.0
MAX_LIST_PAGES = 60          # safety stop; the site had 26 at the time of writing

# The product's own gallery: an alicdn key with no extra path segment.
GALLERY_RE = re.compile(r"^https://[a-z0-9.]*alicdn\.com/kf/[^/]+\.(?:png|jpe?g|webp)$", re.I)


def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.read()


def slugify(text, fallback="item"):
    """ASCII-only, lowercase, hyphenated — guarantees a Latin filename."""
    text = unicodedata.normalize("NFKD", text)
    text = text.encode("ascii", "ignore").decode("ascii")
    text = re.sub(r"[^a-zA-Z0-9]+", "-", text).strip("-").lower()
    return (re.sub(r"-{2,}", "-", text) or fallback)[:70]


def product_urls(html):
    out = []
    for href in re.findall(rb'href="([^"]*?/product-[^"]*?)"', html):
        u = urllib.parse.urljoin(BASE, href.decode("utf-8", "ignore"))
        u = u.split("#")[0].split("?")[0]
        if u not in out:
            out.append(u)
    return out


def page_title(html):
    m = re.search(rb"<title>(.*?)</title>", html, re.S | re.I)
    if not m:
        return ""
    t = re.sub(r"\s+", " ", m.group(1).decode("utf-8", "ignore")).strip()
    return re.split(r"\s*[|\-–]\s*", t)[0].strip()


def gallery_candidates(html):
    """Gallery-shaped image URLs, in the order the page lists them."""
    urls = []
    for m in re.findall(rb'(?:src|data-src|data-original)="([^"]+)"', html):
        u = urllib.parse.urljoin(BASE, m.decode("utf-8", "ignore")).split("?")[0]
        if GALLERY_RE.match(u) and u not in urls:
            urls.append(u)
    return urls


def strip_metadata(path):
    try:
        from PIL import Image
    except ImportError:
        return False
    try:
        with Image.open(path) as im:
            clean = Image.new(im.mode, im.size)
            clean.paste(im)          # paste, not getdata() — that one is deprecated
            clean.save(path)
        return True
    except Exception:
        return False


# --------------------------------------------------------------------- stage 1
def listing_pages():
    """Yield /products, /products_2, … stopping when a page adds nothing new."""
    seen, page = [], 1
    while page <= MAX_LIST_PAGES:
        url = BASE + "/products" + ("" if page == 1 else "_%d" % page)
        try:
            html = get(url)
        except Exception as e:
            print("  listing %s — %s (stopping)" % (url, e))
            return seen
        found = product_urls(html)
        fresh = [u for u in found if u not in seen]
        print("listing %-34s %3d link(s), %3d new" % (url.rsplit("/", 1)[-1], len(found), len(fresh)))
        if not fresh:
            return seen
        seen.extend(fresh)
        page += 1
        time.sleep(DELAY)
    return seen


def build_index(out_dir):
    pages = listing_pages()
    if not pages:
        sys.exit("no product links found — the site's markup changed; "
                 "product_urls() needs updating")
    print("\n%d product pages to visit\n" % len(pages))

    record = {}
    for n, url in enumerate(pages, 1):
        time.sleep(DELAY)
        try:
            page = get(url)
        except Exception as e:
            print("  !! %s — %s" % (url, e))
            continue
        title = page_title(page)
        slug = slugify(title or url.rsplit("/", 1)[-1])
        # Slugs must stay unique — two products can share a title.
        base, i = slug, 2
        while any(r["slug"] == slug for r in record.values()):
            slug = "%s-%d" % (base, i)
            i += 1
        record[url] = {"slug": slug, "title": title, "images": gallery_candidates(page)}
        if n % 10 == 0 or n == len(pages):
            print("[%d/%d] %s" % (n, len(pages), slug))

    os.makedirs(out_dir, exist_ok=True)
    with open(os.path.join(out_dir, "_index.json"), "w", encoding="utf-8") as f:
        json.dump(record, f, ensure_ascii=False, indent=1)
    return record


def own_images(record, shared_at):
    """Drop anything seen on `shared_at` or more pages — that is the related strip."""
    freq = Counter(u for rec in record.values() for u in set(rec["images"]))
    return {url: [u for u in rec["images"] if freq[u] < shared_at]
            for url, rec in record.items()}


def write_index_csv(record, own, out_dir):
    path = os.path.join(out_dir, "catalog-index.csv")
    with open(path, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["slug", "title", "gallery_images", "candidates", "url"])
        for url, rec in sorted(record.items(), key=lambda kv: kv[1]["slug"]):
            w.writerow([rec["slug"], rec["title"], len(own[url]), len(rec["images"]), url])
    return path


# --------------------------------------------------------------------- stage 2
def download(record, own, out_dir, wanted, max_images):
    rows, failed = [], 0
    targets = [(u, r) for u, r in record.items() if not wanted or r["slug"] in wanted]
    if wanted:
        for m in sorted(wanted - {r["slug"] for r in record.values()}):
            print("  ?? no such slug in the index: %s" % m)
    if not targets:
        sys.exit("nothing to download — check the slugs in your --only file")
    print("downloading for %d product(s)\n" % len(targets))

    for n, (url, rec) in enumerate(targets, 1):
        slug = rec["slug"]
        imgs = own[url][:max_images]
        folder = os.path.join(out_dir, slug)
        os.makedirs(folder, exist_ok=True)
        print("[%d/%d] %s — %d image(s)" % (n, len(targets), slug, len(imgs)))
        for i, iu in enumerate(imgs, 1):
            ext = os.path.splitext(iu)[1].lower() or ".jpg"
            dest = os.path.join(folder, "%s-%02d%s" % (slug, i, ext))
            if os.path.exists(dest):
                continue
            time.sleep(DELAY)
            try:
                blob = get(iu)
            except Exception as e:
                print("    !! %s — %s" % (iu, e))
                failed += 1
                continue
            with open(dest, "wb") as f:
                f.write(blob)
            strip_metadata(dest)
            rows.append({"folder": slug, "source_title": rec["title"],
                         "file": os.path.relpath(dest, out_dir).replace("\\", "/"),
                         "bytes": os.path.getsize(dest)})

    manifest = os.path.join(out_dir, "manifest.csv")
    with open(manifest, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=["folder", "source_title", "file", "bytes"])
        w.writeheader()
        w.writerows(rows)
    print("\n%d new image(s). manifest: %s" % (len(rows), manifest))
    if failed:
        print("%d request(s) failed — rerun to pick them up, existing files are skipped" % failed)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default="img/equipment/_source")
    ap.add_argument("--index", action="store_true", help="crawl and index only, download nothing")
    ap.add_argument("--only", metavar="FILE", help="text file of slugs, one per line")
    ap.add_argument("--max-images", type=int, default=8)
    ap.add_argument("--shared-at", type=int, default=2,
                    help="an image seen on this many pages or more is a related-product thumb")
    args = ap.parse_args()

    cache = os.path.join(args.out, "_index.json")
    if args.index or not os.path.exists(cache):
        record = build_index(args.out)
    else:
        print("using existing index:", cache, "(--index to rebuild)")
        with open(cache, encoding="utf-8") as f:
            record = json.load(f)

    own = own_images(record, args.shared_at)
    csv_path = write_index_csv(record, own, args.out)
    kept = sum(len(v) for v in own.values())
    print("\n%d products · %d gallery image(s) · %.1f per product"
          % (len(record), kept, kept / max(1, len(record))))
    print("index written:", csv_path)

    if args.index:
        print("\nNothing downloaded. Send the CSV over, then run with --only <file>.")
        return

    wanted = set()
    if args.only:
        with open(args.only, encoding="utf-8") as f:
            for line in f:
                line = line.split("#")[0].strip()
                if line:
                    wanted.add(line)
        print("slugs requested:", len(wanted))

    download(record, own, args.out, wanted, args.max_images)


if __name__ == "__main__":
    main()
