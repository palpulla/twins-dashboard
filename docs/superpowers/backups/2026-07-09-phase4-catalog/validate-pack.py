#!/usr/bin/env python3
"""Self-check for content-pack.json (Phase 4 Clopay catalog).

Checks (per plan Task 2 Step 3):
  - every product_id maps to clopay-api-snapshot/product-{id}.json and exists in products-list.json
  - exactly 21 entries: 20 product pages + 1 hub (product_id null, slug clopay-garage-doors)
  - the 20 product ids = all 23 catalog ids minus the 3 live pages (170, 12, 13)
  - slugs unique, kebab-case, clopay- prefixed
  - sibling slugs resolve within the pack or the 3 live pages; 3-4 siblings each
  - intro_copy word count 120-180
  - exactly 3 checklist cards and 5 FAQs per entry
  - banned strings absent from ALL text fields (excluding the must_not_contain list itself)
  - rank_math_title <= 62 chars; meta_description 130-165 chars
  - no verbatim Clopay text: no 8-word span in our copy also present in that product's API text
  - no em-dashes in any text field (house style)

Exit 0 when clean, 1 with findings otherwise. Run from the phase-4 backup dir.
"""
import json
import os
import re
import sys
import html as htmllib

HERE = os.path.dirname(os.path.abspath(__file__))
SNAP = os.path.join(HERE, "clopay-api-snapshot")
LIVE_PAGES = {"clopay-modern-steel", "clopay-gallery-steel", "clopay-classic-collection"}
BANNED = ["608", "859", "[Insert", "TBD", "lorem", "{{"]
KEBAB = re.compile(r"^[a-z0-9]+(-[a-z0-9]+)*$")

errors = []
warnings = []


def err(msg):
    errors.append(msg)


def warn(msg):
    warnings.append(msg)


def strip_html(h):
    if not h:
        return ""
    t = re.sub(r"<[^>]+>", " ", h)
    t = htmllib.unescape(t)
    return re.sub(r"\s+", " ", t).strip()


def norm_words(text):
    return re.sub(r"[^a-z0-9\s]", " ", text.lower()).split()


def ngrams(words, n=8):
    return {tuple(words[i:i + n]) for i in range(len(words) - n + 1)}


def iter_text_fields(entry):
    """Yield (path, string) for every authored text field, skipping assertions lists."""
    def walk(node, path):
        if isinstance(node, str):
            yield (path, node)
        elif isinstance(node, list):
            for i, v in enumerate(node):
                yield from walk(v, f"{path}[{i}]")
        elif isinstance(node, dict):
            for k, v in node.items():
                if k == "assertions":
                    continue
                yield from walk(v, f"{path}.{k}")
    yield from walk(entry, entry.get("slug", "?"))


