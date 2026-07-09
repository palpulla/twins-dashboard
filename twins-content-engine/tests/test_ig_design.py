import subprocess
from pathlib import Path

import pytest

from engine.config import load_instagram_config
from engine.ig_design import render_card

ROOT = Path(__file__).resolve().parents[1]


def _cfg_for(tmp_path):
    cfg = load_instagram_config(ROOT / "config" / "instagram.yaml")
    # Keep the real (frozen) template but redirect rendered output to tmp_path.
    # An absolute out_dir overrides `root` entirely in Path's / join, so the
    # `root` argument passed to render_card in these tests is irrelevant.
    cfg.design["out_dir"] = str(tmp_path)
    return cfg


def _fake_chrome_writes_output(cmd, capture_output=True, timeout=60):
    for arg in cmd:
        if arg.startswith("--screenshot="):
            Path(arg.split("=", 1)[1]).write_bytes(b"fakepng")
    return subprocess.CompletedProcess(cmd, 0, stdout=b"", stderr=b"")


def test_render_card_fills_markers_and_returns_expected_path(tmp_path, mocker):
    cfg = _cfg_for(tmp_path)
    mocker.patch("engine.ig_design.subprocess.run", side_effect=_fake_chrome_writes_output)
    photo = tmp_path / "completed_job.jpg"
    photo.write_bytes(b"x")

    result = render_card(photo, headline="New <Install>", kicker="Recent Work",
                         cfg=cfg, root=ROOT)

    assert result == tmp_path / "completed_job_card.png"
    assert result.exists() and result.stat().st_size > 0

    html_text = (tmp_path / "completed_job_card.html").read_text()
    assert f"file://{photo.resolve()}" in html_text
    assert "New &lt;Install&gt;" in html_text  # escaped
    assert "Recent Work" in html_text
    assert "{{PHOTO}}" not in html_text
    assert "{{BRAND}}" not in html_text
    assert "{{KICKER}}" not in html_text
    assert "{{HEADLINE}}" not in html_text


def test_render_card_raises_on_nonzero_exit(tmp_path, mocker):
    cfg = _cfg_for(tmp_path)
    mocker.patch("engine.ig_design.subprocess.run",
                return_value=subprocess.CompletedProcess([], 1, stdout=b"", stderr=b"boom"))
    photo = tmp_path / "completed_job.jpg"
    photo.write_bytes(b"x")

    with pytest.raises(RuntimeError):
        render_card(photo, headline="X", kicker="Y", cfg=cfg, root=ROOT)


def test_render_card_raises_on_missing_output(tmp_path, mocker):
    cfg = _cfg_for(tmp_path)
    # Exit 0 but chrome never actually wrote the screenshot file.
    mocker.patch("engine.ig_design.subprocess.run",
                return_value=subprocess.CompletedProcess([], 0, stdout=b"", stderr=b""))
    photo = tmp_path / "completed_job.jpg"
    photo.write_bytes(b"x")

    with pytest.raises(RuntimeError):
        render_card(photo, headline="X", kicker="Y", cfg=cfg, root=ROOT)


def test_render_card_raises_on_empty_output(tmp_path, mocker):
    cfg = _cfg_for(tmp_path)

    def _writes_empty_file(cmd, capture_output=True, timeout=60):
        for arg in cmd:
            if arg.startswith("--screenshot="):
                Path(arg.split("=", 1)[1]).write_bytes(b"")
        return subprocess.CompletedProcess(cmd, 0, stdout=b"", stderr=b"")

    mocker.patch("engine.ig_design.subprocess.run", side_effect=_writes_empty_file)
    photo = tmp_path / "completed_job.jpg"
    photo.write_bytes(b"x")

    with pytest.raises(RuntimeError):
        render_card(photo, headline="X", kicker="Y", cfg=cfg, root=ROOT)


def test_fit_contain_marker_applied(tmp_path, mocker):
    cfg = _cfg_for(tmp_path)
    mocker.patch("engine.ig_design.subprocess.run", side_effect=_fake_chrome_writes_output)
    photo = tmp_path / "before_after_collage.jpg"
    photo.write_bytes(b"x")

    render_card(photo, headline="Before & After", kicker="Good To Know",
                cfg=cfg, root=ROOT, fit="contain")
    filled = (tmp_path / "before_after_collage_card.html").read_text()
    assert 'class="photo contain"' in filled
    assert "{{FIT}}" not in filled

    other = tmp_path / "completed_x.jpg"
    other.write_bytes(b"x")
    render_card(other, headline="X", kicker="Y", cfg=cfg, root=ROOT)
    assert 'class="photo "' in (tmp_path / "completed_x_card.html").read_text()
