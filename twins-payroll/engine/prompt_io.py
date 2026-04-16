"""Production IO adapter for the parts prompt, backed by prompt_toolkit."""
from __future__ import annotations

from prompt_toolkit import PromptSession
from prompt_toolkit.completion import FuzzyWordCompleter

from engine.price_sheet import PriceSheet


class PromptToolkitIO:
    """Production IOProtocol implementation with autocomplete."""

    def __init__(self, price_sheet: PriceSheet):
        self._session = PromptSession(completer=FuzzyWordCompleter(price_sheet.part_names))

    def write(self, text: str) -> None:
        print(text)

    def read(self, prompt: str) -> str:
        return self._session.prompt(prompt)
