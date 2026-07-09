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

_ADDRESS_RE = re.compile(
    r"\b\d{1,5}\s+(?:[A-Z][a-z]+\s){0,2}"
    r"(?:St|Street|Ave|Avenue|Rd|Road|Dr|Drive|Ln|Lane|Blvd|Ct|Court|Way|Trail|Tr)\b\.?",
)


def _to_value(token: str) -> float:
    return float(token.replace("$", "").replace(",", ""))


def _dollar_values(text: str) -> list[tuple[str, float, int]]:
    """Return (matched_token, numeric_value, match_start) for each currency amount in text."""
    out = []
    for m in _DOLLAR_RE.finditer(text):
        tok = m.group(0)
        out.append((tok, _to_value(tok), m.start()))
    return out


# Flexible amount group used inside approved-phrase regexes: tolerates commas and
# optional cents ("$49.00 tune-up"); the matched amount is verified numerically
# against the phrase's own value, so "$49.99 tune-up" is never vouched for.
_AMOUNT_GROUP = r"(\$\d[\d,]*(?:\.\d+)?)"


def _approved_phrase_patterns(cfg: InstagramConfig) -> list[tuple[re.Pattern[str], float]]:
    """Compile each approved offer phrase into (regex, expected_value).

    The phrase's literal dollar token becomes a flexible amount group and word
    gaps become ``\\s+`` so whitespace variants still match. Phrases without a
    dollar amount are skipped (they carry no value to vouch for).
    """
    patterns: list[tuple[re.Pattern[str], float]] = []
    for phrase in cfg.approved_offer_phrases:
        m = _DOLLAR_RE.search(phrase)
        if not m:
            continue
        parts: list[str] = []
        before = phrase[: m.start()].split()
        after = phrase[m.end():].split()
        if before:
            parts.append(r"\s+".join(re.escape(w) for w in before))
        parts.append(_AMOUNT_GROUP)
        if after:
            parts.append(r"\s+".join(re.escape(w) for w in after))
        patterns.append((re.compile(r"\s+".join(parts), re.IGNORECASE), _to_value(m.group(0))))
    return patterns


def _vouched_amount_spans(text: str, cfg: InstagramConfig) -> list[tuple[int, int]]:
    """Spans of dollar tokens that sit inside an approved offer phrase occurrence."""
    spans: list[tuple[int, int]] = []
    for pattern, phrase_value in _approved_phrase_patterns(cfg):
        for m in pattern.finditer(text):
            if _to_value(m.group(1)) == phrase_value:
                spans.append((m.start(1), m.end(1)))
    return spans


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

    for place in cfg.banned_place_terms:
        if re.search(rf"\b{re.escape(place)}\b", lowered):
            violations.append(Violation("ig:out_of_area", "error",
                                        f"Out-of-area reference not allowed: {place!r}"))

    if _ADDRESS_RE.search(text):
        violations.append(Violation("ig:private_info", "error",
                                    "Looks like a street address (customer privacy)."))

    for term in cfg.commercial_terms:
        if term.lower() in lowered:
            violations.append(Violation("ig:commercial", "error",
                                        f"Commercial content not allowed: {term!r}"))

    # Compare dollar amounts by numeric value so decimals/thousands/trailing
    # punctuation don't create false matches or false positives.
    approved_values = {value for _, value, _ in _dollar_values(" ".join(cfg.approved_offers))}
    # Span-based phrase verification: each dollar occurrence must ITSELF sit
    # inside an approved-phrase occurrence. A neighboring approved phrase must
    # never vouch for a separate, unapproved use of the same amount.
    vouched_spans = _vouched_amount_spans(text, cfg)
    for tok, value, start in _dollar_values(text):
        if value not in approved_values:
            violations.append(Violation("ig:unapproved_offer", "error",
                                        f"Dollar amount {tok} is not an approved offer."))
            continue
        end = start + len(tok)
        if not any(s <= start and end <= e for s, e in vouched_spans):
            violations.append(Violation("ig:unapproved_offer_phrase", "error",
                                        f"{tok} used outside an approved offer phrasing."))

    return RuleReport(passed=len(violations) == 0, violations=violations)


def check_instagram_draft(caption: str, cta: str, cfg: InstagramConfig) -> RuleReport:
    report = check_instagram_caption(caption, cfg)
    if cta not in cfg.approved_cta:
        report.violations.append(Violation("ig:cta", "error", f"CTA not in approved list: {cta!r}"))
    return RuleReport(passed=len(report.violations) == 0, violations=report.violations)