def main():
    pack_path = os.path.join(HERE, "content-pack.json")
    with open(pack_path) as f:
        pack = json.load(f)
    pages = pack["pages"]

    with open(os.path.join(SNAP, "products-list.json")) as f:
        catalog = {int(p["ProductId"]): strip_html(p["Title"]) for p in json.load(f)}

    # --- entry count / hub ---
    if len(pages) != 21:
        err(f"expected 21 entries, found {len(pages)}")
    hubs = [p for p in pages if p.get("product_id") is None]
    if len(hubs) != 1 or hubs[0]["slug"] != "clopay-garage-doors":
        err(f"expected exactly one hub entry with slug clopay-garage-doors, found {[h['slug'] for h in hubs]}")

    # --- product ids ---
    product_pages = [p for p in pages if p.get("product_id") is not None]
    ids = [p["product_id"] for p in product_pages]
    if len(ids) != len(set(ids)):
        err("duplicate product_ids in pack")
    expected_ids = set(catalog) - {170, 12, 13}
    if set(ids) != expected_ids:
        err(f"product id mismatch. missing={sorted(expected_ids - set(ids))} extra={sorted(set(ids) - expected_ids)}")
    for pid in ids:
        snap_file = os.path.join(SNAP, f"product-{pid}.json")
        if not os.path.isfile(snap_file):
            err(f"no snapshot file for product_id {pid}")

    # --- slugs ---
    slugs = [p["slug"] for p in pages]
    if len(slugs) != len(set(slugs)):
        err("duplicate slugs in pack")
    for s in slugs:
        if not KEBAB.match(s):
            err(f"slug not kebab-case: {s}")
        if not s.startswith("clopay-") and s != "clopay-garage-doors":
            err(f"slug missing clopay- prefix: {s}")
    known = set(slugs) | LIVE_PAGES

    # --- per-entry checks ---
    for p in pages:
        slug = p["slug"]

        for key in ("h1", "rank_math_title", "meta_description", "hero_subhead",
                    "intro_copy", "checklist_cards", "faq", "siblings", "assertions"):
            if key not in p:
                err(f"{slug}: missing key {key}")

        wc = len(p.get("intro_copy", "").split())
        if not 120 <= wc <= 180:
            err(f"{slug}: intro_copy word count {wc} (want 120-180)")

        cards = p.get("checklist_cards", [])
        if len(cards) != 3 or any(not c.get("title") or not c.get("body") for c in cards):
            err(f"{slug}: need exactly 3 checklist cards with title+body, got {len(cards)}")

        faqs = p.get("faq", [])
        if len(faqs) != 5 or any(not q.get("q") or not q.get("a") for q in faqs):
            err(f"{slug}: need exactly 5 FAQs with q+a, got {len(faqs)}")

        sibs = p.get("siblings", [])
        if not 3 <= len(sibs) <= 4:
            err(f"{slug}: siblings count {len(sibs)} (want 3-4)")
        for s in sibs:
            if s not in known:
                err(f"{slug}: sibling slug does not resolve: {s}")
            if s == slug:
                err(f"{slug}: lists itself as sibling")

        t = p.get("rank_math_title", "")
        if len(t) > 62:
            err(f"{slug}: rank_math_title {len(t)} chars (max 62): {t}")
        m = p.get("meta_description", "")
        if not 130 <= len(m) <= 165:
            err(f"{slug}: meta_description {len(m)} chars (want 130-165)")

        a = p.get("assertions", {})
        if not a.get("must_contain"):
            err(f"{slug}: assertions.must_contain empty")
        for mark in ("®", "™"):
            for mc in a.get("must_contain", []):
                if mark in mc:
                    err(f"{slug}: must_contain entry contains trademark mark: {mc!r}")
        if a.get("must_not_contain") != BANNED:
            err(f"{slug}: must_not_contain != canonical banned list")

        # banned strings + em-dashes in all authored text fields
        for path, text in iter_text_fields(p):
            for b in BANNED:
                if b.lower() in text.lower():
                    err(f"banned string {b!r} in {path}: ...{text[:80]}...")
            if "—" in text:
                err(f"em-dash in {path}: ...{text[:80]}...")

        # verbatim check: 8-gram overlap with this product's API text
        pid = p.get("product_id")
        if pid is not None:
            with open(os.path.join(SNAP, f"product-{pid}.json")) as f:
                api = json.load(f)
            api_text = " ".join(
                strip_html(api.get(k) or "")
                for k in ("ShortDescription", "Overview", "Construction", "ColorDisclaimer",
                          "TopSectionDisclaimer", "HardwareDisclaimer", "DesignDisclaimer", "Options"))
            api_grams = ngrams(norm_words(api_text))
            for path, text in iter_text_fields(p):
                for gram in ngrams(norm_words(text)):
                    if gram in api_grams:
                        err(f"verbatim 8-word span from Clopay API in {path}: \"{' '.join(gram)}\"")
                        break

    # --- summary ---
    print(f"content-pack validation: {len(pages)} entries "
          f"({len(product_pages)} product pages + {len(hubs)} hub)")
    print(f"product ids covered: {sorted(ids)}")
    wcs = [len(p['intro_copy'].split()) for p in pages]
    print(f"intro word counts: min {min(wcs)} / max {max(wcs)}")
    print(f"title lengths: max {max(len(p['rank_math_title']) for p in pages)}")
    metas = [len(p['meta_description']) for p in pages]
    print(f"meta lengths: min {min(metas)} / max {max(metas)}")
    for w in warnings:
        print(f"WARN: {w}")
    if errors:
        print(f"\nFAIL: {len(errors)} issue(s)")
        for e in errors:
            print(f"  - {e}")
        return 1
    print("\nPASS: pack is clean")
    return 0


if __name__ == "__main__":
    sys.exit(main())
