"""Instagram-specific preflight validation. Reuses engine.rules primitives."""
from __future__ import annotations

import re

from engine.config import InstagramConfig
from engine.rules import RuleReport, Violation

_EMOJI_RE = re.compile(
    "[" "\U0001F300-\U0001FAFF" "\U00002600-\U000027BF" "\U0001F1E6-\U0001F1FF" "]",
    flags=re.UNICODE,
)
_OUT_OF_STATE_RE = re.compile(r"\b(kentucky|lexington|ky)\b", re.IGNORECASE)
_DOLLAR_RE = re.compile(r"\$\d{1,3}(?:,\d{3})*(?:\.\d+)?")
# Note: same-day service is a permitted, owner-confirmed claim, so it is NOT blocked.


def _dollar_values(text: str) -> list[tuple[str, float]]:
    """Return (matched_token, numeric_value) for each currency amount in text."""
    out = []
    for m in _DOLLAR_RE.finditer(text):
        tok = m.group(0)
        out.append((tok, float(tok.replace("$", "").replace(",", ""))))
    return out


def check_instagram_caption(text: str, cfg: InstagramConfig) -> RuleReport:
    violations: list[Violation] = []

    if _EMOJI_RE.search(text):
        violations.append(Violation("ig:emoji", "error", "Emojis are not allowed."))

    if _OUT_OF_STATE_RE.search(text):
        violations.append(Violation("ig:out_of_state", "error", "Kentucky/Lexington reference not allowed."))

    lowered = text.lower()
    for term in cfg.banned_corporate_terms:
        if re.search(rf"\b{re.escape(term)}(?:s|es)?\b", lowered):
            violations.append(Violation("ig:corporate_term", "error", f"Corporate term not allowed: {term!r}"))

    for tag in cfg.banned_hashtags:
        if tag.lower() in lowered:
            violations.append(Violation("ig:banned_hashtag", "error", f"Banned hashtag: {tag}"))

    # Compare dollar amounts by numeric value so decimals/thousands/trailing
    # punctuation don't create false matches or false positives.
    approved_values = {value for _, value in _dollar_values(" ".join(cfg.approved_offers))}
    for tok, value in _dollar_values(text):
        if value not in approved_values:
            violations.append(Violation("ig:unapproved_offer", "error",
                                        f"Dollar amount {tok} is not an approved offer."))

    return RuleReport(passed=len(violations) == 0, violations=violations)
