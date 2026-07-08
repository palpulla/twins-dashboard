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
_DOLLAR_RE = re.compile(r"\$\d[\d,]*(?:\.\d{2})?")
# Note: same-day service is a permitted, owner-confirmed claim, so it is NOT blocked.


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

    # Dollar strings must exactly match a dollar amount that appears in an
    # approved offer (substring matching would let e.g. "$4" slip through as
    # a false-positive match against "$49 tune-up").
    approved_dollars = {
        m.group(0) for offer in cfg.approved_offers for m in _DOLLAR_RE.finditer(offer)
    }
    for m in _DOLLAR_RE.finditer(text):
        dollar = m.group(0)
        if dollar not in approved_dollars:
            violations.append(Violation("ig:unapproved_offer", "error",
                                        f"Dollar amount {dollar} is not an approved offer."))

    return RuleReport(passed=len(violations) == 0, violations=violations)
