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


def _unverified_cfg():
    cfg = load_instagram_config(CFG_PATH)
    cfg.publish_tools["verified"] = False
    return cfg


def _approved(dirp, name, date,
              caption="Broken spring repair in Verona.\n\nCall or book through the link in our profile",
              asset_path=_CREATE, hashtags=None, image_url="https://example.com/x.jpg"):
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
    if hashtags is not None:
        post["hashtags"] = hashtags
    if image_url is not None:
        post["image_url"] = image_url
    p.write_text(frontmatter.dumps(post))
    return p


def _composio_success(slug, payload):
    """Default mocked run_tool: succeeds for both the container-create and
    publish steps, returning a container id from the create step."""
    if slug == CFG.publish_tools["media_create"]:
        return {"successful": True, "data": {"id": "123"}}
    return {"successful": True}


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
                    _unverified_cfg(), today=dt.date(2026, 7, 6), live=True)


def test_live_publishes_and_stamps(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 1 and result["failed"] == 0
    assert run_tool.call_count == 2
    first_call, second_call = run_tool.call_args_list
    assert first_call[0][0] == CFG.publish_tools["media_create"]
    assert second_call[0][0] == CFG.publish_tools["media_publish"]
    assert second_call[0][1]["creation_id"] == "123"
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


def test_corrupt_yaml_in_published_does_not_block_run(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    published = tmp_path / "published"; published.mkdir()
    (published / "corrupt.md").write_text("---\n: not: valid: yaml:\n---\nx")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, published, tmp_path / "state.json", _verified_cfg(),
                         today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 1


def test_corrupt_yaml_in_approved_skipped_invalid(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    (approved / "2026-07-01_bad.md").write_text("---\n: not: valid: yaml:\n---\nx")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 1 and result["skipped_invalid"] == 1


def test_dry_run_previews_missing_image_skip(tmp_path, mocker, capsys):
    mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06", asset_path=None)
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json", CFG,
                         today=dt.date(2026, 7, 6), live=False)
    assert result["skipped_unsafe"] == 1 and result["published"] == 0
    assert "missing image" in capsys.readouterr().out.lower()


def test_hashtags_are_appended_to_posted_caption(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    cfg = _verified_cfg()
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06", hashtags=["#VeronaWI", "#GarageDoorRepair"])
    publish_due(approved, tmp_path / "published", tmp_path / "state.json", cfg,
                today=dt.date(2026, 7, 6), live=True)
    sent = run_tool.call_args_list[0][0][1]["caption"]
    assert sent.endswith("#VeronaWI #GarageDoorRepair")


def test_banned_hashtag_caught_at_publish_time(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06", hashtags=["#fyp"])
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["skipped_unsafe"] == 1
    run_tool.assert_not_called()


def test_poison_date_skipped(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-05_bad.md", "not-a-date")
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["skipped_invalid"] == 1
    assert result["published"] == 1  # the good draft still went out
    assert run_tool.call_count == 2  # container create + publish for the one good draft


def test_missing_image_url_skipped_unsafe(tmp_path, mocker):
    # No image_url AND no real asset on disk to upload instead -> still unsafe.
    run_tool = mocker.patch("scripts.publish_ig.run_tool")
    upload_image = mocker.patch("engine.ig_hosting.upload_image")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06", image_url=None, asset_path=None)
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["skipped_unsafe"] == 1
    run_tool.assert_not_called()
    upload_image.assert_not_called()
    assert not list((tmp_path / "published").glob("*.md"))


def test_container_failure_no_publish_call(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", return_value={"successful": False})
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["failed"] == 1
    assert run_tool.call_count == 1  # publish step never called after container failure
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    post = frontmatter.loads(published[0].read_text())
    assert post.get("publish_error")
    assert not post.get("published_at")


def test_publish_step_failure_marked_failed(tmp_path, mocker, capsys):
    def side_effect(slug, payload):
        if slug == CFG.publish_tools["media_create"]:
            return {"successful": True, "data": {"id": "999"}}
        return {"successful": False}
    run_tool = mocker.patch("scripts.publish_ig.run_tool", side_effect=side_effect)
    cfg = _verified_cfg()
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         cfg, today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["failed"] == 1
    assert run_tool.call_count == 2
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    post = frontmatter.loads(published[0].read_text())
    assert post.get("publish_error")
    assert not post.get("published_at")
    # Ambiguous case: the container was created (may be visible to Instagram)
    # but the publish step itself failed, so we can't confirm success — the
    # NEXT run must warn that this file needs a manual check on Instagram.
    capsys.readouterr()  # clear first-run output
    publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                cfg, today=dt.date(2026, 7, 6), live=True)
    assert "manual check" in capsys.readouterr().out


def test_dry_run_previews_upload_for_real_asset_without_url(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool")
    upload_image = mocker.patch("engine.ig_hosting.upload_image")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06", image_url=None)
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         CFG, today=dt.date(2026, 7, 6), live=False)
    assert result["would_upload"] == 1
    assert result["skipped_unsafe"] == 0
    assert result["published"] == 0
    upload_image.assert_not_called()
    run_tool.assert_not_called()


def test_live_uploads_then_publishes(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    approved = tmp_path / "approved"
    draft_path = _approved(approved, "2026-07-06_proof.md", "2026-07-06", image_url=None)

    def _fake_upload(local_path, cfg, root):
        # By the time upload_image is called, the draft must still be
        # sitting in approved/ with no image_url yet (upload precedes save).
        pre = frontmatter.loads(draft_path.read_text())
        assert not pre.get("image_url")
        return "https://example.supabase.co/storage/v1/object/public/ig-media/2026-07/x.jpg"

    upload_image = mocker.patch("engine.ig_hosting.upload_image", side_effect=_fake_upload)
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    upload_image.assert_called_once()
    assert result["published"] == 1 and result["failed"] == 0
    assert run_tool.call_count == 2
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    post = frontmatter.loads(published[0].read_text())
    assert post.get("image_url", "").startswith("https://example.supabase.co/")
    assert post.get("published_at")


def test_upload_failure_leaves_draft_in_approved(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", side_effect=_composio_success)
    upload_image = mocker.patch("engine.ig_hosting.upload_image",
                                side_effect=RuntimeError("Supabase upload failed: status 403: nope"))
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06", image_url=None)
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         _verified_cfg(), today=dt.date(2026, 7, 6), live=True)
    upload_image.assert_called_once()
    assert result["failed"] == 1 and result["published"] == 0
    run_tool.assert_not_called()
    remaining = list((tmp_path / "approved").glob("*.md"))
    assert len(remaining) == 1
    post = frontmatter.loads(remaining[0].read_text())
    assert not post.get("publish_attempted_at")
    assert not list((tmp_path / "published").glob("*.md"))
