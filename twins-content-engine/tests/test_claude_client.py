"""Tests for engine.claude_client."""
from __future__ import annotations

from unittest.mock import MagicMock

import pytest

from engine.claude_client import ClaudeClient, FakeClaudeClient, CompletionResult


def test_fake_client_returns_queued_responses():
    fake = FakeClaudeClient(responses=["first", "second"])
    r1 = fake.complete(system="s", user="u1")
    r2 = fake.complete(system="s", user="u2")
    assert r1.text == "first"
    assert r2.text == "second"
    assert len(fake.calls) == 2
    assert fake.calls[0]["user"] == "u1"


def test_fake_client_raises_when_empty():
    fake = FakeClaudeClient(responses=[])
    with pytest.raises(RuntimeError, match="FakeClaudeClient exhausted"):
        fake.complete(system="s", user="u")


def test_real_client_calls_anthropic_sdk(monkeypatch):
    """ClaudeClient.complete calls Anthropic.messages.create with correct args."""
    mock_anthropic_cls = MagicMock()
    mock_sdk = MagicMock()
    mock_anthropic_cls.return_value = mock_sdk
    mock_response = MagicMock()
    mock_response.content = [MagicMock(text="hello")]
    mock_response.usage = MagicMock(input_tokens=100, output_tokens=50)
    mock_sdk.messages.create.return_value = mock_response

    monkeypatch.setattr("engine.claude_client.Anthropic", mock_anthropic_cls)

    client = ClaudeClient(api_key="fake", model="claude-sonnet-4-6")
    result = client.complete(system="sys", user="user prompt")

    assert result.text == "hello"
    assert result.input_tokens == 100
    assert result.output_tokens == 50

    call_args = mock_sdk.messages.create.call_args
    assert call_args.kwargs["model"] == "claude-sonnet-4-6"
    # system sent as list with cache_control for prompt caching
    assert isinstance(call_args.kwargs["system"], list)
    assert call_args.kwargs["system"][0]["cache_control"] == {"type": "ephemeral"}
    assert call_args.kwargs["system"][0]["text"] == "sys"
    assert call_args.kwargs["messages"] == [{"role": "user", "content": "user prompt"}]


def test_real_client_retries_on_rate_limit(monkeypatch):
    from anthropic import RateLimitError

    mock_anthropic_cls = MagicMock()
    mock_sdk = MagicMock()
    mock_anthropic_cls.return_value = mock_sdk

    error = RateLimitError(
        message="slow down",
        response=MagicMock(status_code=429),
        body=None,
    )
    ok = MagicMock()
    ok.content = [MagicMock(text="ok")]
    ok.usage = MagicMock(input_tokens=1, output_tokens=1)
    mock_sdk.messages.create.side_effect = [error, ok]

    monkeypatch.setattr("engine.claude_client.Anthropic", mock_anthropic_cls)

    client = ClaudeClient(api_key="fake")
    result = client.complete(system="s", user="u")
    assert result.text == "ok"
    assert mock_sdk.messages.create.call_count == 2


def test_real_client_reraises_after_exhaustion(monkeypatch):
    """After 3 consecutive retryable errors, the underlying exception propagates."""
    from anthropic import RateLimitError

    mock_anthropic_cls = MagicMock()
    mock_sdk = MagicMock()
    mock_anthropic_cls.return_value = mock_sdk

    error = RateLimitError(
        message="still slow",
        response=MagicMock(status_code=429),
        body=None,
    )
    mock_sdk.messages.create.side_effect = [error, error, error]

    monkeypatch.setattr("engine.claude_client.Anthropic", mock_anthropic_cls)

    client = ClaudeClient(api_key="fake")
    with pytest.raises(RateLimitError):
        client.complete(system="s", user="u")
    assert mock_sdk.messages.create.call_count == 3


def test_real_client_does_not_retry_on_auth_error(monkeypatch):
    """AuthenticationError is a permanent failure; it must NOT be retried."""
    from anthropic import AuthenticationError

    mock_anthropic_cls = MagicMock()
    mock_sdk = MagicMock()
    mock_anthropic_cls.return_value = mock_sdk

    auth_err = AuthenticationError(
        message="bad key",
        response=MagicMock(status_code=401),
        body=None,
    )
    mock_sdk.messages.create.side_effect = auth_err

    monkeypatch.setattr("engine.claude_client.Anthropic", mock_anthropic_cls)

    client = ClaudeClient(api_key="fake")
    with pytest.raises(AuthenticationError):
        client.complete(system="s", user="u")
    # Critical: only ONE call, not 3.
    assert mock_sdk.messages.create.call_count == 1


def test_real_client_returns_empty_text_when_response_content_empty(monkeypatch):
    """Defensive: empty response.content -> CompletionResult.text == ''."""
    mock_anthropic_cls = MagicMock()
    mock_sdk = MagicMock()
    mock_anthropic_cls.return_value = mock_sdk
    mock_response = MagicMock()
    mock_response.content = []
    mock_response.usage = MagicMock(input_tokens=5, output_tokens=0)
    mock_sdk.messages.create.return_value = mock_response

    monkeypatch.setattr("engine.claude_client.Anthropic", mock_anthropic_cls)

    client = ClaudeClient(api_key="fake")
    result = client.complete(system="s", user="u")
    assert result.text == ""
    assert result.input_tokens == 5
