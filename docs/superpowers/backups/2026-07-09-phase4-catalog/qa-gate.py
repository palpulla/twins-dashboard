#!/usr/bin/env python3
"""Per-page QA gate for the Phase 4 Clopay catalog pages (spec section 6).

Usage:
  python3 qa-gate.py <url> <slug>            # gate one live page against its pack entry
  python3 qa-gate.py --all [--base URL]      # gate every page in the pack (6s throttle)

Options:
  --pack PATH     path to content-pack.json (default: alongside this script)
  --base URL      base URL for --all mode (default: https://twinsgaragedoors.com)
  --skip-links    skip the internal-link 200 sweep (fast mode)

Assertions per page (fetched once, browser UA):
  1. exactly one <h1>, and it contains every assertions.must_contain term
  2. <title> present and contains the primary must_contain term
  3. meta description present, non-trivial (>=50 chars)
  4. phone (833) 833-2010 present in some rendering
  5. every JSON-LD block parses; a Product block's name matches the H1 (product pages)
  6. no banned string (assertions.must_not_contain) in the page's visible text
  7. every internal link in the page returns 200 (throttled 6s per request)

Exit 0 when every checked page passes, 1 otherwise, with a findings list.
Stdlib only.
"""
import argparse
import html as htmllib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36")
THROTTLE_S = 6
PHONE_VARIANTS = ["(833) 833-2010", "833-833-2010", "833.833.2010", "tel:+18338332010", "18338332010"]


def fetch(url, method="GET", timeout=30):
    req = urllib.request.Request(url, headers={"User-Agent": UA}, method=method)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read() if method == "GET" else b""
            return resp.status, body.decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        return e.code, ""
    except Exception as e:
        return None, str(e)


class PageParser(HTMLParser):
    """Collects h1 texts, title, meta description, JSON-LD blocks, links, visible text."""

    SKIP_TEXT_TAGS = {"script", "style", "noscript", "template"}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.h1s = []
        self.title = ""
        self.meta_description = None
        self.jsonld_blocks = []
        self.links = []
        self.text_parts = []
        self._stack = []
        self._in_h1 = 0
        self._in_title = False
        self._in_jsonld = False

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        self._stack.append(tag)
        if tag == "h1":
            self._in_h1 += 1
            self.h1s.append("")
        elif tag == "title":
            self._in_title = True
        elif tag == "script" and (attrs.get("type") or "").strip().lower() == "application/ld+json":
            self._in_jsonld = True
            self.jsonld_blocks.append("")
        elif tag == "meta" and (attrs.get("name") or "").lower() == "description":
            self.meta_description = attrs.get("content", "")
        elif tag == "a" and attrs.get("href"):
            self.links.append(attrs["href"])

    def handle_endtag(self, tag):
        if tag in self._stack:
            # pop up to and including the tag (tolerate malformed nesting)
            while self._stack:
                if self._stack.pop() == tag:
                    break
        if tag == "h1" and self._in_h1:
            self._in_h1 -= 1
        elif tag == "title":
            self._in_title = False
        elif tag == "script":
            self._in_jsonld = False

    def handle_data(self, data):
        if self._in_jsonld:
            self.jsonld_blocks[-1] += data
            return
        if self._in_title:
            self.title += data
        if self._in_h1 and self.h1s:
            self.h1s[-1] += data
        if not any(t in self.SKIP_TEXT_TAGS for t in self._stack):
            self.text_parts.append(data)

    @property
    def visible_text(self):
        return re.sub(r"\s+", " ", " ".join(self.text_parts))


def norm(s):
    """Normalize for name comparison: drop marks/entities, collapse space, lowercase."""
    s = htmllib.unescape(s or "")
    s = s.replace("®", "").replace("™", "")
    return re.sub(r"\s+", " ", s).strip().lower()


def find_products(node, found):
    """Recursively find JSON-LD objects whose @type is/contains Product."""
    if isinstance(node, dict):
        t = node.get("@type")
        types = t if isinstance(t, list) else [t]
        if any(isinstance(x, str) and x.lower() == "product" for x in types if x):
            found.append(node)
        for v in node.values():
            find_products(v, found)
    elif isinstance(node, list):
        for v in node:
            find_products(v, found)


