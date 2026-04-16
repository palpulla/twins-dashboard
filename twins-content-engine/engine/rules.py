"""Pure-Python rules engine for generated content validation."""
from __future__ import annotations

import re
from dataclasses import dataclass, field
from typing import Any, Literal, Optional

from engine.config import BrandConfig, RulesConfig, ServiceAreaConfig


Severity = Literal["error", "warning"]


@dataclass
class Violation:
    rule_id: str
    severity: Severity
    message: str
    span: Optional[tuple[int, int]] = None


@dataclass
class RuleReport:
    passed: bool
    violations: list[Violation] = field(default_factory=list)
    suggestions: list[str] = field(default_factory=list)


# ---------- regex helpers ----------

_NUMBER_RE = re.compile(r"\b\d+(?:[-.,]\d+)?\b|\$\d+")
_TIMEFRAME_RE = re.compile(
    r"\b(same[- ]day|within \d+ hours?|today|tomorrow|this week|before winter|"
    r"in under \d+|\d+\s*hours?|\d+\s*minutes?)\b",
    re.IGNORECASE,
)
_PRICE_RANGE_RE = re.compile(r"\$\d[\d,]*\s*[–-]\s*\$?\d[\d,]*")
_PHONE_RE = re.compile(r"\(\d{3}\)\s*\d{3}[-.]\d{4}|\b\d{3}[-.]\d{3}[-.]\d{4}\b")
_SENTENCE_SPLIT = re.compile(r"(?<=[.!?])\s+")


def _first_n_words(text: str, n: int) -> str:
    words = text.strip().split()
    return " ".join(words[:n])


def _count_occurrences(text: str, needle: str) -> int:
    return len(re.findall(re.escape(needle), text, flags=re.IGNORECASE))


def _contains_any_town(text: str, service_area: ServiceAreaConfig) -> bool:
    names = [service_area.town_display_names[s] for s in service_area.towns]
    lowered = text.lower()
    return any(n.lower() in lowered for n in names)


def _town_mention_count(text: str, service_area: ServiceAreaConfig) -> int:
    total = 0
    for slug in service_area.towns:
        display = service_area.town_display_names[slug]
        total += _count_occurrences(text, display)
    return total


def _town_mention_positions(text: str, service_area: ServiceAreaConfig) -> list[int]:
    positions: list[int] = []
    lowered = text.lower()
    for slug in service_area.towns:
        display = service_area.town_display_names[slug].lower()
        start = 0
        while True:
            idx = lowered.find(display, start)
            if idx == -1:
                break
            positions.append(idx)
            start = idx + len(display)
    return sorted(positions)


def _consecutive_sentence_mentions(text: str, service_area: ServiceAreaConfig) -> bool:
    sentences = _SENTENCE_SPLIT.split(text)
    prev = False
    for s in sentences:
        has = _contains_any_town(s, service_area)
        if has and prev:
            return True
        prev = has
    return False


# ---------- individual rule checks ----------

def _check_blacklist_phrases(text: str, rules: RulesConfig) -> list[Violation]:
    violations: list[Violation] = []
    lowered = text.lower()
    for phrase in rules.anti_ai_spam_blacklist.phrases:
        if phrase.lower() in lowered:
            violations.append(
                Violation(
                    rule_id="anti_ai_spam:phrase",
                    severity="error",
                    message=f"Contains blacklisted phrase: {phrase!r}",
                )
            )
    return violations


def _check_blacklist_structural(text: str, rules: RulesConfig) -> list[Violation]:
    violations: list[Violation] = []
    structural = set(rules.anti_ai_spam_blacklist.structural)
    if "em_dash_pair_as_dramatic_pause" in structural:
        # pattern: "— X —" where X is a word or short phrase between em dashes
        if re.search(r"—[^—\n]{1,80}—", text):
            violations.append(Violation(
                rule_id="anti_ai_spam:structural_em_dash_pair",
                severity="error",
                message="Dramatic em-dash pair detected (AI tell).",
            ))
    if "formulaic_triadic_list" in structural:
        # pattern: "X, Y, Z." or "X. Y. Z." with short adjective triples at end of a clause
        if re.search(
            r"\b([A-Z][a-z]{2,12})[,.]\s+([A-Z][a-z]{2,12})[,.]\s+([A-Z][a-z]{2,12})\.",
            text,
        ):
            violations.append(Violation(
                rule_id="anti_ai_spam:structural_triadic_list",
                severity="warning",
                message="Formulaic three-adjective list detected (AI tell).",
            ))
    return violations


