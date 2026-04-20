"""Anthropic Claude wrapper with retries, prompt caching, and a test fake."""
from __future__ import annotations

import json
import os
import subprocess
from dataclasses import dataclass, field
from typing import Any, Optional, Protocol

from dotenv import load_dotenv

from anthropic import (
    Anthropic,
    APIConnectionError,
    InternalServerError,
    RateLimitError,
)

load_dotenv(override=True)
from tenacity import (
    retry,
    retry_if_exception_type,
    stop_after_attempt,
    wait_exponential,
)


@dataclass
class CompletionResult:
    text: str
    input_tokens: int = 0
    output_tokens: int = 0


class ClaudeClientProtocol(Protocol):
    def complete(
        self,
        *,
        system: str,
        user: str,
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> CompletionResult: ...


class ClaudeClient:
    """Real Anthropic client with retries and prompt caching on system prompts."""

    DEFAULT_MODEL = "claude-sonnet-4-6"

    def __init__(self, api_key: Optional[str] = None, model: Optional[str] = None) -> None:
        # max_retries=0 hands retry policy entirely to tenacity (see @retry below).
        self._sdk = Anthropic(api_key=api_key, max_retries=0)
        self.model = model or self.DEFAULT_MODEL

    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, min=1, max=10),
        retry=retry_if_exception_type((RateLimitError, APIConnectionError, InternalServerError)),
        reraise=True,
    )
    def complete(
        self,
        *,
        system: str,
        user: str,
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> CompletionResult:
        response = self._sdk.messages.create(
            model=self.model,
            max_tokens=max_tokens,
            temperature=temperature,
            system=[
                {
                    "type": "text",
                    "text": system,
                    "cache_control": {"type": "ephemeral"},
                }
            ],
            messages=[{"role": "user", "content": user}],
        )
        text = response.content[0].text if response.content else ""
        return CompletionResult(
            text=text,
            input_tokens=getattr(response.usage, "input_tokens", 0),
            output_tokens=getattr(response.usage, "output_tokens", 0),
        )


_MODEL_ALIAS = {
    "claude-sonnet-4-6": "sonnet",
    "claude-haiku-4-5-20251001": "haiku",
    "claude-opus-4-7": "opus",
}


class CLIClaudeClient:
    """Fallback client that shells out to the `claude` CLI.

    Used when direct Anthropic API access is blocked (e.g., a new account's
    credit balance hasn't propagated yet). Costs are covered by the user's
    Claude Code subscription instead of prepaid API credits.
    """

    DEFAULT_MODEL = "claude-sonnet-4-6"

    def __init__(self, model: Optional[str] = None) -> None:
        full = model or self.DEFAULT_MODEL
        self.model = _MODEL_ALIAS.get(full, full)

    def complete(
        self,
        *,
        system: str,
        user: str,
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> CompletionResult:
        env = dict(os.environ)
        env.pop("ANTHROPIC_API_KEY", None)
        result = subprocess.run(
            [
                "claude",
                "-p",
                "--output-format", "json",
                "--model", self.model,
                "--system-prompt", system,
                user,
            ],
            capture_output=True,
            text=True,
            env=env,
            timeout=300,
        )
        if result.returncode != 0:
            raise RuntimeError(
                f"claude CLI exited {result.returncode}: {result.stderr.strip() or result.stdout.strip()}"
            )
        payload = json.loads(result.stdout)
        if payload.get("is_error"):
            raise RuntimeError(f"claude CLI error: {payload.get('result', payload)}")
        usage = payload.get("usage", {}) or {}
        return CompletionResult(
            text=payload.get("result", ""),
            input_tokens=int(usage.get("input_tokens", 0)),
            output_tokens=int(usage.get("output_tokens", 0)),
        )


def make_client(model: Optional[str] = None) -> ClaudeClientProtocol:
    """Return the right client based on CONTENT_ENGINE_USE_CLI env var."""
    if os.environ.get("CONTENT_ENGINE_USE_CLI") == "1":
        return CLIClaudeClient(model=model)
    return ClaudeClient(model=model)


@dataclass
class FakeClaudeClient:
    """Test double. Pass a list of response strings; they are returned in order."""

    responses: list[str]
    calls: list[dict[str, Any]] = field(default_factory=list)

    def complete(
        self,
        *,
        system: str,
        user: str,
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> CompletionResult:
        self.calls.append(
            {"system": system, "user": user, "max_tokens": max_tokens, "temperature": temperature}
        )
        if not self.responses:
            raise RuntimeError("FakeClaudeClient exhausted — add more responses")
        return CompletionResult(text=self.responses.pop(0), input_tokens=0, output_tokens=0)