def check_page(url, entry, skip_links=False):
    findings = []
    slug = entry["slug"]
    must_contain = entry["assertions"]["must_contain"]
    must_not = entry["assertions"]["must_not_contain"]
    is_product = entry.get("product_id") is not None

    status, body = fetch(url)
    if status != 200:
        return [f"{slug}: fetch failed, HTTP {status} for {url}"]

    p = PageParser()
    p.feed(body)
    text = p.visible_text
    text_unescaped = htmllib.unescape(text)

    # 1. exactly one h1 containing the product name terms
    if len(p.h1s) != 1:
        findings.append(f"{slug}: expected exactly one <h1>, found {len(p.h1s)}: {p.h1s}")
    else:
        h1 = norm(p.h1s[0])
        for term in must_contain:
            if norm(term) not in h1:
                findings.append(f"{slug}: <h1> missing term {term!r}: {p.h1s[0].strip()!r}")

    # 2. title present + product-correct
    if not p.title.strip():
        findings.append(f"{slug}: <title> missing or empty")
    elif norm(must_contain[0]) not in norm(p.title):
        findings.append(f"{slug}: <title> missing {must_contain[0]!r}: {p.title.strip()!r}")

    # 3. meta description present
    if p.meta_description is None or len(p.meta_description.strip()) < 50:
        findings.append(f"{slug}: meta description missing or too short: {p.meta_description!r}")

    # 4. phone
    hay = htmllib.unescape(body)
    if not any(v in hay for v in PHONE_VARIANTS):
        findings.append(f"{slug}: phone (833) 833-2010 not found on page")

    # 5. JSON-LD parses; Product name matches H1
    products = []
    for i, block in enumerate(p.jsonld_blocks):
        if not block.strip():
            continue
        try:
            data = json.loads(block)
        except json.JSONDecodeError as e:
            findings.append(f"{slug}: JSON-LD block {i} does not parse: {e}")
            continue
        find_products(data, products)
    if is_product:
        if not products:
            findings.append(f"{slug}: no Product JSON-LD found")
        elif len(p.h1s) == 1:
            h1n = norm(p.h1s[0])
            names = [norm(pr.get("name", "")) for pr in products]
            if not any(n and (n == h1n or n in h1n or h1n in n) for n in names):
                findings.append(f"{slug}: Product JSON-LD name does not match H1. names={names} h1={h1n!r}")

    # must_contain terms somewhere in visible page text too
    for term in must_contain:
        if norm(term) not in norm(text_unescaped):
            findings.append(f"{slug}: page text missing required term {term!r}")

    # 6. banned strings in visible text (and title/meta)
    scan = " ".join([text_unescaped, p.title or "", p.meta_description or ""])
    for b in must_not:
        if b.lower() in scan.lower():
            idx = scan.lower().find(b.lower())
            findings.append(f"{slug}: banned string {b!r} found in page text: ...{scan[max(0, idx-40):idx+40]}...")

    # 7. internal links return 200
    if not skip_links:
        base = urllib.parse.urlparse(url)
        seen = set()
        internal = []
        for href in p.links:
            if href.startswith(("#", "mailto:", "tel:", "javascript:")):
                continue
            absu = urllib.parse.urljoin(url, href)
            parsed = urllib.parse.urlparse(absu)
            if parsed.netloc != base.netloc:
                continue
            absu = absu.split("#")[0]
            if absu in seen:
                continue
            seen.add(absu)
            internal.append(absu)
        for link in internal:
            time.sleep(THROTTLE_S)
            st, _ = fetch(link, method="HEAD")
            if st in (403, 405, None):  # some servers reject HEAD; retry GET
                time.sleep(THROTTLE_S)
                st, _ = fetch(link)
            if st != 200:
                findings.append(f"{slug}: internal link not 200 ({st}): {link}")

    return findings


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("url", nargs="?", help="page URL (single-page mode)")
    ap.add_argument("slug", nargs="?", help="content-pack slug (single-page mode)")
    ap.add_argument("--all", action="store_true", help="gate every page in the pack")
    ap.add_argument("--base", default="https://twinsgaragedoors.com", help="base URL for --all mode")
    ap.add_argument("--pack", default=os.path.join(os.path.dirname(os.path.abspath(__file__)), "content-pack.json"))
    ap.add_argument("--skip-links", action="store_true", help="skip internal link 200 checks")
    args = ap.parse_args()

    with open(args.pack) as f:
        pack = json.load(f)
    by_slug = {e["slug"]: e for e in pack["pages"]}

    jobs = []
    if args.all:
        for e in pack["pages"]:
            jobs.append((f"{args.base.rstrip('/')}/{e['slug']}/", e))
    else:
        if not args.url or not args.slug:
            ap.error("single-page mode needs <url> <slug> (or use --all)")
        if args.slug not in by_slug:
            print(f"FAIL: slug {args.slug!r} not in content pack")
            return 1
        jobs.append((args.url, by_slug[args.slug]))

    all_findings = []
    for i, (url, entry) in enumerate(jobs):
        if i:
            time.sleep(THROTTLE_S)
        print(f"checking {entry['slug']} -> {url}")
        findings = check_page(url, entry, skip_links=args.skip_links)
        if findings:
            all_findings.extend(findings)
            for fnd in findings:
                print(f"  FAIL {fnd}")
        else:
            print("  ok")

    print()
    if all_findings:
        print(f"QA GATE: FAIL ({len(all_findings)} finding(s) across {len(jobs)} page(s))")
        return 1
    print(f"QA GATE: PASS ({len(jobs)} page(s) clean)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