def _check_specificity(text: str, rules: RulesConfig,
                       service_area: ServiceAreaConfig) -> list[Violation]:
    violations: list[Violation] = []
    markers = rules.specificity_requirements.must_include_one_of
    found_marker = False
    for marker in markers:
        if marker == "specific_number" and _NUMBER_RE.search(text):
            found_marker = True
            break
        if marker == "named_location":
            if _contains_any_town(text, service_area):
                found_marker = True
                break
            if service_area.madison_neighborhoods:
                if any(n.lower() in text.lower() for n in service_area.madison_neighborhoods):
                    found_marker = True
                    break
        if marker == "timeframe" and _TIMEFRAME_RE.search(text):
            found_marker = True
            break
        if marker == "concrete_detail":
            # heuristic: any all-caps product-like token or spec with units
            if re.search(r"\b[A-Z]{2,}\w*\b", text) or re.search(r"\b\d+\s*(lbs?|ft|inches|in|years?|cycles?)\b", text, re.IGNORECASE):
                found_marker = True
                break
    if not found_marker:
        violations.append(Violation(
            rule_id="specificity:required_marker",
            severity="error",
            message=f"Content lacks any required specificity marker: {markers}",
        ))
    for bad in rules.specificity_requirements.must_avoid_phrases:
        if bad.lower() in text.lower():
            violations.append(Violation(
                rule_id="specificity:forbidden_phrase",
                severity="error",
                message=f"Contains forbidden generic claim: {bad!r}",
            ))
    return violations


def _check_hook_video_script(content: str, hook_cfg: dict[str, Any]) -> list[Violation]:
    violations: list[Violation] = []
    hook_line = _extract_video_hook_line(content)
    if not hook_line:
        return [Violation(
            rule_id="hook:video_script_missing",
            severity="error",
            message="video_script is missing a HOOK (0:00-0:02) block.",
        )]
    forbidden = [f.lower() for f in hook_cfg.get("forbidden_openers", [])]
    hook_lower = hook_line.strip().lower()
    for f in forbidden:
        if hook_lower.startswith(f):
            violations.append(Violation(
                rule_id="hook:forbidden_opener",
                severity="error",
                message=f"Hook starts with forbidden opener {f!r}.",
            ))
            break
    word_count = len(hook_line.split())
    lo = int(hook_cfg.get("min_words", 6))
    hi = int(hook_cfg.get("max_words", 12))
    if not (lo <= word_count <= hi):
        violations.append(Violation(
            rule_id="hook:video_script_length",
            severity="warning",
            message=f"Hook is {word_count} words; expected {lo}-{hi}.",
        ))
    return violations


def _extract_video_hook_line(content: str) -> Optional[str]:
    m = re.search(r"HOOK\s*\([^)]*\)\s*:\s*(.+)", content)
    if not m:
        return None
    line = m.group(1).strip()
    # If the hook spans multiple lines, take just the first non-empty line
    return line.split("\n")[0].strip()


def _check_hook_gbp(content: str, service_area: ServiceAreaConfig,
                   first_n: int) -> list[Violation]:
    prefix = _first_n_words(content, first_n)
    if not _contains_any_town(prefix, service_area):
        return [Violation(
            rule_id="hook:gbp_town",
            severity="error",
            message=f"First {first_n} words of gbp_post must contain a service-area town name.",
        )]
    return []


def _check_blog_first_sentence(content: str, req: list[str]) -> list[Violation]:
    first_sentence = _SENTENCE_SPLIT.split(content.strip())[0]
    for r in req:
        if r == "specific_number" and _NUMBER_RE.search(first_sentence):
            return []
        if r == "timeframe" and _TIMEFRAME_RE.search(first_sentence):
            return []
        if r == "price_range" and _PRICE_RANGE_RE.search(first_sentence):
            return []
    return [Violation(
        rule_id="hook:blog_first_sentence",
        severity="error",
        message=f"First sentence must contain one of: {req}",
    )]


