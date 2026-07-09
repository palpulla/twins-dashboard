import pytest

from engine.ghl_social import create_social_post, get_social_accounts


def _write_env(tmp_path, token="pit-abc123", location="loc-xyz"):
    env_file = tmp_path / ".env"
    env_file.write_text(f"GHL_API_TOKEN={token}\nGHL_LOCATION_ID={location}\n")
    return env_file


class _FakeResponse:
    def __init__(self, status_code=200, json_data=None, text=""):
        self.status_code = status_code
        self.ok = 200 <= status_code < 300
        self._json = json_data or {}
        self.text = text or str(json_data)

    def json(self):
        return self._json


def test_missing_env_vars_raises_value_error(tmp_path):
    env_file = tmp_path / ".env"
    env_file.write_text("SOMETHING_ELSE=1\n")
    with pytest.raises(ValueError, match="GHL_API_TOKEN"):
        get_social_accounts(env_file)


def test_missing_one_env_var_names_it(tmp_path):
    env_file = tmp_path / ".env"
    env_file.write_text("GHL_API_TOKEN=pit-abc\n")
    with pytest.raises(ValueError, match="GHL_LOCATION_ID"):
        get_social_accounts(env_file)


def test_get_social_accounts_parses_results(tmp_path, mocker):
    env_file = _write_env(tmp_path)
    payload = {
        "results": {
            "accounts": [
                {"id": "acct-fb", "platform": "facebook", "name": "Twins FB", "isExpired": False},
                {"id": "acct-ig", "platform": "instagram", "name": "Twins IG", "isExpired": True},
            ]
        }
    }
    mocker.patch("engine.ghl_social.requests.get", return_value=_FakeResponse(200, payload))
    accounts = get_social_accounts(env_file)
    assert len(accounts) == 2
    assert accounts[0] == {"id": "acct-fb", "platform": "facebook", "name": "Twins FB", "isExpired": False}
    assert accounts[1]["isExpired"] is True


def test_get_social_accounts_filters_expired(tmp_path, mocker):
    env_file = _write_env(tmp_path)
    payload = {
        "results": {
            "accounts": [
                {"id": "acct-fb", "platform": "facebook", "name": "Twins FB", "isExpired": False},
                {"id": "acct-ig", "platform": "instagram", "name": "Twins IG", "isExpired": True},
            ]
        }
    }
    mocker.patch("engine.ghl_social.requests.get", return_value=_FakeResponse(200, payload))
    accounts = get_social_accounts(env_file)
    active = [a for a in accounts if not a["isExpired"]]
    assert active == [{"id": "acct-fb", "platform": "facebook", "name": "Twins FB", "isExpired": False}]


def test_get_social_accounts_raises_without_leaking_token(tmp_path, mocker):
    env_file = _write_env(tmp_path, token="pit-supersecret")
    mocker.patch("engine.ghl_social.requests.get", return_value=_FakeResponse(401, text="unauthorized"))
    with pytest.raises(RuntimeError) as exc:
        get_social_accounts(env_file)
    assert "401" in str(exc.value)
    assert "pit-supersecret" not in str(exc.value)


def test_create_social_post_success(tmp_path, mocker):
    env_file = _write_env(tmp_path, location="loc-xyz")
    resp_payload = {"success": True, "statusCode": 201, "results": {"post": {"_id": "abc123"}}}
    mock_post = mocker.patch("engine.ghl_social.requests.post", return_value=_FakeResponse(201, resp_payload))
    result = create_social_post(env_file, ["acct-fb", "acct-ig"], "Another new garage door installed for a local homeowner in the Madison and Milwaukee areas.", "https://example.com/x.jpg")
    assert result["success"] is True
    assert mock_post.call_count == 1
    _, kwargs = mock_post.call_args
    body = kwargs["json"]
    assert body["accountIds"] == ["acct-fb", "acct-ig"]
    assert body["summary"] == "Another new garage door installed for a local homeowner in the Madison and Milwaukee areas."
    assert body["status"] == "published"
    assert body["userId"] == "loc-xyz"
    # media type + tags are REQUIRED: GHL's publish-now path 400s without them
    assert body["media"] == [{"url": "https://example.com/x.jpg", "type": "image/jpeg"}]
    assert body["tags"] == []
    assert body["type"] == "post"


