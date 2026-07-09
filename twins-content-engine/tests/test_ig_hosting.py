import hashlib
import re
from pathlib import Path

import pytest

from engine.config import InstagramConfig
from engine.ig_hosting import _load_hosting_env, upload_image

HOSTING = {
    "provider": "supabase",
    "bucket": "ig-media",
    "env_file": ".secrets/test.env",
    "url_var": "NEW_SUPABASE_URL",
    "key_var": "NEW_SUPABASE_SERVICE_ROLE_KEY",
}


def _cfg(hosting=None):
    return InstagramConfig(
        cycle_anchor="2026-07-06",
        approved_offers=[],
        banned_corporate_terms=[],
        banned_hashtags=[],
        approved_cta=[],
        max_ai_fraction=0.34,
        format_targets=[],
        monthly_mix={},
        hosting=hosting if hosting is not None else dict(HOSTING),
    )


def _write_env(tmp_path: Path, url="https://example.supabase.co", key="secret-service-key") -> Path:
    secrets_dir = tmp_path / ".secrets"
    secrets_dir.mkdir(parents=True, exist_ok=True)
    env_file = secrets_dir / "test.env"
    env_file.write_text(
        "# a comment\n"
        "\n"
        f"NEW_SUPABASE_URL={url}\n"
        f"NEW_SUPABASE_SERVICE_ROLE_KEY={key}\n"
    )
    return env_file


def test_load_hosting_env_parses_values(tmp_path):
    env_file = _write_env(tmp_path)
    url, key = _load_hosting_env(env_file, "NEW_SUPABASE_URL", "NEW_SUPABASE_SERVICE_ROLE_KEY")
    assert url == "https://example.supabase.co"
    assert key == "secret-service-key"


def test_load_hosting_env_missing_var_raises(tmp_path):
    secrets_dir = tmp_path / ".secrets"
    secrets_dir.mkdir(parents=True, exist_ok=True)
    env_file = secrets_dir / "test.env"
    env_file.write_text("NEW_SUPABASE_URL=https://example.supabase.co\n")
    with pytest.raises(ValueError, match="NEW_SUPABASE_SERVICE_ROLE_KEY"):
        _load_hosting_env(env_file, "NEW_SUPABASE_URL", "NEW_SUPABASE_SERVICE_ROLE_KEY")


def test_upload_image_success_returns_public_url(tmp_path, mocker):
    _write_env(tmp_path, url="https://example.supabase.co", key="topsecretkey")
    img = tmp_path / "photo.jpg"
    img.write_bytes(b"fake-jpeg-bytes")

    mock_resp = mocker.Mock(status_code=200, text="ok")
    mock_post = mocker.patch("engine.ig_hosting.requests.post", return_value=mock_resp)

    url = upload_image(img, _cfg(), root=tmp_path)

    # Object key = {yyyy-mm}/{sha256[:12]}_{name} so same-basename drafts can't collide.
    assert url.startswith("https://example.supabase.co/storage/v1/object/public/")
    assert re.search(r"/ig-media/\d{4}-\d{2}/[0-9a-f]{12}_photo\.jpg$", url)
    expected_digest = hashlib.sha256(b"fake-jpeg-bytes").hexdigest()[:12]
    assert f"/{expected_digest}_photo.jpg" in url
    mock_post.assert_called_once()
    called_url = mock_post.call_args[0][0]
    assert re.search(
        r"^https://example\.supabase\.co/storage/v1/object/ig-media/\d{4}-\d{2}/[0-9a-f]{12}_photo\.jpg$",
        called_url,
    )
    headers = mock_post.call_args.kwargs["headers"]
    assert headers["Authorization"].startswith("Bearer ")
    assert headers["Content-Type"] == "image/jpeg"
    assert headers["x-upsert"] == "true"
    assert mock_post.call_args.kwargs["timeout"] == 60


def test_upload_image_failure_status_raises(tmp_path, mocker):
    _write_env(tmp_path)
    img = tmp_path / "photo.png"
    img.write_bytes(b"fake-png-bytes")

    mock_resp = mocker.Mock(status_code=403, text="Forbidden: bad key")
    mocker.patch("engine.ig_hosting.requests.post", return_value=mock_resp)

    with pytest.raises(RuntimeError, match="403"):
        upload_image(img, _cfg(), root=tmp_path)


def test_upload_image_missing_env_raises_value_error(tmp_path):
    img = tmp_path / "photo.jpg"
    img.write_bytes(b"x")
    with pytest.raises(ValueError, match="NEW_SUPABASE_URL"):
        upload_image(img, _cfg(), root=tmp_path)


def test_upload_image_rejects_unsupported_suffix(tmp_path, mocker):
    _write_env(tmp_path)
    mock_post = mocker.patch("engine.ig_hosting.requests.post")
    vid = tmp_path / "clip.mp4"
    vid.write_bytes(b"fake-video-bytes")
    with pytest.raises(ValueError, match=r"Unsupported image type: \.mp4"):
        upload_image(vid, _cfg(), root=tmp_path)
    mock_post.assert_not_called()


def test_load_hosting_env_strips_export_prefix(tmp_path):
    secrets_dir = tmp_path / ".secrets"
    secrets_dir.mkdir(parents=True, exist_ok=True)
    env_file = secrets_dir / "test.env"
    env_file.write_text(
        "export NEW_SUPABASE_URL=https://example.supabase.co\n"
        "export NEW_SUPABASE_SERVICE_ROLE_KEY=secret-service-key\n"
    )
    url, key = _load_hosting_env(env_file, "NEW_SUPABASE_URL", "NEW_SUPABASE_SERVICE_ROLE_KEY")
    assert url == "https://example.supabase.co"
    assert key == "secret-service-key"


def test_load_hosting_env_unquotes_values(tmp_path):
    secrets_dir = tmp_path / ".secrets"
    secrets_dir.mkdir(parents=True, exist_ok=True)
    env_file = secrets_dir / "test.env"
    env_file.write_text(
        'NEW_SUPABASE_URL="https://example.supabase.co"\n'
        "NEW_SUPABASE_SERVICE_ROLE_KEY='secret-service-key'\n"
    )
    url, key = _load_hosting_env(env_file, "NEW_SUPABASE_URL", "NEW_SUPABASE_SERVICE_ROLE_KEY")
    assert url == "https://example.supabase.co"
    assert key == "secret-service-key"