def _check_cta(content: str, funnel_stage: str, rules: RulesConfig) -> list[Violation]:
    violations: list[Violation] = []
    if rules.cta_rules.one_per_piece:
        phone_count = len(_PHONE_RE.findall(content))
        if phone_count > 1:
            violations.append(Violation(
                rule_id="cta:multiple",
                severity="error",
                message=f"Found {phone_count} phone CTAs; only one allowed.",
            ))
    mapping = rules.cta_rules.funnel_stage_mapping
    expected = mapping.get(funnel_stage)
    if expected == "call_now":
        if not _PHONE_RE.search(content):
            violations.append(Violation(
                rule_id="cta:call_now_missing_phone",
                severity="error",
                message="high-intent content must include a phone-number CTA.",
            ))
    return violations


def _check_local_embedding(content: str, content_format: str, rules: RulesConfig,
                           service_area: ServiceAreaConfig) -> list[Violation]:
    violations: list[Violation] = []
    freq = rules.local_embedding.mention_frequency.get(content_format)
    if not freq:
        return violations
    lo, hi = freq[0], freq[1]
    count = _town_mention_count(content, service_area)
    if count < lo or count > hi:
        violations.append(Violation(
            rule_id="local_embedding:frequency",
            severity="error",
            message=f"{content_format} has {count} town mentions; expected {lo}-{hi}.",
        ))
    if rules.local_embedding.placement_rules.no_consecutive_sentences:
        if _consecutive_sentence_mentions(content, service_area):
            violations.append(Violation(
                rule_id="local_embedding:consecutive_sentences",
                severity="warning",
                message="Consecutive sentences both mention a town (sounds spammy).",
            ))
    pct = rules.local_embedding.placement_rules.require_mention_in_first_pct
    if count > 0:
        positions = _town_mention_positions(content, service_area)
        if positions:
            threshold = int(len(content) * (pct / 100))
            if positions[0] > threshold:
                violations.append(Violation(
                    rule_id="local_embedding:late_first_mention",
                    severity="warning",
                    message=f"First town mention appears after the first {pct}% of content.",
                ))
    return violations


# ---------- top-level ----------

def run_rules(
    *,
    content_format: str,
    content: str,
    funnel_stage: str,
    ctx: dict[str, Any],
) -> RuleReport:
    """Run every applicable rule. Return RuleReport with passed=False if any
    error-severity violation was found."""
    service_area: ServiceAreaConfig = ctx["service_area"]
    rules: RulesConfig = ctx["rules"]

    violations: list[Violation] = []
    violations += _check_blacklist_phrases(content, rules)
    violations += _check_blacklist_structural(content, rules)
    violations += _check_specificity(content, rules, service_area)
    violations += _check_cta(content, funnel_stage, rules)
    violations += _check_local_embedding(content, content_format, rules, service_area)

    hook_reqs = rules.hook_requirements or {}
    if content_format == "video_script" and "video_script" in hook_reqs:
        violations += _check_hook_video_script(content, hook_reqs["video_script"])
    elif content_format == "gbp_post" and "gbp_post" in hook_reqs:
        first_n = int(hook_reqs["gbp_post"].get("first_n_words", 10))
        violations += _check_hook_gbp(content, service_area, first_n)
    elif content_format == "blog_snippet" and "blog_snippet" in hook_reqs:
        req = hook_reqs["blog_snippet"].get("first_sentence_must_contain_one_of", [])
        violations += _check_blog_first_sentence(content, req)

    errors = [v for v in violations if v.severity == "error"]
    suggestions = _suggestions_from_violations(violations)
    return RuleReport(passed=not errors, violations=violations, suggestions=suggestions)


def _suggestions_from_violations(violations: list[Violation]) -> list[str]:
    """Produce human-readable critique lines for LLM regeneration prompts."""
    out: list[str] = []
    for v in violations:
        out.append(f"- [{v.rule_id}] {v.message}")
    return out
