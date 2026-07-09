import datetime as dt
import json
import frontmatter
import pytest
from pathlib import Path
from engine.config import load_instagram_config
from scripts.publish_ig import publish_due

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def _approved(dirp, name, date, caption="Broken spring repair in Verona.\n\nCall or book through the link in our profile"):
    dirp.mkdir(parents=True, exist_ok=True)
    p = dirp / name
    post = frontmatter.Post(caption)
    post["date"] = date; post["slot"] = "proof"; post["approved"] = True
    post["cta"] = "Call or book through the link in our profile"
    post["visual"] = {"kind": "real", "asset_path": "/tmp/x.jpg", "ai_spec": None, "fallback_level": 1, "needs_approval": False}
    p.write_text(frontmatter.dumps(post))
    return p


def test_dry_run_publishes_nothing_and_lists_due(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         CFG, today=dt.date(2026, 7, 6), live=False)
    assert result["due"] == 1 and result["published"] == 0
    run_tool.assert_not_called()


def test_live_refuses_unverified_tools(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    with pytest.raises(RuntimeError, match="verified"):
        publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                    CFG, today=dt.date(2026, 7, 6), live=True)


def test_live_publishes_and_stamps(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", return_value={"successful": True})
    cfg = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")
    cfg.publish_tools["verified"] = True
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         cfg, today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 1
    run_tool.assert_called_once()
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    assert frontmatter.loads(published[0].read_text()).get("published_at")
    state = json.loads((tmp_path / "state.json").read_text())
    assert state["total"] == 1


def test_unsafe_edited_draft_is_skipped(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool")
    cfg = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")
    cfg.publish_tools["verified"] = True
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06",
              caption="Get $25 off in Rockford!\n\nCall or book through the link in our profile")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         cfg, today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["skipped_unsafe"] == 1