def test_create_social_post_default_status_is_published(tmp_path, mocker):
    env_file = _write_env(tmp_path)
    mock_post = mocker.patch(
        "engine.ghl_social.requests.post",
        return_value=_FakeResponse(201, {"success": True}),
    )
    create_social_post(env_file, ["acct-fb"], "Another new garage door installed for a local homeowner in the Madison and Milwaukee areas.", "https://example.com/x.jpg")
    assert mock_post.call_args.kwargs["json"]["status"] == "published"


def test_create_social_post_honors_status_override(tmp_path, mocker):
    env_file = _write_env(tmp_path)
    mock_post = mocker.patch(
        "engine.ghl_social.requests.post",
        return_value=_FakeResponse(201, {"success": True}),
    )
    create_social_post(env_file, ["acct-fb"], "cap", "https://example.com/x.jpg", status="draft")
    assert mock_post.call_args.kwargs["json"]["status"] == "draft"


def test_create_social_post_raises_on_non_2xx_without_leaking_token(tmp_path, mocker):
    env_file = _write_env(tmp_path, token="pit-supersecret")
    mocker.patch("engine.ghl_social.requests.post", return_value=_FakeResponse(500, text="server error"))
    with pytest.raises(RuntimeError) as exc:
        create_social_post(env_file, ["acct-fb"], "Another new garage door installed for a local homeowner in the Madison and Milwaukee areas.", "https://example.com/x.jpg")
    assert "500" in str(exc.value)
    assert "pit-supersecret" not in str(exc.value)


def test_create_social_post_raises_when_success_not_true(tmp_path, mocker):
    env_file = _write_env(tmp_path, token="pit-supersecret")
    mocker.patch(
        "engine.ghl_social.requests.post",
        return_value=_FakeResponse(200, {"success": False, "msg": "nope"}),
    )
    with pytest.raises(RuntimeError) as exc:
        create_social_post(env_file, ["acct-fb"], "Another new garage door installed for a local homeowner in the Madison and Milwaukee areas.", "https://example.com/x.jpg")
    assert "pit-supersecret" not in str(exc.value)


def test_create_social_post_missing_env_vars_raises_value_error(tmp_path):
    env_file = tmp_path / ".env"
    env_file.write_text("# empty\n")
    with pytest.raises(ValueError):
        create_social_post(env_file, ["acct-fb"], "Another new garage door installed for a local homeowner in the Madison and Milwaukee areas.", "https://example.com/x.jpg")


def test_publish_rejects_probe_content(tmp_path, mocker):
    env_file = _write_env(tmp_path)
    mock_post = mocker.patch("engine.ghl_social.requests.post")
    import pytest
    for bad in ("PROBE C", "test post do not publish", "deleteme", "asdf"):
        with pytest.raises(RuntimeError, match="test post"):
            create_social_post(env_file, ["acct"], bad, "https://x.com/y.png", status="published")
    mock_post.assert_not_called()  # never even hit the network


def test_publish_rejects_too_short_caption(tmp_path, mocker):
    env_file = _write_env(tmp_path)
    mock_post = mocker.patch("engine.ghl_social.requests.post")
    import pytest
    with pytest.raises(RuntimeError, match="short"):
        create_social_post(env_file, ["acct"], "New door!", "https://x.com/y.png", status="published")
    mock_post.assert_not_called()


def test_draft_probe_content_is_allowed(tmp_path, mocker):
    # drafts never go live, so probing with a draft must still work
    env_file = _write_env(tmp_path)
    mocker.patch("engine.ghl_social.requests.post",
                 return_value=_FakeResponse(201, {"success": True, "results": {"post": {"_id": "d1"}}}))
    out = create_social_post(env_file, ["acct"], "PROBE X", "https://x.com/y.png", status="draft")
    assert out["success"] is True
