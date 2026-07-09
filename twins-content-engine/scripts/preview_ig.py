"""Render pending + approved Instagram drafts into a local HTML preview.

Usage: python scripts/preview_ig.py   (writes logs/ig_preview.html and opens it)
"""
from __future__ import annotations

import html
import subprocess
from pathlib import Path

import frontmatter

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "logs" / "ig_preview.html"

_CARD = """
<div class="card {status_class}">
  <div class="status">{status}</div>
  {img}
  <div class="body">
    <div class="meta">{date} &middot; {slot} &middot; visual: {vkind}{flags}</div>
    <p class="caption">{caption}</p>
    <p class="hashtags">{hashtags}</p>
  </div>
  <div class="fname">{fname}</div>
</div>
"""


def _cards(folder: Path, status: str, status_class: str) -> list[str]:
    cards = []
    if not folder.exists():
        return cards
    for f in sorted(folder.glob("*.md")):
        try:
            post = frontmatter.loads(f.read_text())
        except Exception:
            cards.append(_CARD.format(status=status, status_class=status_class,
                                      img="", date="?", slot="?", vkind="?", flags="",
                                      caption="(unparseable draft)", hashtags="", fname=f.name))
            continue
        visual = post.get("visual") or {}
        asset = visual.get("asset_path")
        img = f'<img src="file://{html.escape(str(asset))}">' if asset and Path(str(asset)).exists() else \
              '<div class="noimg">no image yet (AI graphic pending)</div>'
        flags = ", ".join((post.get("source") or {}).get("needs_approval") or [])
        flags = f' &middot; <b>flags: {html.escape(flags)}</b>' if flags else ""
        caption = html.escape(post.content.strip()).replace("\n", "<br>")
        tags = " ".join(post.get("hashtags") or [])
        cards.append(_CARD.format(
            status=status, status_class=status_class, img=img,
            date=html.escape(str(post.get("date", "?"))), slot=html.escape(str(post.get("slot", "?"))),
            vkind=html.escape(str(visual.get("kind", "?"))), flags=flags,
            caption=caption, hashtags=html.escape(tags), fname=html.escape(f.name),
        ))
    return cards


def main() -> None:
    pending = _cards(ROOT / "pending" / "instagram", "PENDING YOUR APPROVAL", "pending")
    approved = _cards(ROOT / "approved" / "instagram", "APPROVED - will post at its date", "approved")
    published = _cards(ROOT / "published" / "instagram", "PUBLISHED", "published")
    held = _cards(ROOT / "pending" / "instagram" / "held", "HELD BY SAFEGUARDS", "held")
    body = "".join(approved + pending + held + published) or "<p>No drafts anywhere. Run generate_ig_week.py.</p>"
    OUT.parent.mkdir(exist_ok=True)
    OUT.write_text(f"""<!doctype html><meta charset="utf-8">
<title>Twins IG drafts</title>
<style>
 body {{ font-family: -apple-system, sans-serif; background:#fafafa; margin:0; padding:24px; }}
 h1 {{ font-size:20px; }}
 .grid {{ display:flex; flex-wrap:wrap; gap:20px; }}
 .card {{ width:360px; background:#fff; border:1px solid #ddd; border-radius:12px; overflow:hidden; }}
 .card img {{ width:100%; height:auto; display:block; }}  /* cards are 1080x1350; show them whole */
 .noimg {{ width:100%; height:120px; display:flex; align-items:center; justify-content:center; color:#999; background:#f0f0f0; }}
 .body {{ padding:12px 14px; }}
 .meta {{ color:#777; font-size:12px; margin-bottom:8px; }}
 .caption {{ font-size:14px; line-height:1.45; margin:0 0 8px; }}
 .hashtags {{ color:#00376b; font-size:13px; margin:0; }}
 .status {{ font-size:11px; font-weight:700; letter-spacing:.5px; padding:6px 14px; }}
 .pending .status {{ background:#fff4d6; color:#8a6d00; }}
 .approved .status {{ background:#e0f4e0; color:#1a7a1a; }}
 .published .status {{ background:#e3ecfb; color:#1a4c9c; }}
 .held .status {{ background:#fde3e3; color:#a11616; }}
 .fname {{ font-family:monospace; font-size:11px; color:#999; padding:8px 14px; border-top:1px solid #eee; }}
</style>
<h1>Twins Instagram drafts</h1>
<p>Approve in Terminal: <code>.venv/bin/python scripts/approve_ig.py approve &lt;filename&gt;</code></p>
<div class="grid">{body}</div>""")
    print(OUT)
    subprocess.run(["open", str(OUT)], check=False)


if __name__ == "__main__":
    main()
