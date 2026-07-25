"""GHL (GoHighLevel) Social Planner delivery rail — publish a post to
multiple connected social accounts (Facebook, Instagram, GBP, LinkedIn, ...)
in one call.

Verified live against Daniel's account (see spec) — trust the shapes here
over the public docs:

- Base: https://services.leadconnectorhq.com
- Headers: Authorization: Bearer <token>, Version: 2021-07-28, JSON content
  type, and a browsery User-Agent (Cloudflare 1010-blocks default python UAs
  on some endpoints).
- GET  /social-media-posting/{locationId}/accounts
      -> {"results": {"accounts": [{"id", "platform", "name", "isExpired"}]}}
- POST /social-media-posting/{locationId}/posts
      body: {"accountIds": [...], "summary": <caption>, "type": "post",
             "status": "draft"|"published"|"scheduled",
             "userId": <location id works>, "media": [{"url": ...}]}
      -> 201 {"success": true, "statusCode": 201, "results": {"post": {"_id": ...}}}
"""
from __future__ import annotations

import os
from pathlib import Path

import requests

_API_BASE = "https://services.leadconnectorhq.com"
_VERSION = "2021-07-28"
_USER_AGENT = "Mozilla/5.0 (compatible; TwinsContentEngine/1.0)"
_TIMEOUT = 30


def _unquote(value: str) -> str:
    """Strip one pair of matching surrounding quotes, if present."""
    if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
        return value[1:-1]
    return value


def _load_ghl_env(env_file: Path) -> tuple[str, str]:
    """Parse GHL_API_TOKEN and GHL_LOCATION_ID out of env_file.

    Tolerates shell-style lines: a leading ``export `` prefix and matching
    surrounding quotes around the value are stripped. Never logs the values.
    Raises ValueError naming the missing var(s) if either is absent.
    """
    values: dict[str, str] = {}
    text = env_file.read_text() if env_file.exists() else ""
    for line in text.splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or "=" not in stripped:
            continue
        if stripped.startswith("export "):
            stripped = stripped[len("export "):].lstrip()
        key, _, val = stripped.partition("=")
        values[key.strip()] = _unquote(val.strip())
    token = values.get("GHL_API_TOKEN")
    location = values.get("GHL_LOCATION_ID")
    missing = [name for name, val in (("GHL_API_TOKEN", token), ("GHL_LOCATION_ID", location)) if not val]
    if missing:
        raise ValueError(f"Missing {', '.join(missing)} in {env_file}")
    return token, location


def _headers(token: str) -> dict:
    return {
        "Authorization": f"Bearer {token}",
        "Version": _VERSION,
        "Content-Type": "application/json",
        "Accept": "application/json",
        "User-Agent": _USER_AGENT,
    }


def get_social_accounts(env_file: Path) -> list[dict]:
    """Return connected GHL social accounts as [{id, platform, name, isExpired}, ...].

    Raises ValueError if env vars are missing, RuntimeError on a non-2xx
    response (message includes status + first 300 chars of body, never the
    token).
    """
    token, location = _load_ghl_env(env_file)
    url = f"{_API_BASE}/social-media-posting/{location}/accounts"
    resp = requests.get(url, headers=_headers(token), timeout=_TIMEOUT)
    if not resp.ok:
        raise RuntimeError(f"GHL accounts fetch failed: status {resp.status_code}: {resp.text[:300]}")
    data = resp.json()
    results = data.get("results") if isinstance(data.get("results"), dict) else {}
    accounts = results.get("accounts") or []
    out = []
    for acc in accounts:
        if not isinstance(acc, dict):
            continue
        out.append({
            "id": acc.get("id"),
            "platform": (acc.get("platform") or "").lower(),
            "name": acc.get("name"),
            "isExpired": bool(acc.get("isExpired")),
        })
    return out



def _mime_for(url: str) -> str:
    lowered = url.lower().split("?")[0]
    if lowered.endswith(".png"):
        return "image/png"
    if lowered.endswith((".jpg", ".jpeg")):
        return "image/jpeg"
    return "image/png"


# Hard guard: content that is obviously a test/probe must NEVER reach a live
# platform. This is a backstop for careless debugging — a "PROBE C" test post
# reached the live Twins Facebook page on 2026-07-09 because a manual call used
# status="published". This makes that impossible regardless of the caller.
_TEST_MARKERS = ("probe", "schema probe", "deleteme", "test caption", "test post",
                 "lorem ipsum", "asdf", "do not publish", "not for publishing")


def _reject_if_test_content(caption: str, status: str) -> None:
    if status == "draft":
        return  # drafts never go live; probing with drafts is always allowed
    lowered = (caption or "").lower()
    for marker in _TEST_MARKERS:
        if marker in lowered:
            raise RuntimeError(
                f"Refusing to PUBLISH content that looks like a test post "
                f"(matched marker {marker!r}). Use status='draft' for probing."
            )
    if len((caption or "").strip()) < 20:
        raise RuntimeError(
            "Refusing to PUBLISH a suspiciously short caption "
            f"({len((caption or '').strip())} chars). Real posts are longer; "
            "use status='draft' for probing."
        )


def reject_if_retired() -> None:
    """Phase 0 (2026-07-25) retired GHL social publishing from the content engine.

    Scheduling moved to the twins-marketing-os app. The GHL Social Planner UI
    stays usable by hand; this only stops code from writing posts. Fails closed:
    only the exact string "true" re-enables writes.

    Public because scripts/publish_to_ghl.py has its own independent write path
    (its own ghl_post helper) and must call this directly — guarding
    create_social_post alone does not cover it.
    """
    if os.environ.get("GHL_SOCIAL_WRITES_ENABLED") != "true":
        raise RuntimeError(
            "GHL social writes are retired (Phase 0, 2026-07-25). Scheduling now "
            "lives in twins-marketing-os; use the GHL Social Planner UI by hand. "
            "Set GHL_SOCIAL_WRITES_ENABLED=true to override."
        )


def create_social_post(
    env_file: Path,
    account_ids: list[str],
    caption: str,
    image_url: str,
    status: str = "published",
) -> dict:
    """POST a post to the given GHL social accounts.

    Raises RuntimeError immediately when GHL writes are retired (the default
    since Phase 0, 2026-07-25) — set GHL_SOCIAL_WRITES_ENABLED=true to
    override. Raises ValueError if env vars are missing. Raises RuntimeError
    on a non-2xx response, a response where success is not True, or when a
    non-draft (live) post looks like test/probe content. The message includes
    the status code and first 300 chars of the response body, never the token.
    """
    reject_if_retired()
    _reject_if_test_content(caption, status)
    token, location = _load_ghl_env(env_file)
    url = f"{_API_BASE}/social-media-posting/{location}/posts"
    body = {
        "accountIds": account_ids,
        "summary": caption,
        "type": "post",
        "status": status,
        "userId": location,
        # GHL's publish-now path reads media type and tags server-side
        # (undefined.includes crashes with a 400 when either is absent;
        # draft status never hits that path). Always send both.
        "media": [{"url": image_url, "type": _mime_for(image_url)}],
        "tags": [],
    }
    resp = requests.post(url, headers=_headers(token), json=body, timeout=_TIMEOUT)
    if not resp.ok:
        raise RuntimeError(f"GHL post create failed: status {resp.status_code}: {resp.text[:300]}")
    data = resp.json()
    if data.get("success") is not True:
        raise RuntimeError(f"GHL post create not successful: status {resp.status_code}: {resp.text[:300]}")
    return data
