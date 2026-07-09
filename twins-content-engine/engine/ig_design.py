"""Composite a raw job photo into the approved branded-card template.

The template (assets/instagram/brand/post_template.html) is Daniel-approved
and FROZEN: layout, fonts, and styling never change here. This module only
fills in the five markers the template exposes — {{PHOTO}}, {{BRAND}},
{{KICKER}}, {{HEADLINE}}, {{FIT}} — and rasterizes the result with headless
Chrome. {{FIT}} selects the photo treatment: "" (default cover crop) or
"contain" (full image letterboxed over a blurred backdrop — used for
before/after collages so the comparison is never cropped away).
"""
from __future__ import annotations

import html
import subprocess
from pathlib import Path

from engine.config import InstagramConfig


def render_card(photo_path: Path, headline: str, kicker: str, cfg: InstagramConfig,
                root: Path, fit: str = "") -> Path:
    """Render photo_path into the branded card template and screenshot it to PNG.

    Raises RuntimeError if chrome exits non-zero, times out, or produces a
    missing/empty PNG — callers should treat that as a hold-the-draft signal,
    never a crash.
    """
    design = cfg.design
    template_path = root / design["template"]
    out_dir = root / design["out_dir"]
    out_dir.mkdir(parents=True, exist_ok=True)
    brand_dir = template_path.resolve().parent

    filled = (
        template_path.read_text()
        .replace("{{PHOTO}}", f"file://{photo_path.resolve()}")
        .replace("{{BRAND}}", f"file://{brand_dir}")
        .replace("{{KICKER}}", html.escape(kicker))
        .replace("{{HEADLINE}}", html.escape(headline))
        .replace("{{FIT}}", "contain" if fit == "contain" else "")
    )

    html_path = out_dir / f"{photo_path.stem}_card.html"
    html_path.write_text(filled)

    out_png = out_dir / f"{photo_path.stem}_card.png"
    cmd = [
        design["chrome"],
        "--headless",
        "--disable-gpu",
        f"--screenshot={out_png}",
        "--window-size=1080,1350",
        "--hide-scrollbars",
        "--virtual-time-budget=8000",
        f"file://{html_path.resolve()}",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, timeout=60)
    except subprocess.TimeoutExpired as e:
        raise RuntimeError(f"chrome render timed out for {photo_path.name}: {e}") from e

    if result.returncode != 0:
        stderr = result.stderr.decode(errors="replace")[:500] if result.stderr else ""
        raise RuntimeError(
            f"chrome render failed (exit {result.returncode}) for {photo_path.name}: {stderr}"
        )
    if not out_png.exists() or out_png.stat().st_size == 0:
        raise RuntimeError(f"chrome render produced no output for {photo_path.name}: {out_png}")
    return out_png
