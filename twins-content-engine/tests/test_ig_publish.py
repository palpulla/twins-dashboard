import datetime as dt
import json
import frontmatter
import pytest
from pathlib import Path
from engine.config import load_instagram_config
from scripts.publish_ig import publish_due

CFG_PATH = Path(__file__).resolve().parents[1] / "config" / "instagram.yaml"
CFG = load_instagram_config(CFG_PATH)

_CREATE = object()  # sentinel: helper creates a real image file on disk


def _verified_cfg():
    cfg = load_instagram_config(CFG_PATH)
    cfg.publish_tools["verified"] = True
    return cfg


def _approved(dirp, name, date,
              caption="Broken spring repair in Verona.\n\nCall or book through the link in our profile",
              asset_path=_CREATE):
    dirp.mkdir(parents=True, exist_ok=True)
    if asset_path is _CREATE:
        asset = dirp / f"{name}.jpg"
        asset.write_bytes(b"x")
        asset_path = str(asset)
    p = dirp / name
    post = frontmatter.Post(caption)
    post["date"] = date; post["slot"] = "proof"; post["approved"] = True
    post["cta"] = "Call or book through the link in our profile"
    post["visual"] = {"kind": "real", "asset_path": asset_path, "ai_spec": None, "fallback_level": 1, "needs_approval": False}
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


def test_dry_run_does_not_create_state_file(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                CFG, today=dt.date(2026, 7, 6), live=False)
    assert not (tmp_path / "state.json").exists()


def test_live_refuses_unverified_tools(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    with pytest.raises(RuntimeError, match="verified"):
        publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                    CFG, today=dt.date(2026, 7, 6), live=True)


def test_live_publishes_and_stamps(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", return_value={"successful": True})
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 1 and result["failed"] == 0
    run_tool.assert_called_once()
    assert not list((tmp_path / "approved").glob("*.md"))
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    post = frontmatter.loads(published[0].read_text())
    assert post.get("publish_attempted_at")
    assert post.get("published_at")
    assert not post.get("publish_error")
    state = json.loads((tmp_path / "state.json").read_text())
    assert state["total"] == 1


def test_unsuccessful_response_not_marked_published(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool", return_value={"successful": False})
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["failed"] == 1
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    post = frontmatter.loads(published[0].read_text())
    assert post.get("publish_error")
    assert not post.get("published_at")
    state_path = tmp_path / "state.json"
    assert not state_path.exists() or json.loads(state_path.read_text())["total"] == 0


def test_crash_after_post_leaves_attempt_marker(tmp_path, mocker, capsys):
    run_tool = mocker.patch("scripts.publish_ig.run_tool",
                            side_effect=RuntimeError("network died mid-call"))
    cfg = _verified_cfg()
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    r1 = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                     cfg, today=dt.date(2026, 7, 6), live=True)
    assert run_tool.call_count == 1
    assert r1["published"] == 0 and r1["failed"] == 1
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    post = frontmatter.loads(published[0].read_text())
    assert post.get("publish_attempted_at")
    assert not post.get("published_at")
    capsys.readouterr()  # clear first-run output
    r2 = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                     cfg, today=dt.date(2026, 7, 6), live=True)
    assert run_tool.call_count == 1  # never re-posted
    assert r2["due"] == 0 and r2["published"] == 0
    assert "manual check" in capsys.readouterr().out


def test_unsafe_edited_draft_is_skipped(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06",
              caption="Get $25 off in Rockford!\n\nCall or book through the link in our profile")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["skipped_unsafe"] == 1
    run_tool.assert_not_called()


def test_missing_image_is_skipped_unsafe(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06", asset_path=None)
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["skipped_unsafe"] == 1
    run_tool.assert_not_called()
    assert not list((tmp_path / "published").glob("*.md"))


def test_poison_date_skipped(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", return_value={"successful": True})
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-05_bad.md", "not-a-date")
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["skipped_invalid"] == 1
    assert result["published"] == 1  # the good draft still went out
    run_tool.assert_called_once()
