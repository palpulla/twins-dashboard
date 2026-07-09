"""Upload local draft images to public hosting (Supabase storage) for IG publishing."""
from __future__ import annotations

import datetime as dt
import hashlib
from pathlib import Path

import requests

from engine.config import InstagramConfig

_CONTENT_TYPES = {
    ".jpg": "image/jpeg",
    ".jpeg": "image/jpeg",
    ".png": "image/png",
}


def _content_type_for(path: Path) -> str:
    """Content type for an allowed image suffix; anything else is rejected.

    The IG program is photo-first: only jpg/jpeg/png ever reach the upload
    path (videos are excluded upstream in ig_assets), but guard here too so
    a stray file can never be hosted as application/octet-stream.
    """
    ctype = _CONTENT_TYPES.get(path.suffix.lower())
    if ctype is None:
        raise ValueError(f"Unsupported image type: {path.suffix}")
    return ctype


def _unquote(value: str) -> str:
    """Strip one pair of matching surrounding quotes, if present."""
    if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
        return value[1:-1]
    return value


def _load_hosting_env(env_file: Path, url_var: str, key_var: str) -> tuple[str, str]:
    """Parse KEY=VALUE lines out of env_file, ignoring comments/blank lines.

    Tolerates shell-style lines: a leading ``export `` prefix and matching
    surrounding quotes around the value are stripped. Never logs the values.
    Raises ValueError naming the missing var(s) if either url_var or key_var
    is absent from the file.
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
    url = values.get(url_var)
    key = values.get(key_var)
    if not url or not key:
        raise ValueError(f"Missing {url_var} or {key_var} in {env_file}")
    return url, key


def upload_image(local_path: Path, cfg: InstagramConfig, root: Path) -> str:
    """Upload local_path to the configured Supabase storage bucket.

    The object key embeds a short content hash so two drafts sharing a
    basename in the same month can never overwrite each other (x-upsert
    is only ever a same-content re-upload, e.g. a crash/rerun).

    Returns the public URL. Raises RuntimeError on a non-2xx response
    (including the status code and first 300 chars of response text, but
    never the service-role key). Raises ValueError if hosting env vars are
    missing or the file type is not an allowed image.
    """
    hosting = cfg.hosting
    env_file = (root / hosting["env_file"]).resolve()
    content_type = _content_type_for(local_path)
    url, key = _load_hosting_env(env_file, hosting["url_var"], hosting["key_var"])
    bucket = hosting["bucket"]

    file_bytes = local_path.read_bytes()
    digest = hashlib.sha256(file_bytes).hexdigest()[:12]
    yyyy_mm = dt.date.today().strftime("%Y-%m")
    object_key = f"{yyyy_mm}/{digest}_{local_path.name}"
    endpoint = f"{url}/storage/v1/object/{bucket}/{object_key}"
    headers = {
        "Authorization": f"Bearer {key}",
        "Content-Type": content_type,
        "x-upsert": "true",
    }
    resp = requests.post(endpoint, headers=headers, data=file_bytes, timeout=60)
    if resp.status_code not in (200, 201):
        raise RuntimeError(
            f"Supabase upload failed: status {resp.status_code}: {resp.text[:300]}"
        )
    return f"{url}/storage/v1/object/public/{bucket}/{object_key}"
