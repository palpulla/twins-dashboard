"""Tests for engine/hcp_client.py — mocked via respx."""
from pathlib import Path

import httpx
import pytest
import respx

from engine.hcp_client import HCPAuthError, HCPClient, HCPRateLimitError, HCPServerError


@pytest.fixture
def client(tmp_path) -> HCPClient:
    return HCPClient(
        api_key="test-key",
        base_url="https://api.housecallpro.com",
        cache_dir=tmp_path / "cache",
        max_retries=3,
        backoff_base=0.0,
    )


@respx.mock
def test_get_sends_auth_header_and_caches(client, tmp_path):
    route = respx.get("https://api.housecallpro.com/jobs").respond(
        200, json={"data": [{"id": "j1"}], "next": None}
    )
    resp = client.get("/jobs", params={"limit": 10})

    assert resp == {"data": [{"id": "j1"}], "next": None}
    assert route.called
    req = route.calls.last.request
    assert req.headers["Authorization"] == "Bearer test-key"
    cached = list((tmp_path / "cache").glob("*.json"))
    assert len(cached) == 1


@respx.mock
def test_get_401_raises_auth_error_no_retry(client):
    route = respx.get("https://api.housecallpro.com/jobs").respond(401, json={"error": "bad key"})
    with pytest.raises(HCPAuthError):
        client.get("/jobs")
    assert route.call_count == 1


@respx.mock
def test_get_retries_on_429(client):
    route = respx.get("https://api.housecallpro.com/jobs")
    route.side_effect = [
        httpx.Response(429, headers={"Retry-After": "0"}, json={"error": "rate"}),
        httpx.Response(429, headers={"Retry-After": "0"}, json={"error": "rate"}),
        httpx.Response(200, json={"data": []}),
    ]
    resp = client.get("/jobs")
    assert resp == {"data": []}
    assert route.call_count == 3


@respx.mock
def test_get_429_exhausts_retries(client):
    route = respx.get("https://api.housecallpro.com/jobs").mock(
        return_value=httpx.Response(429, headers={"Retry-After": "0"})
    )
    with pytest.raises(HCPRateLimitError):
        client.get("/jobs")


@respx.mock
def test_get_500_retries(client):
    route = respx.get("https://api.housecallpro.com/jobs")
    route.side_effect = [
        httpx.Response(500),
        httpx.Response(200, json={"ok": True}),
    ]
    resp = client.get("/jobs")
    assert resp == {"ok": True}


@respx.mock
def test_get_500_exhausts_retries(client):
    route = respx.get("https://api.housecallpro.com/jobs").mock(
        return_value=httpx.Response(500)
    )
    with pytest.raises(HCPServerError):
        client.get("/jobs")


@respx.mock
def test_paginate_follows_cursor(client):
    # First call has no cursor param; second call has cursor=c2.
    import httpx as _httpx
    responses = iter([
        _httpx.Response(200, json={"data": [{"id": "a"}], "next_cursor": "c2"}),
        _httpx.Response(200, json={"data": [{"id": "b"}], "next_cursor": None}),
    ])
    respx.get("https://api.housecallpro.com/jobs").mock(side_effect=lambda req: next(responses))
    pages = list(client.paginate("/jobs", cursor_param="cursor",
                                  next_path=["next_cursor"], items_path=["data"]))
    assert [p["id"] for p in pages] == ["a", "b"]
