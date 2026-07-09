"""Upload local draft images to public hosting (Supabase storage) for IG publishing."""
from __future__ import annotations

import datetime as dt
from pathlib import Path

import requests

from engine.config import InstagramConfig

_CONTENT_TYPES = {
    ".jpg": "image/jpeg",
    ".jpeg": "image/jpeg",
    ".png": "image/png",
}


def _content_type_for(path: Path) -> str:
    return _CONTENT_TYPES.get(path.suffix.lower(), "application/octet-stream")


def _load_hosting_env(env_file: Path, url_var: str, key_var: str) -> tuple[str, str]:
    """Parse KEY=VALUE lines out of env_file, ignoring comments/blank lines.

    Never logs the values. Raises ValueError naming the missing var(s) if
    either url_var or key_var is absent from the file.
    """
    values: dict[str, str] = {}
    text = env_file.read_text() if env_file.exists() else ""
    for line in text.splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or "=" not in stripped:
            continue
        key, _, val = stripped.partition("=")
        values[key.strip()] = val.strip()
    url = values.get(url_var)
    key = values.get(key_var)
    if not url or not key:
        raise ValueError(f"Missing {url_var} or {key_var} in {env_file}")
    return url, key


def upload_image(local_path: Path, cfg: InstagramConfig, root: Path) -> str:
    """Upload local_path to the configured Supabase storage bucket.

    Returns the public URL. Raises RuntimeError on a non-2xx response
    (including the status code and first 300 chars of response text, but
    never the service-role key). Raises ValueError if hosting env vars are
    missing.
    """
    hosting = cfg.hosting
    env_file = (root / hosting["env_file"]).resolve()
    url, key = _load_hosting_env(env_file, hosting["url_var"], hosting["key_var"])
    bucket = hosting["bucket"]

    object_key = f"{dt.date.today().strftime('%Y-%m')}/{local_path.name}"
    endpoint = f"{url}/storage/v1/object/{bucket}/{object_key}"
    headers = {
        "Authorization": f"Bearer {key}",
        "Content-Type": _content_type_for(local_path),
        "x-upsert": "true",
    }
    resp = requests.post(endpoint, headers=headers, data=local_path.read_bytes(), timeout=60)
    if resp.status_code not in (200, 201):
        raise RuntimeError(
            f"Supabase upload failed: status {resp.status_code}: {resp.text[:300]}"
        )
    return f"{url}/storage/v1/object/public/{bucket}/{object_key}"
