"""Anthropic Claude wrapper with retries, prompt caching, and a test fake."""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Optional, Protocol

from anthropic import Anthropic, APIError, RateLimitError
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
        self._sdk = Anthropic(api_key=api_key) if api_key else Anthropic()
        self.model = model or self.DEFAULT_MODEL

    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, min=1, max=10),
        retry=retry_if_exception_type((RateLimitError, APIError)),
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
