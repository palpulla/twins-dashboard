"""Typed Housecall Pro HTTP client.

Handles auth, retry (429/5xx), pagination, and caches every response to
disk for offline replay and audit.
"""
from __future__ import annotations

import json
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterator

import httpx


class HCPError(Exception):
    """Base for HCP client errors."""


class HCPAuthError(HCPError):
    """401 or 403 from HCP."""


class HCPRateLimitError(HCPError):
    """429 still returned after all retries."""


class HCPServerError(HCPError):
    """5xx still returned after all retries."""


def _slugify_path(path: str) -> str:
    return path.strip("/").replace("/", "_").replace("?", "_")[:80] or "root"


class HCPClient:
    def __init__(
        self,
        *,
        api_key: str,
        base_url: str,
        cache_dir: Path,
        max_retries: int = 5,
        backoff_base: float = 1.0,
        timeout: float = 30.0,
    ):
        if not api_key:
            raise HCPAuthError("HCP_API_KEY is empty")
        self._base_url = base_url.rstrip("/")
        self._cache_dir = Path(cache_dir)
        self._cache_dir.mkdir(parents=True, exist_ok=True)
        self._max_retries = max_retries
        self._backoff_base = backoff_base
        self._client = httpx.Client(
            headers={"Authorization": f"Bearer {api_key}",
                     "Accept": "application/json"},
            timeout=timeout,
        )

    def close(self) -> None:
        self._client.close()

    def get(self, path: str, params: dict[str, Any] | None = None) -> dict[str, Any]:
        url = f"{self._base_url}{path}"
        last_err: Exception | None = None
        for attempt in range(self._max_retries + 1):
            try:
                resp = self._client.get(url, params=params)
            except httpx.HTTPError as e:
                last_err = e
                self._sleep_backoff(attempt)
                continue

            if resp.status_code in (401, 403):
                raise HCPAuthError(f"{resp.status_code} from {url}: {resp.text[:200]}")
            if resp.status_code == 429:
                if attempt >= self._max_retries:
                    raise HCPRateLimitError(f"429 after {attempt} retries on {url}")
                retry_after = float(resp.headers.get("Retry-After", self._backoff_base * (2 ** attempt)))
                time.sleep(retry_after)
                continue
            if resp.status_code >= 500:
                if attempt >= self._max_retries:
                    raise HCPServerError(f"{resp.status_code} after {attempt} retries on {url}")
                self._sleep_backoff(attempt)
                continue
            resp.raise_for_status()
            data = resp.json()
            self._archive(path, params, data)
            return data
        raise HCPServerError(f"Exhausted retries on {url}: {last_err}")

    def paginate(
        self,
        path: str,
        *,
        cursor_param: str,
        next_path: list[str],
        items_path: list[str],
        params: dict[str, Any] | None = None,
    ) -> Iterator[dict[str, Any]]:
        """Follow a cursor-paginated endpoint and yield each item across all pages."""
        cursor: Any = None
        base_params = dict(params or {})
        while True:
            call_params = dict(base_params)
            call_params[cursor_param] = cursor
            page = self.get(path, params=call_params)
            for item in _dig(page, items_path) or []:
                yield item
            nxt = _dig(page, next_path)
            if not nxt:
                return
            cursor = nxt

    def _sleep_backoff(self, attempt: int) -> None:
        if self._backoff_base <= 0:
            return
        time.sleep(self._backoff_base * (2 ** attempt))

    def _archive(self, path: str, params: dict[str, Any] | None, data: Any) -> None:
        ts = datetime.now(tz=timezone.utc).strftime("%Y%m%dT%H%M%S%f")
        fname = f"{ts}_{_slugify_path(path)}.json"
        payload = {"path": path, "params": params or {}, "response": data}
        (self._cache_dir / fname).write_text(json.dumps(payload, indent=2, default=str))


def _dig(obj: Any, path: list[str]) -> Any:
    cur = obj
    for key in path:
        if cur is None:
            return None
        if isinstance(cur, dict):
            cur = cur.get(key)
        else:
            return None
    return cur
